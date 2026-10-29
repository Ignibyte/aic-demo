<?php

declare(strict_types=1);

namespace App\Settings\Form;

/**
 * A member form's values as typed (#22): the name, the email (the add form's alone) and the role's stored value. Nothing
 * in it is checked yet.
 */
final readonly class MemberEntry
{
    /**
     * @param string $name  The name.
     * @param string $email The email; empty on the edit form, which does not change it.
     * @param string $role  The role, as `users.role` stores it.
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $role,
    ) {
    }
}
