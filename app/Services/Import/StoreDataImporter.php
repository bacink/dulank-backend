<?php

namespace App\Services\Import;

use App\Enums\AccountType;
use App\Enums\BannerType;
use App\Enums\CartItemStatus;
use App\Enums\CustomerChannel;
use App\Enums\InvoiceStatus;
use App\Enums\MachineKind;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaperOwnerType;
use App\Enums\PaymentStatus;
use App\Enums\PriceType;
use App\Enums\ProductStatus;
use App\Enums\TicketAuthorType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\WishlistStatus;
use App\Models\Address;
use App\Models\Banner;
use App\Models\BlogPost;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Client;
use App\Models\Customer;
use App\Models\DieCuttingBlade;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Invoice;
use App\Models\LegacyIdMap;
use App\Models\Machine;
use App\Models\Order;
use App\Models\PaperGroup;
use App\Models\PaperPrice;
use App\Models\PaperSize;
use App\Models\PaperType;
use App\Models\Partner;
use App\Models\PartnerMetric;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SupportTicket;
use App\Models\Tag;
use App\Models\User;
use App\Models\Wishlist;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Importer data mock `dulank-store` (server/data/*.json) ke skema kanonik.
 *
 * - Idempoten: dapat dijalankan berulang tanpa menggandakan data.
 * - Menjaga ID numerik lama agar referensi frontend tetap valid.
 * - `--dry-run` dijalankan dalam transaksi lalu di-rollback.
 */
final class StoreDataImporter
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
            $counts['users'] = $this->importUsers();
            $counts['addresses'] = $this->importAddresses();
            $counts['categories'] = $this->importCategories();
            $counts['products'] = $this->importProducts();
            $counts['blog_posts'] = $this->importBlogPosts();
            $counts['faqs'] = $this->importFaqs();
            $counts['sale_banners'] = $this->importSaleBanners();
            $counts['clients'] = $this->importClients();
            $counts['partners'] = $this->importPartners();
            $counts['machines'] = $this->importMachines();
            $counts['die_cutting_blades'] = $this->importDieCuttingBlades();
            $counts['paper'] = $this->importPapers();
            $counts['cart_items'] = $this->importCart();
            $counts['wishlists'] = $this->importWishlists();
            $counts['orders'] = $this->importOrders();
            $counts['quotations'] = $this->importQuotations();
            $counts['tickets'] = $this->importTickets();
            $counts['invoices'] = $this->importInvoices();
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

    private function importUsers(): int
    {
        $rows = $this->json('users.json');
        $count = 0;

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $occupant = User::query()->find($id);

            /** @var User|null $user */
            $user = ($occupant !== null && $occupant->isStore()) ? $occupant : null;

            if ($user === null) {
                $mappedUserId = LegacyIdMap::query()
                    ->where('source', 'store')
                    ->where('entity', 'users')
                    ->where('legacy_id', (string) $id)
                    ->value('model_id');

                if ($mappedUserId !== null) {
                    $user = User::query()->find((int) $mappedUserId);
                }
            }

            if ($user === null) {
                $user = new User;
                $user->id = $occupant === null ? $id : (int) User::query()->max('id') + 1;
                $user->forceFill(['password' => $this->importPassword]);
            }

            $user->forceFill([
                'name' => (string) ($row['name'] ?? 'User '.$id),
                'email' => (string) ($row['email'] ?? "user{$id}@dulank.test"),
                'phone' => $row['phone'] ?? null,
                'avatar_path' => $row['avatar'] ?? null,
                'join_date' => $this->parseDateTime($row['joinDate'] ?? null),
                'last_login_at' => $this->parseDateTime($row['lastLogin'] ?? null),
                'company_type' => $row['companyType'] ?? null,
                'company_name' => $row['companyName'] ?? null,
                'department_name' => $row['departmentName'] ?? null,
                'npwp' => $row['npwp'] ?? null,
                'transaction_code' => $row['transactionCode'] ?? null,
                'tax_address' => $row['taxAddress'] ?? null,
                'attach_tax_invoice' => (bool) ($row['attachTaxInvoice'] ?? false),
                'type' => AccountType::Store,
                'status' => 'Active',
            ])->save();

            Customer::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'code' => 'CUS-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
                    'channel' => CustomerChannel::Online,
                    'date_join' => $user->join_date?->toDateString() ?? now()->toDateString(),
                ],
            );

            $this->map('users', $id, $user);
            $count++;
        }

        $this->say("users: {$count}");

        return $count;
    }

    private function importAddresses(): int
    {
        $rows = $this->json('addresses.json');
        $count = 0;

        foreach ($rows as $row) {
            $address = $this->upsertWithId(Address::class, (int) $row['id'], [
                'addressable_type' => User::class,
                'addressable_id' => $this->resolveUserId((int) ($row['userId'] ?? 1)),
                'recipient_name' => $row['recipientName'] ?? null,
                'phone' => $row['phone'] ?? null,
                'type' => $row['type'] ?? 'Home',
                'name' => $row['name'] ?? null,
                'street' => $row['street'] ?? null,
                'district' => $row['district'] ?? null,
                'city' => $row['city'] ?? null,
                'province' => $row['province'] ?? null,
                'postal_code' => isset($row['postalCode']) ? (string) $row['postalCode'] : null,
                'country' => $row['country'] ?? 'ID',
            ], 'addresses');

            $this->map('addresses', $row['id'], $address);
            $count++;
        }

        $this->say("addresses: {$count}");

        return $count;
    }

    private function importCategories(): int
    {
        $rows = $this->json('categories.json');
        $count = 0;

        foreach ($rows as $row) {
            $category = $this->upsertWithId(Category::class, (int) $row['id'], [
                'name' => (string) $row['name'],
                'code' => 'CAT-'.(string) $row['id'],
                'slug' => (string) $row['slug'],
                'description' => $row['description'] ?? null,
                'image_path' => $row['image'] ?? null,
                'status' => 'Active',
            ], 'categories');

            $this->map('categories', $row['id'], $category);
            $count++;
        }

        $this->say("categories: {$count}");

        return $count;
    }

    private function importProducts(): int
    {
        $rows = $this->json('products.json');
        $count = 0;

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $priceFrom = (int) ($row['priceFrom'] ?? 0);
            $priceTo = (int) ($row['priceTo'] ?? $priceFrom);

            $product = $this->upsertWithId(Product::class, $id, [
                'code' => 'PRD-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT),
                'slug' => (string) $row['slug'],
                'name' => (string) $row['name'],
                'category_id' => isset($row['categoryId']) ? (int) $row['categoryId'] : null,
                'description' => $row['description'] ?? null,
                'image_path' => $row['image'] ?? null,
                'price' => $priceFrom,
                'price_from' => $priceFrom,
                'price_to' => $priceTo,
                'price_type' => $priceFrom === $priceTo ? PriceType::Fixed : PriceType::Range,
                'quantity' => 0,
                'status' => ProductStatus::Active,
            ], 'products');

            $product->specs()->delete();
            foreach ((array) ($row['specs'] ?? []) as $index => $spec) {
                $product->specs()->create([
                    'group_name' => (string) ($spec['group'] ?? ''),
                    'options' => $spec['options'] ?? [],
                    'sort_order' => $index,
                ]);
            }

            $product->images()->delete();
            if (! empty($row['image'])) {
                $product->images()->create(['path' => (string) $row['image'], 'sort_order' => 0]);
            }

            $tagIds = [];
            foreach ((array) ($row['tags'] ?? []) as $name) {
                $tag = Tag::query()->firstOrCreate(['slug' => Str::slug((string) $name)], ['name' => (string) $name]);
                $tagIds[] = $tag->id;
            }
            $product->tags()->sync($tagIds);

            $this->map('products', $id, $product);
            $count++;
        }

        $this->say("products: {$count}");

        return $count;
    }

    private function importBlogPosts(): int
    {
        $rows = $this->json('blog.json');
        $count = 0;

        foreach ($rows as $row) {
            $post = $this->upsertWithId(BlogPost::class, (int) $row['id'], [
                'slug' => (string) $row['slug'],
                'title' => (string) $row['title'],
                'excerpt' => $row['excerpt'] ?? null,
                'content' => $row['content'] ?? null,
                'image_path' => $row['image'] ?? null,
                'author_name' => $row['author'] ?? null,
                'published_at' => $this->parseDate($row['date'] ?? null),
                'status' => 'Publish',
            ], 'blog_posts');

            $tagIds = [];
            foreach ((array) ($row['tags'] ?? []) as $name) {
                $tag = Tag::query()->firstOrCreate(['slug' => Str::slug((string) $name)], ['name' => (string) $name]);
                $tagIds[] = $tag->id;
            }
            $post->tags()->sync($tagIds);

            $this->map('blog_posts', $row['id'], $post);
            $count++;
        }

        $this->say("blog_posts: {$count}");

        return $count;
    }

    private function importFaqs(): int
    {
        $rows = $this->json('faqs.json');
        $count = 0;

        foreach ($rows as $row) {
            $category = FaqCategory::query()->firstOrCreate(['name' => (string) ($row['category'] ?? 'Umum')]);

            $faq = $this->upsertWithId(Faq::class, (int) $row['id'], [
                'faq_category_id' => $category->id,
                'question' => (string) $row['question'],
                'answer' => (string) $row['answer'],
                'sort_order' => (int) $row['id'],
            ], 'faqs');

            $this->map('faqs', $row['id'], $faq);
            $count++;
        }

        $this->say("faqs: {$count}");

        return $count;
    }

    private function importSaleBanners(): int
    {
        $rows = $this->json('sale-banners.json');
        $count = 0;

        foreach ($rows as $row) {
            $banner = $this->upsertWithId(Banner::class, (int) $row['id'], [
                'title' => (string) $row['title'],
                'discount' => (string) ($row['discount'] ?? ''),
                'image_url' => $row['image'] ?? null,
                'type' => BannerType::Product,
                'status' => 'Active',
                'sort_order' => (int) $row['id'],
            ], 'sale_banners');

            $this->map('sale_banners', $row['id'], $banner);
            $count++;
        }

        $this->say("sale_banners: {$count}");

        return $count;
    }

    private function importClients(): int
    {
        $rows = $this->json('clients.json');
        $count = 0;

        foreach ($rows as $row) {
            $client = $this->upsertWithId(Client::class, (int) $row['id'], [
                'name' => (string) $row['name'],
                'logo_url' => $row['logo'] ?? null,
                'status' => 'Active',
                'sort_order' => (int) $row['id'],
            ], 'clients');

            $this->map('clients', $row['id'], $client);
            $count++;
        }

        $this->say("clients: {$count}");

        return $count;
    }

    private function importPartners(): int
    {
        $count = 0;

        foreach ($this->json('printing-shops.json') as $row) {
            $partner = $this->upsertWithId(Partner::class, (int) $row['id'], [
                'type' => 'printing_shop',
                'name' => (string) $row['name'],
                'province' => $row['province'] ?? null,
                'city' => $row['city'] ?? null,
                'district' => $row['district'] ?? null,
                'followers_label' => isset($row['followers']) ? (string) $row['followers'] : null,
                'following_count' => (int) ($row['following'] ?? 0),
                'image' => $row['image'] ?? null,
                'status' => 'Active',
            ], 'partners');

            PartnerMetric::query()->updateOrCreate(['partner_id' => $partner->id], [
                'paper_count' => (int) ($row['paperCount'] ?? 0),
                'printing_count' => (int) ($row['printingCount'] ?? 0),
                'lamination_count' => (int) ($row['laminationCount'] ?? 0),
                'die_cutting_count' => (int) ($row['dieCuttingCount'] ?? 0),
                'foil_count' => (int) ($row['foilCount'] ?? 0),
            ]);

            $this->map('partners', 'printing-shop-'.$row['id'], $partner);
            $count++;
        }

        foreach ($this->json('paper-stores.json') as $row) {
            $partner = Partner::query()->updateOrCreate(
                ['type' => 'paper_store', 'name' => (string) $row['name']],
                [
                    'province' => $row['province'] ?? null,
                    'city' => $row['city'] ?? null,
                    'district' => $row['district'] ?? null,
                    'followers_label' => isset($row['followers']) ? (string) $row['followers'] : null,
                    'following_count' => (int) ($row['following'] ?? 0),
                    'image' => $row['image'] ?? null,
                    'status' => 'Active',
                ],
            );

            PartnerMetric::query()->updateOrCreate(['partner_id' => $partner->id], [
                'group_count' => (int) ($row['groupCount'] ?? 0),
                'type_count' => (int) ($row['typeCount'] ?? 0),
            ]);

            $this->map('partners', 'paper-store-'.$row['id'], $partner);
            $count++;
        }

        $this->say("partners: {$count}");

        return $count;
    }

    private function importMachines(): int
    {
        $rows = $this->json('printing-machines.json');
        $partnerIds = Partner::query()->pluck('id', 'name');
        $count = 0;

        foreach ($rows as $row) {
            $ownerType = $this->ownerType((string) ($row['source'] ?? ''));
            $kind = MachineKind::tryFrom((string) ($row['kind'] ?? 'printing')) ?? MachineKind::Printing;

            $machine = $this->upsertWithId(Machine::class, (int) $row['id'], [
                'owner_type' => $ownerType,
                'partner_id' => $ownerType === PaperOwnerType::Vendor ? ($partnerIds[$row['source'] ?? ''] ?? null) : null,
                'kind' => $kind,
                'source' => $row['source'] ?? null,
                'name' => (string) $row['name'],
                'size' => $row['size'] ?? null,
                'price' => isset($row['price']) ? (int) $row['price'] : null,
                'unit_price' => isset($row['unitPrice']) ? (int) $row['unitPrice'] : null,
                'published' => (bool) ($row['published'] ?? false),
                'active' => (bool) ($row['active'] ?? false),
                'status' => 'Active',
                'updated_at' => $this->parseDateTime($row['updatedAt'] ?? null) ?? now(),
            ], 'machines');

            $this->map('machines', $row['id'], $machine);
            $count++;
        }

        $this->say("machines: {$count}");

        return $count;
    }

    private function importDieCuttingBlades(): int
    {
        $rows = $this->json('die-cutting-blades.json');
        $count = 0;

        foreach ($rows as $row) {
            $blade = $this->upsertWithId(DieCuttingBlade::class, (int) $row['id'], [
                'name' => (string) $row['name'],
                'price_per_cm' => (int) ($row['pricePerCm'] ?? 0),
                'published' => (bool) ($row['published'] ?? false),
                'active' => (bool) ($row['active'] ?? false),
                'updated_at' => $this->parseDateTime($row['updatedAt'] ?? null) ?? now(),
            ], 'die_cutting_blades');

            $this->map('die_cutting_blades', $row['id'], $blade);
            $count++;
        }

        $this->say("die_cutting_blades: {$count}");

        return $count;
    }

    private function importPapers(): int
    {
        $partnerIds = Partner::query()->pluck('id', 'name');
        $count = 0;

        foreach ($this->json('paper-groups.json') as $row) {
            $ownerType = $this->ownerType((string) ($row['source'] ?? ''));

            $group = $this->upsertWithId(PaperGroup::class, (int) $row['id'], [
                'owner_type' => $ownerType,
                'partner_id' => $ownerType === PaperOwnerType::Vendor ? ($partnerIds[$row['source'] ?? ''] ?? null) : null,
                'source' => $row['source'] ?? null,
                'name' => (string) $row['name'],
                'merk' => $row['brand'] ?? null,
                'status' => 'Active',
                'updated_at' => $this->parseDateTime($row['updatedAt'] ?? null) ?? now(),
            ], 'paper_groups');

            $this->map('paper_groups', $row['id'], $group);
            $count++;
        }

        foreach ($this->json('paper-types.json') as $row) {
            $group = PaperGroup::query()
                ->where('name', (string) ($row['group'] ?? ''))
                ->orderBy('id')
                ->first();

            $ownerType = $this->ownerType((string) ($row['source'] ?? ''));

            $type = $this->upsertWithId(PaperType::class, (int) $row['id'], [
                'paper_group_id' => $group?->id,
                'partner_id' => $ownerType === PaperOwnerType::Vendor ? ($partnerIds[$row['source'] ?? ''] ?? null) : null,
                'source' => $row['source'] ?? null,
                'name' => (string) $row['name'],
                'gsm' => isset($row['gsm']) ? (float) $row['gsm'] : null,
                'published' => true,
                'active' => true,
                'status' => 'Active',
                'updated_at' => $this->parseDateTime($row['updatedAt'] ?? null) ?? now(),
            ], 'paper_types');

            $this->map('paper_types', $row['id'], $type);
            $count++;
        }

        foreach ($this->json('paper-prices.json') as $row) {
            $group = PaperGroup::query()
                ->where('name', (string) ($row['group'] ?? ''))
                ->orderBy('id')
                ->first();

            $price = $this->upsertWithId(PaperPrice::class, (int) $row['id'], [
                'paper_group_id' => $group?->id,
                'source' => $row['source'] ?? null,
                'name' => (string) $row['name'],
                'price' => (int) ($row['price'] ?? 0),
                'published' => (bool) ($row['published'] ?? false),
                'active' => (bool) ($row['active'] ?? false),
                'status' => 'Active',
                'updated_at' => $this->parseDateTime($row['updatedAt'] ?? null) ?? now(),
            ], 'paper_prices');

            $this->map('paper_prices', $row['id'], $price);
            $count++;
        }

        foreach ($this->json('paper-sizes.json') as $row) {
            $size = $this->upsertWithId(PaperSize::class, (int) $row['id'], [
                'source' => $row['source'] ?? null,
                'name' => (string) $row['name'],
                'width' => isset($row['width']) ? (float) $row['width'] : null,
                'length' => isset($row['height']) ? (float) $row['height'] : null,
                'unit' => $row['unit'] ?? null,
                'published' => true,
                'active' => true,
                'status' => 'Active',
                'updated_at' => $this->parseDateTime($row['updatedAt'] ?? null) ?? now(),
            ], 'paper_sizes');

            $this->map('paper_sizes', $row['id'], $size);
            $count++;
        }

        $this->say("paper (groups/types/prices/sizes): {$count}");

        return $count;
    }

    private function importCart(): int
    {
        $userId = $this->importUserId();

        CartItem::query()
            ->where('user_id', $userId)
            ->where('status', CartItemStatus::Active)
            ->delete();

        $count = 0;
        foreach ($this->json('cart.json') as $row) {
            $productId = $this->existingProductId($row['productId'] ?? null);

            if ($productId === null) {
                continue;
            }

            CartItem::query()->create([
                'user_id' => $userId,
                'product_id' => $productId,
                'name' => (string) $row['name'],
                'image' => $row['image'] ?? null,
                'quantity' => (float) ($row['quantity'] ?? 1),
                'price' => (int) ($row['price'] ?? 0),
                'spec' => (string) ($row['spec'] ?? ''),
                'status' => CartItemStatus::Active,
            ]);
            $count++;
        }

        $this->say("cart_items: {$count}");

        return $count;
    }

    private function importWishlists(): int
    {
        $rows = $this->json('wishlists.json');
        $count = 0;

        foreach ($rows as $row) {
            $productId = $this->existingProductId($row['productId'] ?? null);

            if ($productId === null) {
                continue;
            }

            $wishlist = $this->upsertWithId(Wishlist::class, (int) $row['id'], [
                'user_id' => $this->resolveUserId((int) ($row['userId'] ?? 1)) ?? $this->importUserId(),
                'product_id' => $productId,
                'name' => (string) $row['name'],
                'image' => $row['image'] ?? null,
                'price' => (int) ($row['price'] ?? 0),
                'spec' => (string) ($row['spec'] ?? ''),
                'status' => WishlistStatus::Active,
                'added_at' => $this->parseDate($row['addedAt'] ?? null),
                'expire_date' => $this->parseDate($row['expireDate'] ?? null, 'j/n/Y'),
            ], 'wishlists');

            $this->map('wishlists', $row['id'], $wishlist);
            $count++;
        }

        $this->say("wishlists: {$count}");

        return $count;
    }

    private function importOrders(): int
    {
        $rows = $this->json('orders.json');
        $count = 0;

        foreach ($rows as $row) {
            $number = (string) $row['id'];
            $timeline = (array) ($row['timeline'] ?? []);
            $total = (int) ($row['total'] ?? 0);

            /** @var Order $order */
            $order = Order::query()->where('number', $number)->first() ?? new Order(['number' => $number]);

            $order->forceFill([
                'user_id' => isset($row['userId']) ? $this->resolveUserId((int) $row['userId']) : null,
                'channel' => OrderChannel::Website,
                'status' => $this->orderStatus((string) ($row['status'] ?? 'pending')),
                'shipping_method' => null,
                'shipping_address' => $row['shippingAddress'] ?? null,
                'sub_total' => $total,
                'total' => $total,
                'paid' => 0,
                'due' => $total,
                'payment_status' => PaymentStatus::Unpaid,
                'ordered_at' => $this->parseWib($timeline[0]['date'] ?? null)
                    ?? $this->parseDate($row['date'] ?? null),
            ])->save();

            $order->items()->delete();
            foreach ((array) ($row['items'] ?? []) as $item) {
                $order->items()->create([
                    'product_id' => $this->existingProductId($item['productId'] ?? null),
                    'name' => (string) ($item['name'] ?? ''),
                    'category' => $item['category'] ?? null,
                    'quantity' => (float) ($item['quantity'] ?? 1),
                    'price' => (int) ($item['price'] ?? 0),
                    'total' => (int) ($item['total'] ?? 0),
                    'spec' => (string) ($item['spec'] ?? ''),
                    'artwork' => $item['artwork'] ?? null,
                    'print_result' => $item['printResult'] ?? null,
                    'note' => $item['note'] ?? null,
                ]);
            }

            $order->statusHistories()->delete();
            foreach ($timeline as $history) {
                $order->statusHistories()->create([
                    'status' => (string) ($history['status'] ?? ''),
                    'occurred_at' => $this->parseWib($history['date'] ?? null),
                ]);
            }

            $this->map('orders', $number, $order);
            $count++;
        }

        $this->say("orders: {$count}");

        return $count;
    }

    private function importQuotations(): int
    {
        $rows = $this->json('quotations.json');
        $count = 0;

        foreach ($rows as $row) {
            $noQuotation = (string) $row['id'];
            $customer = (array) ($row['customerInfo'] ?? []);

            /** @var Quotation $quotation */
            $quotation = Quotation::query()->where('no_quotation', $noQuotation)->first()
                ?? new Quotation(['no_quotation' => $noQuotation]);

            $quotation->forceFill([
                'user_id' => isset($row['userId']) ? $this->resolveUserId((int) $row['userId']) : null,
                'date' => $this->parseDate($row['date'] ?? null) ?? now()->toDateString(),
                'valid_until' => $this->parseDate($row['validUntil'] ?? null),
                'status' => (string) ($row['status'] ?? 'Draft'),
                'channel' => 'online',
                'customer_name' => $customer['name'] ?? null,
                'customer_email' => $customer['email'] ?? null,
                'customer_phone' => $customer['phone'] ?? null,
                'sub_total' => (int) ($row['subtotal'] ?? 0),
                'discount' => 0,
                'tax' => (int) ($row['tax'] ?? 0),
                'shipping_cost' => (int) ($row['shippingCost'] ?? 0),
                'total' => (int) ($row['total'] ?? 0),
            ])->save();

            $quotation->items()->delete();
            foreach ((array) ($row['items'] ?? []) as $item) {
                $quotation->items()->create([
                    'product_id' => $this->existingProductId($item['productId'] ?? null),
                    'product_name' => (string) ($item['name'] ?? ''),
                    'order_qty' => (float) ($item['quantity'] ?? 1),
                    'unit_price' => (int) ($item['unitPrice'] ?? 0),
                    'amount' => (int) ($item['total'] ?? 0),
                    'spec' => (string) ($item['spec'] ?? ''),
                    'category' => $item['category'] ?? null,
                    'artwork' => $item['artwork'] ?? null,
                    'print_result' => $item['printResult'] ?? null,
                    'note' => $item['note'] ?? null,
                ]);
            }

            $this->map('quotations', $noQuotation, $quotation);
            $count++;
        }

        $this->say("quotations: {$count}");

        return $count;
    }

    private function importTickets(): int
    {
        $rows = $this->json('tickets.json');
        $count = 0;

        foreach ($rows as $row) {
            $ticketNo = (string) $row['id'];

            /** @var SupportTicket $ticket */
            $ticket = SupportTicket::query()->where('ticket_no', $ticketNo)->first()
                ?? new SupportTicket(['ticket_no' => $ticketNo]);

            $ticket->forceFill([
                'user_id' => $this->resolveUserId((int) ($row['userId'] ?? 1)) ?? $this->importUserId(),
                'subject' => (string) ($row['subject'] ?? ''),
                'type' => $row['type'] ?? null,
                'priority' => TicketPriority::fromLegacy((string) ($row['priority'] ?? 'medium')),
                'status' => TicketStatus::fromLegacy((string) ($row['status'] ?? 'open')),
                'created_date' => $this->parseDate($row['date'] ?? null),
                'description' => (string) ($row['message'] ?? ''),
            ])->save();

            $ticket->messages()->delete();
            foreach ((array) ($row['replies'] ?? []) as $reply) {
                $ticket->messages()->create([
                    'author_type' => $reply['from'] === 'support' ? TicketAuthorType::Support : TicketAuthorType::User,
                    'message' => (string) ($reply['message'] ?? ''),
                    'created_at' => $this->parseDateTime($reply['date'] ?? null) ?? now(),
                    'updated_at' => $this->parseDateTime($reply['date'] ?? null) ?? now(),
                ]);
            }

            $this->map('tickets', $ticketNo, $ticket);
            $count++;
        }

        $this->say("tickets: {$count}");

        return $count;
    }

    private function importInvoices(): int
    {
        $rows = $this->json('billings.json');
        $count = 0;

        foreach ($rows as $row) {
            $invoiceNo = (string) $row['id'];

            /** @var Invoice $invoice */
            $invoice = Invoice::query()->where('invoice_no', $invoiceNo)->first()
                ?? new Invoice(['invoice_no' => $invoiceNo]);

            $orderId = null;
            if (! empty($row['orderId'])) {
                $orderId = Order::query()->where('number', (string) $row['orderId'])->value('id');
            }

            $total = (int) ($row['total'] ?? 0);

            $invoice->forceFill([
                'order_id' => $orderId,
                'user_id' => isset($row['userId']) ? (int) $row['userId'] : null,
                'invoice_date' => $this->parseDate($row['invoiceDate'] ?? null) ?? now()->toDateString(),
                'due_date' => $this->parseDate($row['dueDate'] ?? null),
                'amount' => $total,
                'paid' => 0,
                'amount_due' => $total,
                'status' => InvoiceStatus::tryFrom((string) ($row['status'] ?? 'Unpaid')) ?? InvoiceStatus::Unpaid,
            ])->save();

            $this->map('invoices', $invoiceNo, $invoice);
            $count++;
        }

        $this->say("invoices: {$count}");

        return $count;
    }

    private function importUserId(): int
    {
        return $this->resolveUserId(1)
            ?? (int) (User::query()->where('type', AccountType::Store)->min('id') ?? 0);
    }

    /**
     * Resolusi referensi userId legacy ke id user store hasil impor.
     */
    private function resolveUserId(int $legacyUserId): ?int
    {
        $mapped = LegacyIdMap::query()
            ->where('source', 'store')
            ->where('entity', 'users')
            ->where('legacy_id', (string) $legacyUserId)
            ->value('model_id');

        if ($mapped !== null) {
            return (int) $mapped;
        }

        return User::query()->whereKey($legacyUserId)->value('id');
    }

    /**
     * Resolusi product_id; null bila produk tidak ada di katalog.
     */
    private function existingProductId(mixed $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        return Product::query()->whereKey((int) $id)->value('id');
    }

    private function ownerType(string $source): PaperOwnerType
    {
        return $source === 'Milik Sendiri' ? PaperOwnerType::Self : PaperOwnerType::Vendor;
    }

    private function orderStatus(string $legacy): OrderStatus
    {
        try {
            return OrderStatus::fromLegacy($legacy);
        } catch (Throwable) {
            return OrderStatus::Pending;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function json(string $file): array
    {
        $path = $this->basePath.'/server/data/'.$file;

        if (! File::exists($path)) {
            throw new RuntimeException("Importer: data tidak ditemukan {$path}");
        }

        return json_decode((string) File::get($path), true) ?? [];
    }

    /**
     * Upsert dengan mempertahankan ID lama.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $attributes
     */
    private function upsertWithId(string $modelClass, int $legacyId, array $attributes, string $entity): Model
    {
        $model = $modelClass::query()->find($legacyId);

        if ($model === null) {
            /** @var Model $model */
            $model = new $modelClass;
            $model->setAttribute($model->getKeyName(), $legacyId);
        }

        $model->forceFill($attributes)->save();

        $this->map($entity, $legacyId, $model);

        return $model;
    }

    private function map(string $entity, int|string $legacyId, Model $model): void
    {
        LegacyIdMap::query()->updateOrCreate(
            ['source' => 'store', 'entity' => $entity, 'legacy_id' => (string) $legacyId],
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
        return $this->parseDate($value);
    }

    private function parseWib(?string $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        return $this->parseDate(str_replace(' WIB', '', $value));
    }

    private function say(string $message): void
    {
        if ($this->log !== null) {
            ($this->log)($message);
        }
    }
}
