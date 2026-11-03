import { randomInt } from 'node:crypto';
import { aic, aicInBackground } from './console';
import { literal, sql } from './inventory';

// The scheduler and the worker run on this host (`$AIC_PHP bin/aic`, AIC #225), against the database the site under
// test uses, and mail into DDEV's Mailpit through the SMTP port DDEV publishes (`./console`).

// A UTC minute at that time of day on a random day long past, `YYYY-MM-DDTHH:MM`, that the schedule ledger has never
// fired: AIC's ledger fires each minute once, so a look picks a minute no one ran before (AIC #208).
export function unusedMinute(time: string): string {
    for (;;) {
        const day = new Date(Date.UTC(1990, 0, 1) + randomInt(0, 36500) * 86_400_000).toISOString().slice(0, 10);
        const minute = `${day}T${time}`;
        const [fired] = sql(`SELECT count(*) FROM aic_schedule_runs WHERE minute = ${literal(`${day} ${time}:00+00`)}`);

        if (fired === '0') {
            return minute;
        }
    }
}

// Runs `bin/aic schedule:run` for one UTC minute and gives its line.
export function runSchedule(minute: string): string {
    return aic(['schedule:run', minute]).trim();
}

// Runs `bin/aic queue:work` once and gives its line.
export function work(): string {
    return aic(['queue:work', '1000']).trim();
}

// Runs `bin/aic queue:work` once with the mail server out of reach: port 1 on this host answers nothing.
export function workWithMailDown(): string {
    return aic(['queue:work', '1000'], { AIC_MAIL_PORT: '1' }).trim();
}

// Starts `bin/aic queue:work` and gives its line when it ends, so two can run at once.
export async function workInBackground(): Promise<string> {
    return (await aicInBackground(['queue:work', '1000'])).trim();
}

// How many jobs a `queue:work` line says it ran.
export function ran(line: string): number {
    const found = /^queue:work ran (\d+) job\(s\)\.$/.exec(line);

    if (found === null) {
        throw new Error(`Not a queue:work line: ${line}`);
    }

    return Number(found[1]);
}

// One line of `bin/aic queue:failed`.
export interface FailedJob {
    id: number;
    job: string;
    line: string;
}

// The failed jobs `bin/aic queue:failed` lists, newest first.
export function failedJobs(): FailedJob[] {
    const output = aic(['queue:failed']);

    return output.split('\n').filter((line) => line.startsWith('#')).map((line) => {
        const [id, job] = line.slice(1).split(' ');

        return { id: Number(id), job, line };
    });
}

// Runs `bin/aic queue:retry <id>` and gives its line.
export function retry(id: number): string {
    return aic(['queue:retry', String(id)]).trim();
}

// Empties the queue of what is due, so a look's counts start from nothing of anyone else's.
export function drain(): void {
    work();
}
