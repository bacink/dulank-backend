<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = Faq::query()->with('category');

        $category = trim((string) $request->input('category', ''));
        if ($category !== '') {
            $query->whereHas('category', fn (Builder $query) => $query->where('name', $category));
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('question', 'like', '%'.$search.'%');
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $faqs = $query->orderBy('sort_order')->forPage($page, $limit)->get();

        return ApiResponse::success($faqs->map(fn (Faq $faq) => [
            'id' => (string) $faq->id,
            'question' => $faq->question,
            'answer' => $faq->answer,
            'category' => $faq->category?->name,
            'order' => $faq->sort_order,
        ])->values(), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateFaq($request);

        $faq = Faq::query()->create([
            'faq_category_id' => $this->categoryId($validated['category'] ?? null),
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'sort_order' => (int) ($validated['order'] ?? 0),
        ]);

        return ApiResponse::success([
            'id' => (string) $faq->id,
            'question' => $faq->question,
            'answer' => $faq->answer,
        ], [], 201);
    }

    public function update(Request $request, Faq $faq): JsonResponse
    {
        $validated = $this->validateFaq($request);

        $faq->forceFill([
            'faq_category_id' => $this->categoryId($validated['category'] ?? null) ?? $faq->faq_category_id,
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'sort_order' => (int) ($validated['order'] ?? $faq->sort_order),
        ])->save();

        return ApiResponse::success([
            'id' => (string) $faq->id,
            'question' => $faq->question,
            'answer' => $faq->answer,
        ]);
    }

    public function destroy(Faq $faq): JsonResponse
    {
        $faq->delete();

        return ApiResponse::success(['id' => (string) $faq->id, 'removed' => true]);
    }

    public function categories(AdminIndexRequest $request): JsonResponse
    {
        $query = FaqCategory::query()->withCount('faqs');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (FaqCategory $category) => [
            'id' => (string) $category->id,
            'name' => $category->name,
            'faqCount' => $category->faqs_count,
        ]));
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $category = FaqCategory::query()->firstOrCreate(['name' => $validated['name']]);

        return ApiResponse::success(['id' => (string) $category->id, 'name' => $category->name], [], 201);
    }

    public function destroyCategory(FaqCategory $faqCategory): JsonResponse
    {
        $faqCategory->delete();

        return ApiResponse::success(['id' => (string) $faqCategory->id, 'removed' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFaq(Request $request): array
    {
        return $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);
    }

    private function categoryId(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return FaqCategory::query()->firstOrCreate(['name' => trim($name)])->id;
    }
}
