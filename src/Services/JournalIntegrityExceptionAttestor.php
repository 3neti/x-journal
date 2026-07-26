<?php

namespace LBHurtado\XJournal\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use RuntimeException;

final readonly class JournalIntegrityExceptionAttestor
{
    private const CLASSIFICATIONS = [
        'legacy_canonicalization',
        'noncanonical_test_fixture',
    ];

    public function __construct(
        private ExecutionJournalRecorder $recorder,
        private JournalIntegrityVerifier $verifier,
    ) {}

    /**
     * @param  list<string>  $referenceNumbers
     * @return array<string, mixed>
     */
    public function inspect(
        array $referenceNumbers,
        string $classification,
    ): array {
        $contexts = $this->contexts(
            $referenceNumbers,
            $classification,
        );

        return $this->result($contexts, committed: false);
    }

    /**
     * @param  list<string>  $referenceNumbers
     * @return array<string, mixed>
     */
    public function attest(
        array $referenceNumbers,
        string $classification,
        string $authorizationReference,
    ): array {
        $authorizationReference = trim($authorizationReference);

        if (
            $authorizationReference === ''
            || mb_strlen($authorizationReference) > 191
        ) {
            throw new RuntimeException(
                'A stable authorization reference of at most 191 characters is required.',
            );
        }

        $contexts = $this->contexts(
            $referenceNumbers,
            $classification,
        );

        DB::transaction(function () use (
            &$contexts,
            $authorizationReference,
        ): void {
            foreach ($contexts as &$context) {
                if ($context['already_attested']) {
                    continue;
                }

                $locked = ExecutionJournalEntry::query()
                    ->whereKey($context['entry']->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $this->assertEntryUnchanged(
                    $context['entry'],
                    $locked,
                );
                $context['attestation'] = $this->record(
                    $locked,
                    $context['issue_codes'],
                    $context['classification'],
                    $authorizationReference,
                );
            }
        }, attempts: 5);

        return $this->result($contexts, committed: true);
    }

    /**
     * @param  list<string>  $referenceNumbers
     * @return list<array{
     *     entry: ExecutionJournalEntry,
     *     issue_codes: list<string>,
     *     classification: string,
     *     attestation: ?ExecutionJournalEntry,
     *     already_attested: bool
     * }>
     */
    private function contexts(
        array $referenceNumbers,
        string $classification,
    ): array {
        $classification = trim($classification);

        if (! in_array($classification, self::CLASSIFICATIONS, true)) {
            throw new RuntimeException(
                'Classification must be legacy_canonicalization or noncanonical_test_fixture.',
            );
        }

        $referenceNumbers = array_values(array_unique(array_map(
            static fn (string $reference): string => trim($reference),
            $referenceNumbers,
        )));

        if (
            $referenceNumbers === []
            || in_array('', $referenceNumbers, true)
        ) {
            throw new RuntimeException(
                'At least one non-empty journal reference number is required.',
            );
        }

        return array_map(function (
            string $referenceNumber,
        ) use ($classification): array {
            $entry = ExecutionJournalEntry::query()
                ->where('reference_number', $referenceNumber)
                ->firstOrFail();
            $prefix = ExecutionJournalEntry::query()
                ->whereKey($entry->getKey())
                ->orWhere(
                    $entry->getQualifiedKeyName(),
                    '<',
                    $entry->getKey(),
                )
                ->orderBy('id')
                ->get();
            $issueCodes = collect(
                $this->verifier->verify($prefix)->issues,
            )
                ->filter(
                    fn (object $issue): bool => $issue->referenceNumber
                        === $referenceNumber,
                )
                ->map(fn (object $issue): string => $issue->code)
                ->unique()
                ->values()
                ->all();

            if ($issueCodes === []) {
                throw new RuntimeException(
                    "Journal entry {$referenceNumber} has no integrity issue to attest.",
                );
            }

            $attestation = ExecutionJournalEntry::query()
                ->where(
                    'idempotency_key',
                    $this->idempotencyKey(
                        $entry,
                        $classification,
                    ),
                )
                ->first();

            return [
                'entry' => $entry,
                'issue_codes' => $issueCodes,
                'classification' => $classification,
                'attestation' => $attestation,
                'already_attested' => $attestation
                    instanceof ExecutionJournalEntry,
            ];
        }, $referenceNumbers);
    }

    /**
     * @param  list<string>  $issueCodes
     */
    private function record(
        ExecutionJournalEntry $entry,
        array $issueCodes,
        string $classification,
        string $authorizationReference,
    ): ExecutionJournalEntry {
        $actualHash = (string) data_get(
            $entry->integrity,
            'hash',
        );

        return $this->recorder->record(
            new ExecutionJournalEntryData(
                eventType: 'journal.integrity_exception.attested',
                occurredAt: CarbonImmutable::now(),
                actor: new ExecutionActorData(
                    id: null,
                    type: 'system_control',
                ),
                subject: new ExecutionSubjectData(
                    id: (string) $entry->reference_number,
                    type: 'journal_entry',
                    display: 'Execution Journal Entry',
                ),
                references: new ExecutionReferenceData(
                    correlationId: 'journal-integrity-exception:'
                        .$entry->reference_number,
                    causationId: (string) $entry->reference_number,
                    executionId: (string) $entry->reference_number,
                    externalReference: $actualHash,
                    metadata: [
                        'original_entry_id' => (string) $entry->getKey(),
                        'authorization_reference' => $authorizationReference,
                    ],
                ),
                idempotencyKey: $this->idempotencyKey(
                    $entry,
                    $classification,
                ),
                payload: [
                    'status' => 'attested_legacy_exception',
                    'classification' => $classification,
                    'original_reference_number' => (string) $entry->reference_number,
                    'issue_codes' => $issueCodes,
                    'original_entry_unchanged' => true,
                    'base_verifier_status' => 'unverified',
                ],
                metadata: [
                    'schema' => 'x-journal.integrity-exception-attestation.v1',
                    'source' => 'guarded_operator_attestation',
                ],
            ),
        );
    }

    private function assertEntryUnchanged(
        ExecutionJournalEntry $before,
        ExecutionJournalEntry $locked,
    ): void {
        if (
            $before->getRawOriginal('occurred_at')
                !== $locked->getRawOriginal('occurred_at')
            || $before->getRawOriginal('integrity')
                !== $locked->getRawOriginal('integrity')
            || $before->idempotency_fingerprint
                !== $locked->idempotency_fingerprint
        ) {
            throw new RuntimeException(
                'The journal entry changed after inspection.',
            );
        }
    }

    private function idempotencyKey(
        ExecutionJournalEntry $entry,
        string $classification,
    ): string {
        return 'x-journal:integrity-exception:'.hash(
            'sha256',
            implode(':', [
                (string) $entry->reference_number,
                (string) data_get($entry->integrity, 'hash'),
                $classification,
            ]),
        );
    }

    /**
     * @param  list<array{
     *     entry: ExecutionJournalEntry,
     *     issue_codes: list<string>,
     *     classification: string,
     *     attestation: ?ExecutionJournalEntry,
     *     already_attested: bool
     * }>  $contexts
     * @return array<string, mixed>
     */
    private function result(
        array $contexts,
        bool $committed,
    ): array {
        $alreadyAttested = collect($contexts)->every(
            fn (array $context): bool => $context['already_attested'],
        );

        return [
            'schema' => 'x-journal.integrity-exception-attestation.v1',
            'success' => true,
            'status' => match (true) {
                $alreadyAttested => 'already_attested',
                $committed => 'attested',
                default => 'ready',
            },
            'entries' => array_map(
                static fn (array $context): array => [
                    'original_reference_number' => (string) $context['entry']->reference_number,
                    'issue_codes' => $context['issue_codes'],
                    'classification' => $context['classification'],
                    'attestation_reference_number' => $context['attestation']?->reference_number,
                ],
                $contexts,
            ),
            'committed' => $committed,
            'original_entries_unchanged' => true,
            'base_verifier_remains_unverified' => true,
        ];
    }
}
