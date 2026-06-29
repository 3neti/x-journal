<?php

namespace LBHurtado\XJournal\Models;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XJournal\Exceptions\JournalEntryImmutableException;

class ExecutionJournalEntry extends Model
{
    protected $table = 'execution_journal_entries';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw JournalEntryImmutableException::cannotUpdate();
        });

        static::deleting(function (): void {
            throw JournalEntryImmutableException::cannotDelete();
        });
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'actor' => 'array',
            'subject' => 'array',
            'money' => 'array',
            'references' => 'array',
            'payload' => 'array',
            'integrity' => 'array',
            'metadata' => 'array',
        ];
    }
}
