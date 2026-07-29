<?php

declare(strict_types=1);

namespace App\Filament\Resources\Offers\Schemas;

use App\Domain\Catalog\Enums\OfferType;
use App\Domain\Catalog\Enums\VerificationProvider;
use App\Filament\Support\EnumOptions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Edit form for an Offer (one brand's discount). Grouped into identity, the
 * editorial discount detail, and the audience pivot. The simple string-list JSON
 * columns (eligibility/exclusions/key_facts) are edited as tag lists; the richer
 * structured JSON (savings tables, chooser, promo) is managed on the page itself
 * and left out of the quick-edit form.
 */
class OfferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->columns(2)
                    ->schema([
                        Select::make('connection_id')
                            ->relationship('connection', 'brand')
                            ->searchable()
                            ->preload(false)
                            ->required(),
                        TextInput::make('internal_label')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Internal name, e.g. "YETI — Everyday discount".'),
                        Select::make('offer_type')
                            ->options(EnumOptions::map(OfferType::cases()))
                            ->required(),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_primary')
                            ->helperText('The offer the brand page renders as its main deal.'),
                        Toggle::make('is_published'),
                    ]),

                Section::make('Discount detail')
                    ->columns(2)
                    ->schema([
                        TextInput::make('headline_discount')->maxLength(255),
                        TextInput::make('audience_label')->maxLength(255),
                        Textarea::make('discount_summary')->columnSpanFull()->rows(2),
                        Select::make('verification')
                            ->options(EnumOptions::map(VerificationProvider::cases())),
                        TextInput::make('verification_url')->url()->maxLength(2048),
                        TextInput::make('official_url')->url()->maxLength(2048),
                        TextInput::make('cta_label')->maxLength(255),
                        TextInput::make('cta_subnote')->maxLength(255),
                        TextInput::make('sticky_cta_label')->maxLength(255),
                        TagsInput::make('eligibility')->columnSpanFull()
                            ->helperText('One eligibility bullet per tag.'),
                        TagsInput::make('exclusions')->columnSpanFull(),
                        TagsInput::make('key_facts')->columnSpanFull(),
                    ]),

                Section::make('Audiences')
                    ->schema([
                        Select::make('audiences')
                            ->relationship('audiences', 'key')
                            ->multiple()
                            ->preload()
                            ->helperText('Audiences this offer serves (offer_audience pivot).'),
                    ]),
            ]);
    }
}
