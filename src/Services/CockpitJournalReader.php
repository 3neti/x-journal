<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Contracts\JournalCockpitEntryPresentationContract;
use LBHurtado\XJournal\Contracts\JournalVisibilityProfileResolverContract;
use LBHurtado\XJournal\Data\CockpitJournalQueryData;
use LBHurtado\XJournal\Data\CockpitJournalViewData;
use LBHurtado\XJournal\Data\JournalRetrievalQueryData;

class CockpitJournalReader
{
    public function __construct(
        protected JournalEntryRetriever $entries,
        protected JournalVisibilityGate $visibility,
        protected JournalCockpitEntryPresentationContract $presenter,
        protected JournalVisibilityProfileResolverContract $profileResolver,
    ) {}

    public function read(CockpitJournalQueryData $query): CockpitJournalViewData
    {
        $limit = $query->query->limit;
        $offset = $query->query->offset;
        $visibleWindowStart = $offset;
        $visibleWindowEnd = $offset + $limit;
        $visibleIndex = 0;
        $hasMore = false;
        $scannedOffset = 0;
        $scanLimit = max(100, $limit * 3);
        $total = 0;
        $visibleEntries = collect();
        $finished = false;

        while (! $finished) {
            $scanQuery = new JournalRetrievalQueryData(
                referenceNumber: $query->query->referenceNumber,
                actorType: $query->query->actorType,
                actorId: $query->query->actorId,
                subjectType: $query->query->subjectType,
                subjectId: $query->query->subjectId,
                correlationId: $query->query->correlationId,
                causationId: $query->query->causationId,
                executionId: $query->query->executionId,
                eventType: $query->query->eventType,
                limit: $scanLimit,
                offset: $scannedOffset,
                order: $query->query->order,
            );

            $result = $this->entries->search($scanQuery);
            $total = $result->total;
            $scannedOffset += $scanLimit;

            foreach ($result->entries as $entry) {
                $decision = $this->visibility->decide($entry, $query->actor);

                if (! $decision->allowed) {
                    continue;
                }

                if ($visibleIndex >= $visibleWindowStart && $visibleIndex < $visibleWindowEnd) {
                    $profile = $this->profileResolver->resolve(
                        $entry,
                        $query->actor,
                        $decision,
                        $query->visibilityProfile,
                    );

                    $visibleEntries->push($this->presenter->present(
                        $entry,
                        $query->actor,
                        $decision,
                        $profile,
                    ));
                }

                $visibleIndex++;

                if ($visibleIndex > $visibleWindowEnd) {
                    $hasMore = true;
                    $finished = true;
                    break;
                }
            }

            if (! $finished && (count($result->entries) < $scanLimit || $scannedOffset > $total)) {
                $finished = true;
            }
        }

        return new CockpitJournalViewData(
            entries: $visibleEntries,
            retrievedTotal: $total,
            visibleTotal: $visibleEntries->count(),
            limit: $limit,
            offset: $offset,
            hasMore: $hasMore,
            context: $query->context,
            metadata: array_replace_recursive($query->metadata, [
                'pagination' => [
                    'limit_semantics' => 'visible_entries',
                    'offset_semantics' => 'visible_entries',
                    'visible_total_semantics' => 'page_visible_count',
                    'retrieved_total_semantics' => 'raw_matching_entries',
                    'has_more_semantics' => 'more_visible_entries',
                ],
            ]),
        );
    }
}
