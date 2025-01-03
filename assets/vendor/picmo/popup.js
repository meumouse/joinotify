import { createPopup } from 'https://unpkg.com/@picmo/popup-picker@latest/dist/index.js?module';

document.addEventListener('DOMContentLoaded', () => {
    const trigger_button = document.querySelector('.emoji_picker');

    const picker = createPopup({}, {
        referenceElement: trigger_button,
        triggerElement: trigger_button,
        position: 'right-end',
        maxRecents: 25,
    });

    picker.addEventListener('emoji:select', (selection) => {
        const input = document.querySelector('.set-whatsapp-message');
        input.value += selection.emoji;
        input.focus();
    });

    document.querySelector('.emoji_picker').addEventListener('click', () => {
        picker.toggle();
    });
});