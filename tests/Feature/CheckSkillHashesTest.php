<?php

declare(strict_types=1);

use App\Domain\Research\Models\Skill;
use App\Domain\Research\Services\SkillHasher;
use Illuminate\Support\Facades\File;

/** Create a throwaway skills root and point config at it. */
function makeSkillsDir(): string
{
    $dir = sys_get_temp_dir().'/nw-skills-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($dir);
    config(['research.skills_path' => $dir]);

    return $dir;
}

function writeSkillFixture(string $base, string $key, string $body): void
{
    File::ensureDirectoryExists("{$base}/{$key}");
    File::put("{$base}/{$key}/SKILL.md", $body);
}

it('hashes content deterministically and returns null when absent', function () {
    $dir = makeSkillsDir();
    writeSkillFixture($dir, 's', 'alpha');
    $hasher = app(SkillHasher::class);

    $a = $hasher->hash("{$dir}/s");
    writeSkillFixture($dir, 's', 'alpha');   // identical rewrite
    $b = $hasher->hash("{$dir}/s");
    writeSkillFixture($dir, 's', 'beta');    // changed
    $c = $hasher->hash("{$dir}/s");

    expect($a)->toBe($b)
        ->and($c)->not->toBe($a)
        ->and($hasher->hash("{$dir}/missing"))->toBeNull();

    File::deleteDirectory($dir);
});

it('passes --check when the on-disk skill matches its stored hash', function () {
    $dir = makeSkillsDir();
    writeSkillFixture($dir, 'seo-geo', "# SEO / GEO\nrules");
    $hash = app(SkillHasher::class)->hash("{$dir}/seo-geo");
    Skill::create(['key' => 'seo-geo', 'name' => 'SEO / GEO', 'current_version' => '1.0.0', 'content_hash' => $hash]);

    $this->artisan('skills:check-hashes', ['--check' => true])->assertSuccessful();

    File::deleteDirectory($dir);
});

it('fails --check when a skill content has drifted from its stored hash', function () {
    $dir = makeSkillsDir();
    writeSkillFixture($dir, 'seo-geo', 'changed content');
    Skill::create(['key' => 'seo-geo', 'name' => 'SEO / GEO', 'current_version' => '1.0.0', 'content_hash' => 'stale-hash']);

    $this->artisan('skills:check-hashes', ['--check' => true])
        ->expectsOutputToContain('content changed')
        ->assertFailed();

    File::deleteDirectory($dir);
});

it('fails --check when a registered skill is missing on disk', function () {
    makeSkillsDir(); // empty root
    Skill::create(['key' => 'ghost', 'name' => 'Ghost', 'current_version' => '1.0.0', 'content_hash' => 'abc']);

    $this->artisan('skills:check-hashes', ['--check' => true])
        ->expectsOutputToContain('not found')
        ->assertFailed();
});

it('reports without failing when --check is absent', function () {
    $dir = makeSkillsDir();
    Skill::create(['key' => 'ghost', 'name' => 'Ghost', 'current_version' => '1.0.0', 'content_hash' => 'abc']);

    $this->artisan('skills:check-hashes')->assertSuccessful();

    File::deleteDirectory($dir);
});
