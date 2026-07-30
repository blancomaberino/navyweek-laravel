<?php

namespace App\Filament\Resources\Skills\Pages;

use App\Domain\Research\Models\Skill;
use App\Filament\Resources\Skills\SkillResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSkill extends EditRecord
{
    protected static string $resource = SkillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // `research_skill.skill_id` is cascadeOnDelete, so deleting a cited skill
            // would silently drop every brief's "which skill + version produced this"
            // provenance link. Block it while any brief cites the skill.
            DeleteAction::make()
                ->disabled(fn (Skill $record): bool => $record->research()->exists())
                ->tooltip(fn (Skill $record): ?string => $record->research()->exists()
                    ? 'Cited by research briefs — deleting would drop their skill provenance.'
                    : null),
        ];
    }
}
