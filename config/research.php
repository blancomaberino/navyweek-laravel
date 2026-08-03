<?php

declare(strict_types=1);

return [
    // Where the research/QA skills live on disk. The skills sit at the REPO ROOT
    // (`.claude/skills/<key>/`), one level above this Laravel app, so the hash
    // detector can compare a skill's on-disk content to `skills.content_hash`.
    'skills_path' => env('RESEARCH_SKILLS_PATH', base_path('../.claude/skills')),

    // Headless research automation: LaunchResearchJob runs the `military-discount-research`
    // skill via the Claude CLI (`claude -p … --dangerously-skip-permissions`).
    'automation' => [
        // Master switch — OFF by default. Merging this feature must NOT enable live
        // process spawning anywhere; a controlled host with the CLI + credentials
        // installed sets RESEARCH_AUTOMATION_ENABLED=true explicitly. When false,
        // LaunchResearchAction throws before spawning anything.
        'enabled' => (bool) env('RESEARCH_AUTOMATION_ENABLED', false),

        // The CLI binary, invoked with ARRAY args (never a shell string), so the
        // connection slug/brand can never break out into shell interpolation.
        'binary' => env('RESEARCH_CLAUDE_BINARY', 'claude'),

        // cwd for the run — the repo root (one level above this app) where the skill
        // and runbooks live.
        'working_directory' => env('RESEARCH_WORKING_DIRECTORY', base_path('..')),

        // Per-run wall-clock cap (seconds) — a runaway headless run is killed.
        'timeout' => (int) env('RESEARCH_TIMEOUT', 1800),

        // The skills the run uses, in provenance order (the FIRST is primary and is
        // stamped on `research.skill_key`/`skill_version`; all are written to the
        // `research_skill` pivot with `used_for`). Keys must exist in the `skills`
        // registry so the current version can be stamped.
        'skills' => [
            'military-discount-research' => 'facts',
            'seo-geo' => 'citability',
        ],
    ],
];
