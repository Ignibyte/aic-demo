<?php

declare(strict_types=1);

namespace App\Inbox\Command;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use AIC\Notification\Application\Inbox;
use App\Inbox\Repository\MemberRepository;
use InvalidArgumentException;
use Override;

use function count;

/**
 * `bin/aic inbox:forget {login}`: deletes every kept notification of the member of staff who signs in with that login
 * (#21), through AIC's `Inbox::forget()` (AIC #210). gate:23 runs it for the gate user before each pass, so each pass
 * starts that inbox empty and the notification an export leaves stays within its own pass. Exit 1 for no such member,
 * 2 with its usage for anything but one argument.
 */
final readonly class InboxForget implements Command
{
    /**
     * @param MemberRepository $memberRepository Finds the member's id.
     * @param Inbox            $inbox            The kept notifications.
     */
    public function __construct(
        private MemberRepository $memberRepository,
        private Inbox $inbox,
    ) {
    }

    /**
     * @param CommandInput $commandInput The login.
     *
     * @throws QueryException           When the users cannot be read or the notifications deleted.
     * @throws InvalidArgumentException When the users' type is malformed, or the result's exit code is invalid.
     *
     * @return CommandResponse How many were deleted, or why nothing was.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $login = $commandInput->argument(0, '');

        if (count($commandInput->arguments()) !== 1 || $login === '') {
            return new CommandResult(['usage: bin/aic inbox:forget {login}'], 2);
        }

        $id = $this->memberRepository->memberId($login);

        if ($id === 0) {
            return new CommandResult(['inbox:forget found no member of staff who signs in as ' . $login . '.'], 1);
        }

        $forgotten = $this->inbox->forget((string) $id);

        return new CommandResult(['inbox:forget deleted ' . $forgotten . ' notification(s) of ' . $login . '.'], 0);
    }
}
