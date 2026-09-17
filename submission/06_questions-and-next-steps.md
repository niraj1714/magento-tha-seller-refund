# 06 - Questions and Next Steps

## Questions before sign-off

- The FRD says the contractual window is 7 days, while BR-02 and the inherited config say 14. I treated the signed agreement and the explicit no-override rule as authoritative and changed the default to 7; product/legal should confirm the effective date and whether existing stores need a configuration migration.
- Section 4 says status read is initiated per refund by an operator, while section 9.2 says it is a scheduled batch. Should scheduled reconciliation be recovery-only, or is the operator action meant to enqueue one bounded job?
- Section 2 says the store allocates `refund_no`, while section 9.5 says ERP allocates and returns it. Which system owns allocation and what identifier is stable across a timeout before the response is received?
- Does the qualified receipt show the whole mixed order or only the seller portion? The current calculator is seller-scoped, while the presentation requirement says original order figures.
- What are the exact Settlement Adjustment Export schema, acknowledgement, retry, and reconciliation guarantees? It is financially authoritative but not otherwise represented in the module contract.

## Assumptions

I assumed the supplied local stub is the contract test authority, MariaDB is available for the outbox and locking guarantees, and no AMQP broker may be introduced for this assignment. I assumed the operator's store timezone must be used for delivery-window boundaries and that refund item snapshots are immutable after submit.

## Deliberate stopping point

I completed the written review/design, the contractual-window fix and regression tests, and the two convenience PRs. I did not attempt a broad worklist read-model rewrite, full customer-surface unification, or production load test because those are separate epics and the Docker daemon was inaccessible in this environment.

## Next steps

1. Run the full unit, integration, and smoke suites from a clean clone with Docker access.
2. Add the corrected ERP retry/idempotency/tax payload tests and update the supplied PR branches or close/reopen them after author changes.
3. Resolve the FRD ownership contradictions with Product, Finance, and ERP owners.
4. Add concurrent-submit, crash/replay, reconciliation, settlement-export, and presentation-contract tests before production sign-off.
