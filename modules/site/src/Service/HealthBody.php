<?php

declare(strict_types=1);

namespace App\Site\Service;

use JsonSerializable;
use Override;

/** Serialize the public database health response. */
final readonly class HealthBody implements JsonSerializable
{
    /**
     * @param bool   $ready    Whether the database answered.
     * @param string $protocol The request protocol version.
     */
    public function __construct(private bool $ready, private string $protocol)
    {
    }

    /** @return array<string, string> The application, database and protocol details. */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['application' => 'Lotline', 'database' => $this->ready ? 'up' : 'down', 'http' => $this->protocol];
    }
}
