document.querySelectorAll('[data-stored-project-chat]').forEach((root) => {
    const dialog = root.querySelector('dialog');
    const form = dialog.querySelector('form');
    const cancel = dialog.querySelector('[data-stored-reply-cancel]');
    const label = dialog.querySelector('[data-stored-reply-label]');
    const open = () => {
        if (!dialog.open) dialog.showModal();
        const token = root.dataset.csrfToken;
        if (token) {
            const ids = [...dialog.querySelectorAll('[data-comment-id]')].map((item) => Number(item.dataset.commentId));
            const requests = [];
            for (let offset = 0; offset < ids.length; offset += 200) {
                requests.push(fetch(root.dataset.readUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ ids: ids.slice(offset, offset + 200) }) }));
            }
            Promise.all(requests).then((responses) => {
                if (responses.every((response) => response.ok)) root.querySelector('.detail-chat-count').hidden = true;
            }).catch(() => {});
        }
    };
    root.querySelector('[data-stored-chat-open]').addEventListener('click', open);
    root.querySelector('[data-stored-chat-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    root.querySelectorAll('[data-stored-reply]').forEach((button) => button.addEventListener('click', () => {
        if (!form) return;
        form.elements.parent_id.value = button.dataset.storedReply;
        label.textContent = 'Replying to comment #' + button.dataset.storedReply;
        label.hidden = false;
        cancel.hidden = false;
        form.elements.body.focus();
    }));
    cancel?.addEventListener('click', () => { form.elements.parent_id.value = ''; label.hidden = true; cancel.hidden = true; });
    if (location.hash === '#project-discussion') open();
    window.addEventListener('hashchange', () => { if (location.hash === '#project-discussion') open(); });
});
document.querySelectorAll('[data-step-target]').forEach((button) => button.addEventListener('click', () => {
    document.querySelectorAll('[data-step-target]').forEach((step) => step.setAttribute('aria-expanded', String(step === button)));
    document.querySelectorAll('[data-step-detail]').forEach((panel) => { panel.hidden = panel.id !== button.dataset.stepTarget; });
    document.getElementById(button.dataset.stepTarget)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}));
document.querySelector('select[name="year"]')?.addEventListener('change', (event) => {
    const report = event.target.form?.querySelector('[name="report"]');
    if (report) report.value = '';
});
