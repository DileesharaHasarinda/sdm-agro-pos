import { apiRequest } from "../lib/api";
import type {
  InventoryBatchActionResponse,
  StockProductListResponse,
  StockWriteOffReason,
} from "../types/stock";

interface StockListParameters {
  search?: string;
  categoryId?: string;
  page?: number;
  perPage?: number;
}

export async function getStockProducts(
  token: string,
  parameters: StockListParameters
): Promise<StockProductListResponse> {
  const query = new URLSearchParams();

  query.set("page", String(parameters.page ?? 1));
  query.set("per_page", String(parameters.perPage ?? 10));

  if (parameters.search?.trim()) {
    query.set("search", parameters.search.trim());
  }

  if (parameters.categoryId) {
    query.set("category_id", parameters.categoryId);
  }

  return apiRequest<StockProductListResponse>(`/stock/products?${query}`, {
    method: "GET",
    token,
  });
}

/** Admin only. Retains both purchase history and the original stock batch. */
export async function writeOffStockBatch(
  token: string,
  batchId: number,
  data: { reason: StockWriteOffReason; notes: string }
): Promise<InventoryBatchActionResponse> {
  return apiRequest<InventoryBatchActionResponse>(
    `/stock-batches/${batchId}/write-off`,
    {
      method: "POST",
      token,
      body: JSON.stringify(data),
    }
  );
}

/** Admin only. Only an untouched, purchase-linked Bag batch can be converted. */
export async function enableLooseStockBatch(
  token: string,
  batchId: number,
  data: {
    conversion_factor: number;
    secondary_selling_price: number;
    reason: string;
  }
): Promise<InventoryBatchActionResponse> {
  return apiRequest<InventoryBatchActionResponse>(
    `/stock-batches/${batchId}/enable-loose`,
    {
      method: "POST",
      token,
      body: JSON.stringify(data),
    }
  );
}
