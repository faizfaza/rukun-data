<x-filament-panels::page>
    <form wire:submit="simpan" class="space-y-6">
        {{ $this->form }}

        <div class="flex items-center justify-end gap-3 pt-2">
            <x-filament::button type="submit" icon="heroicon-m-check">
                Simpan Pengaturan Desa
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>

