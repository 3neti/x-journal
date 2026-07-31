<?php

use LBHurtado\XJournal\XJournalServiceProvider;

it('registers the x-journal package configuration', function () {
    expect(config('x-journal'))->toBeArray()
        ->and(config('x-journal.connection'))->toBeNull();
});

it('registers the package service provider in the test application', function () {
    expect(app()->getLoadedProviders())
        ->toHaveKey(XJournalServiceProvider::class);
});
