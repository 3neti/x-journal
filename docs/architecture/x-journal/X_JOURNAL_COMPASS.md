# x-journal Compass

## Mission

Make `x-journal` the evidentiary system log for the x-change Settlement Operating System.

The package records what happened across execution, claims, authorization, settlement, provider callbacks, reconciliation, operator actions, campaigns, and exceptions. It must not decide business policy, mutate money, or become lifecycle truth.

## Current Position

Current wave: Wave 2A — x-journal  
Current slice: Package bootstrap / Phase 0 foundation  
Status: In progress  
Last updated: 2026-06-29

## Phase Progress

| Phase | Focus | Status |
|---|---|---|
| 0 | Architectural foundation and package bootstrap | In progress |
| 1 | Core journal foundation | Not started |
| 2 | Event transformation layer | Not started |
| 3 | Sink architecture | Not started |
| 4 | Visibility and authorization | Not started |
| 5 | Artifact generation | Not started |
| 6 | Verification and integrity | Not started |
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

## Discoveries

- `docs/todo/x-journal/x-journal_codex_instructions.md` is empty.
- `docs/todo/x-journal/x-journal_codex_instructions_addendum.md` contains the operative Compass rules.
- The functional specifications and 01–05 planning files are coherent enough to guide Phase 0 and Phase 1.

## Risks

- The package has no runtime journal implementation yet.
- The empty primary instruction file can confuse future agents unless the addendum remains treated as authoritative.
- Core journal schema, ERN format, append-only enforcement, and correlation semantics must be introduced through tests before production code.
- x-journal must remain independent of voucher execution internals and x-change product workflow decisions.

## Architectural Decisions

- x-journal is the evidentiary layer, not the operational database.
- x-journal records what happened; it does not decide what should happen.
- Journal entries must be append-only once core persistence exists.
- Every future journal entry must have an evidence/reference number.
- Execution Engine remains journal-ready but not journal-dependent.
- Monolog/log files may be sinks or technical diagnostics, but they are not canonical journal truth.

## Test Coverage Status

- Package bootstrap tests exist for configuration and service-provider registration.
- No core journal behavior tests exist yet.
- Phase 1 must begin with failing tests for journal entry persistence, ERN generation, append-only behavior, DTO normalization, and sink behavior.

## Next Recommended Slice

Phase 1A — Core Journal Foundation tests.

Start with failing tests for:

- `ExecutionJournalEntry` persistence shape
- ERN/reference generation
- canonical payload, actor, subject, money, reference, and integrity DTOs
- `ExecutionJournalRecorder`
- default `DatabaseJournalSink`
- append-only protection

## Open Questions

- What exact ERN format should be used in production: `ERN-YYYY-#########` or a package-configurable format?
- Should Phase 1 use `spatie/laravel-data` DTOs immediately or plain immutable PHP data objects first?
- Should `execution_journal_entries` use JSON columns only, or include indexed scalar projection columns for common queries from day one?
- Which host package should first consume x-journal: x-change adapters, voucher events, or a dedicated integration layer?

