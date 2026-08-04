<?php

declare(strict_types=1);

namespace App\Domain\Navigation\Models;

use App\Domain\Navigation\Enums\MenuLocation;
use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An editable navigation menu — one named, ordered list of links rendered in a
 * region of the site chrome ({@see MenuLocation}). Replaces the arrays that were
 * hardcoded in the header/footer Blade partials so a non-technical editor can
 * manage the site's navigation from the admin panel.
 *
 * `key` is the stable identity (e.g. `header-primary`, `footer-navy-week`) the
 * seeder and any code lookups pin to; `location` + `sort_order` place the menu in
 * the chrome; `name` is the visible heading (used for footer columns).
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property MenuLocation $location
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, MenuItem> $items
 *
 * @method static MenuFactory factory($count = null, $state = [])
 */
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'location',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'location' => MenuLocation::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return MenuFactory
     */
    protected static function newFactory(): Factory
    {
        return MenuFactory::new();
    }

    /**
     * Every item in the menu (both top-level links and nested dropdown children),
     * ordered. The Filament relation manager edits this flat list.
     *
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order');
    }

    /**
     * The active, top-level links of the menu (dropdown children excluded — they
     * are reached via {@see MenuItem::activeChildren()}), ordered. This is the
     * render relation the repository eager-loads for the header/footer.
     *
     * @return HasMany<MenuItem, $this>
     */
    public function activeItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }
}
