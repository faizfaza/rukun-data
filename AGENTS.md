# Instruksi Pengembangan Rukun Data

## Tujuan dan batas pekerjaan

Dokumen ini adalah arahan implementasi kebutuhan lanjutan Rukun Data. Baca `CODEBASE.md` dan kode terkait sebelum mengubah aplikasi. Kebutuhan di bawah merupakan target, bukan klaim bahwa fitur sudah tersedia.

Ketika ditugaskan mengimplementasikan kebutuhan ini, selesaikan seluruh alur sampai dapat dipakai: migration, model, validasi server, otorisasi, form, daftar/detail, navigasi, data referensi, pengujian, dan dokumentasi. Jangan berhenti pada mockup, tombol tanpa aksi, field yang tidak tersimpan, atau sebagian fitur lalu menyatakan semuanya selesai. Jika tugas hanya meminta menulis/mengubah instruksi, batasi pekerjaan pada dokumentasi tersebut.

Gunakan Laravel, Filament, Livewire, Blade, dan tema yang sudah ada. Pertahankan data lama, enkripsi, audit, serta isolasi wilayah. Buat migration tambahan; jangan mengubah migration lama untuk mengakali database yang sudah terpasang. Jangan menjalankan reset database atau mengganti kunci enkripsi/HMAC pada data pengguna.

## Interpretasi kebutuhan

- “Wilayah diaktifkan saja yang bisa ditambah” berarti hanya wilayah aktif secara efektif yang boleh menjadi tujuan penambahan keluarga, penugasan admin, atau induk wilayah operasional baru. Aktif efektif berarti node dan seluruh leluhurnya aktif.
- “Identitas role” berarti profil identitas pemilik akun admin, terpisah dari pilihan perannya; tidak perlu membuat peran baru hanya untuk menyimpan identitas.
- “Sorting admin per wilayah” mencakup filter geografis dan penelusuran subtree, bukan sekadar urutan alfabet.
- “Tree punya counterlink” diterapkan sebagai tree dengan jumlah data sesuai izin, breadcrumb, serta tautan dua arah wilayah ↔ admin/keluarga. Konteks wilayah harus tetap terlihat dan tidak hilang setelah simpan/kembali.
- “Full profiling” berarti profil anggota yang lengkap dan terstruktur. Daftar field awal di bawah adalah rancangan produk yang harus diverifikasi, bukan klaim sebagai skema resmi Kemendagri.
- “Jadi semua” berarti semua kriteria penerimaan dalam dokumen ini dipenuhi dan diverifikasi. Catat hambatan nyata secara spesifik; jangan menutupi bagian yang belum selesai.

## 1. Aktivasi wilayah

### Implementasi

1. Tambahkan status aktif pada wilayah. Pertahankan perilaku data lama melalui backfill yang eksplisit: wilayah lama tetap aktif; wilayah baru dari impor belum otomatis menjadi wilayah operasional aktif.
2. Sediakan aksi aktif/nonaktif di Kelola Wilayah, dengan indikator status diri sendiri dan status terblokir oleh leluhur.
3. Batasi pengubahan status sesuai izin pengelolaan wilayah: superadmin/admin pada lingkup yang diizinkan; admin RW hanya RT di bawah RW-nya. Jangan memperluas akses admin RT.
4. Terapkan pemeriksaan aktif efektif pada semua jalur create/assignment, termasuk request Livewire, parameter URL, relasi form, dan layanan impor operasional. Menyembunyikan pilihan UI saja tidak cukup.
5. Wilayah tidak aktif tetap dapat dilihat oleh pengelola yang berhak untuk riwayat dan reaktivasi. Jangan menghapus keluarga atau memutus penugasan akun lama secara otomatis.
6. Untuk record lama di wilayah yang dinonaktifkan, tetap izinkan pembacaan sesuai policy. Izinkan koreksi data nonwilayah; tolak penambahan anggota/keluarga/admin baru dan perubahan penugasan ke wilayah yang tidak aktif. Tampilkan penjelasan yang jelas.
7. Nonaktifkan induk tanpa menimpa flag lokal anak; status efektif anak mengikuti induk. Reaktivasi induk tidak mengaktifkan anak yang sebelumnya dinonaktifkan sendiri.
8. Catat perubahan status melalui mekanisme audit yang ada.

### Kriteria penerimaan

- Wilayah nonaktif atau yang leluhurnya nonaktif tidak dapat dipilih sebagai tujuan baru, termasuk melalui manipulasi state/URL.
- Aktivasi/nonaktivasi tidak menghilangkan data lama; status efektif konsisten pada semua form.
- Akun tidak memperoleh izin baru hanya karena suatu wilayah diaktifkan.

## 2. Identitas opsional admin

### Implementasi

1. Ganti istilah pengguna yang mengacu pada pengelola aplikasi menjadi **Admin** pada menu, judul, aksi, filter, notifikasi, dan teks bantuan. Pertahankan pembeda Superadmin, Admin, Admin RW, dan Admin RT. Jangan mengubah warga/anggota keluarga menjadi “admin”.
2. Pisahkan bagian **Akun dan Peran**, **Wilayah Penugasan**, dan **Identitas Admin**. Username, password awal, nama akun, serta role tetap mengikuti kewajiban akun yang ada.
3. Profil tambahan bersifat opsional: nama lengkap identitas, NIK, nomor kontak, alamat, tempat/tanggal lahir, jenis kelamin, dan jabatan/keterangan penugasan. Sesuaikan penyimpanan dalam profil terpisah atau kolom nullable dengan alasan yang didokumentasikan.
4. Tidak perlu mengisi semua field bila hanya satu informasi diketahui. Validasi hanya nilai yang diisi: NIK 16 digit, tanggal valid, panjang teks wajar, kontak tidak dipaksa menjadi integer.
5. Setiap nilai yang diisi harus benar-benar tersimpan, tampil kembali pada detail/edit, dan dapat diubah atau dikosongkan secara sengaja. Blank password saat edit tidak mengganti password lama.
6. Enkripsi identitas sensitif menggunakan pola `SensitiveData`; jangan mencatat plaintext identitas/password pada audit, log, atau notifikasi. Tampilkan masking sesuai hak akses.
7. Identitas admin bukan otomatis anggota keluarga dan tidak menggunakan record anggota sebagai akun login. Jangan membuat relasi wajib di antara keduanya.
8. Pertahankan pembatasan perubahan role, penonaktifan akun, dan pembuatan akun yang sudah ada. Identitas tambahan tidak memberikan hak akses.

### Kriteria penerimaan

- Akun dapat dibuat tanpa profil tambahan.
- Profil terisi sebagian tetap tersimpan setelah reload dan dapat diperbarui/dikosongkan.
- Role dan wilayah tetap tervalidasi di server; data sensitif tidak muncul pada serialisasi/log tanpa kebutuhan.

## 3. Kelola wilayah: pencarian dan admin per wilayah

### Implementasi

1. Aktifkan pencarian nama dan kode wilayah di Kelola Wilayah, termasuk saat memilih induk/tujuan pada form. Gunakan query server yang dibatasi dan dipaginasi; jangan preload seluruh wilayah Indonesia.
2. Hasil pencarian menampilkan level, kode, rantai induk, serta status aktif agar nama seperti “RT 01” tidak ambigu.
3. Navigasi tree dan pencarian harus bekerja bersama: hasil dapat membuka node bersangkutan dengan breadcrumb benar dan tetap dibatasi subtree pengguna.
4. Pada detail/konteks wilayah, sediakan tab atau panel **Admin di Wilayah Ini**, daftar admin penugasan langsung, dan pilihan **Termasuk Wilayah Turunan**.
5. Sediakan **Tambah Admin di Wilayah Ini** hanya bagi peran yang memiliki izin membuat akun. Isi wilayah otomatis dan kunci konteksnya; role yang ditawarkan harus cocok dengan level wilayah. Untuk node provinsi/desa, arahkan pemilihan turun ke level penugasan yang didukung, jangan menciptakan role/assignment tidak valid.
6. Pada admin RW, daftar admin terkait dapat ditampilkan hanya bila diizinkan oleh policy baru yang eksplisit; jangan otomatis membuka profil lengkap atau memberi izin membuat akun. Default aman: daftar akun hanya bagi superadmin/admin sesuai policy saat ini.
7. Tautan dari daftar admin ke wilayah dan dari wilayah ke admin mempertahankan konteks/filter pengguna.

### Kriteria penerimaan

- Cari “Bojongsoang” atau kode wilayah membawa pengguna ke node yang tepat dengan rantai induk terlihat.
- Pencarian nama/kode tidak mengungkap wilayah atau akun di luar kewenangan.
- Tambah admin dari RT/RW menetapkan penugasan yang benar; manipulasi state untuk wilayah lain ditolak.

## 4. Direktori admin dengan filter wilayah

### Implementasi

1. Gunakan halaman utama **Kelola Admin** dengan pemilih wilayah bertingkat/tree, konsisten dengan Jelajah Wilayah untuk keluarga.
2. Sediakan pencarian nama/username, filter role, status akun, wilayah, serta cakupan langsung/subtree. Pencarian nama terenkripsi harus menggunakan pendekatan indeks yang sesuai, bukan membandingkan ciphertext.
3. Contoh: memilih Jawa Barat menampilkan admin yang ditugaskan pada subtree Jawa Barat; memilih Bojongsoang mempersempit hasil sesuai node yang dipilih.
4. Akun global tanpa penugasan ditampilkan melalui filter/kelompok **Admin Global**, bukan dimasukkan ke semua wilayah tanpa penjelasan.
5. Tampilkan kolom nama, username, role, status, dan alamat wilayah lengkap. Identitas sensitif tidak perlu muncul di tabel utama.
6. Tree menampilkan counter admin/keluarga sesuai izin dan pilihan cakupan. Jangan menghitung data lintas wilayah yang tidak boleh diketahui pengguna. Hindari query per node; gunakan agregasi terukur dan lazy loading anak.
7. Pertahankan filter saat membuka detail/edit dan kembali. Berikan empty state yang menjelaskan cakupan aktif.
8. Jika URL `/admin/pengguna` diganti, sediakan redirect atau kompatibilitas tautan lama. Perubahan label tidak mewajibkan penggantian nama model `User`.

### Kriteria penerimaan

- Admin dapat menemukan admin Jawa Barat/Bojongsoang tanpa menelusuri tabel nasional secara manual.
- Breadcrumb, counter, hasil tabel, serta target create selalu menunjuk konteks yang sama.
- Dua wilayah bernama sama tidak tertukar; filter tetap ada setelah kembali dari edit.

## 5. Profil anggota keluarga lengkap

### Implementasi

1. Audit kolom/form anggota yang ada; pertahankan data identitas, relasi keluarga, agama, pendidikan, pekerjaan, status perkawinan, kelahiran, orang tua, dan penghasilan.
2. Susun form/detail dalam bagian yang mudah dipahami: Identitas, Kelahiran, Pendidikan, Pekerjaan, Perkawinan, Orang Tua, dan Administrasi Kependudukan. Gunakan section/tab dengan ringkasan, bukan satu daftar field panjang.
3. Tambahkan profil pendidikan: status sekolah, nama sekolah/perguruan tinggi, jenjang, kelas/program studi, lokasi institusi. Bedakan pendidikan terakhir dengan pendidikan yang sedang ditempuh.
4. Tambahkan profil perkawinan: status perkawinan yang sudah ada, tanggal/tempat perkawinan, status pencatatan, nomor dokumen perkawinan bila diperlukan; informasi perceraian hanya relevan pada kondisi terkait. “Posisi pernikahan” diinterpretasikan sebagai status, lokasi, dan pencatatan, bukan koordinat lokasi pribadi.
5. Verifikasi kebutuhan administrasi lain terhadap referensi resmi yang relevan: kewarganegaraan, dokumen kelahiran, serta dokumen kependudukan pendukung. Dokumentasikan kamus field, sumber, tipe, pilihan nilai, kewajiban, dan aturan kondisional sebelum menyatakan sesuai Kemendagri.
6. Field baru default nullable untuk kompatibilitas data lama. Jangan memaksa warga memberikan data yang tidak diketahui atau tidak relevan. Hindari pengumpulan data sensitif tambahan tanpa kebutuhan produk yang jelas.
7. Terapkan skema/validasi bersama untuk repeater anggota di keluarga dan halaman edit anggota agar keduanya konsisten. Semua field harus tersedia pada detail dan persist setelah reload.
8. Enkripsi nomor dokumen dan informasi pribadi tambahan sesuai klasifikasi; gunakan blind index hanya jika memang perlu dicari. Jangan memasukkan detail sensitif ke widget agregat.
9. Pertahankan aturan satu kepala keluarga, keunikan NIK, soft delete, restore, audit, dan transaksi keluarga beserta relasinya. Tambahkan test kegagalan di tengah penyimpanan melalui form untuk memastikan rollback nyata.

### Kriteria penerimaan

- Data sekolah dan perkawinan dapat dibuat, dibaca, diubah, dan dikosongkan dari alur keluarga maupun anggota.
- Keluarga lama tetap dapat dibuka/disimpan tanpa harus mengisi seluruh field baru.
- Label dan enum terverifikasi; jangan mengklaim profil “resmi Kemendagri” berdasarkan asumsi atau repository pihak ketiga saja.

## 6. Kode wilayah Kemendagri dan impor

### Riset sumber

1. Saat implementasi, cari sumber dengan kata kunci **“kode wilayah kemendagri di github”** dan telusuri sumber resmi Kemendagri yang dirujuk.
2. Bandingkan asal data, tanggal/versi rilis, lisensi, kelengkapan, dan perubahan kode. GitHub adalah sarana distribusi, bukan bukti otomatis bahwa data resmi atau terbaru.
3. Catat URL sumber, versi/tanggal snapshot, checksum bila tersedia, lisensi, serta pemetaan kolom di dokumentasi. Jangan mengambil source code remote lalu menjalankannya tanpa pemeriksaan.
4. Jangan menganggap RT/RW/dusun tersedia dalam dataset nasional; cek cakupannya. Pertahankan node lokal di bawah desa dengan penanda sumber lokal.

### Implementasi impor

1. Buat importer/command yang dapat diulang, memiliki dry-run, validasi, laporan hasil/error, dan transaksi per batch. Gunakan snapshot berformat jelas; jangan scraping sumber remote saat pengguna membuka form.
2. Simpan kode sebagai string agar nol di depan dan separator tidak hilang. Bedakan kode sumber, level, sumber data, dan identitas internal.
3. Impor/upsert berdasarkan kunci sumber yang stabil. Jangan mengganti primary key wilayah lama atau merusak FK keluarga/admin dan path.
4. Sediakan mekanisme pemetaan kode contoh ke kode resmi yang dapat ditinjau. Jangan otomatis mencocokkan berdasarkan nama saja atau menghapus wilayah yang tidak muncul dalam snapshot baru.
5. Node referensi yang baru diimpor default nonaktif untuk penggunaan operasional. Aktivasi merupakan aksi pengelola yang terpisah dan mengikuti aturan leluhur.
6. Jika sumber tidak memuat negara, gunakan root Indonesia yang sudah ada. Bentuk/validasi hierarki sebelum menghitung path; jangan melewati observer tanpa pengganti validasi yang setara.
7. Deteksi pemekaran, perubahan induk/kode, dan konflik sebagai laporan untuk rekonsiliasi. Aturan sekarang melarang pemindahan induk; jangan mengabaikannya secara diam-diam melalui query langsung.
8. Data contoh tetap khusus pengembangan. Jangan menjalankan `DatabaseSeeder` ke database produksi sebagai pengganti impor resmi.

### Kriteria penerimaan

- Impor ulang snapshot yang sama tidak menambah duplikat atau merusak relasi.
- Dry-run tidak menulis data dan melaporkan perubahan yang direncanakan.
- Kode bernol awal, konflik, data induk hilang, serta kegagalan batch diuji.
- Sumber dan keterbatasan cakupan dapat dilacak oleh pengembang/pengelola.

## 7. Tampilan lebih berwarna dan mudah dibaca

1. Kembangkan palet yang lebih berwarna dari tema saat ini tanpa menjadikan semua komponen warna-warni. Gunakan token semantik konsisten: primary teal/blue, sukses hijau, perhatian amber, bahaya merah, informasi biru, dan aksen violet untuk kategori bila cocok.
2. Bedakan level wilayah melalui badge berlabel/ikon dan aksen yang terukur. Status aktif/nonaktif tidak boleh mengandalkan warna saja. Hindari gradasi yang menyamarkan teks atau memberi kesan hierarki palsu.
3. Grafik menggunakan beberapa warna yang dapat dibedakan, legenda jelas, dan warna kategori konsisten antarhalaman. Jangan menyamakan warna kategori dengan status berbahaya tanpa alasan.
4. Kelola token di tema Filament; hindari inline style tersebar. Periksa kontras teks, fokus keyboard, hover, selected state, disabled state, error, dan empty state.
5. Verifikasi dashboard, tree, daftar admin, form keluarga, detail anggota, serta login pada desktop dan layar kecil. Periksa label panjang dan breadcrumb wilayah yang dalam.
6. Pilih palet final secara praktis dengan pemeriksaan visual; jangan berhenti pada usulan warna atau hanya mengubah warna primary.

## 8. Urutan pelaksanaan

1. Baca implementasi dan test; dokumentasikan keputusan skema/profil serta perilaku aktivasi.
2. Tambahkan migration kompatibel, model, layanan validasi/akses bersama, dan audit.
3. Implementasikan aktivasi wilayah dan pencarian hierarki.
4. Implementasikan identitas admin, direktori admin, filter tree, counter, dan tautan konteks.
5. Lengkapi profil anggota pada seluruh alur create/edit/detail.
6. Riset dan implementasikan importer wilayah dengan dry-run serta pemetaan aman.
7. Terapkan palet dan verifikasi visual seluruh alur.
8. Jalankan pemeriksaan integrasi, perbaiki kegagalan, dan perbarui `CODEBASE.md` dengan perilaku final.

Jangan memperluas scope menjadi penggantian framework, API publik, atau perombakan role tanpa kebutuhan. Gunakan keputusan implementasi yang masuk akal untuk detail kecil; minta klarifikasi hanya bila keputusan memengaruhi makna data/hak akses secara signifikan dan tidak bisa disimpulkan dari konteks.

## 9. Definisi selesai

Implementasi selesai hanya bila semua poin berikut terbukti:

- [ ] Status aktif wilayah beserta leluhur ditegakkan di server dan UI, dengan data lama tetap utuh.
- [ ] Profil admin opsional tersimpan lengkap; istilah antarmuka konsisten memakai Admin.
- [ ] Kelola Wilayah dapat dicari berdasarkan nama/kode dan menghubungkan wilayah dengan akun yang berhak dilihat.
- [ ] Direktori admin dapat difilter sampai subtree Jawa Barat/Bojongsoang/RT, dengan counter dan breadcrumb konsisten.
- [ ] Create dari konteks wilayah tidak bisa dialihkan diam-diam ke wilayah lain lewat manipulasi request.
- [ ] Profil anggota lengkap pada form keluarga, edit anggota, dan detail, termasuk pendidikan serta perkawinan.
- [ ] Importer kode wilayah memiliki sumber terdokumentasi, dry-run, idempotensi, dan penanganan konflik; data lokal tidak rusak.
- [ ] Palet baru diterapkan dan dicek secara visual, bukan hanya ditulis sebagai rencana.
- [ ] Test mencakup peran/isolasi, aktivasi leluhur, persist profil opsional, validasi kondisional, impor, dan transaksi relasi dari form.
- [ ] Test lama tetap lulus; jalankan `composer test` dan `npm run build`, serta pemeriksaan format yang relevan. Laporkan perintah dan hasil aktual, bukan asumsi.
- [ ] Periksa query tree/counter/filter pada data volume: pagination, lazy loading, dan tidak ada N+1 per node.
- [ ] Jalankan verifikasi migrasi pada database uji yang mewakili data lama; jangan memakai database pengguna untuk percobaan destruktif.
- [ ] `CODEBASE.md` menjelaskan skema, flow, konfigurasi, sumber wilayah, dan cara memakai fitur yang sudah final.

Laporan akhir harus menyebut fitur yang selesai, pengujian yang dijalankan beserta hasilnya, dan batasan yang masih nyata. Jika akses sumber atau dependensi menghalangi verifikasi, sebutkan bagian yang terhalang dan jangan mengklaim seluruh aplikasi sudah siap.

## Preferensi pengujian antarmuka

Gunakan **Microsoft Edge** untuk pengujian browser sesuai permintaan pengguna. Verifikasi desktop dan emulasi layar kecil menggunakan database dummy yang terpisah dari database operasional.
