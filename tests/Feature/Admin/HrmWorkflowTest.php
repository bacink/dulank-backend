<?php

use App\Models\Employee;
use App\Models\FlowCategory;
use App\Models\FlowName;
use App\Models\JobBranch;
use App\Models\JobOrder;
use App\Models\MyIncentive;
use App\Models\Payslip;
use App\Models\User;

it('creates departments, designations and employees', function () {
    $token = adminToken(adminWithPermissions('hrm.manage'));

    $this->withToken($token)->postJson('/api/v1/admin/departments', ['name' => 'Produksi'])->assertCreated();
    $this->withToken($token)->postJson('/api/v1/admin/designations', ['name' => 'Operator'])->assertCreated();

    $created = $this->withToken($token)->postJson('/api/v1/admin/employees', [
        'name' => 'Budi Santoso',
        'department' => 'Produksi',
        'designation' => 'Operator',
        'phone' => '+62811',
        'email' => 'budi@example.test',
        'joinChannel' => 'Walk-in',
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.name', 'Budi Santoso')
        ->assertJsonPath('data.department', 'Produksi')
        ->assertJsonPath('data.designation', 'Operator')
        ->assertJsonStructure(['data' => ['id', 'employeeCode', 'name', 'department', 'designation', 'status', 'joinDate']]);

    expect($created->json('data.employeeCode'))->toStartWith('EMP-');

    $this->withToken($token)->getJson('/api/v1/admin/employees?search=Budi')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->withToken($token)->getJson('/api/v1/admin/departments')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Produksi', 'employeeCount' => 1]);
});

it('stores salary with allowances, generates and pays a payslip', function () {
    $token = adminToken(adminWithPermissions('hrm.manage'));

    $employee = Employee::query()->create(['employee_code' => 'EMP-9001', 'name' => 'Siti Aminah', 'status' => 'Active']);

    $this->withToken($token)->postJson('/api/v1/admin/employee-salaries', [
        'employeeId' => $employee->id,
        'name' => 'Gaji Pokok',
        'salary' => 4000000,
        'allowances' => [
            ['name' => 'Transport', 'amount' => 500000],
            ['name' => 'Makan', 'amount' => 300000],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.allowanceTotal', 800000);

    $payslip = $this->withToken($token)->postJson('/api/v1/admin/payslips/generate', [
        'employeeId' => $employee->id,
        'period' => '2026-10',
        'overtime' => 250000,
        'deduction' => 100000,
    ]);

    $payslip->assertCreated()
        ->assertJsonPath('data.salaryRate', 4000000)
        ->assertJsonPath('data.allowance', 800000)
        ->assertJsonPath('data.total', 4950000)
        ->assertJsonPath('data.status', 'Unpaid')
        ->assertJsonStructure(['data' => ['slipNo', 'period', 'items']]);

    $slipNo = $payslip->json('data.slipNo');

    expect($slipNo)->toStartWith('SLP-');

    $this->withToken($token)->postJson("/api/v1/admin/payslips/{$slipNo}/pay", ['method' => 'Transfer'])
        ->assertOk()
        ->assertJsonPath('data.status', 'Paid');

    expect(Payslip::query()->where('slip_no', $slipNo)->value('paid_date'))->not->toBeNull();

    // Payslip yang sudah dibayar tidak bisa dibayar ulang.
    $this->withToken($token)->postJson("/api/v1/admin/payslips/{$slipNo}/pay")
        ->assertStatus(409);
});

it('manages flows and work flows with steps', function () {
    $token = adminToken(adminWithPermissions('workflow.manage'));

    $this->withToken($token)->postJson('/api/v1/admin/flow-categories', ['name' => 'Cetak', 'code' => 'CTK'])->assertCreated();

    $flowName = $this->withToken($token)->postJson('/api/v1/admin/flow-names', [
        'category' => 'Cetak',
        'name' => 'Cetak Offset',
        'incentive' => 500,
        'unit' => 'lembar',
        'flowType' => 'Inhouse',
        'assignees' => ['Budi Santoso', 'Siti Aminah'],
    ]);

    $flowName->assertCreated()
        ->assertJsonPath('data.name', 'Cetak Offset')
        ->assertJsonPath('data.incentive', 500)
        ->assertJsonPath('data.assignees.0', 'Budi Santoso');

    $this->withToken($token)->postJson('/api/v1/admin/flow-templates', ['name' => 'Template Offset'])->assertCreated();

    $this->withToken($token)->postJson('/api/v1/admin/work-flows', [
        'category' => 'Cetak',
        'workflowSteps' => 'Cetak -> Potong -> Finishing',
        'steps' => [
            ['name' => 'Cetak', 'category' => 'Cetak'],
            ['name' => 'Potong', 'category' => 'Finishing'],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.no', fn ($value) => str_starts_with($value, 'WF-'));

    $this->withToken($token)->getJson('/api/v1/admin/work-flows')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.steps.0.name', 'Cetak');
});

it('creates job orders with steps and updates the status', function () {
    $token = adminToken(adminWithPermissions('workflow.manage'));

    $flowName = createFlowName();

    $created = $this->withToken($token)->postJson('/api/v1/admin/job-orders', [
        'flowNameId' => $flowName->id,
        'customerName' => 'PT. Maju Jaya',
        'productName' => 'Brosur A4',
        'jobTitle' => 'Cetak Brosur',
        'qty' => 1000,
        'priority' => 'High',
        'workflowCategory' => 'Cetak',
        'steps' => [
            ['name' => 'Cetak', 'status' => 'active'],
            ['name' => 'Potong', 'status' => 'pending'],
        ],
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.status', 'Waiting')
        ->assertJsonPath('data.customer', 'PT. Maju Jaya')
        ->assertJsonPath('data.steps.0.name', 'Cetak');

    $jobNo = $created->json('data.no');

    expect($jobNo)->toStartWith('JO-');

    $this->withToken($token)->postJson("/api/v1/admin/job-orders/{$created->json('data.id')}/status", ['status' => 'On Process'])
        ->assertOk()
        ->assertJsonPath('data.status', 'On Process');
});

it('completes a job branch and generates incentives per assignee', function () {
    $token = adminToken(adminWithPermissions('workflow.manage'));

    $flowName = createFlowName();
    $order = createJobOrder($flowName);

    $branch = JobBranch::query()->create([
        'no' => 'JB-001',
        'job_order_id' => $order->id,
        'flow_name' => 'Cetak Offset',
        'customer' => 'PT. Maju Jaya',
        'product' => 'Brosur A4',
        'job_title' => 'Cetak Brosur',
        'qty' => 1000,
        'date' => now()->toDateString(),
        'assignees' => ['Budi Santoso', 'Siti Aminah'],
    ]);

    $completed = $this->withToken($token)->postJson("/api/v1/admin/job-branches/{$branch->id}/complete");

    $completed->assertOk()
        ->assertJsonPath('data.status', 'Completed')
        ->assertJsonPath('data.histories.0.flowName', 'Cetak Offset')
        ->assertJsonPath('data.incentives.0.amount', 500000); // 500 × 1000

    expect(MyIncentive::query()->count())->toBe(2)
        ->and(MyIncentive::query()->where('employee_name', 'Budi Santoso')->value('amount'))->toBe(500000);

    // Selesai dua kali ditolak.
    $this->withToken($token)->postJson("/api/v1/admin/job-branches/{$branch->id}/complete")->assertStatus(409);

    $this->withToken($token)->getJson('/api/v1/admin/my-incentives?status=Pending')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});

it('requires hrm and workflow permissions', function () {
    $admin = User::factory()->admin()->create();

    $this->withToken(adminToken($admin))->getJson('/api/v1/admin/employees')->assertStatus(403);
    $this->withToken(adminToken($admin))->getJson('/api/v1/admin/job-orders')->assertStatus(403);

    $this->withToken(adminToken(adminWithPermissions('hrm.manage')))->getJson('/api/v1/admin/employees')->assertOk();
    $this->withToken(adminToken(adminWithPermissions('workflow.manage')))->getJson('/api/v1/admin/job-orders')->assertOk();
});

function createFlowName(): FlowName
{
    $category = FlowCategory::query()->firstOrCreate(['name' => 'Cetak']);

    return FlowName::query()->create([
        'flow_category_id' => $category->id,
        'name' => 'Cetak Offset',
        'incentive' => 500,
        'unit' => 'lembar',
        'flow_type' => 'Inhouse',
        'assignees' => ['Budi Santoso', 'Siti Aminah'],
    ]);
}

function createJobOrder(FlowName $flowName): JobOrder
{
    return JobOrder::query()->create([
        'no' => 'JO-8001',
        'flow_name_id' => $flowName->id,
        'customer_name' => 'PT. Maju Jaya',
        'product_name' => 'Brosur A4',
        'job_title' => 'Cetak Brosur',
        'qty' => 1000,
        'status' => 'On Process',
    ]);
}
