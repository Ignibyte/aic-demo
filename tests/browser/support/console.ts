import { execFile, execFileSync } from 'node:child_process';
import { promisify } from 'node:util';

// The app's console as a look runs it (AIC #225): `$AIC_PHP bin/aic …` on this host, with the environment gate:23 and
// `aic-gate browser` hand the tests (the sites' PG* settings and AIC_PHP among it), so a job's effect shows on whichever
// site the look tests. Its mail goes to DDEV's Mailpit, where the looks read it, through the port DDEV publishes on
// 127.0.0.1 for the web container's SMTP (`.ddev/docker-compose.mailpit-smtp.yaml`).

const run = promisify(execFile);

// The PHP the gate runs; `php` when a look runs outside it.
const PHP = process.env.AIC_PHP ?? 'php';

// Mailpit's SMTP port inside DDEV's web container.
const SMTP = '1025';

// What `ddev describe -j` says of the web container.
interface Described {
    raw: { services: { web: { full_name: string; host_ports_mapping?: { exposed_port: string; host_port: string }[] } } };
}

let smtpPort = '';

// The host port DDEV publishes for the web container's SMTP: as `ddev describe -j` lists it, else as `docker port`
// gives it. Read once per test process.
function mailPort(): string {
    if (smtpPort !== '') {
        return smtpPort;
    }

    const described = JSON.parse(execFileSync('ddev', ['describe', '-j'], { encoding: 'utf8' })) as Described;
    const web = described.raw.services.web;
    const mapped = (web.host_ports_mapping ?? []).find((mapping) => mapping.exposed_port === SMTP);

    if (mapped !== undefined) {
        smtpPort = mapped.host_port;

        return smtpPort;
    }

    const published = execFileSync('docker', ['port', web.full_name, `${SMTP}/tcp`], { encoding: 'utf8' });
    const found = /^127\.0\.0\.1:(\d+)$/m.exec(published);

    if (found === null) {
        throw new Error(`DDEV publishes no host port for its web container's SMTP; run ddev restart. docker port said: ${published}`);
    }

    smtpPort = found[1];

    return smtpPort;
}

// The environment a console run gets: the tests', DDEV's Mailpit SMTP, then what the caller sets.
function environment(env: Record<string, string>): NodeJS.ProcessEnv {
    return { ...process.env, AIC_MAIL_HOST: '127.0.0.1', AIC_MAIL_PORT: mailPort(), ...env };
}

// Runs `bin/aic` with those arguments and gives what it printed.
export function aic(args: string[], env: Record<string, string> = {}): string {
    return execFileSync(PHP, ['bin/aic', ...args], { encoding: 'utf8', env: environment(env) });
}

// Starts `bin/aic` with those arguments and gives what it printed when it ends, so two can run at once.
export async function aicInBackground(args: string[], env: Record<string, string> = {}): Promise<string> {
    const { stdout } = await run(PHP, ['bin/aic', ...args], { encoding: 'utf8', env: environment(env) });

    return stdout;
}
