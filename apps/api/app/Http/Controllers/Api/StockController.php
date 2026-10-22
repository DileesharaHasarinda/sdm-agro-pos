<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $categoryId = $validated['category_id'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 10);

        $products = Product::query()
            ->with([
                'category:id,name',
                'stockBatches' => fn($query) => $query
                    ->where('available_quantity', '>', 0)
                    ->orderByRaw('expiry_date IS NULL')
                    ->orderBy('expiry_date')
                    ->orderBy('id'),
            ])
            ->whereHas('stockBatches', fn(Builder $query) => $query
                ->where('available_quantity', '>', 0))
            ->when($categoryId !== null, fn(Builder $query) => $query
                ->where('category_id', $categoryId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage);

        $data = collect($products->items())
            ->map(fn(Product $product): array => $this->stockProductData($product))
            ->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
        ]);
    }

    public function priceOptions(Product $product): JsonResponse
    {
        $product->load([
            'category:id,name',
            'stockBatches' => fn($query) => $query
                ->where('available_quantity', '>', 0)
                ->orderByRaw('expiry_date IS NULL')
                ->orderBy('expiry_date')
                ->orderBy('id'),
        ]);

        return response()->json([
            'data' => $this->stockProductData($product),
        ]);
    }

    /** @return array<string, mixed> */
    private function stockProductData(Product $product): array
    {
        $availableBatches = $product->stockBatches
            ->filter(fn(StockBatch $batch): bool => (float) $batch->available_quantity > 0);

        // Group by the original selling price, preserving the existing response shape.
        $priceOptions = $availableBatches
            ->groupBy(fn(StockBatch $batch): string => number_format(
                (float) $batch->selling_price, 2, '.', ''
            ))
            ->map(function ($batches, string $sellingPrice): array {
                return [
                    'selling_price' => (float) $sellingPrice,
                    'available_quantity' => round($batches->sum(
                        fn(StockBatch $batch): float => (float) $batch->available_quantity
                    ), 3),
                    'batches' => $batches->map(fn(StockBatch $batch): array => [
                        'id' => $batch->id,
                        'batch_code' => $batch->batch_code,
                        'batch_number' => $batch->batch_number,
                        'purchase_cost' => (float) $batch->purchase_cost,
                        'selling_price' => (float) $batch->selling_price,
                        'available_quantity' => (float) $batch->available_quantity,
                        'received_quantity' => (float) $batch->received_quantity,
                        'purchase_item_id' => $batch->purchase_item_id,
                        'is_dual_unit' => (bool) $batch->is_dual_unit,
                        'stock_unit' => $batch->stockUnitValue(),
                        'secondary_unit' => $batch->secondaryUnitValue(),
                        'conversion_factor' => $batch->conversionFactorValue(),
                        'secondary_selling_price' => $batch->secondarySellingPriceValue(),
                        'expiry_date' => $batch->expiry_date?->format('Y-m-d'),
                        'received_at' => $batch->received_at?->toISOString(),
                    ])->values(),
                ];
            })
            ->sortBy('selling_price')
            ->values();

        // A mixture of Bag and Kg quantities cannot be represented truthfully
        // by a single quantity and the parent product's unit.
        $stockByUnit = $availableBatches
            ->groupBy(fn(StockBatch $batch): string => $batch->stockUnitValue())
            ->map(fn($batches, $unit): string => sprintf(
                '%s %s',
                rtrim(rtrim(number_format((float) $batches->sum('available_quantity'), 3, '.', ''), '0'), '.'),
                $unit,
            ))
            ->values()
            ->implode(' + ');

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'unit' => $product->unit,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
            ] : null,
            // Retain legacy numeric field for current clients.
            'total_available_quantity' => round($availableBatches->sum(
                fn(StockBatch $batch): float => (float) $batch->available_quantity
            ), 3),
            'stock_summary' => $stockByUnit,
            'price_options' => $priceOptions,
        ];
    }
}
