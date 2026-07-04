<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Contracts\JournalSinkContract;
use LBHurtado\XJournal\Contracts\SecondaryJournalSinkContract;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use Throwable;

class JournalSinkDispatcher implements JournalSinkContract
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $projectionFailures = [];

    /**
     * @param  array<string, SecondaryJournalSinkContract>  $secondarySinks
     */
    public function __construct(
        protected DatabaseJournalSink $canonicalSink,
        protected array $secondarySinks = [],
    ) {}

    public function addSecondarySink(SecondaryJournalSinkContract $sink, ?string $name = null): self
    {
        $this->secondarySinks[$name ?? $this->nextSecondaryName($sink)] = $sink;

        return $this;
    }

    /**
     * @return array<string, SecondaryJournalSinkContract>
     */
    public function secondarySinks(): array
    {
        return $this->secondarySinks;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function projectionFailures(): array
    {
        return $this->projectionFailures;
    }

    public function hasSecondarySink(string $name): bool
    {
        return array_key_exists($name, $this->secondarySinks);
    }

    public function record(ExecutionJournalEntryData $entry): ExecutionJournalEntry
    {
        return $this->recordWithSinkSelection($entry);
    }

    /**
     * @param  array<int, string>|null  $selectedSinks
     */
    public function recordWithSinkSelection(ExecutionJournalEntryData $entry, ?array $selectedSinks = null): ExecutionJournalEntry
    {
        $canonicalEntry = $this->canonicalSink->record($entry);

        $sinksToDispatch = $selectedSinks === null
            ? $this->secondarySinks
            : $this->selectedSecondarySinks($selectedSinks);

        foreach ($sinksToDispatch as $name => $secondarySink) {
            try {
                $secondarySink->recordProjection($canonicalEntry, $entry);
            } catch (Throwable $exception) {
                $this->captureProjectionFailure((string) $name, $secondarySink, $canonicalEntry, $exception);
            }
        }

        return $canonicalEntry;
    }

    protected function captureProjectionFailure(
        string $name,
        SecondaryJournalSinkContract $sink,
        ExecutionJournalEntry $entry,
        Throwable $exception,
    ): void {
        $failure = [
            'sink' => $name,
            'sink_class' => $sink::class,
            'reference_number' => $entry->reference_number,
            'entry_id' => $entry->getKey(),
            'exception_class' => $exception::class,
            'message' => $exception->getMessage(),
        ];

        $this->projectionFailures[] = $failure;

        logger()->warning('x-journal secondary sink projection failed', $failure);
    }

    /**
     * @param  array<int, string>  $selectedSinks
     * @return array<string, SecondaryJournalSinkContract>
     */
    protected function selectedSecondarySinks(array $selectedSinks): array
    {
        $targets = [];

        foreach ($selectedSinks as $name) {
            if (is_string($name) && isset($this->secondarySinks[$name])) {
                $targets[$name] = $this->secondarySinks[$name];
            }
        }

        return $targets;
    }

    protected function nextSecondaryName(SecondaryJournalSinkContract $sink): string
    {
        $base = class_basename($sink);
        $name = $base;

        $index = 1;
        while (array_key_exists($name, $this->secondarySinks)) {
            $index++;
            $name = $base.'#'.$index;
        }

        return $name;
    }
}
