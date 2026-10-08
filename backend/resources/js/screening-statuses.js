const screeningStatuses = {
    haram: {
        title: 'Харам — обнаружен компонент свиного происхождения',
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
    if (status === 'haram' && reasonCodes.includes('contains_intoxicating_beverage')) {
        return {
            title: 'Харам — обнаружен опьяняющий напиток',
            tone: 'danger',
            icon: '!',
        };
    }

    return screeningStatuses[status] ?? screeningStatuses.unsupported;
}
