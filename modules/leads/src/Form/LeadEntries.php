<?php

declare(strict_types=1);

namespace App\Leads\Form;

use AIC\Form\Application\PostedFields;
use App\Leads\Entity\LeadDetails;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Fills a lead form's entry (#18): from what was posted, or from a lead that passed its checks, so the thank-you shows
 * the values as they were stored, trimmed and the email lower case.
 */
final readonly class LeadEntries
{
    /**
     * @param PostedFields $postedFields Reads a posted field.
     */
    public function __construct(
        private PostedFields $postedFields = new PostedFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted lead form.
     *
     * @return LeadEntry Each field as posted; empty when it is missing or not text.
     */
    public function posted(ServerRequestInterface $serverRequest): LeadEntry
    {
        return new LeadEntry(
            $this->postedFields->value($serverRequest, 'name'),
            $this->postedFields->value($serverRequest, 'email'),
            $this->postedFields->value($serverRequest, 'phone'),
            $this->postedFields->value($serverRequest, 'message'),
            $this->postedFields->value($serverRequest, 'drive_on'),
            $this->postedFields->value($serverRequest, 'drive_at'),
        );
    }

    /**
     * @param LeadDetails $leadDetails A lead that passed its checks.
     *
     * @return LeadEntry Its values.
     */
    public function sent(LeadDetails $leadDetails): LeadEntry
    {
        return new LeadEntry(
            $leadDetails->name,
            $leadDetails->email,
            $leadDetails->phone,
            $leadDetails->message,
            $leadDetails->driveOn,
            $leadDetails->driveAt,
        );
    }
}
