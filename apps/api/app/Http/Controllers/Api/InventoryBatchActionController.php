<?php

namespace App\Http\Controllers\Api;

use App\Events\PosStockUpdated;
use App\Http\Controllers\Controller;
use App\Models\StockBatch;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin-only stock corrections.
 * Do not delete received purchases, sale items, stock batches, or audit rows.
 * The routes MUST be inside the role:admin middleware group.
 */
class InventoryBatchActionController extends Controller
{
    public function writeOff(Request $request, StockBatch $stockBatch): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', Rule::in([
                'expired', 'damaged', 'incorrect_entry', 'other',
            ])],
            'notes' => ['required', 'string', 'min:5', 'max:180'],
        ]);

        $result = DB::transaction(function () use ($request, $stockBatch, $validated): array {
            /** @var StockBatch $batch */
            $batch = StockBatch::query()
                ->lockForUpdate()
                ->findOrFail($stockBatch->id);

            $available = round((float) $batch->available_quantity, 3);

            if ($available <= 0) {
                throw ValidationException::withMessages([
                    'stock_batch' => ['This batch has no remaining stock to write off.'],
                ]);
            }

            if ($validated['reason'] === 'expired' && !$batch->isExpired()) {
                throw ValidationException::withMessages([
                    'reason' => ['This batch is not expired. Select another reason, or correct its expiry date.'],
                ]);
            }

            $batch->forceFill(['available_quantity' => 0])->save();

            $reasonLabels = [
                'expired' => 'Expired stock',
                'damaged' => 'Damaged / unsellable stock',
                'incorrect_entry' => 'Incorrect stock receipt',
                'other' => 'Other inventory adjustment',
            ];

            $movement = StockMovement::query()->create([
                'product_id' => $batch->product_id,
                'stock_batch_id' => $batch->id,
                'movement_type' => StockMovement::TYPE_ADJUSTMENT,
                'quantity_before' => $available,
                'quantity_change' => -$available,
                'quantity_after' => 0,
                'reference_type' => 'stock_adjustment',
                'reference_id' => $batch->id,
                'reference_number' => $batch->batch_number ?: $batch->batch_code,
                'notes' => mb_strcut(sprintf(
                    '%s. Wrote off %.3f %s. Reason: %s',
                    $reasonLabels[$validated['reason']],
                    $available,
                    $batch->stockUnitValue(),
                    trim($validated['notes']),
                ), 0, 240, 'UTF-8'),
                'created_by' => $request->user()?->id,
            ]);

            return [
                'batch_id' => (int) $batch->id,
                'product_id' => (int) $batch->product_id,
                'product_variant_id' => $batch->product_variant_id,
                'quantity_written_off' => $available,
                'stock_unit' => $batch->stockUnitValue(),
                'stock_movement_id' => $movement->id,
                'batch' => $batch,
            ];
        }, 3);

        $this->broadcastBatch($result['batch'], 'stock_batch_update');

        return response()->json([
            'message' => 'The remaining batch stock was written off. Purchase and sales history were preserved.',
            'data' => collect($result)->except('batch')->all(),
        ]);
    }

    /**
     * Correct a wrongly received Bag batch by enabling full-Bag + loose-Kg sales.
     * Only before ANY sales / returns / manual adjustments have touched the batch.
     * The receipt and supplier payable remain intact.
     */
    public function enableLoose(Request $request, StockBatch $stockBatch): JsonResponse
    {
        $validated = $request->validate([
            'conversion_factor' => ['required', 'numeric', 'gt:0', 'max:999999.999'],
            'secondary_selling_price' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'reason' => ['required', 'string', 'min:5', 'max:180'],
        ]);

        $result = DB::transaction(function () use ($request, $stockBatch, $validated): array {
            /** @var StockBatch $batch */
            $batch = StockBatch::query()
                ->with(['product', 'productVariant', 'purchaseItem'])
                ->lockForUpdate()
                ->findOrFail($stockBatch->id);

            $product = $batch->product;
            $purchaseItem = $batch->purchaseItem;

            if (
                !$product || strtolower(trim((string) $product->unit)) !== 'bag'
                || $batch->product_variant_id !== null
                || $batch->usesDualUnit()
                || strtolower(trim($batch->stockUnitValue())) !== 'bag'
                || !$purchaseItem
                || $purchaseItem->usesDualUnit()
            ) {
                throw ValidationException::withMessages([
                    'stock_batch' => ['Only an unconverted purchase-linked Bag batch can be changed to Bag + Kg loose selling.'],
                ]);
            }

            // Never rewrite the units of a batch used by any sale or adjustment.
            if (
                $batch->saleItems()->exists()
                || $batch->movements()
                    ->where('movement_type', '!=', StockMovement::TYPE_PURCHASE_RECEIPT)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'stock_batch' => ['This batch already has sales, returns, or adjustments. Its unit configuration cannot be changed safely.'],
                ]);
            }

            if (StockBatch::query()
                ->where('purchase_item_id', $purchaseItem->id)
                ->where('id', '!=', $batch->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'stock_batch' => ['Multiple batches reference this purchase item; correct them through a dedicated purchase-receipt workflow.'],
                ]);
            }

            $oldReceived = round((float) $batch->received_quantity, 3);
            $oldAvailable = round((float) $batch->available_quantity, 3);
            $receivedPurchasingUnits = round((float) $purchaseItem->received_quantity, 3);

            if (
                $oldReceived <= 0 || $oldAvailable <= 0
                || abs($oldReceived - $oldAvailable) > 0.0001
                || abs($oldReceived - $receivedPurchasingUnits) > 0.0001
            ) {
                throw ValidationException::withMessages([
                    'stock_batch' => ['This batch must be completely untouched with its full original Bag quantity still available.'],
                ]);
            }

            $otherBatches = StockBatch::query()
                ->where('product_id', $batch->product_id)
                ->whereNull('product_variant_id')
                ->where('id', '!=', $batch->id)
                ->where('available_quantity', '>', 0)
                ->lockForUpdate()
                ->get();

            foreach ($otherBatches as $other) {
                if (strtolower(trim($other->stockUnitValue())) !== 'kg') {
                    throw ValidationException::withMessages([
                        'stock_batch' => [
                            'Other active batches of this product are counted in Bag. Convert or clear those batches before changing this one to Kg.',
                        ],
                    ]);
                }
            }

            $factor = round((float) $validated['conversion_factor'], 3);
            $loosePrice = round((float) $validated['secondary_selling_price'], 2);
            if ($factor <= 0) {
                throw ValidationException::withMessages([
                    'conversion_factor' => ['Weight per Bag must be greater than zero.'],
                ]);
            }

            $newQuantity = round($oldReceived * $factor, 3);
            if ($newQuantity <= 0 || $newQuantity > 999999999.999) {
                throw ValidationException::withMessages([
                    'conversion_factor' => ['The converted stock quantity is outside the supported range.'],
                ]);
            }

            $purchaseCost = (float) $batch->purchase_cost;
            $batch->forceFill([
                'is_dual_unit' => true,
                'stock_unit' => 'Kg',
                'secondary_unit' => 'Kg',
                'conversion_factor' => $factor,
                'secondary_selling_price' => $loosePrice,
                'base_unit_cost' => round($purchaseCost / $factor, 4),
                'received_quantity' => $newQuantity,
                'available_quantity' => $newQuantity,
            ])->save();

            // Keep the linked purchase item consistent with the converted stock batch.
            // The purchase quantity, unit cost, supplier invoice and payable do not change.
            $purchaseItem->forceFill([
                'is_dual_unit' => true,
                'secondary_unit' => 'Kg',
                'conversion_factor' => $factor,
                'secondary_selling_price' => $loosePrice,
            ])->save();

            $movement = StockMovement::query()->create([
                'product_id' => $batch->product_id,
                'stock_batch_id' => $batch->id,
                'movement_type' => StockMovement::TYPE_ADJUSTMENT,
                'quantity_before' => $oldAvailable,
                'quantity_change' => round($newQuantity - $oldAvailable, 3),
                'quantity_after' => $newQuantity,
                'reference_type' => 'stock_adjustment',
                'reference_id' => $batch->id,
                'reference_number' => $batch->batch_number ?: $batch->batch_code,
                'notes' => mb_strcut(sprintf(
                    'Unit correction: %.3f Bag converted to %.3f Kg at %.3f Kg per Bag. Unit changed from Bag to Kg; quantity difference is a unit conversion, not new stock. Loose price: %.2f. Reason: %s',
                    $oldAvailable,
                    $newQuantity,
                    $factor,
                    $loosePrice,
                    trim($validated['reason']),
                ), 0, 240, 'UTF-8'),
                'created_by' => $request->user()?->id,
            ]);

            return [
                'batch_id' => (int) $batch->id,
                'product_id' => (int) $batch->product_id,
                'available_quantity' => $newQuantity,
                'stock_unit' => 'Kg',
                'stock_movement_id' => $movement->id,
                'batch' => $batch,
            ];
        }, 3);

        $this->broadcastBatch($result['batch'], 'stock_batch_update');

        return response()->json([
            'message' => 'Loose Kg selling enabled. The batch and linked purchase item were updated; historical purchase records remain available.',
            'data' => collect($result)->except('batch')->all(),
        ]);
    }

    private function broadcastBatch(StockBatch $batch, string $reason): void
    {
        $batch->refresh();
        $batch->load(['product', 'productVariant']);

        event(new PosStockUpdated(
            0,
            sprintf('ADMIN-STOCK-%d', $batch->id),
            [[
                'id' => $batch->id,
                'product_id' => $batch->product_id,
                'product_variant_id' => $batch->product_variant_id,
                'available_quantity' => round((float) $batch->available_quantity, 3),
                'selling_price' => round((float) $batch->selling_price, 2),
                'secondary_selling_price' => $batch->secondary_selling_price === null
                    ? null : round((float) $batch->secondary_selling_price, 2),
                'is_dual_unit' => (bool) $batch->is_dual_unit,
                'primary_unit' => $batch->primaryUnitValue(),
                'stock_unit' => $batch->stockUnitValue(),
                'secondary_unit' => $batch->secondaryUnitValue(),
                'conversion_factor' => round($batch->conversionFactorValue(), 3),
                'updated_at' => $batch->updated_at?->toISOString(),
            ]],
            $reason,
        ));
    }
}
