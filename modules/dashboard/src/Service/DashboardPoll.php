<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\SourcedComponent;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageUnchanged;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Form\Exception\InvalidEnvelope;
use AIC\Tessera\Application\TesseraProps;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffPanels;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The dashboard's live part as Tessera polls it (#14, AIC #193): the part leads `content`, its version sealed in its
 * props, and a refresh of it alone whose props carry the version it has now answers 204 before anything is built. Only
 * a region's child carries a props token, so `StaffPages` places the part and gives its path; no code here spells one.
 */
final readonly class DashboardPoll
{
    /**
     * @param StaffPages    $staffPages    The staff pages' document, which places the part and names its path.
     * @param DashboardLive $dashboardLive The live part's source.
     * @param TesseraProps  $tesseraProps  Opens a refresh's props.
     * @param EnvelopeCodec $envelopeCodec Seals the part's props.
     */
    public function __construct(
        private StaffPages $staffPages,
        private DashboardLive $dashboardLive,
        private TesseraProps $tesseraProps,
        private EnvelopeCodec $envelopeCodec,
    ) {
    }

    /**
     * Asked before the page builds anything, so the props are verified where a refusal can still answer 400: any
     * request that sends a token for the live part has it opened, whatever else it names, and only a refresh of the
     * part alone may answer 204.
     *
     * @param ServerRequestInterface $serverRequest The dashboard's request.
     * @param string                 $version       What the live part's figures are now (`DashboardLive::version()`).
     *
     * @throws InvalidArgumentException When the refusal's page does not fit the theme.
     *
     * @return PageResponse|null 204 for a refresh of the live part alone whose props carry that version; 400 when a
     *                           request sends altered props for the part; null when the page is to be built.
     */
    public function answer(ServerRequestInterface $serverRequest, string $version): ?PageResponse
    {
        $target = $this->staffPages->target(DashboardLive::KEY);

        try {
            $this->tesseraProps->forTarget($serverRequest, $target);

            return $this->tesseraProps->unchanged($serverRequest, $target, $version)
                ? new PageUnchanged()
                : null;
        } catch (InvalidEnvelope) {
            return (new StaffPanels($this->staffPages, 'dashboard'))->warning(
                'Dashboard',
                'The dashboard could not be refreshed. Reload the page.',
                'dashboard-refused',
                'Reload the dashboard',
                '/app',
                400,
            );
        }
    }

    /**
     * @param string $version What the live part's figures are now.
     *
     * @throws JsonException            When the props cannot be sealed.
     * @throws InvalidArgumentException When the key, the anchor or the token breaks the grammar.
     *
     * @return KeyedComponent The live part under its key and anchor, its version sealed in its props, built from its
     *                        source when it is rendered.
     */
    public function part(string $version): KeyedComponent
    {
        $props = $this->envelopeCodec->seal(['version' => $version]);

        return new KeyedComponent(DashboardLive::KEY, new SourcedComponent($this->dashboardLive), $props, DashboardLive::ANCHOR);
    }
}
