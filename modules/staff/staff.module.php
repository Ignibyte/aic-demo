<?php

declare(strict_types=1);

/*
 * The staff module's manifest (#3): Ridgeway Motors' staff sign in on AIC's
 * Auth, and everything under /app is theirs alone. The users table and its
 * repository, the sign-in middleware over the protected prefixes, the sign-in
 * and sign-out pages, the header's account menu, and the user:add and
 * staff:seed commands; /app itself is the dashboard module's page (#8). It
 * exports the staff pages' document and the signed-in member (#4), for the
 * pages and the navigation other modules add, and a record page's panel, its
 * one-time notice and the envelope sealing its path (#6), which inventory and
 * customers share, and the `users` entity type, which sales references and
 * reads (#14); and the member and their role, by which photos decides who may
 * change a vehicle's photos (#16). For the settings (#22) it deactivates a member
 * (`AddUserDeactivation`), holds every signed-in session to the users table
 * before the sign-in guard runs (`StaffStanding`), removes a member who made no
 * records (`user:remove`), and exports the members' guarded reads and writes
 * (`AccountRepository`, `StaffAccount`, `AccountWrite`) and the roles in order
 * (`Roles`). The build reads it; nothing reads it at run time.
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
use App\Staff\Command\UserRemove;
use App\Staff\Controller\SignInPage;
use App\Staff\Controller\SignInPost;
use App\Staff\Controller\SignOutPost;
use App\Staff\Entity\AccountWrite;
use App\Staff\Entity\Role;
use App\Staff\Entity\StaffAccount;
use App\Staff\Entity\StaffMember;
use App\Staff\Entity\StaffTable;
use App\Staff\Form\StaffSeals;
use App\Staff\Middleware\StaffStanding;
use App\Staff\Migration\AddUserDeactivation;
use App\Staff\Migration\CreateUsersTable;
use App\Staff\Repository\AccountRepository;
use App\Staff\Repository\StaffRepository;
use App\Staff\Service\AccessRules;
use App\Staff\Service\Roles;
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
    ->binding(UserProvider::class, StaffRepository::class)
    ->binding(PasswordHasher::class, PgcryptoPasswordHasher::class)
    ->middleware(StaffStanding::class, MiddlewarePosition::AfterSession)
    ->middleware(RequireAuthenticationMiddleware::class, MiddlewarePosition::AfterSession)
    ->protectedPrefix('/app')
    ->factory(AccessRules::class)
    ->factory(StaffSettings::class)
    ->block(AccountMenuBlock::class)
    ->command('user:add', UserAdd::class)
    ->command('staff:seed', StaffSeed::class)
    ->command('user:remove', UserRemove::class)
    ->migration(CreateUsersTable::class)
    ->migration(AddUserDeactivation::class)
    ->export(StaffPages::class)
    ->export(StaffSession::class)
    ->export(StaffPanels::class)
    ->export(StaffNotices::class)
    ->export(StaffSeals::class)
    ->export(StaffRequests::class)
    ->export(StaffTable::class)
    ->export(StaffMember::class)
    ->export(Role::class)
    ->export(Roles::class)
    ->export(AccountRepository::class)
    ->export(StaffRepository::class)
    ->export(StaffAccount::class)
    ->export(AccountWrite::class);
