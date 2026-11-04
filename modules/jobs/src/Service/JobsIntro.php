<?php

declare(strict_types=1);

namespace App\Jobs\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Paragraph;
use App\Staff\Service\StaffNotices;
use App\Theme\Components\Panel\Panel;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The jobs page's intro panel (#35), outside the live part, so a refresh never removes what it holds: the notice a
 * Retry now or a Discard kept, if any, then a line naming the console that works the queue. The notice is taken only
 * for a whole page; a refresh of the live part alone gets the panel without it, so the notice waits for the next page.
 */
final readonly class JobsIntro
{
    /**
     * What the console line says.
     */
    private const string CONSOLE = 'Workers take these jobs with bin/aic queue:work, and bin/aic schedule:run queues '
        . 'the scheduled ones, such as the nightly digest, each minute. A job that cannot run now retries after a delay; '
        . 'after its last attempt it moves to Failed with the reason. From the console, bin/aic queue:failed lists the '
        . 'failed jobs and bin/aic queue:retry with a number queues one again.';

    /**
     * The line under the panel's heading.
     */
    private const string LINE = 'Background work, such as mail, newest first. The lists refresh every five seconds.';

    /**
     * @param StaffNotices $staffNotices The notice a retry or a discard kept.
     */
    public function __construct(
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The jobs page's request, with its session.
     *
     * @throws LogicException When the request carries no session, or a key does not fit the theme.
     *
     * @return KeyedComponent The panel, keyed `intro`: the notice, `#jobs-notice`, then the console line,
     *                        `#jobs-console`.
     */
    public function panel(ServerRequestInterface $serverRequest): KeyedComponent
    {
        $body = [];

        foreach ($this->staffNotices->take($serverRequest, 'jobs-notice') as $key => $notice) {
            $body[] = new KeyedComponent($key, $notice);
        }

        return self::intro($body);
    }

    /**
     * For a refresh of the live part alone, whose answer sends the part and nothing of the intro: the notice stays
     * kept.
     *
     * @throws LogicException When a key does not fit the theme.
     *
     * @return KeyedComponent The panel, keyed `intro`, with the console line alone.
     */
    public function withoutNotice(): KeyedComponent
    {
        return self::intro([]);
    }

    /**
     * @param list<KeyedComponent> $body What comes before the console line.
     *
     * @throws LogicException When a key does not fit the theme.
     *
     * @return KeyedComponent The panel, keyed `intro`, that body then the console line, `#jobs-console`.
     */
    private static function intro(array $body): KeyedComponent
    {
        $body[] = new KeyedComponent('console', new Paragraph(self::CONSOLE, 'jobs-console'));

        return new KeyedComponent('intro', new Panel('jobs', 'The queue', self::LINE, true, $body));
    }
}
