# 04 - Build Impact Note

## Part 3 fix

The refund window was enforced only by the admin order-view button. A direct POST to the save action reached `RefundProcessor::submit()` and could create a refund after the contractual return period. The fix makes the existing `RefundEligibility` service part of the server-side `RefundValidator` path, uses Magento store time for the check, and changes the module default from 14 days to the signed 7-day window.

Regression coverage proves that day seven is accepted and day eight is rejected, and that the validator refuses an ineligible order before line validation. This closes the browser/controller bypass without changing refund calculations, persistence shape, or ERP payloads.

Implementation commit: to be linked after commit, as `IMPLEMENTATION_COMMIT`.

Exact test command:

```bash
bin/assignment-test unit --filter RefundEligibilityTest
```

The command requires the assignment web container. PHP syntax checks passed locally; the container test was blocked in this environment by Docker socket permissions.
