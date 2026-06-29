<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Data\CockpitJournalEntryData;
use LBHurtado\XJournal\Data\CockpitJournalQueryData;
use LBHurtado\XJournal\Data\CockpitJournalViewData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class CockpitJournalReader
{
    public function __construct(
        protected JournalEntryRetriever $entries,
        protected JournalVisibilityGate $visibility,
    ) {}

    public function read(CockpitJournalQueryData $query): CockpitJournalViewData
    {
        $result = $this->entries->search($query->query);

        $visibleEntries = $result->entries
            ->map(function (ExecutionJournalEntry $entry) use ($query): ?CockpitJournalEntryData {
                $decision = $this->visibility->decide($entry, $query->actor);

                if (! $decision->allowed) {
                    return null;
                }

                return CockpitJournalEntryData::fromEntry($entry, $decision);
            })
            ->filter()
            ->values();

        return new CockpitJournalViewData(
            entries: $visibleEntries,
            retrievedTotal: $result->total,
            visibleTotal: $visibleEntries->count(),
            limit: $result->limit,
            offset: $result->offset,
            hasMore: $result->hasMore(),
            context: $query->context,
            metadata: $query->metadata,
        );
    }
}
