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

Endpoint admin (`/api/v1/admin/*`, ability `admin` + permission modul):

- Auth: `auth/login`, `auth/me`, `auth/logout`, `profile` (show/update/password), `uploads/avatar`, `uploads/image`
- `orders.manage`: orders (list/detail/status/stats), delivery-notes (CRUD), sales-returns (CRUD + refund)
- `finance.manage`: payments (list/record/verify/reject), invoices (list/detail + record payment), bank-account-types & bank-accounts (CRUD + ledger + statement), money-transfers, cash-advances (+installments), income-categories & incomes (+cancel), expense-categories & expenses (+payments), input-taxes & output-taxes, tax-rates (CRUD), `finance/summary`, `finance/cash-flow`, `finance/customer-entries` (+balance)
- `users.manage`: roles (CRUD + permissions), `PUT users/{id}/roles`, users/members (list/update/delete), user-admins (CRUD)
- `products.manage`: products (CRUD + varian + gambar + price range otomatis), categories, sub-categories, units, variants (master), product-processes, workshop-services, **paper-groups/items/sizes/prices, printing-machines, die-cutting-blades, calculator-components (fiks/minimum/jasa-lain), profit-tiers, calculator/partners, calculator/listings (+freeze & moderation-history), calculator/dashboard**
- `sales.manage`: sales/POS (list/create/payments/void), quotations (CRUD + `POST {id}/order`), request-quotations (CRUD + duplicate), banners, coupons, vouchers, discount-plans, discounts, `pos/products`, `pos/held-orders`
- `orders.manage`: orders (list/detail/status/stats), delivery-notes (CRUD), sales-returns (CRUD + refund), **webstore/carts, webstore/checkout, webstore/wishlist, webstore/reviews (+stats), webstore/contact-forms (+stats)**
- `peoples.manage`: customers (CRUD + saldo derived), customer-types, stores, addresses (polymorphic customer/supplier + stats)
- `purchases.manage`: suppliers (CRUD), purchase-categories, purchase-items, purchases (CRUD + `payments` utang), purchase-orders (CRUD), purchase-returns (CRUD + `payments` refund)
- `hrm.manage`: departments & designations (CRUD), employees (CRUD), employee-salaries (upsert + allowances), payslips (`generate` + `{slipNo}/pay`), incentives (CRUD + `pay`)
- `workflow.manage`: flow-categories/flow-names/flow-templates (CRUD), work-flows (+steps), job-orders (+steps + `status`), job-branches (CRUD + `{id}/complete` → riwayat + insentif), job-progress, `job-list`, `my-jobs`, my-incentives
- `content.manage`: blogs/blogs-categories/tags/comments, faqs + categories, clients, download-files (+upload), footer, appearance
- `settings.manage`: `settings/{group}` (read/update), custom-fields, ban-ips, languages (+translations), `gateways/{group}` (payment-gateways, sms-gateways)
- `reports.view`: dashboard, `reports/{type}` (15 tipe: sales, purchases, finance, products, customers, annual, best-seller, customer-dues, supplier-dues, suppliers, taxes, profit-loss, invoices, expenses, incomes), `reports/{type}/export` (CSV default, `?format=xlsx` untuk Excel)

Dokumen cetak PDF (`?` lihat grup permission masing-masing): `invoices/{no}/pdf`, `quotations/{no}/pdf`, `delivery-notes/{no}/pdf`, `payslips/{no}/pdf`, `purchases/{no}/pdf`.

Keamanan tambahan: middleware `BlockBannedIp` menolak request dari IP di tabel `ban_ips` (cache 5 menit, otomatis invalid saat ban dibuat/dihapus).

Importer data mock (idempoten, `--dry-run`):

```bash
php artisan dulank:import store   # 22 JSON dulank-store → tabel kanonik (Fase 1)
php artisan dulank:import admin   # data/*.json dulank-admin: customer, supplier, store, purchase, paper self, prefix
```

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
- ✅ **Fase 2** — checkout `POST /orders` (+ invoice & timeline), quotation CRUD, wishlist add, address CRUD, profil/password/2FA (TOTP), upload avatar/artwork, bukti pembayaran
- ✅ **Fase 3** — admin operations lengkap: monitoring order + status/stats, verifikasi & pencatatan pembayaran (invoice recalculate), sales/POS, delivery note, sales return (+refund), quotation admin + konversi, RFQ + duplikat, **purchases** (supplier, master item, pembelian + utang, PO, retur + refund), role/permission matrix, activity log, **importer data admin**
- ✅ **Fase 4A — Finance**: bank account + buku besar (saldo = opening + credit − debit), money transfer (2 entri ledger), cash advance + cicilan, income/expense (+pajak input/output), saldo customer dari `customer_account_entries` (otomatis dari checkout & pembayaran terverifikasi), laporan ringkas & arus kas
- ✅ **Fase 4B — HRM & Workflow**: department/designation/employee, payroll (gaji + tunjangan → payslip → bayar via payment/ledger), insentif, flow category/name/template, work flow + steps, job order + steps, job branch (+complete → riwayat + insentif per assignee dari `flow_names.incentive × qty`), job progress, my-incentives
- ✅ **Fase 5 — Konten, Settings, Reports**: blog/FAQ/client/download/footer/appearance CRUD, settings per grup (`settings/{group}`), custom fields, ban IP (+enforcement middleware), bahasa + terjemahan (progress), dashboard admin, laporan agregat (sales/purchases/finance/products/customers) + export CSV/Excel, dokumen cetak PDF (invoice, quotation, delivery note, payslip, purchase)
- ⏭️ Sisa: integrasi aktual payment gateway/SMS/email (konfigurasi & daftar gateway sudah tersedia via settings), worker queue produksi, dan **adaptasi frontend** (proxy `/api` + login UI store, adapter admin per modul)

Catatan: endpoint milik user (cart/orders/tickets/dst.) memerlukan token `store`; halaman store yang belum punya login UI perlu dihubungkan pada sesi adaptasi frontend.


## Fondasi data (20 Oktober 2026)

Kontrak aktif, hasil audit, batas implementasi, dan lokasi backup ada di [PRD bagian 14](PRD.md#14-fondasi-data-dan-kontrak-kanonik--20-oktober-2026).

```sh
php artisan migrate --no-interaction
php artisan dulank:audit-data --json
php artisan db:seed --class=DemoMasterDataSeeder --no-interaction
php artisan dulank:audit-data --json
```

Seeder contoh hanya local/testing, terpisah dari seeder instalasi, idempoten, dan tidak menimpa record existing. Tidak membuat transaksi maupun saldo/stok awal. Jangan memakai `migrate:fresh`, truncate, atau menjalankan ulang seluruh seeder lama pada database operasional untuk mengisi contoh.

Laporan audit menyajikan agregat tanpa identitas pribadi. Exit 1 berarti ada error; opsi `--strict` juga gagal pada warning. Hasil audit bersih tidak menggantikan uji alur transaksi dan rekonsiliasi keuangan.
