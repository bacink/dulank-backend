<?php

namespace Database\Seeders;

use App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** Fictional, relational master data. Never creates sales, payments, stock or opening balances. */
class DemoMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo master data is only allowed in local/testing environments.');
        }

        DB::transaction(function (): void {
            $stores = [];
            foreach (['Karawang Pusat', 'Cikampek'] as $index => $name) {
                $stores[] = $this->record(Models\Store::class, 'store-'.$index, ['store_name' => 'Dulank '.$name.' (Contoh)'], [
                    'address' => $index === 0 ? 'Jl. Niaga Raya No. 18, Karawang' : 'Jl. Ahmad Yani No. 42, Cikampek',
                    'email' => 'cabang'.$index.'@dulank-demo.test', 'status' => 'Active',
                ]);
            }
            $unit = $this->record(Models\Unit::class, 'unit-pcs', ['name' => 'Pieces'], ['short_name' => 'pcs', 'status' => 'Active']);
            $sheet = $this->record(Models\Unit::class, 'unit-sheet', ['name' => 'Lembar'], ['short_name' => 'lbr', 'status' => 'Active']);
            $square = $this->record(Models\Unit::class, 'unit-m2', ['name' => 'Meter Persegi'], ['short_name' => 'm2', 'status' => 'Active']);

            $jobUnit = $this->record(Models\Unit::class, 'unit-job', ['name' => 'Pekerjaan'], ['short_name' => 'job', 'status' => 'Active']);
            $types = [];
            foreach (['Retail / Umum', 'Reseller', 'Korporat / Instansi'] as $name) {
                $types[] = $this->record(Models\CustomerType::class, 'customer-type-'.$name, ['name' => $name], ['status' => 'Active']);
            }
            $customers = [];
            foreach (['Rina Handayani', 'Bagus Pratama', 'Dewi Kartika', 'Fajar Nugraha', 'CV Kemasan Rasa Nusantara', 'PT Kreasi Pangan Karawang', 'Koperasi Sejahtera Cikampek', 'Studio Desain Langit Biru'] as $index => $name) {
                $user = $this->record(Models\User::class, 'customer-user-'.$index, ['email' => 'pelanggan'.($index + 1).'@dulank-demo.test'], [
                    'name' => $name, 'type' => 'store', 'password' => Str::random(64), 'status' => 'Inactive',
                    'company_name' => $index >= 4 ? $name : null,
                ]);
                $customer = $this->record(Models\Customer::class, 'customer-'.$index, ['code' => sprintf('DEMO-CUS-%03d', $index + 1)], [
                    'user_id' => $user->id, 'customer_type_id' => $types[$index >= 4 ? 2 : $index % 2]->id,
                    'channel' => $index % 2 === 0 ? 'Offline' : 'Online', 'date_join' => '2026-09-'.sprintf('%02d', $index + 1),
                ]);
                $this->address($customer, 'customer-address-'.$index, $name, $index);
                $customers[] = $customer;
            }
            foreach (['CV Sumber Kertas Mandiri', 'PT Bahan Grafika Sentosa', 'CV Tinta Warna Abadi', 'UD Perlengkapan Finishing Jaya'] as $index => $name) {
                $supplier = $this->record(Models\Supplier::class, 'supplier-'.$index, ['code' => sprintf('DEMO-SUP-%03d', $index + 1)], [
                    'name' => $name, 'email' => 'supplier'.($index + 1).'@dulank-demo.test',
                    'pic_name' => ['Hendra Saputra', 'Maya Anggraini', 'Rudi Hartono', 'Nina Permata'][$index],
                    'status' => 'Active', 'date' => '2026-09-01',
                ]);
                $this->address($supplier, 'supplier-address-'.$index, $name, $index + 8);
            }
            $employees = [];
            $staff = [
                ['Agus Setiawan', 'Produksi & Percetakan', 'Operator Mesin Offset', 5600000],
                ['Lina Marlina', 'Desain Grafis & CTP', 'Graphic Designer & Setting CTP', 5400000],
                ['Dimas Ramadhan', 'Produksi & Percetakan', 'Operator Digital Printing & Banner', 5300000],
                ['Sari Puspita', 'Finishing & Pasca Cetak', 'Operator Laminasi & Hot Print', 5200000],
                ['Yusuf Maulana', 'Gudang & Logistik', 'Admin Gudang & Stok', 5100000],
                ['Intan Wulandari', 'Kasir & Front Office', 'Kasir POS & Customer Service', 5200000],
            ];
            foreach ($staff as $index => [$name, $department, $designation, $salary]) {
                $dept = $this->record(Models\Department::class, 'department-'.$index, ['name' => $department], ['status' => 'Active']);
                $job = $this->record(Models\Designation::class, 'designation-'.$index, ['name' => $designation], ['status' => 'Active']);
                $employee = $this->record(Models\Employee::class, 'employee-'.$index, ['employee_code' => sprintf('DEMO-EMP-%03d', $index + 1)], [
                    'name' => $name, 'department_id' => $dept->id, 'designation_id' => $job->id,
                    'email' => 'pegawai'.($index + 1).'@dulank-demo.test', 'join_date' => '2026-08-03', 'status' => 'Active',
                    'address' => 'Karawang, Jawa Barat', 'detail_address' => '[DEMO] Profil karyawan fiktif untuk pengujian.',
                ]);
                $employees[] = $employee;
                $pay = $this->record(Models\EmployeeSalary::class, 'salary-'.$index, ['employee_id' => $employee->id], [
                    'name' => 'Gaji bulanan', 'salary' => $salary, 'system' => 'Monthly',
                    'allowance_total' => 600000, 'overtime_rate' => 35000, 'status' => 'Active',
                ]);
                foreach (['Transport' => 250000, 'Uang makan' => 350000] as $label => $amount) {
                    $this->record(Models\EmployeeSalaryAllowance::class, 'allowance-'.$index.'-'.$label, ['employee_salary_id' => $pay->id, 'name' => $label], ['amount' => $amount]);
                }
            }
            $cashType = $this->record(Models\BankAccountType::class, 'cash-type', ['name' => 'Kas Tunai Toko'], ['status' => 'Active']);
            foreach ($stores as $index => $store) {
                $this->record(Models\BankAccount::class, 'cash-'.$index, ['account_name' => 'Kas Contoh - '.$store->store_name], [
                    'branch_id' => $store->id, 'bank_account_type_id' => $cashType->id,
                    'opening_balance' => 0, 'description' => '[DEMO] Kas simulasi; saldo awal nol.', 'status' => 'Active',
                ]);
            }
            foreach ([Models\IncomeCategory::class => ['Jasa desain', 'Jasa pengiriman', 'Pendapatan lainnya'], Models\ExpenseCategory::class => ['Listrik produksi', 'Perawatan mesin', 'Transportasi operasional', 'Perlengkapan kantor'], Models\BlogCategory::class => ['Tips Percetakan', 'Inspirasi Kemasan', 'Panduan Desain']] as $class => $names) {
                foreach ($names as $name) {
                    $this->record($class, $name, ['name' => $name], ['status' => 'Active']);
                }
            }
            foreach (['Persiapan File', 'Pemesanan dan Pengiriman'] as $index => $name) {
                $this->record(Models\FaqCategory::class, 'faq-'.$index, ['name' => $name], []);
            }
            foreach (['cetak-offset', 'kemasan-umkm', 'desain-siap-cetak'] as $slug) {
                $this->record(Models\Tag::class, 'tag-'.$slug, ['slug' => $slug], ['name' => Str::headline($slug)]);
            }
            foreach (['paper_store' => 'Toko Kertas Sinar Karawang (Contoh)', 'printing_shop' => 'Percetakan Mitra Cikampek (Contoh)'] as $type => $name) {
                $this->record(Models\Partner::class, 'partner-'.$type, ['type' => $type, 'name' => $name], ['province' => 'Jawa Barat', 'city' => 'Karawang', 'join_date' => '2026-09-01', 'status' => 'Inactive']);
            }
            $plan = $this->record(Models\DiscountPlan::class, 'discount-plan', ['name' => 'Reseller Kemasan (Contoh)'], ['customers' => [$types[1]->name], 'status' => 'Inactive']);
            $this->record(Models\Discount::class, 'discount', ['name' => 'Diskon reseller 5 persen (Contoh)', 'discount_plan_id' => $plan->id], ['value' => 5, 'type' => 'Percentage', 'status' => 'Inactive']);
            $this->record(Models\Coupon::class, 'coupon', ['code' => 'DEMO-CETAK-HEMAT'], ['name' => 'Potongan cetak contoh', 'type' => 'Fixed', 'discount' => 10000, 'limit' => 50, 'status' => 'Inactive']);
            $this->record(Models\Voucher::class, 'voucher', ['code' => 'DEMO-PELANGGAN-BARU'], ['name' => 'Voucher pelanggan contoh', 'type' => 'Fixed', 'discount' => 15000, 'limit' => 25, 'all_products' => true, 'once_per_customer' => true, 'status' => 'Inactive']);
            $purchaseCategory = $this->record(Models\PurchaseCategory::class, 'purchase-paper', ['name' => 'Bahan Kertas Cetak'], ['status' => 'Active']);
            $paperGroups = [];
            foreach ([['Art Paper', 150, 1800], ['Art Carton', 260, 3400], ['HVS', 80, 900], ['Ivory', 300, 4200]] as $index => [$name, $gsm, $price]) {
                $group = $this->record(Models\PaperGroup::class, 'paper-group-'.$index, ['source' => 'demo:masters', 'name' => $name], ['owner_type' => 'self', 'price_type' => 'Yes', 'status' => 'Active']);
                $paperGroups[] = $group;
                $paper = $this->record(Models\PaperType::class, 'paper-type-'.$index, ['source' => 'demo:masters', 'name' => $name.' '.$gsm.' gsm'], [
                    'paper_group_id' => $group->id, 'gsm' => $gsm, 'dimension' => '65 x 100 cm', 'unit' => 'Lembar',
                    'stock' => 0, 'unit_stock' => 'Lembar', 'price' => $price, 'min_order' => 1, 'multiple' => 1,
                    'published' => false, 'active' => true, 'status' => 'Active',
                ]);
                $this->record(Models\PaperSize::class, 'paper-size-'.$index, ['source' => 'demo:masters', 'paper_group_id' => $group->id, 'name' => 'Plano 65 x 100 cm'], ['length' => 100, 'width' => 65, 'unit' => 'cm', 'dimension' => '65 x 100 cm', 'active' => true, 'published' => false, 'status' => 'Active']);
                $this->record(Models\PaperPrice::class, 'paper-price-'.$index, ['source' => 'demo:masters', 'paper_type_id' => $paper->id, 'name' => 'Harga per lembar'], ['paper_group_id' => $group->id, 'price' => $price, 'min_order' => 1, 'multiple' => 1, 'active' => true, 'published' => false, 'status' => 'Active']);
                $this->record(Models\PurchaseItem::class, 'purchase-item-'.$index, ['code' => sprintf('DEMO-MAT-%03d', $index + 1)], ['purchase_category_id' => $purchaseCategory->id, 'name' => $paper->name.' Plano 65 x 100', 'price' => $price, 'unit' => $sheet->name, 'status' => 'Active', 'description' => '[DEMO] Bahan contoh; belum ada stok masuk.']);
            }
            foreach ([['Offset 4 Warna 52', 'printing', 52, 36, 350000], ['Digital A3+', 'printing', 48, 33, 5000], ['Laminasi Roll 52', 'lamination', 52, 36, 1500], ['Pond Kemasan 52', 'die-cutting', 52, 36, 250000], ['Hot Foil 30', 'foil', 30, 20, 200000]] as $index => [$name, $kind, $width, $height, $price]) {
                $this->record(Models\Machine::class, 'machine-'.$index, ['source' => 'demo:masters', 'name' => $name], ['owner_type' => 'self', 'kind' => $kind, 'size' => $width.' x '.$height.' cm', 'max_area' => $width.' x '.$height, 'price' => $price, 'minim' => $price, 'active' => true, 'published' => false, 'status' => 'Active']);
            }
            foreach ([['printing', 'Cetak digital A3+', 5000], ['laminate', 'Laminasi doff A3', 1500], ['die_cutting', 'Jasa pond kemasan', 250000], ['hot_print', 'Hot foil kemasan', 200000]] as $index => [$category, $name, $price]) {
                $this->record(Models\WorkshopService::class, 'service-'.$index, ['name' => $name.' (Contoh)', 'store_id' => $stores[0]->id], ['category' => $category, 'price' => $price, 'price_unit' => 'Lembar', 'quantity_minimum' => 1, 'status' => 'Active']);
            }
            foreach ([['komponen-fiks', 'Persiapan file cetak', 25000], ['komponen-minimum', 'Minimum biaya produksi', 50000], ['jasa-lain', 'Potong dan sortir', 15000]] as [$kind, $name, $value]) {
                $this->record(Models\CalculatorComponent::class, $kind, ['kind' => $kind, 'name' => $name.' (Contoh)'], ['value' => $value, 'rate' => $value, 'minim' => $value, 'unit' => 'Job', 'status' => 'Active']);
            }
            // Existing margin rules must never be overlapped by demo tiers.
            if (! Models\ProfitTier::query()->exists()) {
                foreach ([[1, 500, 35, 30], [501, 2000, 30, 25], [2001, null, 25, 20]] as $index => [$min, $max, $pos, $web]) {
                    $this->record(Models\ProfitTier::class, 'profit-'.$index, ['min_qty' => $min], ['max_qty' => $max, 'profit_pos_percent' => $pos, 'profit_webstore_percent' => $web]);
                }
            }
            $products = [];
            foreach ([
                ['Brosur A5 Art Paper 150 gsm', 'Media Promosi', 'Brosur', 1200, 'Single Product', 'Fixed'],
                ['Kartu Nama Premium', 'Identitas Bisnis', 'Kartu Nama', 55000, 'Variable Product', 'Range'],
                ['Stiker Label Produk', 'Label dan Kemasan', 'Stiker', 18000, 'Size Calculation', 'Calculator'],
                ['Banner Flexi 280 gsm', 'Media Promosi', 'Banner', 25000, 'Large Format', 'Calculator'],
                ['Kemasan Ivory 300 gsm', 'Label dan Kemasan', 'Dus Lipat', 3500, 'Minimum Qty or Price', 'Calculator'],
                ['Jasa Cetak Offset 4 Warna', 'Jasa Produksi', 'Cetak Offset', 350000, 'Offset Service Price', 'Calculator'],
                ['Kop Surat HVS A4', 'Identitas Bisnis', 'Kop Surat', 500, 'Single Product', 'Fixed'],
                ['Kalender Meja Spiral', 'Media Promosi', 'Kalender', 22000, 'Single Product', 'Fixed'],
            ] as $index => [$name, $categoryName, $subName, $price, $productType, $priceType]) {
                $category = $this->record(Models\Category::class, 'category-'.$categoryName, ['name' => $categoryName], ['code' => 'DEMO-CAT-'.substr(sha1($categoryName), 0, 6), 'slug' => 'demo-'.Str::slug($categoryName), 'status' => 'Active']);
                $sub = $this->record(Models\SubCategory::class, 'sub-'.$index, ['category_id' => $category->id, 'name' => $subName], ['status' => 'Active']);
                $product = $this->record(Models\Product::class, 'product-'.$index, ['code' => sprintf('DEMO-PRD-%03d', $index + 1)], [
                    'slug' => 'demo-'.Str::slug($name), 'name' => $name, 'category_id' => $category->id,
                    'sub_category_id' => $sub->id, 'unit_id' => ($index === 3 ? $square : ($index === 5 ? $jobUnit : (in_array($index, [0, 6], true) ? $sheet : $unit)))->id, 'store_id' => $stores[$index % 2]->id,
                    'price' => $price, 'price_from' => $price, 'price_to' => $index === 1 ? 75000 : $price,
                    'price_type' => $priceType, 'product_type' => $productType, 'quantity' => 0,
                    'min_order_qty' => $index === 0 ? 100 : 1, 'description' => '[DEMO] Produk percetakan fiktif untuk pengujian master; harga ilustratif.',
                    'status' => 'Active',
                ]);
                $products[] = $product;
                $this->record(Models\ProductSpec::class, 'spec-'.$index, ['product_id' => $product->id, 'group_name' => 'Sisi cetak'], ['options' => ['1 sisi', '2 sisi'], 'sort_order' => 0]);
                $this->record(Models\ProductProcess::class, 'product-process-'.$index, ['product_id' => $product->id, 'code' => 'DEMO-PROC-'.($index + 1)], ['process_name' => 'Pemeriksaan file dan cetak', 'status' => 'Active']);
            }
            $this->record(Models\Variant::class, 'variant-finish', ['name' => 'Finishing Kartu Nama'], ['values' => ['Tanpa laminasi', 'Laminasi doff'], 'status' => 'Active']);
            foreach (['Tanpa laminasi' => 55000, 'Laminasi doff' => 75000] as $value => $price) {
                $this->record(Models\ProductVariant::class, 'product-variant-'.$value, ['product_id' => $products[1]->id, 'variation' => 'Finishing Kartu Nama', 'value' => $value], ['price' => $price, 'quantity' => 0]);
            }
            $flows = [];
            foreach ([['Pracetak', 'Pemeriksaan file', 0, 1], ['Cetak', 'Cetak digital', 150, 2], ['Finishing', 'Laminasi doff', 100, 3], ['Finishing', 'Potong dan kemas', 75, 4]] as $index => [$categoryName, $name, $incentive, $employeeIndex]) {
                $category = $this->record(Models\FlowCategory::class, 'flow-category-'.$categoryName, ['name' => $categoryName], ['code' => 'DEMO-FC-'.substr(sha1($categoryName), 0, 6)]);
                $flows[] = $this->record(Models\FlowName::class, 'flow-'.$index, ['code' => 'DEMO-FLOW-'.($index + 1)], ['name' => $name, 'flow_category_id' => $category->id, 'incentive' => $incentive, 'unit' => 'Lembar', 'flow_type' => 'Inhouse', 'assignees' => [['id' => (string) $employees[$employeeIndex]->id, 'name' => $employees[$employeeIndex]->name]]]);
            }
            $template = $this->record(Models\FlowTemplate::class, 'template', ['code' => 'DEMO-TPL-001'], ['name' => 'Cetak digital dan finishing', 'information' => '[DEMO] Cek file, cetak, laminasi, potong, dan kemas.']);
            $steps = array_map(fn (Model $flow): array => ['id' => (string) $flow->id, 'name' => $flow->name, 'category' => $flow->category->name, 'template' => $template->name, 'selected' => true], $flows);
            foreach ([$products[0], $products[1]] as $index => $product) {
                $workflow = $this->record(Models\WorkFlow::class, 'workflow-'.$index, ['no' => 'DEMO-WF-'.($index + 1)], ['date' => '2026-10-01', 'product_id' => $product->id, 'flow_category_id' => $flows[1]->flow_category_id, 'workflow_steps' => implode(', ', array_column($steps, 'name')), 'steps' => $steps]);
                foreach ($steps as $order => $step) {
                    $this->record(Models\WorkFlowStep::class, 'workflow-step-'.$index.'-'.$order, ['work_flow_id' => $workflow->id, 'sort_order' => $order], ['name' => $step['name'], 'category' => $step['category'], 'template' => $step['template']]);
                }
            }
        });
    }

    private function address(Model $owner, string $key, string $name, int $index): void
    {
        $this->record(Models\Address::class, $key, ['addressable_type' => $owner->getMorphClass(), 'addressable_id' => $owner->id, 'label' => 'Alamat utama (Contoh)'], [
            'recipient_name' => $name, 'name' => $name, 'type' => 'Office', 'street' => 'Jl. Contoh Niaga No. '.($index + 10),
            'district' => 'Karawang Barat', 'city' => 'Kab. Karawang', 'province' => 'Jawa Barat', 'postal_code' => '41312', 'country' => 'ID', 'is_default' => true,
        ]);
    }

    /**
     * @param  class-string<Model>  $class
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $attributes
     */
    private function record(string $class, string $key, array $identity, array $attributes): Model
    {
        $table = (new $class)->getTable();
        $map = Models\LegacyIdMap::query()->where(['source' => 'demo', 'entity' => $table, 'legacy_id' => $key])->first();
        if ($map !== null) {
            if ($map->model_type !== $class) {
                throw new RuntimeException('Demo mapping type mismatch: '.$table);
            }

            return $class::query()->findOrFail($map->model_id);
        }
        $existing = $class::query()->where($identity)->first();
        if ($existing !== null && isset($identity['code']) && str_starts_with($identity['code'], 'DEMO-')) {
            throw new RuntimeException('Demo code already belongs to an untracked record: '.$table);
        }
        $record = $existing ?? $class::query()->create($identity + $attributes);
        if ($record->wasRecentlyCreated) {
            Models\LegacyIdMap::query()->create(['source' => 'demo', 'entity' => $table, 'legacy_id' => $key, 'model_type' => $class, 'model_id' => $record->id]);
        }

        return $record;
    }
}
