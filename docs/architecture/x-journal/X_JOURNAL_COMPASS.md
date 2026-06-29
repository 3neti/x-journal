# x-journal Compass

## Mission

Make `x-journal` the evidentiary system log for the x-change Settlement Operating System.

The package records what happened across execution, claims, authorization, settlement, provider callbacks, reconciliation, operator actions, campaigns, and exceptions. It must not decide business policy, mutate money, or become lifecycle truth.

## Current Position

Current wave: Wave 2A — x-journal  
Current slice: Phase 6 — Verification and Integrity  
Status: Complete  
Last updated: 2026-06-29

## Phase Progress

| Phase | Focus | Status |
|---|---|---|
| 0 | Architectural foundation and package bootstrap | Complete |
| 1 | Core journal foundation | Phase 1B complete |
| 2 | Event transformation layer | Phase 2B complete |
| 3 | Sink architecture | Complete |
| 4 | Visibility and authorization | Complete |
| 5 | Artifact generation | Complete |
| 6 | Verification and integrity | Complete |
| 7 | Search and retrieval | Not started |
| 8 | x-change execution integration | Not started |
| 9 | Provider callback integration | Not started |
| 10 | Reconciliation integration | Not started |
| 11 | Operator action integration | Not started |
| 12 | Campaign integration | Not started |
| 13 | Cockpit integration | Not started |
| 14 | Hardening | Not started |
| 15 | Production readiness | Not started |

## Completed Work

- Read the planning documents under `/Users/rli/PhpstormProjects/x-change-sandbox/docs/todo/x-journal`.
- Confirmed the original expected package path was missing before bootstrap.
- Created the independent package root at `/Users/rli/PhpstormProjects/packages/x-journal`.
- Established the package namespace `LBHurtado\XJournal`.
- Added Composer package metadata and Laravel package auto-discovery.
- Added the initial package service provider and configuration file.
- Added Pest/Testbench bootstrap tests for package registration.
- Began Phase 1A with tests and minimal implementation for core journal persistence, ERN generation, canonical data objects, recorder behavior, database sink behavior, and append-only guards.
- Added `spatie/laravel-data` and converted the journal DTO layer to Spatie Data objects.
- Added `ExecutionJournalEntry` and `execution_journal_entries` as the initial durable journal store.
- Added `ExecutionReferenceNumberGenerator`, `ExecutionJournalRecorder`, and `DatabaseJournalSink`.
- Added append-only model guards for update/delete attempts.
- Completed Phase 1B hardening:
  - counter-backed ERN sequencing by prefix/year
  - scalar projection columns for common journal queries
  - deterministic integrity hash calculation
  - previous-hash chaining between persisted journal entries
  - package-consumer sink replacement seam via `JournalSinkContract`
- Completed Phase 2 Event Transformation Layer:
  - `JournalEventData` for normalized incoming event payloads
  - `JournalEventTransformerContract`
  - `JournalEventTransformerRegistry`
  - `JournalEventRecorder`
  - `ExecutionResultJournalTransformer`
  - clear unsupported-event failure behavior
  - package-consumer transformer registration seam
- Completed Phase 2B Domain Event Transformer Baselines:
  - claim lifecycle transformer baseline
  - provider callback transformer baseline
  - reconciliation transformer baseline
  - tests proving domain transformers normalize events without deciding outcomes
- Completed Phase 3 Sink Architecture:
  - `SecondaryJournalSinkContract`
  - `JournalSinkDispatcher`
  - database sink retained as canonical default
  - secondary sink fan-out for projections/exports
  - tests proving secondary sinks do not become canonical journal truth
- Completed Phase 4 Visibility and Authorization:
  - `JournalAccessActorData`
  - `JournalAccessDecisionData`
  - `JournalVisibilityPolicyContract`
  - `JournalVisibilityGate`
  - default actor-or-subject visibility policy
  - explicit `x-journal.view` permission support
  - package-consumer visibility policy seam
- Completed Phase 5 Artifact Generation:
  - `JournalArtifactProfileData`
  - `JournalArtifactData`
  - `JournalArtifactRendererContract`
  - `JournalArtifactGenerator`
  - text receipt renderer baseline
  - text statement renderer baseline
  - unsupported-profile failure behavior
  - package-consumer artifact renderer registration seam
- Completed Phase 6 Verification and Integrity:
  - `JournalIntegrityIssueData`
  - `JournalIntegrityVerificationData`
  - `JournalIntegrityVerifier`
  - clean hash-chain verification
  - canonical payload tamper detection
  - previous-hash continuity verification
  - missing-hash detection
  - tests proving verification does not mutate journal entries
  - explicit unsigned baseline behavior for future signature work

## Discoveries

- `docs/todo/x-journal/x-journal_codex_instructions.md` is empty.
- `docs/todo/x-journal/x-journal_codex_instructions_addendum.md` contains the operative Compass rules.
- The functional specifications and 01–05 planning files are coherent enough to guide Phase 0 and Phase 1.
- Phase 1A uses `spatie/laravel-data` DTOs, matching the planning docs and local package conventions.
- The initial journal schema stores canonical actor, subject, money, references, payload, integrity, and metadata as JSON.
- Scalar projections are now stored beside the canonical JSON payload for actor, subject, correlation, causation, and execution IDs.
- Event transformers normalize raw event payloads into journal-entry data; they do not decide lifecycle outcomes.
- The first built-in transformer supports `execution.*` event types only.
- Built-in domain transformer baselines now support `claim.*`, `provider.*`, and `reconciliation.*` event types.
- Sink architecture now separates canonical persistence from secondary projection/export sinks.
- Visibility checks are read-side access decisions and do not mutate journal entries.
- Artifact generation can be implemented as an in-memory rendering layer over canonical journal entries without adding artifact persistence.
- Receipt and statement artifacts can carry journal reference numbers so generated outputs remain traceable back to canonical entries.
- Verification can recompute existing deterministic hashes from persisted canonical journal payloads without changing persistence.
- Broken previous-hash continuity is distinguishable from canonical payload tampering through issue codes.
- Signatures are already represented in integrity data, but Phase 6 does not require or validate signatures.

## Risks

- The empty primary instruction file can confuse future agents unless the addendum remains treated as authoritative.
- ERN generation now uses a dedicated counter table with row locking.
- x-journal must remain independent of voucher execution internals and x-change product workflow decisions.
- Current append-only enforcement is model-event based; direct database updates remain outside this slice.
- Hash chaining is deterministic but not yet signed; signature strategy remains a later verification concern.
- Phase 2 intentionally avoids dependencies on voucher or x-change classes; integration adapters remain future work.
- Domain transformer baselines intentionally preserve raw domain payloads instead of interpreting success, failure, discrepancy resolution, or required next actions.
- Secondary sinks currently execute synchronously after canonical database persistence.
- Default visibility allows matching journal actor, matching journal subject, or explicit `x-journal.view` permission.
- Artifact persistence, signatures, public URLs, storage disks, and PDF generation are intentionally deferred.
- Generated artifacts currently render from the entries supplied by the caller; authorization and query scoping must happen before artifact generation in consuming applications.
- Phase 6 verification detects tampering after the fact; it does not prevent direct database mutation.
- Verification currently operates over the entries supplied by the caller or the full ordered journal table; scoped verification windows need careful handling in future retrieval/integration work.
- Signature policy remains deferred and should be designed before treating generated artifacts as externally verifiable legal/evidentiary bundles.

## Architectural Decisions

- x-journal is the evidentiary layer, not the operational database.
- x-journal records what happened; it does not decide what should happen.
- Journal entries must be append-only once core persistence exists.
- Every future journal entry must have an evidence/reference number.
- Initial ERN format is `ERN-YYYY-#########`.
- Initial DTO strategy is `spatie/laravel-data` with explicit canonical array normalization for journal payloads.
- ERN sequencing uses `execution_journal_reference_counters` keyed by prefix and year.
- x-journal stores scalar projection columns for common query dimensions while retaining canonical JSON payloads.
- Integrity hashing uses SHA-256 over a canonical execution-journal payload and includes `previous_hash`.
- Event transformation is package-local and contract-driven.
- Unsupported event types fail clearly with `JournalEventTransformerNotFoundException`.
- Claim, provider, and reconciliation domain events are normalized through dedicated transformers.
- `JournalSinkContract` resolves to `JournalSinkDispatcher`.
- `DatabaseJournalSink` remains the canonical durable sink.
- `SecondaryJournalSinkContract` is for projections/exports only.
- `JournalVisibilityGate` controls read visibility and does not alter journal truth.
- Artifacts are renderings of journal truth; they are not canonical journal truth.
- Canonical journal entries remain the durable source of evidence.
- Built-in Phase 5 artifact formats are text/plain receipt and statement baselines.
- Artifact renderer registration is contract-driven so host packages can add PDF, JSON, or domain-specific renderers later.
- Integrity verification is a read-side diagnostic service; it does not mutate journal entries or enforce business policy.
- Verification returns structured issue data instead of throwing for ordinary corruption findings.
- Phase 6 verifies SHA-256 payload hashes and previous-hash continuity.
- Phase 6 intentionally does not require signatures; signing remains a future hardening decision.
- Execution Engine remains journal-ready but not journal-dependent.
- Monolog/log files may be sinks or technical diagnostics, but they are not canonical journal truth.

## Test Coverage Status

- Package bootstrap tests exist for configuration and service-provider registration.
- Core journal foundation tests cover entry persistence, ERN generation, append-only behavior, DTO normalization, recorder behavior, and database sink behavior.
- Artifact generation tests cover profile normalization, receipt rendering, statement rendering, non-mutating artifact generation, unsupported renderer failure, and package-consumer renderer registration.
- Verification and integrity tests cover clean chains, payload tampering, broken previous-hash continuity, missing hashes, non-mutating verification, and unsigned baseline behavior.
- Full x-journal package suite is green: `55 passed, 165 assertions`.

## Next Recommended Slice

Phase 7 — Search and Retrieval.

Recommended next coverage:

- Query object or service for finding journal entries by reference, actor, subject, correlation, causation, execution ID, and event type.
- Pagination/windowing baselines for operator and API consumption.
- Tests proving retrieval is read-only and does not bypass visibility decisions when paired with the visibility gate.
- Clear separation between retrieval, authorization, and artifact generation.

## Open Questions

- Which host package should first consume x-journal: x-change adapters, voucher events, or a dedicated integration layer?
- What signature strategy should be used for future tamper-evident verification artifacts?
- Should default transformers be configured declaratively through config or only registered programmatically?
- Should secondary sinks remain synchronous or move to queued dispatch before production use?
- Should visibility policies be configured declaratively through config or composed programmatically by host packages?
- Which artifact formats should become first-class beyond text/plain baselines: PDF, JSON, CSV, or signed bundles?
- Where should persisted artifacts live once storage is introduced: local disk, S3-compatible storage, or host-package controlled disks?
- Should verification support scoped windows with an externally supplied starting previous hash?
- What signature algorithm and key-management strategy should be used for externally verifiable artifacts?
