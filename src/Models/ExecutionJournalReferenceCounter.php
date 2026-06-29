<?php

namespace LBHurtado\XJournal\Models;

use Illuminate\Database\Eloquent\Model;

class ExecutionJournalReferenceCounter extends Model
{
    protected $table = 'execution_journal_reference_counters';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'next_sequence' => 'integer',
        ];
    }
}
