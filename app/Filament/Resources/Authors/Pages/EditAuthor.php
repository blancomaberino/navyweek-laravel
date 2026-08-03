<?php

namespace App\Filament\Resources\Authors\Pages;

use App\Filament\Resources\Authors\AuthorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAuthor extends EditRecord
{
    protected static string $resource = AuthorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // pages.author_id / reviewer_id are nullOnDelete, so deleting an author
            // never deletes their pages — it silently clears the byline. Warn first.
            DeleteAction::make()
                ->modalDescription('Deleting this author clears the byline (author/reviewer) on every page that cites them. The pages themselves are kept.'),
        ];
    }

    /**
     * `is_admin` is guarded (see CreateAuthor), so a plain `update()`/`fill()` would
     * silently drop toggling panel access. Mass-assign the ordinary fields via
     * `fill()` (guard stays active for everything else), then `forceFill` only the
     * guarded `is_admin`. `password` is not in the form, so it is left untouched.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->fill($data)->forceFill([
            'is_admin' => (bool) ($data['is_admin'] ?? false),
        ])->save();

        return $record;
    }
}
