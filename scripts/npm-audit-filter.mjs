// Filtered `npm audit` gate for CI.
//
// `npm audit --audit-level=high` currently fails on exactly one advisory:
//
//   braces <=3.0.3 — GHSA-vfj7-8cjw-p6xm (high, stack exhaustion via deeply
//   nested patterns), pulled in transitively by tailwindcss@3 (chokidar /
//   micromatch / fast-glob -> braces). 3.0.3 is the latest published braces,
//   so there is no patched version to upgrade to; npm's only suggested fix is
//   the breaking tailwindcss 3 -> 4 migration.
//
// Accepted-risk rationale (Tailwind v3 kept deliberately):
// - braces is build/dev-time only: it expands `content` globs from
//   tailwind.config.js and watches files via chokidar. It is not shipped to
//   the browser; production output is static CSS.
// - Exploit requires attacker-controlled glob patterns. Our patterns are
//   local trusted config (`resources/js/**/*.{jsx,tsx}`, blade paths), not
//   remote input.
// - Revisit on Tailwind v4 migration (which drops the braces chain) or when
//   a patched braces is published.
//
// This script fails closed: any high/critical advisory NOT in ALLOWLISTED
// fails the build. Only the advisory above is ignored.

import { exec } from 'node:child_process';

const ALLOWLISTED_ADVISORY_URL_SUBSTRINGS = ['GHSA-vfj7-8cjw-p6xm'];

const SEVERITIES_THAT_FAIL = new Set(['high', 'critical']);

function collectAdvisoryVias(report) {
    const found = [];
    const vulns = report?.vulnerabilities ?? {};

    for (const [name, vuln] of Object.entries(vulns)) {
        const vias = Array.isArray(vuln?.via) ? vuln.via : [];

        for (const via of vias) {
            if (typeof via === 'object' && via !== null) {
                const severity = String(via.severity ?? '').toLowerCase();

                if (SEVERITIES_THAT_FAIL.has(severity)) {
                    found.push({ package: name, title: via.title ?? name, url: via.url ?? '', severity });
                }
            }
        }
    }

    return found;
}

function runNpmAuditJson() {
    return new Promise((resolve) => {
        exec(
            'npm audit --json --audit-level=high',
            { maxBuffer: 16 * 1024 * 1024 },
            (error, stdout, stderr) => {
                const raw = String(stdout ?? '').trim() || String(stderr ?? '').trim();
                resolve({ raw, execError: error });
            },
        );
    });
}

const { raw } = await runNpmAuditJson();

let report;

try {
    report = JSON.parse(raw);
} catch {
    console.error('npm-audit-filter: could not parse `npm audit --json` output.');
    console.error(raw.slice(0, 4000));
    process.exit(1);
}

const advisoryVias = collectAdvisoryVias(report);
const blocking = advisoryVias.filter(
    (via) => !ALLOWLISTED_ADVISORY_URL_SUBSTRINGS.some((sub) => via.url.includes(sub)),
);
const ignored = advisoryVias.filter((via) =>
    ALLOWLISTED_ADVISORY_URL_SUBSTRINGS.some((sub) => via.url.includes(sub)),
);

if (blocking.length > 0) {
    console.error(`npm-audit-filter: ${blocking.length} non-allowlisted high/critical advisory reference(s) found:`);

    for (const via of blocking) {
        console.error(`- [${via.severity}] ${via.package}: ${via.title} ${via.url}`);
    }

    process.exit(1);
}

if (ignored.length > 0) {
    console.log(
        `npm-audit-filter: pass. Ignored ${ignored.length} allowlisted advisory reference(s) (${ALLOWLISTED_ADVISORY_URL_SUBSTRINGS.join(', ')}).`,
    );
} else {
    console.log('npm-audit-filter: pass. No high/critical advisories found.');
}
