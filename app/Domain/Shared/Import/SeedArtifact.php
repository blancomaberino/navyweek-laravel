<?php

declare(strict_types=1);

namespace App\Domain\Shared\Import;

use JsonException;
use RuntimeException;

/**
 * Stage-B reader for the JSON artifacts emitted by the Stage-A exporter
 * (database/export/*.ts → database/seed-data/*.json). Centralizes artifact
 * loading so every domain importer reads the handoff contract the same way.
 */
final class SeedArtifact
{
    /**
     * Read a seed artifact by name (without the `.json`) as an array of rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function read(string $name): array
    {
        // Safe-by-construction: the artifact name is a bare slug, never a path.
        // Guards the reusable reader against a future caller passing non-literal
        // input that could `../`-escape the seed-data directory.
        if (! preg_match('/^[a-z0-9-]+$/', $name)) {
            throw new RuntimeException("Invalid seed artifact name: {$name}");
        }

        $path = database_path("seed-data/{$name}.json");

        if (! is_file($path)) {
            throw new RuntimeException("Missing seed artifact: {$name}.json (run `npm run export:legacy`).");
        }

        $contents = (string) file_get_contents($path);

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Malformed seed artifact: {$name}.json — {$e->getMessage()}", 0, $e);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException("Seed artifact {$name}.json is not a list of rows.");
        }

        /** @var array<int, array<string, mixed>> $decoded */
        return $decoded;
    }

    /**
     * Read one text corpus file (a research brief) from
     * database/seed-data/<dir>/<slug>.md. Both segments are bare kebab slugs, so
     * no caller input can `../`-escape the seed-data directory. Returns null when
     * the file is absent.
     */
    public static function readText(string $dir, string $slug): ?string
    {
        if (! preg_match('/^[a-z0-9-]+$/', $dir) || ! preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new RuntimeException("Invalid seed corpus path: {$dir}/{$slug}");
        }

        $path = database_path("seed-data/{$dir}/{$slug}.md");

        return is_file($path) ? (string) file_get_contents($path) : null;
    }
}
