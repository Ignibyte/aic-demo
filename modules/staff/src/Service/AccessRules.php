<?php

declare(strict_types=1);

namespace App\Staff\Service;

use AIC\Auth\Domain\RouteAuthConfig;
use InvalidArgumentException;

/**
 * The sign-in rules, a factory the manifest lists: every module's protected path prefixes (`/app` is this module's),
 * and the sign-in page an anonymous visitor to one is sent to.
 */
final readonly class AccessRules
{
    /**
     * Where an anonymous visitor to a protected path signs in.
     */
    private const string SIGN_IN_PATH = '/sign-in';

    /**
     * @param list<string> $protectedPrefixes Every module's protected path prefixes.
     */
    public function __construct(
        private array $protectedPrefixes,
    ) {
    }

    /**
     * @throws InvalidArgumentException When the sign-in path is not a path on this site.
     *
     * @return RouteAuthConfig The paths the sign-in middleware guards, and where it sends a visitor.
     */
    public function routeAuthConfig(): RouteAuthConfig
    {
        return new RouteAuthConfig($this->protectedPrefixes, self::SIGN_IN_PATH);
    }
}
