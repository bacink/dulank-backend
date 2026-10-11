<?php

use App\Http\Controllers\Api\V1\Admin\AdminProfileController;
use App\Http\Controllers\Api\V1\Admin\AuthController;
use App\Http\Controllers\Api\V1\Admin\BankAccountController;
use App\Http\Controllers\Api\V1\Admin\BlogController;
use App\Http\Controllers\Api\V1\Admin\CalculatorController;
use App\Http\Controllers\Api\V1\Admin\CashAdvanceController;
use App\Http\Controllers\Api\V1\Admin\CatalogMasterController;
use App\Http\Controllers\Api\V1\Admin\ContentAssetController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\DeleteAccountController;
use App\Http\Controllers\Api\V1\Admin\DeliveryNoteController;
use App\Http\Controllers\Api\V1\Admin\DocumentPdfController;
use App\Http\Controllers\Api\V1\Admin\EmployeeController;
use App\Http\Controllers\Api\V1\Admin\ExpenseController;
use App\Http\Controllers\Api\V1\Admin\FaqController;
use App\Http\Controllers\Api\V1\Admin\FinanceReportController;
use App\Http\Controllers\Api\V1\Admin\GatewaySettingController;
use App\Http\Controllers\Api\V1\Admin\IncomeController;
use App\Http\Controllers\Api\V1\Admin\InvoiceController;
use App\Http\Controllers\Api\V1\Admin\JobBranchController;
use App\Http\Controllers\Api\V1\Admin\JobOrderController;
use App\Http\Controllers\Api\V1\Admin\JobViewController;
use App\Http\Controllers\Api\V1\Admin\MoneyTransferController;
use App\Http\Controllers\Api\V1\Admin\OrderController;
use App\Http\Controllers\Api\V1\Admin\OrganizationController;
use App\Http\Controllers\Api\V1\Admin\PaperController;
use App\Http\Controllers\Api\V1\Admin\PaymentController;
use App\Http\Controllers\Api\V1\Admin\PayrollController;
use App\Http\Controllers\Api\V1\Admin\PeopleController;
use App\Http\Controllers\Api\V1\Admin\PosController;
use App\Http\Controllers\Api\V1\Admin\ProductController;
use App\Http\Controllers\Api\V1\Admin\PromoController;
use App\Http\Controllers\Api\V1\Admin\PurchaseCategoryController;
use App\Http\Controllers\Api\V1\Admin\PurchaseController;
use App\Http\Controllers\Api\V1\Admin\PurchaseReturnController;
use App\Http\Controllers\Api\V1\Admin\PurchaseItemController;
use App\Http\Controllers\Api\V1\Admin\PurchaseOrderController;
use App\Http\Controllers\Api\V1\Admin\QuotationController;
use App\Http\Controllers\Api\V1\Admin\RegionController;
use App\Http\Controllers\Api\V1\Admin\ReportController;
use App\Http\Controllers\Api\V1\Admin\RequestQuotationController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\SaleController;
use App\Http\Controllers\Api\V1\Admin\SalesReturnController;
use App\Http\Controllers\Api\V1\Admin\SettingController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionController;
use App\Http\Controllers\Api\V1\Admin\SupplierController;
use App\Http\Controllers\Api\V1\Admin\TaxController;
use App\Http\Controllers\Api\V1\Admin\TaxRateController;
use App\Http\Controllers\Api\V1\Admin\UserManagementController;
use App\Http\Controllers\Api\V1\Admin\WebstoreController;
use App\Http\Controllers\Api\V1\Admin\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware(['throttle:api', 'throttle:auth']);

    Route::middleware(['auth:sanctum', 'abilities:admin', 'throttle:admin'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('profile', [AdminProfileController::class, 'show']);
        Route::put('profile', [AdminProfileController::class, 'update']);
        Route::put('profile/password', [AdminProfileController::class, 'updatePassword']);
        Route::post('uploads/avatar', [AdminProfileController::class, 'uploadAvatar']);
        Route::post('uploads/image', [AdminProfileController::class, 'uploadImage']);

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
            Route::get('delivery-notes/{dnNo}/pdf', [DocumentPdfController::class, 'deliveryNote']);

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
            Route::get('quotations/{noQuotation}/pdf', [DocumentPdfController::class, 'quotation']);

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
            Route::get('invoices/{invoiceNo}/pdf', [DocumentPdfController::class, 'invoice']);

            // Bank & ledger.
            Route::get('bank-account-types', [BankAccountController::class, 'types']);
            Route::post('bank-account-types', [BankAccountController::class, 'storeType']);
            Route::put('bank-account-types/{bankAccountType}', [BankAccountController::class, 'updateType']);
            Route::delete('bank-account-types/{bankAccountType}', [BankAccountController::class, 'destroyType']);

            Route::get('bank-accounts', [BankAccountController::class, 'index']);
            Route::post('bank-accounts', [BankAccountController::class, 'store']);
            Route::put('bank-accounts/{bankAccount}', [BankAccountController::class, 'update']);
            Route::delete('bank-accounts/{bankAccount}', [BankAccountController::class, 'destroy']);
            Route::get('bank-accounts/{bankAccount}/ledger', [BankAccountController::class, 'ledger']);
            Route::get('bank-accounts/{bankAccount}/statement', [BankAccountController::class, 'statement']);

            Route::get('money-transfers', [MoneyTransferController::class, 'index']);
            Route::post('money-transfers', [MoneyTransferController::class, 'store']);
            Route::get('money-transfers/{moneyTransfer}', [MoneyTransferController::class, 'show']);

            Route::get('cash-advances', [CashAdvanceController::class, 'index']);
            Route::post('cash-advances', [CashAdvanceController::class, 'store']);
            Route::get('cash-advances/{cashAdvance}', [CashAdvanceController::class, 'show']);
            Route::post('cash-advances/{cashAdvance}/payments', [CashAdvanceController::class, 'recordPayment']);
            Route::delete('cash-advances/{cashAdvance}', [CashAdvanceController::class, 'destroy']);

            // Pendapatan & pengeluaran.
            Route::get('income-categories', [IncomeController::class, 'categories']);
            Route::post('income-categories', [IncomeController::class, 'storeCategory']);
            Route::put('income-categories/{incomeCategory}', [IncomeController::class, 'updateCategory']);
            Route::delete('income-categories/{incomeCategory}', [IncomeController::class, 'destroyCategory']);
            Route::get('incomes', [IncomeController::class, 'index']);
            Route::post('incomes', [IncomeController::class, 'store']);
            Route::put('incomes/{income}', [IncomeController::class, 'update']);
            Route::post('incomes/{income}/cancel', [IncomeController::class, 'cancel']);
            Route::delete('incomes/{income}', [IncomeController::class, 'destroy']);

            Route::get('expense-categories', [ExpenseController::class, 'categories']);
            Route::post('expense-categories', [ExpenseController::class, 'storeCategory']);
            Route::put('expense-categories/{expenseCategory}', [ExpenseController::class, 'updateCategory']);
            Route::delete('expense-categories/{expenseCategory}', [ExpenseController::class, 'destroyCategory']);
            Route::get('expenses', [ExpenseController::class, 'index']);
            Route::post('expenses', [ExpenseController::class, 'store']);
            Route::put('expenses/{expense}', [ExpenseController::class, 'update']);
            Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy']);
            Route::post('expenses/{expense}/payments', [ExpenseController::class, 'payments']);

            // Dokumen pajak.
            Route::get('input-taxes', [TaxController::class, 'inputIndex']);
            Route::post('input-taxes', [TaxController::class, 'inputStore']);
            Route::put('input-taxes/{inputTaxDocument}', [TaxController::class, 'inputUpdate']);
            Route::get('output-taxes', [TaxController::class, 'outputIndex']);
            Route::post('output-taxes', [TaxController::class, 'outputStore']);
            Route::put('output-taxes/{outputTaxDocument}', [TaxController::class, 'outputUpdate']);

            // Laporan kеuangan.
            Route::get('finance/summary', [FinanceReportController::class, 'summary']);
            Route::get('finance/cash-flow', [FinanceReportController::class, 'cashFlow']);
            Route::get('finance/balance-sheet', [FinanceReportController::class, 'balanceSheet']);
            Route::get('finance/customer-entries', [FinanceReportController::class, 'customerEntries']);
            Route::get('finance/customer-entries/{customerId}/balance', [FinanceReportController::class, 'customerBalance']);

            Route::get('tax-rates', [TaxRateController::class, 'index']);
            Route::post('tax-rates', [TaxRateController::class, 'store']);
            Route::put('tax-rates/{taxRate}', [TaxRateController::class, 'update']);
            Route::delete('tax-rates/{taxRate}', [TaxRateController::class, 'destroy']);
        });

        // User management: role & permission matrix.
        Route::middleware('permission:users.manage')->group(function (): void {
            Route::get('roles', [RoleController::class, 'index']);
            Route::post('roles', [RoleController::class, 'store']);
            Route::put('roles/{role}', [RoleController::class, 'update']);
            Route::delete('roles/{role}', [RoleController::class, 'destroy']);
            Route::get('permissions', [RoleController::class, 'permissions']);
            Route::put('users/{id}/roles', [RoleController::class, 'assignToUser']);

            // Members (user store) & user admin.
            Route::get('users', [UserManagementController::class, 'members']);
            Route::put('users/{user}', [UserManagementController::class, 'updateMember']);
            Route::delete('users/{user}', [UserManagementController::class, 'destroyMember']);

            Route::get('user-admins', [UserManagementController::class, 'userAdmins']);
            Route::post('user-admins', [UserManagementController::class, 'storeUserAdmin']);
            Route::put('user-admins/{userAdmin}', [UserManagementController::class, 'updateUserAdmin']);
            Route::delete('user-admins/{userAdmin}', [UserManagementController::class, 'destroyUserAdmin']);

            Route::get('delete-accounts', [DeleteAccountController::class, 'index']);
            Route::delete('delete-accounts/{deleteAccountRequest}', [DeleteAccountController::class, 'destroy']);
        });

        // Products & master katalog.
        Route::middleware('permission:products.manage')->group(function (): void {
            Route::get('products', [ProductController::class, 'index']);
            Route::post('products', [ProductController::class, 'store']);
            Route::get('products/{product}', [ProductController::class, 'show']);
            Route::put('products/{product}', [ProductController::class, 'update']);
            Route::delete('products/{product}', [ProductController::class, 'destroy']);

            Route::get('categories', [CatalogMasterController::class, 'categories']);
            Route::post('categories', [CatalogMasterController::class, 'storeCategory']);
            Route::put('categories/{category}', [CatalogMasterController::class, 'updateCategory']);
            Route::delete('categories/{category}', [CatalogMasterController::class, 'destroyCategory']);

            Route::get('sub-categories', [CatalogMasterController::class, 'subCategories']);
            Route::post('sub-categories', [CatalogMasterController::class, 'storeSubCategory']);
            Route::put('sub-categories/{subCategory}', [CatalogMasterController::class, 'updateSubCategory']);
            Route::delete('sub-categories/{subCategory}', [CatalogMasterController::class, 'destroySubCategory']);

            Route::get('units', [CatalogMasterController::class, 'units']);
            Route::post('units', [CatalogMasterController::class, 'storeUnit']);
            Route::put('units/{unit}', [CatalogMasterController::class, 'updateUnit']);
            Route::delete('units/{unit}', [CatalogMasterController::class, 'destroyUnit']);

            Route::get('variants', [CatalogMasterController::class, 'variants']);
            Route::post('variants', [CatalogMasterController::class, 'storeVariant']);
            Route::put('variants/{variant}', [CatalogMasterController::class, 'updateVariant']);
            Route::delete('variants/{variant}', [CatalogMasterController::class, 'destroyVariant']);

            Route::get('product-processes', [CatalogMasterController::class, 'productProcesses']);
            Route::post('product-processes', [CatalogMasterController::class, 'storeProductProcess']);
            Route::put('product-processes/{productProcess}', [CatalogMasterController::class, 'updateProductProcess']);
            Route::delete('product-processes/{productProcess}', [CatalogMasterController::class, 'destroyProductProcess']);

            Route::get('workshop-services', [CatalogMasterController::class, 'workshopServices']);
            Route::post('workshop-services', [CatalogMasterController::class, 'storeWorkshopService']);
            Route::put('workshop-services/{workshopService}', [CatalogMasterController::class, 'updateWorkshopService']);
            Route::delete('workshop-services/{workshopService}', [CatalogMasterController::class, 'destroyWorkshopService']);
        });

        // Peoples: customers, customer-types, stores, addresses.
        Route::middleware('permission:peoples.manage')->group(function (): void {
            Route::get('customers', [PeopleController::class, 'customers']);
            Route::post('customers', [PeopleController::class, 'storeCustomer']);
            Route::put('customers/{customer}', [PeopleController::class, 'updateCustomer']);
            Route::delete('customers/{customer}', [PeopleController::class, 'destroyCustomer']);

            Route::get('customer-types', [PeopleController::class, 'customerTypes']);
            Route::post('customer-types', [PeopleController::class, 'storeCustomerType']);
            Route::put('customer-types/{customerType}', [PeopleController::class, 'updateCustomerType']);
            Route::delete('customer-types/{customerType}', [PeopleController::class, 'destroyCustomerType']);

            Route::get('stores', [PeopleController::class, 'stores']);
            Route::post('stores', [PeopleController::class, 'storeStore']);
            Route::put('stores/{store}', [PeopleController::class, 'updateStore']);
            Route::delete('stores/{store}', [PeopleController::class, 'destroyStore']);

            Route::get('addresses/stats', [PeopleController::class, 'addressStats']);
            Route::get('addresses', [PeopleController::class, 'addresses']);
            Route::post('addresses', [PeopleController::class, 'storeAddress']);
            Route::put('addresses/{address}', [PeopleController::class, 'updateAddress']);
            Route::delete('addresses/{address}', [PeopleController::class, 'destroyAddress']);
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
            Route::get('purchases/{noPurchase}/pdf', [DocumentPdfController::class, 'purchase']);

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

        // HRM: organisasi, karyawan, payroll, insentif.
        Route::middleware('permission:hrm.manage')->group(function (): void {
            Route::get('departments', [OrganizationController::class, 'departments']);
            Route::post('departments', [OrganizationController::class, 'storeDepartment']);
            Route::put('departments/{department}', [OrganizationController::class, 'updateDepartment']);
            Route::delete('departments/{department}', [OrganizationController::class, 'destroyDepartment']);

            Route::get('designations', [OrganizationController::class, 'designations']);
            Route::post('designations', [OrganizationController::class, 'storeDesignation']);
            Route::put('designations/{designation}', [OrganizationController::class, 'updateDesignation']);
            Route::delete('designations/{designation}', [OrganizationController::class, 'destroyDesignation']);

            Route::get('employees', [EmployeeController::class, 'index']);
            Route::post('employees', [EmployeeController::class, 'store']);
            Route::get('employees/{employee}', [EmployeeController::class, 'show']);
            Route::put('employees/{employee}', [EmployeeController::class, 'update']);
            Route::delete('employees/{employee}', [EmployeeController::class, 'destroy']);

            Route::get('employee-salaries', [PayrollController::class, 'salaries']);
            Route::post('employee-salaries', [PayrollController::class, 'storeSalary']);
            Route::delete('employee-salaries/{salary}', [PayrollController::class, 'destroySalary']);

            Route::get('payslips', [PayrollController::class, 'payslips']);
            Route::post('payslips/generate', [PayrollController::class, 'generatePayslip']);
            Route::post('payslips/{slipNo}/pay', [PayrollController::class, 'payPayslip']);
            Route::get('payslips/{slipNo}/pdf', [DocumentPdfController::class, 'payslip']);

            Route::get('incentives', [PayrollController::class, 'incentives']);
            Route::post('incentives', [PayrollController::class, 'storeIncentive']);
            Route::post('incentives/{incentive}/pay', [PayrollController::class, 'payIncentive']);
        });

        // Workflow & produksi: flow, work flow, job order, job branch, progress.
        Route::middleware('permission:workflow.manage')->group(function (): void {
            Route::get('flow-categories', [WorkflowController::class, 'categories']);
            Route::post('flow-categories', [WorkflowController::class, 'storeCategory']);
            Route::put('flow-categories/{flowCategory}', [WorkflowController::class, 'updateCategory']);
            Route::delete('flow-categories/{flowCategory}', [WorkflowController::class, 'destroyCategory']);

            Route::get('flow-names', [WorkflowController::class, 'names']);
            Route::post('flow-names', [WorkflowController::class, 'storeName']);
            Route::put('flow-names/{flowName}', [WorkflowController::class, 'updateName']);
            Route::delete('flow-names/{flowName}', [WorkflowController::class, 'destroyName']);

            Route::get('flow-templates', [WorkflowController::class, 'templates']);
            Route::post('flow-templates', [WorkflowController::class, 'storeTemplate']);
            Route::delete('flow-templates/{flowTemplate}', [WorkflowController::class, 'destroyTemplate']);

            Route::get('work-flow-context', [WorkflowController::class, 'context']);
            Route::get('work-flows', [JobOrderController::class, 'workFlows']);
            Route::post('work-flows', [JobOrderController::class, 'storeWorkFlow']);
            Route::put('work-flows/{workFlow}', [JobOrderController::class, 'updateWorkFlow']);
            Route::delete('work-flows/{workFlow}', [JobOrderController::class, 'destroyWorkFlow']);

            Route::get('job-order-context', [JobOrderController::class, 'context']);
            Route::get('job-orders', [JobOrderController::class, 'index']);
            Route::post('job-orders', [JobOrderController::class, 'store']);
            Route::get('job-orders/{jobOrder}', [JobOrderController::class, 'show']);
            Route::put('job-orders/{jobOrder}', [JobOrderController::class, 'update']);
            Route::post('job-orders/{jobOrder}/status', [JobOrderController::class, 'updateStatus']);
            Route::delete('job-orders/{jobOrder}', [JobOrderController::class, 'destroy']);

            Route::get('job-branches/history', [JobBranchController::class, 'history']);
            Route::get('job-branches', [JobBranchController::class, 'index']);
            Route::post('job-branches', [JobBranchController::class, 'store']);
            Route::get('job-branches/{jobBranch}', [JobBranchController::class, 'show']);
            Route::put('job-branches/{jobBranch}', [JobBranchController::class, 'update']);
            Route::delete('job-branches/{jobBranch}', [JobBranchController::class, 'destroy']);
            Route::post('job-branches/{jobBranch}/complete', [JobBranchController::class, 'complete']);

            Route::get('job-progress', [JobBranchController::class, 'jobProgress']);
            Route::post('job-progress', [JobBranchController::class, 'storeJobProgress']);
            Route::put('job-progress/{jobProgress}', [JobBranchController::class, 'updateJobProgress']);

            Route::get('job-list', [JobViewController::class, 'jobList']);
            Route::get('my-jobs', [JobViewController::class, 'myJobs']);
            Route::get('my-incentives', [JobBranchController::class, 'myIncentives']);
        });

        // Konten: blog, FAQ, client, download, footer, appearance.
        Route::middleware('permission:content.manage')->group(function (): void {
            Route::get('blogs', [BlogController::class, 'index']);
            Route::post('blogs', [BlogController::class, 'store']);
            Route::put('blogs/{blogPost}', [BlogController::class, 'update']);
            Route::delete('blogs/{blogPost}', [BlogController::class, 'destroy']);

            Route::get('blog-categories', [BlogController::class, 'categories']);
            Route::post('blog-categories', [BlogController::class, 'storeCategory']);
            Route::put('blog-categories/{blogCategory}', [BlogController::class, 'updateCategory']);
            Route::delete('blog-categories/{blogCategory}', [BlogController::class, 'destroyCategory']);

            Route::get('blog-tags', [BlogController::class, 'tags']);
            Route::post('blog-tags', [BlogController::class, 'storeTag']);
            Route::delete('blog-tags/{tag}', [BlogController::class, 'destroyTag']);

            Route::get('blog-comments', [BlogController::class, 'comments']);
            Route::put('blog-comments/{blogComment}', [BlogController::class, 'updateComment']);
            Route::delete('blog-comments/{blogComment}', [BlogController::class, 'destroyComment']);

            Route::get('faqs', [FaqController::class, 'index']);
            Route::post('faqs', [FaqController::class, 'store']);
            Route::put('faqs/{faq}', [FaqController::class, 'update']);
            Route::delete('faqs/{faq}', [FaqController::class, 'destroy']);

            Route::get('faq-categories', [FaqController::class, 'categories']);
            Route::post('faq-categories', [FaqController::class, 'storeCategory']);
            Route::delete('faq-categories/{faqCategory}', [FaqController::class, 'destroyCategory']);

            Route::get('clients', [ContentAssetController::class, 'clients']);
            Route::post('clients', [ContentAssetController::class, 'storeClient']);
            Route::put('clients/{client}', [ContentAssetController::class, 'updateClient']);
            Route::delete('clients/{client}', [ContentAssetController::class, 'destroyClient']);

            Route::get('download-files', [ContentAssetController::class, 'downloadFiles']);
            Route::post('download-files', [ContentAssetController::class, 'storeDownloadFile']);
            Route::delete('download-files/{downloadFile}', [ContentAssetController::class, 'destroyDownloadFile']);

            Route::get('footer', [ContentAssetController::class, 'footer']);
            Route::put('footer', [ContentAssetController::class, 'updateFooter']);
            Route::get('appearance', [ContentAssetController::class, 'appearance']);
            Route::put('appearance', [ContentAssetController::class, 'updateAppearance']);
        });

        // Settings: grup, custom field, ban IP, bahasa.
        Route::middleware('permission:settings.manage')->group(function (): void {
            Route::get('settings/{group}', [SettingController::class, 'show']);
            Route::put('settings/{group}', [SettingController::class, 'update']);

            Route::get('custom-fields', [SettingController::class, 'customFields']);
            Route::post('custom-fields', [SettingController::class, 'storeCustomField']);
            Route::put('custom-fields/{customField}', [SettingController::class, 'updateCustomField']);
            Route::delete('custom-fields/{customField}', [SettingController::class, 'destroyCustomField']);

            Route::get('ban-ips', [SettingController::class, 'banIps']);
            Route::post('ban-ips', [SettingController::class, 'storeBanIp']);
            Route::put('ban-ips/{banIp}', [SettingController::class, 'updateBanIp']);
            Route::delete('ban-ips/{banIp}', [SettingController::class, 'destroyBanIp']);

            Route::get('languages', [SettingController::class, 'languages']);
            Route::post('languages', [SettingController::class, 'storeLanguage']);
            Route::delete('languages/{language}', [SettingController::class, 'destroyLanguage']);
            Route::put('languages/{language}', [SettingController::class, 'updateLanguage']);
            Route::get('languages/{language}/translations', [SettingController::class, 'translations']);
            Route::put('languages/{language}/translations', [SettingController::class, 'updateTranslations']);

            Route::get('gateways/{group}', [GatewaySettingController::class, 'index']);
            Route::post('gateways/{group}', [GatewaySettingController::class, 'store']);
            Route::put('gateways/{group}/{itemId}', [GatewaySettingController::class, 'update']);
            Route::delete('gateways/{group}/{itemId}', [GatewaySettingController::class, 'destroy']);
        });

        // Wilayah (provinsi/kabupaten/kecamatan) — referensi alamat.
        Route::middleware('permission:peoples.manage')->group(function (): void {
            Route::get('provinces', [RegionController::class, 'provinces']);
            Route::post('provinces', [RegionController::class, 'storeProvince']);
            Route::delete('provinces/{province}', [RegionController::class, 'destroyProvince']);

            Route::get('regencies', [RegionController::class, 'regencies']);
            Route::post('regencies', [RegionController::class, 'storeRegency']);
            Route::delete('regencies/{regency}', [RegionController::class, 'destroyRegency']);

            Route::get('districts', [RegionController::class, 'districts']);
            Route::post('districts', [RegionController::class, 'storeDistrict']);
            Route::delete('districts/{district}', [RegionController::class, 'destroyDistrict']);
        });

        // Dashboard & laporan.
        Route::middleware('permission:reports.view')->group(function (): void {
            Route::get('dashboard', [DashboardController::class, 'index']);
            Route::get('dashboard/admin', [DashboardController::class, 'adminStats']);
            Route::get('dashboard/sales', [DashboardController::class, 'salesStats']);
            Route::get('dashboard/analytics', [DashboardController::class, 'analyticsStats']);
            Route::get('reports/{type}', [ReportController::class, 'show']);
            Route::get('reports/{type}/export', [ReportController::class, 'export']);
        });

        // Paper Shop & Machines (self + vendor).
        Route::middleware('permission:products.manage')->group(function (): void {
            Route::get('paper-groups', [PaperController::class, 'groups']);
            Route::post('paper-groups', [PaperController::class, 'storeGroup']);
            Route::put('paper-groups/{paperGroup}', [PaperController::class, 'updateGroup']);
            Route::delete('paper-groups/{paperGroup}', [PaperController::class, 'destroyGroup']);

            Route::get('paper-items', [PaperController::class, 'items']);
            Route::post('paper-items', [PaperController::class, 'storeItem']);
            Route::put('paper-items/{paperItem}', [PaperController::class, 'updateItem']);
            Route::delete('paper-items/{paperItem}', [PaperController::class, 'destroyItem']);

            Route::get('paper-sizes', [PaperController::class, 'sizes']);
            Route::post('paper-sizes', [PaperController::class, 'storeSize']);
            Route::put('paper-sizes/{paperSize}', [PaperController::class, 'updateSize']);
            Route::delete('paper-sizes/{paperSize}', [PaperController::class, 'destroySize']);

            Route::get('paper-prices', [PaperController::class, 'prices']);
            Route::post('paper-prices', [PaperController::class, 'storePrice']);
            Route::put('paper-prices/{paperPrice}', [PaperController::class, 'updatePrice']);
            Route::delete('paper-prices/{paperPrice}', [PaperController::class, 'destroyPrice']);

            Route::get('printing-machines', [PaperController::class, 'machines']);
            Route::post('printing-machines', [PaperController::class, 'storeMachine']);
            Route::put('printing-machines/{printingMachine}', [PaperController::class, 'updateMachine']);
            Route::delete('printing-machines/{printingMachine}', [PaperController::class, 'destroyMachine']);

            Route::get('die-cutting-blades', [PaperController::class, 'blades']);
            Route::post('die-cutting-blades', [PaperController::class, 'storeBlade']);
            Route::put('die-cutting-blades/{dieCuttingBlade}', [PaperController::class, 'updateBlade']);
            Route::delete('die-cutting-blades/{dieCuttingBlade}', [PaperController::class, 'destroyBlade']);

            // Calculator.
            Route::get('calculator-components/{kind}', [CalculatorController::class, 'components']);
            Route::post('calculator-components/{kind}', [CalculatorController::class, 'storeComponent']);
            Route::put('calculator-components/{kind}/{calculatorComponent}', [CalculatorController::class, 'updateComponent']);
            Route::delete('calculator-components/{kind}/{calculatorComponent}', [CalculatorController::class, 'destroyComponent']);

            Route::get('profit-tiers', [CalculatorController::class, 'profitTiers']);
            Route::post('profit-tiers', [CalculatorController::class, 'storeProfitTier']);
            Route::put('profit-tiers/{profitTier}', [CalculatorController::class, 'updateProfitTier']);
            Route::delete('profit-tiers/{profitTier}', [CalculatorController::class, 'destroyProfitTier']);

            Route::get('calculator/partners', [CalculatorController::class, 'partners']);
            Route::post('calculator/partners', [CalculatorController::class, 'storePartner']);
            Route::put('calculator/partners/{partner}', [CalculatorController::class, 'updatePartner']);
            Route::delete('calculator/partners/{partner}', [CalculatorController::class, 'destroyPartner']);
            Route::post('calculator/partners/{partner}/moderate', [CalculatorController::class, 'moderatePartner']);

            Route::get('calculator/listings', [CalculatorController::class, 'listings']);
            Route::post('calculator/listings/{listing}/freeze', [CalculatorController::class, 'freezeListing']);
            Route::delete('calculator/listings/{listing}', [CalculatorController::class, 'destroyListing']);
            Route::get('calculator/moderation-history', [CalculatorController::class, 'moderationHistory']);
            Route::get('calculator/dashboard', [CalculatorController::class, 'dashboard']);
        });

        // Promo: banner, coupon, voucher, diskon.
        Route::middleware('permission:sales.manage')->group(function (): void {
            Route::get('banners', [PromoController::class, 'banners']);
            Route::post('banners', [PromoController::class, 'storeBanner']);
            Route::put('banners/{banner}', [PromoController::class, 'updateBanner']);
            Route::delete('banners/{banner}', [PromoController::class, 'destroyBanner']);

            Route::get('coupons', [PromoController::class, 'coupons']);
            Route::post('coupons', [PromoController::class, 'storeCoupon']);
            Route::put('coupons/{coupon}', [PromoController::class, 'updateCoupon']);
            Route::delete('coupons/{coupon}', [PromoController::class, 'destroyCoupon']);

            Route::get('vouchers', [PromoController::class, 'vouchers']);
            Route::post('vouchers', [PromoController::class, 'storeVoucher']);
            Route::put('vouchers/{voucher}', [PromoController::class, 'updateVoucher']);
            Route::delete('vouchers/{voucher}', [PromoController::class, 'destroyVoucher']);

            Route::get('discount-plans', [PromoController::class, 'discountPlans']);
            Route::post('discount-plans', [PromoController::class, 'storeDiscountPlan']);
            Route::put('discount-plans/{discountPlan}', [PromoController::class, 'updateDiscountPlan']);
            Route::delete('discount-plans/{discountPlan}', [PromoController::class, 'destroyDiscountPlan']);

            Route::get('pos/products', [PosController::class, 'products']);
            Route::get('pos/held-orders', [PosController::class, 'heldOrders']);
            Route::post('pos/held-orders', [PosController::class, 'storeHeldOrder']);
            Route::delete('pos/held-orders/{heldOrder}', [PosController::class, 'destroyHeldOrder']);

            Route::get('discounts', [PromoController::class, 'discounts']);
            Route::post('discounts', [PromoController::class, 'storeDiscount']);
            Route::put('discounts/{discount}', [PromoController::class, 'updateDiscount']);
            Route::delete('discounts/{discount}', [PromoController::class, 'destroyDiscount']);
        });

        // Webstore monitoring.
        Route::middleware('permission:orders.manage')->group(function (): void {
            Route::get('webstore/carts', [WebstoreController::class, 'carts']);
            Route::get('webstore/checkout', [WebstoreController::class, 'checkouts']);
            Route::get('webstore/wishlist', [WebstoreController::class, 'wishlist']);
            Route::delete('webstore/wishlist/{wishlist}', [WebstoreController::class, 'destroyWishlist']);

            Route::get('webstore/reviews/stats', [WebstoreController::class, 'reviewStats']);
            Route::get('webstore/reviews', [WebstoreController::class, 'reviews']);
            Route::put('webstore/reviews/{review}', [WebstoreController::class, 'updateReview']);
            Route::delete('webstore/reviews/{review}', [WebstoreController::class, 'destroyReview']);

            Route::get('webstore/contact-forms/stats', [WebstoreController::class, 'contactStats']);
            Route::get('webstore/contact-forms', [WebstoreController::class, 'contactForms']);
            Route::put('webstore/contact-forms/{contactMessage}', [WebstoreController::class, 'updateContactForm']);
            Route::delete('webstore/contact-forms/{contactMessage}', [WebstoreController::class, 'destroyContactForm']);

            Route::get('webstore/support-tickets/stats', [WebstoreController::class, 'supportTicketStats']);
            Route::get('webstore/support-tickets', [WebstoreController::class, 'supportTickets']);
            Route::post('webstore/support-tickets', [WebstoreController::class, 'storeSupportTicket']);
            Route::get('webstore/support-tickets/{supportTicket}', [WebstoreController::class, 'showSupportTicket']);
            Route::put('webstore/support-tickets/{supportTicket}', [WebstoreController::class, 'updateSupportTicket']);
            Route::delete('webstore/support-tickets/{supportTicket}', [WebstoreController::class, 'destroySupportTicket']);

            Route::get('subscriptions', [SubscriptionController::class, 'index']);
            Route::put('subscriptions/{subscription}', [SubscriptionController::class, 'update']);
        });
    });
});
