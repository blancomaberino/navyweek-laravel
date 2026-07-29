<?php

declare(strict_types=1);

namespace App\Filament\Resources\Research\Schemas;

use App\Domain\Research\Enums\ResearchedBy;
use App\Domain\Research\Enums\ResearchStatus;
use App\Domain\Shared\Enums\ConfidenceLevel;
use App\Filament\Support\EnumOptions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Edit form for a research brief. Provenance (status, researcher, confidence,
 * verification date, skill) is editable; the verbatim `raw_markdown` is shown
 * read-only (it is the auditable source of record, imported from the brief file).
 * The structured columns (executive_summary, verified_facts, …) are populated by a
 * later parsing pass and are not hand-authored here.
 */
class ResearchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Provenance')
                    ->columns(2)
                    ->schema([
                        Select::make('connection_id')
                            ->relationship('connection', 'brand')
                            ->searchable()
                            ->preload(false)
                            ->required(),
                        TextInput::make('version')
                            ->numeric()
                            ->required()
                            ->default(1),
                        Select::make('status')
                            ->options(EnumOptions::map(ResearchStatus::cases()))
                            ->required(),
                        Select::make('researched_by')
                            ->options(EnumOptions::map(ResearchedBy::cases()))
                            ->required(),
                        Select::make('confidence_overall')
                            ->options(EnumOptions::map(ConfidenceLevel::cases())),
                        DatePicker::make('last_verified'),
                        TextInput::make('skill_key')->maxLength(255),
                        TextInput::make('skill_version')->maxLength(255),
                        TextInput::make('brief_path')
                            ->maxLength(2048)
                            ->helperText('Source-of-record path in the monorepo (provenance).'),
                    ]),

                Section::make('Verbatim brief (read-only)')
                    ->collapsed()
                    ->schema([
                        Textarea::make('raw_markdown')
                            ->rows(20)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('The imported brief Markdown — the auditable source of record.'),
                    ]),
            ]);
    }
}
