<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Prefixes nomor dokumen
        foreach ((array) config('dulank.documents') as $type => $prefix) {
            Setting::query()->updateOrCreate(
                ['group' => 'prefixes', 'key' => $type],
                ['value' => ['prefix' => $prefix]],
            );
        }

        // 2. Company Profile
        Setting::query()->updateOrCreate(
            ['group' => 'company', 'key' => 'profile'],
            ['value' => [
                'id' => 'comp-1',
                'companyName' => 'PT Dulank Semesta Cida',
                'tagline' => 'Solusi Percetakan & Kemasan Terpercaya',
                'email' => 'info@dulank.test',
                'phone' => '0812-3456-7890',
                'fax' => '0267-123456',
                'website' => 'https://dulank.com',
                'npwp' => '01.234.567.8-408.000',
                'currency' => 'IDR',
                'address' => 'Jl. Arif Rahman Hakim / Niaga (depan stasiun) Karawang Kel. Nagasari Kec. Karawang Barat Kab. Karawang Jawa Barat',
                'country' => 'Indonesia',
                'province' => 'Jawa Barat',
                'city' => 'Kab. Karawang',
                'postalCode' => '41312',
                'mapEmbedUrl' => 'https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d15862.718289330365!2d107.299863!3d-6.305763!3m2!1i768!4f13.1!3m3!1m2!1s0x2e69765284335fd5%3A0x91110be4c9c4c9d4!2sPercetakan%20Dulank%20PT.%20Dulank%20Semesta%20Cida!5e0!3m2!1sid!2sid!4v1746143056651!5m2!1sid!2sid',
                'images' => [
                    'logo' => '/assets/img/logo-small.png',
                    'icon' => '/assets/img/logo-small.png',
                    'favicon' => '/assets/img/kacetak.jpeg',
                    'darkLogo' => '/assets/img/logo-small.png',
                ],
            ]],
        );

        // 3. Currencies
        Setting::query()->updateOrCreate(
            ['group' => 'currencies', 'key' => 'default'],
            ['value' => [
                'code' => 'IDR',
                'name' => 'Indonesian Rupiah',
                'symbol' => 'Rp',
                'rate' => 1,
                'thousandSeparator' => '.',
                'decimalSeparator' => ',',
                'decimals' => 0,
                'status' => 'Active',
            ]],
        );

        // 4. POS Settings
        Setting::query()->updateOrCreate(
            ['group' => 'pos', 'key' => 'general'],
            ['value' => [
                'enableSound' => true,
                'autoPrintReceipt' => true,
                'defaultCustomer' => 'Retail / Umum',
                'allowNegativeStock' => false,
                'enableDiscount' => true,
                'receiptFooter' => 'Terima kasih atas kepercayaan Anda mencetak bersama PT. Dulank Semesta Cida!',
            ]],
        );

        // 5. Printer Settings
        Setting::query()->updateOrCreate(
            ['group' => 'printers', 'key' => 'config'],
            ['value' => [
                'receiptPrinterType' => 'Thermal 80mm',
                'paperWidth' => 80,
                'invoicePrinterType' => 'Laser/Inkjet A4',
                'autoCut' => true,
                'copies' => 1,
            ]],
        );

        // 6. Invoice Settings
        Setting::query()->updateOrCreate(
            ['group' => 'invoices', 'key' => 'general'],
            ['value' => [
                'defaultDueDays' => 14,
                'termsAndConditions' => '1. Pembayaran dilakukan via transfer bank resmi perusahaan.\n2. Barang cetakan yang telah disetujui proofing tidak dapat dibatalkan.',
                'footerNotes' => 'Barang yang sudah dibeli dan dicetak sesuai spesifikasi ACC tidak dapat dikembalikan.',
                'template' => 'standard',
            ]],
        );

        // 7. Email Settings
        Setting::query()->updateOrCreate(
            ['group' => 'email', 'key' => 'smtp'],
            ['value' => [
                'mailDriver' => 'smtp',
                'mailHost' => 'smtp.mailtrap.io',
                'mailPort' => 587,
                'mailUsername' => 'admin@dulank.test',
                'mailEncryption' => 'tls',
                'fromAddress' => 'info@dulank.com',
                'fromName' => 'PT Dulank Semesta Cida',
            ]],
        );

        // 8. SMS & OTP Settings
        Setting::query()->updateOrCreate(
            ['group' => 'sms', 'key' => 'config'],
            ['value' => [
                'provider' => 'Zenziva',
                'senderId' => 'DULANK',
                'status' => 'Active',
            ]],
        );

        Setting::query()->updateOrCreate(
            ['group' => 'otp', 'key' => 'config'],
            ['value' => [
                'expireMinutes' => 5,
                'maxAttempts' => 3,
                'channel' => 'sms_and_email',
            ]],
        );

        // 9. Payment Gateways
        Setting::query()->updateOrCreate(
            ['group' => 'gateways', 'key' => 'payment_gateway'],
            ['value' => [
                [
                    'id' => 'gw-midtrans',
                    'name' => 'Midtrans Payment Gateway',
                    'code' => 'midtrans',
                    'status' => 'Active',
                    'config' => [
                        'mode' => 'sandbox',
                        'merchantId' => 'M123456',
                        'clientKey' => 'SB-Mid-client-XXXXX',
                    ],
                ],
                [
                    'id' => 'gw-manual',
                    'name' => 'Transfer Bank Manual (BCA, Mandiri, BRI)',
                    'code' => 'bank_transfer',
                    'status' => 'Active',
                    'config' => [
                        'instruction' => 'Silakan upload bukti transfer setelah pembayaran.',
                    ],
                ],
            ]],
        );

        // 10. Localization & System
        Setting::query()->updateOrCreate(
            ['group' => 'localization', 'key' => 'general'],
            ['value' => [
                'timezone' => 'Asia/Jakarta',
                'dateFormat' => 'DD/MM/YYYY',
                'timeFormat' => '24h',
                'defaultLanguage' => 'id',
            ]],
        );

        Setting::query()->updateOrCreate(
            ['group' => 'system', 'key' => 'general'],
            ['value' => [
                'appName' => 'Dulank ERP & Printing Management System',
                'maintenanceMode' => false,
                'sessionTimeout' => 120,
            ]],
        );

        // 11. Security & GDPR
        Setting::query()->updateOrCreate(
            ['group' => 'security', 'key' => 'policy'],
            ['value' => [
                'twoFactorEnabled' => false,
                'maxLoginAttempts' => 5,
                'lockoutDurationMinutes' => 15,
                'passwordMinLength' => 8,
            ]],
        );

        Setting::query()->updateOrCreate(
            ['group' => 'gdpr', 'key' => 'consent'],
            ['value' => [
                'cookieConsentEnabled' => true,
                'privacyPolicyUrl' => '/privacy-policy',
                'termsUrl' => '/terms-of-service',
            ]],
        );

        // 12. Storage
        Setting::query()->updateOrCreate(
            ['group' => 'storage', 'key' => 'config'],
            ['value' => [
                'disk' => 'public',
                'maxUploadKb' => 8192,
                'allowedExtensions' => ['jpg', 'jpeg', 'png', 'pdf', 'ai', 'cdr', 'psd', 'zip'],
            ]],
        );

        // 13. Appearance
        Setting::query()->updateOrCreate(
            ['group' => 'appearance', 'key' => 'featuredServices'],
            ['value' => [
                [
                    'title' => 'Cetak Buku & Kalender',
                    'subtitle' => 'Offset & Full Color',
                    'description' => 'Produksi buku, kalender, dan dokumen cetak lain dengan kualitas tinggi, pilihan ukuran, jilid, dan kertas yang beragam.',
                    'image' => '/images/brosur.jpg',
                ],
                [
                    'title' => 'Sablon & Custom Merchandise',
                    'subtitle' => 'Kaos, Gelas & Spanduk',
                    'description' => 'Layanan sablon kaos, cetak gelas custom, hingga produksi spanduk skala besar untuk kebutuhan personal maupun perusahaan.',
                    'image' => '/images/yasin.jpg',
                ],
            ]],
        );
    }
}
