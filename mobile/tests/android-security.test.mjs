import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const mobileDirectory = resolve(dirname(fileURLToPath(import.meta.url)), '..');

test('uses Android API 26 as the minimum supported version', async () => {
    const variables = await readFile(join(mobileDirectory, 'android', 'variables.gradle'), 'utf8');

    assert.match(variables, /minSdkVersion\s*=\s*26\b/);
});

test('allows cleartext only in the debug manifest', async () => {
    const mainManifest = await readFile(
        join(mobileDirectory, 'android', 'app', 'src', 'main', 'AndroidManifest.xml'),
        'utf8',
    );
    const debugManifest = await readFile(
        join(mobileDirectory, 'android', 'app', 'src', 'debug', 'AndroidManifest.xml'),
        'utf8',
    );

    assert.match(mainManifest, /android:usesCleartextTraffic="false"/);
    assert.match(debugManifest, /android:usesCleartextTraffic="true"/);
    assert.doesNotMatch(mainManifest, /cleartextTrafficPermitted="true"/);
    assert.doesNotMatch(debugManifest, /cleartextTrafficPermitted="true"/);
});

test('declares internet and camera permissions without wildcard navigation', async () => {
    const mainManifest = await readFile(
        join(mobileDirectory, 'android', 'app', 'src', 'main', 'AndroidManifest.xml'),
        'utf8',
    );
    const capacitorConfig = JSON.parse(
        await readFile(join(mobileDirectory, 'capacitor.config.json'), 'utf8'),
    );

    assert.match(mainManifest, /android\.permission\.INTERNET/);
    assert.match(mainManifest, /android\.permission\.CAMERA/);
    assert.match(
        mainManifest,
        /<uses-feature\s+android:name="android\.hardware\.camera"\s+android:required="false"\s*\/>/,
    );
    assert.equal('allowNavigation' in capacitorConfig.server, false);
});
