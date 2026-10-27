<?php

declare(strict_types=1);

/*
 * The staff module's manifest (#3): Ridgeway Motors' staff sign in on AIC's
 * Auth, and everything under /app is theirs alone. The users table and its
 * repository, the sign-in middleware over the protected prefixes, the sign-in
 * and sign-out pages, the first page under /app, the header's account menu,
 * and the user:add and staff:seed commands. It exports the staff pages'
 * document and the signed-in member (#4), for the pages and the navigation
 * other modules add, and a record page's panel, its one-time notice and the
 * envelope sealing its path (#6), which inventory and customers share. The
 * build reads it; nothing reads it at run time.
 */

use AIC\Auth\Application\PgcryptoPasswordHasher;
use AIC\Auth\Application\RequireAuthenticationMiddleware;
use AIC\Auth\Contracts\PasswordHasher;
use AIC\Auth\Contracts\UserProvider;
use AIC\Module\Contracts\MiddlewarePosition;
use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Staff\Block\AccountMenuBlock;
use App\Staff\Command\StaffSeed;
use App\Staff\Command\UserAdd;
use App\Staff\Controller\SignInPage;
use App\Staff\Controller\SignInPost;
use App\Staff\Controller\SignOutPost;
use App\Staff\Controller\StaffHomePage;
use App\Staff\Form\StaffSeals;
use App\Staff\Migration\CreateUsersTable;
use App\Staff\Repository\StaffRepository;
use App\Staff\Service\AccessRules;
use App\Staff\Service\StaffNotices;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffPanels;
use App\Staff\Service\StaffRequests;
use App\Staff\Service\StaffSession;
use App\Staff\Service\StaffSettings;

return (new ModuleManifest('staff'))
    ->route(RouteMethod::Get, '/sign-in', SignInPage::class)
    ->route(RouteMethod::Post, '/sign-in', SignInPost::class)
    ->route(RouteMethod::Post, '/sign-out', SignOutPost::class)
    ->route(RouteMethod::Get, '/app', StaffHomePage::class)
    ->binding(UserProvider::class, StaffRepository::class)
    ->binding(PasswordHasher::class, PgcryptoPasswordHasher::class)
    ->middleware(RequireAuthenticationMiddleware::class, MiddlewarePosition::AfterSession)
    ->protectedPrefix('/app')
    ->factory(AccessRules::class)
    ->factory(StaffSettings::class)
    ->block(AccountMenuBlock::class)
    ->command('user:add', UserAdd::class)
    ->command('staff:seed', StaffSeed::class)
    ->migration(CreateUsersTable::class)
    ->export(StaffPages::class)
    ->export(StaffSession::class)
    ->export(StaffPanels::class)
    ->export(StaffNotices::class)
    ->export(StaffSeals::class)
    ->export(StaffRequests::class);
