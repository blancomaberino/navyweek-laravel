<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Models;

use App\Domain\Publishing\Enums\PageType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property PageType $page_type
 * @property string $slug
 * @property string $url_path
 * @property bool $is_published
 */
class Page extends Model
{
    protected $fillable = [
        'page_type',
        'slug',
        'url_path',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'page_type' => PageType::class,
            'is_published' => 'boolean',
        ];
    }
}
