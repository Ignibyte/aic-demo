<?php

declare(strict_types=1);

namespace App\Theme\Components\SignedInMenu;

use AIC\Component\Contracts\Component;

/**
 * The signed-in staff member's menu, from signed-in-menu.phtml: a daisyUI dropdown with their name, their role and
 * email, and the sign-out form.
 */
final readonly class SignedInMenu implements Component
{
    /**
     * @param string    $name    Their name.
     * @param string    $role    Their role, as staff read it.
     * @param string    $email   Their email.
     * @param Component $signOut The sign-out form.
     */
    public function __construct(
        public string $name,
        public string $role,
        public string $email,
        public Component $signOut,
    ) {
    }
}
