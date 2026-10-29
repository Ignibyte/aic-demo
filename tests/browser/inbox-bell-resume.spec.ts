import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { insertMember, insertNotification, readBell, removeInboxLooks, signInMember } from './support/inbox';

test.beforeEach(() => removeInboxLooks());
test.afterEach(() => removeInboxLooks());

test('a resumed stream sends only what came after the id it resumes from, each once, then the count', async ({ page }) => {
    const errors = collectFormErrors(page);
    const member = insertMember('resume');
    const older = insertNotification(member, 'Resume look one', 3);
    const middle = insertNotification(member, 'Resume look two', 2);
    const newest = insertNotification(member, 'Resume look three', 1);
    await signInMember(page, member);

    // From the oldest: the two after it, oldest first, each prepended once with its own id, then the count.
    const resumed = await readBell(page, String(older));
    expect(resumed.map((event) => [event.id, event.target, event.mode])).toEqual([
        [String(middle), '@bell-items', 'prepend'],
        [String(newest), '@bell-items', 'prepend'],
        ['', '@bell-count', 'inner'],
    ]);
    expect(resumed[0].elements).toContain(`id="bell-item-${middle}"`);
    expect(resumed[1].elements).toContain(`id="bell-item-${newest}"`);
    expect(resumed[2].elements).toContain('>3<');

    // From the newest: nothing to prepend, the count alone.
    const caughtUp = await readBell(page, String(newest));
    expect(caughtUp.map((event) => [event.id, event.target])).toEqual([['', '@bell-count']]);
    expect(errors).toEqual([]);
});
