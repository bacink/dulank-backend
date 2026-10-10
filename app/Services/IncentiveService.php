<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\FlowName;
use App\Models\JobBranch;
use App\Models\MyIncentive;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Workflow produksi: menyelesaikan cabang job → tulis riwayat + buat
 * insentif per assignee (PRD §7.10).
 */
final class IncentiveService
{
    public function completeBranch(JobBranch $branch, User $admin): JobBranch
    {
        return DB::transaction(function () use ($branch): JobBranch {
            $today = now();

            $branch->forceFill([
                'status' => 'Completed',
                'date_finish' => $today->toDateString(),
            ])->save();

            $branch->histories()->create([
                'date' => $today->toDateString(),
                'branch' => $branch->branch,
                'customer' => $branch->customer,
                'flow_name' => $branch->flow_name,
                'date_finish' => $today->toDateString(),
            ]);

            $flowName = $branch->flow_name !== null
                ? FlowName::query()->where('name', $branch->flow_name)->first()
                : null;

            $rate = $flowName !== null ? (int) $flowName->incentive : (int) $branch->incentive_amount;
            $unit = $flowName?->unit ?? $branch->incentive_unit;
            $qty = (float) $branch->qty;

            foreach ((array) ($branch->assignees ?? []) as $assignee) {
                $name = is_array($assignee)
                    ? (string) ($assignee['name'] ?? '')
                    : (string) $assignee;

                if (trim($name) === '') {
                    continue;
                }

                $employee = Employee::query()->where('name', $name)->first();

                MyIncentive::query()->create([
                    'employee_id' => $employee?->id,
                    'employee_name' => $name,
                    'job_order_id' => $branch->job_order_id,
                    'job_branch_id' => $branch->id,
                    'branch_id' => $branch->branch_id,
                    'date' => $today->toDateString(),
                    'job_title' => $branch->job_title,
                    'flow_name' => $branch->flow_name,
                    'process' => $branch->status,
                    'incentive' => $rate,
                    'unit' => $unit,
                    'qty' => $qty,
                    'amount' => (int) round($rate * $qty),
                    'status' => 'Pending',
                ]);
            }

            // Update ringkasan insentif pada cabang.
            $branch->forceFill([
                'incentive_amount' => $rate,
                'incentive_unit' => $unit,
            ])->save();

            return $branch->refresh()->load(['histories', 'incentives']);
        });
    }
}
