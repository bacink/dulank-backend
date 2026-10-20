<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Regency;
use Illuminate\Database\Seeder;

class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        $districtsByRegency = [
            'Kab. Karawang' => [
                ['name' => 'Karawang Barat', 'postal_code' => '41311'],
                ['name' => 'Karawang Timur', 'postal_code' => '41314'],
                ['name' => 'Telukjambe Timur', 'postal_code' => '41361'],
                ['name' => 'Telukjambe Barat', 'postal_code' => '41361'],
                ['name' => 'Klari', 'postal_code' => '41371'],
                ['name' => 'Cikampek', 'postal_code' => '41373'],
                ['name' => 'Rengasdengklok', 'postal_code' => '41352'],
                ['name' => 'Kotabaru', 'postal_code' => '41374'],
                ['name' => 'Majalaya', 'postal_code' => '41371'],
                ['name' => 'Purwasari', 'postal_code' => '41373'],
                ['name' => 'Jatisari', 'postal_code' => '41374'],
                ['name' => 'Rawamerta', 'postal_code' => '41382'],
                ['name' => 'Tempuran', 'postal_code' => '41385'],
                ['name' => 'Pedes', 'postal_code' => '41353'],
                ['name' => 'Batujaya', 'postal_code' => '41354'],
                ['name' => 'Cilamaya Wetan', 'postal_code' => '41384'],
            ],
            'Kota Adm. Jakarta Pusat' => [
                ['name' => 'Gambir', 'postal_code' => '10110'],
                ['name' => 'Tanah Abang', 'postal_code' => '10210'],
                ['name' => 'Menteng', 'postal_code' => '10310'],
                ['name' => 'Senen', 'postal_code' => '10410'],
                ['name' => 'Cempaka Putih', 'postal_code' => '10510'],
                ['name' => 'Johar Baru', 'postal_code' => '10560'],
                ['name' => 'Kemayoran', 'postal_code' => '10610'],
                ['name' => 'Sawah Besar', 'postal_code' => '10710'],
            ],
            'Kota Adm. Jakarta Selatan' => [
                ['name' => 'Kebayoran Baru', 'postal_code' => '12110'],
                ['name' => 'Kebayoran Lama', 'postal_code' => '12210'],
                ['name' => 'Pesanggrahan', 'postal_code' => '12260'],
                ['name' => 'Cilandak', 'postal_code' => '12430'],
                ['name' => 'Pasar Minggu', 'postal_code' => '12520'],
                ['name' => 'Jagakarsa', 'postal_code' => '12620'],
                ['name' => 'Mampang Prapatan', 'postal_code' => '12790'],
                ['name' => 'Pancoran', 'postal_code' => '12780'],
                ['name' => 'Tebet', 'postal_code' => '12810'],
                ['name' => 'Setiabudi', 'postal_code' => '12910'],
            ],
            'Kota Adm. Jakarta Barat' => [
                ['name' => 'Grogol Petamburan', 'postal_code' => '11450'],
                ['name' => 'Palmerah', 'postal_code' => '11480'],
                ['name' => 'Cengkareng', 'postal_code' => '11730'],
                ['name' => 'Kembangan', 'postal_code' => '11610'],
                ['name' => 'Kebon Jeruk', 'postal_code' => '11530'],
                ['name' => 'Kalideres', 'postal_code' => '11840'],
                ['name' => 'Tambora', 'postal_code' => '11210'],
                ['name' => 'Taman Sari', 'postal_code' => '11110'],
            ],
            'Kota Adm. Jakarta Timur' => [
                ['name' => 'Matraman', 'postal_code' => '13110'],
                ['name' => 'Pulo Gadung', 'postal_code' => '13210'],
                ['name' => 'Jatinegara', 'postal_code' => '13310'],
                ['name' => 'Duren Sawit', 'postal_code' => '13440'],
                ['name' => 'Kramat Jati', 'postal_code' => '13510'],
                ['name' => 'Makasar', 'postal_code' => '13570'],
                ['name' => 'Pasar Rebo', 'postal_code' => '13710'],
                ['name' => 'Ciracas', 'postal_code' => '13740'],
                ['name' => 'Cipayung', 'postal_code' => '13840'],
                ['name' => 'Cakung', 'postal_code' => '13910'],
            ],
            'Kota Adm. Jakarta Utara' => [
                ['name' => 'Penjaringan', 'postal_code' => '14440'],
                ['name' => 'Pademangan', 'postal_code' => '14420'],
                ['name' => 'Tanjung Priok', 'postal_code' => '14310'],
                ['name' => 'Koja', 'postal_code' => '14220'],
                ['name' => 'Cilincing', 'postal_code' => '14120'],
                ['name' => 'Kelapa Gading', 'postal_code' => '14240'],
            ],
            'Kota Bandung' => [
                ['name' => 'Coblong', 'postal_code' => '40132'],
                ['name' => 'Sukajadi', 'postal_code' => '40162'],
                ['name' => 'Cicendo', 'postal_code' => '40171'],
                ['name' => 'Andir', 'postal_code' => '40181'],
                ['name' => 'Sumur Bandung', 'postal_code' => '40111'],
                ['name' => 'Bandung Wetan', 'postal_code' => '40115'],
                ['name' => 'Lengkong', 'postal_code' => '40261'],
                ['name' => 'Regol', 'postal_code' => '40251'],
                ['name' => 'Astanaanyar', 'postal_code' => '40241'],
                ['name' => 'Bojongloa Kaler', 'postal_code' => '40231'],
            ],
            'Kota Bekasi' => [
                ['name' => 'Bekasi Barat', 'postal_code' => '17145'],
                ['name' => 'Bekasi Timur', 'postal_code' => '17111'],
                ['name' => 'Bekasi Utara', 'postal_code' => '17121'],
                ['name' => 'Bekasi Selatan', 'postal_code' => '17141'],
                ['name' => 'Rawa Lumbu', 'postal_code' => '17116'],
                ['name' => 'Medan Satria', 'postal_code' => '17132'],
                ['name' => 'Pondok Gede', 'postal_code' => '17411'],
                ['name' => 'Jatiasih', 'postal_code' => '17423'],
            ],
            'Kab. Bekasi' => [
                ['name' => 'Cikarang Pusat', 'postal_code' => '17530'],
                ['name' => 'Cikarang Barat', 'postal_code' => '17520'],
                ['name' => 'Cikarang Timur', 'postal_code' => '17530'],
                ['name' => 'Cikarang Utara', 'postal_code' => '17530'],
                ['name' => 'Cikarang Selatan', 'postal_code' => '17530'],
                ['name' => 'Tambun Selatan', 'postal_code' => '17510'],
                ['name' => 'Tambun Utara', 'postal_code' => '17510'],
                ['name' => 'Cibitung', 'postal_code' => '17520'],
            ],
            'Kota Bogor' => [
                ['name' => 'Bogor Tengah', 'postal_code' => '16121'],
                ['name' => 'Bogor Timur', 'postal_code' => '16143'],
                ['name' => 'Bogor Barat', 'postal_code' => '16111'],
                ['name' => 'Bogor Selatan', 'postal_code' => '16131'],
                ['name' => 'Bogor Utara', 'postal_code' => '16151'],
                ['name' => 'Tanah Sareal', 'postal_code' => '16161'],
            ],
            'Kota Depok' => [
                ['name' => 'Pancoran Mas', 'postal_code' => '16431'],
                ['name' => 'Sukmajaya', 'postal_code' => '16411'],
                ['name' => 'Beji', 'postal_code' => '16421'],
                ['name' => 'Cimanggis', 'postal_code' => '16451'],
                ['name' => 'Sawangan', 'postal_code' => '16511'],
                ['name' => 'Cinere', 'postal_code' => '16514'],
            ],
            'Kota Tangerang' => [
                ['name' => 'Tangerang', 'postal_code' => '15111'],
                ['name' => 'Cipondoh', 'postal_code' => '15148'],
                ['name' => 'Ciledug', 'postal_code' => '15151'],
                ['name' => 'Karawaci', 'postal_code' => '15115'],
            ],
            'Kota Tangerang Selatan' => [
                ['name' => 'Serpong', 'postal_code' => '15311'],
                ['name' => 'Serpong Utara', 'postal_code' => '15326'],
                ['name' => 'Pondok Aren', 'postal_code' => '15224'],
                ['name' => 'Ciputat', 'postal_code' => '15411'],
                ['name' => 'Pamulang', 'postal_code' => '15417'],
            ],
            'Kota Surabaya' => [
                ['name' => 'Genteng', 'postal_code' => '60275'],
                ['name' => 'Tegalsari', 'postal_code' => '60262'],
                ['name' => 'Gubeng', 'postal_code' => '60281'],
                ['name' => 'Rungkut', 'postal_code' => '60293'],
                ['name' => 'Wonokromo', 'postal_code' => '60241'],
            ],
            'Kota Semarang' => [
                ['name' => 'Semarang Tengah', 'postal_code' => '50132'],
                ['name' => 'Semarang Utara', 'postal_code' => '50172'],
                ['name' => 'Semarang Barat', 'postal_code' => '50141'],
                ['name' => 'Banyumanik', 'postal_code' => '50261'],
            ],
            'Kota Yogyakarta' => [
                ['name' => 'Danurejan', 'postal_code' => '55211'],
                ['name' => 'Gondokusuman', 'postal_code' => '55221'],
                ['name' => 'Kotagede', 'postal_code' => '55171'],
                ['name' => 'Umbulharjo', 'postal_code' => '55161'],
            ],
        ];

        foreach ($districtsByRegency as $regencyName => $districts) {
            $regency = Regency::query()->where('name', $regencyName)->first();

            if (! $regency) {
                continue;
            }

            foreach ($districts as $d) {
                District::query()->updateOrCreate(
                    [
                        'regency_id' => $regency->id,
                        'name' => $d['name'],
                    ],
                    [
                        'postal_code' => $d['postal_code'],
                    ],
                );
            }
        }
    }
}

