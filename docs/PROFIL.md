# Kamus profil tambahan

Identitas admin disimpan sebagai satu kolom JSON terenkripsi `users.identitas`; profil anggota sebagai `anggota.profil`. Pilihan ini mendukung field opsional tanpa membuat tabel warga menjadi akun login. Semua nilai baru nullable dan tidak dicari secara plaintext di SQL. Seluruh JSON dikecualikan dari serialisasi model. Akses form admin mengikuti policy manajemen akun; profil anggota mengikuti policy/scope keluarga. Jangan mencatat isi JSON ke audit/log.

| Kelompok | Field | Tipe / nilai | Aturan |
|---|---|---|---|
| Identitas admin | nama_lengkap, tempat_lahir, jabatan | teks | Opsional, batas panjang form |
| Identitas admin | nik | string | Jika diisi harus 16 digit |
| Identitas admin | kontak, alamat | string | Kontak bukan integer; alamat maksimal 500 karakter |
| Identitas admin | tanggal_lahir, jenis_kelamin | tanggal; L/P | Tanggal tidak di masa depan |
| Pendidikan berjalan | status_sekolah | belum_sekolah / bersekolah / tidak_bersekolah | Opsional; berbeda dari pendidikan terakhir |
| Pendidikan berjalan | nama_sekolah, jenjang_berjalan, kelas_program, lokasi_sekolah | string maksimal 200 | Informasi institusi, bukan kode resmi nasional |
| Perkawinan | tanggal_perkawinan, tempat_perkawinan | tanggal / teks | Tanggal tidak di masa depan |
| Perkawinan | pencatatan_perkawinan | tercatat / belum_tercatat / tidak_diketahui | Opsional |
| Perkawinan | nomor_perkawinan | string maksimal 200 | Nomor akta/buku nikah, terenkripsi |
| Perceraian | tanggal_perceraian, nomor_perceraian | tanggal / string | Ditampilkan dan disimpan hanya untuk cerai_hidup |
| Administrasi | kewarganegaraan, nomor_akta_lahir | string maksimal 200 | Opsional, terenkripsi |

Referensi elemen kelahiran/perkawinan/perceraian dan pendidikan terakhir: [F-1.01 Dukcapil Klaten](https://isla.dukcapil.klaten.go.id/assets/formulir/F-1.01.pdf). Nama/lokasi sekolah dan program studi adalah tambahan produk. Form ini bukan salinan lengkap atau jaminan kesesuaian terhadap seluruh regulasi administrasi kependudukan terbaru. Data kesehatan/disabilitas tidak ditambahkan tanpa kebutuhan produk khusus.

Nilai identitas opsional yang diisi tersimpan dan muncul kembali pada edit. Pengosongan field menghapus nilainya. Blank password pada edit tetap mempertahankan password. Kategori baru memakai pilihan aplikasi yang terbaca, bukan angka referensi resmi yang diasumsikan.
