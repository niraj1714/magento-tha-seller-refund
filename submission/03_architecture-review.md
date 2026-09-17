# 03 - Architecture Review

## Sign-off findings and criteria

I ranked risks by whether they can create an incorrect financial result, lose a recoverable operation, or prevent the stated workload from being served. A design is not ready to sign off merely because the happy path works; it must preserve the local record, the ERP credit note, and their audit trail across crashes and retries.

### P0 - Optimistic browser state and synchronous external calls create duplicate or false outcomes

The proposed UI marks a row refunded before the request completes and leaves submit available. The controller then performs a 30-second HTTP call with immediate retries inside the admin request. A slow response can produce repeated submissions, exhausted PHP workers, or a browser that shows success while the local transaction rolled back.

**Response:** disable submit during the request, use a server idempotency token tied to the refund snapshot, commit local state and an outbox job first, and render only the server response. A worker performs bounded ERP attempts and returns a correlation ID.

### P0 - Retry and idempotency guarantees are underspecified

The design marks all 4xx responses failed, including 429, and has no dead-letter or operator replay model. A timeout after ERP acceptance is the dangerous case: the store cannot know whether to create again unless the same `refund_no` is reused and the ERP contract is explicit.

**Response:** classify 429/timeouts/5xx as retryable, keep the lifecycle state, use a durable outbox with leases/backoff/dead state, and make `refund_no` and `erp_refund_id` stable idempotency keys. Reconciliation must compare local and ERP identifiers without editing the database manually.

### P1 - The one-active-refund invariant is application-only

The proposal acknowledges concurrency but does not define a database lock or unique constraint. Two operators can read the same refundable quantity and both create a financial action.

**Response:** lock the order or seller-order row while recalculating and creating the snapshot, re-read current refunded quantities, and use CAS/version checks on later transitions. Add a database-backed invariant or an active-refund coordination row and test concurrent submissions.

### P1 - Worklist and settlement paths do not meet the scale assumptions

The worklist performs per-row order and line reads and renders the full collection. The daily scan filters an unindexed status/date path across 20,000 candidates. Both claims of sub-second/ten-minute operation are estimates without query plans or load measurements.

**Response:** server-side pagination with projected joins/aggregates, indexes on status/operation/available time and settlement predicates, bounded batches, and explain-plan plus representative load tests before release.

### P1 - Presentation logic is duplicated across channels

PDF, account, history, email, and export each calculate or assemble their own figures. That is likely to drift around tax, shipping allocation, partial refunds, or rounding.

**Response:** persist the immutable refund item/header snapshot once, expose a typed read model/DTO for presentation and export, and test every surface against the same fixture. Rendering may format it differently, but must not recalculate financial truth.

### P1 - Audit and security controls are described but not operationalised

The design says payloads are redacted but does not specify a redaction allowlist, retention, access audit, correlation propagation, or alerting for dead jobs and reconciliation gaps.

**Response:** define an allowlist schema for event payloads, encrypt/restrict audit data, propagate a request/correlation ID, retain immutable events under policy, and alert on retry exhaustion, identifier mismatch, and confirmation lag.

## Target-state extension

Keep Magento as the operator and customer-facing system, but make the refund record a durable local aggregate. A single transaction writes the refund header, item snapshots, created event, and Create outbox message. A DB-backed worker claims jobs with a lease, uses stable idempotency keys, records every attempt, and applies exponential backoff. The worker never holds an HTTP request open for ERP availability.

The order lock plus a current-quantity query protects creation; versioned state transitions protect cash registration, status read, confirm, and replay. Confirm is allowed only after a successful cash registration and an ERP pending read. A reconciliation command compares local records with ERP by `refund_no`/`erp_refund_id` and produces an authorised work item rather than silently mutating money.

The worklist becomes a paged SQL read model with seller, SKU, totals, sub-statuses, and last-error summary. Settlement/export scans use indexed bounded batches. All customer surfaces consume the same snapshot DTO, while the export has its own schema contract. Before production I would validate concurrency, crash-after-ERP-acceptance, replay, 429/backoff, tax rounding, PDF qualification, PII redaction, first-page latency, and clean-clone migration/rollback behavior in container tests and a volume-shaped load environment.
