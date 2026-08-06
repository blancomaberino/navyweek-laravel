<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Schemas;

use App\Domain\Publishing\Content\BodyBlocks;
use App\Filament\Support\LinkUrlField;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

/**
 * The CMS body editor: one Builder block per block type
 * `resources/views/pages/content.blade.php` can render.
 *
 * The blade is the authority on this vocabulary — every `@case` in its block switch
 * must have a Block here with fields covering the keys that case reads, or the editor
 * silently cannot express content the site displays.
 * `tests/Feature/Publishing/ContentBlockCoverageTest.php` enforces both halves of that
 * (a renderer case with no block, and a stored key no field binds to) fail-closed, so a
 * new block type in the blade breaks the suite until it is added here.
 *
 * Prose fields bind to `content` and speak HTML; {@see BodyBlocks}
 * converts them to and from the stored `spans` runs around every load and save.
 */
class ContentBlocks
{
    /** Inline formatting the stored `spans` vocabulary can express. */
    public const TOOLBAR = ['bold', 'italic', 'link', 'undo', 'redo'];

    /**
     * Paragraph treatments the legacy pages promote an intro line to. Public because
     * `ContentBlockCoverageTest` checks it against the blade's `$paragraphClass` match —
     * a variant the renderer styles but the form cannot select is unreachable content.
     */
    public const PARAGRAPH_VARIANTS = [
        'capsule' => 'Capsule (boxed intro)',
        'fine' => 'Fine print',
        'lead' => 'Lead (large)',
        'op-lead' => 'Our Process — lead',
        'op-reviewer' => 'Our Process — reviewer',
        'panel' => 'Panel',
        'stamp' => 'Stamp (last-updated line)',
        'sublead' => 'Sub-lead',
        'verified' => 'Verified stamp',
    ];

    public static function make(string $name): Builder
    {
        return Builder::make($name)
            ->hiddenLabel()
            ->addActionLabel('Add block')
            ->collapsible()
            ->collapsed()
            ->blockNumbers(false)
            ->blocks(self::blocks());
    }

    /**
     * @return list<Block>
     */
    public static function blocks(): array
    {
        return [
            Block::make('paragraph')
                ->icon('heroicon-o-bars-3-bottom-left')
                ->schema([
                    self::prose('content')->label('Text'),
                    Select::make('variant')
                        ->options(self::PARAGRAPH_VARIANTS)
                        ->placeholder('Body copy')
                        ->helperText('A page-specific treatment; leave empty for normal body copy.'),
                    Select::make('slot')
                        ->options(['hero' => 'Hero (above the byline)'])
                        ->placeholder('Body'),
                    // Whether this paragraph was stored as plain `text` or as rich
                    // `spans` — restored on save, see BodyBlocks::dehydrate().
                    Hidden::make('shape'),
                ]),

            Block::make('heading')
                ->icon('heroicon-o-hashtag')
                ->schema([
                    TextInput::make('text')->required()->columnSpanFull(),
                    Select::make('level')
                        ->options([2 => 'H2 — section', 3 => 'H3 — sub-section'])
                        ->default(2),
                ]),

            Block::make('list')
                ->icon('heroicon-o-list-bullet')
                ->schema([
                    Toggle::make('ordered')->label('Numbered list')->default(false),
                    Repeater::make('items')
                        ->hiddenLabel()
                        ->addActionLabel('Add item')
                        ->schema([self::prose('content')->hiddenLabel()])
                        ->columnSpanFull(),
                ]),

            // The legacy importer emitted one block per bullet; the renderer folds runs
            // of them back into a single list. Kept so those bodies stay editable.
            Block::make('list_item')
                ->label('List item (legacy)')
                ->icon('heroicon-o-minus-small')
                ->schema([TextInput::make('text')->columnSpanFull()]),

            Block::make('note')
                ->icon('heroicon-o-information-circle')
                ->schema([Textarea::make('text')->rows(3)->columnSpanFull()]),

            Block::make('callout')
                ->icon('heroicon-o-megaphone')
                ->schema([
                    self::prose('content'),
                    Select::make('variant')
                        ->options(['alert' => 'Alert (red)'])
                        ->placeholder('Standard'),
                ]),

            Block::make('toc')
                ->label('Table of contents')
                ->icon('heroicon-o-queue-list')
                ->schema([
                    TextInput::make('label')->placeholder('On this page'),
                    self::links('items'),
                ]),

            Block::make('jump_nav')
                ->label('Jump navigation')
                ->icon('heroicon-o-arrow-turn-down-right')
                ->schema([
                    TextInput::make('label')->placeholder('On this page:'),
                    self::links('items'),
                ]),

            Block::make('table')
                ->icon('heroicon-o-table-cells')
                ->schema([
                    TextInput::make('caption'),
                    TextInput::make('label')->helperText('Accessible name when the table is a scrollable panel.'),
                    Select::make('variant')
                        ->options(['panel' => 'Panel (scrollable region)', 'plain' => 'Plain'])
                        ->placeholder('Plain'),
                    Repeater::make('columns')
                        ->addActionLabel('Add column')
                        ->schema([
                            TextInput::make('label'),
                            self::align(),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                    Repeater::make('rows')
                        ->addActionLabel('Add row')
                        ->schema([
                            Repeater::make('cells')
                                ->hiddenLabel()
                                ->addActionLabel('Add cell')
                                ->schema([
                                    self::prose('content')->hiddenLabel()->columnSpanFull(),
                                    TextInput::make('label')->helperText('Mobile row label.'),
                                    TextInput::make('sub')->label('Sub-text'),
                                    self::align(),
                                    Toggle::make('accent'),
                                ])
                                ->columns(2),
                        ])
                        ->columnSpanFull(),
                ]),

            Block::make('card')
                ->icon('heroicon-o-rectangle-group')
                ->schema([
                    TextInput::make('title')->columnSpanFull(),
                    TextInput::make('eyebrow'),
                    TextInput::make('anchor')->helperText('Fragment id, for deep links.'),
                    TextInput::make('note')->columnSpanFull(),
                    Repeater::make('meta')
                        ->addActionLabel('Add meta line')
                        ->schema([self::prose('content')->hiddenLabel()])
                        ->columnSpanFull(),
                    Repeater::make('body')
                        ->addActionLabel('Add paragraph')
                        ->schema([self::prose('content')->hiddenLabel()])
                        ->columnSpanFull(),
                    TextInput::make('cta.label')->label('CTA label'),
                    self::url('cta.url')->label('CTA URL'),
                ]),

            Block::make('link_card')
                ->icon('heroicon-o-link')
                ->schema([
                    TextInput::make('label'),
                    TextInput::make('value'),
                    self::url('url')->columnSpanFull(),
                    Select::make('icon')
                        ->options(['external' => 'External link', 'mail' => 'Mail'])
                        ->default('external'),
                ]),

            Block::make('info_card')
                ->icon('heroicon-o-identification')
                ->schema([
                    TextInput::make('label'),
                    self::prose('content'),
                ]),

            // /our-process/ only: the editorial-standards layout.
            Block::make('band')
                ->icon('heroicon-o-view-columns')
                ->schema([
                    TextInput::make('eyebrow'),
                    TextInput::make('heading'),
                    Textarea::make('lead')->rows(2)->columnSpanFull(),
                    Select::make('tone')->options(['light' => 'Light', 'dark' => 'Dark'])->default('light'),
                    Select::make('layout')->options(['steps' => 'Steps', 'cards' => 'Cards'])->default('steps'),
                    Repeater::make('cards')
                        ->addActionLabel('Add card')
                        ->schema([
                            TextInput::make('n')->label('Number'),
                            TextInput::make('title'),
                            Textarea::make('desc')->label('Description')->rows(2)->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ]),

            Block::make('step')
                ->icon('heroicon-o-flag')
                ->schema([
                    TextInput::make('tag'),
                    TextInput::make('heading'),
                    self::prose('content'),
                ]),

            Block::make('ladder')
                ->icon('heroicon-o-bars-arrow-up')
                ->schema([
                    TextInput::make('tag'),
                    TextInput::make('heading'),
                    Repeater::make('rows')
                        ->addActionLabel('Add rung')
                        ->schema([
                            TextInput::make('n')->label('Number'),
                            TextInput::make('title'),
                            TextInput::make('tone')->helperText('1–4, picks the rung colour.'),
                            Textarea::make('desc')->label('Description')->rows(2)->columnSpanFull(),
                        ])
                        ->columns(3)
                        ->columnSpanFull(),
                    Textarea::make('note')->rows(2)->columnSpanFull(),
                ]),

            Block::make('refusals')
                ->icon('heroicon-o-x-circle')
                ->schema([
                    TextInput::make('tag'),
                    TextInput::make('heading'),
                    Textarea::make('lead')->rows(2)->columnSpanFull(),
                    TagsInput::make('items')
                        ->helperText('One entry per refusal.')
                        ->columnSpanFull(),
                ]),

            Block::make('freshness')
                ->icon('heroicon-o-clock')
                ->schema([
                    TextInput::make('label'),
                    self::prose('content')->columnSpanFull(),
                    TextInput::make('stat'),
                    TextInput::make('statLabel')->label('Stat label'),
                ]),

            Block::make('rule_note')
                ->icon('heroicon-o-scale')
                ->schema([
                    TextInput::make('heading')->columnSpanFull(),
                    self::prose('content'),
                ]),

            Block::make('cta_panel')
                ->label('CTA panel')
                ->icon('heroicon-o-cursor-arrow-rays')
                ->schema([
                    TextInput::make('heading')->columnSpanFull(),
                    self::prose('content'),
                    self::links('actions')->addActionLabel('Add action'),
                ]),

            Block::make('faq_item')
                ->label('FAQ item')
                ->icon('heroicon-o-question-mark-circle')
                ->schema([
                    TextInput::make('question')->columnSpanFull(),
                    self::prose('content')->label('Answer'),
                    Toggle::make('collapsed')->label('Render collapsed'),
                ]),
        ];
    }

    /**
     * A rich prose field. The toolbar is restricted to the marks the stored `spans`
     * vocabulary can carry, so an editor cannot produce formatting that would be
     * flattened on save.
     */
    private static function prose(string $name): RichEditor
    {
        // Built fresh per field on purpose. Cloning one configured prototype is ~12x
        // cheaper (~6ms per Page form request), but a cloned-then-renamed RichEditor
        // does not round-trip its state through save — measured, not assumed. The
        // eager array is also the shape Filament caches; a Closure here would be
        // re-evaluated per builder item and cost far more than it saves.
        return RichEditor::make($name)
            ->toolbarButtons(self::TOOLBAR)
            ->columnSpanFull();
    }

    /**
     * A field holding an editor-supplied href. The scheme allowlist is enforced here on
     * write as well as by `LinkUrl::sanitize()` on render (repo policy: editor-supplied
     * values are untrusted output), so a bad URL is rejected in the form instead of
     * silently rendering as `#`.
     */
    private static function url(string $name): TextInput
    {
        return TextInput::make($name)
            ->maxLength(2048)
            ->rule(LinkUrlField::rule())
            ->helperText(LinkUrlField::helperText());
    }

    private static function links(string $name): Repeater
    {
        return Repeater::make($name)
            ->addActionLabel('Add link')
            ->schema([
                TextInput::make('label'),
                self::url('url'),
            ])
            ->columns(2)
            ->columnSpanFull();
    }

    private static function align(): Select
    {
        return Select::make('align')
            ->options(['right' => 'Right'])
            ->placeholder('Left');
    }
}
