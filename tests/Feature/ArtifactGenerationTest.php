<?php

use Illuminate\Support\Collection;
use Carbon\CarbonImmutable;
use LBHurtado\XJournal\Contracts\JournalArtifactRendererContract;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Data\JournalArtifactData;
use LBHurtado\XJournal\Data\JournalArtifactProfileData;
use LBHurtado\XJournal\Exceptions\JournalArtifactRendererNotFoundException;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Renderers\TextReceiptArtifactRenderer;
use LBHurtado\XJournal\Renderers\TextStatementArtifactRenderer;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XJournal\Services\JournalArtifactGenerator;

function artifactJournalEntryData(?string $referenceNumber = null): ExecutionJournalEntryData
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
            providerReference: 'provider-1',
        ),
        payload: ['status' => 'succeeded'],
        money: new ExecutionMoneyData(amount: '100.00', currency: 'PHP', minorAmount: 10000),
        metadata: ['source' => 'artifact-test'],
        referenceNumber: $referenceNumber,
    );
}

it('normalizes artifact profiles', function () {
    $profile = JournalArtifactProfileData::fromArray([
        'type' => 'receipt',
        'format' => 'text/plain',
        'options' => ['locale' => 'en_PH'],
    ]);

    expect($profile->toArray())->toBe([
        'type' => 'receipt',
        'format' => 'text/plain',
        'options' => ['locale' => 'en_PH'],
    ]);
});

it('renders text receipts from canonical journal entries', function () {
    $entry = app(ExecutionJournalRecorder::class)->record(artifactJournalEntryData());

    $artifact = app(JournalArtifactGenerator::class)->generate([$entry], new JournalArtifactProfileData('receipt'));

    expect($artifact->type)->toBe('receipt')
        ->and($artifact->format)->toBe('text/plain')
        ->and($artifact->referenceNumbers)->toBe(['ERN-2026-000000001'])
        ->and($artifact->content)->toContain('Journal Receipt')
        ->and($artifact->content)->toContain('Reference: ERN-2026-000000001')
        ->and($artifact->metadata['renderer'])->toBe(TextReceiptArtifactRenderer::class);
});

it('renders text statements from multiple canonical journal entries', function () {
    $recorder = app(ExecutionJournalRecorder::class);

    $first = $recorder->record(artifactJournalEntryData());
    $second = $recorder->record(artifactJournalEntryData());

    $artifact = app(JournalArtifactGenerator::class)->generate(
        ExecutionJournalEntry::query()->orderBy('id')->get(),
        new JournalArtifactProfileData('statement')
    );

    expect($artifact->type)->toBe('statement')
        ->and($artifact->referenceNumbers)->toBe([$first->reference_number, $second->reference_number])
        ->and($artifact->content)->toContain('Journal Statement')
        ->and($artifact->content)->toContain($first->reference_number)
        ->and($artifact->content)->toContain($second->reference_number)
        ->and($artifact->metadata['entry_count'])->toBe(2)
        ->and($artifact->metadata['renderer'])->toBe(TextStatementArtifactRenderer::class);
});

it('treats artifacts as renderings and not journal truth', function () {
    $entry = app(ExecutionJournalRecorder::class)->record(artifactJournalEntryData());
    $original = $entry->fresh()?->toArray();

    app(JournalArtifactGenerator::class)->generate([$entry], new JournalArtifactProfileData('receipt'));

    expect(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and($entry->fresh()?->toArray())->toBe($original);
});

it('fails clearly when no renderer supports a profile', function () {
    $entry = app(ExecutionJournalRecorder::class)->record(artifactJournalEntryData());

    expect(fn () => app(JournalArtifactGenerator::class)->generate(
        [$entry],
        new JournalArtifactProfileData('receipt', 'application/pdf')
    ))->toThrow(JournalArtifactRendererNotFoundException::class);
});

it('allows package consumers to register artifact renderers', function () {
    $entry = app(ExecutionJournalRecorder::class)->record(artifactJournalEntryData());

    $generator = new JournalArtifactGenerator;
    $generator->register(new class implements JournalArtifactRendererContract
    {
        public function supports(JournalArtifactProfileData $profile): bool
        {
            return $profile->type === 'receipt' && $profile->format === 'application/json';
        }

        /**
         * @param  Collection<int, ExecutionJournalEntry>  $entries
         */
        public function render(Collection $entries, JournalArtifactProfileData $profile): JournalArtifactData
        {
            $entry = $entries->first();

            return new JournalArtifactData(
                type: 'receipt',
                format: 'application/json',
                content: json_encode(['reference_number' => $entry?->reference_number], JSON_THROW_ON_ERROR),
                referenceNumbers: [$entry?->reference_number],
                metadata: ['renderer' => static::class],
            );
        }
    });

    $artifact = $generator->generate([$entry], new JournalArtifactProfileData('receipt', 'application/json'));

    expect($artifact->format)->toBe('application/json')
        ->and($artifact->content)->toBe('{"reference_number":"ERN-2026-000000001"}');
});
