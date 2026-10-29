<?php

declare(strict_types=1);

namespace App\Settings\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Settings\Service\MemberEdits;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * POST /app/settings/staff/{id} (#22): an admin saves a member's name and role, when the stored ones are still those the
 * form showed and an active admin remains; 303 to the member, 422 for a refused field or the last active admin, 409
 * for a stale copy, 400 for an envelope that is not this member's form's, 404 and 403.
 */
final readonly class MemberPost implements Page
{
    /**
     * @param MemberEdits $memberEdits Saves a member's edit form.
     */
    public function __construct(
        private MemberEdits $memberEdits,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted edit form, with its session.
     *
     * @throws Throwable When the records cannot be read or written, or the page cannot be built.
     *
     * @return PageResponse 303 to the member; their page as 422 or 409; 400; 404; 403 for anyone but an admin.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->memberEdits->update($serverRequest);
    }
}
