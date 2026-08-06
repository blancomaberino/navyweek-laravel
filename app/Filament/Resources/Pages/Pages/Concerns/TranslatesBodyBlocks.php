<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Pages\Concerns;

use App\Domain\Publishing\Content\BodyBlocks;

/**
 * Converts `body_blocks` between the stored renderer shape and the Builder state on
 * every form fill and every save.
 *
 * The translation is deliberately hung off the page's save hooks rather than the
 * Builder's own state casts: by the time these run, Filament has already re-indexed
 * every nested repeater to a plain list, so the mapper sees exactly the shape it is
 * tested against.
 *
 * Only the SAVE half is shared. The hydrate half lives on {@see EditPage} because
 * `mutateFormDataBeforeFill` is an `EditRecord` hook — `CreateRecord` does not declare
 * it, so a `parent::` call from here would be undefined on the create path (and there
 * is nothing to hydrate when creating).
 */
trait TranslatesBodyBlocks
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->storeBodyBlocks($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->storeBodyBlocks($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function storeBodyBlocks(array $data): array
    {
        if (! array_key_exists('body_blocks', $data)) {
            return $data;
        }

        /** @var array<array-key, array{type?: string, data?: array<string, mixed>}>|null $state */
        $state = $data['body_blocks'];
        $blocks = BodyBlocks::dehydrate($state);

        // A page with no body stores NULL, not an empty array — that is what the
        // generators check when deciding whether a body has been seeded yet.
        $data['body_blocks'] = $blocks === [] ? null : $blocks;

        return $data;
    }
}
