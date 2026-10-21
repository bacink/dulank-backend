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
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    public function show(string $group): JsonResponse
    {
        $settings = Setting::query()->where('group', $group)->get();

        $payload = $settings
            ->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->value])
            ->all();

        if ($group === 'currency' && ! isset($payload['items'])) {
            $legacy = Setting::query()->where('group', 'currencies')->where('key', 'default')->value('value');
            if (is_array($legacy)) {
                $payload['items'] = [['id' => $legacy['code'] ?? 'IDR', 'code' => $legacy['code'] ?? 'IDR', 'name' => $legacy['name'] ?? '', 'symbol' => $legacy['symbol'] ?? '', 'exchangeRate' => $legacy['rate'] ?? 1, 'isDefault' => true, 'status' => strtolower($legacy['status'] ?? 'Active'), 'createdOn' => '']];
            }
        }
        if ($group === 'company') {
            $payload = array_replace((array) ($payload['profile'] ?? []), array_diff_key($payload, ['profile' => true]));
        }

        return ApiResponse::success([
            'group' => $group,
            'values' => $payload,
        ]);
    }

    public function update(Request $request, string $group): JsonResponse
    {
        $values = $request->all();
        if ($group === 'prefixes') {
            foreach ($values as $key => $prefix) {
                $text = is_array($prefix) ? ($prefix['prefix'] ?? null) : $prefix;
                if (! is_string($text) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,19}$/', $text)) {
                    throw ValidationException::withMessages([$key => ['Prefix wajib 1?20 huruf, angka, underscore atau tanda hubung.']]);
                }
            }
        }
        if ($group === 'currency') {
            $request->validate(['items' => ['required', 'array'], 'items.*.code' => ['required', 'string', 'size:3', 'distinct'], 'items.*.exchangeRate' => ['required', 'numeric', 'gt:0'], 'items.*.isDefault' => ['required', 'boolean'], 'items.*.status' => ['required', 'in:active,inactive']]);
            if (count(array_filter($values['items'], fn ($item) => $item['isDefault'] && $item['status'] === 'active')) !== 1) {
                throw ValidationException::withMessages(['items' => ['Pilih tepat satu mata uang default yang aktif.']]);
            }
        }
        if ($group === 'security') {
            return ApiResponse::error('Gunakan endpoint account/security untuk pengaturan akun.', 422);
        }
        if ($group === 'company') {
            $request->validate(['companyName' => ['sometimes', 'string', 'max:255'], 'email' => ['sometimes', 'nullable', 'email'], 'currency' => ['sometimes', 'string', 'size:3']]);
            $existing = Setting::query()->where('group', 'company')->where('key', 'profile')->value('value') ?? [];
            foreach (Setting::query()->where('group', 'company')->where('key', '!=', 'profile')->get() as $setting) {
                $existing[$setting->key] = $setting->value;
            }
            $values = ['profile' => array_replace((array) $existing, $values)];

        }

        DB::transaction(function () use ($values, $group, $request): void {
            if ($group === 'company') {
                Setting::query()->where('group', 'company')->where('key', '!=', 'profile')->delete();
            }
            foreach ($values as $key => $value) {
                Setting::query()->updateOrCreate(
                    ['group' => $group, 'key' => (string) $key],
                    ['value' => $value, 'updated_by' => $request->user()?->id],
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

    public function updateCustomField(Request $request, CustomField $customField): JsonResponse
    {
        $validated = $request->validate([
            'module' => ['sometimes', 'string', 'max:60'],
            'label' => ['sometimes', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:60'],
            'type' => ['sometimes', 'nullable', 'in:text,number,date,select,textarea,checkbox'],
            'options' => ['sometimes', 'nullable', 'array'],
            'required' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $customField->forceFill($validated)->save();

        return ApiResponse::success(['id' => (string) $customField->id, 'label' => $customField->label]);
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

    public function updateBanIp(Request $request, BanIp $banIp): JsonResponse
    {
        $validated = $request->validate([
            'ipAddress' => ['sometimes', 'string', 'max:45'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $banIp->forceFill(array_filter([
            'ip_address' => $validated['ipAddress'] ?? $banIp->ip_address,
            'reason' => array_key_exists('reason', $validated) ? $validated['reason'] : $banIp->reason,
            'status' => $validated['status'] ?? $banIp->status,
        ], fn ($value) => $value !== null))->save();

        Cache::forget('ban_ips');

        return ApiResponse::success([
            'id' => (string) $banIp->id,
            'ip' => $banIp->ip_address,
            'reason' => $banIp->reason,
            'status' => $banIp->status,
            'created' => $banIp->created_at?->toIso8601String(),
        ]);
    }

    public function languages(): JsonResponse
    {
        return ApiResponse::success(Language::query()->withCount('translations')->orderBy('name')->get()->map(fn (Language $language) => [
            'id' => (string) $language->id,
            'code' => $language->code,
            'name' => $language->name,
            'flag' => $language->flag,
            'rtl' => (bool) $language->rtl,
            'totalKeys' => (int) ($language->translations_count ?? 0),
            'doneKeys' => (int) ($language->translations_count ?? 0),
            'progress' => (int) $language->progress,
            'status' => strtolower($language->status),
            'isDefault' => $language->code === 'id',
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

    public function updateLanguage(Request $request, Language $language): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'flag' => ['sometimes', 'nullable', 'string', 'max:255'],
            'rtl' => ['sometimes', 'boolean'],
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $language->forceFill(array_filter([
            'name' => $validated['name'] ?? null,
            'flag' => array_key_exists('flag', $validated) ? $validated['flag'] : null,
            'rtl' => array_key_exists('rtl', $validated) ? (bool) $validated['rtl'] : null,
            'progress' => $validated['progress'] ?? null,
            'status' => $validated['status'] ?? null,
        ], fn ($value) => $value !== null))->save();

        return ApiResponse::success([
            'id' => (string) $language->id,
            'code' => $language->code,
            'name' => $language->name,
            'flag' => $language->flag,
            'rtl' => $language->rtl,
            'progress' => (int) $language->progress,
            'status' => $language->status,
        ]);
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
