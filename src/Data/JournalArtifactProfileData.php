<?php

namespace LBHurtado\XJournal\Data;

use Spatie\LaravelData\Data;

class JournalArtifactProfileData extends Data
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public string $type,
        public string $format = 'text/plain',
        public array $options = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: self::stringValue($data['type'] ?? 'statement', 'statement'),
            format: self::stringValue($data['format'] ?? 'text/plain', 'text/plain'),
            options: is_array($data['options'] ?? null) ? $data['options'] : [],
        );
    }

    protected static function stringValue(mixed $value, string $fallback): string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return $fallback;
    }
}
