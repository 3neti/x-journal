<?php

namespace LBHurtado\XJournal;

use Illuminate\Support\ServiceProvider;

class XJournalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/x-journal.php',
            'x-journal'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/x-journal.php' => config_path('x-journal.php'),
        ], 'x-journal-config');
    }
}

