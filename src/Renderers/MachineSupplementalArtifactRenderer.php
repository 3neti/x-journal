<?php

namespace LBHurtado\XJournal\Renderers;

use Illuminate\Support\Collection;
use LBHurtado\XJournal\Contracts\JournalArtifactRendererContract;
use LBHurtado\XJournal\Data\JournalArtifactData;
use LBHurtado\XJournal\Data\JournalArtifactProfileData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class MachineSupplementalArtifactRenderer implements JournalArtifactRendererContract
{
    /**
     * @var array<int, string>
     */
    protected array $supportedTypes = [
        'certificate',
        'instrument',
        'timeline',
        'statement',
    ];

    public function supports(JournalArtifactProfileData $profile): bool
    {
        return $profile->format === 'application/json' && in_array($profile->type, $this->supportedTypes, true);
    }

    /**
     * @param  Collection<int, ExecutionJournalEntry>  $entries
     */
    public function render(Collection $entries, JournalArtifactProfileData $profile): JournalArtifactData
    {
        $entryPayload = $entries->map(function (ExecutionJournalEntry $entry): array {
            return [
                'reference_number' => $entry->reference_number,
                'event_type' => $entry->event_type,
                'occurred_at' => $entry->occurred_at?->toJSON(),
                'actor' => [
                    'id' => $entry->actor_id,
                    'type' => $entry->actor_type,
                ],
                'subject' => [
                    'id' => $entry->subject_id,
                    'type' => $entry->subject_type,
                ],
                'money' => $entry->money,
                'metadata' => $entry->metadata,
            ];
        })->values()->all();

        $content = [
            'type' => $profile->type,
            'format' => $profile->format,
            'options' => $profile->options,
            'entries' => $entryPayload,
        ];

        return new JournalArtifactData(
            type: $profile->type,
            format: 'application/json',
            content: json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            referenceNumbers: $entries->pluck('reference_number')->filter()->values()->all(),
            metadata: ['renderer' => static::class],
        );
    }
}
