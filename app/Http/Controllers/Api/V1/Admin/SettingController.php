<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\BanIp;
use App\Models\CustomField;
use App\Models\Language;
use App\Models\Setting;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    public function show(string $group): JsonResponse
    {
        $settings = Setting::query()->where('group', $group)->get();

        $payload = $settings
            ->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->value])
            ->all();

        return ApiResponse::success([
            'group' => $group,
            'values' => $payload,
        ]);
    }

    public function update(Request $request, string $group): JsonResponse
    {
        $values = $request->all();

        DB::transaction(function () use ($values, $group, $request): void {
            foreach ($values as $key => $value) {
                Setting::query()->updateOrCreate(
                    ['group' => $group, 'key' => (string) $key],
                    ['value' => $value, 'updated_by' => $request->user()->id],
                );
            }
        });

        return $this->show($group);
    }

    public function customFields(AdminIndexRequest $request): JsonResponse
    {
        $query = CustomField::query();

        $module = trim((string) $request->input('module', ''));
        if ($module !== '') {
            $query->where('module', $module);
        }

        return ApiResponse::success($query->orderBy('module')->orderBy('label')->get()->map(fn (CustomField $field) => [
            'id' => (string) $field->id,
            'module' => $field->module,
            'label' => $field->label,
            'name' => $field->name,
            'type' => $field->type,
            'options' => $field->options ?? [],
            'required' => $field->required,
            'status' => $field->status,
        ]));
    }

    public function storeCustomField(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module' => ['required', 'string', 'max:60'],
            'label' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:60'],
            'type' => ['sometimes', 'nullable', 'in:text,number,date,select,textarea,checkbox'],
            'options' => ['sometimes', 'nullable', 'array'],
            'required' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $field = CustomField::query()->create([
            'module' => $validated['module'],
            'label' => $validated['label'],
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'text',
            'options' => $validated['options'] ?? null,
            'required' => (bool) ($validated['required'] ?? false),
            'status' => $validated['status'] ?? 'Active',
        ]);

        return ApiResponse::success(['id' => (string) $field->id, 'name' => $field->name], [], 201);
    }

    public function destroyCustomField(CustomField $customField): JsonResponse
    {
        $customField->delete();

        return ApiResponse::success(['id' => (string) $customField->id, 'removed' => true]);
    }

    public function banIps(AdminIndexRequest $request): JsonResponse
    {
        $query = BanIp::query();

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('ip_address', 'like', '%'.$search.'%');
        }

        return ApiResponse::success($query->orderByDesc('id')->get()->map(fn (BanIp $ban) => [
            'id' => (string) $ban->id,
            'ipAddress' => $ban->ip_address,
            'reason' => $ban->reason,
            'status' => $ban->status,
            'created' => $ban->created_at?->toIso8601String(),
        ]));
    }

    public function storeBanIp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ipAddress' => ['required', 'string', 'max:45'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $ban = BanIp::query()->create([
            'ip_address' => $validated['ipAddress'],
            'reason' => $validated['reason'] ?? null,
            'status' => $validated['status'] ?? 'Active',
        ]);

        Cache::forget('ban_ips');

        return ApiResponse::success(['id' => (string) $ban->id, 'ipAddress' => $ban->ip_address], [], 201);
    }

    public function destroyBanIp(BanIp $banIp): JsonResponse
    {
        $banIp->delete();

        Cache::forget('ban_ips');

        return ApiResponse::success(['id' => (string) $banIp->id, 'removed' => true]);
    }

    public function languages(): JsonResponse
    {
        return ApiResponse::success(Language::query()->orderBy('name')->get()->map(fn (Language $language) => [
            'id' => (string) $language->id,
            'code' => $language->code,
            'name' => $language->name,
            'flag' => $language->flag,
            'rtl' => $language->rtl,
            'progress' => (int) $language->progress,
            'status' => $language->status,
        ]));
    }

    public function storeLanguage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:languages,code'],
            'name' => ['required', 'string', 'max:255'],
            'flag' => ['sometimes', 'nullable', 'string', 'max:255'],
            'rtl' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $language = Language::query()->create([
            'code' => strtolower($validated['code']),
            'name' => $validated['name'],
            'flag' => $validated['flag'] ?? null,
            'rtl' => (bool) ($validated['rtl'] ?? false),
            'progress' => 0,
            'status' => $validated['status'] ?? 'Active',
        ]);

        return ApiResponse::success(['id' => (string) $language->id, 'code' => $language->code], [], 201);
    }

    public function destroyLanguage(Language $language): JsonResponse
    {
        $language->delete();

        return ApiResponse::success(['id' => (string) $language->id, 'removed' => true]);
    }

    public function translations(Language $language): JsonResponse
    {
        return ApiResponse::success([
            'languageId' => (string) $language->id,
            'code' => $language->code,
            'translations' => $language->translations()->pluck('value', 'key'),
            'progress' => (int) $language->progress,
        ]);
    }

    public function updateTranslations(Request $request, Language $language): JsonResponse
    {
        $validated = $request->validate([
            'translations' => ['required', 'array'],
            'translations.*' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($language, $validated): void {
            foreach ($validated['translations'] as $key => $value) {
                $language->translations()->updateOrCreate(
                    ['key' => (string) $key],
                    ['value' => $value],
                );
            }

            $total = $language->translations()->count();
            $filled = $language->translations()->whereNotNull('value')->where('value', '!=', '')->count();

            $language->forceFill([
                'progress' => $total > 0 ? (int) round($filled / $total * 100) : 0,
            ])->save();
        });

        return $this->translations($language->refresh());
    }
}
