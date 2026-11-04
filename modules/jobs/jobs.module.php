<?php

declare(strict_types=1);

/*
 * The jobs module's manifest (#35): /app/jobs, AIC's queue for an admin. It
 * lists what waits, runs or retries and what failed, newest first, through
 * AIC's QueueRecords (AIC #262), and refreshes the lists every five seconds.
 * A failed job's Retry now and Discard post to the two routes under
 * /app/jobs/failed/, which call PostgresQueue::retry() and discard(). The
 * module writes no SQL and adds no command: bin/aic queue:retry replays a
 * failed job from the console. Staff's protected prefix keeps the routes to
 * signed-in staff, and the module lets in an admin alone. The build reads it;
 * nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Jobs\Controller\FailedDiscardPost;
use App\Jobs\Controller\FailedRetryPost;
use App\Jobs\Controller\JobsPage;

return (new ModuleManifest('jobs'))
    ->requires('staff')
    ->route(RouteMethod::Get, '/app/jobs', JobsPage::class)
    ->route(RouteMethod::Post, '/app/jobs/failed/{id:int}/retry', FailedRetryPost::class)
    ->route(RouteMethod::Post, '/app/jobs/failed/{id:int}/discard', FailedDiscardPost::class);
