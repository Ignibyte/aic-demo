<?php

declare(strict_types=1);

/*
 * The customers module's manifest (#6): the people Ridgeway Motors serves.
 * The customers table, its seed, the searched and paged list, and each
 * customer's page, form and delete, found by its id, all under /app, which the
 * staff module's protected prefix keeps to signed-in staff. It exports the
 * plugin type a customer's pages ask what the customer bought (#7), and the
 * seed's customers for the sales seed, and the customers' entity type, which
 * sales references and the dashboard counts (#14). The list's route comes last,
 * so the generated handler parts do not each open with the record services
 * (gate:14). A list row is edited in place (#28): its row shown, its row as a
 * form, and its save, a PUT (AIC #242), after the list's route, so their
 * generated part builds the rows' services alone (gate:14). The build reads it;
 * nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Customers\Command\CustomersSeed;
use App\Customers\Controller\CustomerCreatePost;
use App\Customers\Controller\CustomerDeletePage;
use App\Customers\Controller\CustomerDeletePost;
use App\Customers\Controller\CustomerEditPage;
use App\Customers\Controller\CustomerListPage;
use App\Customers\Controller\CustomerNewPage;
use App\Customers\Controller\CustomerPage;
use App\Customers\Controller\CustomerRowEditPage;
use App\Customers\Controller\CustomerRowPage;
use App\Customers\Controller\CustomerRowPut;
use App\Customers\Controller\CustomerUpdatePost;
use App\Customers\Entity\CustomerDetails;
use App\Customers\Entity\CustomerTable;
use App\Customers\Entity\Purchase;
use App\Customers\Migration\CreateCustomersTable;
use App\Customers\Service\CustomerPurchases;
use App\Customers\Service\CustomerServices;
use App\Customers\Service\SeedCustomers;

return (new ModuleManifest('customers'))
    ->requires('staff')
    ->route(RouteMethod::Get, '/app/customers/new', CustomerNewPage::class)
    ->route(RouteMethod::Post, '/app/customers', CustomerCreatePost::class)
    ->route(RouteMethod::Get, '/app/customers/{id:int}', CustomerPage::class)
    ->route(RouteMethod::Get, '/app/customers/{id:int}/edit', CustomerEditPage::class)
    ->route(RouteMethod::Post, '/app/customers/{id:int}', CustomerUpdatePost::class)
    ->route(RouteMethod::Get, '/app/customers/{id:int}/delete', CustomerDeletePage::class)
    ->route(RouteMethod::Post, '/app/customers/{id:int}/delete', CustomerDeletePost::class)
    ->route(RouteMethod::Get, '/app/customers', CustomerListPage::class)
    ->route(RouteMethod::Get, '/app/customers/{id:int}/row', CustomerRowPage::class)
    ->route(RouteMethod::Get, '/app/customers/{id:int}/row/edit', CustomerRowEditPage::class)
    ->route(RouteMethod::Put, '/app/customers/{id:int}/row', CustomerRowPut::class)
    ->factory(CustomerServices::class)
    ->command('customers:seed', CustomersSeed::class)
    ->migration(CreateCustomersTable::class)
    ->export(CustomerPurchases::class)
    ->export(Purchase::class)
    ->export(SeedCustomers::class)
    ->export(CustomerDetails::class)
    ->export(CustomerTable::class);
