<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Models;

use App\Domain\Pillars\Enums\TeamId;
use App\Domain\Shared\Models\Faq;
use Database\Factories\JetTeamFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * A flight-demonstration squadron hub (port of `jetteams` `TeamMeta`). `team` is
 * the natural key (enum TeamId); the season schedule and city guides are the
 * `jet_team_schedule` / `jet_team_cities` children. Hub FAQs reuse the shared
 * polymorphic table; the `cross_team` footer link and copy blocks are JSON.
 *
 * @property int $id
 * @property TeamId $team
 * @property string $name
 * @property string $full_name
 * @property string $branch
 * @property string $aircraft
 * @property string $home_base
 * @property string $base_path
 * @property int $year
 * @property string $eyebrow
 * @property string $hub_title
 * @property string $hub_subtitle
 * @property string $seo_headline
 * @property array<int, string> $intro
 * @property array<int, array{label: string, value: string}> $key_facts
 * @property array<int, string> $about
 * @property array<string, mixed> $cross_team
 * @property string $meta_title
 * @property string $meta_description
 * @property string $og_image
 * @property Carbon $date_published
 * @property Carbon $date_modified
 * @property string $last_verified
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, JetTeamScheduleRow> $schedule
 * @property-read Collection<int, JetTeamCity> $cities
 * @property-read Collection<int, Faq> $faqs
 *
 * @method static JetTeamFactory factory($count = null, $state = [])
 */
class JetTeam extends Model
{
    /** @use HasFactory<JetTeamFactory> */
    use HasFactory;

    protected $fillable = [
        'team',
        'name',
        'full_name',
        'branch',
        'aircraft',
        'home_base',
        'base_path',
        'year',
        'eyebrow',
        'hub_title',
        'hub_subtitle',
        'seo_headline',
        'intro',
        'key_facts',
        'about',
        'cross_team',
        'meta_title',
        'meta_description',
        'og_image',
        'date_published',
        'date_modified',
        'last_verified',
    ];

    protected function casts(): array
    {
        return [
            'team' => TeamId::class,
            'year' => 'integer',
            'intro' => 'array',
            'key_facts' => 'array',
            'about' => 'array',
            'cross_team' => 'array',
            'date_published' => 'date',
            'date_modified' => 'date',
        ];
    }

    /**
     * Domain-namespaced models miss the default factory guesser; point at the
     * flat factory explicitly.
     *
     * @return JetTeamFactory
     */
    protected static function newFactory(): Factory
    {
        return JetTeamFactory::new();
    }

    /**
     * Every stop on the team's official season tour, in authored order.
     *
     * @return HasMany<JetTeamScheduleRow, $this>
     */
    public function schedule(): HasMany
    {
        return $this->hasMany(JetTeamScheduleRow::class)->orderBy('sort_order');
    }

    /**
     * The team's authored city guides (published and not), ordered by city.
     *
     * @return HasMany<JetTeamCity, $this>
     */
    public function cities(): HasMany
    {
        return $this->hasMany(JetTeamCity::class)->orderBy('city');
    }

    /**
     * Hub FAQs (shared polymorphic table), in display order.
     *
     * @return MorphMany<Faq, $this>
     */
    public function faqs(): MorphMany
    {
        return $this->morphMany(Faq::class, 'faqable')->orderBy('sort_order');
    }
}
