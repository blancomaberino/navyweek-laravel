<?php

declare(strict_types=1);

namespace App\Filament\Resources\Connections\Tables;

use App\Domain\Crm\Enums\Audience;
use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use App\Domain\Crm\Repositories\ConnectionRepositoryInterface;
use App\Filament\Support\EnumOptions;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
                TextColumn::make('next_review_due')
                    ->date()
                    ->label('Review due')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('priority_tier')
                    ->numeric()
                    ->label('Priority')
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
                Filter::make('due_for_review')
                    ->label('Due for review')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('next_review_due')
                        ->whereDate('next_review_due', '<=', now())),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('setStatus')
                        ->label('Set pipeline status')
                        ->icon('heroicon-o-flag')
                        ->schema([
                            Select::make('status')
                                ->options(EnumOptions::map(ConnectionStatus::cases()))
                                ->required(),
                        ])
                        // Rewrites the pipeline status of every selected row with no
                        // undo — confirm before a select-all mis-click reassigns
                        // thousands of connections (matches promoteFromBacklog).
                        ->requiresConfirmation()
                        ->modalDescription('This overwrites the pipeline status of all selected connections. There is no undo.')
                        // Report the real affected count instead of Filament's blanket
                        // success — a selection that includes trashed rows updates fewer
                        // than were selected, and that shouldn't read as full success.
                        ->successNotification(null)
                        ->action(function (array $data, EloquentCollection $records): void {
                            $status = $data['status'] ?? null;
                            if (! is_string($status)) {
                                return;
                            }
                            $affected = app(ConnectionRepositoryInterface::class)
                                ->updateStatusForIds($records->modelKeys(), ConnectionStatus::from($status));
                            self::notifyBulkResult($affected, $records->count(), 'updated');
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('promoteFromBacklog')
                        ->label('Promote from backlog')
                        ->icon('heroicon-o-arrow-up-circle')
                        ->requiresConfirmation()
                        ->successNotification(null)
                        ->action(function (EloquentCollection $records): void {
                            $affected = app(ConnectionRepositoryInterface::class)->clearBacklogForIds($records->modelKeys());
                            self::notifyBulkResult($affected, $records->count(), 'promoted');
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Surface the real affected count for a bulk action. When fewer rows changed than
     * were selected (e.g. trashed rows are excluded by the soft-delete scope), warn
     * instead of reporting a blanket success.
     */
    private static function notifyBulkResult(int $affected, int $selected, string $verb): void
    {
        if ($affected >= $selected) {
            Notification::make()
                ->title("{$affected} connection(s) {$verb}.")
                ->success()
                ->send();

            return;
        }

        $skipped = $selected - $affected;
        Notification::make()
            ->title("{$affected} of {$selected} {$verb} — {$skipped} skipped")
            ->body('Skipped rows are archived (trashed) and were left unchanged.')
            ->warning()
            ->send();
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
