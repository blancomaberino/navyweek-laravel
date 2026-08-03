<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use App\Domain\Crm\Repositories\ConnectionRepositoryInterface;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * The pipeline as a Kanban board: one column per `ConnectionStatus`, cards for the
 * top brands in each. Cards move between columns via the per-card status control,
 * which writes through `ConnectionRepository::updateStatusForIds` — the same bulk
 * mutation the CRM table's bulk action uses, so the board never touches the model
 * directly.
 *
 * Each column is capped at {@see self::COLUMN_LIMIT} (a status can hold thousands of
 * the ~15k-connection universe) and paired with the true `countByStatus` total, so the
 * board stays fast without hiding the real pipeline size.
 */
class PipelineBoard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationLabel = 'Pipeline board';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?string $title = 'Pipeline board';

    protected string $view = 'filament.pages.pipeline-board';

    /** Max cards rendered per column; the header still shows the full `countByStatus`. */
    public const COLUMN_LIMIT = 25;

    /**
     * One entry per pipeline status, in enum order — the columns the view renders.
     *
     * @return list<array{status: ConnectionStatus, count: int, connections: Collection<int, Connection>}>
     */
    public function columns(): array
    {
        $connections = app(ConnectionRepositoryInterface::class);

        return array_map(static fn (ConnectionStatus $status): array => [
            'status' => $status,
            'count' => $connections->countByStatus($status),
            'connections' => $connections->forStatus($status, self::COLUMN_LIMIT),
        ], ConnectionStatus::cases());
    }

    /**
     * Move a card to another column. `$status` is validated against the enum (an unknown
     * value from a tampered request is ignored), then applied through the repository.
     */
    public function moveTo(int $connectionId, string $status): void
    {
        $target = ConnectionStatus::tryFrom($status);
        if ($target === null) {
            return;
        }

        app(ConnectionRepositoryInterface::class)->updateStatusForIds([$connectionId], $target);

        Notification::make()
            ->title("Moved to {$target->label()}")
            ->success()
            ->send();
    }
}
