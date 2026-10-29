<?php

declare(strict_types=1);

/*
 * The holds module's manifest (#20): a vehicle in stock kept for a customer
 * for 48 hours, off the public site and sold to no one else. It owns the
 * holds table and its hold_states view, the staff pages under /app/holds,
 * which staff's protected prefix keeps to signed-in staff, and the
 * release-holds job, which its schedule queues every 15 minutes (UTC) to
 * release the holds whose time has passed. It exports HoldRepository, which
 * sales asks whether a vehicle is held for another customer. It requires
 * staff for who is signed in and the users, inventory for the vehicles and
 * their status, and customers. The build reads it; nothing reads it at run
 * time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Holds\Controller\HoldCreatePost;
use App\Holds\Controller\HoldListPage;
use App\Holds\Controller\HoldNewPage;
use App\Holds\Job\ReleaseHolds;
use App\Holds\Migration\CreateHoldsTable;
use App\Holds\Migration\CreateHoldsView;
use App\Holds\Repository\HoldRepository;
use App\Holds\Service\HoldServices;

return (new ModuleManifest('holds'))
    ->requires('staff')
    ->requires('inventory')
    ->requires('customers')
    ->route(RouteMethod::Get, '/app/holds/new', HoldNewPage::class)
    ->route(RouteMethod::Post, '/app/holds', HoldCreatePost::class)
    ->route(RouteMethod::Get, '/app/holds', HoldListPage::class)
    ->factory(HoldServices::class)
    ->job('release-holds', ReleaseHolds::class)
    ->schedule('release-holds', 'release-holds', [], '*/15 * * * *')
    ->migration(CreateHoldsTable::class)
    ->migration(CreateHoldsView::class)
    ->export(HoldRepository::class);
