<?php

namespace LBHurtado\XJournal\Contracts;

use Illuminate\Support\Collection;
use LBHurtado\XJournal\Data\JournalArtifactData;
use LBHurtado\XJournal\Data\JournalArtifactProfileData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

interface JournalArtifactRendererContract
{
    public function supports(JournalArtifactProfileData $profile): bool;

    /**
     * @param  Collection<int, ExecutionJournalEntry>  $entries
     */
    public function render(Collection $entries, JournalArtifactProfileData $profile): JournalArtifactData;
}
