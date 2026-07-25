<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Models;

use App\Domain\Publishing\Enums\RedirectMatchType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $from_path
 * @property string $to_path
 * @property int $status
 * @property string $reason
 * @property RedirectMatchType $match_type
 * @property bool $is_active
 * @property int $hits
 */
class Redirect extends Model
{
    protected $fillable = [
        'from_path',
        'to_path',
        'status',
        'reason',
        'match_type',
        'is_active',
        'hits',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'match_type' => RedirectMatchType::class,
            'is_active' => 'boolean',
            'hits' => 'integer',
        ];
    }
}
