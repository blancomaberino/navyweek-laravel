<?php

declare(strict_types=1);

namespace App\Domain\Navigation\Models;

use App\Domain\Navigation\Enums\MenuItemSlot;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A single link in a {@see Menu}. `url` is stored verbatim (a root-relative path
 * like `/schedule/` or an absolute external URL) — navigation links point *at*
 * pages, so they are not coupled to the page-identity/`url_path` machinery.
 *
 * `parent_id` (self-referential, one level) turns an item into a dropdown parent;
 * `target`/`rel` carry the external-link attributes (only the NAVCO link needs
 * them today). `sort_order` is the drag-reorder position within its menu/parent.
 *
 * @property int $id
 * @property int $menu_id
 * @property int|null $parent_id
 * @property string $label
 * @property string $url
 * @property MenuItemSlot|null $slot
 * @property string|null $active_slug
 * @property string|null $target
 * @property string|null $rel
 * @property int $sort_order
 * @property int|null $mobile_sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Menu $menu
 * @property-read MenuItem|null $parent
 * @property-read Collection<int, MenuItem> $children
 *
 * @method static MenuItemFactory factory($count = null, $state = [])
 */
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    protected $fillable = [
        'menu_id',
        'parent_id',
        'label',
        'url',
        'slot',
        'active_slug',
        'target',
        'rel',
        'sort_order',
        'mobile_sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'slot' => MenuItemSlot::class,
            'sort_order' => 'integer',
            'mobile_sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return MenuItemFactory
     */
    protected static function newFactory(): Factory
    {
        return MenuItemFactory::new();
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * Nested dropdown links under this item, ordered.
     *
     * @return HasMany<MenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Active nested dropdown links under this item, ordered — the render relation
     * the repository eager-loads under {@see Menu::activeItems()}.
     *
     * @return HasMany<MenuItem, $this>
     */
    public function activeChildren(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }
}
