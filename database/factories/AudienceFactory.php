<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Crm\Enums\Audience as AudienceEnum;
use App\Domain\Crm\Models\Audience;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Audience>
 */
class AudienceFactory extends Factory
{
    protected $model = Audience::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => AudienceEnum::Military->value,
            'label' => AudienceEnum::Military->label(),
            'sort_order' => 0,
        ];
    }

    /**
     * Build the row for a specific audience from the canonical enum vocabulary.
     */
    public function ofKey(AudienceEnum $key): self
    {
        return $this->state(fn (): array => [
            'key' => $key->value,
            'label' => $key->label(),
        ]);
    }
}
