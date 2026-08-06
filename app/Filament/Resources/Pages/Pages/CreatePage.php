<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Pages\Concerns\TranslatesBodyBlocks;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    use TranslatesBodyBlocks;

    protected static string $resource = PageResource::class;
}
