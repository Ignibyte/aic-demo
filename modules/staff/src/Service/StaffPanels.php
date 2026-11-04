<?php

declare(strict_types=1);

namespace App\Staff\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Link;
use AIC\Controller\Domain\PageDocument;
use App\Theme\Components\Notice\Notice;
use App\Theme\Components\Notice\NoticeTone;
use App\Theme\Components\Panel\Panel;
use InvalidArgumentException;

/**
 * A record's pages' frame: one wide panel in the staff pages' document, and the page a refusal shows in it, a warning
 * and a link on. Each module builds its own with its panel's id stem (`vehicle`, `customer`).
 */
final readonly class StaffPanels
{
    /**
     * @param StaffPages $staffPages The staff pages' document.
     * @param string     $id         The panel's id stem and key.
     */
    public function __construct(
        private StaffPages $staffPages,
        private string $id,
    ) {
    }

    /**
     * @param string                   $title       The top bar's title.
     * @param string                   $heading     The panel's heading.
     * @param string                   $description The line under it.
     * @param array<string, Component> $body        What the panel shows, in order, by its key.
     * @param int                      $status      The page's status.
     * @param list<KeyedComponent>     $parts       What it shows after that, already keyed, each keeping the props and
     *                                              anchor it carries, such as a vehicle's panels (#31); empty for none.
     *
     * @throws InvalidArgumentException When a key or the status does not fit the theme.
     *
     * @return PageDocument The page.
     */
    public function page(
        string $title,
        string $heading,
        string $description,
        array $body,
        int $status,
        array $parts = [],
    ): PageDocument {
        $keyed = [];

        foreach ($body as $key => $component) {
            $keyed[] = new KeyedComponent($key, $component);
        }

        foreach ($parts as $part) {
            $keyed[] = $part;
        }

        $panel = new Panel($this->id, $heading, $description, true, $keyed);

        return $this->staffPages->page($title, [new KeyedComponent($this->id, $panel)], $status);
    }

    /**
     * @param string $title    The top bar's title and the panel's heading.
     * @param string $message  What went wrong, or why nothing can be done.
     * @param string $id       The notice's id.
     * @param string $back     The link's text.
     * @param string $backHref Where it goes.
     * @param int    $status   The page's status.
     *
     * @throws InvalidArgumentException When the status does not fit the theme.
     *
     * @return PageDocument The page: the message as a warning, and the link.
     */
    public function warning(
        string $title,
        string $message,
        string $id,
        string $back,
        string $backHref,
        int $status,
    ): PageDocument {
        $notice = new Notice($message, NoticeTone::Warning, $id);

        return $this->page($title, $title, 'Nothing was changed.', ['notice' => $notice, 'back' => new Link($back, $backHref)], $status);
    }
}
