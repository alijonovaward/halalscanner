import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const mobileDirectory = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const templatePath = join(mobileDirectory, 'www', 'offline.template.html');

function validateProfile(profile) {
    if (!['debug', 'staging'].includes(profile)) {
        throw new Error('Неизвестный профиль сборки: используйте debug или staging.');
    }
}

function validateServerUrl(serverUrl, profile) {
    let parsedUrl;

    try {
        parsedUrl = new URL(serverUrl);
    } catch {
        throw new Error('Укажите корректный адрес backend в HALALSCAN_SERVER_URL.');
    }

    const hasUnsupportedParts = parsedUrl.username
        || parsedUrl.password
        || parsedUrl.pathname !== '/'
        || parsedUrl.search
        || parsedUrl.hash;

    if (!['http:', 'https:'].includes(parsedUrl.protocol) || hasUnsupportedParts) {
        throw new Error('Укажите адрес backend как HTTP(S)-origin без пути и параметров.');
    }

    if (profile === 'staging' && parsedUrl.protocol !== 'https:') {
        throw new Error('Для staging требуется HTTPS-адрес backend.');
    }

    return parsedUrl.origin;
}

function escapeHtml(value) {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;');
}

export async function configureMobileApp({ serverUrl, profile = 'debug', outputDirectory }) {
    validateProfile(profile);

    const normalizedServerUrl = validateServerUrl(serverUrl, profile);
    const targetDirectory = resolve(outputDirectory ?? mobileDirectory);
    const targetWebDirectory = join(targetDirectory, 'www');
    const config = {
        appId: 'uz.halalscan.app',
        appName: 'HalalScan',
        webDir: 'www',
        server: {
            url: normalizedServerUrl,
            errorPath: 'offline.html',
            ...(profile === 'debug' && new URL(normalizedServerUrl).protocol === 'http:'
                ? { cleartext: true }
                : {}),
        },
    };

    const template = await readFile(templatePath, 'utf8');
    const offlinePage = template.replaceAll('{{SERVER_URL}}', escapeHtml(normalizedServerUrl));

    await mkdir(targetWebDirectory, { recursive: true });
    await Promise.all([
        writeFile(
            join(targetDirectory, 'capacitor.config.json'),
            `${JSON.stringify(config, null, 4)}\n`,
        ),
        writeFile(join(targetWebDirectory, 'offline.html'), offlinePage),
    ]);

    return config;
}

const invokedPath = process.argv[1] ? resolve(process.argv[1]) : null;

if (invokedPath === fileURLToPath(import.meta.url)) {
    const serverUrl = process.env.HALALSCAN_SERVER_URL;
    const profile = process.env.HALALSCAN_BUILD_PROFILE ?? 'debug';

    if (!serverUrl) {
        console.error('Задайте HALALSCAN_SERVER_URL, например http://192.168.1.20:8000.');
        process.exitCode = 1;
    } else {
        try {
            const config = await configureMobileApp({ serverUrl, profile });
            console.log(`Configured ${config.appName} for ${config.server.url} (${profile}).`);
        } catch (error) {
            console.error(error instanceof Error ? error.message : String(error));
            process.exitCode = 1;
        }
    }
}
