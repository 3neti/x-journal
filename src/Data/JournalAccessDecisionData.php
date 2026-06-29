<?php

namespace LBHurtado\XJournal\Data;

use Spatie\LaravelData\Data;

class JournalAccessDecisionData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public bool $allowed,
        public string $reason,
        public ?string $policy = null,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function allow(string $reason, ?string $policy = null, array $metadata = []): self
    {
        return new self(true, $reason, $policy, $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function deny(string $reason, ?string $policy = null, array $metadata = []): self
    {
        return new self(false, $reason, $policy, $metadata);
    }
}
