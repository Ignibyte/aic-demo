<?php

declare(strict_types=1);

namespace App\Leads\Entity;

/**
 * Who is looking at the leads (#30): the signed-in member's user id, email and name, and whether they manage (an admin
 * or a sales manager). It holds the one rule for who may move a lead, its salesperson or a manager, which the lead's
 * page, the board, its stream and the stage routes all ask.
 */
final readonly class BoardViewer
{
    /**
     * @param int    $userId  The member's user id, which their moves record; 0 when no one is signed in.
     * @param string $email   The member's email; empty when no one is signed in.
     * @param string $name    Their name, which a move's notice names them by.
     * @param bool   $manages Whether they are an admin or a sales manager.
     */
    public function __construct(
        public int $userId,
        public string $email,
        public string $name,
        public bool $manages,
    ) {
    }

    /**
     * @param Lead $lead A lead.
     *
     * @return bool Whether they may move it: a manager, or the salesperson it is assigned to.
     */
    public function mayMove(Lead $lead): bool
    {
        if ($this->email === '') {
            return false;
        }

        if ($this->manages) {
            return true;
        }

        $seller = $lead->seller;

        return $seller instanceof Seller && $seller->email === $this->email;
    }
}
