<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Session\Application\RequestSession;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The resilience feed's outage (#34), kept in the visitor's session so each reviewer's outage is their own: how many
 * of the feed's polls are still to fail, and how many failed in the last outage. "Take the feed down" starts one three
 * polls long; `ResilienceFaults` answers those polls 503 and counts them here, and the poll after them answers again.
 */
final readonly class ResilienceOutage
{
    /**
     * How many polls an outage fails.
     */
    public const int POLLS = 3;

    /**
     * The session key that keeps how many polls are still to fail.
     */
    private const string DOWN = 'site.feed.down';

    /**
     * The session key that keeps how many polls failed in the last outage.
     */
    private const string FAILED = 'site.feed.failed';

    /**
     * @param RequestSession $requestSession Reads the session off the request.
     */
    public function __construct(
        private RequestSession $requestSession = new RequestSession(),
    ) {
    }

    /**
     * Starts an outage: the next three polls fail, and none has failed yet.
     *
     * @param ServerRequestInterface $serverRequest The take-down post, with its session.
     *
     * @throws LogicException When the request carries no session.
     */
    public function start(ServerRequestInterface $serverRequest): void
    {
        $session = $this->requestSession->read($serverRequest);
        $session->set(self::DOWN, (string) self::POLLS);
        $session->set(self::FAILED, '0');
    }

    /**
     * Whether this poll fails; when it does, it is counted: one fewer to fail, one more failed.
     *
     * @param ServerRequestInterface $serverRequest A poll of the feed, with its session.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return bool True while the outage has polls left to fail.
     */
    public function fails(ServerRequestInterface $serverRequest): bool
    {
        $down = $this->down($serverRequest);

        if ($down < 1) {
            return false;
        }

        $session = $this->requestSession->read($serverRequest);
        $session->set(self::DOWN, (string) ($down - 1));
        $session->set(self::FAILED, (string) ($this->failed($serverRequest) + 1));

        return true;
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return int How many polls are still to fail; 0 when the feed is up.
     */
    public function down(ServerRequestInterface $serverRequest): int
    {
        return (int) ($this->requestSession->read($serverRequest)->get(self::DOWN) ?? '0');
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return int How many polls failed in the last outage; 0 when there was none.
     */
    public function failed(ServerRequestInterface $serverRequest): int
    {
        return (int) ($this->requestSession->read($serverRequest)->get(self::FAILED) ?? '0');
    }
}
