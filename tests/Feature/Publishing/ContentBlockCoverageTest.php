<?php

declare(strict_types=1);

use App\Domain\Publishing\Content\InlineSpans;
use App\Filament\Resources\Pages\Schemas\ContentBlocks;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;

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

/** A Select's options for one block field, by name, wherever it is nested. */
function blockFieldOptions(string $type, string $field): array
{
    $found = [];

    $collect = function (array $components) use (&$collect, &$found, $field): void {
        foreach ($components as $component) {
            if ($component instanceof Select && $component->getName() === $field) {
                $found = (new ReflectionProperty(Select::class, 'options'))->getValue($component) ?? [];
            }

            if (method_exists($component, 'getDefaultChildComponents')) {
                $collect($component->getDefaultChildComponents());
            }
        }
    };

    $collect(builderBlocks()[$type]->getDefaultChildComponents());

    return is_array($found) ? $found : [];
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

    // `paragraph` is the @default arm rather than a @case, so it is never captured
    // here; every other type the view can render must have a block.
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

    // Pinned, not merely non-empty: a NEW mount point must be added here deliberately
    // AND have its pages carry the trait, or this test goes red. A guard that cannot
    // fail is worse than none.
    expect($mounts)->toBe(['PageForm.php']);

    foreach (['CreatePage', 'EditPage'] as $page) {
        $source = (string) file_get_contents(app_path("Filament/Resources/Pages/Pages/{$page}.php"));

        if (! str_contains($source, 'TranslatesBodyBlocks')) {
            $untranslated[] = $page;
        }
    }

    // The hydrate half is an EditRecord-only hook (CreateRecord does not declare it),
    // so it lives on EditPage rather than the shared trait — assert it is actually there.
    expect((string) file_get_contents(app_path('Filament/Resources/Pages/Pages/EditPage.php')))
        ->toContain('mutateFormDataBeforeFill');

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

it('offers every value the corpus stores for a closed-set field', function (): void {
    // The form REJECTS a value outside a Select's options, so an incomplete option list
    // does not merely hide a choice — it makes the page unsaveable. Four lists were
    // invented rather than read from the data (band.tone/layout, table align), and the
    // single-fixture save test could not see it.
    $closed = [
        'band.tone' => 'tone',
        'band.layout' => 'layout',
        'paragraph.variant' => 'variant',
        'paragraph.slot' => 'slot',
        'callout.variant' => 'variant',
        'table.variant' => 'variant',
        'link_card.icon' => 'icon',
        'heading.level' => 'level',
    ];

    $stored = [];

    $walk = function (mixed $node, string $type) use (&$walk, &$stored, $closed): void {
        if (! is_array($node)) {
            return;
        }

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $walk($value, $type);

                continue;
            }

            if (isset($closed["{$type}.{$key}"])) {
                $stored["{$type}.{$key}"][] = $value;
            }
        }
    };

    foreach (bodyBlockCorpus() as $blocks) {
        foreach ($blocks as $block) {
            $walk($block, $block['type'] ?? 'paragraph');
        }
    }

    expect($stored)->not->toBeEmpty();

    $unofferable = [];

    foreach ($stored as $path => $values) {
        [$type, $field] = explode('.', $path);
        $options = blockFieldOptions($type, $field);

        foreach (array_unique($values) as $value) {
            if (! array_key_exists($value, $options)) {
                $unofferable[] = "{$path}={$value}";
            }
        }
    }

    expect(array_values(array_unique($unofferable)))->toBe(
        [],
        'Stored values no CMS option offers (the page cannot be saved): '.implode(', ', array_unique($unofferable)),
    );
});

it('never lets the editor silently destroy a stored mark', function (): void {
    // TipTap drops any mark it does not know (a bare <span> emphasis), so a mark that
    // is NOT in EDITABLE_MARKS must be handled by locking the block, never by routing
    // it through the editor. If a mark is added to TAGS without a decision here, this
    // fails.
    $unhandled = array_diff(array_keys(InlineSpans::TAGS), [...InlineSpans::EDITABLE_MARKS, 'emphasis']);

    expect($unhandled)->toBe(
        [],
        'Marks with no editor round-trip and no lock decision: '.implode(', ', $unhandled),
    );

    // And the lock actually triggers on the shape the corpus stores.
    expect(InlineSpans::hasUneditableMark([['text' => 'x', 'emphasis' => true]]))->toBeTrue()
        ->and(InlineSpans::hasUneditableMark([['text' => 'x', 'bold' => true]]))->toBeFalse();
});

it('restricts every prose toolbar to marks the stored format can carry', function (): void {
    // A strikethrough or heading button would let an editor produce formatting `spans`
    // cannot carry, so it would silently vanish on save. The allowed set is DERIVED
    // from the round-trippable marks (InlineSpans::TAGS) plus link and the history
    // buttons — not hand-copied, so adding a mark to the model updates this too.
    // Derived from the marks the editor can actually carry — `emphasis` is in TAGS but
    // TipTap cannot round-trip it, so it must never appear on a toolbar.
    $allowed = [...InlineSpans::EDITABLE_MARKS, 'link', 'undo', 'redo'];

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
