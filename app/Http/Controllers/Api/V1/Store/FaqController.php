<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\FaqIndexRequest;
use App\Http\Resources\Store\FaqResource;
use App\Models\Faq;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class FaqController extends Controller
{
    public function index(FaqIndexRequest $request): JsonResponse
    {
        $query = Faq::query()->with('category');

        $category = trim((string) $request->input('category', ''));
        if ($category !== '') {
            $query->whereHas('category', fn (Builder $query) => $query->where('name', $category));
        }

        $faqs = $query->orderBy('sort_order')->orderBy('id')->get();

        return ApiResponse::success(FaqResource::collection($faqs));
    }
}
