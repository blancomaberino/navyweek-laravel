<?php

declare(strict_types=1);

namespace App\Filament\Resources\Skills\Schemas;

use App\Domain\Research\Models\Skill;
use App\Filament\Support\SlugKeyField;
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
                            // The key is a canonical kebab-case identifier matched
                            // case-sensitively against the on-disk `.claude/skills/<key>/`
                            // directory by the hash detector. Store it canonical and check
                            // uniqueness in that same form, so a case/whitespace variant
                            // can't slip past the guard and orphan the skill from its source.
                            ->dehydrateStateUsing(SlugKeyField::canonicalDehydrator())
                            ->rule(SlugKeyField::uniqueRule(Skill::class, 'A skill with this key already exists.'))
                            ->helperText('Stable kebab-case registry key, e.g. military-discount-research.'),
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
