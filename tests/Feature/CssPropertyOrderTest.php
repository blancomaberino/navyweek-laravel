<?php

declare(strict_types=1);

use Illuminate\Support\Str;

/**
 * Fail-closed guard for the project rule: within every declaration block of our
 * AUTHORED CSS, properties must be listed in alphabetical order (see platform/CLAUDE.md).
 *
 * Scope = `resources/css/*.css` + every hand-written `<style>` block in a Blade view.
 * Machine-generated CSS (the compiled Tailwind dump in the stock welcome page) is out of
 * scope and skipped. Keep inline comments OUT of declaration blocks — the parser strips
 * comments before checking, so a mid-block comment can't "hide" an out-of-order property.
 */

/**
 * Parse a CSS string into its innermost declaration blocks (a `{ … }` with no nested
 * braces — so at-rule preludes like `@media (max-width: 720px)` and nesting are ignored).
 *
 * @return list<array{selector: string, properties: list<string>}>
 */
function cssDeclarationBlocks(string $css): array
{
    $css = (string) preg_replace('#/\*.*?\*/#s', '', $css); // strip comments

    $blocks = [];
    if (preg_match_all('/([^{}]*)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $properties = [];
            foreach (explode(';', $match[2]) as $declaration) {
                $declaration = trim($declaration);
                if ($declaration === '' || ! str_contains($declaration, ':')) {
                    continue;
                }
                $properties[] = strtolower(trim(Str::before($declaration, ':')));
            }

            if ($properties !== []) {
                $blocks[] = [
                    'selector' => trim((string) preg_replace('/\s+/', ' ', $match[1])),
                    'properties' => $properties,
                ];
            }
        }
    }

    return $blocks;
}

/**
 * Every authored CSS source: standalone stylesheets + hand-written Blade `<style>` blocks
 * (compiled Tailwind dumps excluded).
 *
 * @return array<string, string> label => css
 */
function authoredCssSources(): array
{
    $sources = [];

    // Recursive on purpose: the per-family sheets live in css/families/ and are the
    // bulk of the authored CSS. A non-recursive glob left ~10,000 lines outside the
    // guard — green while never inspecting the code it exists to protect.
    foreach (glob(resource_path('css/{,*/}*.css'), GLOB_BRACE) ?: [] as $file) {
        $sources[str_replace(base_path().'/', '', $file)] = (string) file_get_contents($file);
    }

    $views = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS)
    );
    foreach ($views as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }
        $contents = (string) file_get_contents($file->getPathname());
        if (! preg_match_all('#<style[^>]*>(.*?)</style>#s', $contents, $styleMatches)) {
            continue;
        }
        $relative = str_replace(base_path().'/', '', $file->getPathname());
        foreach ($styleMatches[1] as $index => $style) {
            if (str_contains($style, 'tailwindcss')) {
                continue; // compiled Tailwind output, not authored CSS
            }
            $sources[$relative.' <style#'.$index.'>'] = $style;
        }
    }

    return $sources;
}

it('lists CSS properties alphabetically in every authored declaration block', function () {
    $violations = [];
    $blocksChecked = 0;

    foreach (authoredCssSources() as $label => $css) {
        foreach (cssDeclarationBlocks($css) as $block) {
            $blocksChecked++;
            $sorted = $block['properties'];
            sort($sorted, SORT_STRING);

            if ($sorted !== $block['properties']) {
                $violations[] = sprintf(
                    '%s { %s }: got [%s], expected [%s]',
                    $label,
                    Str::limit($block['selector'], 60),
                    implode(', ', $block['properties']),
                    implode(', ', $sorted),
                );
            }
        }
    }

    // Sanity: the parser actually found blocks (a silent zero would make this vacuous).
    expect($blocksChecked)->toBeGreaterThan(20);
    expect($violations)->toBe([]);
});
