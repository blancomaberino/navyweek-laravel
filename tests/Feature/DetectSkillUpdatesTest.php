<?php

declare(strict_types=1);

use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use App\Domain\Research\Actions\DetectSkillUpdatesAction;
use App\Domain\Research\Models\Research;
use App\Domain\Research\Models\Skill;
use App\Domain\Research\Services\SkillHasher;
use Illuminate\Support\Facades\File;

/** A throwaway skills root, pointed at by config, cleaned up per test. */
function detectSkillsDir(): string
{
    $dir = sys_get_temp_dir().'/nw-detect-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($dir);
    config(['research.skills_path' => $dir]);

    return $dir;
}

function putSkillFile(string $base, string $key, string $body): void
{
    File::ensureDirectoryExists("{$base}/{$key}");
    File::put("{$base}/{$key}/SKILL.md", $body);
}

/** The hash the detector will compute for a skill dir under $base. */
function skillHashOf(string $base, string $key): string
{
    return (string) app(SkillHasher::class)->hash("{$base}/{$key}");
}

/**
 * A connection with a single latest Complete brief citing $skill at $pivotVersion.
 */
function connectionCiting(Skill $skill, string $pivotVersion, ConnectionStatus $status = ConnectionStatus::Published): Connection
{
    $connection = Connection::factory()->create(['status' => $status]);
    $research = Research::factory()->create(['connection_id' => $connection->id, 'version' => 1]);
    $research->skills()->attach($skill->id, ['skill_version' => $pivotVersion, 'used_for' => 'facts']);

    return $connection;
}

it('bumps a changed skill and flags connections whose latest brief used the old version', function () {
    $dir = detectSkillsDir();
    putSkillFile($dir, 'military-discount-research', 'v2 body');
    // Stored hash is stale (content differs on disk) → a real change.
    $skill = Skill::create([
        'key' => 'military-discount-research',
        'name' => 'Military Discount Research',
        'current_version' => '1',
        'content_hash' => 'stale-hash',
    ]);
    $connection = connectionCiting($skill, '1');

    $result = app(DetectSkillUpdatesAction::class)->execute();

    expect($result->bumped)->toBe(['military-discount-research'])
        ->and($result->connectionsFlagged)->toBe(1);

    expect($skill->fresh())
        ->current_version->toBe('2')
        ->content_hash->toBe(skillHashOf($dir, 'military-discount-research'));
    expect($connection->fresh()->status)->toBe(ConnectionStatus::NeedsReverify);

    File::deleteDirectory($dir);
});

it('records a first-time baseline without bumping the version or flagging', function () {
    $dir = detectSkillsDir();
    putSkillFile($dir, 'seo-geo', 'body');
    $skill = Skill::create(['key' => 'seo-geo', 'name' => 'SEO / GEO', 'current_version' => '1', 'content_hash' => null]);
    $connection = connectionCiting($skill, '1');

    $result = app(DetectSkillUpdatesAction::class)->execute();

    expect($result->baselined)->toBe(['seo-geo'])
        ->and($result->bumped)->toBe([])
        ->and($result->connectionsFlagged)->toBe(0);

    expect($skill->fresh())
        ->current_version->toBe('1')
        ->content_hash->toBe(skillHashOf($dir, 'seo-geo'));
    expect($connection->fresh()->status)->toBe(ConnectionStatus::Published);

    File::deleteDirectory($dir);
});

it('leaves an unchanged skill untouched (no bump, no flag)', function () {
    $dir = detectSkillsDir();
    putSkillFile($dir, 'seo-geo', 'stable body');
    $skill = Skill::create([
        'key' => 'seo-geo',
        'name' => 'SEO / GEO',
        'current_version' => '3',
        'content_hash' => skillHashOf($dir, 'seo-geo'),
    ]);
    $connection = connectionCiting($skill, '3');

    $result = app(DetectSkillUpdatesAction::class)->execute();

    expect($result->bumped)->toBe([])
        ->and($result->baselined)->toBe([])
        ->and($result->connectionsFlagged)->toBe(0);
    expect($skill->fresh()->current_version)->toBe('3');
    expect($connection->fresh()->status)->toBe(ConnectionStatus::Published);

    File::deleteDirectory($dir);
});

it('reports a skill missing on disk and never bumps it', function () {
    detectSkillsDir(); // empty root
    $skill = Skill::create(['key' => 'ghost', 'name' => 'Ghost', 'current_version' => '2', 'content_hash' => 'abc']);

    $result = app(DetectSkillUpdatesAction::class)->execute();

    expect($result->missing)->toBe(['ghost'])
        ->and($result->bumped)->toBe([]);
    expect($skill->fresh())
        ->current_version->toBe('2')
        ->content_hash->toBe('abc');
});

it('does not flag a connection whose LATEST brief already uses the current version', function () {
    $dir = detectSkillsDir();
    putSkillFile($dir, 'military-discount-research', 'new body');
    $skill = Skill::create([
        'key' => 'military-discount-research',
        'name' => 'Military Discount Research',
        'current_version' => '1',
        'content_hash' => 'stale-hash',
    ]);

    // Connection whose superseded v1 cited the old skill version, but whose LATEST v2
    // brief already cites version '2' — its live page is current, so it must NOT flag.
    $current = Connection::factory()->create(['status' => ConnectionStatus::Published]);
    $old = Research::factory()->superseded()->create(['connection_id' => $current->id, 'version' => 1]);
    $old->skills()->attach($skill->id, ['skill_version' => '1', 'used_for' => 'facts']);
    $latest = Research::factory()->create(['connection_id' => $current->id, 'version' => 2]);
    $latest->skills()->attach($skill->id, ['skill_version' => '2', 'used_for' => 'facts']);

    // A second connection whose latest still cites the old version → must flag.
    $stale = connectionCiting($skill, '1');

    $result = app(DetectSkillUpdatesAction::class)->execute();

    expect($result->connectionsFlagged)->toBe(1);
    expect($current->fresh()->status)->toBe(ConnectionStatus::Published);
    expect($stale->fresh()->status)->toBe(ConnectionStatus::NeedsReverify);

    File::deleteDirectory($dir);
});

it('never flags a non-active connection even when its latest brief is stale', function () {
    $dir = detectSkillsDir();
    putSkillFile($dir, 'military-discount-research', 'new body');
    $skill = Skill::create([
        'key' => 'military-discount-research',
        'name' => 'Military Discount Research',
        'current_version' => '1',
        'content_hash' => 'stale-hash',
    ]);
    $skipped = connectionCiting($skill, '1', ConnectionStatus::Skipped);

    $result = app(DetectSkillUpdatesAction::class)->execute();

    // The skill still bumps, but markNeedsReverify no-ops a non-active connection.
    expect($result->bumped)->toBe(['military-discount-research'])
        ->and($result->connectionsFlagged)->toBe(0);
    expect($skipped->fresh()->status)->toBe(ConnectionStatus::Skipped);

    File::deleteDirectory($dir);
});

it('drives the same result through the skills:detect-updates command', function () {
    $dir = detectSkillsDir();
    putSkillFile($dir, 'military-discount-research', 'changed');
    $skill = Skill::create([
        'key' => 'military-discount-research',
        'name' => 'Military Discount Research',
        'current_version' => '1',
        'content_hash' => 'stale-hash',
    ]);
    connectionCiting($skill, '1');

    $this->artisan('skills:detect-updates')
        ->expectsOutputToContain('version bumped')
        ->assertSuccessful();

    expect($skill->fresh()->current_version)->toBe('2');

    File::deleteDirectory($dir);
});
