<?php

use LBHurtado\XJournal\Contracts\JournalSinkContract;
use LBHurtado\XJournal\Contracts\SecondaryJournalSinkContract;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XJournal\Services\JournalSinkDispatcher;
use LBHurtado\XJournal\Services\DatabaseJournalSink;
use LBHurtado\XJournal\Services\MonologJournalSink;
use LBHurtado\XJournal\Services\NullJournalSink;
use Carbon\CarbonImmutable;
use Psr\Log\AbstractLogger;

function sinkJournalEntryData(?string $referenceNumber = null): ExecutionJournalEntryData
{
    return new ExecutionJournalEntryData(
        eventType: 'voucher.redeemed',
        occurredAt: CarbonImmutable::parse('2026-06-29 10:15:00', 'UTC'),
        actor: new ExecutionActorData(id: 123, type: 'user', name: 'Beneficiary'),
        subject: new ExecutionSubjectData(id: 'voucher-1', type: 'voucher', display: 'Voucher #1'),
        references: new ExecutionReferenceData(
            correlationId: 'corr-1',
            causationId: 'cause-1',
            executionId: 'exec-1',
        ),
        payload: ['status' => 'succeeded'],
        money: new ExecutionMoneyData(amount: '100.00', currency: 'PHP', minorAmount: 10000),
        metadata: ['source' => 'sink-test'],
        referenceNumber: $referenceNumber,
    );
}

class RecordingSecondaryJournalSink implements SecondaryJournalSinkContract
{
    public ?ExecutionJournalEntry $entry = null;

    public ?ExecutionJournalEntryData $data = null;

    public int $calls = 0;

    public function recordProjection(ExecutionJournalEntry $entry, ExecutionJournalEntryData $data): void
    {
        $this->entry = $entry;
        $this->data = $data;
        $this->calls++;
    }
}

class FailingSecondaryJournalSink implements SecondaryJournalSinkContract
{
    public int $calls = 0;

    public function recordProjection(ExecutionJournalEntry $entry, ExecutionJournalEntryData $data): void
    {
        $this->calls++;

        throw new RuntimeException('projection failed');
    }
}

it('binds the journal sink contract to the sink dispatcher', function () {
    expect(app(JournalSinkContract::class))->toBeInstanceOf(JournalSinkDispatcher::class);
});

it('keeps the database sink as the canonical default sink', function () {
    $entry = app(ExecutionJournalRecorder::class)->record(sinkJournalEntryData());

    expect($entry)->toBeInstanceOf(ExecutionJournalEntry::class)
        ->and($entry->exists)->toBeTrue()
        ->and(ExecutionJournalEntry::query()->count())->toBe(1);
});

it('dispatches canonical entries to secondary sinks after database persistence', function () {
    $secondarySink = new RecordingSecondaryJournalSink;

    app(JournalSinkDispatcher::class)->addSecondarySink($secondarySink);

    $entry = app(ExecutionJournalRecorder::class)->record(sinkJournalEntryData());

    expect($secondarySink->calls)->toBe(1)
        ->and($secondarySink->entry?->is($entry))->toBeTrue()
        ->and($secondarySink->entry?->exists)->toBeTrue()
        ->and($secondarySink->data)->toBeInstanceOf(ExecutionJournalEntryData::class);
});

it('treats secondary sinks as projections and not canonical journal truth', function () {
    $secondarySink = new RecordingSecondaryJournalSink;

    app(JournalSinkDispatcher::class)->addSecondarySink($secondarySink);

    app(ExecutionJournalRecorder::class)->record(sinkJournalEntryData());

    expect($secondarySink->calls)->toBe(1)
        ->and(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and(ExecutionJournalEntry::query()->first()?->reference_number)->toBe('ERN-2026-000000001');
});

it('allows multiple secondary sinks to receive the same canonical entry', function () {
    $first = new RecordingSecondaryJournalSink;
    $second = new RecordingSecondaryJournalSink;

    app(JournalSinkDispatcher::class)
        ->addSecondarySink($first)
        ->addSecondarySink($second);

    $entry = app(ExecutionJournalRecorder::class)->record(sinkJournalEntryData());

    expect($first->entry?->is($entry))->toBeTrue()
        ->and($second->entry?->is($entry))->toBeTrue()
        ->and($first->data?->referenceNumber)->toBe('ERN-2026-000000001')
        ->and($second->data?->referenceNumber)->toBe('ERN-2026-000000001');
});

it('returns the canonical entry when a secondary sink projection fails', function () {
    $failing = new FailingSecondaryJournalSink;
    $dispatcher = new JournalSinkDispatcher(app(DatabaseJournalSink::class), [
        'failing' => $failing,
    ]);

    $entry = $dispatcher->recordWithSinkSelection(sinkJournalEntryData('ERN-2026-000000020'));

    expect($entry)->toBeInstanceOf(ExecutionJournalEntry::class)
        ->and($entry->exists)->toBeTrue()
        ->and(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and($failing->calls)->toBe(1)
        ->and($dispatcher->projectionFailures())->toHaveCount(1)
        ->and($dispatcher->projectionFailures()[0])->toMatchArray([
            'sink' => 'failing',
            'sink_class' => FailingSecondaryJournalSink::class,
            'reference_number' => 'ERN-2026-000000020',
            'exception_class' => RuntimeException::class,
            'message' => 'projection failed',
        ]);
});

it('continues dispatching remaining secondary sinks after a projection failure', function () {
    $failing = new FailingSecondaryJournalSink;
    $recording = new RecordingSecondaryJournalSink;
    $dispatcher = new JournalSinkDispatcher(app(DatabaseJournalSink::class), [
        'failing' => $failing,
        'recording' => $recording,
    ]);

    $entry = $dispatcher->recordWithSinkSelection(sinkJournalEntryData('ERN-2026-000000021'));

    expect($entry->exists)->toBeTrue()
        ->and($failing->calls)->toBe(1)
        ->and($recording->calls)->toBe(1)
        ->and($recording->entry?->is($entry))->toBeTrue()
        ->and($dispatcher->projectionFailures())->toHaveCount(1);
});

it('does not mutate canonical entry payload or integrity when secondary projection fails', function () {
    $dispatcher = new JournalSinkDispatcher(app(DatabaseJournalSink::class), [
        'failing' => new FailingSecondaryJournalSink,
    ]);

    $entry = $dispatcher->recordWithSinkSelection(sinkJournalEntryData('ERN-2026-000000022'));
    $persisted = $entry->fresh();

    expect($persisted?->payload)->toBe(['status' => 'succeeded'])
        ->and($persisted?->metadata)->toBe(['source' => 'sink-test'])
        ->and($persisted?->integrity['hash'])->toBeString()
        ->and($persisted?->integrity['hash'])->toBe($entry->integrity['hash'])
        ->and($persisted?->integrity['previous_hash'])->toBe($entry->integrity['previous_hash'])
        ->and(ExecutionJournalEntry::query()->count())->toBe(1);
});

it('supports explicit sink selection for secondary dispatch', function () {
    $selected = new RecordingSecondaryJournalSink;
    $unselected = new RecordingSecondaryJournalSink;

    $dispatcher = new JournalSinkDispatcher(app(DatabaseJournalSink::class), [
        'selected' => $selected,
        'unselected' => $unselected,
    ]);

    expect($dispatcher->secondarySinks())->toHaveKeys(['selected', 'unselected'])
        ->and($dispatcher->recordWithSinkSelection(sinkJournalEntryData('ERN-2026-000000010'), ['selected']))->toBeInstanceOf(ExecutionJournalEntry::class)
        ->and($selected->calls)->toBe(1)
        ->and($unselected->calls)->toBe(0)
        ->and(ExecutionJournalEntry::query()->count())->toBe(1);
});

it('supports optional null sink dispatch without side effects', function () {
    $dispatcher = new JournalSinkDispatcher(app(DatabaseJournalSink::class), [
        'null' => new NullJournalSink,
    ]);

    $entry = $dispatcher->recordWithSinkSelection(sinkJournalEntryData('ERN-2026-000000011'), ['null']);

    expect($entry)->toBeInstanceOf(ExecutionJournalEntry::class)
        ->and(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and($entry->exists)->toBeTrue();
});

it('supports monolog-style projection sinks', function () {
    $logger = new RecordingLogger;
    $dispatcher = new JournalSinkDispatcher(app(DatabaseJournalSink::class), [
        'monolog' => new MonologJournalSink(
            channel: 'default',
            logger: $logger,
            message: 'execution.journal.recorded.test',
        ),
    ]);

    $dispatcher->recordWithSinkSelection(sinkJournalEntryData('ERN-2026-000000012'), ['monolog']);

    expect($logger->messages)->toHaveCount(1)
        ->and($logger->messages[0]['message'])->toBe('execution.journal.recorded.test')
        ->and($logger->messages[0]['context']['ern'])->toBe('ERN-2026-000000012')
        ->and($logger->messages[0]['context']['event_type'])->toBe('voucher.redeemed');
});

it('keeps canonical persistence when selected sinks are unknown', function () {
    $dispatcher = new JournalSinkDispatcher(app(DatabaseJournalSink::class), [
        'null' => new NullJournalSink,
    ]);

    $entry = $dispatcher->recordWithSinkSelection(sinkJournalEntryData('ERN-2026-000000013'), ['missing']);

    expect(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and($entry->exists)->toBeTrue();
});

it('reads sink enablement from config when materializing the dispatcher', function () {
    config()->set('x-journal.sinks.monolog.enabled', true);

    app()->forgetInstance(JournalSinkDispatcher::class);
    $dispatcher = app(JournalSinkDispatcher::class);

    expect($dispatcher->hasSecondarySink('monolog'))->toBeTrue();

    config()->set('x-journal.sinks.monolog.enabled', false);
    app()->forgetInstance(JournalSinkDispatcher::class);
});

class RecordingLogger extends AbstractLogger
{
    public array $messages = [];

    public function log(mixed $level, mixed $message, array $context = []): void
    {
        $this->messages[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
