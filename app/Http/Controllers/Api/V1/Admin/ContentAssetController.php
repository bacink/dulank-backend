<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\Client;
use App\Models\DownloadFile;
use App\Models\Setting;
use App\Services\MediaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentAssetController extends Controller
{
    public function clients(AdminIndexRequest $request): JsonResponse
    {
        $query = Client::query();

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return ApiResponse::success($query->orderBy('sort_order')->get()->map(fn (Client $client) => [
            'id' => (string) $client->id,
            'name' => $client->name,
            'logoUrl' => $client->logo_url,
            'svgPath' => $client->svg_path,
            'viewBox' => $client->view_box,
            'website' => $client->website,
            'category' => $client->category,
            'status' => $client->status,
            'order' => $client->sort_order,
        ]));
    }

    public function storeClient(Request $request): JsonResponse
    {
        $validated = $this->validateClient($request);

        $client = Client::query()->create($this->clientAttributes($validated, null));

        return ApiResponse::success(['id' => (string) $client->id, 'name' => $client->name], [], 201);
    }

    public function updateClient(Request $request, Client $client): JsonResponse
    {
        $validated = $this->validateClient($request);

        $client->forceFill($this->clientAttributes($validated, $client))->save();

        return ApiResponse::success(['id' => (string) $client->id, 'name' => $client->name]);
    }

    public function destroyClient(Client $client): JsonResponse
    {
        $client->delete();

        return ApiResponse::success(['id' => (string) $client->id, 'removed' => true]);
    }

    public function downloadFiles(AdminIndexRequest $request): JsonResponse
    {
        $query = DownloadFile::query();

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return ApiResponse::success($query->orderByDesc('id')->get()->map(fn (DownloadFile $file) => [
            'id' => (string) $file->id,
            'name' => $file->name,
            'type' => $file->type,
            'size' => $file->size,
            'path' => $file->path,
            'date' => $file->date?->toDateString(),
        ]));
    }

    public function storeDownloadFile(Request $request, MediaService $media): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['sometimes', 'nullable', 'date'],
            'file' => ['sometimes', 'nullable', 'file', 'max:10240'],
        ]);

        $path = isset($validated['file']) ? $media->store($request->file('file'), 'downloads') : null;
        $uploaded = $request->file('file');

        $file = DownloadFile::query()->create([
            'name' => $validated['name'],
            'type' => $uploaded?->getClientOriginalExtension(),
            'size' => $uploaded !== null ? (string) round($uploaded->getSize() / 1024).' KB' : null,
            'path' => $path,
            'date' => $request->date('date')?->toDateString() ?? now()->toDateString(),
        ]);

        return ApiResponse::success(['id' => (string) $file->id, 'name' => $file->name, 'path' => $file->path], [], 201);
    }

    public function destroyDownloadFile(DownloadFile $downloadFile): JsonResponse
    {
        $downloadFile->delete();

        return ApiResponse::success(['id' => (string) $downloadFile->id, 'removed' => true]);
    }

    public function footer(): JsonResponse
    {
        return ApiResponse::success($this->groupValue('footer'));
    }

    public function updateFooter(Request $request): JsonResponse
    {
        $this->saveGroup('footer', $request->all());

        return ApiResponse::success($this->groupValue('footer'));
    }

    public function appearance(): JsonResponse
    {
        return ApiResponse::success($this->groupValue('appearance'));
    }

    public function updateAppearance(Request $request): JsonResponse
    {
        $this->saveGroup('appearance', $request->all());

        return ApiResponse::success($this->groupValue('appearance'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateClient(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'logoUrl' => ['sometimes', 'nullable', 'string', 'max:500'],
            'svgPath' => ['sometimes', 'nullable', 'string', 'max:500'],
            'viewBox' => ['sometimes', 'nullable', 'string', 'max:120'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
            'order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function clientAttributes(array $validated, ?Client $client): array
    {
        return [
            'name' => $validated['name'],
            'logo_url' => $validated['logoUrl'] ?? $client?->logo_url,
            'svg_path' => $validated['svgPath'] ?? $client?->svg_path,
            'view_box' => $validated['viewBox'] ?? $client?->view_box,
            'website' => $validated['website'] ?? $client?->website,
            'category' => $validated['category'] ?? $client?->category,
            'status' => $validated['status'] ?? $client?->status ?? 'Active',
            'sort_order' => (int) ($validated['order'] ?? $client?->sort_order ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function groupValue(string $group): array
    {
        $setting = Setting::query()->where('group', $group)->where('key', 'config')->first();

        return (array) ($setting?->value ?? []);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function saveGroup(string $group, array $values): void
    {
        Setting::query()->updateOrCreate(
            ['group' => $group, 'key' => 'config'],
            ['value' => $values],
        );
    }
}
