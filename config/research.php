<?php

declare(strict_types=1);

return [
    // Where the research/QA skills live on disk. The skills sit at the REPO ROOT
    // (`.claude/skills/<key>/`), one level above this Laravel app, so the hash
    // detector can compare a skill's on-disk content to `skills.content_hash`.
    'skills_path' => env('RESEARCH_SKILLS_PATH', base_path('../.claude/skills')),
];
