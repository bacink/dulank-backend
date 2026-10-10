<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\Tag;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = BlogPost::query()->with(['category', 'tags']);

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $query) => $query->where('title', 'like', $term)->orWhere('slug', 'like', $term));
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $posts = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($posts->map(fn (BlogPost $post) => $this->postPayload($post))->values(), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePost($request);

        $post = BlogPost::query()->create([
            'slug' => $this->uniqueSlug($validated['slug'] ?? $validated['title']),
            'title' => $validated['title'],
            'excerpt' => $validated['excerpt'] ?? null,
            'content' => $validated['content'] ?? null,
            'image_path' => $validated['image'] ?? null,
            'author_name' => $validated['author'] ?? $request->user()->name,
            'blog_category_id' => $this->categoryId($validated['category'] ?? null),
            'published_at' => $request->date('publishedAt')?->toDateString() ?? now()->toDateString(),
            'status' => $validated['status'] ?? 'Publish',
        ]);

        $this->syncTags($post, $validated['tags'] ?? []);

        return ApiResponse::success($this->postPayload($post->load(['category', 'tags'])), [], 201);
    }

    public function update(Request $request, BlogPost $blogPost): JsonResponse
    {
        $validated = $this->validatePost($request);

        $blogPost->forceFill([
            'slug' => isset($validated['slug']) ? $this->uniqueSlug($validated['slug'], $blogPost->id) : $blogPost->slug,
            'title' => $validated['title'],
            'excerpt' => $validated['excerpt'] ?? null,
            'content' => $validated['content'] ?? null,
            'image_path' => $validated['image'] ?? $blogPost->image_path,
            'author_name' => $validated['author'] ?? $blogPost->author_name,
            'blog_category_id' => $this->categoryId($validated['category'] ?? null) ?? $blogPost->blog_category_id,
            'published_at' => $request->date('publishedAt')?->toDateString() ?? $blogPost->published_at?->toDateString(),
            'status' => $validated['status'] ?? $blogPost->status,
        ])->save();

        if (array_key_exists('tags', $validated)) {
            $this->syncTags($blogPost, $validated['tags'] ?? []);
        }

        return ApiResponse::success($this->postPayload($blogPost->refresh()->load(['category', 'tags'])));
    }

    public function destroy(BlogPost $blogPost): JsonResponse
    {
        $blogPost->delete();

        return ApiResponse::success(['id' => (string) $blogPost->id, 'removed' => true]);
    }

    public function categories(AdminIndexRequest $request): JsonResponse
    {
        $query = BlogCategory::query()->withCount('posts');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (BlogCategory $category) => [
            'id' => (string) $category->id,
            'name' => $category->name,
            'status' => $category->status,
            'postCount' => $category->posts_count,
        ]));
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $category = BlogCategory::query()->create([
            'name' => $validated['name'],
            'status' => $validated['status'] ?? 'Active',
        ]);

        return ApiResponse::success(['id' => (string) $category->id, 'name' => $category->name], [], 201);
    }

    public function updateCategory(Request $request, BlogCategory $blogCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $blogCategory->forceFill([
            'name' => $validated['name'],
            'status' => $validated['status'] ?? $blogCategory->status,
        ])->save();

        return ApiResponse::success(['id' => (string) $blogCategory->id, 'name' => $blogCategory->name]);
    }

    public function destroyCategory(BlogCategory $blogCategory): JsonResponse
    {
        $blogCategory->delete();

        return ApiResponse::success(['id' => (string) $blogCategory->id, 'removed' => true]);
    }

    public function tags(AdminIndexRequest $request): JsonResponse
    {
        $query = Tag::query()->withCount('blogPosts');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (Tag $tag) => [
            'id' => (string) $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'postCount' => $tag->blog_posts_count,
        ]));
    }

    public function storeTag(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $tag = Tag::query()->firstOrCreate(['slug' => Str::slug($validated['name'])], ['name' => $validated['name']]);

        return ApiResponse::success(['id' => (string) $tag->id, 'name' => $tag->name, 'slug' => $tag->slug], [], 201);
    }

    public function destroyTag(Tag $tag): JsonResponse
    {
        $tag->delete();

        return ApiResponse::success(['id' => (string) $tag->id, 'removed' => true]);
    }

    public function comments(AdminIndexRequest $request): JsonResponse
    {
        $query = BlogComment::query()->with('post');

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $comments = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($comments->map(fn (BlogComment $comment) => [
            'id' => (string) $comment->id,
            'postId' => (string) $comment->blog_post_id,
            'postTitle' => $comment->post?->title,
            'comment' => $comment->comment,
            'rating' => $comment->rating,
            'author' => $comment->author,
            'status' => $comment->status,
            'created' => $comment->created_at?->toIso8601String(),
        ])->values(), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function updateComment(Request $request, BlogComment $blogComment): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:Publish,Unpublish'],
        ]);

        $blogComment->forceFill(['status' => $validated['status']])->save();

        return ApiResponse::success(['id' => (string) $blogComment->id, 'status' => $blogComment->status]);
    }

    public function destroyComment(BlogComment $blogComment): JsonResponse
    {
        $blogComment->delete();

        return ApiResponse::success(['id' => (string) $blogComment->id, 'removed' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePost(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'content' => ['sometimes', 'nullable', 'string'],
            'image' => ['sometimes', 'nullable', 'string', 'max:255'],
            'author' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'in:Publish,Unpublish,Draft'],
            'publishedAt' => ['sometimes', 'nullable', 'date'],
        ]);
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);
        $slug = $base;
        $suffix = 1;

        while (BlogPost::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    private function categoryId(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return BlogCategory::query()->firstOrCreate(['name' => trim($name)])->id;
    }

    /**
     * @param  array<int, string>  $names
     */
    private function syncTags(BlogPost $post, array $names): void
    {
        $ids = [];

        foreach ($names as $name) {
            $ids[] = Tag::query()->firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id;
        }

        $post->tags()->sync($ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function postPayload(BlogPost $post): array
    {
        return [
            'id' => (string) $post->id,
            'slug' => $post->slug,
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'content' => $post->content,
            'image' => $post->image_path,
            'author' => $post->author_name,
            'category' => $post->category?->name,
            'tags' => $post->tags->pluck('name')->values(),
            'status' => $post->status,
            'publishedAt' => $post->published_at?->toDateString(),
            'created' => $post->created_at?->toIso8601String(),
        ];
    }
}
