# Dulank Backend

Backend API untuk **dulank-store** dan **dulank-admin** — Laravel 13, token-based auth (Sanctum), MySQL.

Acuan produk & arsitektur: [`PRD.md`](PRD.md).

## Stack

- Laravel 13 (PHP 8.3–8.5), MySQL 8
- Auth: Sanctum personal access token, ability `store` / `admin`
- Otorisasi: `spatie/laravel-permission` + Policy (admin bypass via `Gate::before`)
- Filter list: `spatie/laravel-query-builder` · Audit: `spatie/laravel-activitylog`
- API docs: `dedoc/scramble` (`/docs/api`) · PDF: `barryvdh/laravel-dompdf` · Excel: `maatwebsite/excel`
- Test: Pest v4 · Style: Pint · Statis: Larastan

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# sesuaikan DB_*, FRONTEND_URLS, DULANK_STORE_PATH di .env

php artisan migrate --seed          # skema + role, admin, prefix, wilayah (provinsi/kabupaten/kodepos)
php artisan dulank:import store     # impor data mock store (idempoten; --dry-run untuk simulasi)
php artisan storage:link            # untuk upload (Fase 2+)
php artisan serve
```

Akun default (dari seeder, dapat diubah via `DULANK_ADMIN_*`):

| Peran | Email | Password |
|---|---|---|
| Admin | `admin@dulank.test` | `admin12345` |
| Store (hasil impor) | `ahmad@example.com` | `password` |

## API

- Kanonik: `/api/v1/*` (store) dan `/api/v1/admin/*` (admin)
- Alias kompatibilitas mock store lama: `/api/*`
- Envelope: `{ "success": true, "data": ..., "meta": { page, limit, total } }`
- Error: `{ "success": false, "message": "...", "errors": { ... } }`
- Auth:
  - `POST /api/v1/auth/register|login`, `POST /api/v1/auth/logout`, `GET /api/v1/auth/me`
  - `POST /api/v1/admin/auth/login`, `GET /api/v1/admin/auth/me`
- Wilayah: `GET /api/v1/regions/provinces|regencies|districts`
- Dokumentasi interaktif: `GET /docs/api` (Scramble)

Endpoint store (prefixed `/api/v1`, alias `/api` untuk yang lama):

- Publik: products, categories, catalog/{kind}, blog, faqs, sale-banners, regions/*
- Auth: `register`, `login`, `logout`, `me`
- Commerce: cart (CRUD + clear), wishlist (list/add/delete), orders (list/checkout/detail), quotations (list/create/detail/update), tickets (list/create/detail/reply), invoices (+alias `billings`), payments (list/upload bukti)
- Akun: profile (show/update), profile/password, profile/2fa (enable/confirm/disable), addresses (CRUD + default), uploads/avatar, uploads/artwork

## Perintah penting

```bash
php artisan test --compact               # seluruh suite
vendor/bin/pest tests/Feature/Store      # subset store
vendor/bin/pint --dirty                  # format
php artisan dulank:import store --dry-run
php artisan route:list --path=api
```

## Status roadmap

- ✅ **Fase 0** — fondasi: auth token, roles/permissions, wilayah, settings, importer
- ✅ **Fase 1** — parity 26 endpoint store + alias `/api` + importer data store
- ✅ **Fase 2** — checkout `POST /orders` (+ invoice & timeline), quotation CRUD, wishlist add, address CRUD, profil/password/2FA (TOTP), upload avatar/artwork, bukti pembayaran (verifikasi admin menyusul)
- ⏭️ Fase 3 — admin core (sales, purchases, delivery, finance dasar, verifikasi pembayaran), importer admin
- ⏭️ Fase 4–5 — finance/HRM/workflow, konten/settings/reports, integrasi

Catatan: endpoint milik user (cart/orders/tickets/dst.) memerlukan token `store`; halaman store yang belum punya login UI perlu dihubungkan pada sesi adaptasi frontend.
