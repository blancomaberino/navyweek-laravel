<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Models;

use App\Domain\Pillars\Enums\Admission;
use App\Domain\Pillars\Enums\AirShowStatus;
use App\Domain\Shared\Concerns\HasFaqs;
use App\Domain\Shared\Concerns\HasSources;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;
use Database\Factories\AirShowFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An air-show event guide (port of `airshows/*.ts` `AirShow`). `published` gates
 * whether the page is live; `date_unconfirmed` suppresses the Event JSON-LD (and
 * allows empty start/end); `canonical_override` marks a disambiguation page that
 * canonicalizes elsewhere. Block body (`sections`), schema inputs, and list fields
 * are JSON; FAQs and sources reuse the shared polymorphic tables.
 *
 * @property int $id
 * @property string $slug
 * @property string $short_name
 * @property string $name
 * @property string $city
 * @property string $state
 * @property string $state_name
 * @property int $year
 * @property string $base
 * @property string $dates_label
 * @property string $start_date
 * @property string $end_date
 * @property bool $date_unconfirmed
 * @property string|null $gate_time
 * @property Admission $admission
 * @property string|null $parking
 * @property string $headliner
 * @property array<int, string> $performers
 * @property string $official_url
 * @property string|null $phone
 * @property AirShowStatus $status
 * @property bool $published
 * @property array<int, string> $needs_verification
 * @property string $hero_headline
 * @property array<int, string> $intro
 * @property array<int, array{label: string, value: string}> $quick_facts
 * @property array<int, array<string, mixed>> $sections
 * @property array<int, array<string, mixed>> $related_paragraph
 * @property string $card_summary
 * @property array<string, mixed>|null $email_cta
 * @property string $schema_name
 * @property string $event_description
 * @property array<string, mixed> $location
 * @property array<string, mixed> $offer
 * @property array<string, mixed> $organizer
 * @property string $meta_title
 * @property string $meta_description
 * @property string $h1
 * @property string $og_image
 * @property string|null $canonical_override
 * @property Carbon $date_published
 * @property Carbon $date_modified
 * @property string $last_verified
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Faq> $faqs
 * @property-read Collection<int, Source> $sources
 *
 * @method static AirShowFactory factory($count = null, $state = [])
 */
class AirShow extends Model
{
    /** @use HasFactory<AirShowFactory> */
    use HasFactory;

    use HasFaqs;
    use HasSources;

    protected $fillable = [
        'slug',
        'short_name',
        'name',
        'city',
        'state',
        'state_name',
        'year',
        'base',
        'dates_label',
        'start_date',
        'end_date',
        'date_unconfirmed',
        'gate_time',
        'admission',
        'parking',
        'headliner',
        'performers',
        'official_url',
        'phone',
        'status',
        'published',
        'needs_verification',
        'hero_headline',
        'intro',
        'quick_facts',
        'sections',
        'related_paragraph',
        'card_summary',
        'email_cta',
        'schema_name',
        'event_description',
        'location',
        'offer',
        'organizer',
        'meta_title',
        'meta_description',
        'h1',
        'og_image',
        'canonical_override',
        'date_published',
        'date_modified',
        'last_verified',
    ];

    protected function casts(): array
    {
        return [
            'admission' => Admission::class,
            'status' => AirShowStatus::class,
            'year' => 'integer',
            'date_unconfirmed' => 'boolean',
            'published' => 'boolean',
            'performers' => 'array',
            'needs_verification' => 'array',
            'intro' => 'array',
            'quick_facts' => 'array',
            'sections' => 'array',
            'related_paragraph' => 'array',
            'email_cta' => 'array',
            'location' => 'array',
            'offer' => 'array',
            'organizer' => 'array',
            'date_published' => 'date',
            'date_modified' => 'date',
        ];
    }

    /**
     * Domain-namespaced models miss the default factory guesser; point at the
     * flat factory explicitly.
     *
     * @return AirShowFactory
     */
    protected static function newFactory(): Factory
    {
        return AirShowFactory::new();
    }

    /**
     * Whether this guide emits Event JSON-LD — only published guides with a
     * confirmed date and no canonical redirect. Mirrors the legacy render rules.
     */
    public function emitsEventSchema(): bool
    {
        return $this->published
            && ! $this->date_unconfirmed
            && $this->canonical_override === null;
    }
}
