<?php

namespace Database\Seeders;

use App\Models\CustomField;
use Illuminate\Database\Seeder;

class CustomFieldSeeder extends Seeder
{
    public function run(): void
    {
        $fields = [
            [
                'module' => 'products',
                'label' => 'Catatan Khusus Finishing',
                'name' => 'finishing_note',
                'type' => 'textarea',
                'options' => null,
                'required' => false,
                'status' => 'Active',
            ],
            [
                'module' => 'orders',
                'label' => 'Link File Desain (Google Drive / WeTransfer)',
                'name' => 'design_file_link',
                'type' => 'text',
                'options' => null,
                'required' => false,
                'status' => 'Active',
            ],
            [
                'module' => 'customers',
                'label' => 'Nama Kontak Tambahan / PIC Keuangan',
                'name' => 'finance_pic_contact',
                'type' => 'text',
                'options' => null,
                'required' => false,
                'status' => 'Active',
            ],
            [
                'module' => 'purchases',
                'label' => 'Nomor Surat Jalan Asli Supplier',
                'name' => 'vendor_delivery_ref',
                'type' => 'text',
                'options' => null,
                'required' => false,
                'status' => 'Active',
            ],
        ];

        foreach ($fields as $field) {
            CustomField::query()->updateOrCreate(
                ['module' => $field['module'], 'name' => $field['name']],
                $field,
            );
        }
    }
}

