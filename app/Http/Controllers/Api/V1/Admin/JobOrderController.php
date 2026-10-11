<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Models\FlowCategory;
use App\Models\JobOrder;
use App\Models\Order;
use App\Models\WorkFlow;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobOrderController extends Controller
{
    public function workFlows(AdminIndexRequest $request): JsonResponse
    {
        $query = WorkFlow::query()->with(['category', 'stepList']);

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('no', 'like', '%'.$search.'%');
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $workFlows = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($workFlows->map(fn (WorkFlow $workFlow) => [
            'id' => (string) $workFlow->id,
            'no' => $workFlow->no,
            'date' => $workFlow->date?->toDateString(),
            'category' => $workFlow->category?->name,
            'product' => $workFlow->product?->name,
            'workflowSteps' => $workFlow->workflow_steps,
            'steps' => $workFlow->stepList->map(fn ($step) => [
                'id' => (string) $step->id,
                'name' => $step->name,
                'category' => $step->category,
                'template' => $step->template,
            ])->values(),
        ])->values(), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function storeWorkFlow(Request $request): JsonResponse
    {
        $validated = $this->validateWorkFlow($request);

        $workFlow = DB::transaction(function () use ($request, $validated): WorkFlow {
            $workFlow = WorkFlow::query()->create([
                'no' => DocumentNumber::next('workflow', WorkFlow::query(), 'no', 3),
                'date' => $request->date('date')?->toDateString() ?? now()->toDateString(),
                'flow_category_id' => $this->categoryId($validated['category'] ?? null),
                'product_id' => $validated['productId'] ?? null,
                'workflow_steps' => $validated['workflowSteps'] ?? null,
                'steps' => $validated['steps'] ?? null,
            ]);

            $this->syncSteps($workFlow, $validated['steps'] ?? []);

            return $workFlow->load(['category', 'stepList', 'product']);
        });

        return ApiResponse::success(['id' => (string) $workFlow->id, 'no' => $workFlow->no], [], 201);
    }

    public function updateWorkFlow(Request $request, WorkFlow $workFlow): JsonResponse
    {
        $validated = $this->validateWorkFlow($request);

        DB::transaction(function () use ($request, $workFlow, $validated): void {
            $workFlow->forceFill([
                'date' => $request->date('date')?->toDateString() ?? $workFlow->date?->toDateString(),
                'flow_category_id' => $this->categoryId($validated['category'] ?? null) ?? $workFlow->flow_category_id,
                'product_id' => $validated['productId'] ?? $workFlow->product_id,
                'workflow_steps' => $validated['workflowSteps'] ?? $workFlow->workflow_steps,
                'steps' => $validated['steps'] ?? $workFlow->steps,
            ])->save();

            if (array_key_exists('steps', $validated)) {
                $this->syncSteps($workFlow, $validated['steps'] ?? []);
            }
        });

        return ApiResponse::success(['id' => (string) $workFlow->id, 'no' => $workFlow->no]);
    }

    public function destroyWorkFlow(WorkFlow $workFlow): JsonResponse
    {
        $workFlow->delete();

        return ApiResponse::success(['id' => (string) $workFlow->id, 'removed' => true]);
    }

    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = JobOrder::query()->with(['steps', 'branches']);

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
                ->orWhere('customer_name', 'like', $term));
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $jobOrders = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($jobOrders->map(fn (JobOrder $jobOrder) => $this->jobOrderPayload($jobOrder))->values(), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateJobOrder($request);

        $jobOrder = DB::transaction(function () use ($request, $validated): JobOrder {
            $jobOrder = JobOrder::query()->create($this->jobOrderAttributes($request, $validated) + [
                'no' => DocumentNumber::next('job', JobOrder::query(), 'no', 3),
                'status' => 'Waiting',
            ]);

            $this->syncJobSteps($jobOrder, $validated['steps'] ?? []);

            return $jobOrder->load(['steps', 'branches']);
        });

        return ApiResponse::success($this->jobOrderPayload($jobOrder), [], 201);
    }

    public function show(JobOrder $jobOrder): JsonResponse
    {
        return ApiResponse::success($this->jobOrderPayload($jobOrder->load(['steps', 'branches'])));
    }

    public function update(Request $request, JobOrder $jobOrder): JsonResponse
    {
        $validated = $this->validateJobOrder($request);

        DB::transaction(function () use ($request, $jobOrder, $validated): void {
            $jobOrder->forceFill($this->jobOrderAttributes($request, $validated))->save();

            if (array_key_exists('steps', $validated)) {
                $this->syncJobSteps($jobOrder, $validated['steps'] ?? []);
            }
        });

        return ApiResponse::success($this->jobOrderPayload($jobOrder->refresh()->load(['steps', 'branches'])));
    }

    public function updateStatus(Request $request, JobOrder $jobOrder): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:Waiting,On Process,Completed'],
        ]);

        $jobOrder->forceFill(['status' => $validated['status']])->save();

        return ApiResponse::success($this->jobOrderPayload($jobOrder->refresh()->load(['steps', 'branches'])));
    }

    public function destroy(JobOrder $jobOrder): JsonResponse
    {
        $jobOrder->delete();

        return ApiResponse::success(['id' => (string) $jobOrder->id, 'removed' => true]);
    }

    private function categoryId(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return FlowCategory::query()->firstOrCreate(['name' => trim($name)])->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateWorkFlow(Request $request): array
    {
        return $request->validate([
            'date' => ['sometimes', 'nullable', 'date'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'productId' => ['sometimes', 'nullable', 'integer', 'exists:products,id'],
            'workflowSteps' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'steps' => ['sometimes', 'nullable', 'array'],
            'steps.*.name' => ['required', 'string', 'max:255'],
            'steps.*.category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'steps.*.template' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function syncSteps(WorkFlow $workFlow, array $steps): void
    {
        $workFlow->stepList()->delete();

        foreach ($steps as $index => $step) {
            $workFlow->stepList()->create([
                'name' => $step['name'],
                'category' => $step['category'] ?? null,
                'template' => $step['template'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateJobOrder(Request $request): array
    {
        return $request->validate([
            'orderNo' => ['sometimes', 'nullable', 'string', 'max:60'],
            'flowNameId' => ['sometimes', 'nullable', 'integer', 'exists:flow_names,id'],
            'flowType' => ['sometimes', 'nullable', 'in:Inhouse,Outsource'],
            'dueDate' => ['sometimes', 'nullable', 'date'],
            'customerName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'productName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'jobTitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'qty' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'priority' => ['sometimes', 'nullable', 'in:High,Medium,Low'],
            'workflowType' => ['sometimes', 'nullable', 'string', 'max:40'],
            'workflowCategory' => ['sometimes', 'nullable', 'string', 'max:40'],
            'salesNo' => ['sometimes', 'nullable', 'string', 'max:60'],
            'shipping' => ['sometimes', 'nullable', 'string', 'max:60'],
            'orderSummary' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'assignedTo' => ['sometimes', 'nullable', 'string', 'max:255'],
            'inputSpecs' => ['sometimes', 'nullable', 'array'],
            'outputSpecs' => ['sometimes', 'nullable', 'array'],
            'steps' => ['sometimes', 'nullable', 'array'],
            'steps.*.name' => ['required', 'string', 'max:255'],
            'steps.*.status' => ['sometimes', 'nullable', 'in:done,active,pending'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function jobOrderAttributes(Request $request, array $validated): array
    {
        $orderId = null;
        if (! empty($validated['orderNo'])) {
            $orderId = Order::query()->where('number', $validated['orderNo'])->value('id');
        }

        return [
            'order_id' => $orderId,
            'flow_name_id' => $validated['flowNameId'] ?? null,
            'flow_type' => $validated['flowType'] ?? null,
            'due_date' => $request->date('dueDate')?->toDateString(),
            'customer_name' => $validated['customerName'] ?? null,
            'product_name' => $validated['productName'] ?? null,
            'job_title' => $validated['jobTitle'] ?? null,
            'qty' => (float) ($validated['qty'] ?? 0),
            'priority' => $validated['priority'] ?? 'Medium',
            'workflow_type' => $validated['workflowType'] ?? null,
            'workflow_category' => $validated['workflowCategory'] ?? null,
            'sales_no' => $validated['salesNo'] ?? null,
            'shipping' => $validated['shipping'] ?? null,
            'order_summary' => $validated['orderSummary'] ?? null,
            'assigned_to' => $validated['assignedTo'] ?? null,
            'input_specs' => $validated['inputSpecs'] ?? null,
            'output_specs' => $validated['outputSpecs'] ?? null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function syncJobSteps(JobOrder $jobOrder, array $steps): void
    {
        $jobOrder->steps()->delete();

        foreach ($steps as $index => $step) {
            $jobOrder->steps()->create([
                'name' => $step['name'],
                'status' => $step['status'] ?? 'pending',
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function jobOrderPayload(JobOrder $jobOrder): array
    {
        return [
            'id' => (string) $jobOrder->id,
            'no' => $jobOrder->no,
            'orderNo' => $jobOrder->sales_no ?? $jobOrder->order?->number,
            'customer' => $jobOrder->customer_name,
            'product' => $jobOrder->product_name,
            'jobTitle' => $jobOrder->job_title,
            'qty' => (float) $jobOrder->qty,
            'priority' => $jobOrder->priority,
            'status' => $jobOrder->status,
            'flowType' => $jobOrder->flow_type,
            'workflowType' => $jobOrder->workflow_type,
            'workflowCategory' => $jobOrder->workflow_category,
            'dueDate' => $jobOrder->due_date?->toDateString(),
            'salesDate' => $jobOrder->sales_date?->toDateString(),
            'shipping' => $jobOrder->shipping,
            'orderSummary' => $jobOrder->order_summary,
            'assignedTo' => $jobOrder->assigned_to,
            'inputSpecs' => $jobOrder->input_specs ?? [],
            'outputSpecs' => $jobOrder->output_specs ?? [],
            'steps' => $jobOrder->steps->map(fn ($step) => [
                'id' => (string) $step->id,
                'name' => $step->name,
                'status' => $step->status,
            ])->values(),
            'branchCount' => $jobOrder->branches->count(),
        ];
    }

    /**
     * Konteks editor job order: job order & order terkait.
     */
    public function context(Request $request): JsonResponse
    {
        $jobOrderId = $request->input('id');
        $orderId = $request->input('orderId');

        $jobOrder = $jobOrderId !== null && $jobOrderId !== ''
            ? JobOrder::query()->find((int) $jobOrderId)
            : null;

        $order = $orderId !== null && $orderId !== ''
            ? Order::query()->where('number', $orderId)->orWhere('id', (int) $orderId)->with('items')->first()
            : null;

        return ApiResponse::success([
            'jobOrder' => $jobOrder !== null ? $this->jobOrderPayload($jobOrder) : null,
            'order' => $order !== null ? new OrderResource($order) : null,
        ]);
    }
}
