<?php

declare(strict_types=1);

namespace App\Chat\Controller;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use AIC\Controller\Domain\EventStream;
use AIC\Database\Contracts\QueryException;
use App\Chat\Service\ChatStreams;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /chat/events (#32): the visitor's stream, Server-Sent Events in Tessera's protocol, for the chat their session's
 * envelope names. A fresh connection sends the whole thread, then the signals; a resumed one the messages after its
 * `Last-Event-ID`; then what changes, for about twenty seconds, before it ends so Tessera reconnects. With no chat it
 * sends one closing event. It answers `Tessera-Stream-Pause: hidden` (AIC #259), so Tessera closes it while the tab is
 * hidden and reconnects with `Last-Event-ID` when it is visible again.
 */
final readonly class ChatEvents implements Stream
{
    /**
     * @param ChatStreams $chatStreams Builds the connection's source.
     */
    public function __construct(
        private ChatStreams $chatStreams,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @throws QueryException When the chat cannot be read.
     * @throws LogicException When the request carries no session, or a type is malformed.
     *
     * @return StreamResponse The event stream, paused while the page is hidden.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        return (new EventStream($this->chatStreams->visitor($serverRequest)))->pausedWhileHidden();
    }
}
