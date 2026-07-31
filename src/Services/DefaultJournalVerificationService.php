<?php

namespace LBHurtado\XJournal\Services;

use Illuminate\Support\Arr;
use LBHurtado\XJournal\Contracts\JournalVerificationServiceContract;
use LBHurtado\XJournal\Data\ExecutionVerificationData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class DefaultJournalVerificationService implements JournalVerificationServiceContract
{
    public function __construct(
        protected JournalIntegrityVerifier $integrityVerifier,
    ) {}

    public function verify(ExecutionJournalEntry $entry): ExecutionVerificationData
    {
        $token = $this->tokenFor($entry);
        $chainVerified = $this->chainIsVerifiedForEntry($entry);
        $receiptHash = is_array($entry->integrity) ? ($entry->integrity['hash'] ?? null) : null;

        $levels = [];
        $levels[] = 'verification_url';
        if ($token !== null) {
            $levels[] = 'verification_token';
        }
        if ($receiptHash !== null) {
            $levels[] = 'receipt_hash';
        }
        if ($chainVerified) {
            $levels[] = 'journal_chain';
        }

        return new ExecutionVerificationData(
            referenceNumber: (string) $entry->reference_number,
            status: $chainVerified ? 'verified' : 'unverified',
            verificationUrl: $this->verificationUrlFor($entry),
            verificationToken: $token,
            chainVerified: $chainVerified,
            receiptHash: $receiptHash,
            levels: $levels,
            metadata: [
                'verified_at' => now()->toJSON(),
                'issuer' => 'x-journal',
                'reference_entry_id' => $entry->id,
                'supported_levels' => $levels,
            ],
        );
    }

    public function tokenFor(ExecutionJournalEntry $entry): ?string
    {
        $secret = (string) config('x-journal.verification.token_secret', '');
        if ($secret === '') {
            return null;
        }

        $timestamp = $entry->created_at?->getTimestamp() ?? time();
        $payload = sprintf('%s|%s', (string) $entry->reference_number, $timestamp);
        $signature = hash_hmac('sha256', $payload, $secret);

        return sprintf('%s|%s', $payload, $signature);
    }

    public function validateToken(string $referenceNumber, ?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $secret = (string) config('x-journal.verification.token_secret', '');
        if ($secret === '') {
            return false;
        }

        [$tokenReference, $timestamp, $signature] = $this->parseToken($token);
        if ($tokenReference === null || $timestamp === null || $signature === null) {
            return false;
        }

        if ($tokenReference !== $referenceNumber) {
            return false;
        }

        $entry = $this->findByReference($referenceNumber);
        if (! $entry instanceof ExecutionJournalEntry) {
            return false;
        }

        $issuedAt = (int) $timestamp;
        if ($issuedAt <= 0) {
            return false;
        }

        if (! $this->tokenNotExpired((string) $issuedAt)) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', sprintf('%s|%s', $tokenReference, $issuedAt), $secret);
        if (! hash_equals($expectedSignature, $signature)) {
            return false;
        }

        $tokenCreatedAt = $entry->created_at?->getTimestamp();
        if ($tokenCreatedAt !== null && $tokenCreatedAt !== $issuedAt) {
            return false;
        }

        return true;
    }

    protected function verificationUrlFor(ExecutionJournalEntry $entry): string
    {
        $path = trim((string) config('x-journal.verification.path', '/verify'), '/');
        $base = (string) config('x-journal.verification.base_url', '');

        $pathname = sprintf('/%s/%s', $path, $entry->reference_number);

        if ($base === '') {
            return $pathname;
        }

        return sprintf('%s%s', rtrim($base, '/'), $pathname);
    }

    protected function chainIsVerifiedForEntry(ExecutionJournalEntry $entry): bool
    {
        if (! is_numeric($entry->id)) {
            return false;
        }

        $history = ExecutionJournalEntry::query()
            ->where('id', '<=', (int) $entry->id)
            ->orderBy('id')
            ->get();

        if ($history->isEmpty()) {
            return false;
        }

        $result = $this->integrityVerifier->verify($history);

        return $result->verified;
    }

    protected function findByReference(string $referenceNumber): ?ExecutionJournalEntry
    {
        return ExecutionJournalEntry::query()
            ->where('reference_number', $referenceNumber)
            ->first();
    }

    protected function parseToken(string $token): array
    {
        $parts = explode('|', $token, 3);
        if (count($parts) !== 3) {
            return [null, null, null];
        }

        return [
            $parts[0] !== '' ? $parts[0] : null,
            $parts[1] !== '' ? $parts[1] : null,
            $parts[2] !== '' ? $parts[2] : null,
        ];
    }

    protected function tokenNotExpired(string $issuedAt): bool
    {
        $ttl = Arr::get(config('x-journal.verification'), 'token_ttl_minutes', 0);
        if (! is_numeric($ttl) || (int) $ttl <= 0) {
            return true;
        }

        $issued = (int) $issuedAt;
        $maxAge = ((int) $ttl) * 60;
        $now = time();

        return $issued <= $now && ($now - $issued) <= $maxAge;
    }
}
