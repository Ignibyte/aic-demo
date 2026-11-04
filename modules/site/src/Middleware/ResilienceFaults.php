<?php

declare(strict_types=1);

namespace App\Site\Middleware;

use AIC\Http\Application\ResponseBuilder;
use AIC\Tessera\Domain\TesseraRequest;
use App\Site\Service\ResilienceCases;
use App\Site\Service\ResilienceOutage;
use InvalidArgumentException;
use LogicException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The parties the style guide's resilience cases need and a page cannot be (#34), each on its own case path alone: a
 * page answers only with AIC's sealed responses, and none of them is plain text with these statuses.
 *
 * - The gateway in front of the app, which gave up waiting: every POST to the gateway case answers 504 in plain text,
 *   at once, before the router. Tessera cannot use that answer to a write, so it reports the write indeterminate.
 * - A server that speaks only protocol 1: a Tessera request to the protocol case answers 400 with
 *   `Tessera-Protocol: 1`, as AIC's own protocol check refuses, and Tessera loads the page whole instead.
 * - A feed that is down: while `ResilienceOutage` has polls left to fail, a Tessera refresh of the feed case answers
 *   503 in plain text, which Tessera reports as an error.
 *
 * Every other request goes on untouched. It runs after the session, so the outage's counts are saved with it.
 */
final readonly class ResilienceFaults implements MiddlewareInterface
{
    /**
     * The media type of every answer it gives.
     */
    private const string TEXT = 'text/plain; charset=utf-8';

    /**
     * @param ResponseBuilder  $responseBuilder  Makes the answers.
     * @param ResilienceOutage $resilienceOutage The feed's outage.
     */
    public function __construct(
        private ResponseBuilder $responseBuilder,
        private ResilienceOutage $resilienceOutage = new ResilienceOutage(),
    ) {
    }

    /**
     * @param ServerRequestInterface  $request The request, with its session.
     * @param RequestHandlerInterface $handler The rest of the stack.
     *
     * @throws LogicException When a constructed value is invalid, or the request carries no session.
     *
     * @return ResponseInterface The case's fault, or the rest of the stack's answer.
     */
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $fault = match ($request->getUri()->getPath()) {
            ResilienceCases::GATEWAY => $this->gateway($request),
            ResilienceCases::PROTOCOL => $this->oldProtocol($request),
            ResilienceCases::FEED => $this->downFeed($request),
            default => null,
        };

        return $fault ?? $handler->handle($request);
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to the gateway case.
     *
     * @throws InvalidArgumentException When a constructed value is invalid.
     *
     * @return ResponseInterface|null 504 in plain text to a POST, as the gateway gives after its own timeout; null for
     *                                anything else.
     */
    private function gateway(ServerRequestInterface $serverRequest): ?ResponseInterface
    {
        return $serverRequest->getMethod() === 'POST'
            ? $this->responseBuilder->create(504, self::TEXT, "The gateway timed out waiting for the app.\n")
            : null;
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to the protocol case.
     *
     * @throws InvalidArgumentException When a constructed value is invalid.
     *
     * @return ResponseInterface|null 400 naming protocol 1, varied on Tessera's request headers, with an empty body, to
     *                                a Tessera request; null for a plain one.
     */
    private function oldProtocol(ServerRequestInterface $serverRequest): ?ResponseInterface
    {
        if ($serverRequest->getHeaderLine(TesseraRequest::REQUEST) === '') {
            return null;
        }

        $response = $this->responseBuilder->create(400, self::TEXT, '');

        return $this->responseBuilder->withHeader(
            $this->responseBuilder->withHeader($response, TesseraRequest::PROTOCOL, '1'),
            'Vary',
            TesseraRequest::VARY,
        );
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to the feed case, with its session.
     *
     * @throws LogicException When a constructed value is invalid, or the request carries no session.
     *
     * @return ResponseInterface|null 503 in plain text to a Tessera refresh while the outage has polls left to fail,
     *                                each counted; null otherwise.
     */
    private function downFeed(ServerRequestInterface $serverRequest): ?ResponseInterface
    {
        if ($serverRequest->getHeaderLine(TesseraRequest::REQUEST) !== 'refresh') {
            return null;
        }

        return $this->resilienceOutage->fails($serverRequest)
            ? $this->responseBuilder->create(503, self::TEXT, "The feed is down.\n")
            : null;
    }
}
