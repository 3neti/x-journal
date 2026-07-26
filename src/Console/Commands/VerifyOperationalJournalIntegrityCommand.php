<?php

namespace LBHurtado\XJournal\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XJournal\Services\AttestedJournalIntegrityVerifier;

final class VerifyOperationalJournalIntegrityCommand extends Command
{
    protected $signature = 'x-journal:verify-operational-integrity
        {--json : Emit a machine-readable result}';

    protected $description = 'Verify journal integrity with explicit legacy exception attestations';

    public function handle(
        AttestedJournalIntegrityVerifier $verifier,
    ): int {
        $result = $verifier->verify();
        $this->line(json_encode(
            $result,
            JSON_THROW_ON_ERROR
                | ((bool) $this->option('json')
                    ? 0
                    : JSON_PRETTY_PRINT),
        ));

        return $result['verified']
            ? self::SUCCESS
            : self::FAILURE;
    }
}
