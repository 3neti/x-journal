<?php

namespace LBHurtado\XJournal\Services;

use Carbon\CarbonImmutable;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\JournalTimestampPrecisionProofData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

final readonly class JournalTimestampPrecisionLossDetector
{
    public function __construct(
        private ExecutionJournalIntegrityHasher $integrityHasher,
        private ExecutionJournalIdempotencyHasher $idempotencyHasher,
    ) {}

    public function prove(
        ExecutionJournalEntry $entry,
    ): JournalTimestampPrecisionProofData {
        $integrity = is_array($entry->integrity)
            ? $entry->integrity
            : [];
        $actualHash = $this->nullableString($integrity['hash'] ?? null);
        $previousHash = $this->nullableString(
            $integrity['previous_hash'] ?? null,
        );
        $storedFingerprint = $this->nullableString(
            $entry->idempotency_fingerprint,
        );
        $persistedData = $this->entryData($entry);
        $persistedExpectedHash = $this->integrityHasher->hash(
            $persistedData,
            ['previous_hash' => $previousHash],
        );

        if (
            $actualHash === null
            || $storedFingerprint === null
            || hash_equals($actualHash, $persistedExpectedHash)
        ) {
            return $this->failed(
                $entry,
                $actualHash,
                $persistedExpectedHash,
            );
        }

        $templateTimestamp = CarbonImmutable::instance(
            $entry->occurred_at,
        )->setMicrosecond(123456);
        $templateData = $persistedData->withOccurredAt(
            $templateTimestamp,
        );
        $templateJson = $this->integrityHasher->canonicalJson(
            $templateData,
            ['previous_hash' => $previousHash],
        );
        $timestampJson = $templateTimestamp->toJSON();
        $microsecondsOffset = strpos($timestampJson, '123456');
        $timestampOffset = strpos($templateJson, $timestampJson);

        if (
            $microsecondsOffset === false
            || $timestampOffset === false
        ) {
            return $this->failed(
                $entry,
                $actualHash,
                $persistedExpectedHash,
            );
        }

        $hashOffset = $timestampOffset + $microsecondsOffset;
        $prefix = substr($templateJson, 0, $hashOffset);
        $suffix = substr($templateJson, $hashOffset + 6);
        $prefixContext = hash_init('sha256');
        hash_update($prefixContext, $prefix);
        $matchedMicroseconds = [];

        for ($microseconds = 1; $microseconds <= 999999; $microseconds++) {
            $candidateContext = hash_copy($prefixContext);
            hash_update(
                $candidateContext,
                str_pad((string) $microseconds, 6, '0', STR_PAD_LEFT),
            );
            hash_update($candidateContext, $suffix);

            if (hash_equals($actualHash, hash_final($candidateContext))) {
                $matchedMicroseconds[] = $microseconds;
            }
        }

        if (count($matchedMicroseconds) !== 1) {
            return new JournalTimestampPrecisionProofData(
                proved: false,
                referenceNumber: (string) $entry->reference_number,
                actualHash: $actualHash,
                persistedExpectedHash: $persistedExpectedHash,
                candidateCount: count($matchedMicroseconds),
                recoveredMicroseconds: null,
                recoveredOccurredAt: null,
                idempotencyFingerprintMatched: false,
            );
        }

        $recoveredMicroseconds = $matchedMicroseconds[0];
        $recoveredOccurredAt = CarbonImmutable::instance(
            $entry->occurred_at,
        )->setMicrosecond($recoveredMicroseconds);
        $recoveredData = $persistedData->withOccurredAt(
            $recoveredOccurredAt,
        );
        $fingerprintMatched = hash_equals(
            $storedFingerprint,
            $this->idempotencyHasher->fingerprint($recoveredData),
        );

        return new JournalTimestampPrecisionProofData(
            proved: $fingerprintMatched,
            referenceNumber: (string) $entry->reference_number,
            actualHash: $actualHash,
            persistedExpectedHash: $persistedExpectedHash,
            candidateCount: 1,
            recoveredMicroseconds: $recoveredMicroseconds,
            recoveredOccurredAt: $recoveredOccurredAt->toJSON(),
            idempotencyFingerprintMatched: $fingerprintMatched,
        );
    }

    private function entryData(
        ExecutionJournalEntry $entry,
    ): ExecutionJournalEntryData {
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
            'idempotency_key' => $entry->idempotency_key,
        ]);
    }

    private function failed(
        ExecutionJournalEntry $entry,
        ?string $actualHash,
        ?string $persistedExpectedHash,
    ): JournalTimestampPrecisionProofData {
        return new JournalTimestampPrecisionProofData(
            proved: false,
            referenceNumber: (string) $entry->reference_number,
            actualHash: $actualHash,
            persistedExpectedHash: $persistedExpectedHash,
            candidateCount: 0,
            recoveredMicroseconds: null,
            recoveredOccurredAt: null,
            idempotencyFingerprintMatched: false,
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return null;
    }
}
