<?php

declare(strict_types=1);

namespace App\Filament\Resources\Skills\Schemas;

use App\Domain\Research\Models\Skill;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Edit form for a `skills` registry row. `content_hash` is stamped by the skill-hash
 * detector, so it is shown read-only rather than hand-edited.
 */
class SkillForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('key')
                            ->required()
                            ->maxLength(255)
                            ->unique(Skill::class, 'key', ignoreRecord: true)
                            ->helperText('Stable registry key, e.g. military-discount-research.'),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('current_version')
                            ->required()
                            ->maxLength(50)
                            ->helperText('Bumped when the skill content hash changes.'),
                        TextInput::make('source_ref')
                            ->maxLength(2048)
                            ->helperText('Where the skill is defined (path or URL).'),
                        TextInput::make('content_hash')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Maintained by the skill-hash detector — read-only.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
