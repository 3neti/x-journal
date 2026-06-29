<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Contracts\JournalEventTransformerContract;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\JournalEventData;
use LBHurtado\XJournal\Exceptions\JournalEventTransformerNotFoundException;

class JournalEventTransformerRegistry
{
    /**
     * @var array<int, JournalEventTransformerContract>
     */
    protected array $transformers = [];

    public function register(JournalEventTransformerContract $transformer): self
    {
        $this->transformers[] = $transformer;

        return $this;
    }

    public function transform(JournalEventData $event): ExecutionJournalEntryData
    {
        foreach ($this->transformers as $transformer) {
            if ($transformer->supports($event)) {
                return $transformer->transform($event);
            }
        }

        throw JournalEventTransformerNotFoundException::forEventType($event->eventType);
    }
}
