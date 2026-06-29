<?php

namespace LBHurtado\XJournal\Renderers;

use Illuminate\Support\Collection;
use LBHurtado\XJournal\Contracts\JournalArtifactRendererContract;
use LBHurtado\XJournal\Data\JournalArtifactData;
use LBHurtado\XJournal\Data\JournalArtifactProfileData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class TextStatementArtifactRenderer implements JournalArtifactRendererContract
{
    public function supports(JournalArtifactProfileData $profile): bool
    {
        return $profile->type === 'statement' && $profile->format === 'text/plain';
    }

    /**
     * @param  Collection<int, ExecutionJournalEntry>  $entries
     */
    public function render(Collection $entries, JournalArtifactProfileData $profile): JournalArtifactData
    {
        $lines = ['Journal Statement'];
        $referenceNumbers = [];

        foreach ($entries as $entry) {
            $referenceNumbers[] = $entry->reference_number;
            $lines[] = implode(' | ', [
                $entry->reference_number,
                $entry->event_type,
                $entry->occurred_at?->toJSON(),
                $entry->subject_type.':'.$entry->subject_id,
            ]);
        }

        return new JournalArtifactData('statement', 'text/plain', implode(PHP_EOL, $lines), $referenceNumbers, [
            'renderer' => static::class,
            'entry_count' => $entries->count(),
        ]);
    }
}
