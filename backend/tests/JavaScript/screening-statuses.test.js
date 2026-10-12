import assert from 'node:assert/strict';
import test from 'node:test';

import { screeningPresentation } from '../../resources/js/screening-statuses.js';

test('presents explicit pork screening as a categorical haram warning', () => {
    assert.deepEqual(screeningPresentation('haram', ['contains_pork']), {
        title: 'Харам — обнаружен компонент свиного происхождения',
        tone: 'danger',
        icon: '!',
    });
});

test('presents an explicitly intoxicating product as a categorical haram warning', () => {
    assert.deepEqual(screeningPresentation('haram', ['contains_intoxicating_beverage']), {
        title: 'Харам — обнаружен опьяняющий напиток',
        tone: 'danger',
        icon: '!',
    });
});

test('presents explicitly declared blood as a categorical haram warning', () => {
    assert.deepEqual(screeningPresentation('haram', ['contains_blood']), {
        title: 'Харам — обнаружен ингредиент из крови',
        tone: 'danger',
        icon: '!',
    });
});

test('presents explicitly declared carrion as a categorical haram warning', () => {
    assert.deepEqual(screeningPresentation('haram', ['contains_carrion']), {
        title: 'Харам — обнаружен продукт из падали',
        tone: 'danger',
        icon: '!',
    });
});

test('presents a forbidden animal as a categorical haram warning', () => {
    assert.deepEqual(screeningPresentation('haram', ['contains_forbidden_animal']), {
        title: 'Харам — обнаружен продукт запрещённого животного',
        tone: 'danger',
        icon: '!',
    });
});

test('uses a generic haram warning when no specific reason is recognized', () => {
    assert.deepEqual(screeningPresentation('haram', ['future_explicit_haram_reason']), {
        title: 'Харам — обнаружен запрещённый компонент',
        tone: 'danger',
        icon: '!',
    });
});

test('keeps source-dependent ingredients in the review state', () => {
    assert.deepEqual(screeningPresentation('needs_review'), {
        title: 'Требуется проверка',
        tone: 'warning',
        icon: '!',
    });
});

test('presents a named halal certificate claim as strong positive evidence', () => {
    assert.deepEqual(screeningPresentation('halal_certificate_claim'), {
        title: 'На упаковке заявлен сертификат халяль',
        tone: 'clear',
        icon: '✓',
    });
});

test('presents a conflict between a certificate and explicit haram evidence as undetermined', () => {
    assert.deepEqual(screeningPresentation('certificate_conflict'), {
        title: 'Неопределено — сертификат противоречит данным на упаковке',
        tone: 'warning',
        icon: '!',
    });
});

test('asks for an update instead of misreporting an unknown server status', () => {
    assert.deepEqual(screeningPresentation('new-server-status'), {
        title: 'Обновите приложение',
        tone: 'warning',
        icon: '!',
    });
});
