<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\BankAccountType;
use Illuminate\Database\Seeder;

class BankAccountSeeder extends Seeder
{
    public function run(): void
    {
        $typeBank = BankAccountType::query()->where('name', 'Rekening Bank')->first();
        $typeCash = BankAccountType::query()->where('name', 'Kas Tunai Toko')->first();
        $typePetty = BankAccountType::query()->where('name', 'Kas Kecil Kasir')->first();
        $typeQris = BankAccountType::query()->where('name', 'E-Wallet & QRIS')->first();

        $accounts = [
            [
                'account_name' => 'PT Dulank Digital Printing',
                'bank_name' => 'Bank Central Asia (BCA)',
                'account_no' => '5465019823',
                'bank_account_type_id' => $typeBank?->id,
                'opening_balance' => 50000000,
                'ifsc' => 'CENAIDJA',
                'description' => 'Rekening Utama Operasional & Transfer Penjualan',
                'status' => 'Active',
            ],
            [
                'account_name' => 'PT Dulank Digital Printing',
                'bank_name' => 'Bank Mandiri',
                'account_no' => '1320098457612',
                'bank_account_type_id' => $typeBank?->id,
                'opening_balance' => 35000000,
                'ifsc' => 'BMRIIDJA',
                'description' => 'Rekening Pembayaran Vendor & Supplier Bahan Baku',
                'status' => 'Active',
            ],
            [
                'account_name' => 'Kas Tunai Kasir Toko',
                'bank_name' => 'Cash Drawer Kasir',
                'account_no' => 'CASH-POS-01',
                'bank_account_type_id' => $typeCash?->id,
                'opening_balance' => 3000000,
                'ifsc' => null,
                'description' => 'Kas laci kasir untuk transaksi POS tunai',
                'status' => 'Active',
            ],
            [
                'account_name' => 'Kas Kecil Produksi (Petty Cash)',
                'bank_name' => 'Brankas Produksi',
                'account_no' => 'PETTY-01',
                'bank_account_type_id' => $typePetty?->id,
                'opening_balance' => 2000000,
                'ifsc' => null,
                'description' => 'Operasional darurat bengkel cetak & maintenance mesin',
                'status' => 'Active',
            ],
            [
                'account_name' => 'Merchant QRIS Dulank',
                'bank_name' => 'QRIS / Settlement Gateway',
                'account_no' => 'QRIS-DULANK-01',
                'bank_account_type_id' => $typeQris?->id,
                'opening_balance' => 15000000,
                'ifsc' => null,
                'description' => 'Penampung pembayaran QRIS & Virtual Account pelanggan',
                'status' => 'Active',
            ],
        ];

        foreach ($accounts as $account) {
            BankAccount::query()->updateOrCreate(
                ['account_no' => $account['account_no']],
                $account
            );
        }
    }
}

