<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Connection;
use App\Domain\Crm\Repositories\ConnectionRepositoryInterface;
use App\Domain\Research\Actions\LaunchResearchAction;
use App\Domain\Research\Enums\ResearchedBy;
use App\Domain\Research\Enums\ResearchStatus;
use App\Domain\Research\Exceptions\ResearchAutomationDisabledException;
use App\Domain\Research\Jobs\LaunchResearchJob;
use App\Domain\Research\Models\Research;
use App\Domain\Research\Models\Skill;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

/** Register the two skills the run stamps provenance from. */
function seedResearchSkills(): void
{
    Skill::create(['key' => 'military-discount-research', 'name' => 'Military Discount Research', 'current_version' => '4']);
    Skill::create(['key' => 'seo-geo', 'name' => 'SEO / GEO', 'current_version' => '2']);
}

function enableAutomation(): void
{
    config(['research.automation.enabled' => true, 'research.automation.binary' => 'claude']);
}

it('throws and spawns nothing when automation is disabled', function () {
    config(['research.automation.enabled' => false]);
    Process::fake();
    seedResearchSkills();
    $connection = Connection::factory()->create();

    expect(fn () => app(LaunchResearchAction::class)($connection))
        ->toThrow(ResearchAutomationDisabledException::class);

    Process::assertNothingRan();
    expect(Research::query()->count())->toBe(0);
});

it('opens a provenance-stamped draft and stores the brief on a successful run', function () {
    enableAutomation();
    Process::fake(['*' => Process::result(output: "# Chewy Brief\n\nfacts…")]);
    seedResearchSkills();
    $connection = Connection::factory()->create(['brand' => 'Chewy', 'slug' => 'chewy']);

    $run = app(LaunchResearchAction::class)($connection);

    expect($run->status)->toBe(ResearchStatus::Draft)
        ->and($run->researched_by)->toBe(ResearchedBy::ClaudePipeline)
        ->and($run->version)->toBe(1)
        ->and($run->skill_key)->toBe('military-discount-research')
        ->and($run->skill_version)->toBe('4')            // stamped from the registry
        // Brief stored verbatim (the fake result adds a trailing newline like a real run).
        ->and(trim((string) $run->fresh()->raw_markdown))->toBe("# Chewy Brief\n\nfacts…");

    // Both skills recorded in the provenance pivot with their current versions.
    $pivot = $run->skills()->get()->keyBy('key');
    expect($pivot)->toHaveCount(2)
        ->and($pivot['military-discount-research']->pivot->skill_version)->toBe('4')
        ->and($pivot['military-discount-research']->pivot->used_for)->toBe('facts')
        ->and($pivot['seo-geo']->pivot->skill_version)->toBe('2')
        ->and($pivot['seo-geo']->pivot->used_for)->toBe('citability');
});

it('invokes the CLI with array args (no shell) and the dangerous flag', function () {
    enableAutomation();
    Process::fake(['*' => Process::result(output: 'ok')]);
    seedResearchSkills();
    // A brand with shell metacharacters must ride as a single literal argv entry.
    $connection = Connection::factory()->create(['brand' => 'Acme; rm -rf /', 'slug' => 'acme']);

    app(LaunchResearchAction::class)($connection);

    Process::assertRan(function ($process): bool {
        $cmd = $process->command;

        return is_array($cmd)                                  // array form → no shell interpolation
            && $cmd[0] === 'claude'
            && $cmd[1] === '-p'
            && in_array('--dangerously-skip-permissions', $cmd, true)
            && str_contains($cmd[2], 'Acme; rm -rf /')          // metacharacters carried literally in one arg
            && str_contains($cmd[2], 'slug: acme');
    });
});

it('bumps to the next version when the connection already has briefs', function () {
    enableAutomation();
    Process::fake(['*' => Process::result(output: 'v3')]);
    seedResearchSkills();
    $connection = Connection::factory()->create();
    Research::factory()->create(['connection_id' => $connection->id, 'version' => 2]);

    $run = app(LaunchResearchAction::class)($connection);

    expect($run->version)->toBe(3);
});

it('leaves the draft empty and throws when the process fails', function () {
    enableAutomation();
    Process::fake(['*' => Process::result(output: '', errorOutput: 'boom', exitCode: 1)]);
    seedResearchSkills();
    $connection = Connection::factory()->create();

    expect(fn () => app(LaunchResearchAction::class)($connection))->toThrow(RuntimeException::class);

    // Provenance draft persists (for retry/inspection) but has no brief.
    $run = Research::query()->firstOrFail();
    expect($run->status)->toBe(ResearchStatus::Draft)
        ->and($run->raw_markdown)->toBe('');
});

it('throws before spawning when the primary skill is not registered', function () {
    enableAutomation();
    Process::fake();
    // Only the secondary skill exists; the primary is missing.
    Skill::create(['key' => 'seo-geo', 'name' => 'SEO / GEO', 'current_version' => '2']);
    $connection = Connection::factory()->create();

    expect(fn () => app(LaunchResearchAction::class)($connection))->toThrow(RuntimeException::class);

    Process::assertNothingRan();
    expect(Research::query()->count())->toBe(0);
});

it('is dispatched onto the queue and resolves the connection through the repository', function () {
    Queue::fake();
    LaunchResearchJob::dispatch(42);
    Queue::assertPushed(LaunchResearchJob::class, fn (LaunchResearchJob $job): bool => $job->connectionId === 42);
});

it('runs the action from the job handle for a live connection', function () {
    enableAutomation();
    Process::fake(['*' => Process::result(output: '# From job')]);
    seedResearchSkills();
    $connection = Connection::factory()->create();

    (new LaunchResearchJob($connection->id))->handle(
        app(LaunchResearchAction::class),
        app(ConnectionRepositoryInterface::class),
    );

    expect(trim((string) Research::query()->where('connection_id', $connection->id)->first()?->raw_markdown))->toBe('# From job');
});

it('no-ops when the job runs for a deleted connection', function () {
    enableAutomation();
    Process::fake();

    (new LaunchResearchJob(999999))->handle(
        app(LaunchResearchAction::class),
        app(ConnectionRepositoryInterface::class),
    );

    Process::assertNothingRan();
    expect(Research::query()->count())->toBe(0);
});
