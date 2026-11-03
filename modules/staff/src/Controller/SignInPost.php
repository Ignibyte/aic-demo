<?php

declare(strict_types=1);

namespace App\Staff\Controller;

use AIC\Auth\Application\Authenticator;
use AIC\Auth\Domain\SignedInUser;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageFullVisit;
use AIC\Form\Application\PostedFields;
use AIC\Form\Domain\SameOriginPath;
use App\Staff\Entity\StaffMember;
use App\Staff\Form\SignInForm;
use App\Staff\Repository\StaffRepository;
use App\Staff\Service\StaffDealership;
use App\Staff\Service\StaffSession;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * POST /sign-in: signs the member of staff in and goes to `next` when it is a path on this site, else to /app, as a
 * full visit so the header loads again; a wrong email or password shows the form again (422), never saying which.
 */
final readonly class SignInPost implements Page
{
    /**
     * What a refused sign-in says.
     */
    private const string REFUSED = 'Wrong email or password.';

    /**
     * @param SignInForm      $signInForm      The sign-in page, shown again on a refusal.
     * @param Authenticator   $authenticator   Signs the member in.
     * @param StaffRepository $staffRepository Reads who signed in.
     * @param StaffDealership $staffDealership The dealership's name, for the panel's line.
     * @param StaffSession    $staffSession    Keeps them in the session.
     * @param PostedFields    $postedFields    Reads the posted fields.
     */
    public function __construct(
        private SignInForm $signInForm,
        private Authenticator $authenticator,
        private StaffRepository $staffRepository,
        private StaffDealership $staffDealership,
        private StaffSession $staffSession = new StaffSession(),
        private PostedFields $postedFields = new PostedFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws RuntimeException When the member or the dealership's name cannot be read (`QueryException`), or their
     *                          stored role is no role (`UnexpectedValueException`).
     * @throws LogicException   When the page does not fit the theme, or the request carries no session.
     *
     * @return PageResponse A full visit to `next` or /app, or the form again as 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $email = $this->postedFields->value($serverRequest, 'email');
        $next = $this->postedFields->value($serverRequest, 'next');
        $password = $this->postedFields->value($serverRequest, 'password');
        // login() hands back what StaffRepository read with the credentials (AIC #238), so the member is read once.
        $signedIn = $this->authenticator->login($serverRequest, $email, $password);
        $staffMember = $signedIn instanceof SignedInUser
            ? $this->staffRepository->signedIn($signedIn)
            : null;

        if (!$staffMember instanceof StaffMember) {
            return $this->signInForm->page($serverRequest, $email, $next, self::REFUSED, 422, $this->staffDealership->name());
        }

        $this->staffSession->remember($serverRequest, $staffMember);

        return new PageFullVisit((new SameOriginPath('/app'))->resolve($next));
    }
}
