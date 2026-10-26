<?php

declare(strict_types=1);

namespace App\Site\Component;

use AIC\Component\Application\RenderPass;
use AIC\Component\Domain\RendersMarkup;
use AIC\Component\Exception\UnknownComponent;

/**
 * BootstrapLayout's markup, compiled from BootstrapLayout.html.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class BootstrapLayoutTemplate
{
    use RendersMarkup;

    /**
     * @param BootstrapLayout $bootstrapLayout The component.
     * @param RenderPass      $renderPass      The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Its markup.
     */
    public function render(BootstrapLayout $bootstrapLayout, RenderPass $renderPass): string
    {
        return $this->part2($bootstrapLayout, $renderPass);
    }

    /**
     * @return string Markup from BootstrapLayout.html.php.
     */
    private function part0(): string
    {
        return "</html>\n";
    }

    /**
     * @param BootstrapLayout $bootstrapLayout The component.
     * @param RenderPass      $renderPass      The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from BootstrapLayout.html.php.
     */
    private function part1(BootstrapLayout $bootstrapLayout, RenderPass $renderPass): string
    {
        return "\">\n"
            . "    <meta name=\"tessera-csrf-header\" content=\"X-AIC-CSRF\">\n"
            . '    '
            . $renderPass->inline($bootstrapLayout->trackedAssets)
            . "</head>\n"
            . "<body>\n"
            . "    <main class=\"page-shell\">\n"
            . '        '
            . $renderPass->region($bootstrapLayout->content)
            . "    </main>\n"
            . "</body>\n"
            . $this->part0();
    }

    /**
     * @param BootstrapLayout $bootstrapLayout The component.
     * @param RenderPass      $renderPass      The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from BootstrapLayout.html.php.
     */
    private function part2(BootstrapLayout $bootstrapLayout, RenderPass $renderPass): string
    {
        return "<!doctype html>\n"
            . "<html lang=\"en\">\n"
            . "<head>\n"
            . "    <meta charset=\"utf-8\">\n"
            . "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
            . '    <title>'
            . self::text($bootstrapLayout->name)
            . "</title>\n"
            . "    <meta name=\"tessera-layout\" content=\"lotline-bootstrap\">\n"
            . '    <meta name="tessera-csrf-token" content="'
            . self::text($bootstrapLayout->csrfToken)
            . $this->part1($bootstrapLayout, $renderPass);
    }
}
