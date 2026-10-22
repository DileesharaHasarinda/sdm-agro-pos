export interface StockBatchOption {
  id: number;
  batch_code: string;
  batch_number: string | null;
  purchase_cost: number;
  selling_price: number;
  available_quantity: number;
  expiry_date: string | null;
  received_at: string | null;

  received_quantity: number;
  purchase_item_id: number | null;
  is_dual_unit: boolean;
  stock_unit: string;
  secondary_unit: string | null;
  conversion_factor: number;
  secondary_selling_price: number | null;
}

export interface StockPriceOption {
  selling_price: number;
  available_quantity: number;
  batches: StockBatchOption[];
}

export interface StockProduct {
  id: number;
  name: string;
  sku: string | null;
  barcode: string | null;
  unit: string;
  category: { id: number; name: string } | null;
  total_available_quantity: number;
  stock_summary?: string;
  price_options: StockPriceOption[];
}

export interface StockPaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

export interface StockProductListResponse {
  data: StockProduct[];
  meta: StockPaginationMeta;
}

export type StockWriteOffReason =
  | 'expired'
  | 'damaged'
  | 'incorrect_entry'
  | 'other';

export interface InventoryBatchActionResponse {
  message: string;
  data: {
    batch_id: number;
    product_id: number;
    stock_movement_id: number;
    stock_unit: string;
    quantity_written_off?: number;
    available_quantity?: number;
  };
}
