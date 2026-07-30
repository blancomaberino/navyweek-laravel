<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Domain\Publishing\Actions\ChangeUrlPathAction;
use App\Domain\Publishing\Models\Page;
use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
     *
     * The plain-column write and the URL rename (+ its redirect bookkeeping) run in a
     * single transaction so the edit is atomic: if the rename or the redirect listener
     * fails (e.g. a concurrent `url_path` uniqueness conflict), the non-URL fields roll
     * back with it rather than persisting a half-applied edit. The action's own
     * transaction nests as a savepoint inside this one.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Page) {
            return parent::handleRecordUpdate($record, $data);
        }

        $newUrlPath = $data['url_path'] ?? $record->url_path;
        unset($data['url_path']);

        DB::transaction(function () use ($record, $data, $newUrlPath): void {
            $record->fill($data)->save();
            app(ChangeUrlPathAction::class)($record, is_string($newUrlPath) ? $newUrlPath : $record->url_path);
        });

        return $record->refresh();
    }
}
