<?php

namespace LBHurtado\XJournal;

use Illuminate\Support\ServiceProvider;
use LBHurtado\XJournal\Contracts\JournalSinkContract;
use LBHurtado\XJournal\Services\DatabaseJournalSink;
use LBHurtado\XJournal\Services\ExecutionJournalIntegrityHasher;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XJournal\Services\ExecutionReferenceNumberGenerator;
use LBHurtado\XJournal\Services\JournalArtifactGenerator;
use LBHurtado\XJournal\Services\JournalEventRecorder;
use LBHurtado\XJournal\Services\JournalEventTransformerRegistry;
use LBHurtado\XJournal\Services\JournalSinkDispatcher;
use LBHurtado\XJournal\Services\JournalVisibilityGate;
use LBHurtado\XJournal\Policies\ActorOrSubjectJournalVisibilityPolicy;
use LBHurtado\XJournal\Renderers\TextReceiptArtifactRenderer;
use LBHurtado\XJournal\Renderers\TextStatementArtifactRenderer;
use LBHurtado\XJournal\Transformers\ClaimLifecycleJournalTransformer;
use LBHurtado\XJournal\Transformers\ExecutionResultJournalTransformer;
use LBHurtado\XJournal\Transformers\ProviderCallbackJournalTransformer;
use LBHurtado\XJournal\Transformers\ReconciliationJournalTransformer;

class XJournalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/x-journal.php',
            'x-journal'
        );

        $this->app->singleton(ExecutionJournalIntegrityHasher::class);
        $this->app->singleton(ExecutionReferenceNumberGenerator::class);
        $this->app->singleton(DatabaseJournalSink::class);
        $this->app->singleton(JournalSinkDispatcher::class);
        $this->app->singleton(JournalSinkContract::class, JournalSinkDispatcher::class);
        $this->app->singleton(ExecutionJournalRecorder::class);
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
                ->register(new ReconciliationJournalTransformer);
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
