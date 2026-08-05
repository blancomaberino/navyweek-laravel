<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * A login account that also doubles as the editorial byline. The `slug`,
 * `job_title`, `credentials`, `avatar_path`, `knows_about`, `bio`, and
 * `linkedin_url` columns are the PUBLIC author profile the discount-guide `Person`
 * JSON-LD and the `/authors/{slug}/` profile page read from — nullable, so an
 * account with no byline simply omits them. The `*_timeline`, `*_title`, `location_*`,
 * `*_lead`, `featured_works`, and `profile_reviewed_at` columns carry the rest of that
 * public profile: the structured career timelines, the hero identity lines, the section
 * lead-ins, the curated credit list, and the profile's freshness stamp.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $slug
 * @property string|null $job_title
 * @property string|null $credentials
 * @property string|null $service_title
 * @property string|null $current_title
 * @property string|null $location_city
 * @property string|null $location_state
 * @property string|null $location_country
 * @property string|null $military_service
 * @property string|null $civilian_career
 * @property list<array{title: string, org: string, period: string, detail: string|null}>|null $military_timeline
 * @property list<array{title: string, org: string, period: string, detail: string|null}>|null $civilian_timeline
 * @property string|null $avatar_path
 * @property array<int, string>|null $knows_about
 * @property list<string>|null $profile_expertise
 * @property string|null $expertise_lead
 * @property string|null $works_lead
 * @property list<array{url: string, label: string, note: string|null}>|null $featured_works
 * @property string|null $bio
 * @property string|null $linkedin_url
 * @property Carbon|null $profile_reviewed_at
 * @property bool $is_admin
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Page> $authoredPages
 * @property-read Collection<int, Page> $reviewedPages
 * @property-read Page|null $authorProfilePage
 */
#[Fillable(['name', 'email', 'slug', 'job_title', 'credentials', 'service_title', 'current_title', 'location_city', 'location_state', 'location_country', 'military_service', 'civilian_career', 'military_timeline', 'civilian_timeline', 'avatar_path', 'knows_about', 'profile_expertise', 'expertise_lead', 'works_lead', 'featured_works', 'bio', 'linkedin_url', 'profile_reviewed_at', 'password'])]
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
            'profile_expertise' => 'array',
            'military_timeline' => 'array',
            'civilian_timeline' => 'array',
            'featured_works' => 'array',
            'profile_reviewed_at' => 'date',
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

    /**
     * This user's public author-profile page (`/authors/{slug}/`), or null when none has
     * been generated. It is the page whose polymorphic `pageable` is this user and whose
     * type is `Author`. The canonical LOCATION the byline `Person` @id/url resolves to, so
     * an editor rename of the profile's `url_path` is honored (identity vs. location).
     *
     * @return MorphOne<Page, $this>
     */
    public function authorProfilePage(): MorphOne
    {
        return $this->morphOne(Page::class, 'pageable')->where('page_type', PageType::Author);
    }
}
