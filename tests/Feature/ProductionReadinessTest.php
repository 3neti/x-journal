<?php

function productionReadinessPackagePath(string $path): string
{
    return dirname(__DIR__, 2).'/'.$path;
}

it('documents package production readiness and explicit deferrals', function () {
    $readiness = file_get_contents(productionReadinessPackagePath('docs/architecture/x-journal/PRODUCTION_READINESS.md'));
    $adr = file_get_contents(productionReadinessPackagePath('docs/architecture/x-journal/ADR-0001-production-deferrals.md'));

    expect($readiness)->toContain(
        'Package name: `3neti/x-journal`',
        'Namespace: `LBHurtado\XJournal`',
        'Laravel auto-discovery provider: `LBHurtado\XJournal\XJournalServiceProvider`',
        'composer validate --strict',
        'composer test',
        '99 passed, 466 assertions',
        'Wave 2B — `x-action`',
    )
        ->and($adr)->toContain(
            'idempotency keys and duplicate suppression',
            'database-level immutability enforcement',
            'cryptographic signatures and key management',
            'redaction and presentation profiles',
            'visibility-aware cursor pagination',
            'live x-change/voucher/provider/campaign runtime call-site wiring',
        );
});

it('keeps composer metadata aligned with the documented release posture', function () {
    $composer = json_decode(file_get_contents(productionReadinessPackagePath('composer.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($composer)->toMatchArray([
        'name' => '3neti/x-journal',
        'type' => 'library',
        'autoload' => [
            'psr-4' => [
                'LBHurtado\\XJournal\\' => 'src/',
            ],
        ],
        'extra' => [
            'laravel' => [
                'providers' => [
                    'LBHurtado\\XJournal\\XJournalServiceProvider',
                ],
            ],
            'branch-alias' => [
                'dev-main' => '1.x-dev',
            ],
        ],
        'require' => [
            'php' => '^8.2',
            'illuminate/support' => '^12.0 || ^13.0',
            'spatie/laravel-data' => '^4.0',
        ],
        'scripts' => [
            'test' => 'php -d memory_limit=1G vendor/bin/pest',
            'test:coverage' => 'php -d memory_limit=1G vendor/bin/pest --coverage',
        ],
    ]);
});

it('keeps publishable config and migrations available for host installation', function () {
    expect(file_exists(productionReadinessPackagePath('config/x-journal.php')))->toBeTrue()
        ->and(file_exists(productionReadinessPackagePath('database/migrations/2026_06_29_000001_create_execution_journal_entries_table.php')))->toBeTrue()
        ->and(file_exists(productionReadinessPackagePath('database/migrations/2026_06_29_000002_harden_execution_journal_foundation.php')))->toBeTrue()
        ->and(file_get_contents(productionReadinessPackagePath('src/XJournalServiceProvider.php')))->toContain(
            "'x-journal-config'",
            'loadMigrationsFrom',
        );
});
