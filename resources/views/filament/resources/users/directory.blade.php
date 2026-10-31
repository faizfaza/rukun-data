<x-filament-panels::page>
    <x-filament::section heading="Wilayah Penugasan Admin">
        <div class="flex flex-wrap gap-3 mb-4">
            <x-filament::button wire:click="pilih(0)" color="gray">Semua wilayah</x-filament::button>
            @if ($node = $this->node())
                @foreach ($node->ancestors()->push($node) as $ancestor)
                    <x-filament::button wire:click="pilih({{ $ancestor->id }})" color="info">{{ $ancestor->nama }}</x-filament::button>
                @endforeach
            @endif
        </div>
        <label>Cari nama/kode wilayah <input class="border rounded p-2 mb-3" wire:model.live.debounce.300ms="cariWilayah" placeholder="Jawa Barat / Bojongsoang / kode"></label>
        <p class="mb-3">{{ $this->counters()[0] }} admin · {{ $this->counters()[1] }} keluarga dalam cakupan pilihan.</p>
        <div class="flex flex-wrap gap-3 mb-4">
            @php($nodeOptions = $this->nodes())
            @php($nodeLabels = \App\Support\WilayahLabels::for($nodeOptions))
            @foreach ($nodeOptions as $child)
                <x-filament::button wire:click="pilih({{ $child->id }})" color="gray">{{ $nodeLabels[$child->id] }}</x-filament::button>
            @endforeach
        </div>
        <div class="flex gap-3 mb-3"><x-filament::button wire:click="mundurWilayah" color="gray">Sebelumnya</x-filament::button><span>Halaman {{ $halamanWilayah }}</span><x-filament::button wire:click="lanjutWilayah" color="gray">Berikutnya</x-filament::button></div>
        <label class="mr-4"><input type="checkbox" wire:model.live="turunan"> Termasuk wilayah turunan</label>
        <label><input type="checkbox" wire:model.live="global"> Admin Global</label>
        @if ($node)
            <a class="text-primary-700 underline" href="{{ \App\Filament\Resources\WilayahResource::getUrl('index', ['parent' => $node->id]) }}">Kelola wilayah ini</a>
        @endif
    </x-filament::section>
    {{ $this->table }}
</x-filament-panels::page>
