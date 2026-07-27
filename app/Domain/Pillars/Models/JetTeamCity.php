<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Models;

use App\Domain\Pillars\Enums\Admission;
use App\Domain\Pillars\Enums\JetTeamStatus;
use App\Domain\Shared\Concerns\HasFaqs;
use App\Domain\Shared\Concerns\HasSources;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;
use Database\Factories\JetTeamCityFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A published, full jet-team city guide (port of `JetTeamCity`). Only `published`
 * records get a route (`/{team}/{slug}/`), prerender, sitemap entry, and hub
 * link. The optional `second_*` window handles a city the team visits twice in a
 * season. FAQs and sources reuse the shared polymorphic tables; body blocks are
 * JSON.
 *
 * @property int $id
 * @property int $jet_team_id
 * @property string $slug
 * @property string $city
 * @property string $state
 * @property string $state_name
 * @property int $year
 * @property string $show
 * @property string $venue
 * @property Admission $admission
 * @property string $dates_label
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string|null $second_dates_label
 * @property Carbon|null $second_start_date
 * @property Carbon|null $second_end_date
 * @property JetTeamStatus $status
 * @property bool $published
 * @property array<int, string> $needs_verification
 * @property string $hero_dateline
 * @property string|null $dek
 * @property array<int, string> $intro
 * @property array<int, array{label: string, value: string}> $quick_facts
 * @property array<int, array<string, mixed>> $sections
 * @property array<int, array<string, mixed>> $related_paragraph
 * @property string $card_summary
 * @property string $meta_title
 * @property string $meta_description
 * @property string $h1
 * @property string $og_image
 * @property Carbon $date_published
 * @property Carbon $date_modified
 * @property string $last_verified
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JetTeam $team
 * @property-read Collection<int, Faq> $faqs
 * @property-read Collection<int, Source> $sources
 *
 * @method static JetTeamCityFactory factory($count = null, $state = [])
 */
class JetTeamCity extends Model
{
    /** @use HasFactory<JetTeamCityFactory> */
    use HasFactory;

    use HasFaqs;
    use HasSources;

    protected $table = 'jet_team_cities';

    protected $fillable = [
        'jet_team_id',
        'slug',
        'city',
        'state',
        'state_name',
        'year',
        'show',
        'venue',
        'admission',
        'dates_label',
        'start_date',
        'end_date',
        'second_dates_label',
        'second_start_date',
        'second_end_date',
        'status',
        'published',
        'needs_verification',
        'hero_dateline',
        'dek',
        'intro',
        'quick_facts',
        'sections',
        'related_paragraph',
        'card_summary',
        'meta_title',
        'meta_description',
        'h1',
        'og_image',
        'date_published',
        'date_modified',
        'last_verified',
    ];

    protected function casts(): array
    {
        return [
            'admission' => Admission::class,
            'status' => JetTeamStatus::class,
            'year' => 'integer',
            'published' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'second_start_date' => 'date',
            'second_end_date' => 'date',
            'needs_verification' => 'array',
            'intro' => 'array',
            'quick_facts' => 'array',
            'sections' => 'array',
            'related_paragraph' => 'array',
            'date_published' => 'date',
            'date_modified' => 'date',
        ];
    }

    /**
     * Domain-namespaced models miss the default factory guesser; point at the
     * flat factory explicitly.
     *
     * @return JetTeamCityFactory
     */
    protected static function newFactory(): Factory
    {
        return JetTeamCityFactory::new();
    }

    /**
     * @return BelongsTo<JetTeam, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(JetTeam::class, 'jet_team_id');
    }
}
