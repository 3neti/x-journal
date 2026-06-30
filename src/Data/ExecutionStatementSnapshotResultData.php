<?php

namespace LBHurtado\XJournal\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class ExecutionStatementSnapshotResultData extends Data
{
    /**
     * @param  Collection<int, mixed>  $snapshots
     */
    public function __construct(
        public Collection $snapshots,
        public int $total,
        public int $limit,
        public int $offset,
    ) {}

    public function hasMore(): bool
    {
        return $this->offset + $this->limit < $this->total;
    }

    /**
     * @return array{
     *     snapshots: Collection<int, mixed>,
     *     total: int,
     *     limit: int,
     *     offset: int
     * }
     */
    public function toArray(): array
    {
        return [
            'snapshots' => $this->snapshots,
            'total' => $this->total,
            'limit' => $this->limit,
            'offset' => $this->offset,
        ];
    }
}
