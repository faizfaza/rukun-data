<x-filament-panels::page class="fi-dashboard-page">
    <div class="rd-dash-stack">
        {{-- 1. Banner Utama + Kategori Ringkasan Kependudukan (4 KPI Prioritas) --}}
        @livewire(\App\Filament\Widgets\RingkasanWidget::class)

        {{-- 2. Kategori Demografi Penduduk --}}
        <section class="rd-dash-section">
            <div class="rd-dash-section-head">
                <span class="rd-dash-eyebrow">Demografi Penduduk</span>
                <h3 class="rd-dash-section-title">Profil Usia &amp; Jenis Kelamin</h3>
                <p class="rd-dash-section-sub">Gambaran struktur kelompok umur serta komposisi warga laki-laki dan perempuan.</p>
            </div>

            <div class="rd-dash-grid-2">
                @livewire(\App\Filament\Widgets\KelompokUmurChart::class)
                @livewire(\App\Filament\Widgets\JenisKelaminChart::class)
            </div>
        </section>

        {{-- 3. Kategori Sosial & Ekonomi --}}
        <section class="rd-dash-section">
            <div class="rd-dash-section-head">
                <span class="rd-dash-eyebrow">Sosial &amp; Ekonomi</span>
                <h3 class="rd-dash-section-title">Pendidikan &amp; Mata Pencaharian</h3>
                <p class="rd-dash-section-sub">Pemetaan tingkat pendidikan terakhir dan bidang pekerjaan utama warga.</p>
            </div>

            <div class="rd-dash-grid-2">
                @livewire(\App\Filament\Widgets\PendidikanChart::class)
                @livewire(\App\Filament\Widgets\PekerjaanChart::class)
            </div>
        </section>

        {{-- 4. Kategori Sebaran Wilayah --}}
        <section class="rd-dash-section">
            <div class="rd-dash-section-head">
                <span class="rd-dash-eyebrow">Sebaran Wilayah</span>
                <h3 class="rd-dash-section-title">Distribusi Keluarga per RT</h3>
                <p class="rd-dash-section-sub">Perbandingan jumlah Kartu Keluarga yang tercatat di masing-masing RT.</p>
            </div>

            <div>
                @livewire(\App\Filament\Widgets\KeluargaPerRtChart::class)
            </div>
        </section>
    </div>
</x-filament-panels::page>
