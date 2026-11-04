<?php

declare(strict_types=1);

namespace App\Jobs\Service;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageUnchanged;
use AIC\Form\Exception\InvalidEnvelope;
use AIC\Tessera\Application\TesseraProps;
use AIC\Tessera\Domain\TesseraRequest;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A refresh of the jobs page's live part (#35), as the dashboard's `DashboardPoll` answers one (#14, AIC #193): a
 * refresh of `@jobs-live` alone whose props carry the version the queue has now answers 204 before the part is
 * built, and one whose props were altered answers 400. Tessera sends the part's anchor after its props (protocol 2,
 * AIC #194), so the props are read by the anchor wherever the page places the part.
 */
final readonly class JobsPoll
{
    /**
     * @param JobsAccess   $jobsAccess   The 400 page.
     * @param TesseraProps $tesseraProps Opens a refresh's props.
     */
    public function __construct(
        private JobsAccess $jobsAccess,
        private TesseraProps $tesseraProps,
    ) {
    }

    /**
     * Asked before the page builds anything, so the props are verified where a refusal can still answer 400: any
     * request that sends a token for the part has it opened, and only a refresh of the part alone may answer 204.
     *
     * @param ServerRequestInterface $serverRequest The jobs page's request.
     * @param string                 $version       What the part shows now (`JobsLive::version()`).
     *
     * @throws InvalidArgumentException When the refusal's page does not fit the theme.
     *
     * @return PageResponse|null 204 for a refresh of the part alone whose props carry that version; 400 when a request
     *                           sends altered props for the part; null when the page is to be built.
     */
    public function answer(ServerRequestInterface $serverRequest, string $version): ?PageResponse
    {
        $target = '@' . JobsLive::ANCHOR;

        try {
            $this->tesseraProps->forTarget($serverRequest, $target);

            return $this->tesseraProps->unchanged($serverRequest, $target, $version)
                ? new PageUnchanged()
                : null;
        } catch (InvalidEnvelope) {
            return $this->jobsAccess->altered();
        }
    }

    /**
     * A refresh of the part alone is answered with the part alone, so the page built for it shows nothing outside the
     * part: the intro is built for it without the notice, which waits in the session for the next whole page.
     *
     * @param ServerRequestInterface $serverRequest The jobs page's request.
     *
     * @return bool Whether it is a refresh, in AIC's protocol, of `@jobs-live` alone, the one request a 204 may answer.
     */
    public function partAlone(ServerRequestInterface $serverRequest): bool
    {
        $tesseraRequest = new TesseraRequest(
            $serverRequest->getHeaderLine(TesseraRequest::REQUEST),
            $serverRequest->getHeaderLine(TesseraRequest::PROTOCOL),
            $serverRequest->getHeaderLine(TesseraRequest::TARGETS),
            $serverRequest->getHeaderLine(TesseraRequest::LAYOUT),
        );

        return $tesseraRequest->refreshesOnly('@' . JobsLive::ANCHOR);
    }
}
