// Copy practical example prompts without changing Moodle or sending requests.
(() => {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('.copy-prompt');
        if (!button) return;
        const code = button.closest('.case-prompt')?.querySelector('pre code');
        if (!code) return;
        try {
            await navigator.clipboard.writeText(code.textContent || '');
            button.textContent = button.dataset.copiedLabel || 'Copied!';
        } catch (error) {
            button.textContent = 'Select the text / Selecione o texto';
            code.closest('pre')?.setAttribute('tabindex', '0');
            code.closest('pre')?.focus();
        }
    });
})();
