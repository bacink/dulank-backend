<?php

namespace App\Services\Import;

use App\Enums\AccountType;
use App\Enums\CustomerChannel;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\LegacyIdMap;
use App\Models\PaperGroup;
use App\Models\PaperPrice;
use App\Models\PaperSize;
use App\Models\PaperType;
use App\Models\PurchaseCategory;
use App\Models\PurchaseItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\RequestQuotation;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Support\DocumentNumber;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Importer data mock `dulank-admin` (folder `data/*.json` runtime).
 *
 * Semua operasi idempoten (updateOrCreate dengan natural key) dan
 * dijalankan dalam satu transaksi. Menang atas data store untuk master
 * sesuai PRD §5.1.
 */
final class AdminDataImporter
{
    private ?Closure $log = null;

    public function __construct(
        private readonly string $basePath,
        private readonly string $importPassword,
    ) {}

    /**
     * @param  Closure(string):void|null  $log
     * @return array<string, int>
     */
    public function import(bool $dryRun = false, ?Closure $log = null): array
    {
        $this->log = $log;
        $counts = [];

        $run = function () use (&$counts): void {
            $counts['customer_types'] = $this->importCustomerTypes();
            $counts['customers'] = $this->importCustomers();
            $counts['suppliers'] = $this->importSuppliers();
            $counts['stores'] = $this->importStores();
            $counts['purchase_items'] = $this->importPurchaseItems();
            $counts['purchase_orders'] = $this->importPurchaseOrders();
            $counts['purchase_returns'] = $this->importPurchaseReturns();
            $counts['request_quotations'] = $this->importRequestQuotations();
            $counts['paper_self'] = $this->importPaperSelf();
            $counts['prefixes'] = $this->importPrefixes();
        };

        if ($dryRun) {
            DB::beginTransaction();
            try {
                $run();
            } finally {
                DB::rollBack();
            }
        } else {
            DB::transaction($run);
        }

        return $counts;
    }

    private function importCustomerTypes(): int
    {
        $count = 0;

        foreach ($this->json('customer-types.json') as $row) {
            CustomerType::query()->updateOrCreate(
                ['name' => (string) $row['name']],
                ['status' => ($row['status'] ?? 'Active') === 'Active' ? 'Active' : 'Inactive'],
            );
            $count++;
        }

        $this->say("customer_types: {$count}");

        return $count;
    }

    private function importCustomers(): int
    {
        $count = 0;

        foreach ($this->json('customers.json') as $row) {
            $email = trim((string) ($row['email'] ?? ''));

            if ($email === '') {
                continue;
            }

            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $user = new User;
                $user->forceFill([
                    'name' => (string) ($row['name'] ?? 'Customer'),
                    'email' => $email,
                    'password' => $this->importPassword,
                    'type' => AccountType::Store,
                    'phone' => $row['phone'] ?? null,
                    'status' => ($row['status'] ?? 'Active') === 'Suspended' ? 'Inactive' : 'Active',
                    'join_date' => $this->parseDateTime($row['dateJoin'] ?? null),
                ])->save();
            }

            $channel = strtolower((string) ($row['channel'] ?? 'Online')) === 'offline'
                ? CustomerChannel::Offline
                : CustomerChannel::Online;

            $customerType = null;
            if (! empty($row['type'])) {
                $customerType = CustomerType::query()->firstOrCreate(['name' => (string) $row['type']]);
            }

            Customer::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'code' => (string) ($row['customerId'] ?? $row['id']),
                    'customer_type_id' => $customerType?->id,
                    'channel' => $channel,
                    'date_join' => $this->parseDateTime($row['dateJoin'] ?? null)?->toDateString(),
                ],
            );

            $this->map('customers', (string) ($row['id'] ?? $email), $user);
            $count++;
        }

        $this->say("customers: {$count}");

        return $count;
    }

    private function importSuppliers(): int
    {
        $count = 0;

        foreach ($this->json('suppliers.json') as $row) {
            Supplier::query()->updateOrCreate(
                ['code' => (string) ($row['supplierId'] ?? $row['id'])],
                [
                    'name' => (string) $row['name'],
                    'email' => $row['email'] ?? null,
                    'contact' => $row['contact'] ?? null,
                    'pic_name' => $row['picName'] ?? null,
                    'status' => $row['status'] ?? 'Active',
                    'date' => $this->parseDateTime($row['date'] ?? null)?->toDateString(),
                ],
            );
            $count++;
        }

        $this->say("suppliers: {$count}");

        return $count;
    }

    private function importStores(): int
    {
        $count = 0;

        foreach ($this->json('stores.json') as $row) {
            Store::query()->updateOrCreate(
                ['store_name' => (string) $row['storeName']],
                [
                    'address' => $row['address'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'email' => $row['email'] ?? null,
                    'status' => $row['status'] ?? 'Active',
                ],
            );
            $count++;
        }

        $this->say("stores: {$count}");

        return $count;
    }

    private function importPurchaseItems(): int
    {
        $count = 0;

        foreach ($this->json('purchase-items.json') as $row) {
            $category = null;
            if (! empty($row['category'])) {
                $category = PurchaseCategory::query()->firstOrCreate(['name' => (string) $row['category']]);
            }

            $code = (string) ($row['id'] ?? '');

            PurchaseItem::query()->updateOrCreate(
                ['code' => $code !== '' ? $code : 'PIT-'.substr(md5((string) $row['product']), 0, 8)],
                [
                    'purchase_category_id' => $category?->id,
                    'name' => (string) $row['product'],
                    'description' => $row['description'] ?? null,
                    'merk' => $row['merk'] ?? null,
                    'price' => (int) ($row['price'] ?? 0),
                    'unit' => $row['unit'] ?? null,
                    'status' => 'Active',
                ],
            );
            $count++;
        }

        $this->say("purchase_items: {$count}");

        return $count;
    }

    private function importPurchaseOrders(): int
    {
        $count = 0;

        foreach ($this->json('purchase-orders.json') as $row) {
            $supplier = $this->resolveSupplier((string) ($row['supplier'] ?? ''));

            $order = PurchaseOrder::query()->updateOrCreate(
                ['no_po' => (string) $row['noPO']],
                [
                    'date' => $this->parseDate($row['date'] ?? null, 'd/m/Y') ?? now()->toDateString(),
                    'created_by_name' => $row['created'] ?? null,
                    'no_purchase' => $row['noPurchase'] ?? null,
                    'supplier_id' => $supplier?->id,
                    'amount' => (int) ($row['amount'] ?? 0),
                    'po_status' => $row['poStatus'] ?? 'Draft',
                    'goods_status' => $row['goodsStatus'] ?? 'Pending',
                    'goods_date' => $this->parseDate($row['goodsDate'] ?? null, 'd/m/Y'),
                    'goods_by' => $row['goodsBy'] ?? null,
                    'term_of_payment' => $row['termOfPayment'] ?? null,
                    'delivery_date' => $this->parseDate($row['deliveryDate'] ?? null, 'd/m/Y'),
                    'delivery_address' => $row['deliveryAddress'] ?? null,
                    'vendor_reff' => $row['vendorReff'] ?? null,
                    'tax_rate' => (float) ($row['taxRate'] ?? 0),
                ],
            );

            $order->items()->delete();
            foreach ((array) ($row['items'] ?? []) as $item) {
                $qty = (float) ($item['qty'] ?? 0);
                $price = (int) ($item['price'] ?? 0);
                $order->items()->create([
                    'name' => (string) ($item['name'] ?? ''),
                    'qty' => $qty,
                    'unit' => $item['unit'] ?? null,
                    'price' => $price,
                    'total' => (int) round($price * $qty),
                ]);
            }

            $this->map('purchase_orders', (string) $row['id'], $order);
            $count++;
        }

        $this->say("purchase_orders: {$count}");

        return $count;
    }

    private function importPurchaseReturns(): int
    {
        $count = 0;

        foreach ($this->json('purchase-returns.json') as $row) {
            $supplier = $this->resolveSupplier((string) ($row['supplier'] ?? ''));

            $return = PurchaseReturn::query()->updateOrCreate(
                ['no_pr' => (string) $row['noPR']],
                [
                    'no_purchase' => $row['noPurchase'] ?? null,
                    'supplier_id' => $supplier?->id,
                    'date' => $this->parseDate($row['date'] ?? null, 'd/m/Y') ?? now()->toDateString(),
                    'amount' => (int) ($row['amount'] ?? 0),
                    'paid' => (int) ($row['paid'] ?? 0),
                    'due' => (int) ($row['due'] ?? 0),
                    'status' => $row['status'] ?? 'Pending',
                    'status_by' => $row['statusBy'] ?? null,
                    'notes' => $row['notes'] ?? null,
                ],
            );

            $return->items()->delete();
            foreach ((array) ($row['items'] ?? []) as $item) {
                $returnQty = (float) ($item['returnQty'] ?? 0);
                $price = (int) ($item['price'] ?? 0);
                $return->items()->create([
                    'name' => (string) ($item['name'] ?? ''),
                    'qty' => (float) ($item['qty'] ?? 0),
                    'return_qty' => $returnQty,
                    'unit' => $item['unit'] ?? null,
                    'price' => $price,
                    'amount' => (int) ($item['amount'] ?? round($price * $returnQty)),
                    'reason' => $item['reason'] ?? null,
                ]);
            }

            $this->map('purchase_returns', (string) $row['id'], $return);
            $count++;
        }

        $this->say("purchase_returns: {$count}");

        return $count;
    }

    private function importRequestQuotations(): int
    {
        $count = 0;

        foreach ($this->json('request-quotations.json') as $row) {
            RequestQuotation::query()->updateOrCreate(
                ['no_request' => (string) ($row['noRequest'] ?? $row['id'])],
                [
                    'customer' => (string) ($row['customer'] ?? ''),
                    'email' => $row['email'] ?? null,
                    'telp' => $row['telp'] ?? null,
                    'date' => $this->parseDateTime($row['date'] ?? null)?->toDateString() ?? now()->toDateString(),
                    'status' => $row['status'] ?? 'Pending',
                    'document' => $row['document'] ?? null,
                ],
            );
            $count++;
        }

        $this->say("request_quotations: {$count}");

        return $count;
    }

    private function importPaperSelf(): int
    {
        $count = 0;

        foreach ($this->json('paper-groups-self.json') as $row) {
            PaperGroup::query()->updateOrCreate(
                ['name' => (string) $row['name'], 'owner_type' => 'self'],
                ['merk' => $row['merk'] ?? null, 'price_type' => $row['priceType'] ?? null, 'status' => 'Active'],
            );
            $count++;
        }

        foreach ($this->json('paper-items-self.json') as $row) {
            $group = null;
            if (! empty($row['groupId'])) {
                $legacy = $this->json('paper-groups-self.json');
                $groupName = collect($legacy)->firstWhere('id', $row['groupId'])['name'] ?? null;
                if ($groupName !== null) {
                    $group = PaperGroup::query()->where('name', $groupName)->where('owner_type', 'self')->first();
                }
            }

            PaperType::query()->updateOrCreate(
                ['name' => (string) $row['name'], 'source' => 'self'],
                [
                    'paper_group_id' => $group?->id,
                    'merk' => $row['merk'] ?? null,
                    'gsm' => isset($row['gsm']) ? (float) $row['gsm'] : null,
                    'unit' => $row['unitPrice'] ?? null,
                    'stock' => (float) ($row['stock'] ?? 0),
                    'unit_stock' => $row['unitStock'] ?? null,
                    'price' => (int) ($row['price'] ?? 0),
                    'published' => true,
                    'active' => true,
                    'status' => 'Active',
                ],
            );
            $count++;
        }

        foreach ($this->json('paper-prices.json') as $row) {
            $group = PaperGroup::query()->where('name', (string) ($row['group'] ?? ''))->first();

            PaperPrice::query()->updateOrCreate(
                ['name' => (string) $row['nama'], 'paper_group_id' => $group?->id],
                [
                    'source' => $group?->source,
                    'price' => (int) ($row['harga'] ?? 0),
                    'published' => true,
                    'active' => true,
                    'status' => 'Active',
                ],
            );
            $count++;
        }

        foreach ($this->json('paper-sizes.json') as $row) {
            PaperSize::query()->updateOrCreate(
                ['name' => (string) $row['name']],
                [
                    'dimension' => $row['dimension'] ?? null,
                    'length' => isset($row['length']) ? (float) $row['length'] : null,
                    'width' => isset($row['width']) ? (float) $row['width'] : null,
                    'unit' => $row['unit'] ?? null,
                    'published' => true,
                    'active' => true,
                    'status' => 'Active',
                ],
            );
            $count++;
        }

        $this->say("paper_self: {$count}");

        return $count;
    }

    private function importPrefixes(): int
    {
        $count = 0;
        $documentTypes = array_keys((array) config('dulank.documents'));

        foreach ($this->json('prefixes.json') as $row) {
            $key = (string) ($row['key'] ?? '');

            if (! in_array($key, $documentTypes, true)) {
                continue;
            }

            $prefix = trim((string) ($row['prefix'] ?? ''));

            if ($prefix === '') {
                continue;
            }

            Setting::query()->updateOrCreate(
                ['group' => 'prefixes', 'key' => $key],
                ['value' => ['prefix' => rtrim($prefix, ' -')]],
            );
            $count++;
        }

        $this->say("prefixes: {$count}");

        return $count;
    }

    private function resolveSupplier(string $name): ?Supplier
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        return Supplier::query()->firstOrCreate(
            ['name' => $name],
            [
                'code' => DocumentNumber::next('supplier', Supplier::query(), 'code', 3),
                'status' => 'Active',
                'date' => now()->toDateString(),
            ],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function json(string $file): array
    {
        $path = $this->basePath.'/data/'.$file;

        if (! File::exists($path)) {
            return [];
        }

        return json_decode((string) File::get($path), true) ?? [];
    }

    private function map(string $entity, int|string $legacyId, Model $model): void
    {
        LegacyIdMap::query()->updateOrCreate(
            ['source' => 'admin', 'entity' => $entity, 'legacy_id' => (string) $legacyId],
            ['model_type' => $model::class, 'model_id' => $model->getKey()],
        );
    }

    private function parseDate(?string $value, ?string $format = null): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return $format !== null
                ? Carbon::createFromFormat('!'.$format, $value)
                : Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function parseDateTime(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        foreach (['d/m/Y, H:i', 'd/m/Y H:i', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                // coba format berikutnya
            }
        }

        return $this->parseDate($value);
    }

    private function say(string $message): void
    {
        if ($this->log !== null) {
            ($this->log)($message);
        }
    }
}
