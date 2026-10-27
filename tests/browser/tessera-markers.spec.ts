import { expect, test } from '@playwright/test';
import { signIn } from './support/dashboard';
import { anchorPath, collectErrors, pathOf, ready, RESULTS } from './support/tessera';

// The pages that hold components: the dashboard, the three lists and the theme guide.
const PAGES = ['/app', '/app/inventory', '/app/customers', '/app/sales', '/theme'];

test('every page marks its components on their root element, with no comment markers', async ({ page }) => {
    const errors = collectErrors(page);
    await signIn(page);

    for (const path of PAGES) {
        // The document as served, signed in: no protocol 0 comment marker, and components marked by attribute.
        const served = await page.goto(path);
        expect(pathOf(page.url()), path).toBe(path);
        const body = (await served?.text()) ?? '';
        expect(body, path).not.toContain('<!--t:');
        expect(body, path).toContain(' data-t-c="');

        // The document as the browser holds it: no Tessera comment, and every mark on an element.
        await ready(page);
        const marks = await page.evaluate(() => {
            const walker = document.createTreeWalker(document, NodeFilter.SHOW_COMMENT);
            let comments = 0;

            while (walker.nextNode() !== null) {
                comments += (walker.currentNode.nodeValue ?? '').trim().startsWith('t:') ? 1 : 0;
            }

            return { comments, components: document.querySelectorAll('[data-t-c]').length };
        });
        expect(marks.comments, path).toBe(0);
        expect(marks.components, path).toBeGreaterThan(0);
    }

    // A fragment of the inventory results holds one element, their root, with its key and its anchor. The page sends
    // the visit, with its session.
    await page.goto('/app/inventory');
    await ready(page);
    const results = await anchorPath(page, RESULTS);
    expect(results).not.toBe('');
    const answer = await page.evaluate(async (targets) => {
        const response = await fetch('/app/inventory', {
            headers: {
                'Tessera-Request': 'visit',
                'Tessera-Protocol': '1',
                'Tessera-Layout': 'lotline',
                'Tessera-Targets': targets,
            },
        });

        return { status: response.status, contentType: response.headers.get('content-type') ?? '', body: await response.text() };
    }, results);
    expect(answer.status).toBe(200);
    expect(answer.contentType).toMatch(/^text\/vnd\.tessera\+html/);
    const fragment = answer.body;
    expect(fragment).not.toContain('<!--t:');
    const roots = await page.evaluate(({ html, target }) => {
        const parsed = new DOMParser().parseFromString(html, 'text/html');
        const template = parsed.querySelector(`template[data-target="${target}"]`) as HTMLTemplateElement | null;

        return template === null
            ? []
            : [...template.content.children].map((root) => ({
                key: root.getAttribute('data-t-c') ?? '',
                anchor: root.getAttribute('data-t-anchor') ?? '',
            }));
    }, { html: fragment, target: results });
    expect(roots).toEqual([{ key: results.split('/').pop(), anchor: RESULTS }]);

    expect(errors).toEqual([]);
});
