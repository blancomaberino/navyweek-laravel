<?php

declare(strict_types=1);

namespace App\Filament\Resources\Connections\Schemas;

use App\Domain\Crm\Enums\Audience;
use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use App\Filament\Support\EnumOptions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Edit form for a CRM Connection (a brand). Grouped into identity, pipeline, and
 * links; the SEO-metric columns (volumes, difficulty, cpc) are surfaced read-only
 * because they are imported from the queue, not hand-edited here.
 *
 * Enum options are mapped in this Filament layer (value => label) so the domain
 * enums stay framework-agnostic — they carry a label() but not Filament's
 * HasLabel contract.
 */
class ConnectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->columns(2)
                    ->schema([
                        TextInput::make('brand')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->rule('regex:/^[a-z0-9-]+$/')
                            ->unique(Connection::class, 'slug', ignoreRecord: true)
                            ->helperText('Canonical brand key — lowercase, digits, hyphens. Used in URLs and joins.'),
                        TextInput::make('key')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Legacy queue key (usually equals the slug).'),
                        TextInput::make('category')
                            ->maxLength(255),
                    ]),

                Section::make('Pipeline')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options(EnumOptions::map(ConnectionStatus::cases()))
                            ->required(),
                        Select::make('audiences')
                            ->multiple()
                            ->options(EnumOptions::map(Audience::cases()))
                            ->helperText('Eligible audiences this brand targets.'),
                        TextInput::make('priority_tier')
                            ->numeric()
                            ->minValue(0),
                        Toggle::make('is_backlog')
                            ->helperText('A backlog brand is queued but not yet a live page.'),
                        DatePicker::make('last_verified_at'),
                        DatePicker::make('next_review_due'),
                        Select::make('duplicate_of')
                            ->relationship('duplicateOf', 'brand')
                            ->searchable()
                            ->preload(false)
                            ->helperText('Set when this brand duplicates a canonical connection.'),
                    ]),

                Section::make('Links & assets')
                    ->columns(2)
                    ->schema([
                        TextInput::make('official_url')
                            ->url()
                            ->maxLength(2048)
                            ->helperText('The brand\'s official military/veteran discount page.'),
                        TextInput::make('brand_home_url')
                            ->url()
                            ->maxLength(2048),
                        TextInput::make('logo_url')
                            ->url()
                            ->maxLength(2048),
                        Select::make('default_affiliate_network_id')
                            ->relationship('defaultAffiliateNetwork', 'name')
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make('Search metrics (imported, read-only)')
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        TextInput::make('total_volume')->numeric()->disabled(),
                        TextInput::make('max_volume')->numeric()->disabled(),
                        TextInput::make('keyword_count')->numeric()->disabled(),
                        TextInput::make('min_difficulty')->numeric()->disabled(),
                        TextInput::make('cpc')->disabled(),
                        TextInput::make('top_keyword')->disabled(),
                    ]),
            ]);
    }
}
