<?php

declare(strict_types=1);

namespace App\Filament\Resources\Authors;

use App\Filament\Resources\Authors\Pages\CreateAuthor;
use App\Filament\Resources\Authors\Pages\EditAuthor;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Resources\Authors\Schemas\AuthorForm;
use App\Filament\Resources\Authors\Tables\AuthorsTable;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * CRUD over the editorial byline — the `users` rows a page cites as its author /
 * reviewer, whose profile columns (`slug`, `job_title`, `credentials`,
 * `avatar_path`, `knows_about`) drive the discount-guide `Person` JSON-LD and the
 * `/authors/{slug}/` pages. Before this resource these profiles could only be
 * created by `EditorialTeamSeeder`; now they are editable in the panel.
 *
 * An editorial author IS a `User` — this resource does not add a model. It
 * distinguishes editorial authors from plain login/ops accounts by the presence of
 * the public author `slug` (an ops admin with no byline has none), so the list is
 * scoped to `whereNotNull('slug')` rather than introducing a new role column. The
 * form requires a slug, so every author created here is in scope.
 */
class AuthorResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'Authors';

    // The model is `User`, but this resource curates the editorial byline — so the
    // panel reads "Author"/"Authors" (page titles, breadcrumbs, the create button)
    // instead of the model's default "User"/"Users".
    protected static ?string $modelLabel = 'author';

    protected static ?string $pluralModelLabel = 'authors';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Publishing';

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'email'];
    }

    /**
     * Scope the resource to editorial byline profiles — users that carry a public
     * author `slug`. Overriding the resource's own Eloquent query is Filament's
     * model-binding layer (exempt from the repository rule); it keeps pure ops-admin
     * accounts (no byline) out of the Authors list without a new column.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotNull('slug');
    }

    public static function form(Schema $schema): Schema
    {
        return AuthorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuthorsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuthors::route('/'),
            'create' => CreateAuthor::route('/create'),
            'edit' => EditAuthor::route('/{record}/edit'),
        ];
    }
}
