# x-journal

`x-journal` is the evidentiary execution journal package for the 3neti ecosystem.
It stores durable business execution evidence without executing workflows, moving money, or changing lifecycle truth.

The package is internal-first: it is usable independently of `3neti/x-change` at the package level while remaining consumable by host applications as their evidentiary boundary.

For architectural context and Wave 2A planning status, see:

- [X Journal Compass](/docs/architecture/x-journal/X_JOURNAL_COMPASS.md)
- [Production Readiness](/docs/architecture/x-journal/PRODUCTION_READINESS.md)
- [Production Deferrals ADR](/docs/architecture/x-journal/ADR-0001-production-deferrals.md)

For package-specific completion status against functional-spec slices, see the "V1 Completion Compass" section in the package architecture document.

## Package Responsibilities

x-journal owns:

- durable execution evidence
- canonical journal entries
- append-only persistence
- evidence integrity
- visibility-aware retrieval
- artifact rendering

x-journal does **not** own:

- execution
- orchestration
- payouts
- voucher lifecycle
- settlement policy

## Install

```bash
composer require 3neti/x-journal
php artisan vendor:publish --tag=x-journal-config
php artisan migrate
```

The service provider is Laravel auto-discovered (`LBHurtado\XJournal\XJournalServiceProvider`), so no manual registration is required.

## Configuration

Publish and edit `config/x-journal.php`:

- `connection`: database connection used for durable journal storage (`null` uses app default).
- `reference_number.prefix`: ERN prefix, default `ERN`.
- `reference_number.digits`: zero-padding width, default `9`.
- `idempotency.enabled`: toggles entry-level idempotency replay checks, default `true`.

Changing `reference_number.prefix` changes the generated reference format (for example `JRN-2026-000000001`).

## Core Concepts

### Journal entries are canonical and immutable

- Journal entries are represented by `ExecutionJournalEntryData` and stored as durable records in `execution_journal_entries`.
- `ExecutionJournalRecorder` and `DatabaseJournalSink` are used by default for writes.
- Recorded entries get an evidence/reference number (ERN) and integrity fields (`hash`, `previous_hash`, optional `signature`).
- Update and delete operations on persisted entries are blocked by package invariants.

### Entry normalization and event intake

All external inputs are normalized into canonical DTO shapes before persistence:

- `ExecutionActorData`
- `ExecutionSubjectData`
- `ExecutionMoneyData`
- `ExecutionReferenceData`
- `ExecutionJournalEntryData`
- `JournalEventData`

Each DTO normalizes types (for example coercing IDs and metadata to canonical structures).

### V1 completion slices (internal package scope)

The package has been implemented in a scaffolded way for Wave 2A and has full test coverage for the foundational phases.
To align with the functional specification for a fully internal evidentiary package, the following slices are still open:

- **Capture**: base idempotency key scaffolding is implemented (same key + same normalized payload returns existing entry; same key + different payload fails). Remaining policy-level retry-key strategy and tenant scoping are follow-up work.
- **Normalize**: supported event/domain transformations exist, but some taxonomy targets from the full specification (`certificate`, `instrument`, timeline/statement workflows) are not yet added as first-class transformations.
- **Persist**: canonical DB sink remains stable and immutable; database-level immutability, multi-sink lifecycle strategy, and archival sinks are deferred.
- **Govern**: visibility is implemented for read access, but visibility profiles, role matrices, and redaction policies are not yet a package-wide feature set.
- **Render**: receipt rendering plus text + JSON renderers for `statement`, `certificate`, `instrument`, and `timeline` are implemented. HTML/Markdown/PDF formats and public verification rendering remain deferred.
- **Verify**: hash-chain verification is implemented; signatures, token-based verification levels, and public verification contracts remain deferred.
- **Recover**: statement snapshotting and statement anchoring are not yet implemented as first-class package surfaces.

The current implementation should be treated as a tested foundation, not a complete internal evidentiary platform yet.

### Event transformation

`JournalEventRecorder` transforms `JournalEventData` through `JournalEventTransformerRegistry` before persistence.
Built-in transformations currently exist for:

- execution outcomes
- claim lifecycle events
- provider callbacks
- reconciliation events
- operator actions
- campaign events

Unsupported event types fail with `JournalEventTransformerNotFoundException` before writing.

Package consumers can add additional transformations by implementing `JournalEventTransformerContract`.

### Domain integration recorders

Dedicated recorders are available for package-specific payload shapes:

- `XChangeExecutionJournalRecorder`
- `ProviderCallbackJournalRecorder`
- `ReconciliationJournalRecorder`
- `OperatorActionJournalRecorder`
- `CampaignJournalRecorder`

These recorders convert domain DTOs to journal events and pass them to canonical persistence.

### Retrieval

`JournalEntryRetriever` supports bounded query windows and filters on common dimensions:

- actor and subject IDs/types
- correlation, causation, execution, provider-related references
- event type
- reference number
- limit/offset and asc/desc ordering

`findByReferenceNumber` performs direct lookups.
Search operations are read-only and do not mutate persisted entries.

### Visibility

`JournalVisibilityGate` evaluates read access using `JournalAccessActorData`:

- actor-match
- subject-match
- explicit `x-journal.view` permission

Additional visibility logic is supported through `JournalVisibilityPolicyContract`.

### Cockpit read models

`CockpitJournalReader` composes retrieval with visibility evaluation to build operator-facing read models.
Cockpit reads can include visibility reasons; they still preserve canonical facts and do not execute domain behavior.

### Integrity verification

`JournalIntegrityVerifier` validates hash-chain integrity:

- computes and verifies deterministic SHA-256 hashes
- checks previous-hash continuity
- returns `JournalIntegrityVerificationData` with issue codes (`hash_mismatch`, `previous_hash_mismatch`, `missing_hash`)
- never mutates entries during verification

### Artifact generation

`JournalArtifactGenerator` renders canonical entries through registered artifact renderers.
Built-in artifact profiles are available for:

- `receipt` (`text/plain`)
- `statement` (`text/plain`, `application/json`)
- `certificate` (`text/plain`, `application/json`)
- `instrument` (`text/plain`, `application/json`)
- `timeline` (`text/plain`, `application/json`)

Artifacts are renderings, not new canonical evidence. No artifact persistence is implemented in this phase.

## Extension points

The package exposes the following seams:

- `JournalSinkContract` (replace canonical sink)
- `SecondaryJournalSinkContract` (fan out projections/exports)
- `JournalEventTransformerContract` (add domain event support)
- `JournalArtifactRendererContract` (add new output formats)
- `JournalVisibilityPolicyContract` (add visibility rules)

All seams are service-driven and tested through seam-replacement examples.

## Example usage

### Record a canonical execution journal entry

```php
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use Carbon\CarbonImmutable;

$entry = app(ExecutionJournalRecorder::class)->record(new ExecutionJournalEntryData(
    eventType: 'voucher.redeemed',
    occurredAt: CarbonImmutable::parse('2026-06-29T10:15:00Z'),
    actor: new ExecutionActorData(id: '123', type: 'user', name: 'Beneficiary'),
    subject: new ExecutionSubjectData(id: 'voucher-1', type: 'voucher', display: 'Voucher #1'),
    references: new ExecutionReferenceData(executionId: 'exec-1'),
    payload: ['status' => 'succeeded'],
    money: new ExecutionMoneyData(amount: '100.00', currency: 'PHP', minorAmount: 10000),
    metadata: ['source' => 'host-system'],
));
```

### Record an execution outcome from x-change integration shape

```php
use LBHurtado\XJournal\Data\XChangeExecutionOutcomeData;
use LBHurtado\XJournal\Services\XChangeExecutionJournalRecorder;

$payload = XChangeExecutionOutcomeData::fromArray([
    'occurred_at' => '2026-06-29T10:15:00Z',
    'result' => [
        'execution_id' => 'exec-1',
        'successful' => true,
        'status' => 'succeeded',
        'driver' => 'default',
        'events' => ['voucher.redeemed'],
    ],
]);

app(XChangeExecutionJournalRecorder::class)->record($payload);
```

### Search with visibility for cockpit-style reads

```php
use LBHurtado\XJournal\Data\CockpitJournalQueryData;
use LBHurtado\XJournal\Services\CockpitJournalReader;

$view = app(CockpitJournalReader::class)->read(CockpitJournalQueryData::fromArray([
    'actor' => ['id' => 'operator-1', 'type' => 'operator', 'permissions' => ['x-journal.view']],
    'query' => ['execution_id' => 'exec-1', 'limit' => 50],
]));
```

## Validation and testing philosophy

`x-journal` is intended to be a testable infrastructure package with explicit contracts:

- DTO normalization and stable canonical shapes
- deterministic identifier and hashing behavior
- fail-closed unsupported-event handling before persistence
- append-only invariants
- non-mutating read paths
- extension seam validation

Representative verification commands:

```bash
composer validate --strict
composer test
vendor/bin/pest
```

## Production posture

The package is an independent evidentiary foundation layer and should be integrated through host adapters and host-level characterization tests.
Key production deferrals are documented in the architecture docs.
