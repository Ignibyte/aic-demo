<?php

declare(strict_types=1);

namespace App\Settings\Service;

use AIC\Component\Domain\KeyedComponent;
use App\Staff\Entity\StaffAccount;
use App\Staff\Entity\StaffMember;
use App\Staff\Repository\AccountRepository;
use App\Staff\Service\StaffSession;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\StatusBadge\BadgeTone;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function count;
use function number_format;

/**
 * The settings page's staff table (#22): the signed-in admin first, so they find themselves, then every other member by
 * name; each with their name linking to their page, their email, their role and whether they are active or since when
 * they are deactivated.
 */
final readonly class MemberTable
{
    /**
     * Where a member's page sits, under which their id follows.
     */
    public const string PAGES = '/app/settings/staff/';

    /**
     * @param AccountRepository $accountRepository The members.
     * @param StaffSession      $staffSession      Who is signed in, whose row comes first.
     */
    public function __construct(
        private AccountRepository $accountRepository,
        private StaffSession $staffSession = new StaffSession(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The page's request, with its session.
     *
     * @throws RuntimeException When the members cannot be read, or a stored role is no role.
     * @throws LogicException   When the request has no session, or a key is malformed.
     *
     * @return DataTable The table.
     */
    public function table(ServerRequestInterface $serverRequest): DataTable
    {
        $staffMember = $this->staffSession->member($serverRequest);
        $email = $staffMember instanceof StaffMember
            ? $staffMember->email
            : '';
        $rows = [];

        // Keyed by place, not by id: a member's id differs between gate:23's two passes, which each add one.
        foreach ($this->ordered($email) as $index => $tableRow) {
            $rows[] = new KeyedComponent('member-' . ($index + 1), $tableRow);
        }

        return new DataTable('members-table', $this->summary(count($rows)), 'No members yet.', 'content', $this->columns(), $rows);
    }

    /**
     * @param string $email The signed-in admin's login.
     *
     * @throws RuntimeException         When the members cannot be read, or a stored role is no role.
     * @throws InvalidArgumentException When the users' type is malformed.
     *
     * @return list<TableRow> Every member's row: the signed-in admin's first, then the others by name.
     */
    private function ordered(string $email): array
    {
        $first = [];
        $rest = [];

        foreach ($this->accountRepository->all() as $staffAccount) {
            if ($staffAccount->email === $email) {
                $first[] = $this->row($staffAccount, true);

                continue;
            }

            $rest[] = $this->row($staffAccount, false);
        }

        foreach ($rest as $tableRow) {
            $first[] = $tableRow;
        }

        return $first;
    }

    /**
     * @param int $count How many members there are.
     *
     * @return string What the table shows.
     */
    private function summary(int $count): string
    {
        return number_format($count) . ($count === 1 ? ' member' : ' members') . ', you first, then by name';
    }

    /**
     * @return list<TableColumn> The column heads; none sorts.
     */
    private function columns(): array
    {
        return [
            new TableColumn('Name', '', 'none', '', false),
            new TableColumn('Email', '', 'none', '', false),
            new TableColumn('Role', '', 'none', '', false),
            new TableColumn('Status', '', 'none', '', false),
        ];
    }

    /**
     * @param StaffAccount $staffAccount A member.
     * @param bool         $you          Whether they are the signed-in admin.
     *
     * @return TableRow Their row.
     */
    private function row(StaffAccount $staffAccount, bool $you): TableRow
    {
        $name = $you
            ? $staffAccount->name . ' (you)'
            : $staffAccount->name;
        $status = $staffAccount->active()
            ? new TableCell('Active', '', false, false, BadgeTone::Success)
            : new TableCell('Deactivated on ' . $staffAccount->deactivatedOn, '', false, false, BadgeTone::Neutral);

        return new TableRow([
            new TableCell($name, self::PAGES . $staffAccount->id, false, false, null),
            new TableCell($staffAccount->email, '', true, false, null),
            new TableCell($staffAccount->role->label(), '', false, false, null),
            $status,
        ]);
    }
}
