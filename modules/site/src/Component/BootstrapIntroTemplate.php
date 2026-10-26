<?php

declare(strict_types=1);

namespace App\Site\Component;

use AIC\Component\Domain\RendersMarkup;

/**
 * BootstrapIntro's markup, compiled from BootstrapIntro.html.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class BootstrapIntroTemplate
{
    use RendersMarkup;

    /**
     * @param BootstrapIntro $bootstrapIntro The component.
     *
     * @return string Its markup.
     */
    public function render(BootstrapIntro $bootstrapIntro): string
    {
        return $this->part1($bootstrapIntro);
    }

    /**
     * @param BootstrapIntro $bootstrapIntro The component.
     *
     * @return string Markup from BootstrapIntro.html.php.
     */
    private function part0(BootstrapIntro $bootstrapIntro): string
    {
        return "ion in progress</p>\n"
            . '    <a class="health-link" href="'
            . self::text($bootstrapIntro->healthPath)
            . '" data-enhance="off">Check application health <span aria-hidden="true">↗</span><'
            . "/a>\n"
            . "</section>\n";
    }

    /**
     * @param BootstrapIntro $bootstrapIntro The component.
     *
     * @return string Markup from BootstrapIntro.html.php.
     */
    private function part1(BootstrapIntro $bootstrapIntro): string
    {
        return "<section class=\"intro\" aria-labelledby=\"lotline-heading\">\n"
            . '    <p class="eyebrow">'
            . self::text($bootstrapIntro->dealership)
            . "</p>\n"
            . '    <h1 id="lotline-heading">'
            . self::text($bootstrapIntro->title)
            . "</h1>\n"
            . '    <p class="description">'
            . self::text($bootstrapIntro->description)
            . "</p>\n"
            . '    <p class="status"><span class="status-dot" aria-hidden="true"></span>Foundat'
            . $this->part0($bootstrapIntro);
    }
}
