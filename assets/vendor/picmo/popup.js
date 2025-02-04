import { createPopup } from 'https://unpkg.com/@picmo/popup-picker@latest/dist/index.js?module';

jQuery(document).on('workflowReady', function() {
    // Aguarde até que o DOM esteja carregado
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupEmojiPicker);
    } else {
        setupEmojiPicker();
    }
});

function setupEmojiPicker() {
    const trigger_button = document.querySelector('.emoji_picker');

    if (!trigger_button) {
        console.warn("Elemento '.emoji_picker' não encontrado.");
        return;
    }

    // Criar apenas um emoji picker
    if (!window.emojiPickerInstance) {
        window.emojiPickerInstance = createPopup({}, {
            referenceElement: trigger_button,
            triggerElement: trigger_button,
            position: 'right-end',
            maxRecents: 25,
        });

        window.emojiPickerInstance.addEventListener('emoji:select', (selection) => {
            const input = document.querySelector('.set-whatsapp-message');
            if (input) {
                input.value += selection.emoji;
                input.focus();
            }
        });

        trigger_button.addEventListener('click', () => {
            window.emojiPickerInstance.toggle();
        });
    }
}