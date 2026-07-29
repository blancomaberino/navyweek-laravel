<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * CRM dashboard overview — the pipeline at a glance across the ~15.3k connection
 * universe: total, published (live pages), how many are due for re-verification
 * today (the research-cadence backlog), and how many are still in the queue backlog.
 */
class PipelineStatsWidget extends StatsOverviewWidget
{
    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        return [
            Stat::make('Connections', (string) Connection::query()->count())
                ->description('Brands in the universe'),
            Stat::make('Published', (string) Connection::query()
                ->where('status', ConnectionStatus::Published->value)
                ->count())
                ->description('Live pages'),
            Stat::make('Due for review', (string) Connection::query()
                ->whereNotNull('next_review_due')
                ->whereDate('next_review_due', '<=', now())
                ->count())
                ->description('Past the research cadence')
                ->color('warning'),
            Stat::make('Backlog', (string) Connection::query()
                ->where('is_backlog', true)
                ->count())
                ->description('Not yet promoted'),
        ];
    }
}
