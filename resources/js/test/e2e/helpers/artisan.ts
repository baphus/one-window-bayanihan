import { execFileSync } from 'child_process';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const PROJECT_ROOT = path.resolve(__dirname, '../../../../..');

/** Run a Laravel Tinker expression from the project root for E2E setup. */
export function runTinker(code: string, context: string, timeout = 15000): string {
    try {
        return execFileSync('php', ['artisan', 'tinker', '--execute', code], {
            cwd: PROJECT_ROOT,
            timeout,
            encoding: 'utf8',
        });
    } catch (error) {
        const commandError = error as NodeJS.ErrnoException & {
            stdout?: string | Buffer;
            stderr?: string | Buffer;
        };
        const stdout = commandError.stdout ? String(commandError.stdout) : '(none)';
        const stderr = commandError.stderr ? String(commandError.stderr) : '(none)';

        throw new Error(
            `E2E Tinker setup failed (${context}).\nstdout:\n${stdout}\nstderr:\n${stderr}`,
            { cause: error },
        );
    }
}
