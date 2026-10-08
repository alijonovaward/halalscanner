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

test('keeps source-dependent ingredients in the review state', () => {
    assert.deepEqual(screeningPresentation('needs_review'), {
        title: 'Требуется проверка',
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
