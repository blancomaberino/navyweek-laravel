<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Schemas;

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Filament\Support\EnumOptions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
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
            ]);
    }
}
