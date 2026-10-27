<?php

declare(strict_types=1);

namespace App\Staff\Service;

use AIC\Session\Application\RequestSession;
use App\Theme\Components\Notice\Notice;
use App\Theme\Components\Notice\NoticeTone;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The one line a save or a delete leaves for the page it redirects to, kept in the session and cleared when that
 * page shows it as a success notice. One key serves every staff page, so each page a save or a delete redirects to
 * takes it before anything else, a 404 included, and a notice never waits for some later page.
 */
final readonly class StaffNotices
{
    /**
     * The session key that keeps it.
     */
    private const string KEY = 'staff.notice';

    /**
     * @param RequestSession $requestSession Reads the session off the request.
     */
    public function __construct(
        private RequestSession $requestSession = new RequestSession(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The post that saved or deleted, with its session.
     * @param string                 $message       What to tell the next page.
     *
     * @throws LogicException When the request carries no session.
     */
    public function keep(ServerRequestInterface $serverRequest, string $message): void
    {
        $this->requestSession->read($serverRequest)->set(self::KEY, $message);
    }

    /**
     * @param ServerRequestInterface $serverRequest A page's request, with its session.
     * @param string                 $id            The notice's id.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return array<string, Notice> The kept line as a success notice under the key `notice`, and cleared; empty when
     *                               none is kept.
     */
    public function take(ServerRequestInterface $serverRequest, string $id): array
    {
        $session = $this->requestSession->read($serverRequest);
        $message = $session->get(self::KEY) ?? '';

        if ($message === '') {
            return [];
        }

        $session->set(self::KEY, '');

        return ['notice' => new Notice($message, NoticeTone::Success, $id)];
    }
}
