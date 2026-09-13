import type { PosPaymentMethod } from "./sale";

export interface ReportPeriod {
  date_from: string;
  date_to: string;
}

export interface ReportSummary {
  sales_count: number;
  sales_total: number;
  collected_amount: number;
  due_amount: number;
  supplier_due_purchase_count: number;
  supplier_due_amount: number;
  supplier_due_payment_count: number;
  supplier_due_payment_total: number;
  discount_total: number;
  gross_profit: number;
  sale_net_profit: number;
  return_count: number;
  return_refund: number;
  profit_reversal: number;
  expense_count: number;
  expense_total: number;
  final_net_profit: number;
}

export interface ReportDailyPoint {
  date: string;
  sales: number;
  collections: number;
  expenses: number;
  returns: number;
  supplier_payments: number;
}

export interface ReportPaymentBreakdown {
  payment_method: PosPaymentMethod;
  transactions: number;
  total: number;
}

export interface ReportProductPerformance {
  id: number;
  name: string;
  unit: string;
  category: {
    id: number;
    name: string;
  };
  quantity_sold: number;
  sales_total: number;
  cost_total: number;
  gross_profit: number;
}

export interface ReportExpenseCategory {
  id: number;
  name: string;
  expense_count: number;
  total_amount: number;
}

export interface ReportCustomerDue {
  id: number;
  customer_code: string;
  name: string;
  mobile: string | null;
  due_sales: number;
  sales_total: number;
  paid_amount: number;
  due_amount: number;
}

export interface ReportSupplierDue {
  id: number;
  name: string;
  contact_person: string | null;
  phone: string | null;
  due_purchases: number;
  purchase_total: number;
  paid_amount: number;
  due_amount: number;
  oldest_due_date: string | null;
}

export interface ReportSupplierDuePayment {
  id: number;
  supplier_id: number;
  supplier_name: string;
  contact_person: string | null;
  phone: string | null;
  purchase_id: number;
  purchase_number: string;
  payment_method: string;
  payment_type: "initial_payment" | "supplier_due_payment";
  amount: number;
  reference_number: string | null;
  notes: string | null;
  payment_date: string;
}

export interface ReportInventorySummary {
  quantity: number;
  purchase_value: number;
  retail_value: number;
  expiring_batch_count: number;
  expired_batch_count: number;
}

export interface ReportInventoryProduct {
  id: number;
  name: string;
  unit: string;
  category: {
    id: number;
    name: string;
  };
  quantity: number;
  purchase_value: number;
  retail_value: number;
  batch_count: number;
  nearest_expiry: string | null;
}

export interface ReportInventory {
  summary: ReportInventorySummary;
  products: ReportInventoryProduct[];
}

export interface ReportOverviewData {
  period: ReportPeriod;
  summary: ReportSummary;
  daily_series: ReportDailyPoint[];
  payment_breakdown: ReportPaymentBreakdown[];
  product_performance: ReportProductPerformance[];
  expense_categories: ReportExpenseCategory[];
  customer_dues: ReportCustomerDue[];
  supplier_dues: ReportSupplierDue[];
  supplier_due_payments: ReportSupplierDuePayment[];
  inventory: ReportInventory;
  generated_at: string;
}

export interface ReportOverviewResponse {
  data: ReportOverviewData;
}

export interface ReportParameters {
  dateFrom: string;
  dateTo: string;
  paymentMethod?: string;
  paymentStatus?: string;
}
