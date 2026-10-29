<?php

declare(strict_types=1);

namespace App\Reports\Entity;

use JsonSerializable;
use Override;

/**
 * Why `/api/reports/sales` refused (#21): what went wrong, and, for a refused range, each field's error by its name.
 */
final readonly class ApiMessage implements JsonSerializable
{
    /**
     * @param string                $error  What went wrong.
     * @param array<string, string> $fields Each refused field's error, by its name; empty for none.
     */
    public function __construct(
        private string $error,
        private array $fields = [],
    ) {
    }

    /**
     * @return array<string, string|array<string, string>> The error, and the fields when there are any.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->fields === []
            ? ['error' => $this->error]
            : ['error' => $this->error, 'fields' => $this->fields];
    }
}
