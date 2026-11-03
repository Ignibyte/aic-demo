<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\ComponentSource;
use App\Staff\Service\StaffDealership;
use App\Theme\Components\SiteFooter\SiteFooter;
use InvalidArgumentException;
use Override;
use RuntimeException;

/**
 * The footer line's source (#25): the dealership's name is read only when the page renders the footer, so a Tessera
 * answer for one target of the content reads no profile. The profiler showed every such answer reading it for a line
 * it never sent.
 */
final readonly class FooterPart implements ComponentSource
{
    /**
     * @param StaffDealership $staffDealership The dealership's name.
     * @param string          $siteName        The site's name, first on the line.
     */
    public function __construct(
        private StaffDealership $staffDealership,
        private string $siteName,
    ) {
    }

    /**
     * @throws RuntimeException         When the dealership's name cannot be read.
     * @throws InvalidArgumentException When an entity type the name is read through is malformed.
     *
     * @return Component The footer.
     */
    #[Override]
    public function build(): Component
    {
        return new SiteFooter($this->siteName, $this->staffDealership->name());
    }
}
