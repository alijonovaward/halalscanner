import assert from 'node:assert/strict';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';

import { configureMobileApp } from '../scripts/configure.mjs';

async function withOutputDirectory(callback) {
    const outputDirectory = await mkdtemp(join(tmpdir(), 'halalscan-mobile-'));

    try {
        await callback(outputDirectory);
    } finally {
        await rm(outputDirectory, { recursive: true, force: true });
    }
}

test('writes a debug configuration for one local HTTP origin', async () => {
    await withOutputDirectory(async (outputDirectory) => {
        const config = await configureMobileApp({
            serverUrl: 'http://192.0.2.10:8000',
            profile: 'debug',
            outputDirectory,
        });

        assert.deepEqual(config, {
            appId: 'uz.halalscan.app',
            appName: 'HalalScan',
            webDir: 'www',
            server: {
                url: 'http://192.0.2.10:8000',
                errorPath: 'offline.html',
                cleartext: true,
            },
        });
        assert.equal('allowNavigation' in config.server, false);

        const savedConfig = JSON.parse(
            await readFile(join(outputDirectory, 'capacitor.config.json'), 'utf8'),
        );
        assert.deepEqual(savedConfig, config);

        const offlinePage = await readFile(
            join(outputDirectory, 'www', 'offline.html'),
            'utf8',
        );
        assert.match(offlinePage, /http:\/\/192\.0\.2\.10:8000/);
        assert.match(offlinePage, /Повторить/);
    });
});

test('writes a staging configuration only for HTTPS and without cleartext', async () => {
    await withOutputDirectory(async (outputDirectory) => {
        const config = await configureMobileApp({
            serverUrl: 'https://staging.example.com',
            profile: 'staging',
            outputDirectory,
        });

        assert.deepEqual(config.server, {
            url: 'https://staging.example.com',
            errorPath: 'offline.html',
        });
        assert.equal('allowNavigation' in config.server, false);
    });
});

test('rejects insecure staging, unsupported profiles, and malformed server URLs', async () => {
    await withOutputDirectory(async (outputDirectory) => {
        await assert.rejects(
            configureMobileApp({
                serverUrl: 'http://staging.example.com',
                profile: 'staging',
                outputDirectory,
            }),
            /HTTPS/i,
        );

        for (const serverUrl of [
            'not-a-url',
            'ftp://example.com',
            'https://user:secret@example.com',
            'https://example.com/path',
            'https://example.com?debug=1',
        ]) {
            await assert.rejects(
                configureMobileApp({ serverUrl, profile: 'debug', outputDirectory }),
                /адрес backend/i,
            );
        }

        await assert.rejects(
            configureMobileApp({
                serverUrl: 'https://example.com',
                profile: 'release',
                outputDirectory,
            }),
            /профиль/i,
        );
    });
});

test('escapes the configured origin in the generated retry page', async () => {
    await withOutputDirectory(async (outputDirectory) => {
        await configureMobileApp({
            serverUrl: 'https://xn--e1afmkfd.xn--p1ai:8443',
            profile: 'staging',
            outputDirectory,
        });

        const offlinePage = await readFile(
            join(outputDirectory, 'www', 'offline.html'),
            'utf8',
        );

        assert.match(offlinePage, /https:\/\/xn--e1afmkfd\.xn--p1ai:8443/);
        assert.doesNotMatch(offlinePage, /allowNavigation/);
    });
});
