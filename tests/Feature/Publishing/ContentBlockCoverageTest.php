<?php

declare(strict_types=1);

use App\Domain\Publishing\Content\InlineSpans;
use App\Filament\Resources\Pages\Schemas\ContentBlocks;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\RichEditor;

/**
 * Fail-closed coverage between the renderer's block vocabulary, the stored corpus and
 * the CMS form. A block type the site can render but the editor cannot express is the
 * exact defect this slice fixes; these two tests make it a red suite instead of a
 * silently unmanageable page.
 */
/** The Builder's blocks, built once — each call rebuilds ~88 Filament components. */
function builderBlocks(): array
{
    static $blocks = null;

    return $blocks ??= collect(ContentBlocks::blocks())
        ->keyBy(static fn (Block $block): string => $block->getName())
        ->all();
}

function builderBlockNames(): array
{
    return array_keys(builderBlocks());
}

/** Every field name a block's schema binds to, including nested repeater children. */
function builderFieldNames(string $type): array
{
    static $cache = [];

    if (isset($cache[$type])) {
        return $cache[$type];
    }

    $block = builderBlocks()[$type] ?? null;

    expect($block)->not->toBeNull("No CMS block models the `{$type}` block type.");

    $names = [];

    $collect = function (array $components) use (&$collect, &$names): void {
        foreach ($components as $component) {
            $name = method_exists($component, 'getName') ? $component->getName() : null;

            if (is_string($name) && $name !== '') {
                // `cta.label` binds the `cta` map.
                $names[] = str_contains($name, '.') ? strtok($name, '.') : $name;
            }

            if (method_exists($component, 'getDefaultChildComponents')) {
                $collect($component->getDefaultChildComponents());
            }
        }
    };

    $collect($block->getDefaultChildComponents());

    return $cache[$type] = array_unique($names);
}

/** Every RichEditor in the Builder tree, wherever it is nested. */
function builderRichEditors(): array
{
    $editors = [];

    $collect = function (array $components) use (&$collect, &$editors): void {
        foreach ($components as $component) {
            if ($component instanceof RichEditor) {
                $editors[] = $component;
            }

            if (method_exists($component, 'getDefaultChildComponents')) {
                $collect($component->getDefaultChildComponents());
            }
        }
    };

    foreach (builderBlocks() as $block) {
        $collect($block->getDefaultChildComponents());
    }

    return $editors;
}

it('models every block type the renderer can display', function (): void {
    $blade = (string) file_get_contents(resource_path('views/pages/content.blade.php'));

    // `[a-z0-9_-]` not `[a-z_]`: a type spelled with a digit or hyphen would not match
    // a narrower class, so it would be silently absent from $rendered and the diff
    // below would come back clean — a guard that skips what it cannot parse fails OPEN.
    preg_match_all("/@case\('([a-z0-9_-]+)'\)/", $blade, $matches);

    $rendered = array_unique($matches[1]);

    // Guard the guard: every `@case(` in the file must have been captured.
    expect(count($matches[0]))->toBe(
        substr_count($blade, '@case('),
        'The block-type regex did not capture every @case arm in the view.',
    );

    expect($rendered)->not->toBeEmpty();

    // `list_item` runs are folded into lists by the view, and `paragraph` is the
    // @default arm — both are modelled, they just are not spelled as @case arms.
    $missing = array_diff($rendered, builderBlockNames());

    expect($missing)->toBe(
        [],
        'content.blade.php renders block types the CMS cannot edit: '.implode(', ', $missing),
    );
});

it('binds a field to every key the live corpus stores', function (): void {
    // `spans` is edited as `content` (rich HTML) and `type` is the block identity —
    // neither is a form field of its own.
    $translated = ['type', 'spans'];
    $unbound = [];

    foreach (bodyBlockCorpus() as $blocks) {
        foreach ($blocks as $block) {
            $type = $block['type'] ?? 'paragraph';
            $fields = builderFieldNames($type);

            foreach (array_keys($block) as $key) {
                if (in_array($key, $translated, true) || in_array($key, $fields, true)) {
                    continue;
                }

                // A `text`-shaped paragraph is edited through the same rich field.
                if ($type === 'paragraph' && $key === 'text') {
                    continue;
                }

                $unbound[] = $type.'.'.$key;
            }
        }
    }

    expect(array_values(array_unique($unbound)))->toBe(
        [],
        'Stored keys no CMS field binds to (they would be lost on save): '.implode(', ', array_unique($unbound)),
    );
});

it('reads every body_blocks consumer through the shared prose helper', function (): void {
    // A view that indexes `['text']` directly only sees a PLAIN paragraph: the moment
    // an editor bolds a word the block graduates to `spans` and that view silently
    // falls back to nothing. Both base hubs shipped exactly that bug. Every consumer
    // must go through InlineSpans, which reads either shape.
    $offenders = [];

    // Recursive: `glob('views/**/*.blade.php')` only descends ONE level, so a view
    // nested any deeper would escape the check entirely.
    $views = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS),
    );

    $scanned = 0;

    foreach ($views as $blade) {
        if (! str_ends_with((string) $blade, '.blade.php')) {
            continue;
        }

        $source = (string) file_get_contents((string) $blade);

        if (! str_contains($source, 'body_blocks')) {
            continue;
        }

        $scanned++;

        if (preg_match("/body_blocks\[[^]]*\]\['(text|spans)'\]/", $source) === 1) {
            $offenders[] = basename((string) $blade);
        }
    }

    // Guard the guard: the three known consumers must actually have been scanned.
    expect($scanned)->toBeGreaterThanOrEqual(3);

    expect($offenders)->toBe(
        [],
        'Views indexing a body block\'s prose key directly (use InlineSpans::plainText): '.implode(', ', $offenders),
    );
});

it('never stores a bare `cells` key, which the mapper reserves', function (): void {
    // BodyBlocks unwraps any single-key `{cells: [...]}` list item back to a bare list
    // (that is how a table's rows-of-cells survives the nested repeater). A block that
    // stored `cells` natively would therefore be flattened on the first save.
    $found = [];

    $walk = function (mixed $node) use (&$walk, &$found): void {
        if (! is_array($node)) {
            return;
        }

        foreach ($node as $key => $value) {
            if ($key === 'cells') {
                $found[] = $key;
            }

            $walk($value);
        }
    };

    foreach (bodyBlockCorpus() as $blocks) {
        $walk($blocks);
    }

    expect($found)->toBe([], '`cells` is reserved by BodyBlocks::decodeValue().');
});

it('translates the body on every page that mounts the editor', function (): void {
    // The Builder speaks editor HTML; the DB speaks `spans`. TranslatesBodyBlocks is
    // what converts between them, and it lives on the Filament PAGE, not on the
    // component — so a second resource (or an action modal) that mounts ContentBlocks
    // without the trait would write raw HTML straight into body_blocks.
    $mounts = [];
    $untranslated = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(app_path('Filament'), FilesystemIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if (! str_ends_with((string) $file, '.php')) {
            continue;
        }

        $source = (string) file_get_contents((string) $file);

        // The schema class that DEFINES the builder is not itself a mount point.
        if (! str_contains($source, 'ContentBlocks::make') || str_contains($source, 'class ContentBlocks')) {
            continue;
        }

        $mounts[] = basename((string) $file);
    }

    // PageForm mounts it; the Create/Edit pages behind that form carry the trait.
    expect($mounts)->not->toBeEmpty();

    foreach (['CreatePage', 'EditPage'] as $page) {
        $source = (string) file_get_contents(app_path("Filament/Resources/Pages/Pages/{$page}.php"));

        if (! str_contains($source, 'TranslatesBodyBlocks')) {
            $untranslated[] = $page;
        }
    }

    expect($untranslated)->toBe(
        [],
        'Filament pages mounting the body editor without TranslatesBodyBlocks: '.implode(', ', $untranslated),
    );
});

it('offers every paragraph variant the renderer styles', function (): void {
    // The blade's `$paragraphClass` match IS the list of treatments a paragraph can
    // take. One the form cannot select is a treatment no editor can ever apply — the
    // same class of gap as a missing block type, one level down.
    $blade = (string) file_get_contents(resource_path('views/pages/content.blade.php'));

    preg_match('/\$paragraphClass = .*?\};/s', $blade, $match);

    expect($match)->not->toBeEmpty('Could not find $paragraphClass in the content view.');

    preg_match_all("/'([a-z-]+)' =>/", $match[0], $arms);

    $styled = array_unique($arms[1]);

    expect($styled)->not->toBeEmpty();

    $missing = array_diff($styled, array_keys(ContentBlocks::PARAGRAPH_VARIANTS));

    expect($missing)->toBe(
        [],
        'Paragraph variants the renderer styles but the CMS cannot select: '.implode(', ', $missing),
    );
});

it('restricts every prose toolbar to marks the stored format can carry', function (): void {
    // A strikethrough or heading button would let an editor produce formatting `spans`
    // cannot carry, so it would silently vanish on save. The allowed set is DERIVED
    // from the round-trippable marks (InlineSpans::TAGS) plus link and the history
    // buttons — not hand-copied, so adding a mark to the model updates this too.
    $allowed = [...array_keys(InlineSpans::TAGS), 'link', 'undo', 'redo'];

    $editors = builderRichEditors();

    expect($editors)->not->toBeEmpty();

    $property = new ReflectionProperty(RichEditor::class, 'toolbarButtons');

    foreach ($editors as $editor) {
        $buttons = $property->getValue($editor);

        expect($buttons)->toBeArray()
            ->and(array_diff($buttons, $allowed))->toBe(
                [],
                'A prose editor offers formatting the stored `spans` cannot carry.',
            );
    }
});
