# x-journal Production Readiness

## Status

Phase 15 status: scaffolded for package-level production readiness.

This document is a release-readiness checklist for `3neti/x-journal` as the evidentiary system log for the x-change Settlement Operating System.

## Release posture

- Package name: `3neti/x-journal`
- Namespace: `LBHurtado\XJournal`
- Laravel auto-discovery provider: `LBHurtado\XJournal\XJournalServiceProvider`
- Supported PHP constraint: `^8.2`
- Supported Laravel support constraint: `illuminate/support ^12.0 || ^13.0`
- DTO dependency: `spatie/laravel-data ^4.0`
- Current package role: durable journal and audit trail
- Current package boundary: records facts; does not decide policy, execute money, mutate workflows, or own lifecycle truth

## Installation expectations

Host applications should install the package through Composer and allow Laravel package auto-discovery to register the service provider.

Required package resources:

- config file: `config/x-journal.php`
- config publish tag: `x-journal-config`
- migrations loaded from: `database/migrations`

Recommended host install flow:

```bash
composer require 3neti/x-journal
php artisan vendor:publish --tag=x-journal-config
php artisan migrate
```

## Verification commands

Current package validation commands:

```bash
composer validate --strict
composer test
vendor/bin/pest
```

`composer test` runs:

```bash
php -d memory_limit=1G vendor/bin/pest
```

Last known Phase 15 baseline:

```text
99 passed, 466 assertions
```

## Operational capabilities

Implemented package capabilities:

- append-only journal entry model guard
- evidence/reference number generation
- counter-backed ERN sequencing
- canonical journal entry DTOs
- event transformer registry
- execution, claim, provider, reconciliation, operator, and campaign transformer baselines
- canonical database sink
- secondary sink fan-out
- visibility policy composition
- text receipt and statement artifact rendering
- integrity hash-chain verification
- search and retrieval
- x-change execution outcome recording seam
- provider callback recording seam
- reconciliation event recording seam
- operator action recording seam
- campaign event recording seam
- Cockpit read-model seam
- architecture hardening tests

## Production blockers and deferrals

The package is ready as a scaffolded foundation, but production adoption must explicitly resolve or accept the following deferrals.

| Topic | Current decision | Production note |
|---|---|---|
| Idempotency | Deferred | Host integrations can currently record duplicates when retried. |
| Database-level immutability | Deferred | Model guards prevent Eloquent update/delete, but direct database writes can bypass them. |
| Signatures and key management | Deferred | Hash-chain verification exists; externally verifiable signatures do not. |
| Artifact persistence | Deferred | Artifacts are in-memory renderings, not persisted files. |
| Redaction / presentation | Deferred | Cockpit read models expose canonical payloads. Host APIs must add redaction before broad exposure. |
| Visibility-aware pagination | Deferred | Cockpit visibility is applied after retrieval windows. |
| Live host wiring | Deferred | x-change/voucher runtime call-site wiring is not part of Wave 2A package readiness. |
| Queue strategy for secondary sinks | Deferred | Secondary sinks currently run synchronously after canonical persistence. |

## Release notes

Phase 15 closes Wave 2A package scaffolding for `x-journal`.

This package now provides a tested, independent journal foundation that can be consumed by later workstreams. It should still be integrated through explicit host-package adapters and characterization tests.

Do not treat Phase 15 as approval to wire money movement, claim submission, provider callbacks, Cockpit APIs, or campaign jobs without host-package tests.

## Next wave recommendation

After Phase 15, the recommended roadmap step is Wave 2B — `x-action`.

`x-action` should consume journaled facts and execution outcomes to describe next workflow actions. It must not become a money execution engine or mutate journal truth.
