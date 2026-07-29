<?php

declare(strict_types=1);

namespace App\Domain\Research\Services;

/**
 * Content-addresses a skill directory: a deterministic hash over its `SKILL.md`
 * plus `references/*.md`, so a change to the installed skill is detectable against
 * the stored `skills.content_hash`. Returns null when the directory has no skill
 * files (missing / not installed).
 */
final class SkillHasher
{
    public function hash(string $skillDir): ?string
    {
        $files = [];

        $skillMd = $skillDir.'/SKILL.md';
        if (is_file($skillMd)) {
            $files[] = $skillMd;
        }

        $referenceGlob = glob($skillDir.'/references/*.md');
        foreach ($referenceGlob !== false ? $referenceGlob : [] as $reference) {
            $files[] = $reference;
        }

        if ($files === []) {
            return null;
        }

        sort($files);

        $payload = '';
        foreach ($files as $file) {
            // Include the basename so a rename changes the hash even if content is identical.
            $payload .= basename($file)."\0".(string) file_get_contents($file)."\0";
        }

        return hash('sha256', $payload);
    }
}
