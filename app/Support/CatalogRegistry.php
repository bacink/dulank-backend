<?php

namespace App\Support;

use App\Http\Resources\Store\ClientResource;
use App\Http\Resources\Store\DieCuttingBladeResource;
use App\Http\Resources\Store\MachineResource;
use App\Http\Resources\Store\PaperGroupResource;
use App\Http\Resources\Store\PaperPriceResource;
use App\Http\Resources\Store\PaperSizeResource;
use App\Http\Resources\Store\PaperStoreResource;
use App\Http\Resources\Store\PaperTypeResource;
use App\Http\Resources\Store\PrintingShopResource;
use App\Models\Client;
use App\Models\DieCuttingBlade;
use App\Models\Machine;
use App\Models\PaperGroup;
use App\Models\PaperPrice;
use App\Models\PaperSize;
use App\Models\PaperType;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Registry endpoint generik `GET /catalog/{kind}` (PRD §6.4).
 *
 * Setiap kind memetakan ke model + resource store + modifier query.
 */
final class CatalogRegistry
{
    /**
     * @return array{model: class-string, resource: class-string, query: callable|null}|null
     */
    public static function resolve(string $kind): ?array
    {
        return match ($kind) {
            'clients' => [
                'model' => Client::class,
                'resource' => ClientResource::class,
                'query' => fn (Builder $query): Builder => $query->orderBy('sort_order'),
            ],
            'printing-shops' => [
                'model' => Partner::class,
                'resource' => PrintingShopResource::class,
                'query' => fn (Builder $query): Builder => $query
                    ->where('type', 'printing_shop')
                    ->with('metrics')
                    ->orderBy('id'),
            ],
            'paper-stores' => [
                'model' => Partner::class,
                'resource' => PaperStoreResource::class,
                'query' => fn (Builder $query): Builder => $query
                    ->where('type', 'paper_store')
                    ->with('metrics')
                    ->orderBy('id'),
            ],
            'printing-machines' => [
                'model' => Machine::class,
                'resource' => MachineResource::class,
                'query' => fn (Builder $query): Builder => $query->orderBy('id'),
            ],
            'die-cutting-blades' => [
                'model' => DieCuttingBlade::class,
                'resource' => DieCuttingBladeResource::class,
                'query' => fn (Builder $query): Builder => $query->orderBy('id'),
            ],
            'paper-groups' => [
                'model' => PaperGroup::class,
                'resource' => PaperGroupResource::class,
                'query' => fn (Builder $query): Builder => $query->orderBy('id'),
            ],
            'paper-types' => [
                'model' => PaperType::class,
                'resource' => PaperTypeResource::class,
                'query' => fn (Builder $query): Builder => $query->with('group')->orderBy('id'),
            ],
            'paper-prices' => [
                'model' => PaperPrice::class,
                'resource' => PaperPriceResource::class,
                'query' => fn (Builder $query): Builder => $query->with('group')->orderBy('id'),
            ],
            'paper-sizes' => [
                'model' => PaperSize::class,
                'resource' => PaperSizeResource::class,
                'query' => fn (Builder $query): Builder => $query->orderBy('id'),
            ],
            default => null,
        };
    }

    /**
     * @return Collection<int, mixed>|null
     */
    public static function rows(string $kind): ?Collection
    {
        $config = self::resolve($kind);

        if ($config === null) {
            return null;
        }

        $query = $config['model']::query();

        if ($config['query'] !== null) {
            $query = $config['query']($query);
        }

        return $query->get();
    }
}
