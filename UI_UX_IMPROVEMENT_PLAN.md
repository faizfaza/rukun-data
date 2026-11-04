# Rencana Perbaikan UI/UX — Rukun Data Admin

> Dokumen audit dan rencana implementasi. **Tidak ada perubahan kode, konfigurasi, basis data, atau logika bisnis** yang dilakukan dalam penyusunan dokumen ini. Seluruh temuan diturunkan dari pembacaan kode dan audit runtime headless Chrome pada aplikasi lokal.

- **Proyek:** `D:\Semester 7\protein\app`
- **Stack:** Laravel 12, Filament 5, Tailwind 4, Livewire
- **Halaman yang diaudit:** `/admin` (dashboard), `/admin/pengguna` (Kelola Admin), `/admin/wilayah` (Wilayah)
- **Akun audit:** `superadmin` / `password`
- **Viewport audit:** 1920×1080 dan 400×900 (headless Chrome)
- **Basis kode:** working tree saat audit (banyak perubahan belum di-commit)
- **Tanggal audit:** 4 November 2026

---

## 1. Executive Summary

Secara visual, Rukun Data sudah memiliki identitas yang kuat dan konsisten: permukaan terang, aksen near-black, aksen sekunder indigo `#4755ae`, sudut membulat, tipografi rapat, dan sidebar terang tanpa topbar. Audit ini **tidak menyarankan perubahan identitas visual**. Masalah utama bukan estetika, melainkan **prioritas informasi, kejelasan istilah, aksesibilitas, dan beberapa cacat data pada grafik**.

Tiga masalah paling berdampak:

1. **Halaman Kelola Admin menaruh tugas utama di bawah lipatan layar.** Panel penelusuran wilayah memakan seluruh viewport 1080p; tabel admin baru dimulai pada `y≈1086`, sehingga pengguna harus menggulir tanpa petunjuk bahwa tabel ada di bawah. Ini adalah temuan **High**.
2. **Grafik “Keluarga per RT” menampilkan label RT yang duplikat** karena hanya memakai `wilayah.nama` tanpa konteks induk (RW/desa). Grafik menjadi ambigu dan tidak dapat ditindaklanjuti. Ini adalah temuan **High** karena menyangkut kebenaran pembacaan data, bukan sekadar tampilan.
3. **Beberapa kontrol dan teks penting gagal pada aksesibilitas keyboard/kontras**: `rd-chip`, `rd-list-btn`, dan `rd-quiet-link` tidak memiliki `:focus-visible`, sementara `--rd-muted-soft` (`#9ca3af`) dipakai untuk teks 10–11px di atas putih. Ini temuan **Medium** yang perlu diperbaiki sebelum rilis lebih luas.

Selain itu, terdapat **inkonsistensi istilah** yang membingungkan: `Kelola Admin` di navigasi tetapi `Admin` di judul halaman; `Admin Global` sebagai checkbox yang sebenarnya berarti “hanya admin tanpa penugasan”; `Wilayah Penugasan Admin` untuk panel yang sebenarnya adalah penjelajah wilayah; serta `Master Wilayah`, `Daftar`, dan `Jelajah` yang bercampur. Penyeragaman istilah akan memberi perbaikan persepsi yang besar dengan risiko teknis rendah.

**Catatan penting tentang screenshot:** screenshot yang disertakan menunjukkan label `Tombol wilayah` dan `Tombol Admin`. Pada kode saat ini (`ListWilayah.php:78`, `ListUsers.php:139`) dan pada runtime live, label yang benar adalah **`Tambah wilayah`** dan **`Tambah Admin`**. Perbedaan ini **tidak dilaporkan sebagai bug terkonfirmasi**; kemungkinan berasal dari build/annotation lama. Verifikasi ulang sebelum mengubah apa pun. Demikian pula, jarak kosong besar yang terlihat pada screenshot tidak tereproduksi pada runtime 1920×1080; temuan spasi di bawah hanya bersifat poles opsional.

### Ringkasan prioritas

| Prioritas | Jumlah temuan | Fokus |
|---|---:|---|
| Critical | 0 | Tidak ada kegagalan total fungsi |
| High | 4 | Tabel admin di bawah lipatan, label RT duplikat, urutan wilayah arbitrer, navigasi halaman wilayah tanpa batas |
| Medium | 9 | Istilah, cakupan pencarian/filter, aksesibilitas fokus, kontras, badge filter `0`, kejelasan status |
| Low | 8 | Kepadatan grid, tooltip, label grafik kosong, poles spasi, microcopy |

---

## 2. Detailed Findings

Kode warna tingkat keparahan:

- **Critical** — fungsi utama tidak dapat dipakai atau data salah secara material.
- **High** — menghambat alur utama, menyesatkan pembacaan data, atau menyebabkan kehilangan konteks.
- **Medium** — menyulitkan penggunaan, aksesibilitas, atau konsistensi; perlu diperbaiki dalam siklus ini.
- **Low** — poles, kepadatan, dan peningkatan pengalaman.

### 2.1 Dashboard `/admin`

#### D-01 — Label “Keluarga per RT” duplikat — **High**

**Bukti:** `app/Filament/Widgets/KeluargaPerRtChart.php:29` hanya memilih `wilayah.nama as label`; `:31` mengelompokkan per `keluarga.id_wilayah` dan `wilayah.nama`; `:32` mengurutkan berdasarkan `keluarga.id_wilayah`. Nama RT seperti `RT 01`, `RT 02` berulang di RW/desa berbeda. Audit runtime menampilkan label `RT 01` dan `RT 02` lebih dari sekali.

**Dampak:** Petugas tidak dapat membedakan RT mana yang dimaksud. Grafik kehilangan nilai operasional karena tujuannya adalah melihat RT dengan data tertinggal.

**Akar masalah:** Label tidak memuat rantai induk atau kode unik; pengurutan memakai ID yang tidak bermakna bagi pengguna.

**Rekomendasi:** Bangun label yang memuat konteks, misalnya `RT 01 · RW 02 · Sukamaju` atau `03.02.01 · RT 01`. Urutkan berdasarkan `wilayah.path` atau `kode`, bukan `id_wilayah`. Bila label panjang, pertahankan tooltip berisi alamat lengkap. Hindari N+1: ambil rantai induk dalam satu query agregat atau gunakan relasi leluhur yang sudah dimuat.

**Kriteria penerimaan:**
- Tidak ada dua batang dengan label identik pada satu grafik.
- Urutan batang deterministik dan sesuai hierarki wilayah.
- Jumlah query tidak bertambah linear terhadap jumlah RT.
- Label tetap terbaca pada lebar kartu grafik di 1280px dan 1920px.

#### D-02 — Judul grafik “Keluarga per RT (n)” menggunakan jumlah keluarga — **Medium**

**Bukti:** `KeluargaPerRtChart.php:47` memakai `Keluarga::query()->count()`, padahal judul menyiratkan jumlah RT.

**Dampak:** Pengguna dapat membaca `(5)` sebagai “5 RT”, sementara itu adalah 5 keluarga. Angka ini juga tidak memberi informasi tentang sebaran.

**Rekomendasi:** Ubah judul menjadi dua angka yang eksplisit, misalnya `Keluarga per RT · 5 RT · 5 keluarga`, atau tampilkan hanya `Keluarga per RT` dan letakkan total di subjudul.

**Kriteria penerimaan:** Judul tidak lagi ambigu; total RT dan total keluarga dapat dibedakan tanpa membuka grafik.

#### D-03 — Bucket usia kosong tetap ditampilkan tanpa nilai — **Medium**

**Bukti:** `app/Filament/Widgets/KelompokUmurChart.php:28-48` selalu membuat lima label, termasuk `Balita (0–5)`, `Anak (6–12)`, dan `Lansia (60+)`, meskipun hasil count = 0. Audit runtime menunjukkan baris kosong tanpa batang dan tanpa angka.

**Dampak:** Ruang vertikal terbuang; pengguna tidak tahu apakah kategori kosong berarti 0 atau data gagal dimuat. Pada data kecil, grafik terlihat “rusak”.

**Rekomendasi:** Tampilkan label nilai `0` melalui plugin datalabels/tooltip, atau filter bucket dengan nilai 0 sambil tetap mempertahankan urutan kategori. Jika difilter, sertakan catatan “Kategori tanpa data disembunyikan”. Jangan mengubah definisi bucket.

**Kriteria penerimaan:** Setiap kategori yang ditampilkan memiliki nilai eksplisit; tidak ada baris kosong tanpa penjelasan.

#### D-04 — Batang tanpa label nilai dan skala maksimum 1 — **Medium**

**Bukti:** `KelompokUmurChart.php:66-82`, `KeluargaPerRtChart.php:50-66`, `PendidikanChart.php:52-68`, `PekerjaanChart.php:52-68` tidak mengaktifkan data labels. Ketika semua nilai 1, sumbu x berakhir di 1 dan panjang batang tampak seragam. Audit runtime mengonfirmasi grafik keluarga/usia dengan nilai kecil.

**Dampak:** Pengguna tidak dapat membandingkan nilai secara tepat; panjang batang menyesatkan pada dataset kecil.

**Rekomendasi:** Tambahkan label nilai di ujung batang atau tooltip yang selalu menampilkan nilai. Untuk dataset sangat kecil, pertimbangkan tampilan tabel ringkas di samping grafik. Jangan memaksa `suggestedMax` yang menyesatkan.

**Kriteria penerimaan:** Nilai setiap kategori dapat diketahui tanpa menebak panjang batang.

#### D-05 — Tidak ada indikator cakupan wilayah yang konsisten — **Medium**

**Bukti:** `app/Filament/Widgets/RingkasanWidget.php:43-47` hanya menaruh keterangan cakupan pada kartu pertama, sedangkan lima grafik tidak menampilkan cakupan sama sekali. Pengguna dengan peran RT/RW/kabupaten melihat angka berbeda tanpa penanda mengapa.

**Dampak:** Angka dapat disalahartikan sebagai data nasional, terutama saat akun berbeda peran.

**Rekomendasi:** Tambahkan chip/subjudul cakupan yang konsisten di atas area grafik, misalnya `Cakupan: RW 02 · Sukamaju` atau `Cakupan: Nasional`. Ambil dari scope pengguna yang sama dengan widget.

**Kriteria penerimaan:** Setiap widget menampilkan cakupan yang sama atau merujuk ke satu indikator global yang terlihat.

#### D-06 — Kontras dan ukuran teks kecil — **Low**

**Bukti:** `resources/css/filament/admin/theme.css:17` menetapkan `--rd-muted-soft: #9ca3af`; dipakai untuk `.rd-mini-stat-hint` (`:735`), placeholder (`:751`), label grafik (`#9ca3af` di `Chart` options), dan label level uppercase `10.9px` di `directory.blade.php:113`. `.rd-stat-desc` berukuran `0.7rem` (`theme.css:871`).

**Dampak:** Teks deskriptif dan tick grafik sulit dibaca, terutama pada layar proyektor atau pengguna low vision. Rasio kontras `#9ca3af` di atas putih sekitar 2.5:1, di bawah WCAG AA untuk teks normal.

**Rekomendasi:** Gunakan `--rd-muted` (`#6b7280`) untuk teks informatif; simpan `--rd-muted-soft` hanya untuk elemen dekoratif. Naikkan ukuran minimum teks informatif menjadi 12px dan label grafik menjadi 11–12px. Jangan mengubah palet aksen.

**Kriteria penerimaan:** Teks informatif memenuhi kontras minimal 4.5:1 pada latar putih/`#f9fafb`; label grafik terbaca pada 1280px.

#### D-07 — Whitspace kartu statistik dan animasi angka — **Low**

**Bukti:** `ringkasan.blade.php:5-44` dan `theme.css:816-874`. Kartu `min-height: 9.25rem` dengan `gap: 1.15rem` menghasilkan ruang kosong antara ikon dan nilai pada layar lebar. Animasi angka memakai `requestAnimationFrame` dan menghormati `prefers-reduced-motion` (`ringkasan.blade.php:17`).

**Dampak:** Kartu terlihat renggang, tetapi tidak mengganggu fungsi. Tidak ada masalah animasi.

**Rekomendasi:** Opsional. Kurangi `min-height` atau ubah tata letak kartu menjadi ikon sejajar label. Jangan menghapus animasi tanpa alasan.

**Kriteria penerimaan:** Kartu tetap konsisten pada 1/2/4 kolom dan tidak ada nilai yang terpotong.

#### D-08 — Ringkasan hanya menyebut “Nasional” untuk akun admin pusat — **Low**

**Bukti:** `RingkasanWidget.php:46` mengembalikan `Tersebar di n RT (Nasional)` untuk semua pengguna selain Admin RW/RT, termasuk Admin yang cakupannya mungkin bukan nasional.

**Dampak:** Label dapat keliru bila model peran berubah. Saat ini hanya kosmetik.

**Rekomendasi:** Turunkan label dari scope data aktual atau gunakan istilah netral `Seluruh wilayah yang dapat Anda akses`.

**Kriteria penerimaan:** Tidak ada klaim cakupan yang bertentangan dengan data yang tampil.

---

### 2.2 Kelola Admin `/admin/pengguna`

#### A-01 — Tabel admin berada sepenuhnya di bawah lipatan layar — **High**

**Bukti:** `resources/views/filament/resources/users/directory.blade.php:10-140` menempatkan panel penelusuran wilayah lengkap (header, chip, dua kartu statistik, pencarian, pager, grid 25 wilayah, footer checkbox) **sebelum** `{{ $this->table }}` pada `:142`. Audit runtime 1920×1080 menunjukkan tabel mulai pada `y≈1086`, di luar viewport.

**Dampak:** Halaman bernama “Kelola Admin” tetapi tugas utama — melihat dan mengubah admin — tidak terlihat saat halaman dibuka. Pengguna mungkin mengira halaman hanya berisi penelusuran wilayah. Ini juga memperpanjang waktu tugas dan meningkatkan beban kognitif.

**Akar masalah:** Penjelajah wilayah diperlakukan sebagai konten utama, bukan alat bantu filter.

**Rekomendasi (pilih salah satu, tanpa mengubah logika bisnis):**
1. **Paling disarankan:** Pindahkan tabel admin ke atas, lalu jadikan penjelajah wilayah panel yang dapat dilipat (`<details>` atau toggle) dengan ringkasan filter aktif. Pertahankan kemampuan memilih wilayah.
2. **Alternatif:** Pertahankan urutan, tetapi ringkas panel menjadi satu baris filter + chip breadcrumb + tombol `Telusuri wilayah` yang membuka drawer/modal.
3. **Alternatif minimal:** Tambahkan anchor `Lihat daftar admin ↓` yang menonjol dan sticky, serta kurangi tinggi panel (grid maksimal 2 baris + scroll internal).

**Kriteria penerimaan:**
- Pada 1920×1080, minimal header tabel dan 3 baris admin terlihat tanpa menggulir.
- Penelusuran wilayah tetap dapat diakses dan filter tetap bekerja.
- Tidak ada perubahan pada query atau hak akses.

#### A-02 — Urutan provinsi arbitrer — **High**

**Bukti:** `app/Filament/Resources/UserResource/Pages/ListUsers.php:76` memakai `orderBy('path')`. `path` adalah string sehingga urutan mengikuti nilai tekstual, bukan urutan kode/nama. Audit runtime menghasilkan urutan provinsi: 13, 14, 15, 16, 32, 17, 18, 19, 21, 31, 33, 34, 35, 11, 36. `WilayahResource.php:234` juga memakai `orderBy('path')`.

**Dampak:** Pengguna sulit menemukan provinsi tertentu; urutan terlihat acak dan tidak dapat diprediksi.

**Rekomendasi:** Gunakan `orderBy('kode')` untuk urutan administratif, atau `orderBy('nama')` untuk urutan alfabetis yang ramah pengguna. Jika `path` dipertahankan untuk hierarki, tambahkan tie-breaker `kode`/`nama`. Terapkan konsisten pada `ListUsers::nodes()` dan `WilayahResource::getEloquentQuery()`.

**Kriteria penerimaan:** Daftar provinsi dan wilayah anak selalu berada pada urutan yang sama dan dapat dijelaskan (kode atau nama).

#### A-03 — Navigasi halaman wilayah tanpa batas dan tanpa total — **High**

**Bukti:** `ListUsers.php:31-34` menaikkan `halamanWilayah` tanpa batas; `:77-78` memakai offset/limit 25. Tidak ada perhitungan total. Footer `directory.blade.php:136-138` hanya menampilkan `n wilayah ditampilkan`. Tombol `Berikutnya` (`:91`) selalu aktif.

**Dampak:** Pengguna dapat masuk ke halaman kosong tanpa jalan kembali yang jelas; tidak tahu posisi halaman atau total wilayah. Ini merusak kepercayaan pada navigasi.

**Rekomendasi:** Hitung total hasil dengan query count yang sama (tanpa offset/limit), tentukan `totalHalaman`, nonaktifkan `Berikutnya` di halaman terakhir dan `Sebelumnya` di halaman pertama. Tampilkan `25 dari 38 wilayah · Halaman 1/2`.

**Kriteria penerimaan:**
- Tidak mungkin membuka halaman kosong melalui tombol navigasi.
- Footer menampilkan total dan posisi halaman.
- Perhitungan total menghormati filter pencarian dan scope node.

#### A-04 — Pencarian wilayah mengabaikan scope node — **Medium**

**Bukti:** `ListUsers.php:75-75` ketika `cariWilayah !== ''` mencari seluruh wilayah non-negara tanpa membatasi ke subtree node aktif. Bandingkan dengan `:66-74` yang membatasi ke anak node.

**Dampak:** Saat pengguna berada di `Kecamatan X`, pencarian dapat menampilkan wilayah dari kecamatan lain, lalu memilihnya memindahkan konteks secara tak terduga.

**Rekomendasi:** Ketika node aktif, batasi pencarian ke subtree (`path like node.path%`), kecuali pengguna secara eksplisit memilih “Cari di semua wilayah”. Tampilkan chip konteks di hasil.

**Kriteria penerimaan:** Hasil pencarian tidak keluar dari cakupan node aktif kecuali ada kontrol eksplisit untuk itu.

#### A-05 — Semantik “Admin Global” menyesatkan — **Medium**

**Bukti:** `ListUsers.php:89-90` mengembalikan `[$admins->whereNull('id_wilayah')->count(), 0]` saat `global` aktif. `directory.blade.php:130-133` memberi label checkbox `Admin Global`. Kartu Keluarga tetap berlabel `terdata di wilayah ini` dengan nilai 0 (`directory.blade.php:66-70`).

**Dampak:** Pengguna mengira “Admin Global” adalah cakupan wilayah, padahal itu filter “hanya admin tanpa penugasan”. Nilai Keluarga 0 terbaca sebagai “tidak ada keluarga”, bukan “tidak relevan”.

**Rekomendasi:** Ganti label menjadi `Hanya admin tanpa penugasan` atau gunakan segmented control `Semua admin / Admin wilayah / Admin tanpa penugasan`. Saat mode global aktif, sembunyikan kartu Keluarga atau beri label `Tidak berlaku untuk admin global`. Pertahankan perilaku query.

**Kriteria penerimaan:** Label kontrol menjelaskan efeknya tanpa ambigu; tidak ada angka 0 yang menyesatkan.

#### A-06 — Istilah halaman tidak seragam — **Medium**

**Bukti:** Navigasi `Kelola Admin` (`UserResource.php:41`), judul halaman `Admin` (default dari `modelLabel`, `:43`), breadcrumb `Admin › Daftar`, heading tabel `Data Admin` (`:108`), tombol `Tambah Admin` (`ListUsers.php:139`). Di halaman wilayah: judul `Wilayah` (`WilayahResource.php:36`), breadcrumb `Wilayah › Daftar`, tab `Master Wilayah`, `Jelajah`.

**Dampak:** Pengguna harus memetakan beberapa nama untuk satu konsep. Navigasi dan judul tidak cocok, sehingga orientasi menurun.

**Rekomendasi:** Tetapkan satu istilah per konsep:
- Halaman: `Kelola Admin` untuk judul dan breadcrumb; `Data Admin` boleh sebagai heading tabel.
- Halaman wilayah: `Wilayah` sebagai judul, dengan subjudul dinamis `Jelajah Wilayah` atau `Master Wilayah` sesuai tab.
- Gunakan `Tambah Admin`/`Tambah wilayah` secara konsisten (sudah benar di runtime).

**Kriteria penerimaan:** Navigasi, judul, breadcrumb, dan heading tabel tidak memakai sinonim yang membingungkan untuk konsep yang sama.

#### A-07 — Label kartu wilayah memotong status dan tidak dapat dibaca penuh — **Medium**

**Bukti:** `app/Support/WilayahLabels.php:21` menggabungkan `kode · rantai nama · status` menjadi satu string. `directory.blade.php:112` memakai `truncate` tanpa `title`. Audit runtime menunjukkan label terpotong, misalnya `... · A...`.

**Dampak:** Informasi status aktif/nonaktif hilang, dan pengguna tidak punya cara melihat nama lengkap. Status `Nonaktif / induk nonaktif` juga mencampur dua kondisi berbeda.

**Rekomendasi:** Pisahkan menjadi tiga elemen visual: kode (mono/kecil), nama (maksimal 2 baris atau line-clamp), dan badge status. Tambahkan `title` atau tooltip dengan rantai lengkap. Bedakan `Nonaktif` (lokal) dan `Induk nonaktif` (warisan).

**Kriteria penerimaan:** Status selalu terlihat; nama panjang dapat dibaca melalui tooltip/wrap; tidak ada informasi yang hilang karena truncation.

#### A-08 — Kontrol kustom tidak memiliki fokus keyboard yang terlihat — **Medium**

**Bukti:** `theme.css` mendefinisikan `.rd-chip` (`:677`), `.rd-list-btn` (`:759`), `.rd-quiet-link` (`:657`), dan `.rd-search-input` (`:739`), tetapi hanya `.rd-search-input:focus` yang memiliki gaya. Tidak ada `:focus-visible` untuk chip/list/link. Tombol-tombol ini adalah `<button>`/`<a>` yang dapat difokus.

**Dampak:** Pengguna keyboard tidak tahu elemen mana yang aktif. Ini kegagalan aksesibilitas WCAG 2.4.7.

**Rekomendasi:** Tambahkan `:focus-visible` dengan ring konsisten, misalnya `outline: 2px solid var(--rd-accent); outline-offset: 2px;` atau `box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--rd-accent)`. Jangan menghapus `outline` tanpa pengganti.

**Kriteria penerimaan:** Semua kontrol kustom menampilkan indikator fokus yang jelas saat dinavigasi dengan Tab.

#### A-09 — Label dan metadata memakai kontras rendah — **Medium**

**Bukti:** `directory.blade.php:113` memakai `text-gray-400` untuk label level uppercase `0.68rem`; `theme.css:735` memakai `--rd-muted-soft` untuk hint; `theme.css:751` untuk placeholder.

**Dampak:** Teks sulit dibaca, terutama pada layar dengan kontras rendah.

**Rekomendasi:** Gunakan `text-gray-500`/`--rd-muted` minimal, naikkan ukuran ke `0.72rem`. Pertahankan hierarki visual melalui bobot dan spasi, bukan kontras yang terlalu rendah.

**Kriteria penerimaan:** Metadata memenuhi kontras AA dan tetap terasa sekunder.

#### A-10 — Kepadatan grid wilayah pada layar lebar — **Low**

**Bukti:** `directory.blade.php:97` memakai `sm:grid-cols-2 xl:grid-cols-3`. Pada 1920px, kartu menjadi sangat lebar untuk label pendek.

**Dampak:** Ruang horizontal terbuang, panel terasa kosong, dan daftar 25 wilayah memakan tinggi berlebih.

**Rekomendasi:** Tambahkan breakpoint `2xl:grid-cols-4` atau batasi lebar maksimum grid. Pertahankan 1 kolom di mobile dan 2 di tablet.

**Kriteria penerimaan:** Tidak ada kartu yang terlalu lebar; jumlah baris berkurang pada layar ≥1536px tanpa memotong label.

#### A-11 — Pager wilayah terlihat seperti pagination tabel — **Low**

**Bukti:** `directory.blade.php:84-95` menempatkan tombol `Sebelumnya`/`Berikutnya` dan angka halaman tepat di samping pencarian, tanpa label “wilayah”. Tabel admin di bawah memiliki pagination Filament sendiri.

**Dampak:** Pengguna dapat mengira pager wilayah mengontrol tabel admin.

**Rekomendasi:** Beri label `Halaman wilayah` atau pindahkan pager ke footer panel bersama total. Pisahkan secara visual dari pagination tabel.

**Kriteria penerimaan:** Tidak ada dua kontrol pagination yang tampak saling terkait secara ambigu.

#### A-12 — Checkbox filter jauh dari angka yang dipengaruhinya — **Low**

**Bukti:** `directory.blade.php:124-134` menempatkan `Termasuk wilayah turunan` dan `Admin Global` di footer, sedangkan kartu statistik yang berubah ada di `:60-71`.

**Dampak:** Pengguna mengubah filter lalu tidak melihat efeknya karena kartu berada di atas; pada layar kecil, keduanya tidak terlihat bersamaan.

**Rekomendasi:** Pindahkan kedua kontrol ke baris filter ringkas di dekat kartu statistik, atau tampilkan ringkasan filter aktif di dekat angka.

**Kriteria penerimaan:** Perubahan filter dan dampaknya pada angka dapat dipahami dalam satu pandangan.

#### A-13 — Panel penjelajah diberi nama “Wilayah Penugasan Admin” — **Medium**

**Bukti:** `directory.blade.php:17-18` memberi judul `Wilayah Penugasan Admin` dan deskripsi `Telusuri wilayah, lalu kelola akun admin di cakupan tersebut.` Panel tidak menampilkan atau mengubah penugasan; ia memfilter tabel.

**Dampak:** Pengguna mengira mereka sedang mengatur penugasan admin, padahal hanya menelusuri wilayah.

**Rekomendasi:** Ganti menjadi `Cakupan Wilayah` atau `Telusuri Wilayah`, dengan deskripsi `Pilih wilayah untuk memfilter daftar admin.`

**Kriteria penerimaan:** Judul panel sesuai dengan aksi yang tersedia.

---

### 2.3 Wilayah `/admin/wilayah`

#### W-01 — Badge merah `0` pada tombol filter — **Medium**

**Bukti:** Tombol filter tabel Filament menampilkan badge jumlah filter aktif. Audit runtime menunjukkan badge merah `0` pada trigger filter. Konfigurasi filter ada di `WilayahResource.php:173-175`.

**Dampak:** Badge merah biasanya menandakan peringatan atau filter aktif; angka `0` membingungkan dan membuat UI terlihat rusak.

**Rekomendasi:** Sembunyikan badge saat jumlah filter aktif = 0, atau ganti dengan indikator netral. Jika ini perilaku bawaan Filament, gunakan CSS untuk menyembunyikan badge nol pada trigger, tanpa mengubah logika filter.

**Kriteria penerimaan:** Tidak ada badge merah `0`; badge hanya muncul saat ada filter aktif.

#### W-02 — Judul/tab tidak konsisten — **Medium**

**Bukti:** `WilayahResource.php:36` `navigationLabel = 'Wilayah'`; judul halaman default `Wilayah`; breadcrumb `Wilayah › Daftar`; tab `Master Wilayah` dan `Jelajah` (`list-wilayah.blade.php:22,33`).

**Dampak:** Pengguna tidak yakin apakah “Wilayah” dan “Master Wilayah” adalah halaman yang sama.

**Rekomendasi:** Pertahankan `Wilayah` sebagai judul, tambahkan subjudul dinamis sesuai tab. Atau ubah judul halaman menjadi `Jelajah Wilayah` saat tab jelajah dan `Master Wilayah` saat tab master.

**Kriteria penerimaan:** Judul halaman mencerminkan tab aktif dan konsisten dengan navigasi.

#### W-03 — Dua badge hijau “Status lokal” dan “Operasional” tanpa penjelasan — **Medium**

**Bukti:** `WilayahResource.php:110-111` menampilkan `Status lokal` (`is_active`) dan `Operasional` (`aktifEfektif()`). Keduanya hijau untuk wilayah aktif.

**Dampak:** Pengguna tidak memahami perbedaannya; pada wilayah akar keduanya redundan. Jika salah satu kuning, penyebabnya tidak dijelaskan.

**Rekomendasi:** Tambahkan tooltip/deskripsi kolom: `Status lokal: status wilayah ini sendiri` dan `Operasional: status efektif termasuk induk`. Untuk baris dengan status berbeda, tampilkan alasan singkat.

**Kriteria penerimaan:** Perbedaan dua kolom dapat dipahami tanpa dokumentasi eksternal.

#### W-04 — Ikon filter tanpa label aksesibel — **Low**

**Bukti:** Trigger filter adalah ikon tanpa teks. Tidak ada tooltip yang terlihat pada audit.

**Dampak:** Pengguna baru atau pengguna screen reader tidak tahu fungsi tombol.

**Rekomendasi:** Tambahkan `aria-label`/tooltip `Filter wilayah`. Pastikan trigger memiliki nama aksesibel.

**Kriteria penerimaan:** Tombol filter memiliki nama yang dapat dibaca teknologi bantu dan tooltip visual.

#### W-05 — Afordans badge “Rantai induk” dan “Anak langsung” rendah — **Low**

**Bukti:** `WilayahResource.php:155-167` membuat badge `children_count` dapat diklik menuju halaman anak; `rantai_induk` hanya teks. Badge tidak terlihat seperti tautan.

**Dampak:** Pengguna tidak menemukan navigasi cepat melalui badge.

**Rekomendasi:** Tambahkan `cursor`, hover state, dan tooltip `Buka daftar anak wilayah`. Jika “Rantai induk” tidak interaktif, jangan beri gaya badge yang menyerupai tombol.

**Kriteria penerimaan:** Elemen interaktif terlihat interaktif; elemen statis tidak.

#### W-06 — Pencarian tabel di akar mencakup seluruh hierarki — **Low**

**Bukti:** `ListWilayah.php:101-109` ketika ada pencarian dan `parentId === null`, mengembalikan seluruh query tanpa batas. Saat berada di akar, mode “Per tingkat” normalnya hanya menampilkan Indonesia; pencarian mengubah cakupan secara diam-diam.

**Dampak:** Hasil pencarian dapat menampilkan wilayah jauh di bawah, di luar ekspektasi “per tingkat”.

**Rekomendasi:** Tambahkan helper text `Pencarian mencakup seluruh wilayah` atau batasi pencarian ke level saat ini dan sediakan opsi `Cari di semua tingkat`.

**Kriteria penerimaan:** Cakupan pencarian dijelaskan secara eksplisit di UI.

#### W-07 — Tabel 7 kolom pada layar sempit — **Low**

**Bukti:** `WilayahResource.php:108-168` memiliki 7 kolom. Audit 400px menunjukkan scroll horizontal.

**Dampak:** Pengalaman mobile buruk, tetapi bukan jalur utama admin.

**Rekomendasi:** Tetapkan prioritas kolom (Kode, Nama, Level, Status) dan sembunyikan kolom sekunder pada breakpoint kecil, atau jadikan nama kolom sticky. Jangan menghilangkan informasi pada desktop.

**Kriteria penerimaan:** Pada 400px, kolom utama dapat dibaca tanpa scroll horizontal berlebihan; informasi sekunder tetap dapat diakses.

#### W-08 — Jarak vertikal antarblok — **Low (tidak terkonfirmasi sebagai bug)**

**Bukti:** `list-wilayah.blade.php:14` `mb-5`; `hierarchy-header.blade.php:16` `mb-4`. Audit runtime menunjukkan jarak navigasi→panel ±53px dan panel→tabel ±48px. Jarak besar pada screenshot tidak tereproduksi.

**Dampak:** Tidak ada. Ini hanya poles.

**Rekomendasi:** Opsional; jangan ubah tanpa membandingkan screenshot terbaru. Jika ingin dirapatkan, gunakan satu skala spasi konsisten (misalnya 16/24px), bukan nilai ad hoc.

**Kriteria penerimaan:** Tidak ada regresi visual; jarak konsisten di semua tab.

---

### 2.4 Lintas halaman

#### X-01 — Fokus keyboard tidak konsisten — **Medium**

Lihat A-08. Berlaku untuk chip breadcrumb, tombol daftar wilayah, tautan tenang, dan tab kustom.

#### X-02 — Kontras teks sekunder — **Medium**

Lihat D-06/A-09. Nilai `--rd-muted-soft` terlalu terang untuk teks informatif dan label grafik.

#### X-03 — Truncation tanpa akses ke teks lengkap — **Medium**

Lihat A-07. Pola `truncate` juga dipakai pada label wilayah di panel dan berpotensi pada tabel.

#### X-04 — Istilah campur bahasa dan sinonim — **Medium**

Contoh: `Admin Global`, `Semua wilayah`, `Master Wilayah`, `Jelajah`, `Daftar`, `Data Admin`, `Kelola Admin`. Tetapkan glosarium kecil dan gunakan konsisten.

#### X-05 — Tidak ada indikator loading yang jelas saat filter berubah — **Low**

Filter Livewire memicu request; tabel/daftar dapat tampak diam sesaat. Filament memiliki indikator, tetapi panel kustom tidak selalu menampilkannya. Tambahkan state halus (opacity/`aria-busy`) bila perlu.

#### X-06 — `prefers-reduced-motion` sudah ditangani — **Catatan positif**

`theme.css:926-941` menonaktifkan animasi dan transform hover. Pertahankan.

#### X-07 — Identitas visual sudah kuat — **Catatan positif**

Palet, radius, bayangan, tipografi, sidebar, dan tab konsisten. Jangan mengubah arah visual dalam perbaikan ini.

---

## 3. Technical Recommendations

Bagian ini menjelaskan pendekatan teknis per area tanpa memerintahkan perubahan logika bisnis. Semua rekomendasi dapat diimplementasikan sebagai perubahan presentasi/query tampilan.

### 3.1 Grafik dashboard

**KeluargaPerRtChart**
- Ganti `selectRaw('wilayah.nama as label, COUNT(*) as total')` dengan label yang memuat konteks. Pilihan:
  - Tambahkan join ke leluhur dan susun `kode`/nama, atau
  - Muat relasi `wilayah` beserta leluhur, lalu bentuk label di PHP.
- Urutkan dengan `orderBy('wilayah.path')` atau `orderBy('wilayah.kode')`. Jangan memakai `keluarga.id_wilayah`.
- Pastikan agregasi tetap `GROUP BY keluarga.id_wilayah` agar tidak menggabungkan RT berbeda.
- Judul: hitung `distinct id_wilayah` untuk jumlah RT dan `count(*)` untuk keluarga.
- Tambahkan tooltip berisi alamat lengkap untuk label yang dipotong.
- Pertimbangkan batas jumlah batang (misalnya 10–15 teratas) dengan tautan “Lihat semua” jika data RT banyak; ini keputusan produk, bukan logika bisnis.

**KelompokUmurChart**
- Pertahankan bucket. Untuk nilai 0, tetap tampilkan label `0` di ujung baris atau sembunyikan baris dengan catatan.
- Tambahkan plugin datalabels ringan atau konfigurasi tooltip yang selalu aktif.
- Hindari `now()` berulang di loop; hitung satu kali untuk konsistensi batas usia dalam satu render.

**Semua bar chart**
- Aktifkan tooltip dengan nilai dan persentase.
- Pertimbangkan `ticks.precision: 0` dan `beginAtZero: true`.
- Tambahkan `plugins.datalabels` hanya jika tidak menambah dependensi baru; jika perlu library baru, dokumentasikan dan minta persetujuan.
- Jangan mengubah warna agar tetap sesuai identitas.

**Cakupan**
- Buat partial kecil `scope-chip` yang menerima label cakupan dari service/helper yang sama dengan `RingkasanWidget`.
- Tempatkan di atas grid grafik atau di heading setiap widget.

### 3.2 Kelola Admin

**Tata letak**
- Opsi paling aman: pindahkan `{{ $this->table }}` ke atas panel penelusuran, lalu bungkus panel penelusuran dalam komponen yang dapat dilipat. Filter tetap mengikat `wilayah`, `turunan`, `global`.
- Jika mengubah urutan berisiko, tambahkan tombol anchor sticky `Ke daftar admin` dengan `href="#daftar-admin"` dan `id` pada wrapper tabel.
- Kurangi tinggi panel: batasi grid wilayah dengan `max-height` dan `overflow-y: auto`, atau tampilkan 6–9 kartu per halaman alih-alih 25. Ini mengubah pagination tampilan, bukan data.

**Query tampilan**
- `ListUsers::nodes()`: ganti `orderBy('path')` menjadi `orderBy('kode')` atau `orderBy('nama')`.
- Tambahkan `totalWilayah()` dengan query count yang identik dengan `nodes()` (tanpa offset/limit), lalu gunakan untuk mengklamp `lanjutWilayah()` dan menampilkan footer.
- Batasi pencarian ke subtree saat `node` aktif.
- Jangan mengubah `counters()` selain penyesuaian label; perilaku `global` dipertahankan.

**Semantik filter**
- Pisahkan konsep “cakupan wilayah” dan “jenis admin”. Gunakan segmented control atau label eksplisit.
- Saat `global` aktif, sembunyikan kartu Keluarga atau beri state `Tidak berlaku`.

**Label wilayah**
- Ubah `WilayahLabels::for()` agar mengembalikan struktur (`kode`, `nama`, `status`, `status_reason`) alih-alih satu string. Ini perubahan pada helper tampilan; pastikan pemanggil lain disesuaikan.
- Di blade, render badge status terpisah. Tambahkan `title` pada nama.

**Aksesibilitas**
- Tambahkan `:focus-visible` untuk `.rd-chip`, `.rd-list-btn`, `.rd-quiet-link`, `.rd-checkbox`, dan tab kustom.
- Tambahkan `aria-label` pada input pencarian dan tombol pager.
- Pastikan `aria-current="page"` pada chip aktif.

### 3.3 Wilayah

- Sembunyikan badge filter nol. Jika memungkinkan, gunakan hook/atribut Filament; jika tidak, CSS terarah pada trigger yang badge-nya `0`.
- Tambahkan tooltip pada kolom status ganda.
- Tambahkan `aria-label` pada trigger filter.
- Pertahankan tab dan breadcrumb; hanya perjelas judul dinamis.
- Jangan mengubah `getTableQuery()` selain penambahan pengurutan yang konsisten, kecuali untuk memperjelas cakupan pencarian (yang merupakan perubahan presentasi/query tampilan).

### 3.4 Desain sistem

- Pertahankan token yang ada. Tambahkan token fokus:
  ```css
  --rd-focus-ring: 0 0 0 2px #ffffff, 0 0 0 4px var(--rd-accent);
  ```
- Gunakan `--rd-muted` untuk teks informatif. `--rd-muted-soft` hanya untuk dekorasi.
- Tetapkan skala spasi: 4/8/12/16/24/32. Hindari `mb-5` + `mb-4` acak.
- Tambahkan aturan global:
  ```css
  .rd-chip:focus-visible,
  .rd-list-btn:focus-visible,
  .rd-quiet-link:focus-visible,
  .fi-tabs-item:focus-visible {
      outline: none;
      box-shadow: var(--rd-focus-ring);
  }
  ```
- Jangan mengubah `AdminPanelProvider` selain jika perlu menambah hook tampilan; warna dan topbar tetap.

### 3.5 Kinerja dan risiko

- Hindari N+1 pada label RT/wilayah. Gunakan eager loading atau satu query agregat.
- Perhitungan total pagination harus memakai query yang sama; cache per request bila perlu.
- Perubahan `WilayahLabels` berdampak pada halaman admin dan wilayah; uji semua pemanggil.
- Jangan menyentuh enkripsi, policy, observer, atau seeder.

---

## 4. Implementation Plan

Rencana dibagi menjadi empat fase. Setiap fase dapat dirilis terpisah dan tidak memerlukan migrasi basis data.

### Fase 0 — Verifikasi dan baseline (0.5 hari)

1. Jalankan aplikasi dengan PHP 8.2 (`php82.bat artisan serve`) dan login `superadmin`.
2. Verifikasi label tombol live: `Tambah wilayah`, `Tambah Admin`. Catat jika screenshot lama berbeda.
3. Ambil screenshot baseline 1920×1080 dan 400×900 untuk tiga halaman.
4. Catat jumlah query dashboard sebelum perubahan (Laravel Debugbar/Telescope atau `DB::listen` sementara di lokal, tanpa commit).
5. Pastikan build asset berjalan (`npm run build` atau `npm run dev`) sebelum menilai CSS.

**Output:** baseline screenshot, catatan query, konfirmasi label.

### Fase 1 — Perbaikan berdampak tinggi (2–3 hari)

| Item | File utama | Estimasi |
|---|---|---:|
| A-01 Tabel admin di atas lipatan | `directory.blade.php`, mungkin `ListUsers.php` | 1 hari |
| D-01/D-02 Label & judul RT | `KeluargaPerRtChart.php` | 0.5 hari |
| A-02 Urutan wilayah | `ListUsers.php:76`, `WilayahResource.php:234` | 0.25 hari |
| A-03 Pagination wilayah | `ListUsers.php`, `directory.blade.php` | 0.5 hari |

**Urutan:** A-02 → A-03 → D-01/D-02 → A-01. A-01 terakhir karena menyentuh tata letak dan perlu regression test manual.

**Gate:** Tabel admin terlihat di 1080p; tidak ada label RT duplikat; tidak ada halaman kosong; urutan provinsi stabil.

### Fase 2 — Kejelasan dan aksesibilitas (2–3 hari)

| Item | File utama | Estimasi |
|---|---|---:|
| A-05 Semantik Admin Global | `ListUsers.php`, `directory.blade.php` | 0.5 hari |
| A-06/W-02 Istilah | `UserResource.php`, `WilayahResource.php`, blade | 0.5 hari |
| A-07/X-03 Label wilayah | `WilayahLabels.php`, `directory.blade.php` | 0.5 hari |
| A-08/X-01 Fokus keyboard | `theme.css` | 0.25 hari |
| D-03/D-04 Grafik | widget chart | 1 hari |
| W-01 Badge filter nol | CSS/Filament hook | 0.25 hari |
| W-03 Tooltip status | `WilayahResource.php` | 0.25 hari |

**Gate:** Navigasi keyboard terlihat; tidak ada badge `0`; label status tidak terpotong; istilah konsisten.

### Fase 3 — Poles dan konsistensi (1–2 hari)

| Item | File utama | Estimasi |
|---|---|---:|
| A-09/D-06 Kontras & ukuran | `theme.css`, chart options | 0.5 hari |
| A-10 Grid 4 kolom | `directory.blade.php` | 0.25 hari |
| A-11 Pager wilayah | `directory.blade.php` | 0.25 hari |
| A-12 Posisi filter | `directory.blade.php` | 0.25 hari |
| D-05 Cakupan | widget/partial baru | 0.5 hari |
| W-04/W-05/W-06 aksesibilitas & pencarian | `WilayahResource.php`, blade | 0.5 hari |
| D-07/W-08 poles spasi | CSS | opsional |

### Fase 4 — Verifikasi dan serah terima (1 hari)

1. Uji manual per peran: Superadmin, Admin, Admin RW, Admin RT.
2. Uji keyboard: Tab melalui chip, kartu wilayah, pager, tabel.
3. Uji viewport: 400, 768, 1280, 1920.
4. Uji data kosong: 0 keluarga, 0 anggota, RT tanpa keluarga, wilayah nonaktif.
5. Uji pagination: halaman pertama, tengah, terakhir; pencarian tanpa hasil.
6. Jalankan `npm run build` dan pastikan tidak ada error CSS/Tailwind.
7. Jalankan test suite yang tersedia (lihat `CODEBASE.md`/`composer.json`). Jangan menambah test jika tidak diminta, tetapi jalankan yang ada.
8. Bandingkan screenshot sebelum/sesudah dan lampirkan di PR.

**Definition of done:**
- Tidak ada perubahan pada skema DB, policy, enkripsi, atau alur bisnis.
- Tidak ada regresi pada filter/tabel.
- Semua kriteria penerimaan High/Medium terpenuhi.
- Identitas visual tetap.

### Estimasi total

±6–9 hari kerja satu engineer, tergantung hasil verifikasi dan ketersediaan waktu untuk regression manual.

---

## 5. Implementation Handoff

Bagian ini ditujukan untuk engineer atau agen coding berikutnya. Ikuti urutan dan batasan di bawah.

### 5.1 Batasan wajib

1. **Jangan** mengubah skema database, migrasi, seeder, model bisnis, policy, observer, enkripsi, atau API.
2. **Jangan** mengubah logika hak akses atau scope data. Perubahan hanya pada presentasi, query tampilan, label, dan CSS.
3. **Jangan** mengganti identitas visual: near-black `#0b0f14`, indigo `#4755ae`, radius, bayangan, tipografi, sidebar terang, topbar mati.
4. **Jangan** menambah dependensi baru tanpa persetujuan. Untuk data labels grafik, prioritaskan tooltip bawaan Chart.js/Filament.
5. **Jangan** mengubah label tombol berdasarkan screenshot lama. Verifikasi runtime: saat ini `Tambah wilayah` dan `Tambah Admin`.
6. **Jangan** menganggap jarak besar pada screenshot sebagai bug. Ukur ulang di runtime.
7. Setiap perubahan harus dapat direview terpisah; jangan menggabungkan refactor besar dengan perbaikan UI.

### 5.2 Urutan kerja yang disarankan

1. **Baseline**: jalankan app, ambil screenshot, catat perilaku.
2. **A-02**: ubah pengurutan wilayah di `ListUsers::nodes()` dan `WilayahResource::getEloquentQuery()`. Uji daftar provinsi.
3. **A-03**: tambahkan total dan clamp pagination. Uji halaman terakhir.
4. **D-01/D-02**: perbaiki label dan judul `KeluargaPerRtChart`. Uji dengan data RT duplikat.
5. **A-01**: ubah tata letak `directory.blade.php`. Uji filter tetap bekerja dan tabel terlihat.
6. **A-05/A-06/A-07**: perjelas semantik dan label. Uji semua peran.
7. **A-08**: tambahkan fokus. Uji dengan keyboard.
8. **D-03/D-04**: perbaiki grafik. Uji data kosong dan data kecil.
9. **W-01/W-03**: perbaiki badge dan status.
10. **Poles**: kontras, grid, pager, cakupan.

### 5.3 Detail implementasi per file

#### `app/Filament/Widgets/KeluargaPerRtChart.php`

- Pertahankan `getType() = 'bar'`, `maxHeight`, dan warna.
- Ubah query agar label unik. Contoh pendekatan (bukan kode final):
  - Ambil `id_wilayah`, `COUNT(*)`, lalu muat `Wilayah` beserta leluhur.
  - Bentuk label dari `kode` + nama RT + nama RW/desa.
- Ubah `orderBy` ke `wilayah.path` atau `wilayah.kode`.
- Ubah `getHeading()` agar menghitung RT dan keluarga secara terpisah.
- Tambahkan tooltip pada opsi chart.
- **Jangan** mengubah definisi “keluarga per RT”.

#### `app/Filament/Widgets/KelompokUmurChart.php`

- Jangan ubah batas bucket.
- Hitung `now()` sekali di luar loop.
- Tampilkan nilai 0 secara eksplisit atau sembunyikan dengan catatan.
- Tambahkan tooltip nilai.

#### `app/Filament/Resources/UserResource/Pages/ListUsers.php`

- `nodes()`: ganti `orderBy('path')` menjadi `orderBy('kode')` atau `orderBy('nama')`.
- Tambahkan method total (misalnya `totalNodes()`) dengan filter yang sama.
- Clamp `lanjutWilayah()`: `min(totalHalaman, halaman + 1)`.
- Batasi pencarian ke subtree saat `$this->wilayah` aktif.
- **Jangan** mengubah `counters()` selain label/kondisi tampilan.
- **Jangan** mengubah `pilih()`, `getTableQuery()`, atau `getHeaderActions()` kecuali perlu penyesuaian label.

#### `resources/views/filament/resources/users/directory.blade.php`

- Pindahkan `{{ $this->table }}` ke atas panel atau tambahkan anchor + collapse.
- Ganti judul panel menjadi `Cakupan Wilayah`.
- Pisahkan label wilayah menjadi elemen terpisah; tambahkan tooltip.
- Tambahkan `aria-label` pada pencarian dan pager.
- Pindahkan checkbox filter ke dekat kartu statistik.
- Tambahkan total/posisi halaman.
- **Jangan** mengubah `wire:click`/`wire:model` yang ada tanpa memastikan filter tetap berfungsi.

#### `app/Support/WilayahLabels.php`

- Jika diubah, pertahankan kompatibilitas pemanggil atau sesuaikan semua pemanggil.
- Kembalikan struktur data, bukan string gabungan.
- Pastikan status membedakan lokal vs warisan.

#### `resources/css/filament/admin/theme.css`

- Tambahkan `:focus-visible` untuk kontrol kustom dan tab.
- Naikkan kontras teks informatif.
- Jangan mengubah token warna utama.
- Tambahkan aturan badge filter nol secara terarah.
- Uji `prefers-reduced-motion` tetap bekerja.

#### `app/Filament/Resources/WilayahResource.php`

- Tambahkan tooltip pada kolom status.
- Pastikan pengurutan konsisten.
- Jangan mengubah filter/aksi/visibilitas.
- Badge filter nol sebaiknya ditangani di CSS/hook, bukan dengan menghapus filter.

#### `resources/views/filament/resources/wilayah-resource/pages/list-wilayah.blade.php`

- Opsional: subjudul dinamis berdasarkan `$tab`.
- Jangan mengubah include atau livewire key tanpa alasan.

### 5.4 Skenario uji wajib

| Skenario | Langkah | Ekspektasi |
|---|---|---|
| Login Superadmin | Buka `/admin` | Dashboard tampil, angka sesuai, tidak ada error |
| Tabel admin terlihat | Buka `/admin/pengguna` di 1920×1080 | Header tabel + minimal 3 baris terlihat tanpa scroll |
| Filter wilayah | Pilih provinsi → kabupaten | Tabel dan counter berubah; breadcrumb benar |
| Pencarian wilayah | Cari `RT 01` di dalam kecamatan | Hasil tidak keluar dari kecamatan |
| Pagination wilayah | Klik `Berikutnya` sampai akhir | Tombol nonaktif di akhir; tidak ada halaman kosong |
| Admin Global | Aktifkan filter | Hanya admin tanpa wilayah; Keluarga tidak menyesatkan |
| Keyboard | Tab melalui chip/kartu/pager | Fokus terlihat jelas |
| Grafik RT | Buka dashboard dengan RT duplikat | Label unik dan berurutan |
| Grafik usia kosong | Data tanpa balita | Nilai 0 ditampilkan atau kategori disembunyikan dengan catatan |
| Filter wilayah | Buka `/admin/wilayah` | Tidak ada badge merah `0` |
| Status ganda | Lihat baris aktif dan nonaktif | Tooltip menjelaskan perbedaan |
| Mobile | Buka 400px | Tidak ada overflow tak terkendali; kolom utama terbaca |
| Reduced motion | Aktifkan preferensi | Animasi/transform dimatikan |

### 5.5 Format commit yang disarankan

Pisahkan commit per area agar mudah direview:

```
fix(admin): urutkan wilayah berdasarkan kode
fix(admin): batasi pagination wilayah dan tampilkan total
fix(dashboard): label unik dan judul RT
feat(admin): tampilkan tabel admin di atas lipatan
fix(a11y): fokus keyboard pada kontrol kustom
fix(ui): kontras teks sekunder dan badge filter nol
```

Gunakan pesan yang menjelaskan **mengapa**, bukan hanya apa. Jangan menyertakan file build (`public/build`) kecuali memang kebijakan repo.

### 5.6 Hal yang harus dikonfirmasi sebelum implementasi

1. Apakah urutan wilayah yang diinginkan alfabetis (`nama`) atau administratif (`kode`)? Rekomendasi: `kode` untuk konsistensi hierarki, `nama` jika pengguna lebih sering mencari nama.
2. Apakah panel penelusuran wilayah boleh dilipat, atau harus selalu terlihat? Rekomendasi: dilipat dengan ringkasan filter aktif.
3. Apakah jumlah kartu wilayah per halaman boleh dikurangi dari 25 menjadi 9/12? Rekomendasi: boleh, untuk mengurangi tinggi panel.
4. Apakah diperlukan library data labels baru? Rekomendasi: tidak; gunakan tooltip.
5. Apakah teks `Nasional` pada kartu ringkasan harus dipertahankan untuk akun pusat? Rekomendasi: ganti dengan label netral yang diturunkan dari scope.

Jika jawaban belum tersedia, implementasikan rekomendasi default dan catat asumsinya di PR.

### 5.7 Kriteria serah terima akhir

- Semua item **High** dan **Medium** selesai atau memiliki alasan tertulis untuk ditunda.
- Tidak ada perubahan pada logika bisnis, DB, atau hak akses.
- Build asset bersih.
- Screenshot before/after dilampirkan.
- Catatan query dashboard tidak menunjukkan regresi N+1.
- Reviewer dapat memverifikasi setiap kriteria penerimaan tanpa membaca seluruh kode.

---

## Lampiran A — Peta temuan ke file

| ID | Tingkat | File utama |
|---|---|---|
| D-01 | High | `app/Filament/Widgets/KeluargaPerRtChart.php:29-32` |
| D-02 | Medium | `app/Filament/Widgets/KeluargaPerRtChart.php:47` |
| D-03 | Medium | `app/Filament/Widgets/KelompokUmurChart.php:28-48` |
| D-04 | Medium | chart widgets (`getOptions`) |
| D-05 | Medium | `RingkasanWidget.php:43-47`, chart headings |
| D-06 | Low | `theme.css:17,735,751`, chart ticks |
| D-07 | Low | `ringkasan.blade.php`, `theme.css:816-874` |
| D-08 | Low | `RingkasanWidget.php:46` |
| A-01 | High | `directory.blade.php:10-142` |
| A-02 | High | `ListUsers.php:76`, `WilayahResource.php:234` |
| A-03 | High | `ListUsers.php:31-34,77-78`, `directory.blade.php:136-138` |
| A-04 | Medium | `ListUsers.php:75` |
| A-05 | Medium | `ListUsers.php:89-90`, `directory.blade.php:66-70,130-133` |
| A-06 | Medium | `UserResource.php:41-43,108`, `ListUsers.php:139` |
| A-07 | Medium | `WilayahLabels.php:21`, `directory.blade.php:112` |
| A-08 | Medium | `theme.css:657-705,759-778` |
| A-09 | Medium | `directory.blade.php:113`, `theme.css:735,751` |
| A-10 | Low | `directory.blade.php:97` |
| A-11 | Low | `directory.blade.php:84-95` |
| A-12 | Low | `directory.blade.php:60-71,124-134` |
| A-13 | Medium | `directory.blade.php:17-18` |
| W-01 | Medium | `WilayahResource.php:173-175` (perilaku trigger Filament) |
| W-02 | Medium | `WilayahResource.php:36`, `list-wilayah.blade.php:22,33` |
| W-03 | Medium | `WilayahResource.php:110-111` |
| W-04 | Low | `WilayahResource.php:173-175` |
| W-05 | Low | `WilayahResource.php:155-167` |
| W-06 | Low | `ListWilayah.php:101-109` |
| W-07 | Low | `WilayahResource.php:108-168` |
| W-08 | Low | `list-wilayah.blade.php:14`, `hierarchy-header.blade.php:16` |

## Lampiran B — Catatan audit runtime

- Viewport 1920×1080: `.fi-main` padding, `.fi-page-content` gap 32px, kartu statistik ±298×157.
- Tabel admin pada `/admin/pengguna` mulai `y≈1086` sebelum perbaikan.
- Urutan provinsi runtime: 13, 14, 15, 16, 32, 17, 18, 19, 21, 31, 33, 34, 35, 11, 36.
- Filter wilayah menampilkan badge merah `0`.
- Grafik `Keluarga per RT` menampilkan `RT 01`/`RT 02` berulang.
- Grafik usia menampilkan baris kosong untuk bucket tanpa data.
- Label live: `Tambah wilayah`, `Tambah Admin`.
- Screenshot lama menunjukkan `Tombol wilayah`/`Tombol Admin`; tidak tereproduksi, jangan dianggap bug.
- Jarak antarblok pada runtime: navigasi→panel ±53px, panel→tabel ±48px; jarak besar pada screenshot tidak tereproduksi.

## Lampiran C — Prinsip desain yang dipertahankan

- Permukaan terang, kanvas `#f3f4f6`, kartu putih.
- Aksen utama near-black `#0b0f14`; aksen sekunder `#4755ae`.
- Sudut membulat 11–18px; bayangan kartu halus.
- Tipografi rapat dengan letter-spacing negatif pada heading.
- Sidebar terang, topbar dimatikan.
- Animasi halus dan menghormati `prefers-reduced-motion`.
- Bahasa Indonesia sebagai bahasa utama antarmuka.
