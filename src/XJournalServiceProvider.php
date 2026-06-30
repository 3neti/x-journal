<?php

namespace LBHurtado\XJournal;

use Illuminate\Support\ServiceProvider;
use LBHurtado\XJournal\Contracts\JournalSinkContract;
use LBHurtado\XJournal\Services\CampaignJournalRecorder;
use LBHurtado\XJournal\Services\CockpitJournalReader;
use LBHurtado\XJournal\Services\DatabaseJournalSink;
use LBHurtado\XJournal\Services\ExecutionJournalIntegrityHasher;
use LBHurtado\XJournal\Services\ExecutionJournalIdempotencyHasher;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XJournal\Services\ExecutionReferenceNumberGenerator;
use LBHurtado\XJournal\Services\JournalEntryRetriever;
use LBHurtado\XJournal\Services\JournalArtifactGenerator;
use LBHurtado\XJournal\Services\JournalEventRecorder;
use LBHurtado\XJournal\Services\JournalEventTransformerRegistry;
use LBHurtado\XJournal\Services\JournalIntegrityVerifier;
use LBHurtado\XJournal\Services\JournalSinkDispatcher;
use LBHurtado\XJournal\Services\MonologJournalSink;
use LBHurtado\XJournal\Services\JournalVisibilityGate;
use LBHurtado\XJournal\Services\OperatorActionJournalRecorder;
use LBHurtado\XJournal\Services\ProviderCallbackJournalRecorder;
use LBHurtado\XJournal\Services\ReconciliationJournalRecorder;
use LBHurtado\XJournal\Services\NullJournalSink;
use LBHurtado\XJournal\Services\XChangeExecutionJournalRecorder;
use LBHurtado\XJournal\Policies\ActorOrSubjectJournalVisibilityPolicy;
use LBHurtado\XJournal\Renderers\TextReceiptArtifactRenderer;
use LBHurtado\XJournal\Renderers\TextStatementArtifactRenderer;
use LBHurtado\XJournal\Transformers\CampaignJournalTransformer;
use LBHurtado\XJournal\Transformers\ClaimLifecycleJournalTransformer;
use LBHurtado\XJournal\Transformers\ExecutionResultJournalTransformer;
use LBHurtado\XJournal\Transformers\OperatorActionJournalTransformer;
use LBHurtado\XJournal\Transformers\ProviderCallbackJournalTransformer;
use LBHurtado\XJournal\Transformers\ReconciliationJournalTransformer;
use Illuminate\Support\Arr;

class XJournalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/x-journal.php',
            'x-journal'
        );

        $this->app->singleton(ExecutionJournalIntegrityHasher::class);
        $this->app->singleton(ExecutionJournalIdempotencyHasher::class);
        $this->app->singleton(JournalIntegrityVerifier::class);
        $this->app->singleton(ExecutionReferenceNumberGenerator::class);
        $this->app->singleton(JournalEntryRetriever::class);
        $this->app->singleton(CockpitJournalReader::class);
        $this->app->singleton(DatabaseJournalSink::class);
        $this->app->singleton(MonologJournalSink::class, function (): MonologJournalSink {
            return new MonologJournalSink(
                channel: (string) Arr::get(config('x-journal.sinks.monolog'), 'channel', 'default'),
                message: (string) Arr::get(config('x-journal.sinks.monolog'), 'message', 'execution.journal.recorded'),
            );
        });
        $this->app->singleton(NullJournalSink::class);
        $this->app->singleton(JournalSinkDispatcher::class, function (): JournalSinkDispatcher {
            $dispatcher = new JournalSinkDispatcher(app(DatabaseJournalSink::class));

            if ((bool) Arr::get(config('x-journal.sinks.monolog'), 'enabled', false)) {
                $dispatcher->addSecondarySink(app(MonologJournalSink::class), 'monolog');
            }

            if ((bool) Arr::get(config('x-journal.sinks.null'), 'enabled', false)) {
                $dispatcher->addSecondarySink(app(NullJournalSink::class), 'null');
            }

            return $dispatcher;
        });
        $this->app->singleton(JournalSinkContract::class, JournalSinkDispatcher::class);
        $this->app->singleton(ExecutionJournalRecorder::class);
        $this->app->singleton(XChangeExecutionJournalRecorder::class);
        $this->app->singleton(ProviderCallbackJournalRecorder::class);
        $this->app->singleton(ReconciliationJournalRecorder::class);
        $this->app->singleton(OperatorActionJournalRecorder::class);
        $this->app->singleton(CampaignJournalRecorder::class);
        $this->app->singleton(JournalVisibilityGate::class, function (): JournalVisibilityGate {
            return (new JournalVisibilityGate)
                ->addPolicy(new ActorOrSubjectJournalVisibilityPolicy);
        });
        $this->app->singleton(JournalArtifactGenerator::class, function (): JournalArtifactGenerator {
            return (new JournalArtifactGenerator)
                ->register(new TextStatementArtifactRenderer)
                ->register(new TextReceiptArtifactRenderer);
        });
        $this->app->singleton(JournalEventTransformerRegistry::class, function (): JournalEventTransformerRegistry {
            return (new JournalEventTransformerRegistry)
                ->register(new ExecutionResultJournalTransformer)
                ->register(new ClaimLifecycleJournalTransformer)
                ->register(new ProviderCallbackJournalTransformer)
                ->register(new ReconciliationJournalTransformer)
                ->register(new OperatorActionJournalTransformer)
                ->register(new CampaignJournalTransformer);
        });
        $this->app->singleton(JournalEventRecorder::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/x-journal.php' => config_path('x-journal.php'),
        ], 'x-journal-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
