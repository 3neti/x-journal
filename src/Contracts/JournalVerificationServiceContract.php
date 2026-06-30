<?php

namespace LBHurtado\XJournal\Contracts;

use LBHurtado\XJournal\Data\ExecutionVerificationData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

interface JournalVerificationServiceContract
{
    public function verify(ExecutionJournalEntry $entry): ExecutionVerificationData;

    public function tokenFor(ExecutionJournalEntry $entry): ?string;

    public function validateToken(string $referenceNumber, ?string $token): bool;
}
