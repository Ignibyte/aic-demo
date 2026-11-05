<?php

declare(strict_types=1);

/*
 * The settings module's manifest (#22): the admins' settings at /app/settings.
 * The dealership's profile (its name, address, phone, opening hours, the
 * aged-stock threshold and the default APR, #26), one row saved by upsert, which storefront, leads and
 * reports read through the exported `DealershipProfiles`; the staff, added,
 * edited, deactivated and reactivated through staff's exported
 * `AccountRepository`; and the password links a new or reset member is
 * mailed, which land on /password/{link}. It requires staff and finance: the
 * users table and its rules are staff's, and it names the dealership on the
 * staff pages through staff's `DealershipNames` plugin type (#23); the same
 * class gives the vehicle page's payment calculator its APR through finance's
 * `FinanceRates` plugin type (#26), and finance writes the APR's standard. It
 * exports `SettingsConfig`, the site's address, which its factory reads from
 * config/settings.php and storefront's XML feed (#37) writes absolute URLs
 * with. The build reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Finance\Service\FinanceRates;
use App\Settings\Controller\MemberActivePost;
use App\Settings\Controller\MemberAddPost;
use App\Settings\Controller\MemberLinkPost;
use App\Settings\Controller\MemberPage;
use App\Settings\Controller\MemberPost;
use App\Settings\Controller\PasswordPage;
use App\Settings\Controller\PasswordPost;
use App\Settings\Controller\ProfilePost;
use App\Settings\Controller\SettingsPage;
use App\Settings\Entity\DealershipProfile;
use App\Settings\Entity\OpeningHours;
use App\Settings\Entity\SettingsConfig;
use App\Settings\Migration\AddDefaultApr;
use App\Settings\Migration\CreateDealershipProfileTable;
use App\Settings\Migration\CreatePasswordLinksTable;
use App\Settings\Migration\CreatePasswordLinkStatesView;
use App\Settings\Repository\DealershipNameRepository;
use App\Settings\Service\DealershipProfiles;
use App\Settings\Service\MemberServices;
use App\Settings\Service\SettingsServices;
use App\Staff\Service\DealershipNames;

return (new ModuleManifest('settings'))
    ->requires('staff')
    ->requires('finance')
    ->route(RouteMethod::Get, '/app/settings', SettingsPage::class)
    ->route(RouteMethod::Post, '/app/settings/profile', ProfilePost::class)
    ->route(RouteMethod::Post, '/app/settings/staff', MemberAddPost::class)
    ->route(RouteMethod::Get, '/app/settings/staff/{id:int}', MemberPage::class)
    ->route(RouteMethod::Post, '/app/settings/staff/{id:int}', MemberPost::class)
    ->route(RouteMethod::Post, '/app/settings/staff/{id:int}/active', MemberActivePost::class)
    ->route(RouteMethod::Post, '/app/settings/staff/{id:int}/link', MemberLinkPost::class)
    ->route(RouteMethod::Get, '/password/{link}', PasswordPage::class)
    ->route(RouteMethod::Post, '/password/{link}', PasswordPost::class)
    ->plugin(DealershipNames::class, DealershipNameRepository::class)
    ->plugin(FinanceRates::class, DealershipNameRepository::class)
    ->factory(SettingsServices::class)
    ->factory(MemberServices::class)
    ->migration(CreateDealershipProfileTable::class)
    ->migration(CreatePasswordLinksTable::class)
    ->migration(CreatePasswordLinkStatesView::class)
    ->migration(AddDefaultApr::class)
    ->export(DealershipProfiles::class)
    ->export(DealershipProfile::class)
    ->export(OpeningHours::class)
    ->export(SettingsConfig::class);
