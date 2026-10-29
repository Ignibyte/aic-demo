<?php

declare(strict_types=1);

namespace App\Holds\Form;

use AIC\Form\Application\PostedFields;
use App\Staff\Entity\StaffMember;
use App\Staff\Service\StaffRequests;
use App\Staff\Service\StaffSession;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function strtoupper;
use function trim;

/**
 * Reads the hold form's requests (#20): the VIN `?vehicle=` names for the form's start, a posted form's fields as sent
 * (a missing one or an array is empty), and the login of the member of staff signed in, who places the hold.
 */
final readonly class HoldEntries
{
    /**
     * @param PostedFields  $postedFields  Reads the posted fields.
     * @param StaffSession  $staffSession  Who is signed in.
     * @param StaffRequests $staffRequests Reads `?vehicle=` as text.
     */
    public function __construct(
        private PostedFields $postedFields = new PostedFields(),
        private StaffSession $staffSession = new StaffSession(),
        private StaffRequests $staffRequests = new StaffRequests(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for the form.
     *
     * @return string The VIN `?vehicle=` names, in capitals; empty for none.
     */
    public function chosenVin(ServerRequestInterface $serverRequest): string
    {
        return strtoupper(trim($this->staffRequests->query($serverRequest, 'vehicle')));
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted hold form.
     *
     * @return HoldEntry What it sent.
     */
    public function posted(ServerRequestInterface $serverRequest): HoldEntry
    {
        $values = [];

        foreach (HoldEntry::FIELDS as $field) {
            $values[$field] = $this->postedFields->value($serverRequest, $field);
        }

        return new HoldEntry($values);
    }

    /**
     * @param ServerRequestInterface $serverRequest A request with its session.
     *
     * @throws LogicException           When the request carries no session.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return string The signed-in member's login; empty when no one is signed in.
     */
    public function login(ServerRequestInterface $serverRequest): string
    {
        $staffMember = $this->staffSession->member($serverRequest);

        return $staffMember instanceof StaffMember
            ? $staffMember->email
            : '';
    }
}
