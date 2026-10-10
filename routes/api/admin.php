<?php

use App\Http\Controllers\Api\V1\Admin\AuthController;
use App\Http\Controllers\Api\V1\Admin\DeliveryNoteController;
use App\Http\Controllers\Api\V1\Admin\InvoiceController;
use App\Http\Controllers\Api\V1\Admin\OrderController;
use App\Http\Controllers\Api\V1\Admin\PaymentController;
use App\Http\Controllers\Api\V1\Admin\PurchaseCategoryController;
use App\Http\Controllers\Api\V1\Admin\PurchaseController;
use App\Http\Controllers\Api\V1\Admin\PurchaseItemController;
use App\Http\Controllers\Api\V1\Admin\PurchaseOrderController;
use App\Http\Controllers\Api\V1\Admin\PurchaseReturnController;
use App\Http\Controllers\Api\V1\Admin\QuotationController;
use App\Http\Controllers\Api\V1\Admin\RequestQuotationController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\SaleController;
use App\Http\Controllers\Api\V1\Admin\SalesReturnController;
use App\Http\Controllers\Api\V1\Admin\SupplierController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware(['throttle:api', 'throttle:auth']);

    Route::middleware(['auth:sanctum', 'abilities:admin', 'throttle:admin'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Order monitoring + pengiriman + retur penjualan.
        Route::middleware('permission:orders.manage')->group(function (): void {
            Route::get('orders/stats', [OrderController::class, 'stats']);
            Route::get('orders', [OrderController::class, 'index']);
            Route::get('orders/{number}', [OrderController::class, 'show']);
            Route::post('orders/{number}/status', [OrderController::class, 'updateStatus']);

            Route::get('delivery-notes', [DeliveryNoteController::class, 'index']);
            Route::post('delivery-notes', [DeliveryNoteController::class, 'store']);
            Route::get('delivery-notes/{dnNo}', [DeliveryNoteController::class, 'show']);
            Route::put('delivery-notes/{dnNo}', [DeliveryNoteController::class, 'update']);
            Route::delete('delivery-notes/{dnNo}', [DeliveryNoteController::class, 'destroy']);

            Route::get('sales-returns', [SalesReturnController::class, 'index']);
            Route::post('sales-returns', [SalesReturnController::class, 'store']);
            Route::get('sales-returns/{returnNo}', [SalesReturnController::class, 'show']);
            Route::put('sales-returns/{returnNo}', [SalesReturnController::class, 'update']);
            Route::delete('sales-returns/{returnNo}', [SalesReturnController::class, 'destroy']);
            Route::post('sales-returns/{returnNo}/refund', [SalesReturnController::class, 'refund']);
        });

        // Sales/POS + quotation + RFQ.
        Route::middleware('permission:sales.manage')->group(function (): void {
            Route::get('sales', [SaleController::class, 'index']);
            Route::post('sales', [SaleController::class, 'store']);
            Route::post('sales/{number}/payments', [SaleController::class, 'recordPayment']);
            Route::post('sales/{number}/void', [SaleController::class, 'void']);

            Route::get('quotations', [QuotationController::class, 'index']);
            Route::post('quotations', [QuotationController::class, 'store']);
            Route::get('quotations/{noQuotation}', [QuotationController::class, 'show']);
            Route::put('quotations/{noQuotation}', [QuotationController::class, 'update']);
            Route::post('quotations/{noQuotation}/order', [QuotationController::class, 'convert']);

            Route::get('request-quotations', [RequestQuotationController::class, 'index']);
            Route::post('request-quotations', [RequestQuotationController::class, 'store']);
            Route::get('request-quotations/{noRequest}', [RequestQuotationController::class, 'show']);
            Route::put('request-quotations/{noRequest}', [RequestQuotationController::class, 'update']);
            Route::delete('request-quotations/{noRequest}', [RequestQuotationController::class, 'destroy']);
            Route::post('request-quotations/{noRequest}/duplicate', [RequestQuotationController::class, 'duplicate']);
        });

        // Keuangan: pembayaran + invoice.
        Route::middleware('permission:finance.manage')->group(function (): void {
            Route::get('payments', [PaymentController::class, 'index']);
            Route::post('payments', [PaymentController::class, 'store']);
            Route::post('payments/{payment}/verify', [PaymentController::class, 'verify']);
            Route::post('payments/{payment}/reject', [PaymentController::class, 'reject']);

            Route::get('invoices', [InvoiceController::class, 'index']);
            Route::get('invoices/{invoiceNo}', [InvoiceController::class, 'show']);
            Route::post('invoices/{invoiceNo}/payments', [InvoiceController::class, 'recordPayment']);
        });

        // User management: role & permission matrix.
        Route::middleware('permission:users.manage')->group(function (): void {
            Route::get('roles', [RoleController::class, 'index']);
            Route::post('roles', [RoleController::class, 'store']);
            Route::put('roles/{role}', [RoleController::class, 'update']);
            Route::delete('roles/{role}', [RoleController::class, 'destroy']);
            Route::get('permissions', [RoleController::class, 'permissions']);
            Route::put('users/{id}/roles', [RoleController::class, 'assignToUser']);
        });

        // Purchases: supplier, master item, pembelian, PO, retur.
        Route::middleware('permission:purchases.manage')->group(function (): void {
            Route::get('suppliers', [SupplierController::class, 'index']);
            Route::post('suppliers', [SupplierController::class, 'store']);
            Route::get('suppliers/{supplierId}', [SupplierController::class, 'show']);
            Route::put('suppliers/{supplierId}', [SupplierController::class, 'update']);
            Route::delete('suppliers/{supplierId}', [SupplierController::class, 'destroy']);

            Route::get('purchase-categories', [PurchaseCategoryController::class, 'index']);
            Route::post('purchase-categories', [PurchaseCategoryController::class, 'store']);
            Route::put('purchase-categories/{purchaseCategory}', [PurchaseCategoryController::class, 'update']);
            Route::delete('purchase-categories/{purchaseCategory}', [PurchaseCategoryController::class, 'destroy']);

            Route::get('purchase-items', [PurchaseItemController::class, 'index']);
            Route::post('purchase-items', [PurchaseItemController::class, 'store']);
            Route::put('purchase-items/{purchaseItem}', [PurchaseItemController::class, 'update']);
            Route::delete('purchase-items/{purchaseItem}', [PurchaseItemController::class, 'destroy']);

            Route::get('purchases', [PurchaseController::class, 'index']);
            Route::post('purchases', [PurchaseController::class, 'store']);
            Route::get('purchases/{noPurchase}', [PurchaseController::class, 'show']);
            Route::put('purchases/{noPurchase}', [PurchaseController::class, 'update']);
            Route::delete('purchases/{noPurchase}', [PurchaseController::class, 'destroy']);
            Route::post('purchases/{noPurchase}/payments', [PurchaseController::class, 'recordPayment']);

            Route::get('purchase-orders', [PurchaseOrderController::class, 'index']);
            Route::post('purchase-orders', [PurchaseOrderController::class, 'store']);
            Route::get('purchase-orders/{noPo}', [PurchaseOrderController::class, 'show']);
            Route::put('purchase-orders/{noPo}', [PurchaseOrderController::class, 'update']);
            Route::delete('purchase-orders/{noPo}', [PurchaseOrderController::class, 'destroy']);

            Route::get('purchase-returns', [PurchaseReturnController::class, 'index']);
            Route::post('purchase-returns', [PurchaseReturnController::class, 'store']);
            Route::get('purchase-returns/{noPr}', [PurchaseReturnController::class, 'show']);
            Route::put('purchase-returns/{noPr}', [PurchaseReturnController::class, 'update']);
            Route::delete('purchase-returns/{noPr}', [PurchaseReturnController::class, 'destroy']);
            Route::post('purchase-returns/{noPr}/payments', [PurchaseReturnController::class, 'recordPayment']);
        });
    });
});
