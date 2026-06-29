<?php

namespace LBHurtado\XJournal\Data;

use Spatie\LaravelData\Data;

class JournalArtifactData extends Data
{
    /**
     * @param  array<int, string>  $referenceNumbers
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $type,
        public string $format,
        public string $content,
        public array $referenceNumbers = [],
        public array $metadata = [],
    ) {}
}
