<x-filament-panels::page>
    @php
        // Filament badge color per status → a Tailwind text class for the count dot.
        $dot = [
            'success' => 'text-success-500',
            'info' => 'text-info-500',
            'warning' => 'text-warning-500',
            'danger' => 'text-danger-500',
            'gray' => 'text-gray-400',
        ];
    @endphp

    <div class="flex gap-4 overflow-x-auto pb-4">
        @foreach ($this->columns() as $column)
            @php($status = $column['status'])
            <div class="w-72 shrink-0 rounded-xl bg-gray-50 p-3 dark:bg-white/5" wire:key="col-{{ $status->value }}">
                <header class="mb-3 flex items-center justify-between px-1">
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                        <span class="{{ $dot[$status->color()] ?? 'text-gray-400' }}">&#9679;</span>
                        {{ $status->label() }}
                    </span>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ number_format($column['count']) }}</span>
                </header>

                <div class="space-y-2">
                    @forelse ($column['connections'] as $connection)
                        <div
                            wire:key="card-{{ $connection->id }}"
                            class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-900"
                        >
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $connection->brand }}</div>
                            <div class="mt-0.5 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span>{{ $connection->category ?? '—' }}</span>
                                <span>{{ $connection->total_volume !== null ? number_format($connection->total_volume).' vol' : '' }}</span>
                            </div>

                            <select
                                class="mt-2 w-full rounded-md border-gray-200 bg-white text-xs text-gray-600 dark:border-white/10 dark:bg-gray-800 dark:text-gray-300"
                                wire:change="moveTo({{ $connection->id }}, $event.target.value)"
                                aria-label="Move {{ $connection->brand }} to another status"
                            >
                                <option value="" selected>Move to…</option>
                                @foreach (\App\Domain\Crm\Enums\ConnectionStatus::cases() as $option)
                                    @if ($option !== $status)
                                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    @empty
                        <p class="px-1 py-4 text-center text-xs text-gray-400">No connections</p>
                    @endforelse

                    @if ($column['count'] > $column['connections']->count())
                        <p class="px-1 pt-1 text-center text-xs text-gray-400">
                            + {{ number_format($column['count'] - $column['connections']->count()) }} more
                        </p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
