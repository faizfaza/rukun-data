<x-filament-widgets::widget>
    <div class="fi-wi-stats-overview">
        <div class="rd-stat-grid">
            @foreach ($stats as $stat)
                @php
                    $palette = match ($loop->index % 4) {
                        0 => ['icon_bg' => 'bg-blue-50 text-blue-600 border border-blue-200', 'hover' => 'hover:border-blue-300'],
                        1 => ['icon_bg' => 'bg-emerald-50 text-emerald-600 border border-emerald-200', 'hover' => 'hover:border-emerald-300'],
                        2 => ['icon_bg' => 'bg-purple-50 text-purple-600 border border-purple-200', 'hover' => 'hover:border-purple-300'],
                        default => ['icon_bg' => 'bg-amber-50 text-amber-600 border border-amber-200', 'hover' => 'hover:border-amber-300'],
                    };
                @endphp
                <article class="rd-stat-card border border-gray-200 {{ $palette['hover'] }} transition" wire:key="stat-{{ $loop->index }}">
                    <div class="rd-stat-icon {{ $palette['icon_bg'] }}">
                        {{ \Filament\Support\generate_icon_html($stat['icon']) }}
                    </div>

                    <div>
                        <span
                            class="rd-stat-value"
                            x-data="{
                                value: 0,
                                target: {{ (int) $stat['value'] }},
                                init() {
                                    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                                        this.value = this.target
                                        return
                                    }

                                    const start = performance.now()
                                    const duration = 720

                                    const tick = (now) => {
                                        const progress = Math.min(1, (now - start) / duration)
                                        const eased = 1 - Math.pow(1 - progress, 3)
                                        this.value = Math.round(this.target * eased)

                                        if (progress < 1) {
                                            requestAnimationFrame(tick)
                                        }
                                    }

                                    requestAnimationFrame(tick)
                                },
                            }"
                            x-text="new Intl.NumberFormat('id-ID').format(value)"
                        >{{ number_format($stat['value'], 0, ',', '.') }}</span>

                        <div class="rd-stat-label">{{ $stat['label'] }}</div>
                        <div class="rd-stat-desc">{{ $stat['description'] }}</div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
