<?php

namespace App\Filament\Resources\ValidasiPerubahanResource\Pages;

use App\Filament\Resources\ValidasiPerubahanResource;
use App\Models\ValidasiPerubahan;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListValidasiPerubahans extends ListRecords
{
    protected static string $resource = ValidasiPerubahanResource::class;

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua Pengajuan'),
            'menunggu_rw' => Tab::make('Menunggu RW')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ValidasiPerubahan::STATUS_MENUNGGU_RW))
                ->badge(fn (): int => ValidasiPerubahanResource::getEloquentQuery()->where('status', ValidasiPerubahan::STATUS_MENUNGGU_RW)->count())
                ->badgeColor('warning'),
            'menunggu_desa' => Tab::make('Menunggu Desa')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ValidasiPerubahan::STATUS_MENUNGGU_DESA))
                ->badge(fn (): int => ValidasiPerubahanResource::getEloquentQuery()->where('status', ValidasiPerubahan::STATUS_MENUNGGU_DESA)->count())
                ->badgeColor('info'),
            'disetujui' => Tab::make('Terverifikasi Desa')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ValidasiPerubahan::STATUS_DISETUJUI))
                ->badgeColor('success'),
            'ditolak' => Tab::make('Ditolak')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ValidasiPerubahan::STATUS_DITOLAK))
                ->badgeColor('danger'),
        ];
    }
}

