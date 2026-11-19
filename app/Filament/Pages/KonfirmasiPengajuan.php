<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ValidasiPerubahanResource;
use App\Models\ValidasiPerubahan;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class KonfirmasiPengajuan extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'konfirmasi-pengajuan';

    protected static ?string $title = 'Konfirmasi Pengajuan Perubahan';

    protected string $view = 'filament.pages.konfirmasi-pengajuan';

    /**
     * @var Collection<int, ValidasiPerubahan>
     */
    public Collection $tiketList;

    public function mount(): void
    {
        $rawParam = (string) request()->query('tiket', '');
        $ids = collect(explode(',', $rawParam))
            ->map(fn (string $v): int => (int) trim($v))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $query = ValidasiPerubahanResource::getEloquentQuery();

        if ($ids !== []) {
            $this->tiketList = (clone $query)
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->get();
        } else {
            // Fallback: tampilkan tiket terbaru milik pengguna saat ini
            $this->tiketList = (clone $query)
                ->where('id_pengguna', auth()->id())
                ->latest('id')
                ->limit(1)
                ->get();
        }
    }

    /**
     * Bangun URL halaman konfirmasi untuk satu atau beberapa ID tiket validasi.
     *
     * @param  list<int>  $ticketIds
     */
    public static function urlForTickets(array $ticketIds): string
    {
        $cleanIds = array_values(array_unique(array_filter(array_map('intval', $ticketIds), fn (int $id): bool => $id > 0)));

        if ($cleanIds === []) {
            return static::getUrl();
        }

        return static::getUrl(['tiket' => implode(',', $cleanIds)]);
    }
}
