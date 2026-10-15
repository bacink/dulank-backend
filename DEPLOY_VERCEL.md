# Perbaikan deployment Dulank Laravel API di Vercel

Proyek: **Laravel 13.35**, PHP **8.4** (`vercel-php@0.8.0`). React frontend merupakan **Vercel Project terpisah**.

## Apa yang diperbaiki

1. `api/index.php` sekarang memuat `bootstrap/vercel.php` lalu entry point Laravel standar `public/index.php`.
2. `bootstrap/vercel.php` menyiapkan direktori sementara yang dapat ditulis pada `/tmp`, lalu mengatur lokasi cache bootstrap, storage sementara, compiled views, dan logging ke `stderr` sebelum Laravel dijalankan.
3. Informasi script PHP dinormalisasi agar URL `/api/health` tidak keliru dibaca relatif terhadap script router `/api/index.php` ketika Vercel melakukan rewrite.
4. `bootstrap/app.php` tetap mendaftarkan seluruh route API, middleware autentikasi, Sanctum, Spatie, dan exception handler. Route `/` didaftarkan **tanpa middleware web yang membutuhkan session**, karena proyek ini hanya REST API.
5. Middleware `BlockBannedIp` melewati query database **hanya** untuk endpoint liveness `/api/health`. Endpoint lainnya tetap memakai pemeriksaan IP.
6. Ditambahkan `tests/Feature/DeploymentHealthTest.php` untuk menguji `/` dan `/api/health`.

## Langkah deploy

1. Ganti isi repository GitHub `dulank-backend` dengan proyek ZIP hasil perbaikan (jangan mengunggah folder induk ekstra).
2. Push ke branch `main`.
3. Di Vercel, pastikan **Root Directory = root repository** (lokasi `artisan` dan `vercel.json`), **Framework = Other**, dan semua override untuk Build Command, Install Command, Output Directory **OFF**. `vercel.json` sudah mengatur `outputDirectory: public` dan runtime PHP.
4. Lakukan deployment baru; untuk percobaan pertama setelah pembaruan, jangan gunakan cache build lama.
5. Pertahankan kredensial database dan `APP_KEY` yang sudah berfungsi. **Jangan menjalankan migrate:fresh** atau mengganti kunci enkripsi.

## Environment Variables yang perlu diperiksa

Contoh **nama** variabel dan jenis nilainya (bukan kredensial sebenarnya):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://dulank-backend.vercel.app
APP_KEY=<APP_KEY_LAMA_YANG_VALID>
DB_CONNECTION=mysql
DB_HOST=<HOST_MYSQL>
DB_PORT=<PORT_MYSQL>
DB_DATABASE=<NAMA_DATABASE>
DB_USERNAME=<USERNAME>
DB_PASSWORD=<PASSWORD>
FRONTEND_URLS=https://alamat-frontend.vercel.app
SESSION_DRIVER=array
CACHE_STORE=database
QUEUE_CONNECTION=sync
```

`LOG_CHANNEL=stderr` akan dipasang oleh entry point Vercel. `SESSION_DRIVER=array` tepat untuk API stateless menggunakan Bearer token. `CACHE_STORE=database` dianjurkan karena rate limiter dan daftar banned IP perlu berbagi cache antar-invocation; pastikan tabel `cache` dan `cache_locks` tersedia dari migrasi yang sudah dilakukan. `QUEUE_CONNECTION=sync` hanya sesuai jika pekerjaan boleh berjalan dalam satu request; jika bergantung pada worker, gunakan infrastruktur worker terpisah.

Jika penyedia database mengharuskan TLS dengan CA, atur `MYSQL_ATTR_SSL_CA` dan sertifikat yang sesuai; kode saja tidak dapat menyediakan sertifikat/kredensial itu.

## Verifikasi sesudah deployment

| Endpoint | Respons yang diharapkan |
| --- | --- |
| `GET /` | HTTP 200, JSON `success:true`, `data.status:ok` |
| `GET /api/health` | HTTP 200, JSON `status:success` |
| `GET /up` | HTTP 200, health check Laravel |
| `GET /api/v1/products` | Respons endpoint asli, sekaligus menguji koneksi database dan middleware |

`/api/health` adalah liveness check, bukan pemeriksaan koneksi database. Untuk login dan operasi produk/admin, uji endpoint nyata sesuai kebutuhan autentikasi.

## Batasan Vercel yang harus diperhatikan

**File dalam `/tmp` tidak permanen.** Direktori baru digunakan untuk cache dan file kerja Laravel, **bukan untuk menyimpan upload pengguna**. Proyek ini memiliki fitur upload avatar, artwork, bukti pembayaran, dan dokumen yang saat ini default ke disk lokal `public`. Supaya upload benar-benar persisten dan dapat diakses sesudah function berakhir, diperlukan layanan object storage eksternal seperti S3-compatible storage, pengaturan `DULANK_UPLOAD_DISK=s3`, serta penyesuaian URL download/link sesuai penyedia storage. Jangan menganggap upload ke `/tmp` sebagai penyimpanan permanen.

## Cakupan pengujian paket ini

- Semua file PHP di folder utama proyek telah diperiksa sintaksnya.
- Konfigurasi JSON Vercel valid.
- Entry point PHP telah diuji secara terpisah menggunakan mock front controller untuk URL `/`, `/api/health`, dan `/api/v1/products`, termasuk penyiapan `/tmp`.
- **Tes Laravel end-to-end belum bisa dilakukan dalam lingkungan ini** karena ZIP awal tidak berisi `vendor/` dan instalasi Composer membutuhkan akses paket eksternal; kredensial database dan Vercel kamu juga tidak ada di ZIP. Karena itu hasil deploy nyata tetap harus diverifikasi menggunakan endpoint pada tabel di atas.
