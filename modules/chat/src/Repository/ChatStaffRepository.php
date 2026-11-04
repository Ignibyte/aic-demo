<?php

declare(strict_types=1);

namespace App\Chat\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use App\Chat\Entity\ChatSummary;
use App\Chat\Entity\ChatTable;
use App\Chat\Entity\ChatViewer;
use App\Staff\Entity\Role;
use App\Staff\Entity\StaffTable;
use InvalidArgumentException;

use function implode;
use function substr;

/**
 * The chats as the staff see them (#32): the list a member sees and its version, read from the `chat_list` view, and
 * the sales manager a chat on a car with no lead goes to.
 */
final readonly class ChatStaffRepository
{
    /**
     * @param EntityStore $entityStore The entity store.
     * @param ChatTable   $chatTable   The chats' types.
     * @param StaffTable  $staffTable  The users' type, for the sales manager.
     */
    public function __construct(
        private EntityStore $entityStore,
        private ChatTable $chatTable = new ChatTable(),
        private StaffTable $staffTable = new StaffTable(),
    ) {
    }

    /**
     * @param ChatViewer $chatViewer Who looks.
     * @param int        $limit      How many to read, at least 1.
     *
     * @throws QueryException           When the list cannot be read.
     * @throws InvalidArgumentException When a type is malformed or the limit is below 1.
     *
     * @return list<ChatSummary> The chats they see, open first, then by their newest message, newest first.
     */
    public function listed(ChatViewer $chatViewer, int $limit): array
    {
        $entityQuery = $this->entityStore->query($this->chatTable->listing());

        if (!$chatViewer->manages) {
            $entityQuery = $entityQuery->where('staff_id', $chatViewer->userId);
        }

        $summaries = [];
        $rows = $entityQuery->orderByDescending('status')->orderByDescending('latest')->orderByDescending('id')->limit($limit)->rows();

        foreach ($rows as $row) {
            $summaries[] = new ChatSummary(
                $row->int('id'),
                $row->string('car_title'),
                $row->string('visitor_name'),
                $row->string('staff_name'),
                $row->string('status') === 'open',
                self::minute($row->stringOrNull('latest') ?? ''),
                $row->int('unread'),
                $row->int('last_seq'),
                $row->int('staff_seen'),
            );
        }

        return $summaries;
    }

    /**
     * @param list<ChatSummary> $summaries The rows a list shows.
     *
     * @return string What the list is now: each row's version, joined by `,`.
     */
    public function version(array $summaries): string
    {
        $versions = [];

        foreach ($summaries as $summary) {
            $versions[] = $summary->version();
        }

        return $versions === []
            ? 'none'
            : implode(',', $versions);
    }

    /**
     * @throws QueryException           When the users cannot be read.
     * @throws InvalidArgumentException When the users' type is malformed.
     *
     * @return int The active sales manager with the lowest id; 0 for none.
     */
    public function salesManager(): int
    {
        $row = $this->entityStore->query($this->staffTable->type())
            ->where('role', Role::SalesManager->value)
            ->where('deactivated_on', null)
            ->orderBy('id')
            ->limit(1)
            ->first();

        return $row instanceof Row
            ? $row->int('id')
            : 0;
    }

    /**
     * @param string $stamp A timestamp as Postgres writes it; empty for none.
     *
     * @return string It to the minute, `YYYY-MM-DD HH:MM UTC`; empty for none.
     */
    private static function minute(string $stamp): string
    {
        return $stamp === ''
            ? ''
            : substr($stamp, 0, 16) . ' UTC';
    }
}
