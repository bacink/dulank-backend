<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    /**
     * Public settings for the storefront (company profile, footer, etc.).
     */
    public function show(string $group): JsonResponse
    {
        $allowed = ['company', 'footer', 'appearance', 'currency'];

        if (! in_array($group, $allowed, true)) {
            return ApiResponse::error('Unknown settings group', 404);
        }

        $settings = Setting::query()
            ->where('group', $group)
            ->get()
            ->mapWithKeys(fn (Setting $setting): array => [$setting->key => $setting->value]);

        return ApiResponse::success($settings);
    }
}
