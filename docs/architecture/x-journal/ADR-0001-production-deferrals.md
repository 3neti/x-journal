# ADR-0001 — Production Deferrals

## Status

Accepted as the Phase 15 production-readiness position.

## Context

`x-journal` is an evidentiary package. It records system facts for the x-change Settlement Operating System and must remain independent of domain-package execution behavior.

The package now has core persistence, transformation, sinks, visibility, artifacts, verification, retrieval, integration seams, Cockpit read models, and hardening tests.

Several production concerns are intentionally unresolved because resolving them requires host-package policy, infrastructure decisions, or cross-workstream coordination.

## Decision

The following concerns are explicitly deferred from Wave 2A package scaffolding:

1. idempotency keys and duplicate suppression;
2. database-level immutability enforcement;
3. cryptographic signatures and key management;
4. artifact persistence and public/private storage policy;
5. redaction and presentation profiles for Cockpit/API exposure;
6. visibility-aware cursor pagination;
7. queue/asynchronous strategy for secondary sinks;
8. live x-change/voucher/provider/campaign runtime call-site wiring.

The package remains production-ready as a tested foundation only when these deferrals are visible to host adopters.

## Rationale

- Idempotency requires domain-level identifiers that differ by event source.
- Database immutability strategy depends on the host database engine and deployment policy.
- Signatures require key storage, rotation, and verification policy.
- Redaction requires product, privacy, and role-based access rules.
- Visibility-aware pagination changes retrieval semantics and should be designed with Cockpit/API needs.
- Host call-site wiring must be characterized in each consuming package before integration.

## Consequences

- `x-journal` can be released as an independent package scaffold.
- Host packages must not silently assume duplicate suppression, database-level write protection, signed artifacts, redacted Cockpit payloads, or live event wiring.
- Future workstreams should reference this ADR before consuming `x-journal` in production-facing paths.

## Next decision points

Before broad production use, create follow-up ADRs for:

- idempotency strategy;
- database-level immutability;
- signature and key management;
- artifact persistence;
- redaction and presentation;
- visibility-aware pagination.
