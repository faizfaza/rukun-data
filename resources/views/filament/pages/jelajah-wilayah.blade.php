<x-filament-panels::page>
    <div class="flex flex-col gap-6">
        {{-- Fase 3.1: breadcrumb hanya dari titik awal pengguna ke bawah. --}}
        <nav class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            @foreach ($this->breadcrumbsRantai() as $i => $crumb)
                @if ($i > 0)
                    <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 text-gray-400" />
                @endif

                @if ($crumb['aktif'])
                    <span class="font-semibold text-gray-950 dark:text-white">{{ $crumb['nama'] }}</span>
                @else
                    <a href="{{ $crumb['url'] }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $crumb['nama'] }}</a>
                @endif
            @endforeach
        </nav>

        {{-- Ringkasan jumlah node aktif. --}}
        @php($ringkasan = $this->ringkasan())
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Level</div>
                <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $this->labelLevel() }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Anak langsung</div>
                <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($ringkasan['anak'], 0, ',', '.') }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">KK aktif</div>
                <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($ringkasan['kk'], 0, ',', '.') }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Anggota aktif</div>
                <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($ringkasan['anggota'], 0, ',', '.') }}</div>
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
