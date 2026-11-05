<?php

declare(strict_types=1);

/*
 * The Coverage page's rows (#38): one per surface `bin/aic code:coverage` lists, in its order, as data in code. A shown
 * row names the page where Lotline's use of the surface can be seen and the kept browser test that holds it; a reasoned
 * row says why Lotline does not use it. `code:coverage` leaves this file out of what it reads for uses (AIC #272, #273), so
 * a surface named here is no use of it. `CoverageCatalog`, the site module's service, requires this file; the page's
 * browser test, `tests/browser/coverage.spec.ts`, holds these rows to `bin/aic code:coverage --json`.
 */
use AIC\Auth\Contracts\PasswordHasher;
use AIC\Auth\Contracts\UserProvider;
use AIC\Block\Contracts\Block;
use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\ComponentSource;
use AIC\Component\Contracts\Frame;
use AIC\Component\Contracts\Keyed;
use AIC\Component\Contracts\Layout;
use AIC\Component\Contracts\LayoutName;
use AIC\Component\Contracts\PageLayouts;
use AIC\Component\Contracts\RenderObserver;
use AIC\Component\Domain\Alert;
use AIC\Component\Domain\Badge;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Checkbox;
use AIC\Component\Domain\CodeBlock;
use AIC\Component\Domain\FileInput;
use AIC\Component\Domain\FilterForm;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\GuardFields;
use AIC\Component\Domain\Heading;
use AIC\Component\Domain\HiddenInput;
use AIC\Component\Domain\Input;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Link;
use AIC\Component\Domain\Paragraph;
use AIC\Component\Domain\PasswordInput;
use AIC\Component\Domain\RawHtml;
use AIC\Component\Domain\SourcedComponent;
use AIC\Component\Domain\Stack;
use AIC\Component\Domain\Table;
use AIC\Component\Domain\Tabs;
use AIC\Component\Domain\Textarea;
use AIC\Component\Domain\ThemeLayout;
use AIC\Component\Domain\ThemePage;
use AIC\Component\Domain\Timeline;
use AIC\Component\Domain\TrackedAssets;
use AIC\Container\Contracts\ServiceProvider;
use AIC\Container\Contracts\ServiceRegistry;
use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Contracts\CommandWriter;
use AIC\Controller\Contracts\EventSource;
use AIC\Controller\Contracts\EventWriter;
use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobRecorder;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Contracts\ResponseWriter;
use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Controller\Domain\EventStream;
use AIC\Controller\Domain\FileDownload;
use AIC\Controller\Domain\JobDone;
use AIC\Controller\Domain\JobFailed;
use AIC\Controller\Domain\JobRetry;
use AIC\Controller\Domain\JsonResponse;
use AIC\Controller\Domain\PageDocument;
use AIC\Controller\Domain\PageFragments;
use AIC\Controller\Domain\PageFullVisit;
use AIC\Controller\Domain\PageRedirect;
use AIC\Controller\Domain\PageUnchanged;
use AIC\Controller\Domain\XmlResponse;
use AIC\Database\Contracts\Database;
use AIC\Entity\Application\EntityQuery;
use AIC\Http\Contracts\PublicAssets;
use AIC\Kernel\Contracts\RequestObserver;
use AIC\Module\Contracts\ModuleDeclaration;
use AIC\Module\Contracts\ModuleManifest;
use AIC\Notification\Contracts\Channel;
use AIC\Notification\Contracts\MailTransport;
use AIC\Routing\Contracts\RouteObserver;
use AIC\Sapi\Contracts\RequestExtractor;
use AIC\Sapi\Contracts\SapiSink;
use AIC\Schema\Contracts\ColumnTypes;
use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use AIC\Schema\Contracts\TableAlteration;
use AIC\Schema\Contracts\TableBuilder;
use AIC\Session\Contracts\Session;
use AIC\Session\Contracts\SessionStore;
use App\Site\Entity\CoverageRow;
use App\Site\Entity\CoverageRows;

$frameworkOnly = 'Only AIC implements it: ';
$generated = 'The build generates what implements it: ';
$observer = "AIC's profiler implements it (AIC_PROFILER=1); Lotline needs no observer of its own.";
$addedColumns = 'Lotline added a string, an integer and a date column to tables it had made; no later column needed this type.';
$script = "Lotline's one script, helpers.page.js, starts Tessera with the money helper and nothing more (#26).";

return new CoverageRows([
    CoverageRow::shown('contracts', PasswordHasher::class, '/sign-in', 'sign-in-comes-back.spec.ts'),
    CoverageRow::shown('contracts', UserProvider::class, '/sign-in', 'sign-in-comes-back.spec.ts'),
    CoverageRow::shown('contracts', Block::class, '/app', 'inbox-bell-live.spec.ts'),
    CoverageRow::shown('contracts', Component::class, '/theme', 'tessera-markers.spec.ts'),
    CoverageRow::shown('contracts', ComponentSource::class, '/app/customers', 'customers-search.spec.ts'),
    CoverageRow::reasoned(
        'contracts',
        Frame::class,
        $frameworkOnly . "the render pass's region, markers and fragments.",
    ),
    CoverageRow::reasoned(
        'contracts',
        Keyed::class,
        "AIC's KeyedComponent implements it; Lotline keys its parts with that atom.",
    ),
    CoverageRow::reasoned(
        'contracts',
        Layout::class,
        $generated . "the theme's page adapter, which PageDocuments builds.",
    ),
    CoverageRow::reasoned(
        'contracts',
        LayoutName::class,
        $generated . 'the ThemeLayoutName enum, whose cases Lotline names.',
    ),
    CoverageRow::reasoned(
        'contracts',
        PageLayouts::class,
        $generated . "the theme's document factory, which PageDocuments calls.",
    ),
    CoverageRow::reasoned('contracts', RenderObserver::class, $observer),
    CoverageRow::reasoned('contracts', ServiceProvider::class, $generated . "each module's provider, from its plugins."),
    CoverageRow::reasoned(
        'contracts',
        ServiceRegistry::class,
        $frameworkOnly . 'the container the generated providers fill.',
    ),
    CoverageRow::shown('contracts', Api::class, '/api/vehicles', 'storefront-feed.spec.ts'),
    CoverageRow::shown('contracts', ApiResponse::class, '/api/vehicles', 'storefront-feed.spec.ts'),
    CoverageRow::shown('contracts', Command::class, '/sign-in', 'sign-in-comes-back.spec.ts'),
    CoverageRow::shown('contracts', CommandResponse::class, '/sign-in', 'sign-in-comes-back.spec.ts'),
    CoverageRow::reasoned(
        'contracts',
        CommandWriter::class,
        $frameworkOnly . "the console's writer, over its output stream.",
    ),
    CoverageRow::shown('contracts', EventSource::class, '/app/chats', 'chat-live.spec.ts'),
    CoverageRow::reasoned(
        'contracts',
        EventWriter::class,
        $frameworkOnly . "the response writer, for a stream's events.",
    ),
    CoverageRow::shown('contracts', Job::class, '/app/jobs', 'jobs-page.spec.ts'),
    CoverageRow::reasoned(
        'contracts',
        JobRecorder::class,
        $frameworkOnly . 'the queue worker, which records each result.',
    ),
    CoverageRow::shown('contracts', JobResponse::class, '/app/jobs', 'jobs-page.spec.ts'),
    CoverageRow::shown('contracts', Page::class, '/app/inventory', 'inventory-filter.spec.ts'),
    CoverageRow::shown('contracts', PageResponse::class, '/app/inventory', 'inventory-filter.spec.ts'),
    CoverageRow::reasoned('contracts', ResponseWriter::class, $frameworkOnly . 'the PSR-7 response writer.'),
    CoverageRow::shown('contracts', Stream::class, '/app/inbox', 'inbox-bell-live.spec.ts'),
    CoverageRow::shown('contracts', StreamResponse::class, '/app/inbox', 'inbox-bell-live.spec.ts'),
    CoverageRow::reasoned(
        'contracts',
        Database::class,
        'gate:9 keeps modules off it; Lotline reads and writes through entity queries.',
    ),
    CoverageRow::reasoned(
        'contracts',
        PublicAssets::class,
        $generated . "the asset lookup AIC's layouts and asset handler read.",
    ),
    CoverageRow::reasoned('contracts', RequestObserver::class, $observer),
    CoverageRow::reasoned(
        'contracts',
        ModuleDeclaration::class,
        $frameworkOnly . 'the build reads it from what a manifest declares.',
    ),
    CoverageRow::reasoned(
        'contracts',
        Channel::class,
        "AIC's mail and database channels deliver Lotline's notifications.",
    ),
    CoverageRow::reasoned('contracts', MailTransport::class, "AIC's SMTP transport sends Lotline's mail."),
    CoverageRow::reasoned('contracts', RouteObserver::class, $observer),
    CoverageRow::reasoned('contracts', RequestExtractor::class, $frameworkOnly . 'the SAPI adapter.'),
    CoverageRow::reasoned('contracts', SapiSink::class, $frameworkOnly . "the worker, over the SAPI's output calls."),
    CoverageRow::shown('contracts', ColumnTypes::class, '/app/leads', 'leads-board.spec.ts'),
    CoverageRow::shown('contracts', Migration::class, '/app/inventory', 'inventory-filter.spec.ts'),
    CoverageRow::shown('contracts', Schema::class, '/app/inventory', 'inventory-filter.spec.ts'),
    CoverageRow::shown('contracts', TableAlteration::class, '/app/leads', 'leads-board.spec.ts'),
    CoverageRow::shown('contracts', TableBuilder::class, '/app/inventory', 'inventory-filter.spec.ts'),
    CoverageRow::shown('contracts', Session::class, '/chat', 'chat-start.spec.ts'),
    CoverageRow::reasoned('contracts', SessionStore::class, "AIC's Postgres session store keeps Lotline's sessions."),

    CoverageRow::shown('responses', CommandResult::class, '/sign-in', 'sign-in-comes-back.spec.ts'),
    CoverageRow::shown('responses', EventStream::class, '/app/inbox', 'inbox-bell-live.spec.ts'),
    CoverageRow::shown('responses', FileDownload::class, '/cars', 'brochure.spec.ts'),
    CoverageRow::shown('responses', JobDone::class, '/app/jobs', 'jobs-page.spec.ts'),
    CoverageRow::shown('responses', JobFailed::class, '/app/jobs', 'jobs-page.spec.ts'),
    CoverageRow::reasoned(
        'responses',
        JobRetry::class,
        "AIC's DeliverNotification answers it when the mail host refuses, as /app/jobs shows (#35); Lotline's own jobs "
        . 'fail only on a payload that names nothing.',
    ),
    CoverageRow::shown('responses', JsonResponse::class, '/api/vehicles', 'storefront-feed.spec.ts'),
    CoverageRow::shown('responses', PageDocument::class, '/app/inventory', 'inventory-filter.spec.ts'),
    CoverageRow::reasoned(
        'responses',
        PageFragments::class,
        'PageDocument makes it for a Tessera visit or refresh; no page builds it.',
    ),
    CoverageRow::shown('responses', PageFullVisit::class, '/theme/resilience/full-visit', 'resilience-full-visit.spec.ts'),
    CoverageRow::shown('responses', PageRedirect::class, '/app/customers/new', 'form-saved-visit.spec.ts'),
    CoverageRow::shown('responses', PageUnchanged::class, '/app', 'dashboard-poll-idle.spec.ts'),
    CoverageRow::shown('responses', XmlResponse::class, '/feeds/vehicles.xml', 'vehicle-feed-xml.spec.ts'),

    CoverageRow::shown('manifest', ModuleManifest::class . '::binding', '/sign-in', 'sign-in-comes-back.spec.ts'),
    CoverageRow::shown('manifest', ModuleManifest::class . '::block', '/app', 'inbox-bell-live.spec.ts'),
    CoverageRow::shown('manifest', ModuleManifest::class . '::command', '/sign-in', 'sign-in-comes-back.spec.ts'),
    CoverageRow::shown('manifest', ModuleManifest::class . '::export', '/cars', 'storefront-browse.spec.ts'),
    CoverageRow::shown('manifest', ModuleManifest::class . '::factory', '/app/settings', 'settings-new-member.spec.ts'),
    CoverageRow::shown('manifest', ModuleManifest::class . '::job', '/app/jobs', 'jobs-page.spec.ts'),
    CoverageRow::shown(
        'manifest',
        ModuleManifest::class . '::middleware',
        '/theme/resilience/gateway',
        'resilience-gateway.spec.ts',
    ),
    CoverageRow::shown('manifest', ModuleManifest::class . '::migration', '/app/inventory', 'inventory-filter.spec.ts'),
    CoverageRow::shown('manifest', ModuleManifest::class . '::plugin', '/app/inventory', 'vehicle-panels-lazy.spec.ts'),
    CoverageRow::shown('manifest', ModuleManifest::class . '::protectedPrefix', '/app', 'sign-out-locks-app.spec.ts'),
    CoverageRow::shown('manifest', ModuleManifest::class . '::requires', '/cars', 'storefront-browse.spec.ts'),
    CoverageRow::shown('manifest', ModuleManifest::class . '::route', '/app/inventory', 'inventory-filter.spec.ts'),
    CoverageRow::shown('manifest', ModuleManifest::class . '::schedule', '/app/holds', 'holds-release.spec.ts'),

    CoverageRow::reasoned('schema', ColumnTypes::class . '::bigInteger', $addedColumns),
    CoverageRow::reasoned('schema', ColumnTypes::class . '::boolean', $addedColumns),
    CoverageRow::shown('schema', ColumnTypes::class . '::date', '/app/settings', 'settings-deactivate.spec.ts'),
    CoverageRow::reasoned('schema', ColumnTypes::class . '::identity', $addedColumns),
    CoverageRow::shown('schema', ColumnTypes::class . '::integer', '/app/settings', 'finance-calculator-staff.spec.ts'),
    CoverageRow::reasoned('schema', ColumnTypes::class . '::numeric', $addedColumns),
    CoverageRow::shown('schema', ColumnTypes::class . '::string', '/app/leads', 'leads-board.spec.ts'),
    CoverageRow::reasoned('schema', ColumnTypes::class . '::text', $addedColumns),
    CoverageRow::reasoned('schema', ColumnTypes::class . '::timestampTz', $addedColumns),
    CoverageRow::shown('schema', Migration::class . '::describe', '/app/inventory', 'inventory-filter.spec.ts'),
    CoverageRow::shown('schema', Migration::class . '::version', '/app/inventory', 'inventory-filter.spec.ts'),
    CoverageRow::shown('schema', Schema::class . '::alter', '/app/leads', 'leads-board.spec.ts'),
    CoverageRow::shown('schema', Schema::class . '::create', '/app/inventory', 'inventory-filter.spec.ts'),
    CoverageRow::reasoned(
        'schema',
        Schema::class . '::extension',
        'No Lotline table needs a Postgres extension; search is a bound ILIKE.',
    ),
    CoverageRow::shown('schema', Schema::class . '::types', '/app/leads', 'leads-board.spec.ts'),
    CoverageRow::shown('schema', Schema::class . '::view', '/app', 'dashboard-tiles.spec.ts'),
    CoverageRow::shown('schema', TableAlteration::class . '::addColumn', '/app/settings', 'settings-deactivate.spec.ts'),
    CoverageRow::shown('schema', TableAlteration::class . '::changeType', '/app/leads', 'leads-board.spec.ts'),
    CoverageRow::shown('schema', TableAlteration::class . '::index', '/app/leads', 'leads-board.spec.ts'),
    CoverageRow::shown('schema', TableAlteration::class . '::nullable', '/app/settings', 'settings-deactivate.spec.ts'),
    CoverageRow::reasoned('schema', TableAlteration::class . '::unique', 'Each unique key Lotline has was made with its table.'),
    CoverageRow::shown('schema', TableBuilder::class . '::bigInteger', '/app/chats', 'chat-live.spec.ts'),
    CoverageRow::shown('schema', TableBuilder::class . '::boolean', '/app/settings', 'settings-link-once.spec.ts'),
    CoverageRow::shown('schema', TableBuilder::class . '::date', '/app/inventory', 'vehicle-price-changes.spec.ts'),
    CoverageRow::shown('schema', TableBuilder::class . '::foreignKey', '/app/chats', 'chat-live.spec.ts'),
    CoverageRow::shown('schema', TableBuilder::class . '::identity', '/app/customers', 'customer-add.spec.ts'),
    CoverageRow::shown('schema', TableBuilder::class . '::index', '/app/chats', 'chat-live.spec.ts'),
    CoverageRow::shown('schema', TableBuilder::class . '::integer', '/app/inventory', 'inventory-sort.spec.ts'),
    CoverageRow::shown('schema', TableBuilder::class . '::nullable', '/app/chats', 'chat-live.spec.ts'),
    CoverageRow::reasoned(
        'schema',
        TableBuilder::class . '::numeric',
        'Prices are whole dollars and the APR whole basis points, in integer columns; nothing needs a decimal.',
    ),
    CoverageRow::shown('schema', TableBuilder::class . '::primary', '/app/customers', 'customer-add.spec.ts'),
    CoverageRow::shown('schema', TableBuilder::class . '::string', '/app/customers', 'customer-add.spec.ts'),
    CoverageRow::shown('schema', TableBuilder::class . '::text', '/app/chats', 'chat-live.spec.ts'),
    CoverageRow::shown('schema', TableBuilder::class . '::timestampTz', '/app/chats', 'chat-live.spec.ts'),
    CoverageRow::shown('schema', TableBuilder::class . '::unique', '/app/customers', 'customer-duplicate-email.spec.ts'),

    CoverageRow::shown('query', EntityQuery::class . '::changes', '/app/chats', 'chat-live.spec.ts'),
    CoverageRow::shown('query', EntityQuery::class . '::first', '/app/customers', 'customer-edit.spec.ts'),
    CoverageRow::shown('query', EntityQuery::class . '::forUpdate', '/app/chats', 'chat-live.spec.ts'),
    CoverageRow::shown('query', EntityQuery::class . '::limit', '/app', 'dashboard-tiles.spec.ts'),
    CoverageRow::shown('query', EntityQuery::class . '::offset', '/app/customers', 'customers-search.spec.ts'),
    CoverageRow::shown('query', EntityQuery::class . '::one', '/app/settings', 'settings-profile.spec.ts'),
    CoverageRow::shown('query', EntityQuery::class . '::orderBy', '/app/inventory', 'inventory-sort.spec.ts'),
    CoverageRow::shown('query', EntityQuery::class . '::orderByDescending', '/app', 'dashboard-tiles.spec.ts'),
    CoverageRow::shown('query', EntityQuery::class . '::rows', '/app/inventory', 'inventory-filter.spec.ts'),
    CoverageRow::shown('query', EntityQuery::class . '::search', '/app/customers', 'customers-search.spec.ts'),
    CoverageRow::shown('query', EntityQuery::class . '::totals', '/app/chats', 'chat-live.spec.ts'),
    CoverageRow::shown('query', EntityQuery::class . '::where', '/app/inventory', 'inventory-filter.spec.ts'),
    CoverageRow::shown('query', EntityQuery::class . '::whereAny', '/app/sales/new', 'sale-record.spec.ts'),
    CoverageRow::shown('query', EntityQuery::class . '::whereIn', '/app/inventory', 'bulk-edit-status.spec.ts'),
    CoverageRow::shown('query', EntityQuery::class . '::with', '/app/chats', 'chat-live.spec.ts'),

    CoverageRow::shown('atoms', Alert::class, '/app/customers/new', 'form-refused-in-place.spec.ts'),
    CoverageRow::reasoned(
        'atoms',
        Badge::class,
        "The theme's StatusBadge and BellBadge draw Lotline's badges in daisyUI's tones.",
    ),
    CoverageRow::shown('atoms', Button::class, '/app/customers', 'customer-delete.spec.ts'),
    CoverageRow::reasoned(
        'atoms',
        Checkbox::class,
        "The theme's CheckGroup and the tables' row boxes draw Lotline's checkboxes.",
    ),
    CoverageRow::shown('atoms', CodeBlock::class, '/theme/coverage', 'coverage.spec.ts'),
    CoverageRow::shown('atoms', FileInput::class, '/app/inventory', 'vehicle-photo-add.spec.ts'),
    CoverageRow::reasoned(
        'atoms',
        FilterForm::class,
        "The theme's FilterBar draws Lotline's filters: search, status and sort in one bar.",
    ),
    CoverageRow::shown('atoms', Form::class, '/app/customers/new', 'customer-add.spec.ts'),
    CoverageRow::shown('atoms', FormField::class, '/app/inventory', 'vehicle-photo-add.spec.ts'),
    CoverageRow::shown('atoms', GuardFields::class, '/app/customers/new', 'form-one-write.spec.ts'),
    CoverageRow::reasoned('atoms', Heading::class, "The theme's TopBar, Panel and StoreHeading carry Lotline's headings."),
    CoverageRow::shown('atoms', HiddenInput::class, '/app/reports', 'reports-export.spec.ts'),
    CoverageRow::reasoned(
        'atoms',
        Input::class,
        "The theme's FormField and SearchField draw Lotline's inputs, with their errors.",
    ),
    CoverageRow::shown('atoms', KeyedComponent::class, '/theme', 'tessera-markers.spec.ts'),
    CoverageRow::shown('atoms', Link::class, '/app/customers', 'customers-search.spec.ts'),
    CoverageRow::shown('atoms', Paragraph::class, '/app/jobs', 'jobs-page.spec.ts'),
    CoverageRow::reasoned(
        'atoms',
        PasswordInput::class,
        "The theme's FormField draws the password fields, with their errors.",
    ),
    CoverageRow::reasoned('atoms', RawHtml::class, 'Nothing in Lotline is trusted HTML; config/raw-html.php grants none.'),
    CoverageRow::shown('atoms', SourcedComponent::class, '/app', 'dashboard-tiles.spec.ts'),
    CoverageRow::reasoned(
        'atoms',
        Stack::class,
        "The theme's PanelGrid and its components' own layouts stack Lotline's parts.",
    ),
    CoverageRow::shown('atoms', Table::class, '/cars', 'finance-calculator-store.spec.ts'),
    CoverageRow::shown('atoms', Tabs::class, '/app/leads', 'leads-staff.spec.ts'),
    CoverageRow::reasoned('atoms', Textarea::class, "The theme's FormField draws Lotline's text boxes, with their errors."),
    CoverageRow::shown('atoms', ThemeLayout::class, '/', 'navigation-walk.spec.ts'),
    CoverageRow::reasoned('atoms', ThemePage::class, $generated . "the theme's page adapter, from a page's regions."),
    CoverageRow::reasoned(
        'atoms',
        Timeline::class,
        'No Lotline page draws spans over time; aged stock and price history are tables.',
    ),
    CoverageRow::reasoned(
        'atoms',
        TrackedAssets::class,
        "AIC's ThemeLayout carries it, and the theme's HTML templates place it.",
    ),

    CoverageRow::shown('tessera', 'data-region', '/theme', 'tessera-markers.spec.ts'),
    CoverageRow::shown('tessera', 'data-t-c', '/theme', 'tessera-markers.spec.ts'),
    CoverageRow::shown('tessera', 'data-t-props', '/app/inventory', 'inline-edit-vehicle.spec.ts'),
    CoverageRow::shown('tessera', 'data-t-anchor', '/app', 'dashboard-live-in-grid.spec.ts'),
    CoverageRow::shown('tessera', 'data-target', '/app/inventory', 'inventory-table-swap.spec.ts'),
    CoverageRow::shown('tessera', 'data-push', '/app/inventory', 'inventory-table-history.spec.ts'),
    CoverageRow::shown('tessera', 'data-enhance', '/app/inventory', 'inventory-sort.spec.ts'),
    CoverageRow::shown('tessera', 'data-track', '/', 'page-script.spec.ts'),
    CoverageRow::shown('tessera', 'autofocus', '/app/chats', 'chat-reply-focus.spec.ts'),
    CoverageRow::shown('tessera', 'data-focus-key', '/app/customers', 'search-as-you-type-customers.spec.ts'),
    CoverageRow::shown('tessera', 'data-error-summary', '/app/chats', 'chat-refused.spec.ts'),
    CoverageRow::shown('tessera', 'aria-invalid', '/app/customers/new', 'form-refused-in-place.spec.ts'),
    CoverageRow::shown('tessera', 'data-permanent', '/cars', 'chat-permanent.spec.ts'),
    CoverageRow::shown('tessera', 'data-lazy', '/app/inventory', 'vehicle-panels-lazy.spec.ts'),
    CoverageRow::shown('tessera', 'data-poll', '/app', 'dashboard-poll.spec.ts'),
    CoverageRow::shown('tessera', 'data-prefetch', '/app/inventory', 'inventory-prefetch.spec.ts'),
    CoverageRow::shown('tessera', 'data-stream', '/app/inbox', 'inbox-bell-live.spec.ts'),
    CoverageRow::shown('tessera', 'data-signals', '/app/inventory', 'bulk-edit-status.spec.ts'),
    CoverageRow::shown('tessera', 'data-computed', '/cars', 'finance-calculator-store.spec.ts'),
    CoverageRow::shown('tessera', 'data-bind', '/app/inventory', 'bulk-edit-status.spec.ts'),
    CoverageRow::shown('tessera', 'data-text', '/app/inventory', 'bulk-edit-status.spec.ts'),
    CoverageRow::shown('tessera', 'data-show', '/cars', 'compare-tick.spec.ts'),
    CoverageRow::shown('tessera', 'data-class', '/app/chats', 'chat-live.spec.ts'),
    CoverageRow::shown('tessera', 'data-attr', '/app/inventory', 'bulk-edit-status.spec.ts'),
    CoverageRow::reasoned('tessera', 'data-style', 'Nothing in Lotline sets a style property from a signal; its states are classes.'),
    CoverageRow::shown('tessera', 'data-on', '/app/leads', 'leads-board-drag.spec.ts'),
    CoverageRow::shown('tessera', 'data-init', '/app/inventory', 'inline-edit-vehicle.spec.ts'),
    CoverageRow::shown('tessera', 'data-effect', '/app/inventory', 'bulk-edit-status.spec.ts'),
    CoverageRow::shown('tessera', 'data-loading', '/app/inventory', 'bulk-edit-status.spec.ts'),
    CoverageRow::reasoned(
        'tessera',
        'data-ignore',
        "No element in Lotline belongs to another script that Tessera's directives must leave alone.",
    ),
    CoverageRow::shown('tessera', 'data-ignore-morph', '/app/chats', 'chat-live.spec.ts'),
    CoverageRow::reasoned('tessera', 'data-ref', 'No expression in Lotline needs another element by name; each acts on its own (el).'),
    CoverageRow::shown('tessera', '@get', '/app/inventory', 'inline-edit-vehicle.spec.ts'),
    CoverageRow::shown('tessera', '@post', '/app/inventory', 'bulk-edit-status.spec.ts'),
    CoverageRow::shown('tessera', '@put', '/app/inventory', 'inline-edit-vehicle.spec.ts'),
    CoverageRow::shown('tessera', '@patch', '/app/leads', 'leads-board-drag.spec.ts'),
    CoverageRow::reasoned(
        'tessera',
        '@delete',
        'Every delete in Lotline is a form POST, so it works with JavaScript off; a form cannot send DELETE.',
    ),
    CoverageRow::shown('tessera', 'tessera-layout', '/', 'navigation-walk.spec.ts'),
    CoverageRow::shown('tessera', 'tessera-csrf-token', '/app/customers/new', 'form-one-write.spec.ts'),
    CoverageRow::shown('tessera', 'tessera-csrf-header', '/app/customers/new', 'form-one-write.spec.ts'),
    CoverageRow::shown('tessera', 'tessera-start', '/cars', 'finance-calculator-store.spec.ts'),
    CoverageRow::shown('tessera', 'tessera-debug', '/', 'profiler-walk.spec.ts'),
    CoverageRow::shown('tessera', 'tessera-patch-elements', '/app/chats', 'chat-live.spec.ts'),
    CoverageRow::shown('tessera', 'tessera-patch-signals', '/app/chats', 'chat-live.spec.ts'),
    CoverageRow::shown('tessera', 'data-scope', '/app/inventory', 'bulk-edit-signal-patch.spec.ts'),
    CoverageRow::shown('tessera', 'data-only-if-missing', '/app/inventory', 'bulk-edit-signal-patch.spec.ts'),
    CoverageRow::shown('tessera', 'tessera-close', '/app/chats', 'chat-end.spec.ts'),
    CoverageRow::shown('tessera', 'tessera:error', '/theme/resilience/feed', 'resilience-feed.spec.ts'),
    CoverageRow::shown('tessera', 'tessera:expired', '/theme/resilience/expired', 'resilience-expired.spec.ts'),
    CoverageRow::shown('tessera', 'tessera:indeterminate', '/theme/resilience/gateway', 'resilience-gateway.spec.ts'),
    CoverageRow::reasoned('tessera', 'tessera:exchange', "AIC's profiler toolbar reads it; Lotline watches no exchange itself."),
    CoverageRow::shown('tessera', 'tessera:patched', '/app/inventory', 'bulk-edit-status.spec.ts'),
    CoverageRow::shown('tessera', 'start', '/cars', 'finance-calculator-store.spec.ts'),
    CoverageRow::reasoned('tessera', 'signals', $script),
    CoverageRow::reasoned('tessera', 'visit', $script),
    CoverageRow::reasoned('tessera', 'intercept', $script),
    CoverageRow::reasoned('tessera', 'stop', $script),
]);
