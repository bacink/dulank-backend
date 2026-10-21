<?php

use App\Models;

return [
    'resources' => [
        'customer-types' => [Models\CustomerType::class, 'name', 'name'],
        'stores' => [Models\Store::class, 'storeName', 'store_name'],
        'categories' => [Models\Category::class, 'name', 'name'],
        'sub-categories' => [Models\SubCategory::class, 'name', 'name'],
        'units' => [Models\Unit::class, 'name', 'name'],
        'variants' => [Models\Variant::class, 'name', 'name'],
        'departments' => [Models\Department::class, 'name', 'name'],
        'designations' => [Models\Designation::class, 'name', 'name'],
        'income-categories' => [Models\IncomeCategory::class, 'name', 'name'],
        'expense-categories' => [Models\ExpenseCategory::class, 'name', 'name'],
        'bank-account-types' => [Models\BankAccountType::class, 'name', 'name'],
        'tax-rates' => [Models\TaxRate::class, 'name', 'name'],
    ],
];
