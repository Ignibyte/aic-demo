<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;
use App\Site\Form\ThemeEntry;
use App\Theme\Components\Notice\Notice;
use App\Theme\Components\Notice\NoticeTone;
use App\Theme\Components\Panel\Panel;
use App\Theme\Components\PanelGrid\PanelGrid;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The style guide's content: a line saying what it is, then every part of Lotline's theme with sample data in a
 * dashboard grid, from the figures and charts down to the forms, the notices, the account menu and the icons.
 */
final readonly class ThemeSamples
{
    /**
     * The line above the guide.
     */
    private const string INTRO = "Every part of Lotline's theme, with sample Ridgeway Motors data.";

    /**
     * @param ThemeSampleCharts  $themeSampleCharts  The figures and charts.
     * @param ThemeSampleRecords $themeSampleRecords The list.
     * @param ThemeSampleForms   $themeSampleForms   The vehicle form.
     * @param ThemeSampleActions $themeSampleActions The delete and sign-out forms.
     * @param ThemeSampleParts   $themeSampleParts   The card, badges, notices and icons.
     */
    public function __construct(
        private ThemeSampleCharts $themeSampleCharts = new ThemeSampleCharts(),
        private ThemeSampleRecords $themeSampleRecords = new ThemeSampleRecords(),
        private ThemeSampleForms $themeSampleForms = new ThemeSampleForms(),
        private ThemeSampleActions $themeSampleActions = new ThemeSampleActions(),
        private ThemeSampleParts $themeSampleParts = new ThemeSampleParts(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request: the list's query and the visitor's token.
     * @param ThemeEntry             $themeEntry    The vehicle form's values; empty on a first visit.
     * @param array<string, string>  $problems      What is wrong with them, by field name.
     *
     * @throws LogicException When a key is malformed, or the request carries no session.
     *
     * @return list<Component> The `content` region's components.
     */
    public function content(ServerRequestInterface $serverRequest, ThemeEntry $themeEntry, array $problems): array
    {
        return [
            new Notice(self::INTRO, NoticeTone::Info, 'theme-intro'),
            new PanelGrid([
                ...$this->themeSampleCharts->items(),
                ...$this->panels($serverRequest, $themeEntry, $problems),
            ]),
        ];
    }

    /**
     * @param ServerRequestInterface $serverRequest The request: the list's query and the visitor's token.
     * @param ThemeEntry             $themeEntry    The vehicle form's values.
     * @param array<string, string>  $problems      What is wrong with them, by field name.
     *
     * @throws LogicException When a key is malformed, or the request carries no session.
     *
     * @return list<KeyedComponent> The list, record, delete and form panels, then the smaller parts'.
     */
    private function panels(ServerRequestInterface $serverRequest, ThemeEntry $themeEntry, array $problems): array
    {
        $vehicleForm = $this->themeSampleForms->vehicle($serverRequest, $themeEntry, $problems);

        return [
            $this->panel('inventory', 'Inventory', 'A list: filters, sortable heads, badges and pages', true, [
                ...$this->themeSampleRecords->list($serverRequest),
            ]),
            $this->panel('vehicle', 'A record', 'Its facts, its status and what can be done', false, [
                new KeyedComponent('card', $this->themeSampleParts->record()),
            ]),
            $this->panel('confirm', 'Before deleting', 'A question with its form and a way back', false, [
                new KeyedComponent('card', $this->themeSampleActions->confirm($serverRequest)),
            ]),
            $this->panel('form', 'A form', 'Fields in a grid, posted for real: leave it empty for the errors', true, [
                new KeyedComponent('vehicle', $vehicleForm),
            ]),
            ...$this->small($serverRequest),
        ];
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, for the sign-out form's token.
     *
     * @throws LogicException When a key is malformed, or the request carries no session.
     *
     * @return list<KeyedComponent> The badge, notice, account and icon panels.
     */
    private function small(ServerRequestInterface $serverRequest): array
    {
        $keyedComponent = new KeyedComponent('legend', $this->themeSampleParts->icons());

        return [
            $this->panel('badges', 'Badges', "A record's status", false, $this->themeSampleParts->badges()),
            $this->panel('notices', 'Notices', 'Short messages', false, $this->themeSampleParts->notices()),
            $this->panel('account', 'Account menu', 'A sample staff member; nobody is signed in', false, [
                new KeyedComponent('menu', $this->themeSampleActions->account($serverRequest)),
            ]),
            $this->panel('icons', 'Icons', 'Heroicons, 20 solid', false, [$keyedComponent]),
        ];
    }

    /**
     * @param string               $id          The panel's id stem, also its key.
     * @param string               $title       Its heading.
     * @param string               $description The line under it.
     * @param bool                 $wide        Whether it spans the grid.
     * @param list<KeyedComponent> $body        What it shows.
     *
     * @throws InvalidArgumentException When the key is malformed.
     *
     * @return KeyedComponent The panel, keyed by its id.
     */
    private function panel(string $id, string $title, string $description, bool $wide, array $body): KeyedComponent
    {
        return new KeyedComponent($id, new Panel($id, $title, $description, $wide, $body));
    }
}
