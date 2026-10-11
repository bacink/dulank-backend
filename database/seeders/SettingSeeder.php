<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ((array) config('dulank.documents') as $type => $prefix) {
            Setting::query()->updateOrCreate(
                ['group' => 'prefixes', 'key' => $type],
                ['value' => ['prefix' => $prefix]],
            );
        }

        Setting::query()->updateOrCreate(
            ['group' => 'company', 'key' => 'profile'],
            ['value' => [
                'name' => 'PT Dulank Semesta Cida',
                'email' => 'info@dulank.test',
                'phone' => null,
                'address' => 'Jl. Arif Rahman Hakim / Niaga (depan stasiun) Karawang Kel. Nagasari Kec. Karawang Barat Kab. Karawang Jawa Barat',
                'npwp' => null,
                'mapEmbedUrl' => 'https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d15862.718289330365!2d107.299863!3d-6.305763!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e69765284335fd5%3A0x91110be4c9c4c9d4!2sPercetakan%20Dulank%20PT.%20Dulank%20Semesta%20Cida!5e0!3m2!1sid!2sid!4v1746143056651!5m2!1sid!2sid',
            ]],
        );

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
