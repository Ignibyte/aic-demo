<?php

declare(strict_types=1);

namespace App\Storefront\Service;

use App\Storefront\Entity\FeedVehicle;
use JsonSerializable;
use Override;

/**
 * The feed's answer for a list of vehicles, `{"vehicles": [...]}`, or for a vehicle not for sale,
 * `{"error": "..."}`.
 */
final readonly class FeedBody implements JsonSerializable
{
    /**
     * @param list<FeedVehicle> $vehicles The vehicles.
     * @param string            $error    Why there is no vehicle; empty for a list.
     */
    public function __construct(
        private array $vehicles,
        private string $error = '',
    ) {
    }

    /**
     * @return array<string, list<FeedVehicle>|string> The vehicles, or the error.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->error === ''
            ? ['vehicles' => $this->vehicles]
            : ['error' => $this->error];
    }
}
