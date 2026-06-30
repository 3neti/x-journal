<?php

namespace LBHurtado\XJournal\Data;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class ExecutionStatementSnapshotQueryData extends Data
{
    public function __construct(
        public ?string $statementType = null,
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        public ?string $statementNumber = null,
        public ?CarbonInterface $generatedAfter = null,
        public ?CarbonInterface $generatedBefore = null,
        public int $limit = 50,
        public int $offset = 0,
        public string $order = 'desc',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            statementType: self::nullableString($data['statement_type'] ?? null),
            subjectType: self::nullableString($data['subject_type'] ?? null),
            subjectId: self::nullableString($data['subject_id'] ?? null),
            statementNumber: self::nullableString($data['statement_number'] ?? null),
            generatedAfter: self::nullableDateTime($data['generated_after'] ?? null),
            generatedBefore: self::nullableDateTime($data['generated_before'] ?? null),
            limit: self::boundedInteger($data['limit'] ?? 50, 1, 200, 50),
            offset: self::boundedInteger($data['offset'] ?? 0, 0, PHP_INT_MAX, 0),
            order: self::orderValue($data['order'] ?? 'desc'),
        );
    }

    /**
     * @return array{
     *     statement_type: ?string,
     *     subject_type: ?string,
     *     subject_id: ?string,
     *     statement_number: ?string,
     *     generated_after: ?string,
     *     generated_before: ?string,
     *     limit: int,
     *     offset: int,
     *     order: string
     * }
     */
    public function toArray(): array
    {
        return [
            'statement_type' => $this->statementType,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'statement_number' => $this->statementNumber,
            'generated_after' => $this->generatedAfter?->toJSON(),
            'generated_before' => $this->generatedBefore?->toJSON(),
            'limit' => $this->limit,
            'offset' => $this->offset,
            'order' => $this->order,
        ];
    }

    protected static function nullableString(mixed $value): ?string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return null;
    }

    protected static function nullableDateTime(mixed $value): ?CarbonInterface
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value, 'UTC');
    }

    protected static function boundedInteger(mixed $value, int $minimum, int $maximum, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return max($minimum, min($maximum, (int) $value));
    }

    protected static function orderValue(mixed $value): string
    {
        return strtolower((string) $value) === 'asc' ? 'asc' : 'desc';
    }
}
