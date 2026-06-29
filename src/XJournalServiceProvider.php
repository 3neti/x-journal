<?php

namespace LBHurtado\XJournal;

use Illuminate\Support\ServiceProvider;
use LBHurtado\XJournal\Contracts\JournalSinkContract;
use LBHurtado\XJournal\Services\DatabaseJournalSink;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XJournal\Services\ExecutionReferenceNumberGenerator;

class XJournalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/x-journal.php',
            'x-journal'
        );

        $this->app->singleton(ExecutionReferenceNumberGenerator::class);
        $this->app->singleton(JournalSinkContract::class, DatabaseJournalSink::class);
        $this->app->singleton(ExecutionJournalRecorder::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/x-journal.php' => config_path('x-journal.php'),
        ], 'x-journal-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
