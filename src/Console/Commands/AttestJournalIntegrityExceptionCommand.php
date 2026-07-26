<?php

namespace LBHurtado\XJournal\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XJournal\Services\JournalIntegrityExceptionAttestor;
use RuntimeException;
use Throwable;

final class AttestJournalIntegrityExceptionCommand extends Command
{
    protected $signature = 'x-journal:attest-integrity-exception
        {--reference=* : Journal reference numbers with legacy integrity issues}
        {--classification= : legacy_canonicalization or noncanonical_test_fixture}
        {--authorization-reference= : Stable control authorization reference}
        {--commit : Append integrity-exception attestations}
        {--confirm-append-only-exception : Confirm original entries remain unchanged}
        {--json : Emit a machine-readable result}';

    protected $description = 'Guardedly attest immutable legacy journal integrity exceptions';

    public function handle(
        JournalIntegrityExceptionAttestor $attestor,
    ): int {
        try {
            $references = $this->option('reference');
            $commit = (bool) $this->option('commit');

            if (! is_array($references)) {
                $references = [];
            }

            if (
                $commit
                && ! (bool) $this->option(
                    'confirm-append-only-exception',
                )
            ) {
                throw new RuntimeException(
                    'Commit requires --confirm-append-only-exception.',
                );
            }

            $result = $commit
                ? $attestor->attest(
                    $references,
                    (string) $this->option('classification'),
                    (string) $this->option(
                        'authorization-reference',
                    ),
                )
                : $attestor->inspect(
                    $references,
                    (string) $this->option('classification'),
                );

            $this->line(json_encode(
                $result,
                JSON_THROW_ON_ERROR
                    | ((bool) $this->option('json')
                        ? 0
                        : JSON_PRETTY_PRINT),
            ));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->line(json_encode([
                'schema' => 'x-journal.integrity-exception-attestation.v1',
                'success' => false,
                'status' => 'rejected',
                'message' => $exception->getMessage(),
                'committed' => false,
                'original_entries_unchanged' => true,
            ], JSON_THROW_ON_ERROR));

            return self::FAILURE;
        }
    }
}
