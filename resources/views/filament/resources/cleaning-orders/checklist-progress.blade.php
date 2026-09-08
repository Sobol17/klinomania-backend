@php
    $isEmpty = $metrics['total'] === 0;
    $isComplete = ! $isEmpty && $metrics['remaining'] === 0;
@endphp

<div
    class="km-checklist-progress {{ $isComplete ? 'is-complete' : '' }}"
    role="group"
    aria-label="Ход выполнения чек-листа"
>
    @if ($isEmpty)
        <div class="km-checklist-progress__empty">
            <strong>Для этой услуги нет чек-листа</strong>
            <span>Клинеру не нужно отмечать отдельные пункты для этого заказа.</span>
        </div>
    @else
        <div class="km-checklist-progress__header">
            <div>
                <span class="km-checklist-progress__eyebrow">Ход выполнения</span>
                <strong>{{ $isComplete ? 'Все работы отмечены' : "Выполнено {$metrics['completed']} из {$metrics['total']}" }}</strong>
                <span>{{ $isComplete ? 'Чек-лист закрыт' : "Осталось выполнить: {$metrics['remaining']}" }}</span>
            </div>
            <div class="km-checklist-progress__percent" aria-hidden="true">{{ $metrics['percent'] }}%</div>
        </div>

        <div
            class="km-checklist-progress__track"
            role="progressbar"
            aria-label="Общий прогресс"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-valuenow="{{ $metrics['percent'] }}"
            aria-valuetext="Выполнено {{ $metrics['completed'] }} из {{ $metrics['total'] }}"
        >
            <span style="width: {{ $metrics['percent'] }}%"></span>
        </div>

        <div class="km-checklist-progress__sections">
            @foreach ($sections as $section)
                <div class="km-checklist-progress__section">
                    <div class="km-checklist-progress__section-heading">
                        <span>{{ $section['label'] }}</span>
                        <strong>{{ $section['completed'] }} / {{ $section['total'] }}</strong>
                    </div>
                    <div class="km-checklist-progress__section-track" aria-hidden="true">
                        <span style="width: {{ $section['percent'] }}%"></span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

@once
    <style>
        .km-checklist-progress {
            --km-progress-accent: var(--primary-500);
            padding: 1.125rem;
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            background: linear-gradient(135deg, var(--gray-50), white 68%);
            color: var(--gray-950);
        }

        .km-checklist-progress.is-complete {
            --km-progress-accent: var(--success-500);
        }

        .km-checklist-progress__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .km-checklist-progress__header > div:first-child,
        .km-checklist-progress__empty {
            display: grid;
            gap: 0.2rem;
        }

        .km-checklist-progress__eyebrow {
            color: var(--gray-500);
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .km-checklist-progress__header strong,
        .km-checklist-progress__empty strong {
            font-size: 1rem;
            font-weight: 700;
        }

        .km-checklist-progress__header span:last-child,
        .km-checklist-progress__empty span {
            color: var(--gray-500);
            font-size: 0.8125rem;
        }

        .km-checklist-progress__percent {
            color: var(--km-progress-accent);
            font-size: 1.75rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            line-height: 1;
        }

        .km-checklist-progress__track,
        .km-checklist-progress__section-track {
            overflow: hidden;
            border-radius: 999px;
            background: var(--gray-200);
        }

        .km-checklist-progress__track {
            height: 0.5rem;
            margin-top: 1rem;
        }

        .km-checklist-progress__track span,
        .km-checklist-progress__section-track span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: var(--km-progress-accent);
        }

        .km-checklist-progress__sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .km-checklist-progress__section {
            padding: 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: 0.5rem;
            background: white;
        }

        .km-checklist-progress__section-heading {
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
            color: var(--gray-600);
            font-size: 0.75rem;
        }

        .km-checklist-progress__section-heading strong {
            flex-shrink: 0;
            color: var(--gray-950);
            font-variant-numeric: tabular-nums;
        }

        .km-checklist-progress__section-track {
            height: 0.25rem;
            margin-top: 0.55rem;
        }

        .dark .km-checklist-progress {
            border-color: var(--gray-700);
            background: linear-gradient(135deg, var(--gray-900), var(--gray-950) 68%);
            color: white;
        }

        .dark .km-checklist-progress__section {
            border-color: var(--gray-700);
            background: var(--gray-900);
        }

        .dark .km-checklist-progress__section-heading strong {
            color: white;
        }

        .dark .km-checklist-progress__track,
        .dark .km-checklist-progress__section-track {
            background: var(--gray-700);
        }

        @media (max-width: 40rem) {
            .km-checklist-progress__percent {
                font-size: 1.35rem;
            }
        }
    </style>
@endonce
