<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Schemas;

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Filament\Support\EnumOptions;
use App\Filament\Support\PathField;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Edit form for a published `pages` row. Grouped into routing and SEO. The
 * polymorphic `pageable` link and the render-built `json_ld` are set by the Stage-B
 * import / render layer, so they are not hand-edited here.
 */
class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Routing')
                    ->columns(2)
                    ->schema([
                        TextInput::make('url_path')
                            ->required()
                            ->maxLength(2048)
                            // Store the canonical form (leading + trailing slash, lowercased,
                            // collapsed slashes) so it's exactly what the router matches and
                            // what ChangeUrlPathAction derives the 301 from.
                            ->dehydrateStateUsing(PathField::canonicalDehydrator())
                            ->unique(Page::class, 'url_path', ignoreRecord: true)
                            ->helperText('Canonical path, leading + trailing slash (e.g. /discount/yeti/).'),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255),
                        Select::make('page_type')
                            ->options(EnumOptions::map(PageType::cases()))
                            ->required(),
                        Toggle::make('is_published'),
                        Toggle::make('noindex')
                            ->helperText('Emits noindex,nofollow and drops the Organization schema.'),
                    ]),

                Section::make('SEO')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')->maxLength(255)->columnSpanFull(),
                        Textarea::make('meta_description')->rows(2)->columnSpanFull(),
                        TextInput::make('canonical_path')
                            ->maxLength(2048)
                            ->helperText('Overrides the canonical URL when it differs from url_path.'),
                        TextInput::make('og_type')->maxLength(64)->default('website'),
                        TextInput::make('og_image_path')->maxLength(2048),
                        DateTimePicker::make('date_published'),
                        DateTimePicker::make('date_modified'),
                    ]),

                Section::make('Byline')
                    ->columns(2)
                    ->description('The editorial author + reviewer (E-E-A-T). Drives the discount guide’s Article `author` and WebPage `reviewedBy` Person JSON-LD; left empty, those nodes are omitted.')
                    ->schema([
                        Select::make('author_id')
                            ->label('Author')
                            ->relationship('author', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Byline author — the Article author Person node.'),
                        Select::make('reviewer_id')
                            ->label('Reviewer')
                            ->relationship('reviewer', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Who verified the page — the WebPage reviewedBy Person node.'),
                    ]),

                Section::make('Content body')
                    ->description('The CMS-editable body for content pages (privacy, terms, veterans-day, …), rendered as ordered blocks. Data-driven pages (discounts, bases, events) leave this empty — their body comes from their aggregate.')
                    ->collapsed()
                    ->schema([
                        Repeater::make('body_blocks')
                            ->hiddenLabel()
                            ->addActionLabel('Add block')
                            ->reorderable()
                            ->collapsible()
                            ->schema([
                                Select::make('type')
                                    ->options([
                                        'paragraph' => 'Paragraph',
                                        'heading' => 'Heading',
                                        'list' => 'List',
                                        'note' => 'Note',
                                    ])
                                    ->default('paragraph')
                                    ->live()
                                    ->required(),
                                Textarea::make('text')
                                    ->rows(3)
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => $get('type') !== 'list'),
                                TagsInput::make('items')
                                    ->helperText('One entry per list item.')
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => $get('type') === 'list'),
                            ]),
                    ]),
            ]);
    }
}
