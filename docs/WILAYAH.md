# Sumber dan impor wilayah

Snapshot `database/data/wilayah-2026-10-31.json.gz` berisi 91.599 record hasil parsing tuple kode/nama dari [cahyadsn/wilayah](https://github.com/cahyadsn/wilayah), diunduh 31 Oktober 2026. Lisensi MIT, atribusi cahya dsn; salinan lisensi disertakan. Cakupan provinsi sampai desa/kelurahan; RT/RW/dusun tetap data lokal.

README upstream mengacu Kepmendagri 300.2.2-2430 Tahun 2025, sedangkan header berkas SQL masih menyebut 2138 Tahun 2025 dan edit Februari 2026. Karena perbedaan metadata ini, snapshot diperlakukan sebagai referensi pihak ketiga yang harus direkonsiliasi, bukan sertifikasi daftar resmi terbaru. Referensi pemerintah: [JDIH Aceh](https://jdih.acehprov.go.id/dih/detail/82fbc6e4-ea70-442c-8f28-4406b2d20b6d).

Pembanding riset: [yonatanyl/KODE-WILAYAH-KEPMENDAGRI-2025](https://github.com/yonatanyl/KODE-WILAYAH-KEPMENDAGRI-2025) mendistribusikan hasil OCR/Excel, CC BY 4.0; [edwin/database-wilayah-kemendagri](https://github.com/edwin/database-wilayah-kemendagri) menyebut pembaruan 2021. Snapshot yang disertakan memakai cahyadsn karena struktur tuple kode/nama dapat dinormalisasi tanpa menjalankan SQL sumber.

SHA256 sumber SQL: `c4c3396d9380d4edee072af1d9dff83573b574d7cd00a6562cf82e200e954031`.
SHA256 snapshot gzip: `8b198c756bf6a64b3bcf6055799c0dbb184c2ee9de5c39d91b0f1ff195ccd671`.

## Penggunaan

Pastikan migrasi terbaru dan root negara sudah tersedia. Gunakan PHP 8.2–8.4 sesuai lockfile. Cadangkan database sebelum impor operasional.

```sh
php -d memory_limit=512M artisan wilayah:import database/data/wilayah-2026-10-31.json.gz --dry-run
php -d memory_limit=512M artisan wilayah:import database/data/wilayah-2026-10-31.json.gz
```

Dry-run menjalankan validasi dan penulisan dalam transaksi yang dibatalkan, sehingga tidak ada perubahan persisten termasuk audit. Ia tetap dapat mengambil lock database; jalankan pengujian volume pada database terpisah. Impor atomik untuk seluruh snapshot agar konflik di akhir tidak menyisakan setengah hierarki. Ini lebih ketat dari commit per batch, dengan konsekuensi transaksi lebih lama. Jangan menguji impor nasional di database yang sedang dipakai untuk preview UI.

Node baru nonaktif. Aktifkan dari negara/provinsi ke bawah sebelum menambahkan data operasional. Impor ulang memakai pasangan sumber/kode, memperbarui nama/versi, mempertahankan ID dan flag aktif, tidak menghapus data lokal. Perubahan induk/level memerlukan rekonsiliasi terpisah.

Pemetaan data contoh yang sudah digunakan harus eksplisit. Buat JSON objek kode resmi → ID lokal, misalnya `{"32": 2}`, lalu gunakan `--mapping=/path/mapping.json`. Review setiap ID dan induknya; tidak ada pencocokan nama otomatis. Root dan seluruh rantai induk harus sesuai sebelum pemetaan dapat diterima.

## Format snapshot dan pembaruan

JSON atau JSON gzip memuat `source_url`, `version`, `license`, `rows`; metadata tambahan seperti `source_sha256` boleh disertakan. Setiap baris: `code` string, `name`, `level` (`provinsi`, `kabupaten_kota`, `kecamatan`, `desa`), dan `parent_code` string atau null untuk provinsi. Kode disimpan string; separator dan nol awal dipertahankan.

Untuk menormalisasi sumber SQL baru, unduh data dari upstream setelah memeriksa versi/lisensi, lalu:

```sh
python3 scripts/prepare_wilayah.py /path/wilayah.sql /path/snapshot.json.gz
```

Script hanya mengekstrak tuple data dengan format yang dikenali dan tidak mengeksekusi SQL. Tanggal snapshot/URL pada script perlu diperbarui saat mengambil rilis baru. Importer menolak duplikat kode, level tidak didukung, induk hilang/siklus, perubahan induk/level, dan mapping bertabrakan. Pesan konflik tersedia di output command dengan exit code gagal.
