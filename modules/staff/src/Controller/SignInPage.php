<?php

declare(strict_types=1);

namespace App\Staff\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Staff\Form\SignInForm;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function is_string;

/**
 * GET /sign-in: the sign-in form, carrying the `next` it was sent with, so signing in comes back there.
 */
final readonly class SignInPage implements Page
{
    /**
     * @param SignInForm $signInForm The sign-in page.
     */
    public function __construct(
        private SignInForm $signInForm,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session and perhaps `?next=`.
     *
     * @throws LogicException When the page does not fit the theme, or the request carries no session.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $query = $serverRequest->getQueryParams();
        $next = array_key_exists('next', $query) && is_string($query['next'])
            ? $query['next']
            : '';

        return $this->signInForm->page($serverRequest, '', $next, '', 200);
    }
}
