<?php

namespace LBHurtado\XJournal\Renderers;

use Illuminate\Support\Collection;
use LBHurtado\XJournal\Contracts\JournalArtifactRendererContract;
use LBHurtado\XJournal\Data\JournalArtifactData;
use LBHurtado\XJournal\Data\JournalArtifactProfileData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class TextSupplementalArtifactRenderer implements JournalArtifactRendererContract
{
    /**
     * @var array<int, string>
     */
    protected array $supportedTypes = [
        'certificate',
        'instrument',
        'timeline',
    ];

    public function supports(JournalArtifactProfileData $profile): bool
    {
        return $profile->format === 'text/plain' && in_array($profile->type, $this->supportedTypes, true);
    }

    /**
     * @param  Collection<int, ExecutionJournalEntry>  $entries
     */
    public function render(Collection $entries, JournalArtifactProfileData $profile): JournalArtifactData
    {
        if ($entries->isEmpty()) {
            return new JournalArtifactData(
                type: $profile->type,
                format: 'text/plain',
                content: 'Journal '.ucfirst($profile->type).': no entries',
                metadata: ['renderer' => static::class],
            );
        }

        $title = 'Journal '.ucfirst($profile->type);
        $lines = [$title];
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

        return new JournalArtifactData(
            type: $profile->type,
            format: 'text/plain',
            content: implode(PHP_EOL, $lines),
            referenceNumbers: $referenceNumbers,
            metadata: [
                'renderer' => static::class,
                'entry_count' => $entries->count(),
            ],
        );
    }
}
