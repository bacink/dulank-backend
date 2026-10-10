<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\BlogPostIndexRequest;
use App\Http\Resources\Store\BlogPostResource;
use App\Models\BlogPost;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class BlogPostController extends Controller
{
    public function index(BlogPostIndexRequest $request): JsonResponse
    {
        $query = BlogPost::query()->published()->with('tags');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $query) use ($term): void {
                $query->where('title', 'like', $term)->orWhere('excerpt', 'like', $term);
            });
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $posts = $query->orderByDesc('published_at')->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success(BlogPostResource::collection($posts), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $post = BlogPost::query()
            ->published()
            ->with('tags')
            ->where('slug', $slug)
            ->first();

        if ($post === null) {
            return ApiResponse::error('Blog post not found.', 404);
        }

        return ApiResponse::success(new BlogPostResource($post));
    }
}
