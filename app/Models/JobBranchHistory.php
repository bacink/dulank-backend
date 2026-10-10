<?php

namespace App\Models;

use Database\Factories\JobBranchHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobBranchHistory extends Model
{
    /** @use HasFactory<JobBranchHistoryFactory> */
    use HasFactory;

    protected $fillable = ['job_branch_id', 'date', 'branch', 'customer', 'flow_name', 'date_finish'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'date_finish' => 'date',
        ];
    }

    /**
     * @return BelongsTo<JobBranch, $this>
     */
    public function jobBranch(): BelongsTo
    {
        return $this->belongsTo(JobBranch::class);
    }
}
