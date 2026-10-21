<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Services\MasterDataService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Shared integrity checks for simple master CRUD, after route permission middleware. */
class ValidateMasterData
{
    public function handle(Request $request, Closure $next): Response
    {
        $resource = $request->segment(4);
        $masters = config('master_integrity.resources', []);
        if (! isset($masters[$resource])) {
            return $next($request);
        }
        [$class, $field, $column] = $masters[$resource];
        $record = null;
        foreach ($request->route()->parameters() as $parameter) {
            if ($parameter instanceof $class) {
                $record = $parameter;
            }
        }
        if ($record === null && $request->filled('id') && in_array($resource, ['units', 'customer-types', 'departments', 'designations', 'bank-account-types', 'tax-rates'], true)) {
            $request->validate(['id' => ['integer']]);
            $record = $class::query()->findOrFail($request->input('id'));
        }
        if ($request->isMethod('DELETE') && $record instanceof Model) {
            MasterDataService::assertDeletable($record);
        } elseif (in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            $scope = [];
            if ($resource === 'sub-categories') {
                $categoryId = $request->input('categoryId') ?: Category::query()->where('name', $request->input('category'))->value('id');
                if ($categoryId) {
                    $scope['category_id'] = $categoryId;
                }
            }
            MasterDataService::unique((new $class)->getTable(), $column, $request->input($field), $field, $record?->id, $scope);
            if (in_array($resource, ['categories', 'sub-categories'], true)) {
                MasterDataService::unique((new $class)->getTable(), 'code', $request->input('code'), 'code', $record?->id);
            }
        }

        return $next($request);
    }
}
