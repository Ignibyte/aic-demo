<?php

declare(strict_types=1);

namespace App\Site\Component;

use AIC\Component\Contracts\Layout;
use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Component\Domain\TrackedAssets;
use InvalidArgumentException;
use Override;

/** The Lotline document shell, with one content region and tracked assets. */
final readonly class BootstrapLayout implements Layout
{
    /**
     * @param string        $name          The document title.
     * @param TrackedAssets $trackedAssets The stylesheet and pinned browser runtime.
     * @param Regions       $pageRegions   The regions filled by the page.
     * @param Region        $content       The visible content region.
     * @param string        $csrfToken     The visitor's token, issued by the framework.
     */
    public function __construct(
        public string $name,
        public TrackedAssets $trackedAssets,
        private Regions $pageRegions,
        public Region $content,
        public string $csrfToken = '',
    ) {
    }

    /** @return list<string> The region this layout places. */
    #[Override]
    public function names(): array
    {
        return ['content'];
    }

    /** @return Regions The page's filled regions. */
    #[Override]
    public function regions(): Regions
    {
        return $this->pageRegions;
    }

    /**
     * @param Regions $regions The regions after framework composition.
     *
     * @throws InvalidArgumentException When a region is invalid.
     *
     * @return self The layout holding those regions.
     */
    #[Override]
    public function withRegions(Regions $regions): self
    {
        return new self($this->name, $this->trackedAssets, $regions, $regions->region('content'), $this->csrfToken);
    }

    /** @return string The stable browser layout identity. */
    #[Override]
    public function tesseraLayout(): string
    {
        return 'lotline-bootstrap';
    }

    /** @return string The document title. */
    #[Override]
    public function title(): string
    {
        return $this->name;
    }

    /** @return TrackedAssets The CSS and pinned script. */
    #[Override]
    public function assets(): TrackedAssets
    {
        return $this->trackedAssets;
    }

    /** @return string The visitor's framework token. */
    #[Override]
    public function csrfToken(): string
    {
        return $this->csrfToken;
    }

    /**
     * @param string $csrfToken The token issued for this visitor.
     *
     * @return self The layout carrying the token.
     */
    #[Override]
    public function withCsrfToken(string $csrfToken): self
    {
        return new self($this->name, $this->trackedAssets, $this->pageRegions, $this->content, $csrfToken);
    }
}
