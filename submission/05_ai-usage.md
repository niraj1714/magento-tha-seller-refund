# 05 - AI Usage

I used GitHub Copilot as a coding and review assistant throughout the assignment. I asked it to inspect the supplied branch diffs, trace changed code into callers and state transitions, identify contract mismatches against the FRD, draft focused unit tests, and edit the submission documents.

I treated repository code, the FRD, architecture note, and test results as the source of truth. I reviewed the generated reasoning, checked the relevant PHP and XML files, and chose the final findings and design trade-offs myself. In particular, I rejected a generic retry recommendation in favor of the concrete defects visible in PR-02: transient ERP errors being terminalised, business idempotency being changed, and tax codes being converted in the wrong direction.

Copilot also helped create the Part 3 eligibility regression test. I ran PHP syntax checks and editor diagnostics. The container unit command could not run because the current user lacks permission to access `/var/run/docker.sock`; that limitation is recorded rather than presented as a passing test.
