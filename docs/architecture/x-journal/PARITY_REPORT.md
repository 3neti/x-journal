# x-journal Parity Report

## Context

Date: 2026-07-01  
Reviewer scope: source code, tests, README, current architecture docs, and execution-plan documents in `/Users/rli/PhpstormProjects/x-change-sandbox/docs/todo/x-journal`.

Reference docs used:
- `01-current-state.md`
- `02-target-state.md`
- `03-evolution-plan.md`
- `04-test-strategy.md`
- `05-architecture-invariants.md`
- `x-journal_functional_specifications.md`
- `x-journal_functional_specifications_addendum.md`
- `docs/architecture/x-journal/X_JOURNAL_COMPASS.md`
- `docs/architecture/x-journal/PRODUCTION_READINESS.md`
- `docs/architecture/x-journal/ADR-0001-production-deferrals.md`

## 1. Executive Summary

**Status: Mostly aligned**

The package has moved well beyond the initially authorized Phase 1 and most core architecture intentions are implemented and covered by tests.  
The implementation is broader than a minimal journal scaffold (event-transformer domain baselines, retrieval, visibility governance, artifact rendering, idempotency, snapshots, and verification), while still keeping the package boundaries to recording evidentiary facts.  

Current drift is mostly scope/completeness drift, not architectural inversion:
- some public/system-level capabilities from functional specifications are still deferred (e.g., HTML/Markdown/PDF formats, object-storage sink, public verification UI/API, advanced pagination/queued sinks),
- some intended defaults differ (for example Monolog sink defaults are disabled in config, and configuration does not yet expose all optional sinks),
- some advanced governance and recovery operations are implemented as scaffolding with limited surface.
- the 2026-07-04 stabilization slice has closed the previously ambiguous secondary sink failure behavior, Cockpit pagination semantics, and snapshot anchoring boundary-test gaps.

Given the above, the package is coherent as a tested evidentiary foundation, but not yet a full "all-functionalities-on" implementation.

## 2. Implemented Scope vs Intended Scope

| Phase | Intended Scope | As-Built Status | Notes |
|---|---|---|---|
| Phase 1 — Core Journal Foundation | Canonical entry, ERN, migration, DTOs, recorder, database sink, append-only | **Implemented** | `ExecutionJournalEntry`, `ExecutionReferenceNumberGenerator`, `ExecutionJournalEntryData`, `ExecutionJournalRecorder`, `DatabaseJournalSink`, tests in `CoreJournalFoundationTest`, `CoreJournalHardeningTest`, migrations. |
| Phase 2 — Event Transformation Layer | Event projector/transformer contract and registry | **Implemented** | `JournalEventTransformerContract`, `JournalEventTransformerRegistry`, `JournalEventRecorder`, tests in `EventTransformationLayerTest`, `DomainEventTransformerBaselinesTest`. |
| Phase 3 — Sink Architecture | Canonical + secondary sinks, config-driven dispatch | **Implemented (partial/config-limited)** | Canonical dispatcher + monolog/null secondaries in config; no object storage, search, or SIEM sink implementations currently. |
| Phase 4 — Integrity and Idempotency | Hash chain + idempotency + verification command | **Implemented** | Hashing/previous hash + idempotent replay with conflict detection, `x-journal:verify-integrity`. |
| Phase 5 — Visibility Governance | Visibility policies and profiles | **Partially implemented** | `JournalVisibilityGate`, `JournalVisibilityProfileResolver`, profile-aware Cockpit projection exists; no full profile matrix/redaction taxonomy yet. |
| Phase 6 — Artifact Rendering | Receipts/certificates/instruments/timelines | **Partially implemented** | Text + JSON profile rendering exists via registry. No markdown/html/PDF first-class renderers. |
| Phase 7 — Artifact Profiles | Artifact profile registry by type/format | **Partially implemented** | Profile struct exists; built-in profile support is practical but not governed by a catalog/registry DSL. |
| Phase 8 — Verification Framework | Verification service, metadata, levels, endpoint | **Partially implemented** | `JournalVerificationServiceContract` + metadata + token/url support, but no web endpoint layer and no production signature profile routing. |
| Phase 9 — Statement Engine | Snapshot model/generation/verification | **Implemented** | `execution_statement_snapshots` + generator/retriever/reconciler/verifier + command coverage. |
| Phase 10 — Timeline Engine | Timeline projection/grouping | **Partially implemented** | Timeline artifact is emitted as grouped artifact content; no dedicated timeline query/model/service boundary yet. |
| Phase 11 — Settlement Envelope Integration | Envelope references journal ERNs | **Documented but not implemented in package** | Compass/Readme mention integrations; package provides adapters for domain event recording only, no envelope-consuming contract. |
| Phase 12 — Recovery Anchors | Anchoring and recovery checks | **Partially implemented** | Statement chaining + reconciliation checks provide recovery anchors in journal context. No settlement-envelope anchoring/ops-level recovery workflow yet. |
| Phase 13 — Digital Signatures | Signature metadata and validation | **Deferred** | Integrity includes signature field but no signing/verifier enforcement strategy in package. |
| Phase 14 — Regulatory Exports | CSV/JSON/PDF exports | **Deferred** | No export serializers or export jobs in current code. |
| Phase 15 — Public Trust Layer | Public verification + trust UX | **Partially implemented** | Token/url metadata and levels exist; no public controller/portal surfaces or signature-aware public trust workflow. |

## 3. Architecture Alignment

| Invariant | Preserved? | Evidence in Code | Notes |
|---|---:|---|---|
| `x-journal` is the evidentiary layer | ✅ | `ExecutionJournalRecorder`, event normalization, immutable record model, and read APIs store canonical facts rather than workflow decisions (`src/Services`, `src/Models`). | No state machine transitions or orchestration are implemented in package services. |
| It is not a workflow engine | ✅ | Domain adapters record facts only (`XChangeExecutionJournalRecorder`, `ProviderCallbackJournalRecorder`, etc.) and never mutate domain state. | Verified by tests asserting no workflow side effects. |
| It is not an accounting ledger | ✅ | No accounting balance logic; only operational metadata and references captured in `execution_journal_entries`. | Finance values are recorded as evidence only. |
| It is not merely a log package | ✅ | Canonical ERN, idempotency, hashing, retrieval, snapshots, visibility, verification service present. | These are structured evidentiary services, not raw message logs. |
| RDBMS remains canonical store | ✅ | `execution_journal_entries` + `execution_journal_reference_counters` + `execution_statement_snapshots` migrations; canonical writes through `DatabaseJournalSink`. | Mongo/object storage remain sinks/projections only (deferred). |
| Monolog is a sink, not the journal | ✅ | Monolog writes projection records only through `SecondaryJournalSinkContract` and `JournalSinkDispatcher`. | Sink is optional and does not affect canonical write success criteria. |
| Artifacts are renderings, not truth | ✅ | `JournalArtifactGenerator` + renderers never persist changes back to journal. | Metadata includes reference numbers for traceability only. |
| Visibility belongs to `x-journal` | ✅ | `JournalVisibilityGate`, resolver, profile handling, actor policies are in package surface and are tested. | Host UI still decides final layout. |
| `x-ray` must not authorize visibility | ✅ | No `x-ray` dependency in package composer/runtime and no policy dispatch to x-ray. | Enforcement is `JournalVisibilityPolicy` and resolver contracts local to package. |
| Settlement envelopes consume journal entries | ⚠️ | No concrete envelope consumer yet; only evidence-ready records are available. | Documented intent exceeds current package API for envelope coupling. |
| Flexible evidence remains JSON-first | ✅ | `actor`, `subject`, `money`, `references`, `payload`, `metadata`, `integrity` are JSON columns with DTO-backed canonical casts. | Scalar projections also added for indexing. |
| Tests enforce architecture | ✅ | `ArchitectureHardeningTest`, `ProductionReadinessTest` check boundaries, dependencies, and critical invariants. | Tests enforce composition and append-only expectations. |

## 4. Risks and Drift

- **Implemented early / scope creep:** Domain event baselines (provider/campaign/operator/reconciliation) and statement recovery are scaffolded and tested, which is good progress but exceeds pure Phase-1 authorization. Requires explicit release notes when consumed by upstream apps.
- **Visibility + Cockpit pagination:** `CockpitJournalReader` now treats `limit` and `offset` as visible-entry pagination controls and exposes pagination semantics in metadata. Large production journals may still need cursor-based tuning later.
- **Idempotency policy drift:** Core replay is generic; idempotency key strategy is host-dependent and not standardized across all domains yet.
- **Sink failure behavior:** Secondary sink failures are now non-blocking projection failures by default. Failures are captured on the dispatcher and logged; no retry or queue orchestration exists yet.
- **Database write-enforcement boundary:** Model-level append-only guards exist; direct SQL writes can still bypass Eloquent protections.
- **Verification surface gap:** Token/url level fields are present; public verification UX/API is not implemented.
- **Artifacts/format gap:** Timeline/certificate/instrument/statement renderers are scaffolded text/json only; expected HTML/markdown/PDF render stack is not implemented.
- **Production hardening still pending:** queueing strategy, signature service, artifact persistence, and tenant-aware redaction matrix remain in deferral ADR.
- **Naming/default mismatch from specs:** Spec references `yearly_sequence` and additional sink classes not yet represented in config or command surface.

## 5. Recommended Corrective Actions

### Must Fix
- Add explicit contract tests for statement anchoring and snapshot cross-check boundaries where journaling feeds external recovery workflows. **Completed in the 2026-07-04 stabilization slice for package-local snapshot anchoring boundaries.**

### Should Fix
- Add a production-safe idempotency guidance contract for host-provided keys (domain-specific resolver examples and defaults).
- Add explicit `event_profiles`/`role_profiles` fixtures in docs and tests for non-trivial redaction matrices.
- Clarify whether default config should enable Monolog and provide default null-safe production profile.
- Consider whether secondary projection failures should be exported to a host-provided observability sink before production use.

### Can Defer
- PDF artifact rendering and object-storage sink.
- Dedicated public verification endpoints/routers.
- Full timeline dedicated service/query model and statement-profile catalog beyond current minimal profile data.
- Advanced recovery orchestration and settlement envelope wiring.

## Functional Specification Coverage Matrix

| Functional Spec Area | Intended Capability | As-Built Classes / Files | Status | Notes |
|---|---|---|---|---|
| Package skeleton | Package service/provider and entry composition | `src/XJournalServiceProvider.php`, `README.md`, `composer.json` | Implemented | Package registered through Laravel auto-discovery and publishes config/migrations. |
| Configuration | Config keys and sink toggles | `config/x-journal.php`, provider boot binding | Implemented | Minimal subset implemented; object-storage/sinks are deferred in config. |
| Migration | Journal + counter + snapshot tables | `database/migrations/*` | Implemented | Table set includes snapshots and idempotency fields. |
| Execution journal entry model | Persisting canonical execution entries | `src/Models/ExecutionJournalEntry.php`, `src/Data/ExecutionJournalEntryData.php` | Implemented | Append-only model behavior is active. |
| ExecutionJournalEntryData | Canonical DTO shape | `src/Data/ExecutionJournalEntryData.php` | Implemented | Includes integrity/idempotency/reference fields and normalization. |
| Actor DTO | Canonical actor representation | `src/Data/ExecutionActorData.php` | Implemented | Normalized ID/type/name metadata. |
| Subject DTO | Canonical subject representation | `src/Data/ExecutionSubjectData.php` | Implemented | Normalization present. |
| Money DTO | Amount, currency, minor amount | `src/Data/ExecutionMoneyData.php` | Implemented | Scalar JSON persisted. |
| Reference DTO | Correlation/causation/execution refs | `src/Data/ExecutionReferenceData.php` | Implemented | Also supports provider/external refs and metadata. |
| Integrity DTO | Hash/previous/signature fields | `src/Data/ExecutionIntegrityData.php`, `src/Services/ExecutionJournalIntegrityHasher.php` | Implemented | Signature remains present but unsigned; chain verification implemented. |
| ERN generation | Prefix+year+digits sequencing | `src/Services/ExecutionReferenceNumberGenerator.php`, `ExecutionJournalReferenceCounter` | Implemented | Prefix and digit length configurable. |
| Database sink | Canonical write sink | `src/Services/DatabaseJournalSink.php` | Implemented | Hash/idempotency values computed on persistence. |
| Journal recorder | Facade for write orchestration | `src/Services/ExecutionJournalRecorder.php` | Implemented | Handles idempotency + ERN assignment. |
| Append-only behavior | Immutable write model | `src/Models/ExecutionJournalEntry.php`, tests | Implemented | Update/delete throw; create only. |
| Idempotency | Replay safety and conflict detection | `src/Services/ExecutionJournalRecorder.php`, `ExecutionJournalIdempotencyHasher.php`, tests | Partially implemented | Generic resolver + key policy; no domain-specific key strategy. |
| Hashing | Entry hash and previous hash | `src/Services/ExecutionJournalIntegrityHasher.php` | Implemented | SHA-256 canonical hashing over normalized payload. |
| Integrity verification | Tamper and chain checks | `src/Services/JournalIntegrityVerifier.php`, `VerifyJournalCommand` | Implemented | Structured issue codes and metadata. |
| Monolog sink | Projection/sink pattern | `src/Contracts/SecondaryJournalSinkContract.php`, `src/Services/MonologJournalSink.php` | Implemented | Configurable channel/message; optional. |
| Null sink | Projection no-op sink | `src/Services/NullJournalSink.php` | Implemented | Useful for test/disable path. |
| Secondary sink dispatch | Fan-out to sinks | `src/Services/JournalSinkDispatcher.php`, provider config | Implemented | Supports sink selection and registration; secondary failures are non-blocking projection failures by default. |
| Event transformer registry | Select transformer by event | `src/Services/JournalEventTransformerRegistry.php` | Implemented | Built-ins include execution/claim/provider/reconciliation/operator/campaign. |
| Event transformers | Domain-specific normalization | `src/Transformers/*` | Implemented | Tests cover each domain baseline. |
| Domain journal recorders | Adapters for integration DTOs | `src/Services/*JournalRecorder.php` | Implemented | x-change/provider/reconciliation/operator/campaign adapters scaffolded. |
| Retrieval/search | Query APIs | `src/Services/JournalEntryRetriever.php`, `src/Data/JournalRetrievalQueryData.php` | Implemented | Bounded limit/offset, filters, ordering. |
| Visibility gate | Read access decisions | `src/Services/JournalVisibilityGate.php` | Implemented | Policy-first allow/deny flow. |
| Visibility policies | Policy seam | `src/Contracts/JournalVisibilityPolicyContract.php`, `src/Policies/ActorOrSubjectJournalVisibilityPolicy.php` | Implemented | Single default policy plus pluggable resolver. |
| Access reason logging | Auditable reason logging | `src/Contracts/JournalVisibilityAccessReasonLoggerContract.php`, `src/Services/NullJournalVisibilityAccessReasonLogger.php` | Partially implemented | Contract available; default logger is no-op. |
| Cockpit read models | Operator-facing read models | `src/Services/CockpitJournalReader.php`, `src/Data/Cockpit*` | Implemented | Supports visibility-aware reads with profile resolution and explicit visible-entry pagination semantics. |
| Artifact generation | Artifact service entry point | `src/Services/JournalArtifactGenerator.php` | Implemented | Registry-based renderer selection. |
| Receipt rendering | Receipt artifact | `src/Renderers/TextReceiptArtifactRenderer.php`, `JournalArtifactProfileData` | Implemented | Text output only. |
| Certificate rendering | Certificate artifact | `src/Renderers/TextSupplementalArtifactRenderer.php`, `MachineSupplementalArtifactRenderer.php` | Implemented | Supported as text/json supplemental profile. |
| Instrument rendering | Instrument artifact | `src/Renderers/TextSupplementalArtifactRenderer.php`, `MachineSupplementalArtifactRenderer.php` | Implemented | Supported as text/json supplemental profile. |
| Timeline rendering | Timeline artifact | `src/Renderers/TextSupplementalArtifactRenderer.php`, `MachineSupplementalArtifactRenderer.php` | Partially implemented | No dedicated timeline entity model; relies on grouped artifact content. |
| Verification service | Entry-level verification data | `src/Services/DefaultJournalVerificationService.php`, `src/Data/ExecutionVerificationData.php` | Implemented | URL, token, hash, and chain status included. |
| Verification metadata | Metadata for verification UX | `src/Contracts/JournalIntegrityVerificationMetadataContract.php`, `DefaultJournalIntegrityVerificationMetadataProvider.php` | Implemented | Includes issue counts and reference windowing. |
| Statement snapshots | Snapshot model + generator | `src/Services/ExecutionStatementSnapshot*.php`, `ExecutionStatementSnapshot` model | Implemented | Statement types and subject-scoped generation supported. |
| Statement reconciliation | Cross-check snapshot against period entries | `src/Services/ExecutionStatementSnapshotReconciler.php` | Implemented | Detects hash/count drift without mutating entries or snapshots. |
| Statement verification | Snapshot verification command | `src/Services/ExecutionStatementSnapshotVerifier.php`, `VerifySnapshotsCommand.php` | Implemented | Supports filtering by type/subject/statement number. |
| Console commands | Integrity + verification CLI | `src/Console/Commands/VerifyJournalCommand.php`, `VerifySnapshotsCommand.php` | Implemented | JSON output and non-zero on failure behavior implemented. |
| Testing coverage | Unit + feature test suite | `tests/Feature/*`, `tests/Unit/ExecutionJournalEntryDataTest.php` | Implemented | Broad slice-level coverage; no route-level integration tests. |
| Compass maintenance | Architecture checkpoint doc | `docs/architecture/x-journal/X_JOURNAL_COMPASS.md` | Implemented | Needs refresh for table visibility (applied here). |
| Production readiness documentation | ADR/readiness posture | `docs/architecture/x-journal/PRODUCTION_READINESS.md`, `ADR-0001-production-deferrals.md` | Implemented | Explicitly documents deferred items. |

## As-Built Features and Benefits

| As-Built Feature | Classes / Files | Benefit | Consumer |
|---|---|---|---|
| Canonical durable journaling | `ExecutionJournalEntry`, recorder, database sink, integrity hasher | Immutable auditable execution ledger | x-change, finance, support, compliance |
| Deterministic ERN sequencing | `ExecutionReferenceNumberGenerator`, counter table | Stable cross-system reference and traceability | all downstream workflows |
| Append-only protection | `ExecutionJournalEntry` model observers | Prevents record tampering via model-level updates/deletes | auditors, compliance |
| Idempotent recording | Recorder + idempotency key/fingerprint + resolver | Suppresses duplicate retries | event ingress, host adapters |
| Sink architecture | `JournalSinkDispatcher`, monolog/null sinks | Separation of canonical record and projections | x-ray/logs/operations |
| Event normalization | Transformer registry + domain transformers | Reduces host coupling and normalizes semantic payload | x-change, settlement integration, operator tooling |
| Visibility governance | Visibility gate, policy, resolver, profiles | Access-limited evidence surfacing | operators, finance, support, compliance |
| Cockpit read models | `CockpitJournalReader`, presenter | Operator-safe query and visibility-aware visible-entry pagination | x-change cockpit/UIs |
| Artifact rendering | Artifact generator/renderers | Human-readable artifacts from journal facts | beneficiaries, admins, regulators |
| Verification metadata | verification service/token helper | External verifiability metadata for artifact workflows | public/verifier tools, compliance |
| Hash-chain integrity | Hasher + verifier + issue reporting | Tamper detection over immutable stream | audit/compliance |
| Statement snapshots | `ExecutionStatementSnapshot*` services | Recovery checkpoints and recovery-anchoring evidence | recovery, finance, regulators |
| Provider/operator/campaign integration recorders | domain recorders + transformers | Uniform evidentiary capture without orchestration coupling | x-change, providers, campaign tooling |
| Test-driven architecture boundaries | `ArchitectureHardeningTest`, `ProductionReadinessTest` | Prevents boundary drift in refactors | maintainers, release governance |

## Test Coverage Summary

| Test File | Feature Covered | Confidence Level | Notes |
|---|---|---|---|
| `tests/Feature/ArchitectureHardeningTest.php` | Boundary/invariant enforcement | High | Runtime dependency and policy hardening coverage. |
| `tests/Feature/ArtifactGenerationTest.php` | Renderers, profiles, renderer registration | High | Exercises multiple profiles and formats supported now. |
| `tests/Feature/CampaignIntegrationTest.php` | Campaign integration adapter | High | Verifies normalize/record/retrieve semantics. |
| `tests/Feature/CockpitIntegrationTest.php` | Cockpit read path + visibility-aware pagination | High | Covers gating, profile resolution, visible-entry limit/offset semantics, `has_more`, raw totals, page-visible counts, and pagination metadata. |
| `tests/Feature/CoreJournalFoundationTest.php` | Recorder, ERN, persistence guardrails | High | Core foundation behavior. |
| `tests/Feature/CoreJournalHardeningTest.php` | Counters, hashing, contract binding | High | Core hardening. |
| `tests/Feature/DomainEventTransformerBaselinesTest.php` | Baseline domain transformer behavior | High | Claim/provider/reconciliation adapters. |
| `tests/Feature/EventTransformationLayerTest.php` | Event->canonical transformation path | High | Unsupported-event fail-closed. |
| `tests/Feature/ExecutionJournalIdempotencyTest.php` | Replay + conflict rules | High | Covers expected duplicate/conflict behavior. |
| `tests/Feature/ExecutionStatementSnapshotTest.php` | Statement generation, chain, reconciliation | High | Covers snapshot counts/hashes, snapshot chain links, tampered entries, broken chain links, mismatch facts, and non-mutating boundary behavior. |
| `tests/Feature/OperatorActionIntegrationTest.php` | Operator action recorder and retrieval | High | No workflow side effects. |
| `tests/Feature/PackageBootstrapTest.php` | Service provider/bootstrap and publish paths | High | Installation contract. |
| `tests/Feature/ProductionReadinessTest.php` | Production docs and testbench metadata | Medium | Behavioral posture only. |
| `tests/Feature/ProviderCallbackIntegrationTest.php` | Provider callback adapter and retrieval | High | Domain adapter fidelity. |
| `tests/Feature/ReconciliationIntegrationTest.php` | Reconciliation adapter semantics | High | Capture-only behavior validated. |
| `tests/Feature/SearchRetrievalTest.php` | Retrieval/filter/pagination window behavior | High | Important read path regression guard. |
| `tests/Feature/SinkArchitectureTest.php` | Canonical sink + secondary projection semantics | High | Covers sink selection, non-blocking secondary failures, continued fan-out, and canonical entry immutability under projection failure. |
| `tests/Feature/VerificationCommandTest.php` | CLI command results and JSON output | Medium | Output and failure conditions covered. |
| `tests/Feature/VerificationIntegrityTest.php` | Hash tampering and continuity failures | High | Tamper and missing hash branches covered. |
| `tests/Feature/VerificationMetadataTest.php` | Verification metadata/token behavior | High | URL/token level behavior validated. |
| `tests/Feature/VerificationSnapshotsCommandTest.php` | Snapshot command and JSON/reporting | High | Filtered/JSON paths covered. |
| `tests/Feature/VisibilityAuthorizationTest.php` | Actor-based visibility decisions | Medium-High | Visibility baseline and logger hook covered. |
| `tests/Feature/VisibilityGovernanceTest.php` | Role/event profile resolver behavior | Medium | Config-driven visibility shaping covered. |
| `tests/Feature/XChangeExecutionIntegrationTest.php` | x-change integration adapter | Medium-High | Records execution outcomes as facts only. |
| `tests/Unit/ExecutionJournalEntryDataTest.php` | DTO normalization/shape contracts | High | Canonicalization behavior well covered. |

### Notable missing/weak coverage

- Public verification API surfaces (routes/controllers) are not represented in tests.
- Artifact redaction policy enforcement is tested mainly through Cockpit projection; no standalone redaction policy contract test matrix.
- Queue/retry behavior under secondary sink failures is not implemented or tested; secondary failures are captured synchronously as projection failures.
- Identity-leak/visibility leak property testing at high-scale query boundaries is limited.
- Outbound UI/API formats beyond `text/plain` and `application/json` artifact formats are not exercised.

## Required Next Action

Treat this as an auditable evidentiary foundation release. After the 2026-07-04 stabilization slice, read-only Cockpit integration can consume `CockpitJournalReader`, verification services, and statement snapshot read models as evidence sources.

Remaining production-hardening work should focus on host idempotency strategy, observability for projection failures, public verification surfaces, signatures, redaction matrices, and any future cursor pagination required by high-volume Cockpit screens.

## 2026-07-04 Stabilization Slice

Scope:
- Stabilized secondary sink failure semantics.
- Stabilized Cockpit visibility-aware pagination semantics.
- Strengthened statement/snapshot anchoring boundary tests.
- Updated parity and compass documentation.

Behavior decisions:
- Canonical database persistence remains authoritative.
- Secondary sink failures do not roll back, invalidate, or prevent return of the canonical journal entry by default.
- Secondary sink failures are captured by `JournalSinkDispatcher::projectionFailures()` and logged as projection failures.
- Failure of one secondary sink does not prevent remaining secondary sinks from being attempted.
- Cockpit `limit` and `offset` are visible-entry pagination controls.
- Cockpit `retrieved_total` is the raw matching entry count before visibility filtering.
- Cockpit `visible_total` is the visible count returned in the current page.
- Cockpit `has_more` means at least one more visible entry exists after the current visible page.
- Cockpit metadata includes explicit pagination semantics labels.
- Statement snapshots remain evidentiary anchors; reconciliation and verification report mismatch facts and do not execute recovery, settlement, money movement, or workflow actions.

Tests added/updated:
- `tests/Feature/SinkArchitectureTest.php`
- `tests/Feature/CockpitIntegrationTest.php`
- `tests/Feature/ExecutionStatementSnapshotTest.php`

Commands run:
- `vendor/bin/pest tests/Feature/SinkArchitectureTest.php tests/Feature/CockpitIntegrationTest.php tests/Feature/ExecutionStatementSnapshotTest.php` -> `41 passed, 187 assertions`
- `vendor/bin/pest tests/Feature/ArchitectureHardeningTest.php` -> `6 passed, 26 assertions`
- `php -d memory_limit=1G vendor/bin/pest` -> `160 passed, 758 assertions`
- `vendor/bin/pint --dirty --format agent` -> not run because `vendor/bin/pint` is not installed or executable in this package
