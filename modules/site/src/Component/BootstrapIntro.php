<?php

declare(strict_types=1);

namespace App\Site\Component;

use AIC\Component\Contracts\Component;

/**
 * The bootstrap introduction and its health link, ready for the template.
 */
final readonly class BootstrapIntro implements Component
{
    /**
     * @param string $title       The application name.
     * @param string $dealership  The fictional dealership.
     * @param string $description The foundation's current scope.
     * @param string $healthPath  The existing health endpoint.
     */
    public function __construct(
        public string $title,
        public string $dealership,
        public string $description,
        public string $healthPath,
    ) {
    }
}
