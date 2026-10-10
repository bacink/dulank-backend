<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\CartItem;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Review;
use App\Models\Wishlist;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Monitoring webstore: cart, checkout, wishlist, review, contact form.
 */
class WebstoreController extends Controller
{
    public function carts(AdminIndexRequest $request): JsonResponse
    {
        $query = CartItem::query()->with(['user', 'product']);

        $this->applySearch($query, $request, ['name']);

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($items->map(fn (CartItem $item) => [
            'id' => (string) $item->id,
            'user' => $item->user?->name,
            'productId' => (string) $item->product_id,
            'product' => $item->product?->name ?? $item->name,
            'name' => $item->name,
            'quantity' => (float) $item->quantity,
            'price' => (int) $item->price,
            'spec' => $item->spec,
            'status' => $item->status?->value,
        ])->values(), ['page' => $page, 'limit' => $limit, 'total' => $total]);
    }

    public function checkouts(AdminIndexRequest $request): JsonResponse
    {
        $query = Order::query()->with('items');

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $query) => $query
                ->where('number', 'like', $term)
                ->orWhere('contact->name', 'like', $term));
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $orders = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($orders->map(fn (Order $order) => [
            'id' => $order->number,
            'customer' => $order->contact['name'] ?? $order->user?->name,
            'date' => ($order->ordered_at ?? $order->created_at)?->toDateString(),
            'total' => (int) $order->total,
            'status' => $order->status?->admin(),
            'canonicalStatus' => $order->status?->legacy(),
            'paymentStatus' => $order->payment_status?->value,
            'itemCount' => $order->items->count(),
        ])->values(), ['page' => $page, 'limit' => $limit, 'total' => $total]);
    }

    public function wishlist(AdminIndexRequest $request): JsonResponse
    {
        $query = Wishlist::query()->with(['user', 'product']);

        $this->applySearch($query, $request, ['name']);

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($items->map(fn (Wishlist $item) => [
            'id' => (string) $item->id,
            'user' => $item->user?->name,
            'productId' => (string) $item->product_id,
            'name' => $item->name,
            'price' => (int) $item->price,
            'spec' => $item->spec,
            'addedAt' => $item->added_at?->toDateString(),
            'expired' => $item->isExpired(),
        ])->values(), ['page' => $page, 'limit' => $limit, 'total' => $total]);
    }

    public function destroyWishlist(Wishlist $wishlist): JsonResponse
    {
        $wishlist->delete();

        return ApiResponse::success(['id' => (string) $wishlist->id, 'removed' => true]);
    }

    public function reviews(AdminIndexRequest $request): JsonResponse
    {
        $query = Review::query();

        $rating = trim((string) $request->input('rating', ''));
        if ($rating !== '') {
            $query->where('rating', (int) $rating);
        }

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $this->applySearch($query, $request, ['product_name', 'title', 'user_email']);

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $reviews = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($reviews->map(fn (Review $review) => $this->reviewPayload($review))->values(), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function reviewStats(): JsonResponse
    {
        return ApiResponse::success([
            'totalReview' => Review::query()->count(),
            'totalProduct' => Review::query()->distinct()->count('product_id'),
            'totalPublish' => Review::query()->where('status', 'Publish')->count(),
        ]);
    }

    public function updateReview(Request $request, Review $review): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:Publish,Unpublish'],
        ]);

        $review->forceFill(['status' => $validated['status']])->save();

        return ApiResponse::success($this->reviewPayload($review));
    }

    public function destroyReview(Review $review): JsonResponse
    {
        $review->delete();

        return ApiResponse::success(['id' => (string) $review->id, 'removed' => true]);
    }

    public function contactForms(AdminIndexRequest $request): JsonResponse
    {
        $query = ContactMessage::query();

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $this->applySearch($query, $request, ['name', 'email', 'message']);

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $messages = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($messages->map(fn (ContactMessage $message) => [
            'id' => (string) $message->id,
            'name' => $message->name,
            'email' => $message->email,
            'phone' => $message->phone,
            'message' => $message->message,
            'date' => $message->date?->toDateString(),
            'status' => $message->status,
        ])->values(), ['page' => $page, 'limit' => $limit, 'total' => $total]);
    }

    public function contactStats(): JsonResponse
    {
        return ApiResponse::success([
            'totalContact' => ContactMessage::query()->count(),
        ]);
    }

    public function updateContactForm(Request $request, ContactMessage $contactMessage): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:Unread,Read,Replied,Pending,Answered'],
        ]);

        $contactMessage->forceFill(['status' => $validated['status']])->save();

        return ApiResponse::success(['id' => (string) $contactMessage->id, 'status' => $contactMessage->status]);
    }

    public function destroyContactForm(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->delete();

        return ApiResponse::success(['id' => (string) $contactMessage->id, 'removed' => true]);
    }

    /**
     * @param  Builder<*>  $query
     * @param  array<int, string>  $columns
     */
    private function applySearch(Builder $query, AdminIndexRequest $request, array $columns): void
    {
        $search = trim((string) $request->input('search', ''));
        if ($search === '') {
            return;
        }

        $term = '%'.$search.'%';
        $query->where(function (Builder $query) use ($columns, $term): void {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', $term);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewPayload(Review $review): array
    {
        return [
            'id' => (string) $review->id,
            'customerId' => $review->customer_id !== null ? (string) $review->customer_id : null,
            'productId' => $review->product_id !== null ? (string) $review->product_id : null,
            'productName' => $review->product_name,
            'userEmail' => $review->user_email,
            'date' => $review->date?->toDateString(),
            'rating' => $review->rating,
            'title' => $review->title,
            'review' => $review->review,
            'status' => $review->status,
        ];
    }
}
