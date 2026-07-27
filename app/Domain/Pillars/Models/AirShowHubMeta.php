<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Models;

use App\Domain\Shared\Concerns\HasFaqs;
use App\Domain\Shared\Models\Faq;
use Database\Factories\AirShowHubMetaFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The air-show directory hub (`/air-show/`) content (port of `AirShowHubMeta`).
 * A single editable content row per silo; `base_path` is the natural key. FAQs
 * feed the hub FAQPage schema via the shared polymorphic table; the other copy
 * blocks are JSON.
 *
 * @property int $id
 * @property string $base_path
 * @property int $year
 * @property string $eyebrow
 * @property string $hub_title
 * @property string $hub_subtitle
 * @property string $seo_headline
 * @property array<int, string> $intro
 * @property array<int, array{label: string, value: string}> $key_facts
 * @property array<int, string> $about
 * @property string $meta_title
 * @property string $meta_description
 * @property string $og_image
 * @property Carbon $date_published
 * @property Carbon $date_modified
 * @property string $last_verified
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Faq> $faqs
 *
 * @method static AirShowHubMetaFactory factory($count = null, $state = [])
 */
class AirShowHubMeta extends Model
{
    /** @use HasFactory<AirShowHubMetaFactory> */
    use HasFactory;

    use HasFaqs;

    protected $table = 'air_show_hub';

    protected $fillable = [
        'base_path',
        'year',
        'eyebrow',
        'hub_title',
        'hub_subtitle',
        'seo_headline',
        'intro',
        'key_facts',
        'about',
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
            'year' => 'integer',
            'intro' => 'array',
            'key_facts' => 'array',
            'about' => 'array',
            'date_published' => 'date',
            'date_modified' => 'date',
        ];
    }

    /**
     * Domain-namespaced models miss the default factory guesser; point at the
     * flat factory explicitly.
     *
     * @return AirShowHubMetaFactory
     */
    protected static function newFactory(): Factory
    {
        return AirShowHubMetaFactory::new();
    }
}
