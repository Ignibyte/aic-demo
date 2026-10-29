<?php

declare(strict_types=1);

namespace App\Staff\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Staff\Form\SignInForm;
use App\Staff\Service\StaffDealership;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function array_key_exists;
use function is_string;

/**
 * GET /sign-in: the sign-in form, carrying the `next` it was sent with, so signing in comes back there.
 */
final readonly class SignInPage implements Page
{
    /**
     * @param SignInForm      $signInForm      The sign-in page.
     * @param StaffDealership $staffDealership The dealership's name, for the panel's line.
     */
    public function __construct(
        private SignInForm $signInForm,
        private StaffDealership $staffDealership,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session and perhaps `?next=`.
     *
     * @throws LogicException   When the page does not fit the theme, or the request carries no session.
     * @throws RuntimeException When the dealership's name cannot be read.
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

        return $this->signInForm->page($serverRequest, '', $next, '', 200, $this->staffDealership->name());
    }
}
