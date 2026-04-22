# Money-Related Entities Report — Osool-B2G

_Generated: 2026-04-12_

This report maps every model, table, and service in the Osool-B2G Laravel codebase that deals with money: expenses, costs, payments, incomes, invoices, payroll, fees, deposits, and related financial data.

---

## 1. Marketplace / Bids

| Model → Table | Money-related columns | Relations | How to retrieve |
|---|---|---|---|
| `App\Models\Bid` → `bids` | `service_cost` (decimal 10,2), `additional_cost` (decimal 10,2) | `serviceRequest()`, `vendor()` → VendorProfile, `additionalCosts()` | `ServiceRequest::with('bids')->find($id)` or `BidRepository` |
| `App\Models\BidAdditionalCost` → `bid_additional_cost` | `price`, `type` | `bid()` | `$bid->additionalCosts` |
| `App\Models\ServiceRequest` → `service_requests` | `base_price` | `bids()`, `acceptedBid()` | via bid's `serviceRequest()` |

**Total cost of a bid** = `service_cost + additional_cost + SUM(bid_additional_cost.price)`.

---

## 2. Contracts (Execution)

| Model → Table | Money-related columns | Relations | How to retrieve |
|---|---|---|---|
| `App\Models\Contracts` → `contracts` | `contract_value`, `retention_percent`, local workforce percentages | `contractPerformanceIndicators()`, `usableItems()`, `completionReports()` | `ContractPaymentService::getPaymentTrackingData($contract)` returns pending / overdue / paid plus WorkOrder extras |
| `App\Models\ContractPayroll` → `contract_payrolls` | `file_status` (Pending/Review/Approved/Rejected), `file_path` | `contractPayrollType()`, `scheduleType()`, `payrollRejections()` | `$contract->payrolls` |
| `App\Models\ContractPayrollRejection` → `contract_payroll_rejections` | `rejection_reason`, `file_status` | belongsTo ContractPayroll | `$payroll->payrollRejections` |

Payroll amounts themselves live in uploaded files and in Akaunting — only workflow state is tracked in these tables.

---

## 3. Work Orders

- `App\Models\WorkOrders` → `work_orders` links into contracts as "extras" and feeds contract cost tracking.
- Invoicing connection: junction table `workorder_invoices` maps WOs to Akaunting invoices.
- `ContractPaymentService` sums WO extras against the contract value.

---

## 4. Lease / Tenant Billing

| Model → Table | Money-related columns | Relations | How to retrieve |
|---|---|---|---|
| `App\Models\CommercialContracts` → `commercial_contracts` | `amount`, `security_deposit_amount`, `late_fees_charge`, `brokerage_fee`, `retainer_fee`, `payment_due`, `payment_overdue`, `currency`, `lessor_iban` | `tenants()` → User, `units()`, `leaseContractDetails()`, `propertyBuildings()` | `CommercialContracts::with('tenants','units','leaseContractDetails')->find($id)` |
| `App\Models\PaymentDetails` → `payment_details` | `amount`, `is_paid`, `payment_due_date`, `payment_date`, `installment_end_date`, `date_before_due_date` | `contract()` → CommercialContracts, `tenant()` → User, `createdBy()` → User | `PaymentDetails::fetchShowpaymentdetails($contract_id)` |
| `App\Models\LeaseContractDetail` → `lease_contract_details` | lease-specific terms | reverse of CommercialContracts | `$commercialContract->leaseContractDetails` |

**Outstanding tenant receivables** = `payment_details` where `contract_id = X` AND `is_paid = 0`.

---

## 5. Purchase & Sales (Procurement / AP / AR)

Full pipeline:
`PurchaseRequest → Quotation → PurchaseOrder → Bill` (Accounts Payable) and `SalesOrder → Invoice` (Accounts Receivable).

All models live under `app/Models/PurchaseAndSales/`.

| Model → Table | Money-related columns | Notes |
|---|---|---|
| `PurchaseRequest` → `purchase_requests` | `amount` | Has `PurchaseRequestLine` children |
| `Quotation` → `quotations` | `amount`, `currency_code` | Lines: `price`, `quantity`, `tax`, `discount_rate`, `total`. Status: Draft / Pending / Issued / Sent / Expired / Approved / Confirmed / Invoiced / Refused / Cancelled |
| `PurchaseOrder` → `purchase_orders` | `amount`, `currency_code`, `currency_rate`, `amount_admin_validation` | `PurchaseOrderLine` has price/qty/tax/discount/total |
| `Bill` → `bills` | `amount`, `currency_code`, `currency_rate` | `BillLine` → `bills_lines`: price/qty/tax/discount_type/discount_rate/total. Status: Draft / Sent / Viewed / Confirmed / Cancelled |
| `SalesOrder` → `sales_orders` | `amount`, `currency_code` | Links 1:1 to Invoice |
| `Invoice` → `invoices` | `amount`, `currency_code`, `currency_rate` | `InvoiceLine` → `invoice_lines`: price/qty/tax/discount/total. Status: Draft / Sent / Viewed / Confirmed / Payed / Cancelled |

All AP/AR records sync to **Akaunting** (external accounting system) via `MappingOsoolAkaunting`.

---

## 6. Services & Repositories

| Class | Purpose |
|---|---|
| `App\Repositories\BidRepository` | `placeBid`, `getBidsForRequest`, `acceptBid`, `reviceBid`, `rejectBid` |
| `App\Services\ContractPaymentService` | `getPaymentTrackingData(Contracts $contract)` — pending/overdue/paid + WO extras |
| `App\Services\Finance\FinancePaymentService` | Akaunting-backed payments: `list`, `create`, `dropdownlist` |
| `App\Services\CRM\Sales\InvoiceService` | Invoice CRUD + Akaunting sync |

---

## 7. Domain Relationship Summary

- **Contracts** ←→ **ContractPayroll** (+ rejections) and **WorkOrders** (extras)
- **CommercialContracts** ←→ **PaymentDetails** (installments / due dates)
- **ServiceRequest** ←→ **Bid** ←→ **BidAdditionalCost**
- **PurchaseRequest → Quotation → PurchaseOrder → Bill** (AP flow)
- **SalesOrder → Invoice** (AR flow)
- All invoice/bill/payment records sync to **Akaunting** via `MappingOsoolAkaunting`

---

## 8. Quick Cheatsheet — "Where is the money?"

| Question | Answer |
|---|---|
| Marketplace bid total | `bids.service_cost + bids.additional_cost + SUM(bid_additional_cost.price)` per `service_request_id` |
| Contract spend | `contracts.contract_value` + `ContractPaymentService` WO extras |
| Tenant outstanding receivables | `payment_details WHERE contract_id = X AND is_paid = 0` |
| Accounts Payable (expenses) | `bills` + `bills_lines.total` |
| Accounts Receivable (income) | `invoices` + `invoice_lines.total` where status = `Payed` |
| Payroll state | `contract_payrolls.file_status` (actual values in uploaded files + Akaunting) |
| Deposits / lease fees | `commercial_contracts.security_deposit_amount`, `late_fees_charge`, `brokerage_fee`, `retainer_fee` |

---

## 9. Notes & Caveats

- There is **no unified `transactions` / `wallet` / `ledger` table** in this codebase.
- **Akaunting** is the source of truth for posted financial records; Osool stores workflow state + line-item details that mirror into Akaunting.
- The `bids` table is **not** connected to internal `WorkOrders`, `Contracts`, or `ServiceProvider` tables — only to `MarketplaceWorkOrder` via shared `service_request_id`.
- "Vendors" (bid side) = `vendor_profiles`; this is distinct from `service_providers`.
