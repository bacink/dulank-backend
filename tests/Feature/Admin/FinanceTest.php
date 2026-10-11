<?php

use App\Models\BankAccount;
use App\Models\BankAccountType;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\User;

function financeToken(): string
{
    return adminToken(adminWithPermissions('finance.manage'));
}

function makeAccount(string $name = 'Kas Besar', int $opening = 1000000): BankAccount
{
    return BankAccount::query()->create([
        'account_name' => $name,
        'bank_name' => 'BCA',
        'account_no' => '123456',
        'opening_balance' => $opening,
        'status' => 'Active',
    ]);
}

it('manages bank account types and accounts with computed balance', function () {
    $token = financeToken();

    $this->withToken($token)->postJson('/api/v1/admin/bank-account-types', ['name' => 'Kas'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Kas');

    $typeId = BankAccountType::query()->firstOrFail()->id;

    $created = $this->withToken($token)->postJson('/api/v1/admin/bank-accounts', [
        'accountTypeId' => $typeId,
        'accountName' => 'Kas Besar',
        'bankName' => 'BCA',
        'accountNo' => '123456',
        'openingBalance' => 1000000,
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.currentBalance', 1000000)
        ->assertJsonPath('data.accountTypeName', 'Kas')
        ->assertJsonStructure(['data' => ['id', 'accountName', 'bankName', 'openingBalance', 'currentBalance', 'status']]);

    $this->withToken($token)->getJson('/api/v1/admin/bank-accounts')
        ->assertOk()
        ->assertJsonPath('data.0.currentBalance', 1000000);
});

it('moves balances with a money transfer and writes two ledger entries', function () {
    $from = makeAccount('Kas Besar', 1000000);
    $to = makeAccount('Bank Mandiri', 500000);

    $this->withToken(financeToken())->postJson('/api/v1/admin/money-transfers', [
        'fromAccountId' => $from->id,
        'toAccountId' => $to->id,
        'amount' => 300000,
        'description' => 'Setor kas ke bank',
    ])
        ->assertCreated()
        ->assertJsonPath('data.fromAccount', 'Kas Besar')
        ->assertJsonPath('data.toAccount', 'Bank Mandiri')
        ->assertJsonStructure(['data' => ['id', 'no', 'amount', 'date', 'fromAccount', 'toAccount']]);

    $statement = $this->withToken(financeToken())
        ->getJson("/api/v1/admin/bank-accounts/{$from->id}/statement");

    $statement->assertOk()
        ->assertJsonPath('data.totalDebit', 300000)
        ->assertJsonPath('data.currentBalance', 700000);

    $this->withToken(financeToken())
        ->getJson("/api/v1/admin/bank-accounts/{$to->id}/statement")
        ->assertJsonPath('data.totalCredit', 300000)
        ->assertJsonPath('data.currentBalance', 800000);
});

it('records income to the ledger and reverses it on cancel', function () {
    $account = makeAccount();

    $token = financeToken();

    $created = $this->withToken($token)->postJson('/api/v1/admin/incomes', [
        'name' => 'Penjualan Tunai',
        'category' => 'Penjualan',
        'amount' => 250000,
        'bankAccountId' => $account->id,
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.category', 'Penjualan')
        ->assertJsonPath('data.amount', 250000)
        ->assertJsonStructure(['data' => ['id', 'no', 'date', 'name', 'category', 'amount', 'bankAccount']]);

    expect(BankAccount::query()->find($account->id))->not->toBeNull();
    $this->withToken($token)->getJson("/api/v1/admin/bank-accounts/{$account->id}/statement")
        ->assertJsonPath('data.currentBalance', 1250000);

    $incomeId = $created->json('data.id');

    $this->withToken($token)->postJson("/api/v1/admin/incomes/{$incomeId}/cancel")
        ->assertOk()
        ->assertJsonPath('data.isCancelled', true);

    $this->withToken($token)->getJson("/api/v1/admin/bank-accounts/{$account->id}/statement")
        ->assertJsonPath('data.currentBalance', 1000000);
});

it('records expense payments with ledger debit and recalculation', function () {
    $account = makeAccount();

    $token = financeToken();

    $created = $this->withToken($token)->postJson('/api/v1/admin/expenses', [
        'name' => 'Bayar Listrik',
        'category' => 'Operasional',
        'amount' => 500000,
        'bankAccountId' => $account->id,
        'paid' => 200000,
        'paymentMethod' => 'Transfer',
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.status', 'Partial')
        ->assertJsonPath('data.paid', 200000)
        ->assertJsonPath('data.due', 300000);

    $expenseId = $created->json('data.id');

    $this->withToken($token)->postJson("/api/v1/admin/expenses/{$expenseId}/payments", ['amount' => 300000])
        ->assertCreated()
        ->assertJsonPath('data.status', 'Paid')
        ->assertJsonPath('data.due', 0);

    $this->withToken($token)->getJson("/api/v1/admin/bank-accounts/{$account->id}/statement")
        ->assertJsonPath('data.totalDebit', 500000)
        ->assertJsonPath('data.currentBalance', 500000);

    expect(Payment::query()->where('payable_type', Expense::class)->count())->toBe(2);
});

it('manages cash advances with installments', function () {
    $token = financeToken();

    $created = $this->withToken($token)->postJson('/api/v1/admin/cash-advances', [
        'employeeName' => 'Budi Santoso',
        'totalCash' => 1200000,
        'installmentCount' => 4,
        'period' => 'Monthly',
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.employee', 'Budi Santoso')
        ->assertJsonPath('data.installmentAmount', 300000)
        ->assertJsonPath('data.outstanding', 1200000)
        ->assertJsonPath('data.status', 'On');

    $id = $created->json('data.id');

    $this->withToken($token)->postJson("/api/v1/admin/cash-advances/{$id}/payments", ['amount' => 300000])
        ->assertCreated()
        ->assertJsonPath('data.totalPaid', 300000)
        ->assertJsonPath('data.outstanding', 900000)
        ->assertJsonPath('data.tenorRemain', 3);

    $this->withToken($token)->postJson("/api/v1/admin/cash-advances/{$id}/payments", ['amount' => 5000000])
        ->assertStatus(422);
});

it('manages input and output tax documents with computed views', function () {
    $token = financeToken();

    $purchase = Purchase::query()->create([
        'no_purchase' => 'PR-TAX-1',
        'date' => now()->toDateString(),
        'amount' => 1000000,
        'tax' => 110000,
        'due' => 1110000,
        'status' => 'Ordered',
        'payment_status' => 'Unpaid',
    ]);

    $this->withToken($token)->postJson('/api/v1/admin/input-taxes', [
        'purchaseId' => $purchase->id,
        'invoiceDate' => now()->toDateString(),
        'fakturNo' => '010.000-26.00000001',
        'credited' => 'Yes',
    ])
        ->assertCreated()
        ->assertJsonPath('data.fakturNo', '010.000-26.00000001')
        ->assertJsonPath('data.purchaseNo', 'PR-TAX-1')
        ->assertJsonPath('data.dpp', 1000000)
        ->assertJsonPath('data.vat', 110000);

    $order = Order::factory()->create([
        'number' => 'SO-TAX-1',
        'sub_total' => 500000,
        'tax' => 55000,
        'total' => 555000,
        'contact' => ['name' => 'PT Pajak'],
    ]);

    $this->withToken($token)->postJson('/api/v1/admin/output-taxes', [
        'saleId' => 'SO-TAX-1',
        'etaxDate' => now()->toDateString(),
        'etaxNumber' => '010.000-26.99999999',
        'txCode' => '01',
        'status' => 'Issued',
    ])
        ->assertCreated()
        ->assertJsonPath('data.salesNo', 'SO-TAX-1')
        ->assertJsonPath('data.customerName', 'PT Pajak')
        ->assertJsonPath('data.dpp', 500000)
        ->assertJsonPath('data.vat', 55000)
        ->assertJsonPath('data.total', 555000);
});

it('tracks customer account entries and balance from checkout and payment', function () {
    $user = User::factory()->create();
    $customer = Customer::query()->create([
        'user_id' => $user->id,
        'code' => 'CUS-TEST-1',
        'channel' => 'Online',
    ]);

    CustomerAccountEntry::query()->create([
        'customer_id' => $customer->id,
        'source_type' => 'sale',
        'source_id' => 1,
        'amount' => 1000000,
        'occurred_at' => now(),
    ]);

    CustomerAccountEntry::query()->create([
        'customer_id' => $customer->id,
        'source_type' => 'payment',
        'source_id' => 1,
        'amount' => 400000,
        'occurred_at' => now(),
    ]);

    $token = financeToken();

    $this->withToken($token)->getJson('/api/v1/admin/finance/customer-entries?customerId='.$customer->id)
        ->assertOk()
        ->assertJsonPath('meta.total', 2);

    $this->withToken($token)->getJson("/api/v1/admin/finance/customer-entries/{$customer->id}/balance")
        ->assertOk()
        ->assertJsonPath('data.totalSales', 1000000)
        ->assertJsonPath('data.totalPayments', 400000)
        ->assertJsonPath('data.balance', 600000);
});

it('summarizes finance and requires the finance permission', function () {
    makeAccount();

    $token = financeToken();

    $this->withToken($token)->getJson('/api/v1/admin/finance/summary')
        ->assertOk()
        ->assertJsonStructure(['data' => ['totalBalance', 'accounts', 'totalReceivable', 'totalPayable', 'totalIncome', 'totalExpense']]);

    $this->withToken($token)->getJson('/api/v1/admin/finance/cash-flow')
        ->assertOk()
        ->assertJsonStructure(['data' => ['totalInflow', 'totalOutflow', 'net', 'breakdown']]);

    $this->withToken(financeToken())->getJson('/api/v1/admin/incomes')->assertOk();

    $this->withToken(adminToken(User::factory()->admin()->create()))
        ->getJson('/api/v1/admin/incomes')
        ->assertStatus(403);
});

it('manages income and expense categories with update and delete', function () {
    $token = adminToken(adminWithPermissions('finance.manage'));

    $this->withToken($token)->postJson('/api/v1/admin/income-categories', [
        'name' => 'Sumbangan', 'description' => 'Donasi', 'status' => 'Active',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Sumbangan');

    $this->withToken($token)->getJson('/api/v1/admin/income-categories')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'description', 'status', 'created']]]);

    $incomeCategory = IncomeCategory::query()->where('name', 'Sumbangan')->firstOrFail();

    $this->withToken($token)->putJson("/api/v1/admin/income-categories/{$incomeCategory->id}", [
        'name' => 'Sumbangan Lain', 'status' => 'Inactive',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Sumbangan Lain');

    $this->withToken($token)->deleteJson("/api/v1/admin/income-categories/{$incomeCategory->id}")
        ->assertOk()
        ->assertJsonPath('data.removed', true);

    $this->assertDatabaseMissing('income_categories', ['name' => 'Sumbangan Lain']);

    $this->withToken($token)->postJson('/api/v1/admin/expense-categories', [
        'categoryName' => 'Operasional', 'status' => 'Active',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Operasional');

    $this->withToken($token)->getJson('/api/v1/admin/expense-categories')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'categoryName', 'description', 'date', 'status']]]);

    $expenseCategory = ExpenseCategory::query()->where('name', 'Operasional')->firstOrFail();

    $this->withToken($token)->putJson("/api/v1/admin/expense-categories/{$expenseCategory->id}", [
        'categoryName' => 'Operasional Lain', 'status' => 'Inactive',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Operasional Lain');

    $this->withToken($token)->deleteJson("/api/v1/admin/expense-categories/{$expenseCategory->id}")
        ->assertOk()
        ->assertJsonPath('data.removed', true);

    $this->assertDatabaseMissing('expense_categories', ['name' => 'Operasional Lain']);
});
