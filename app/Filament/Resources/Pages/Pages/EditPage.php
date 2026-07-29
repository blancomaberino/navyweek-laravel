<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Domain\Publishing\Actions\ChangeUrlPathAction;
use App\Domain\Publishing\Models\Page;
use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * A `url_path` edit is a URL rename, not a plain column write: route it through
     * ChangeUrlPathAction so the old path auto-301s to the new one (no deploy). The
     * action is a no-op when the path is unchanged, so a normal save is unaffected.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Page) {
            return parent::handleRecordUpdate($record, $data);
        }

        $newUrlPath = $data['url_path'] ?? $record->url_path;
        unset($data['url_path']);

        $record->fill($data)->save();
        app(ChangeUrlPathAction::class)($record, is_string($newUrlPath) ? $newUrlPath : $record->url_path);

        return $record->refresh();
    }
}
