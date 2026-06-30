<?php

namespace LBHurtado\XJournal\Models;

use Illuminate\Database\Eloquent\Model;

class ExecutionStatementSnapshot extends Model
{
    protected $table = 'execution_statement_snapshots';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_datetime',
            'period_end' => 'immutable_datetime',
            'opening_json' => 'array',
            'activity_json' => 'array',
            'closing_json' => 'array',
            'payload_json' => 'array',
            'generated_at' => 'immutable_datetime',
        ];
    }
}
