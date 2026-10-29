<?php

declare(strict_types=1);

/*
 * The inbox module's manifest (#19): the signed-in member's notifications,
 * which AIC keeps in `aic_notifications` and reads through its `Inbox`
 * (AIC #202). A bell in the top bar of every staff page, kept live by a
 * Server-Sent Events stream; `/app/inbox`, every notification paged; opening
 * one, which marks it read; and marking them all read. It owns no table. The
 * build reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Inbox\Block\BellBlock;
use App\Inbox\Controller\BellEvents;
use App\Inbox\Controller\InboxPage;
use App\Inbox\Controller\InboxReadAllPost;
use App\Inbox\Controller\NotificationPage;
use App\Inbox\Service\InboxServices;

return (new ModuleManifest('inbox'))
    ->requires('staff')
    ->route(RouteMethod::Get, '/app/inbox', InboxPage::class)
    ->route(RouteMethod::Get, '/app/inbox/events', BellEvents::class)
    ->route(RouteMethod::Get, '/app/inbox/{id:int}', NotificationPage::class)
    ->route(RouteMethod::Post, '/app/inbox/read-all', InboxReadAllPost::class)
    ->factory(InboxServices::class)
    ->block(BellBlock::class);
