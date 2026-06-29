<?php

namespace LBHurtado\XJournal\Renderers;

use Illuminate\Support\Collection;
use LBHurtado\XJournal\Contracts\JournalArtifactRendererContract;
use LBHurtado\XJournal\Data\JournalArtifactData;
use LBHurtado\XJournal\Data\JournalArtifactProfileData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class TextReceiptArtifactRenderer implements JournalArtifactRendererContract
{
    public function supports(JournalArtifactProfileData $profile): bool
    {
        return $profile->type === 'receipt' && $profile->format === 'text/plain';
    }

    /**
     * @param  Collection<int, ExecutionJournalEntry>  $entries
     */
    public function render(Collection $entries, JournalArtifactProfileData $profile): JournalArtifactData
    {
        $entry = $entries->first();

        if (! $entry instanceof ExecutionJournalEntry) {
            return new JournalArtifactData('receipt', 'text/plain', 'Journal Receipt: no entries', [], [
                'renderer' => static::class,
            ]);
        }

        $content = implode(PHP_EOL, [
            'Journal Receipt',
            'Reference: '.$entry->reference_number,
            'Event: '.$entry->event_type,
            'Occurred At: '.$entry->occurred_at?->toJSON(),
            'Subject: '.$entry->subject_type.':'.$entry->subject_id,
        ]);

        return new JournalArtifactData('receipt', 'text/plain', $content, [$entry->reference_number], [
            'renderer' => static::class,
        ]);
    }
}
