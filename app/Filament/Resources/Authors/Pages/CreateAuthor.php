<?php

namespace App\Filament\Resources\Authors\Pages;

use App\Filament\Resources\Authors\AuthorResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateAuthor extends CreateRecord
{
    protected static string $resource = AuthorResource::class;

    /**
     * `is_admin` and `password` are guarded (never mass-assignable, by design). An
     * editorial byline is not an interactive login, so it needs no chosen password:
     * mass-assign the ordinary profile fields via `fill()` (keeping the guard active
     * for everything else), then `forceFill` only the two guarded columns — the
     * `is_admin` toggle and a random, unusable password that satisfies the NOT NULL
     * column (the `hashed` cast hashes it on set; nobody knows the value). Panel
     * access is controlled solely by `is_admin`; real login credentials, if ever
     * needed, are set out of band (per `EditorialTeamSeeder`).
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = new User;

        $user->fill($data)->forceFill([
            'is_admin' => (bool) ($data['is_admin'] ?? false),
            'password' => Str::random(40),
        ])->save();

        return $user;
    }
}
