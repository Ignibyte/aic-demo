<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Controller\Domain\PageUnchanged;
use AIC\Form\Application\PostedFields;
use AIC\Form\Contracts\FormToken;
use AIC\Session\Application\RequestSession;
use AIC\Session\Domain\RandomTokens;
use App\Site\Service\ResilienceCases;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /theme/resilience/expired (#34), the style guide's expired page case. "Expire the token" (`intent=lapse`)
 * renews the session's form token and answers 204, which carries no token, so the page keeps one the session no longer
 * holds: its next write is refused by the form guard, 419 to Tessera and 403 to a plain browser, before it gets here.
 * Anything else that gets here came with the session's token, and goes back to the case page (303).
 */
final readonly class ResilienceExpiredPost implements Page
{
    /**
     * @param PostedFields   $postedFields   Reads the intent.
     * @param RequestSession $requestSession Reads the session off the request.
     * @param RandomTokens   $randomTokens   Makes the new token.
     */
    public function __construct(
        private PostedFields $postedFields,
        private RequestSession $requestSession = new RequestSession(),
        private RandomTokens $randomTokens = new RandomTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The post, with its session.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return PageResponse 204 after the lapse; otherwise 303 to the case page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        if ($this->postedFields->value($serverRequest, 'intent') !== 'lapse') {
            return new PageRedirect(ResilienceCases::EXPIRED);
        }

        $this->requestSession->read($serverRequest)->set(FormToken::SESSION_KEY, $this->randomTokens->fresh());

        return new PageUnchanged();
    }
}
