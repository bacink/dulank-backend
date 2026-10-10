<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\FlowCategory;
use App\Models\JobBranch;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * View produksi: daftar job (monitoring) & my-jobs (pekerja).
 */
class JobViewController extends Controller
{
    public function jobList(AdminIndexRequest $request): JsonResponse
    {
        $query = JobBranch::query()->with('jobOrder');

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $query) => $query
                ->where('job_title', 'like', $term)
                ->orWhere('customer', 'like', $term)
                ->orWhere('flow_name', 'like', $term));
        }

        $jobs = $query->orderByDesc('id')->limit(200)->get()->map(fn (JobBranch $branch) => $this->branchPayload($branch))->values();

        $flowCategories = FlowCategory::query()->withCount('flowNames')->orderBy('name')->get()->map(fn (FlowCategory $category) => [
            'id' => (string) $category->id,
            'code' => $category->code,
            'name' => $category->name,
            'usedCount' => $category->flow_names_count,
        ])->values();

        return ApiResponse::success([
            'jobs' => $jobs,
            'flowCategories' => $flowCategories,
        ]);
    }

    public function myJobs(AdminIndexRequest $request): JsonResponse
    {
        $query = JobBranch::query()->with('jobOrder');

        $assignee = trim((string) $request->input('assignee', ''));
        if ($assignee !== '') {
            $query->whereJsonContains('assignees', $assignee);
        }

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('job_title', 'like', '%'.$search.'%');
        }

        return ApiResponse::success($query->orderByDesc('id')->limit(200)->get()
            ->map(fn (JobBranch $branch) => $this->branchPayload($branch))->values());
    }

    /**
     * @return array<string, mixed>
     */
    private function branchPayload(JobBranch $branch): array
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
        ];
    }
}
