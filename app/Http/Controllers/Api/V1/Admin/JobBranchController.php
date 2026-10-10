<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\JobBranch;
use App\Models\JobOrder;
use App\Models\JobProgress;
use App\Models\MyIncentive;
use App\Services\IncentiveService;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobBranchController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = JobBranch::query()->with(['jobOrder', 'histories', 'incentives']);

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $query) => $query
                ->where('no', 'like', $term)
                ->orWhere('job_title', 'like', $term)
                ->orWhere('customer', 'like', $term)
                ->orWhere('flow_name', 'like', $term));
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $branches = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($branches->map(fn (JobBranch $branch) => $this->payload($branch))->values(), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $branch = JobBranch::query()->create([
            'no' => DocumentNumber::next('job_branch', JobBranch::query(), 'no', 3),
            'job_order_id' => $this->jobOrderId($validated['jobOrderNo']),
            'order_no' => $validated['orderNo'] ?? null,
            'branch' => $validated['branch'] ?? null,
            'customer' => $validated['customer'] ?? null,
            'product' => $validated['product'] ?? null,
            'flow_name' => $validated['flowName'] ?? null,
            'priority' => $validated['priority'] ?? 'Medium',
            'status' => $validated['status'] ?? 'Waiting',
            'job_title' => $validated['jobTitle'] ?? null,
            'description' => $validated['description'] ?? null,
            'qty' => (float) ($validated['qty'] ?? 0),
            'date' => $request->date('date')?->toDateString() ?? now()->toDateString(),
            'assignees' => $validated['assignees'] ?? null,
            'incentive_amount' => (int) ($validated['incentiveAmount'] ?? 0),
            'incentive_unit' => $validated['incentiveUnit'] ?? null,
        ]);

        return ApiResponse::success($this->payload($branch->load(['jobOrder', 'histories', 'incentives'])), [], 201);
    }

    public function show(JobBranch $jobBranch): JsonResponse
    {
        return ApiResponse::success($this->payload($jobBranch->load(['jobOrder', 'histories', 'incentives'])));
    }

    public function update(Request $request, JobBranch $jobBranch): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $jobBranch->forceFill([
            'job_order_id' => $this->jobOrderId($validated['jobOrderNo']),
            'order_no' => $validated['orderNo'] ?? $jobBranch->order_no,
            'branch' => $validated['branch'] ?? null,
            'customer' => $validated['customer'] ?? null,
            'product' => $validated['product'] ?? null,
            'flow_name' => $validated['flowName'] ?? null,
            'priority' => $validated['priority'] ?? $jobBranch->priority,
            'status' => $validated['status'] ?? $jobBranch->status,
            'job_title' => $validated['jobTitle'] ?? null,
            'description' => $validated['description'] ?? null,
            'qty' => (float) ($validated['qty'] ?? 0),
            'date' => $request->date('date')?->toDateString() ?? $jobBranch->date?->toDateString(),
            'assignees' => $validated['assignees'] ?? null,
            'incentive_amount' => (int) ($validated['incentiveAmount'] ?? 0),
            'incentive_unit' => $validated['incentiveUnit'] ?? null,
        ])->save();

        return ApiResponse::success($this->payload($jobBranch->refresh()->load(['jobOrder', 'histories', 'incentives'])));
    }

    public function destroy(JobBranch $jobBranch): JsonResponse
    {
        $jobBranch->delete();

        return ApiResponse::success(['id' => (string) $jobBranch->id, 'removed' => true]);
    }

    /**
     * Selesaikan cabang: tulis riwayat + generate insentif per assignee.
     */
    public function complete(Request $request, JobBranch $jobBranch, IncentiveService $incentives): JsonResponse
    {
        if ($jobBranch->status === 'Completed') {
            return ApiResponse::error('Cabang job sudah selesai.', 409);
        }

        $branch = $incentives->completeBranch($jobBranch, $request->user());

        return ApiResponse::success($this->payload($branch));
    }

    public function jobProgress(AdminIndexRequest $request): JsonResponse
    {
        $query = JobProgress::query()->with('jobOrder');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('process', 'like', '%'.$search.'%');
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $rows = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($rows->map(fn (JobProgress $progress) => [
            'id' => (string) $progress->id,
            'jobOrderNo' => $progress->jobOrder?->no,
            'progressCode' => $progress->progress_code,
            'product' => $progress->product,
            'description' => $progress->description,
            'process' => $progress->process,
            'completedBy' => $progress->completed_by,
            'time' => $progress->time?->toIso8601String(),
            'note' => $progress->note,
            'isCompleted' => $progress->is_completed,
        ])->values(), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function storeJobProgress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jobOrderNo' => ['sometimes', 'nullable', 'string', 'max:60'],
            'progressCode' => ['sometimes', 'nullable', 'string', 'max:60'],
            'product' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'process' => ['sometimes', 'nullable', 'string', 'max:255'],
            'completedBy' => ['sometimes', 'nullable', 'string', 'max:255'],
            'time' => ['sometimes', 'nullable', 'date'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'isCompleted' => ['sometimes', 'boolean'],
        ]);

        $progress = JobProgress::query()->create([
            'job_order_id' => isset($validated['jobOrderNo'])
                ? JobOrder::query()->where('no', $validated['jobOrderNo'])->value('id')
                : null,
            'progress_code' => $validated['progressCode'] ?? null,
            'product' => $validated['product'] ?? null,
            'description' => $validated['description'] ?? null,
            'process' => $validated['process'] ?? null,
            'completed_by' => $validated['completedBy'] ?? null,
            'time' => $request->date('time') ?? now(),
            'note' => $validated['note'] ?? null,
            'is_completed' => (bool) ($validated['isCompleted'] ?? false),
        ]);

        return ApiResponse::success(['id' => (string) $progress->id, 'process' => $progress->process], [], 201);
    }

    public function myIncentives(AdminIndexRequest $request): JsonResponse
    {
        $query = MyIncentive::query()->with('employee');

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $employeeId = trim((string) $request->input('employeeId', ''));
        if ($employeeId !== '') {
            $query->where('employee_id', (int) $employeeId);
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $incentives = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($incentives->map(fn (MyIncentive $incentive) => [
            'id' => (string) $incentive->id,
            'employeeId' => $incentive->employee_id !== null ? (string) $incentive->employee_id : null,
            'employee' => $incentive->employee_name,
            'jobOrderId' => $incentive->job_order_id !== null ? (string) $incentive->job_order_id : null,
            'date' => $incentive->date?->toDateString(),
            'jobTitle' => $incentive->job_title,
            'flowName' => $incentive->flow_name,
            'incentive' => (int) $incentive->incentive,
            'unit' => $incentive->unit,
            'qty' => (float) $incentive->qty,
            'amount' => (int) $incentive->amount,
            'status' => $incentive->status,
        ])->values(), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'jobOrderNo' => ['required', 'string', 'max:60'],
            'orderNo' => ['sometimes', 'nullable', 'string', 'max:60'],
            'branch' => ['sometimes', 'nullable', 'string', 'max:255'],
            'customer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'product' => ['sometimes', 'nullable', 'string', 'max:255'],
            'flowName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'priority' => ['sometimes', 'nullable', 'in:High,Medium,Low'],
            'status' => ['sometimes', 'nullable', 'in:Waiting,On Process,Completed'],
            'jobTitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'qty' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'date' => ['sometimes', 'nullable', 'date'],
            'assignees' => ['sometimes', 'nullable', 'array'],
            'incentiveAmount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'incentiveUnit' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);
    }

    private function jobOrderId(string $no): int
    {
        return (int) JobOrder::query()->where('no', $no)->value('id')
            ?: (int) JobOrder::query()->create([
                'no' => DocumentNumber::next('job', JobOrder::query(), 'no', 3),
                'date' => now()->toDateString(),
                'status' => 'Waiting',
            ])->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(JobBranch $branch): array
    {
        return [
            'id' => (string) $branch->id,
            'no' => $branch->no,
            'jobOrderNo' => $branch->jobOrder?->no,
            'orderNo' => $branch->order_no,
            'branch' => $branch->branch,
            'customer' => $branch->customer,
            'product' => $branch->product,
            'flowName' => $branch->flow_name,
            'priority' => $branch->priority,
            'status' => $branch->status,
            'jobTitle' => $branch->job_title,
            'description' => $branch->description,
            'qty' => (float) $branch->qty,
            'date' => $branch->date?->toDateString(),
            'dateFinish' => $branch->date_finish?->toDateString(),
            'assignees' => $branch->assignees ?? [],
            'incentiveAmount' => (int) $branch->incentive_amount,
            'incentiveUnit' => $branch->incentive_unit,
            'histories' => $branch->histories->map(fn ($history) => [
                'id' => (string) $history->id,
                'date' => $history->date?->toDateString(),
                'branch' => $history->branch,
                'customer' => $history->customer,
                'flowName' => $history->flow_name,
                'dateFinish' => $history->date_finish?->toDateString(),
            ])->values(),
            'incentives' => $branch->incentives->map(fn (MyIncentive $incentive) => [
                'id' => (string) $incentive->id,
                'employee' => $incentive->employee_name,
                'amount' => (int) $incentive->amount,
                'status' => $incentive->status,
            ])->values(),
        ];
    }
}
