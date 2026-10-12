const screeningStatuses = {
    haram: {
        title: 'Харам — обнаружен запрещённый компонент',
        tone: 'danger',
        icon: '!',
    },
    needs_review: {
        title: 'Требуется проверка',
        tone: 'warning',
        icon: '!',
    },
    no_obvious_concerns: {
        title: 'В указанном составе явных рисков не найдено',
        tone: 'clear',
        icon: '✓',
    },
    halal_certificate_claim: {
        title: 'На упаковке заявлен сертификат халяль',
        tone: 'clear',
        icon: '✓',
    },
    certificate_conflict: {
        title: 'Неопределено — сертификат противоречит данным на упаковке',
        tone: 'warning',
        icon: '!',
    },
    insufficient_text: {
        title: 'Недостаточно данных',
        tone: 'neutral',
        icon: '?',
    },
    unsupported: {
        title: 'Обновите приложение',
        tone: 'warning',
        icon: '!',
    },
};

export function screeningPresentation(status, reasonCodes = []) {
    if (status === 'haram') {
        const reasonTitles = [
            ['contains_intoxicating_beverage', 'Харам — обнаружен опьяняющий напиток'],
            ['contains_pork', 'Харам — обнаружен компонент свиного происхождения'],
            ['contains_blood', 'Харам — обнаружен ингредиент из крови'],
            ['contains_carrion', 'Харам — обнаружен продукт из падали'],
            ['contains_forbidden_animal', 'Харам — обнаружен продукт запрещённого животного'],
        ];
        const matchedReason = reasonTitles.find(([reason]) => reasonCodes.includes(reason));

        if (matchedReason) {
            return {
                title: matchedReason[1],
                tone: 'danger',
                icon: '!',
            };
        }
    }

    return screeningStatuses[status] ?? screeningStatuses.unsupported;
}
