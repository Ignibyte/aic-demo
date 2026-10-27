<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Link;
use AIC\Controller\Domain\PageDocument;
use App\Staff\Service\StaffPages;
use App\Theme\Components\Notice\Notice;
use App\Theme\Components\Notice\NoticeTone;
use App\Theme\Components\Panel\Panel;
use InvalidArgumentException;

/**
 * The vehicle pages' frame: one wide panel in the staff pages' document, and the page a refusal shows in it, a
 * warning and a link on.
 */
final readonly class VehiclePanels
{
    /**
     * @param StaffPages $staffPages The staff pages' document.
     */
    public function __construct(
        private StaffPages $staffPages,
    ) {
    }

    /**
     * @param string                   $title       The top bar's title.
     * @param string                   $heading     The panel's heading.
     * @param string                   $description The line under it.
     * @param array<string, Component> $body        What the panel shows, in order, by its key.
     * @param int                      $status      The page's status.
     *
     * @throws InvalidArgumentException When a key or the status does not fit the theme.
     *
     * @return PageDocument The page.
     */
    public function page(string $title, string $heading, string $description, array $body, int $status): PageDocument
    {
        $keyed = [];

        foreach ($body as $key => $component) {
            $keyed[] = new KeyedComponent($key, $component);
        }

        $panel = new Panel('vehicle', $heading, $description, true, $keyed);

        return $this->staffPages->page($title, [new KeyedComponent('vehicle', $panel)], $status);
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
