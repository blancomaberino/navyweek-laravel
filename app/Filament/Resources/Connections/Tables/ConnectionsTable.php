<?php

declare(strict_types=1);

namespace App\Filament\Resources\Connections\Tables;

use App\Domain\Crm\Enums\Audience;
use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use App\Filament\Support\EnumOptions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The CRM brand list — ~15.3k connections. Defaults are tuned for that scale:
 * search on the indexed identity columns, a live-status badge, an offers count,
 * and the pipeline/category/backlog filters that make the universe navigable.
 */
class ConnectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('total_volume', 'desc')
            ->columns([
                TextColumn::make('brand')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable()
                    ->color('gray'),
                TextColumn::make('category')
                    ->badge()
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ConnectionStatus $state): string => $state->label())
                    ->color(fn (ConnectionStatus $state): string => self::statusColor($state))
                    ->sortable(),
                TextColumn::make('offers_count')
                    ->counts('offers')
                    ->label('Offers')
                    ->sortable(),
                IconColumn::make('is_backlog')
                    ->boolean()
                    ->label('Backlog')
                    ->toggleable(),
                TextColumn::make('total_volume')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('last_verified_at')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(EnumOptions::map(ConnectionStatus::cases())),
                SelectFilter::make('category')
                    ->options(fn (): array => self::distinctCategories()),
                SelectFilter::make('audiences')
                    ->options(EnumOptions::map(Audience::cases()))
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereJsonContains('audiences', $data['value'])
                        : $query),
                TernaryFilter::make('is_backlog')
                    ->label('Backlog'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    private static function statusColor(ConnectionStatus $status): string
    {
        return match ($status) {
            ConnectionStatus::Published => 'success',
            ConnectionStatus::Drafted => 'info',
            ConnectionStatus::Pending => 'gray',
            ConnectionStatus::Duplicate,
            ConnectionStatus::NeedsInfo,
            ConnectionStatus::NeedsReverify => 'warning',
            ConnectionStatus::Skipped => 'danger',
        };
    }

    /**
     * Distinct non-null categories present in the universe, as a filter option map.
     *
     * @return array<string, string>
     */
    private static function distinctCategories(): array
    {
        /** @var Collection<int, string> $categories */
        $categories = Connection::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return $categories->mapWithKeys(fn (string $c): array => [$c => $c])->all();
    }
}
