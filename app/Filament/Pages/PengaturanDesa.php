<?php

namespace App\Filament\Pages;

use App\Models\Wilayah;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;

class PengaturanDesa extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Pengaturan Desa';

    protected static ?string $title = 'Pengaturan & Kustomisasi Desa';

    protected static ?string $slug = 'pengaturan-desa';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.pengaturan-desa';

    #[Url(as: 'desa')]
    public ?int $desaId = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isSuperadmin() || $user?->isAdmin() || $user?->isAdminDesa());
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $user = auth()->user();
        if ($user?->isAdminDesa() && $user->id_wilayah) {
            $this->desaId = $user->id_wilayah;
        } elseif (! $this->desaId) {
            $this->desaId = Wilayah::desaKlienDefault()?->id;
        } else {
            // Jika URL lama mengarah ke desa abjad pertama (mis. 19 Nopember) yang belum aktif/dikonfigurasi,
            // arahkan otomatis ke Desa Klien aktif (mis. Bojongsoang).
            $aktifIds = Wilayah::idDesaKlienAktif();
            if ($aktifIds !== [] && ! in_array($this->desaId, $aktifIds, true)) {
                $cekDesa = Wilayah::where('level', 'desa')->find($this->desaId);
                if (! $cekDesa || empty($cekDesa->pengaturan)) {
                    $this->desaId = Wilayah::desaKlienDefault()?->id;
                }
            }
        }

        $this->muatPengaturanDesa();
    }

    public function muatPengaturanDesa(): void
    {
        $desa = $this->getDesaTerpilih();
        $pengaturan = $desa ? $desa->pengaturanDesa() : Wilayah::DEFAULT_PENGATURAN_DESA;

        $this->form->fill([
            'desa_id' => $desa?->id,
            'nama_tampilan' => $pengaturan['nama_tampilan'] ?: ($desa?->nama ?? ''),
            'slogan' => $pengaturan['slogan'] ?? 'Sistem Informasi Kependudukan Desa',
            'warna_tema' => $pengaturan['warna_tema'] ?? 'teal',
            'nama_kepala_desa' => $pengaturan['nama_kepala_desa'] ?? '',
            'nip_kepala_desa' => $pengaturan['nip_kepala_desa'] ?? '',
            'alamat_kantor' => $pengaturan['alamat_kantor'] ?? '',
            'telepon_desa' => $pengaturan['telepon_desa'] ?? '',
            'wajib_validasi_berjenjang' => (bool) ($pengaturan['wajib_validasi_berjenjang'] ?? true),
            'rw_lihat_penuh' => (bool) ($pengaturan['rw_lihat_penuh'] ?? false),
            'arsip_otomatis_aktif' => (bool) ($pengaturan['arsip_otomatis_aktif'] ?? true),
            'interval_arsip_jam' => (int) ($pengaturan['interval_arsip_jam'] ?? 36),
        ]);
    }

    public function getDesaTerpilih(): ?Wilayah
    {
        $user = auth()->user();
        if ($user?->isAdminDesa() && $user->id_wilayah) {
            return Wilayah::where('level', 'desa')->find($user->id_wilayah);
        }

        if ($this->desaId) {
            return Wilayah::where('level', 'desa')->find($this->desaId);
        }

        return Wilayah::desaKlienDefault();
    }

    public function form(Schema $schema): Schema
    {
        $user = auth()->user();
        $isGlobal = (bool) ($user?->isSuperadmin() || $user?->isAdmin());

        $warnaOptions = [];
        $warnaDescriptions = [];
        foreach (Wilayah::PALET_WARNA_DESA as $key => $info) {
            $warnaOptions[$key] = new HtmlString(
                '<span class="inline-flex items-center gap-2 font-semibold text-gray-900">'
                .'<span class="inline-block h-4 w-4 rounded-full border border-black/10 shadow-inner" style="background-color: '.$info['hex'].'"></span>'
                .e($info['label'])
                .'</span>'
            );
            $warnaDescriptions[$key] = $info['desc'];
        }

        return $schema
            ->components([
                Section::make('Pilih Desa Klien (Khusus Penyedia Jasa / Superadmin)')
                    ->description('Sebagai penyedia jasa, Anda dapat memilih desa mana yang ingin dikonfigurasi atau dikustomisasi.')
                    ->icon('heroicon-o-building-office-2')
                    ->visible($isGlobal)
                    ->schema([
                        Select::make('desa_id')
                            ->label('Desa / Kelurahan')
                            ->options(function (): array {
                                $ids = Wilayah::idDesaKlienAktif();
                                if ($this->desaId && ! in_array($this->desaId, $ids, true)) {
                                    $ids[] = $this->desaId;
                                }

                                return Wilayah::where('level', 'desa')
                                    ->whereIn('id', $ids)
                                    ->with([Wilayah::RELASI_LELUHUR])
                                    ->orderBy('nama')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (Wilayah $w): array => [$w->id => "{$w->nama} ({$w->rantaiIndukNama()})"])
                                    ->all();
                            })
                            ->getSearchResultsUsing(fn (string $search): array => Wilayah::where('level', 'desa')
                                ->where('nama', 'like', '%'.trim($search).'%')
                                ->with([Wilayah::RELASI_LELUHUR])
                                ->orderBy('nama')
                                ->limit(25)
                                ->get()
                                ->mapWithKeys(fn (Wilayah $w): array => [$w->id => "{$w->nama} ({$w->rantaiIndukNama()})"])
                                ->all()
                            )
                            ->getOptionLabelUsing(function ($value): ?string {
                                if (! $value) {
                                    return null;
                                }
                                $w = Wilayah::with([Wilayah::RELASI_LELUHUR])->find($value);

                                return $w ? "{$w->nama} ({$w->rantaiIndukNama()})" : null;
                            })
                            ->searchable()
                            ->live()
                            ->helperText('Menampilkan desa klien yang sudah aktif. Ketik nama desa untuk mencari desa lain di seluruh Indonesia.')
                            ->afterStateUpdated(function (?int $state): void {
                                $this->desaId = $state;
                                $this->muatPengaturanDesa();
                            }),
                    ]),

                Section::make('1. Identitas & Pemerintahan Desa')
                    ->description('Informasi resmi desa yang ditampilkan pada bagian kiri sidebar dashboard serta kop surat cetak KK dan rekap warga.')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        TextInput::make('nama_tampilan')
                            ->label('Nama Tampilan Desa di Sidebar')
                            ->placeholder('Contoh: Desa Bojongsoang')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('slogan')
                            ->label('Subjudul / Slogan Desa')
                            ->placeholder('Contoh: Sistem Informasi Kependudukan Desa')
                            ->maxLength(120),

                        TextInput::make('nama_kepala_desa')
                            ->label('Nama Kepala Desa / Lurah')
                            ->placeholder('Contoh: H. Sutiyo Wibowo, S.Sos.')
                            ->helperText('Ditampilkan pada bagian pengesahan & tanda tangan dokumen cetak.')
                            ->maxLength(120),

                        TextInput::make('nip_kepala_desa')
                            ->label('NIP / Nomor Induk Kepala Desa (Opsional)')
                            ->placeholder('Contoh: 19780512 200501 1 004')
                            ->maxLength(50),

                        TextInput::make('alamat_kantor')
                            ->label('Alamat Kantor Pemerintahan Desa')
                            ->placeholder('Contoh: Jl. Raya Bojongsoang No. 1, Kab. Bandung')
                            ->maxLength(255),

                        TextInput::make('telepon_desa')
                            ->label('Telepon / Kontak Pelayanan Desa')
                            ->placeholder('Contoh: (022) 7561234 / 0812-3456-7890')
                            ->maxLength(50),
                    ])
                    ->columns(2),

                Section::make('2. Kustomisasi Tampilan & Tema Warna Desa')
                    ->description('Pilih warna aksen utama untuk dashboard desa ini (berlaku untuk Admin Desa, Admin RW, dan Admin RT di bawah desa ini). Palet telah dikurasi oleh penyedia jasa agar tetap kontras dan nyaman dibaca.')
                    ->icon('heroicon-o-swatch')
                    ->schema([
                        Radio::make('warna_tema')
                            ->label('Palet Warna Tema Desa')
                            ->options($warnaOptions)
                            ->descriptions($warnaDescriptions)
                            ->default('teal')
                            ->required()
                            ->columns(2),
                    ]),

                Section::make('3. Kebijakan Validasi Berjenjang & Privasi Data Desa')
                    ->description('Atur aturan operasional verifikasi perubahan data serta visibilitas data sensitif di tingkat RW.')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Toggle::make('wajib_validasi_berjenjang')
                            ->label('Wajibkan Verifikasi / Approval Berjenjang (RT → RW → Desa)')
                            ->helperText('Jika aktif, setiap penambahan keluarga, perubahan data/aset/status, dan mutasi pindah dari Admin RT akan masuk ke antrean Validasi RW lalu disahkan oleh Admin Desa.')
                            ->default(true),

                        Toggle::make('rw_lihat_penuh')
                            ->label('Izinkan Admin RW Melihat NIK & Nomor KK Secara Penuh (Tanpa Sensor)')
                            ->helperText('Secara bawaan, NIK dan No KK disamarkan bagi Admin RW demi privasi warga. Aktifkan bila Kepala Desa mengizinkan Ketua RW melihat digit penuh.')
                            ->default(false),
                    ]),

                Section::make('4. Jadwal Arsip Snapshot Berkala & Dataset Latih (Otomatis)')
                    ->description('Sistem secara otomatis memotret (mengarsipkan) kondisi utuh seluruh warga desa setiap melewati interval jam yang ditentukan apabila terdapat perubahan data. Rekaman historis ini digunakan untuk audit lintas waktu dan pelatihan model AI/Machine Learning.')
                    ->icon('heroicon-o-archive-box')
                    ->schema([
                        Toggle::make('arsip_otomatis_aktif')
                            ->label('Aktifkan Arsip Snapshot Berkala Seluruh Warga (Otomatis)')
                            ->helperText('Jika aktif, sistem otomatis merekam snapshot seluruh keluarga & warga desa setiap kali interval jam tercapai dan ada perubahan data.')
                            ->default(true)
                            ->columnSpanFull(),

                        TextInput::make('interval_arsip_jam')
                            ->label('Interval Waktu Arsip Otomatis (Satuan Jam)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(8760)
                            ->default(36)
                            ->suffix('Jam')
                            ->required()
                            ->helperText('Contoh: 36 jam (1,5 hari), 48 jam (2 hari), atau 240 jam (10 hari). Sistem otomatis membuat snapshot baru ketika waktu sejak snapshot terakhir sudah melewati jumlah jam ini dan terdapat perubahan data warga.'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function simpan(): void
    {
        abort_unless(static::canAccess(), 403);

        $desa = $this->getDesaTerpilih();
        if (! $desa) {
            Notification::make()
                ->title('Desa belum dipilih')
                ->body('Silakan pilih atau tambahkan wilayah tingkat Desa terlebih dahulu.')
                ->danger()
                ->send();

            return;
        }

        $formState = $this->form->getState();
        $warna = (string) ($formState['warna_tema'] ?? 'teal');
        if (! array_key_exists($warna, Wilayah::PALET_WARNA_DESA)) {
            $warna = 'teal';
        }

        $pengaturanBaru = array_merge($desa->pengaturanDesa(), [
            'nama_tampilan' => trim((string) ($formState['nama_tampilan'] ?? $desa->nama)),
            'slogan' => trim((string) ($formState['slogan'] ?? 'Sistem Informasi Kependudukan Desa')),
            'warna_tema' => $warna,
            'nama_kepala_desa' => trim((string) ($formState['nama_kepala_desa'] ?? '')),
            'nip_kepala_desa' => trim((string) ($formState['nip_kepala_desa'] ?? '')),
            'alamat_kantor' => trim((string) ($formState['alamat_kantor'] ?? '')),
            'telepon_desa' => trim((string) ($formState['telepon_desa'] ?? '')),
            'wajib_validasi_berjenjang' => (bool) ($formState['wajib_validasi_berjenjang'] ?? true),
            'rw_lihat_penuh' => (bool) ($formState['rw_lihat_penuh'] ?? false),
            'arsip_otomatis_aktif' => (bool) ($formState['arsip_otomatis_aktif'] ?? true),
            'interval_arsip_jam' => max(1, (int) ($formState['interval_arsip_jam'] ?? 36)),
        ]);

        $desa->update([
            'pengaturan' => $pengaturanBaru,
        ]);

        \App\Models\ArsipSnapshotDesa::prosesSnapshotOtomatisDesa($desa->fresh());

        auth()->user()?->unsetRelation('wilayah');

        Notification::make()
            ->title('Pengaturan Desa Berhasil Disimpan')
            ->body("Kustomisasi dan kebijakan untuk {$pengaturanBaru['nama_tampilan']} telah diperbarui.")
            ->success()
            ->send();
    }
}
