<?php

declare(strict_types=1);

namespace App\Inbox\Service;

use AIC\Database\Contracts\QueryException;
use App\Inbox\Repository\MemberRepository;
use App\Staff\Entity\StaffMember;
use App\Staff\Service\StaffSession;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * Whose inbox a request reads (#19): the signed-in member, as the user id `aic_notifications` keeps for them, their
 * `users.id` as text, which run 18's lead notices store.
 */
final readonly class InboxOwners
{
    /**
     * @param MemberRepository $memberRepository Finds the member's id.
     * @param StaffSession     $staffSession     Who is signed in.
     */
    public function __construct(
        private MemberRepository $memberRepository,
        private StaffSession $staffSession = new StaffSession(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request, with its session.
     *
     * @throws QueryException           When the users cannot be read.
     * @throws LogicException           When the request carries no session, or the users' type is malformed.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return string The signed-in member's user id; empty for a visitor, or a member no longer in `users`.
     */
    public function userId(ServerRequestInterface $serverRequest): string
    {
        $staffMember = $this->staffSession->member($serverRequest);

        if (!$staffMember instanceof StaffMember) {
            return '';
        }

        $id = $this->memberRepository->memberId($staffMember->email);

        return $id === 0
            ? ''
            : (string) $id;
    }
}
