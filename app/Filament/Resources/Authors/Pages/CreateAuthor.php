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
     * `is_admin` is a guarded attribute (never mass-assignable, by design), and an
     * editorial byline is not an interactive login — it needs no chosen password.
     * So `forceFill` the validated data plus a random, unusable password: the
     * NOT NULL `password` column is satisfied (the `hashed` cast hashes it on set),
     * nobody knows the value, and panel access is controlled solely by `is_admin`.
     * Real login credentials, if ever needed, are set out of band (per
     * `EditorialTeamSeeder`).
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = new User;

        $user->forceFill([
            ...$data,
            'password' => Str::random(40),
        ])->save();

        return $user;
    }
}
