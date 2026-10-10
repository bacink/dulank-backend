<?php

use App\Http\Controllers\Api\V1\Store\AuthController;
use App\Http\Controllers\Api\V1\Store\BlogPostController;
use App\Http\Controllers\Api\V1\Store\CartController;
use App\Http\Controllers\Api\V1\Store\CatalogController;
use App\Http\Controllers\Api\V1\Store\CategoryController;
use App\Http\Controllers\Api\V1\Store\FaqController;
use App\Http\Controllers\Api\V1\Store\InvoiceController;
use App\Http\Controllers\Api\V1\Store\OrderController;
use App\Http\Controllers\Api\V1\Store\ProductController;
use App\Http\Controllers\Api\V1\Store\QuotationController;
use App\Http\Controllers\Api\V1\Store\SaleBannerController;
use App\Http\Controllers\Api\V1\Store\SupportTicketController;
use App\Http\Controllers\Api\V1\Store\UserController;
use App\Http\Controllers\Api\V1\Store\WishlistController;
use Illuminate\Support\Facades\Route;

/**
 * Alias kompatibilitas `/api/*` gaya mock store lama.
 *
 * Handler sama dengan /api/v1/*; tidak ada duplikasi logika.
 */
Route::middleware('throttle:api')->group(function (): void {
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');

    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{slug}', [ProductController::class, 'show']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('catalog/{kind}', [CatalogController::class, 'show']);
    Route::get('blog', [BlogPostController::class, 'index']);
    Route::get('blog/{slug}', [BlogPostController::class, 'show']);
    Route::get('faqs', [FaqController::class, 'index']);
    Route::get('sale-banners', [SaleBannerController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::middleware('abilities:store')->group(function (): void {
            Route::get('cart', [CartController::class, 'index']);
            Route::post('cart', [CartController::class, 'store']);
            Route::put('cart/{itemId}', [CartController::class, 'update']);
            Route::delete('cart/{itemId}', [CartController::class, 'destroy']);
            Route::post('cart/clear', [CartController::class, 'clear']);

            Route::get('wishlist', [WishlistController::class, 'index']);
            Route::delete('wishlist/{itemId}', [WishlistController::class, 'destroy']);

            Route::get('orders', [OrderController::class, 'index']);
            Route::get('orders/{number}', [OrderController::class, 'show']);

            Route::get('quotations', [QuotationController::class, 'index']);
            Route::get('quotations/{noQuotation}', [QuotationController::class, 'show']);

            Route::get('tickets', [SupportTicketController::class, 'index']);
            Route::post('tickets', [SupportTicketController::class, 'store']);
            Route::get('tickets/{ticketNo}', [SupportTicketController::class, 'show']);
            Route::post('tickets/{ticketNo}/replies', [SupportTicketController::class, 'reply']);

            Route::get('billings', [InvoiceController::class, 'index']);

            Route::get('users/{id}', [UserController::class, 'show']);
            Route::get('users/{id}/addresses', [UserController::class, 'addresses']);
        });
    });
});
