<?php

declare(strict_types=1);

namespace App\Chat\Service;

use AIC\Auth\Application\Authenticator;
use AIC\Database\Contracts\QueryException;
use AIC\Session\Application\RequestSession;
use App\Chat\Entity\Chat;
use App\Chat\Entity\ChatViewer;
use App\Chat\Repository\ChatRepository;
use App\Staff\Entity\StaffMember;
use App\Staff\Service\StaffRequests;
use App\Staff\Service\StaffSession;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function ctype_digit;
use function strlen;

/**
 * Who looks at the staff's chats, and which chat a staff route names (#32): the viewer from the session, as the inbox
 * reads its owner, and the chat by the route's `id` when the viewer may see it. A manager sees every chat, a
 * salesperson the chats that went to them.
 */
final readonly class ChatAccess
{
    /**
     * @param StaffSession   $staffSession   The signed-in member's name and role.
     * @param ChatRepository $chatRepository The chats.
     * @param StaffRequests  $staffRequests  Reads the route's `id`.
     * @param RequestSession $requestSession Reads the session off the request.
     */
    public function __construct(
        private StaffSession $staffSession,
        private ChatRepository $chatRepository,
        private StaffRequests $staffRequests = new StaffRequests(),
        private RequestSession $requestSession = new RequestSession(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request, with its session.
     *
     * @throws LogicException           When the request carries no session.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return ChatViewer The signed-in member; user 0 for no one.
     */
    public function viewer(ServerRequestInterface $serverRequest): ChatViewer
    {
        $userId = $this->requestSession->read($serverRequest)->get(Authenticator::USER) ?? '';
        $staffMember = $this->staffSession->member($serverRequest);

        if (!$staffMember instanceof StaffMember || $userId === '' || strlen($userId) > 18 || !ctype_digit($userId)) {
            return new ChatViewer(0, false);
        }

        return new ChatViewer((int) $userId, $staffMember->role->manages());
    }

    /**
     * @param ServerRequestInterface $serverRequest A request routed with an `id`.
     *
     * @throws QueryException           When the chats cannot be read.
     * @throws InvalidArgumentException When a type is malformed.
     *
     * @return Chat|null The chat the route names; null when there is none.
     */
    public function routed(ServerRequestInterface $serverRequest): ?Chat
    {
        $id = $this->staffRequests->attribute($serverRequest, 'id');

        return $id !== '' && strlen($id) <= 18 && ctype_digit($id)
            ? $this->chatRepository->find((int) $id)
            : null;
    }
}
