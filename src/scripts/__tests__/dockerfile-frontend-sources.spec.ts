import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const SRC = resolve(import.meta.dirname, '../..');
const DOCKERFILE = resolve(SRC, '../Dockerfile.prod');

/** The scripts the `frontend-sources` stage keeps (`find scripts … ! -name <file>`). */
function keptScripts(): Set<string> {
    const dockerfile = readFileSync(DOCKERFILE, 'utf8');
    const prune = /find scripts ([^]*?)-exec rm -rf \{\} \+/.exec(dockerfile);
    if (prune === null) throw new Error('Dockerfile.prod no longer prunes scripts/');

    return new Set([...prune[1].matchAll(/! -name (\S+)/g)].map((match) => match[1]));
}

/** The files under scripts/ a build file loads, read from its imports and shell calls. */
function scriptsUsedBy(file: string): string[] {
    const source = readFileSync(resolve(SRC, file), 'utf8');
    const prefix = file.startsWith('scripts/')
        ? '(?:\\./|\\./scripts/|scripts/)'
        : '(?:\\./scripts/|scripts/)';
    const pattern = new RegExp(`['"\\s]${prefix}([\\w.-]+\\.(?:mjs|sh))\\b`, 'g');

    return [...source.matchAll(pattern)].map((match) => match[1]);
}

describe('Dockerfile.prod frontend sources', () => {
    it('keeps every script the core and module builds load', () => {
        const kept = keptScripts();
        const pending = ['vite.config.ts', 'vite.module.config.ts'];
        const seen = new Set<string>();

        // verify-build.mjs and build-module.sh run in the image, and so does what they load
        for (const script of ['verify-build.mjs', 'build-module.sh', 'module-build-mode.sh']) {
            pending.push(`scripts/${script}`);
        }

        while (pending.length > 0) {
            const file = pending.pop() as string;
            for (const script of scriptsUsedBy(file)) {
                if (seen.has(script)) continue;
                seen.add(script);
                expect(kept, `${file} loads scripts/${script}`).toContain(script);
                pending.push(`scripts/${script}`);
            }
        }

        expect(seen).toContain('build-file-names.mjs');
    });
});
