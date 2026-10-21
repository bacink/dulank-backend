<?php

return [
    'admin_url' => env('DULANK_ADMIN_URL', 'http://localhost:3000'),
    'store_url' => env('DULANK_STORE_URL', 'http://localhost:3001'),

    /*
    |--------------------------------------------------------------------------
    | Tarif pajak default (PPN)
    |--------------------------------------------------------------------------
    |
    | Dipakai bila tidak ada tax_rate aktif di database. Frontend tidak boleh
    | lagi hardcode 0.11; semua perhitungan pajak dilakukan server.
    |
    */
    'tax_rate_default' => (float) env('DULANK_TAX_RATE', 11),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'default' => 20,
        'max' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Prefix nomor dokumen
    |--------------------------------------------------------------------------
    |
    | Dipakai oleh App\Support\DocumentNumber::next($type). Nilai sebenarnya
    | dapat di-override dari grup setting `prefixes`.
    |
    */
    'documents' => [
        'order' => 'SO',
        'invoice' => 'INV',
        'quotation' => 'QT',
        'purchase' => 'PO',
        'goods_receipt' => 'PR',
        'purchase_item' => 'PIT',
        'supplier' => 'SUP',
        'employee_code' => 'EMP',
        'payslip' => 'SLP',
        'incentive' => 'INC',
        'product' => 'PRD',
        'user_admin' => 'ADM',
        'workflow' => 'WF',
        'job_branch' => 'JB',
        'delivery_note' => 'DN',
        'ticket' => 'TK',
        'job' => 'JO',
        'sales_return' => 'SR',
        'purchase_return' => 'PRT',
        'request_quotation' => 'RFQ',
        'payslip' => 'SLP',
        'payment' => 'PAY',
        'money_transfer' => 'MT',
        'income' => 'INC',
        'expense' => 'EXP',
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload
    |--------------------------------------------------------------------------
    */
    'uploads' => [
        'disk' => env('DULANK_UPLOAD_DISK', 'public'),
        'max_kb' => (int) env('DULANK_UPLOAD_MAX_KB', 4096),
    ],

    /*
    |--------------------------------------------------------------------------
    | Jalur sumber data mock frontend
    |--------------------------------------------------------------------------
    |
    | Dipakai oleh `php artisan dulank:import`.
    |
    */
    'import' => [
        'store_path' => env('DULANK_STORE_PATH'),
        'admin_path' => env('DULANK_ADMIN_PATH'),
    ],

];
