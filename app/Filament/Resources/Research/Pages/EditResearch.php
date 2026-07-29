<?php

namespace App\Filament\Resources\Research\Pages;

use App\Filament\Resources\Research\ResearchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditResearch extends EditRecord
{
    protected static string $resource = ResearchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
