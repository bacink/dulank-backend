<?php

use App\Http\Controllers\Api\V1\Store\AddressController;
use App\Http\Controllers\Api\V1\Store\AuthController;
use App\Http\Controllers\Api\V1\Store\BlogPostController;
use App\Http\Controllers\Api\V1\Store\CartController;
use App\Http\Controllers\Api\V1\Store\CatalogController;
use App\Http\Controllers\Api\V1\Store\CategoryController;
use App\Http\Controllers\Api\V1\Store\FaqController;
use App\Http\Controllers\Api\V1\Store\InvoiceController;
use App\Http\Controllers\Api\V1\Store\OrderController;
use App\Http\Controllers\Api\V1\Store\PaymentController;
use App\Http\Controllers\Api\V1\Store\ProductController;
use App\Http\Controllers\Api\V1\Store\ProfileController;
use App\Http\Controllers\Api\V1\Store\QuotationController;
use App\Http\Controllers\Api\V1\Store\RegionController;
use App\Http\Controllers\Api\V1\Store\SaleBannerController;
use App\Http\Controllers\Api\V1\Store\SupportTicketController;
use App\Http\Controllers\Api\V1\Store\UploadController;
use App\Http\Controllers\Api\V1\Store\UserController;
use App\Http\Controllers\Api\V1\Store\WishlistController;
use Illuminate\Support\Facades\Route;

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

    Route::get('regions/provinces', [RegionController::class, 'provinces']);
    Route::get('regions/regencies', [RegionController::class, 'regencies']);
    Route::get('regions/districts', [RegionController::class, 'districts']);

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
            Route::post('wishlist', [WishlistController::class, 'store']);
            Route::delete('wishlist/{itemId}', [WishlistController::class, 'destroy']);

            Route::get('orders', [OrderController::class, 'index']);
            Route::post('orders', [OrderController::class, 'store']);
            Route::get('orders/{number}', [OrderController::class, 'show']);

            Route::get('quotations', [QuotationController::class, 'index']);
            Route::post('quotations', [QuotationController::class, 'store']);
            Route::get('quotations/{noQuotation}', [QuotationController::class, 'show']);
            Route::put('quotations/{noQuotation}', [QuotationController::class, 'update']);

            Route::get('tickets', [SupportTicketController::class, 'index']);
            Route::post('tickets', [SupportTicketController::class, 'store']);
            Route::get('tickets/{ticketNo}', [SupportTicketController::class, 'show']);
            Route::post('tickets/{ticketNo}/replies', [SupportTicketController::class, 'reply']);

            Route::get('invoices', [InvoiceController::class, 'index']);
            Route::get('invoices/{invoiceNo}', [InvoiceController::class, 'show']);

            Route::get('payments', [PaymentController::class, 'index']);
            Route::post('payments', [PaymentController::class, 'store']);

            Route::get('addresses', [AddressController::class, 'index']);
            Route::post('addresses', [AddressController::class, 'store']);
            Route::put('addresses/{id}', [AddressController::class, 'update']);
            Route::delete('addresses/{id}', [AddressController::class, 'destroy']);

            Route::get('profile', [ProfileController::class, 'show']);
            Route::put('profile', [ProfileController::class, 'update']);
            Route::put('profile/password', [ProfileController::class, 'updatePassword']);
            Route::put('profile/2fa', [ProfileController::class, 'enableTwoFactor']);
            Route::post('profile/2fa/confirm', [ProfileController::class, 'confirmTwoFactor']);
            Route::delete('profile/2fa', [ProfileController::class, 'disableTwoFactor']);

            Route::post('uploads/avatar', [UploadController::class, 'avatar']);
            Route::post('uploads/artwork', [UploadController::class, 'artwork']);

            Route::get('users/{id}', [UserController::class, 'show']);
            Route::get('users/{id}/addresses', [UserController::class, 'addresses']);
        });
    });
});
