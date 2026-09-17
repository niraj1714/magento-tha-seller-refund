# 01 - Code Review

## Scope and ranking

I reviewed both branches against `main`, then followed the changed callers into the refund state machine, outbox, tax resolver, resource models, and unit tests. I ranked findings by financial or lifecycle impact first, then by likelihood, recoverability, and whether the defect can silently produce an incorrect customer or ERP record.

## Findings

### P0 - PR-02 breaks the retry contract and terminally fails transient ERP errors

`review/pr-02-erp-refund-sync`, `Controller/Adminhtml/Refund/Save.php` around lines 88-101, catches the common `ErpException` base class and transitions every ERP error to `failed` with `business_rejected`. That includes the `ErpTransientException` used for timeouts, HTTP 429, and 5xx responses. The FRD explicitly requires those errors to leave the main status unchanged, set the operation sub-status to `retryable_error`, and remain replayable. This change would turn a temporary ERP outage into a terminal financial workflow that requires manual data repair, and the response does not expose a usable retry state.

**Review comment:** Please catch business rejection separately from transient ERP failures. A timeout/429/5xx must commit the local refund, record the attempt, keep it at `calculated`, and leave or enqueue a retryable Create operation. Only a definitive business rejection may transition to `failed`. Add a test for HTTP 503 and verify both the main status and sub-status.

### P0 - PR-02 changes the Create Refund idempotency key

`Model/Erp/PayloadBuilder.php` around lines 41-43 sends `RequestKey::getValue()` as `refund_no`; `RequestKey` appends `-02`, `-03`, and so on for later attempts. The FRD requires the persisted `refund_no` as the stable Create Refund idempotency key. A timeout after ERP acceptance followed by a suffixed retry can therefore be treated as a new credit note instead of the same request. The separate request/correlation key is useful for audit, but it must not replace the business idempotency key.

**Review comment:** Keep `refund_no` unchanged in the JSON payload on every attempt. Send the per-attempt key only as the transport correlation/request header and record it on the event. Add a retry test asserting that the body keeps the original refund number while the audit key changes.

### P0 - PR-02 sends local option IDs where ERP requires business tax codes

`Model/Erp/PayloadBuilder.php` around lines 55-60 calls `toOptionId()` on the stored business code. The calculator stores `010`, `008`, or `999` by calling `TaxCodeResolver::toBusinessCode()`, and the FRD says those stable values are exactly what ERP expects. The proposed payload consequently sends environment-specific IDs such as `7` or `6`, which can be rejected or, worse, interpreted as another tax code.

**Review comment:** Do not resolve a stored business code back to an EAV option ID on the outbound path. Preserve the snapshot value in `taxes[].code`; reserve `toOptionId()` for inbound synchronization. Add coverage for all supported codes and assert the exact ERP values, not merely that a tax entry exists.

### P1 - PR-01 reports prior refunded quantity including the refund being printed

`Model/Pdf/RefundReceipt.php` around lines 65 and 120-127 calls `loadRefundedQtyByOrder()` after the current refund and its items already exist. The resource query excludes only cancelled and failed rows, so it includes the current refund. The displayed `refunded_before` quantity is therefore inflated by the quantity on the receipt itself. That is customer-visible and makes the qualified invoice internally misleading when the line had a previous refund.

**Review comment:** Exclude the current `refund_id` from the aggregate, or calculate the before quantity from the immutable item snapshot plus prior refunds explicitly. Add a regression case with one earlier refund and the current refund and assert the before quantity is not increased by the current request.

### P1 - PR-02 adds an N+1 worklist path at the stated operating scale

`Block/Adminhtml/Refund/Worklist.php` iterates the collection and loads one order and one item collection per row. At 40 concurrent operators and a growing refund table, this makes first-page latency proportional to the full collection and conflicts with the FRD's 1.5-second first-page target. The branch adds columns but does not add server-side paging or joins/aggregates.

**Review comment:** Keep the grid paged and project seller/order/line summary data in one SQL-backed collection (or a purpose-built read model). Add an explain-plan and a bounded first-page integration test before accepting this for production volume.

## Recommendation

I would request changes on both branches. PR-01 has useful presentation coverage but must correct the current-refund quantity aggregation. PR-02 should be redesigned around the existing durable outbox and state machine rather than introducing a synchronous controller transaction; its tax and idempotency payload defects must be fixed before any ERP testing is considered meaningful.
