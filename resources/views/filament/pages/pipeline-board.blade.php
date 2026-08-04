<x-filament-panels::page>
    {{--
        Scoped stylesheet for the Kanban. The admin panel ships Filament's precompiled
        theme and does NOT compile arbitrary Tailwind utilities used in custom views, so
        the board is styled with plain CSS namespaced under `.nw-kanban` — self-contained,
        no build step, and it cannot leak into the rest of the panel. Dark mode follows
        Filament's `.dark` class on <html>. Palette: the NavyWeek Fleet Navy / Service Gold
        system, with Filament's semantic badge colors for the per-status column dots.

        Wrapped in `@assets` so Livewire hoists it into <head> once per page load and keeps
        it out of the reactive payload — a card move re-renders the component, and this
        static CSS should not ride along on every round-trip.
    --}}
    @assets
    <style>
        .nw-kanban {
            --nw-navy: #0a1628;
            --nw-navy-mid: #152340;
            --nw-gold: #c9a84c;

            display: flex;
            gap: 1rem;
            align-items: flex-start;
            overflow-x: auto;
            padding-bottom: 0.75rem;
            /* Bounds the board so columns scroll vertically instead of the whole page. */
            max-height: calc(100vh - 15rem);
            scrollbar-width: thin;
        }

        .nw-kanban__col {
            flex: 0 0 19rem;
            display: flex;
            flex-direction: column;
            max-height: 100%;
            min-height: 8rem;
            border-radius: 0.75rem;
            background: #f4f2ea;
            border: 1px solid rgba(10, 22, 40, 0.08);
            border-top: 3px solid var(--dot, #9ca3af);
        }

        .dark .nw-kanban__col {
            background: var(--nw-navy-mid);
            border-color: rgba(201, 168, 76, 0.18);
            border-top-color: var(--dot, #9ca3af);
        }

        .nw-kanban__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0.75rem 0.875rem;
            flex-shrink: 0;
        }

        .nw-kanban__title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #1e293b;
        }

        .dark .nw-kanban__title {
            color: #e2e8f0;
        }

        .nw-kanban__dot {
            width: 0.625rem;
            height: 0.625rem;
            border-radius: 9999px;
            background: var(--dot, #9ca3af);
            flex-shrink: 0;
        }

        .nw-kanban__count {
            font-size: 0.75rem;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            color: #64748b;
            background: rgba(10, 22, 40, 0.06);
            border-radius: 9999px;
            padding: 0.0625rem 0.5rem;
        }

        .dark .nw-kanban__count {
            color: #cbd5e1;
            background: rgba(255, 255, 255, 0.08);
        }

        .nw-kanban__cards {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            padding: 0 0.625rem 0.75rem;
            overflow-y: auto;
            scrollbar-width: thin;
        }

        .nw-kanban__card {
            background: #ffffff;
            border: 1px solid rgba(10, 22, 40, 0.1);
            border-radius: 0.5rem;
            padding: 0.625rem 0.75rem;
            box-shadow: 0 1px 2px rgba(10, 22, 40, 0.05);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .nw-kanban__card:hover {
            border-color: var(--nw-gold);
            box-shadow: 0 2px 8px rgba(10, 22, 40, 0.08);
        }

        .dark .nw-kanban__card {
            background: var(--nw-navy);
            border-color: rgba(201, 168, 76, 0.15);
            box-shadow: none;
        }

        .dark .nw-kanban__card:hover {
            border-color: var(--nw-gold);
        }

        .nw-kanban__brand {
            font-size: 0.875rem;
            font-weight: 600;
            color: #0f172a;
            line-height: 1.3;
        }

        .dark .nw-kanban__brand {
            color: #f1f5f9;
        }

        .nw-kanban__meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-top: 0.25rem;
            font-size: 0.75rem;
            color: #64748b;
        }

        .dark .nw-kanban__meta {
            color: #94a3b8;
        }

        .nw-kanban__vol {
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .nw-kanban__move {
            margin-top: 0.5rem;
            width: 100%;
            font-size: 0.75rem;
            color: #475569;
            background: #ffffff;
            border: 1px solid rgba(10, 22, 40, 0.15);
            border-radius: 0.375rem;
            padding: 0.3125rem 0.5rem;
            cursor: pointer;
        }

        .nw-kanban__move:focus {
            outline: 2px solid var(--nw-gold);
            outline-offset: 1px;
        }

        .dark .nw-kanban__move {
            color: #cbd5e1;
            background: var(--nw-navy-mid);
            border-color: rgba(255, 255, 255, 0.12);
        }

        .nw-kanban__empty,
        .nw-kanban__more {
            text-align: center;
            font-size: 0.75rem;
            color: #94a3b8;
            padding: 0.5rem 0;
        }

        .nw-kanban__empty {
            padding: 1.25rem 0;
        }
    </style>
    @endassets

    @php
        // Filament semantic badge color (from ConnectionStatus::color()) → column accent hex.
        $dotColor = [
            'success' => '#16a34a',
            'info' => '#2563eb',
            'warning' => '#d97706',
            'danger' => '#dc2626',
            'gray' => '#9ca3af',
        ];
    @endphp

    <div class="nw-kanban">
        @foreach ($this->columns() as $column)
            @php($status = $column['status'])
            <section
                class="nw-kanban__col"
                style="--dot: {{ $dotColor[$status->color()] ?? '#9ca3af' }}"
                wire:key="col-{{ $status->value }}"
                aria-label="{{ $status->label() }} column"
            >
                <header class="nw-kanban__head">
                    <span class="nw-kanban__title">
                        <span class="nw-kanban__dot" aria-hidden="true"></span>
                        {{ $status->label() }}
                    </span>
                    <span class="nw-kanban__count">{{ number_format($column['count']) }}</span>
                </header>

                <div class="nw-kanban__cards">
                    @forelse ($column['connections'] as $connection)
                        <article
                            wire:key="card-{{ $connection->id }}"
                            class="nw-kanban__card"
                        >
                            <div class="nw-kanban__brand">{{ $connection->brand }}</div>
                            <div class="nw-kanban__meta">
                                <span>{{ $connection->category ?? '—' }}</span>
                                <span class="nw-kanban__vol">{{ $connection->total_volume !== null ? number_format($connection->total_volume).' vol' : '' }}</span>
                            </div>

                            <select
                                class="nw-kanban__move"
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
                        </article>
                    @empty
                        <p class="nw-kanban__empty">No connections</p>
                    @endforelse

                    @if ($column['count'] > $column['connections']->count())
                        <p class="nw-kanban__more">
                            + {{ number_format($column['count'] - $column['connections']->count()) }} more
                        </p>
                    @endif
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
