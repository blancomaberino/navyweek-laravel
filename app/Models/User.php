<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Publishing\Models\Page;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * A login account that also doubles as the editorial byline. The `slug`,
 * `job_title`, `credentials`, `avatar_path`, and `knows_about` columns are the
 * PUBLIC author profile the discount-guide `Person` JSON-LD reads from — nullable,
 * so an account with no byline simply omits them.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $slug
 * @property string|null $job_title
 * @property string|null $credentials
 * @property string|null $avatar_path
 * @property array<int, string>|null $knows_about
 * @property bool $is_admin
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Page> $authoredPages
 * @property-read Collection<int, Page> $reviewedPages
 */
#[Fillable(['name', 'email', 'slug', 'job_title', 'credentials', 'avatar_path', 'knows_about', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Gate the Filament back-office: only `is_admin` users may reach any panel.
     * Deny-by-default — a plain authenticated account cannot touch the CRM/CMS.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin === true;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'knows_about' => 'array',
        ];
    }

    /**
     * Pages this user is the byline author of.
     *
     * @return HasMany<Page, $this>
     */
    public function authoredPages(): HasMany
    {
        return $this->hasMany(Page::class, 'author_id');
    }

    /**
     * Pages this user has reviewed/verified.
     *
     * @return HasMany<Page, $this>
     */
    public function reviewedPages(): HasMany
    {
        return $this->hasMany(Page::class, 'reviewer_id');
    }
}
