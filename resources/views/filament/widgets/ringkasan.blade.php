<x-filament-widgets::widget>
    <div class="fi-wi-stats-overview">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
            @foreach ($stats as $stat)
                <a
                    href="{{ $stat['url'] ?? '#' }}"
                    class="rd-stat-card group block p-4 rounded-2xl bg-white border border-gray-100 shadow-sm hover:shadow-md hover:border-teal-200 transition-all duration-200 cursor-pointer"
                    wire:key="stat-{{ $loop->index }}"
                >
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 group-hover:bg-teal-50 group-hover:text-teal-700 transition">
                            {{ $stat['category'] ?? 'Ringkasan' }}
                        </span>
                        <div class="rd-stat-icon text-gray-400 group-hover:text-teal-600 transition">
                            {{ \Filament\Support\generate_icon_html($stat['icon']) }}
                        </div>
                    </div>

                    <div>
                        <span
                            class="rd-stat-value text-2xl font-bold text-gray-900 group-hover:text-teal-700 transition"
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

                        <div class="rd-stat-label text-xs font-semibold text-gray-700 mt-1">{{ $stat['label'] }}</div>
                        <div class="rd-stat-desc text-[11px] text-gray-500 mt-0.5 line-clamp-1">{{ $stat['description'] }}</div>
                        <div class="mt-2.5 flex items-center gap-1 text-[11px] font-medium text-teal-600 opacity-0 group-hover:opacity-100 transition-opacity">
                            <span>Lihat rincian</span>
                            <span>&rarr;</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
