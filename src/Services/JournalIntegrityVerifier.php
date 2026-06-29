<?php

namespace LBHurtado\XJournal\Services;

use Illuminate\Support\Collection;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\JournalIntegrityIssueData;
use LBHurtado\XJournal\Data\JournalIntegrityVerificationData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class JournalIntegrityVerifier
{
    public function __construct(
        protected ExecutionJournalIntegrityHasher $integrityHasher,
    ) {}

    /**
     * @param  iterable<int, ExecutionJournalEntry>|null  $entries
     */
    public function verify(?iterable $entries = null): JournalIntegrityVerificationData
    {
        $collection = $entries === null
            ? ExecutionJournalEntry::query()->orderBy('id')->get()
            : ($entries instanceof Collection ? $entries->values() : collect($entries)->values());

        $issues = [];
        $expectedPreviousHash = null;

        foreach ($collection as $entry) {
            if (! $entry instanceof ExecutionJournalEntry) {
                continue;
            }

            $integrity = is_array($entry->integrity) ? $entry->integrity : [];
            $actualHash = $this->nullableString($integrity['hash'] ?? null);
            $actualPreviousHash = $this->nullableString($integrity['previous_hash'] ?? null);

            if ($actualPreviousHash !== $expectedPreviousHash) {
                $issues[] = new JournalIntegrityIssueData(
                    code: 'previous_hash_mismatch',
                    referenceNumber: $entry->reference_number,
                    message: 'Journal entry previous hash does not match the prior entry hash.',
                    expected: $expectedPreviousHash,
                    actual: $actualPreviousHash,
                    metadata: ['entry_id' => $entry->getKey()],
                );
            }

            if ($actualHash === null) {
                $issues[] = new JournalIntegrityIssueData(
                    code: 'missing_hash',
                    referenceNumber: $entry->reference_number,
                    message: 'Journal entry is missing its integrity hash.',
                    expected: 'sha256',
                    actual: null,
                    metadata: ['entry_id' => $entry->getKey()],
                );

                $expectedPreviousHash = null;

                continue;
            }

            $expectedHash = $this->integrityHasher->hash(
                $this->entryData($entry),
                ['previous_hash' => $actualPreviousHash],
            );

            if ($actualHash !== $expectedHash) {
                $issues[] = new JournalIntegrityIssueData(
                    code: 'hash_mismatch',
                    referenceNumber: $entry->reference_number,
                    message: 'Journal entry integrity hash does not match its canonical payload.',
                    expected: $expectedHash,
                    actual: $actualHash,
                    metadata: ['entry_id' => $entry->getKey()],
                );
            }

            $expectedPreviousHash = $actualHash;
        }

        return new JournalIntegrityVerificationData(
            verified: $issues === [],
            checkedEntryCount: $collection->count(),
            issues: $issues,
        );
    }

    protected function entryData(ExecutionJournalEntry $entry): ExecutionJournalEntryData
    {
        return ExecutionJournalEntryData::fromArray([
            'reference_number' => $entry->reference_number,
            'event_type' => $entry->event_type,
            'occurred_at' => $entry->occurred_at,
            'actor' => $entry->actor,
            'subject' => $entry->subject,
            'money' => $entry->money,
            'references' => $entry->references,
            'payload' => $entry->payload,
            'integrity' => $entry->integrity,
            'metadata' => $entry->metadata,
        ]);
    }

    protected function nullableString(mixed $value): ?string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return null;
    }
}
