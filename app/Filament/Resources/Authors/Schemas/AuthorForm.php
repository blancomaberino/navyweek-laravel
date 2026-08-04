<?php

declare(strict_types=1);

namespace App\Filament\Resources\Authors\Schemas;

use App\Filament\Resources\Authors\Pages\CreateAuthor;
use App\Models\User;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Edit form for an editorial author (`users` row). Grouped into the public
 * editorial profile (the fields the `Person` JSON-LD + `/authors/{slug}/` page
 * read) and account/access.
 *
 * `password` is never edited here — see {@see CreateAuthor} for the byline-profile
 * credential policy. `avatar_path` is a site-relative path (e.g. `/authors/name.jpg`)
 * served by the static site and prefixed with the host in the JSON-LD — a plain
 * path input, not a disk upload, so the stored value matches what the schema
 * builder expects.
 */
class AuthorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Editorial profile')
                    ->description('The public byline the discount-guide Person JSON-LD and the /authors/{slug}/ page read.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->rule('regex:/^[a-z0-9-]+$/')
                            ->unique(User::class, 'slug', ignoreRecord: true)
                            ->helperText('Public profile slug — lowercase, digits, hyphens. Drives /authors/{slug}/ and the Person @id.'),
                        TextInput::make('job_title')
                            ->maxLength(255)
                            ->helperText('schema.org Person.jobTitle, e.g. "Editor, NavyWeek.org".'),
                        TextInput::make('avatar_path')
                            ->maxLength(255)
                            // A single leading slash but NOT a second one: reject a
                            // protocol-relative `//host/…` value that would resolve to
                            // an external host when rendered as an <img src>.
                            ->rule('regex:#^/(?!/)#')
                            ->helperText('Site-relative avatar path, e.g. /authors/name.jpg. Prefixed with the host in JSON-LD.'),
                        TextInput::make('linkedin_url')
                            ->url()
                            ->maxLength(255)
                            ->helperText('Person.sameAs — the public LinkedIn profile shown in "Connect".'),
                        Textarea::make('credentials')
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText('Person.description — the credentials / bio line the pages cite.'),
                        Textarea::make('bio')
                            ->rows(5)
                            ->columnSpanFull()
                            ->helperText('Long-form bio prose shown on the /authors/{slug}/ profile page.'),
                        TagsInput::make('knows_about')
                            ->columnSpanFull()
                            ->helperText('Person.knowsAbout — one expertise topic per tag.'),
                    ]),

                Section::make('Account & access')
                    ->description('The login identity behind the byline. Editorial profiles are not interactive logins by default; grant panel access explicitly.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(User::class, 'email', ignoreRecord: true)
                            ->helperText('Account identity — not published.'),
                        Toggle::make('is_admin')
                            ->label('Admin panel access')
                            // Byline profiles created here get a random, unusable password
                            // and there is no self-service reset — this flag alone can't
                            // produce a working login; a password is set out of band.
                            ->helperText('Marks the account as panel-eligible. A login password must still be set out of band (see EditorialTeamSeeder). Off = byline-only profile.'),
                    ]),
            ]);
    }
}
