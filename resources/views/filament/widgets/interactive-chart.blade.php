@php
    use Filament\Support\Facades\FilamentAsset;
    use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\Contracts\Support\Htmlable;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $isCollapsible = $this->isCollapsible();
    $type = $this->getType();
    $maxHeight = $this->getMaxHeight();
    $hasMaxHeight = filled($maxHeight) && $maxHeight !== '100%';
    $isEmpty = $this->isEmpty();

    $detailType = method_exists($this, 'getDetailType') ? $this->getDetailType() : 'ringkasan';
    $detailItems = method_exists($this, 'getDetailItems') ? $this->getDetailItems() : [];
    $detailKeys = array_values(array_map(fn (array $item): string => (string) $item['key'], $detailItems));

    $chartAccessibleLabel = trim(implode('. ', array_filter([
        $heading instanceof Htmlable ? strip_tags($heading->toHtml()) : $heading,
        $description instanceof Htmlable ? strip_tags($description->toHtml()) : $description,
    ], fn ($value): bool => filled($value))));
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section
        :description="$description"
        :heading="$heading"
        :collapsible="$isCollapsible"
    >
        <x-slot name="afterHeader">
            <button
                type="button"
                x-data
                @click="$dispatch('buka-detail-dashboard', { tipe: @js($detailType), kunci: 'semua' })"
                class="text-xs font-semibold text-teal-600 hover:text-teal-700 hover:underline transition cursor-pointer"
            >
                Lihat rincian &rarr;
            </button>
        </x-slot>

        <div
            @if ($pollingInterval = $this->getPollingInterval())
                wire:poll.{{ $pollingInterval }}="updateChartData"
            @endif
            @if ($isEmpty)
                style="display: none"
            @endif
        >
            <div
                class="fi-wi-chart-image"
                @if (filled($chartAccessibleLabel))
                    role="img"
                    aria-label="{{ $chartAccessibleLabel }}"
                @endif
            >
                <div
                    x-load
                    x-load-src="{{ FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                    wire:ignore
                    data-chart-type="{{ $type }}"
                    x-data="chart({
                        cachedData: @js($this->getCachedData()),
                        options: @js($this->getOptions()),
                        type: @js($type),
                    })"
                    @click="
                        const c = typeof getChart === 'function' ? getChart() : null;
                        if (c) {
                            let pts = c.getElementsAtEventForMode($event, 'nearest', { intersect: true }, true);
                            if ((!pts || pts.length === 0) && @js($type) === 'bar') {
                                pts = c.getElementsAtEventForMode($event, 'index', { intersect: false, axis: 'y' }, true);
                            }
                            const keys = @js($detailKeys);
                            if (pts && pts.length > 0 && keys[pts[0].index] !== undefined) {
                                $dispatch('buka-detail-dashboard', { tipe: @js($detailType), kunci: String(keys[pts[0].index]) });
                            }
                        }
                    "
                    title="Klik pada grafik untuk melihat rincian anggota & Kartu Keluarga"
                    {{
                        (new FilamentComponentAttributeBag)
                            ->color(ChartWidgetComponent::class, $color)
                            ->class([
                                'fi-wi-chart-frame',
                                'fi-wi-chart-canvas-ctn',
                                'cursor-pointer',
                                'fi-wi-chart-frame-no-aspect-ratio' => $hasMaxHeight,
                            ])
                    }}
                >
                    <canvas
                        x-ref="canvas"
                        aria-hidden="true"
                        @style([
                            'width: 100%',
                            'height: 100%; max-height: 100%' => ! $hasMaxHeight,
                            ('max-height: ' . e($maxHeight)) => $hasMaxHeight,
                        ])
                    ></canvas>

                    <span aria-hidden="true" x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
                    <span aria-hidden="true" x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
                    <span aria-hidden="true" x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
                    <span aria-hidden="true" x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
                    <span aria-hidden="true" x-ref="tooltipBackgroundColorElement" class="fi-wi-chart-tooltip-bg-color"></span>
                    <span aria-hidden="true" x-ref="tooltipTextColorElement" class="fi-wi-chart-tooltip-text-color"></span>
                    <span aria-hidden="true" x-ref="tooltipBorderColorElement" class="fi-wi-chart-tooltip-border-color"></span>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
