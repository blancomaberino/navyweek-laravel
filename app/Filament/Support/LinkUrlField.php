<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Domain\Navigation\Support\LinkUrl;
use Closure;

/**
 * The write-side half of the editable-link URL policy, shared by every Filament form
 * where an editor types a raw href (menu items, CMS body blocks).
 *
 * Repo policy is defense in depth: the render layer neutralizes a disallowed scheme via
 * {@see LinkUrl::sanitize()}, and the form REJECTS it, so a bad value is caught where
 * the editor can see it rather than silently becoming `#` on the page. Both halves read
 * the same {@see LinkUrl::ALLOWED_SCHEMES} allowlist and so cannot drift.
 */
class LinkUrlField
{
    /**
     * A validation rule for a field holding an editor-supplied href.
     *
     * @return Closure(): Closure
     */
    public static function rule(): Closure
    {
        return static function (): Closure {
            return static function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && $value !== '' && ! LinkUrl::isAllowed($value)) {
                    $fail('Enter a site path (starting with /) or a URL using '
                        .implode(', ', LinkUrl::ALLOWED_SCHEMES).'.');
                }
            };
        };
    }

    /** The matching helper text, so every link field explains the same rule. */
    public static function helperText(): string
    {
        return 'Root-relative path (e.g. /schedule/) or an absolute '
            .implode('/', LinkUrl::ALLOWED_SCHEMES).' URL.';
    }
}
