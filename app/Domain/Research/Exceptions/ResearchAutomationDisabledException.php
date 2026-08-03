<?php

declare(strict_types=1);

namespace App\Domain\Research\Exceptions;

use RuntimeException;

/**
 * Thrown when a research run is requested but `research.automation.enabled` is false.
 * The headless run spawns the Claude CLI with `--dangerously-skip-permissions`, so it
 * only ever runs on a controlled host that has opted in explicitly — never by default.
 */
final class ResearchAutomationDisabledException extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'Research automation is disabled. Set RESEARCH_AUTOMATION_ENABLED=true on a '
            .'host with the Claude CLI + credentials installed to launch headless research.'
        );
    }
}
