<?php

namespace Goldnead\Accounts\PersonalData;

/**
 * What one eraser did: row counts by kind, never a value from a row.
 */
final class ErasureResult
{
    /**
     * @param  array<string, int>  $deleted
     * @param  array<string, int>  $anonymized
     * @param  array<string, int>  $retained
     */
    public function __construct(
        public readonly string $key,
        public readonly array $deleted = [],
        public readonly array $anonymized = [],
        public readonly array $retained = [],
        public readonly ?string $note = null,
    ) {}

    /**
     * @return array{deleted: array<string, int>, anonymized: array<string, int>, retained: array<string, int>, note: string|null}
     */
    public function toArray(): array
    {
        return [
            'deleted' => $this->deleted,
            'anonymized' => $this->anonymized,
            'retained' => $this->retained,
            'note' => $this->note,
        ];
    }
}
