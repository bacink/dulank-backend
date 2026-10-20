<?php

use App\Enums\ProductType;
use App\Models\BlogPost;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Incentive;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payslip;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\SalesReturn;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;

return [
    'version' => '2026-10-20',
    'masters' => [
        'organization' => ['stores'],
        'identity' => ['users', 'user_admins', 'roles', 'permissions'],
        'regions' => ['provinces', 'regencies', 'districts', 'postal_codes'],
        'people' => ['customer_types', 'customers', 'suppliers', 'addresses'],
        'catalog' => ['categories', 'sub_categories', 'units', 'variants', 'products', 'product_variants', 'product_specs', 'product_processes'],
        'purchasing' => ['purchase_categories', 'purchase_items'],
        'printing' => ['paper_groups', 'paper_types', 'paper_sizes', 'paper_prices', 'machines', 'die_cutting_blades', 'workshop_services', 'calculator_components', 'profit_tiers'],
        'production' => ['flow_categories', 'flow_names', 'flow_templates', 'work_flows', 'work_flow_steps'],
        'hr' => ['departments', 'designations', 'employees', 'employee_salaries', 'employee_salary_allowances'],
        'finance' => ['bank_account_types', 'bank_accounts', 'income_categories', 'expense_categories', 'tax_rates'],
        'partners' => ['partners'],
        'content' => ['blog_categories', 'tags', 'faq_categories', 'languages', 'custom_fields'],
        'promotion' => ['discount_plans', 'discounts', 'coupons', 'vouchers'],
    ],
    'duplicate_keys' => [
        'categories' => ['name'], 'sub_categories' => ['category_id', 'name'],
        'units' => ['name'], 'variants' => ['name'], 'suppliers' => ['name'],
        'stores' => ['store_name'], 'customer_types' => ['name'],
        'departments' => ['name'], 'designations' => ['name'],
        'flow_names' => ['flow_category_id', 'name'], 'blog_categories' => ['name'],
        'faq_categories' => ['name'], 'bank_account_types' => ['name'],
    ],
    'expected_links' => [
        'products' => ['category_id', 'sub_category_id', 'unit_id', 'store_id', 'product_type'],
        'customers' => ['user_id', 'customer_type_id'], 'orders' => ['customer_id', 'branch_id'],
        'invoices' => ['order_id', 'customer_id'], 'employees' => ['department_id', 'designation_id'],
        'bank_accounts' => ['branch_id', 'bank_account_type_id'], 'payments' => ['bank_account_id'],
    ],
    'schema_gaps' => [
        'purchase_lines' => ['purchase_item_id'], 'purchase_order_items' => ['purchase_item_id'],
        'purchase_return_items' => ['purchase_item_id'], 'product_variants' => ['variant_id'],
    ],
    'non_negative' => [
        'products' => ['price', 'quantity'], 'product_variants' => ['price', 'quantity'],
        'paper_types' => ['stock', 'price'], 'purchase_items' => ['price'],
        'orders' => ['total', 'paid', 'due'], 'invoices' => ['amount', 'paid', 'amount_due'],
        'payments' => ['amount'], 'purchases' => ['amount', 'paid', 'due'],
    ],
    'allowed_values' => [
        'products' => ['price_type' => ['Fixed', 'Range', 'Calculator'], 'product_type' => array_column(ProductType::cases(), 'value')],
        'customers' => ['channel' => ['Online', 'Offline']],
        'partners' => ['type' => ['printing_shop', 'paper_store']],
        'machines' => ['kind' => ['printing', 'lamination', 'die-cutting', 'foil'], 'owner_type' => ['self', 'vendor']],
    ],
    'polymorphic' => [
        'addresses' => ['addressable', [User::class, Customer::class, Supplier::class, Store::class]],
        'payments' => ['payable', [Order::class, Invoice::class, Purchase::class, PurchaseReturn::class, SalesReturn::class, Expense::class, Payslip::class, Incentive::class]],
        'taggables' => ['taggable', [Product::class, BlogPost::class]],
    ],
];
