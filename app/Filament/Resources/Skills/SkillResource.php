<?php

declare(strict_types=1);

namespace App\Filament\Resources\Skills;

use App\Domain\Research\Models\Skill;
use App\Filament\Resources\Skills\Pages\CreateSkill;
use App\Filament\Resources\Skills\Pages\EditSkill;
use App\Filament\Resources\Skills\Pages\ListSkills;
use App\Filament\Resources\Skills\Schemas\SkillForm;
use App\Filament\Resources\Skills\Tables\SkillsTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * The research/QA skill registry (`military-discount-research`, `seo-geo`, …). The
 * `content_hash` + `current_version` are maintained by the skill-hash detector; a
 * brief that used an older `skill_version` is a re-verify trigger. Read-mostly —
 * editors adjust identity/provenance, the hash is stamped by automation.
 */
class SkillResource extends Resource
{
    protected static ?string $model = Skill::class;

    protected static ?string $navigationLabel = 'Skills';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Research';

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['key', 'name'];
    }

    public static function form(Schema $schema): Schema
    {
        return SkillForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SkillsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSkills::route('/'),
            'create' => CreateSkill::route('/create'),
            'edit' => EditSkill::route('/{record}/edit'),
        ];
    }
}
