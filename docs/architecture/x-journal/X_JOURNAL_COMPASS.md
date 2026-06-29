# x-journal Compass

## Mission

Make `x-journal` the evidentiary system log for the x-change Settlement Operating System.

The package records what happened across execution, claims, authorization, settlement, provider callbacks, reconciliation, operator actions, campaigns, and exceptions. It must not decide business policy, mutate money, or become lifecycle truth.

## Current Position

Current wave: Wave 2A — x-journal  
Current slice: Phase 12 — Campaign Integration  
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
| 7 | Search and retrieval | Complete |
| 8 | x-change execution integration | Complete |
| 9 | Provider callback integration | Complete |
| 10 | Reconciliation integration | Complete |
| 11 | Operator action integration | Complete |
| 12 | Campaign integration | Complete |
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
- Completed Phase 7 Search and Retrieval:
  - `JournalRetrievalQueryData`
  - `JournalRetrievalResultData`
  - `JournalEntryRetriever`
  - reference-number lookup
  - actor and subject projection filters
  - correlation, causation, execution ID, and event-type filters
  - bounded limit/offset windowing
  - deterministic ascending/descending ordering
  - tests proving retrieval does not mutate journal entries
- Completed Phase 8 x-change Execution Integration:
  - `XChangeExecutionOutcomeData`
  - `XChangeExecutionJournalRecorder`
  - execution outcome normalization from plain arrays
  - voucher `execution_id` mapping into journal references
  - successful execution outcome recording
  - failed execution outcome recording without recovery decisions
  - retrieval of recorded execution outcomes by execution ID
  - tests proving supplied execution outcome data is not mutated
  - no x-change or voucher call sites changed in this slice
- Completed Phase 9 Provider Callback Integration:
  - `ProviderCallbackData`
  - `ProviderCallbackJournalRecorder`
  - provider callback payload normalization
  - provider reference and execution reference preservation
  - raw provider callback payload preservation
  - failed provider callback recording without settlement/reconciliation/next-action decisions
  - retrieval of provider callbacks by execution and provider references
  - tests proving supplied provider callback data is not mutated
- Completed Phase 10 Reconciliation Integration:
  - `ReconciliationEventData`
  - `ReconciliationJournalRecorder`
  - reconciliation comparison payload normalization
  - expected/actual/comparison fact preservation
  - discrepancy recording without correction, settlement, or next-action decisions
  - retrieval of reconciliation entries by execution and provider references
  - tests proving supplied reconciliation event data is not mutated
- Completed Phase 11 Operator Action Integration:
  - `OperatorActionData`
  - `OperatorActionJournalRecorder`
  - `OperatorActionJournalTransformer`
  - operator action payload normalization
  - actor, action, context, and target reference preservation
  - blocked/denied operator action recording without workflow mutation, execution, money movement, or CTA completion
  - retrieval of operator action entries by execution and causation references
  - tests proving supplied operator action data is not mutated
- Completed Phase 12 Campaign Integration:
  - `CampaignEventData`
  - `CampaignJournalRecorder`
  - `CampaignJournalTransformer`
  - campaign event payload normalization
  - campaign, program, beneficiary-list, distribution, and voucher batch context preservation
  - campaign batch fact recording without voucher issuance, execution decisions, campaign state mutation, or distribution dispatch
  - retrieval of campaign entries by execution and program/causation references
  - tests proving supplied campaign event data is not mutated
  - updated unsupported-transformer coverage to use an actually unsupported `exception.*` event now that `campaign.*` is supported

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
- Search and retrieval can use existing scalar projection columns introduced in Phase 1B without schema changes.
- Retrieval is intentionally separate from visibility decisions; consuming layers must pair retrieval with `JournalVisibilityGate` when exposing entries.
- Bounded limit/offset windows are enough for the package baseline; cursor pagination can be introduced later if operator views need it.
- Voucher execution results currently expose `execution_id`, `successful`, `status`, `driver`, `events`, `failure`, provider references, reconciliation, children, and metadata.
- x-journal can accept execution outcome payloads as plain arrays, avoiding a hard Composer/runtime dependency on `3neti/voucher`.
- x-change execution integration can be introduced as an adapter seam before any x-change call sites are wired to it.
- Provider callbacks can use the existing `provider.*` transformer baseline while adding a package-local DTO/recorder seam for host integrations.
- Provider callback recording can preserve raw provider payloads without normalizing provider-specific status codes into lifecycle truth.
- Reconciliation events can use the existing `reconciliation.*` transformer baseline while adding a package-local DTO/recorder seam for host integrations.
- Expected, actual, and comparison payloads are enough for the baseline to preserve reconciliation evidence without resolving discrepancies.
- Operator action events require a dedicated `operator.*` transformer baseline because earlier domain transformer baselines intentionally covered only claim, provider, and reconciliation events.
- Operator action recording can preserve action intent, denial/blocking facts, context, subject, execution, provider, and causation references without invoking or completing the action.
- Campaign events require a dedicated `campaign.*` transformer baseline because campaign/program distribution facts are distinct from execution, provider, reconciliation, and operator action facts.
- Campaign event recording can preserve planning, beneficiary-list, distribution, and batch evidence without depending on x-campaign classes or causing voucher issuance.

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
- Retrieval services can expose sensitive journal entries if host packages do not compose them with visibility policies before API/UI exposure.
- Offset-based pagination is simple and deterministic for the baseline, but may need cursor pagination for large production journals.
- Phase 8 does not yet wire live x-change execution call sites; a later slice must characterize and test those call sites before integration.
- Duplicate journal entries are possible if a host calls the execution integration adapter repeatedly for the same execution result; idempotency remains unresolved.
- The adapter records execution outcomes after execution; it must not be moved into a path that can alter execution semantics or money movement.
- Duplicate provider callbacks are possible if a provider retries the same webhook; idempotency remains unresolved.
- Provider callback status labels are currently recorded as supplied. Host packages must not treat x-journal callback records as settlement truth without domain reconciliation.
- Reconciliation records can duplicate if host reconciliation jobs are retried; idempotency remains unresolved.
- Reconciliation payloads may contain sensitive provider/bank file data; host APIs must pair retrieval with visibility/redaction policies before exposure.
- Operator action records can duplicate if host applications retry the same command/audit hook; idempotency remains unresolved.
- Operator action records may contain sensitive operator context, reasons, IP addresses, case details, and manual review notes; host APIs must pair retrieval with visibility/redaction policies before exposure.
- Host UIs and workflow layers must not treat an operator action journal record as proof that the action was authorized, executed, or completed unless the corresponding domain event also exists.
- Campaign records can duplicate if host batch planners or distribution jobs retry journal recording; idempotency remains unresolved.
- Campaign records may contain sensitive beneficiary-list counts, targeting criteria, program details, distribution schedules, and voucher batch identifiers; host APIs must pair retrieval with visibility/redaction policies before exposure.
- Host campaign layers must not treat campaign journal records as voucher issuance, execution, distribution dispatch, or campaign lifecycle mutation.

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
- Retrieval is read-only query infrastructure; it does not authorize, redact, generate artifacts, verify integrity, or decide workflow outcomes.
- Phase 7 retrieval filters operate on indexed scalar projections where available.
- Query windows are bounded to a maximum limit of 200 entries in the baseline DTO.
- Visibility remains a separate layer composed by consumers or future integration services.
- x-change execution integration is an observational recording seam, not an execution dependency.
- x-journal does not depend on voucher classes; execution outcome data is accepted through package-local DTO normalization.
- `execution_id` is the primary correlation bridge from voucher execution into x-journal references.
- Failed execution outcomes are recorded as facts; x-journal does not decide retry, recovery, reversal, or next action.
- Live x-change/voucher call-site wiring is deferred until characterization tests exist around those call sites.
- Provider callback integration is an observational recording seam, not a provider adapter and not a reconciliation engine.
- Raw provider callback payloads are preserved as evidence.
- Provider callback records may include provider status labels, but x-journal does not interpret those labels as settlement success, failure, or reconciliation decisions.
- `provider_reference`, `execution_id`, and subject references form the Phase 9 correlation bridge.
- Reconciliation integration is an observational recording seam, not a settlement engine and not a discrepancy resolver.
- Reconciliation records preserve expected facts, actual facts, and comparison facts.
- Reconciliation records may indicate mismatch facts, but x-journal does not decide corrections, reversals, settlement state, or next actions.
- `provider_reference`, `execution_id`, and subject references form the Phase 10 reconciliation correlation bridge.
- Operator action integration is an observational recording seam, not an action executor, authorization engine, workflow engine, or CTA completion mechanism.
- Operator action records preserve actor/action/context facts and target references.
- Blocked or denied operator actions are first-class audit facts.
- `causation_id`, `execution_id`, `provider_reference`, external references, and subject references form the Phase 11 operator action correlation bridge.
- Campaign integration is an observational recording seam, not a campaign engine, issuance engine, distribution dispatcher, or execution coordinator.
- Campaign records preserve campaign, program, beneficiary-list, distribution, and voucher batch facts.
- `causation_id`, `execution_id`, external references, reference metadata, and subject references form the Phase 12 campaign correlation bridge.
- Execution Engine remains journal-ready but not journal-dependent.
- Monolog/log files may be sinks or technical diagnostics, but they are not canonical journal truth.

## Test Coverage Status

- Package bootstrap tests exist for configuration and service-provider registration.
- Core journal foundation tests cover entry persistence, ERN generation, append-only behavior, DTO normalization, recorder behavior, and database sink behavior.
- Artifact generation tests cover profile normalization, receipt rendering, statement rendering, non-mutating artifact generation, unsupported renderer failure, and package-consumer renderer registration.
- Verification and integrity tests cover clean chains, payload tampering, broken previous-hash continuity, missing hashes, non-mutating verification, and unsigned baseline behavior.
- Search and retrieval tests cover query normalization, reference lookup, actor/subject filters, correlation/causation/execution/event filters, deterministic windows, descending order, and non-mutating retrieval.
- x-change execution integration tests cover outcome normalization, successful recording, failed recording without decisions, explicit reference precedence, retrieval by execution ID, and non-mutating recording.
- Provider callback integration tests cover payload normalization, fact recording, raw failed callback preservation without decisions, retrieval by execution/provider references, and non-mutating recording.
- Reconciliation integration tests cover comparison normalization, fact recording, discrepancy preservation without decisions, retrieval by execution/provider references, and non-mutating recording.
- Operator action integration tests cover payload normalization, audit fact recording, denied/blocked action preservation without workflow mutation, retrieval by execution/causation references, and non-mutating recording.
- Campaign integration tests cover payload normalization, audit fact recording, batch fact preservation without issuance/execution/distribution decisions, retrieval by execution/program references, and non-mutating recording.
- Full x-journal package suite is green: `88 passed, 410 assertions`.

## Next Recommended Slice

Phase 13 — Cockpit Integration.

Recommended next coverage:

- Cockpit event/query payload normalization.
- Cockpit-facing journal retrieval/read-model seam.
- Tests proving Cockpit integration exposes journal facts without inventing domain behavior, bypassing visibility, mutating entries, or executing operator actions.
- Correlation mapping from Cockpit views/actions to journal entries, operator actions, execution, provider, reconciliation, claim, campaign, and subject references.

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
- Should production retrieval use cursor pagination before Cockpit/operator screens consume large journals?
- Should retrieval queries support date windows in Phase 8+ or wait for Cockpit/search hardening?
- What idempotency key should be used when host applications record execution outcomes: execution ID, ERN, provider reference, or a composite?
- Which x-change execution call site should be wired first once characterization tests are in place?
- What idempotency key should be used for provider callback retries: provider reference, callback ID, provider timestamp, signature, or a composite?
- Which provider callback source should be wired first once host package characterization tests exist?
- What idempotency key should be used for operator action logs: action request ID, operator ID + target + timestamp, command ID, causation ID, or a composite?
- What idempotency key should be used for campaign records: campaign event ID, batch ID + event type, distribution plan ID, causation ID, or a composite?
