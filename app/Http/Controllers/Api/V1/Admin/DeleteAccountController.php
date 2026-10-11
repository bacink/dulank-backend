<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeleteAccountRequest;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Permintaan penghapusan akun dari pengguna webstore.
 */
class DeleteAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DeleteAccountRequest::query();

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $query) => $query
                ->where('email', 'like', $term)
                ->orWhere('user_name', 'like', $term));
        }

        $sort = $request->input('sort', 'newest');
        $query->when($sort === 'oldest', fn (Builder $query) => $query->orderBy('requisition_date'), fn (Builder $query) => $query->orderByDesc('requisition_date'));

        return ApiResponse::success($query->limit(200)->get()->map(fn (DeleteAccountRequest $request) => $this->payload($request))->values());
    }

    public function destroy(DeleteAccountRequest $deleteAccountRequest): JsonResponse
    {
        $deleteAccountRequest->delete();

        return ApiResponse::success(['id' => (string) $deleteAccountRequest->id, 'removed' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(DeleteAccountRequest $request): array
    {
        return [
            'id' => (string) $request->id,
            'email' => $request->email,
            'userName' => $request->user_name,
            'avatar' => $request->avatar ?? '',
            'requisitionDate' => $request->requisition_date?->toDateString(),
            'deleteRequestDate' => $request->delete_request_date?->toDateString(),
            'status' => $request->status,
        ];
    }
}
