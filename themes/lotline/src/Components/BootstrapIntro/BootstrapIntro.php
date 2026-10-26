<?php

declare(strict_types=1);

namespace App\Theme\Components\BootstrapIntro;

use AIC\Component\Contracts\Component;

/**
 * The foundation page's introduction, from bootstrap-intro.phtml: the app, the dealership it is for, what is being
 * built and a link to the health check.
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
