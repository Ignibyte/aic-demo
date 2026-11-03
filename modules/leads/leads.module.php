<?php

declare(strict_types=1);

/*
 * The leads module's manifest (#18): what a visitor asks about a car on its
 * public page, a question or a test drive, and how the salesperson hears of it.
 * It owns the leads table, the lead-notices job that tells the salesperson
 * and the visitor through AIC's notifications, and the staff pages under
 * /app/leads, which staff's protected prefix keeps to signed-in staff. It
 * exports LeadDesk, which storefront's car pages hand their lead forms to, with
 * the kind and the reply its methods take and give. It requires staff for who
 * is signed in and the users, inventory for the vehicles and the database's
 * today, customers to link a lead by email, and settings for the dealership's
 * opening hours and name (#22). The board (#30) sits on the list's route, with
 * `view=board`: a card moves by PATCH, or by POST without script, its stage's
 * changes are `lead_moves`, which the board's stream sends to whoever watches,
 * and a move to Won queues the lead-won job. The build reads it; nothing reads
 * it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Leads\Controller\LeadAssignPost;
use App\Leads\Controller\LeadBoardEvents;
use App\Leads\Controller\LeadClosePost;
use App\Leads\Controller\LeadListPage;
use App\Leads\Controller\LeadPage;
use App\Leads\Controller\LeadStagePatch;
use App\Leads\Controller\LeadStagePost;
use App\Leads\Entity\LeadKind;
use App\Leads\Entity\LeadReply;
use App\Leads\Job\SendLeadNotices;
use App\Leads\Job\SendLeadWon;
use App\Leads\Migration\AddLeadStage;
use App\Leads\Migration\CreateLeadMovesTable;
use App\Leads\Migration\CreateLeadsTable;
use App\Leads\Service\LeadDesk;
use App\Leads\Service\LeadServices;

return (new ModuleManifest('leads'))
    ->requires('staff')
    ->requires('inventory')
    ->requires('customers')
    ->requires('settings')
    ->route(RouteMethod::Get, '/app/leads/{id:int}', LeadPage::class)
    ->route(RouteMethod::Post, '/app/leads/{id:int}/assign', LeadAssignPost::class)
    ->route(RouteMethod::Post, '/app/leads/{id:int}/close', LeadClosePost::class)
    ->route(RouteMethod::Patch, '/app/leads/{id:int}/stage', LeadStagePatch::class)
    ->route(RouteMethod::Post, '/app/leads/{id:int}/stage', LeadStagePost::class)
    ->route(RouteMethod::Get, '/app/leads/events', LeadBoardEvents::class)
    ->route(RouteMethod::Get, '/app/leads', LeadListPage::class)
    ->factory(LeadServices::class)
    ->job('lead-notices', SendLeadNotices::class)
    ->job('lead-won', SendLeadWon::class)
    ->migration(CreateLeadsTable::class)
    ->migration(AddLeadStage::class)
    ->migration(CreateLeadMovesTable::class)
    ->export(LeadDesk::class)
    ->export(LeadKind::class)
    ->export(LeadReply::class);
