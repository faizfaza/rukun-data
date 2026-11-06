<x-filament-widgets::widget>
    <div class="fi-wi-stats-overview">
        <div class="rd-stat-grid">
            @foreach ($stats as $stat)
                <article class="rd-stat-card" wire:key="stat-{{ $loop->index }}">
                    <div class="rd-stat-icon">
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
