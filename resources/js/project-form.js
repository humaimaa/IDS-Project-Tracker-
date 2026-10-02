const statuses = ['Not Started', 'In Progress', 'Completed', 'On Hold', 'Not Applicable'];
function button(text, action) {
    const element = document.createElement('button');
    element.type = 'button';
    element.className = 'rounded-lg border border-teal/20 px-3 py-2 text-sm font-semibold text-teal';
    element.textContent = text;
    element.addEventListener('click', action);
    return element;
}
function field(parent, prefix, key, title, type, value, required = false) {
    const label = document.createElement('label');
    label.className = 'block text-sm font-semibold text-slate-600';
    label.textContent = title + (required ? ' *' : '');
    const input = document.createElement(type === 'select' ? 'select' : type === 'textarea' ? 'textarea' : 'input');
    input.className = 'field mt-2' + (type === 'textarea' ? ' min-h-24 py-2' : '');
    input.name = `${prefix}[${key}]`;
    input.required = required;
    if (type === 'select') statuses.forEach(status => input.add(new Option(status, status)));
    else if (type !== 'textarea') input.type = type;
    input.value = value ?? (type === 'select' ? 'Not Started' : '');
    label.append(input);
    parent.append(label);
    return input;
}
const meetings = document.querySelector('#meeting-actions');
if (meetings) {
    let nextId = 0;
    function addAction(values = {}) {
        const prefix = `actions[${nextId++}]`;
        const card = document.createElement('div');
        card.className = 'grid gap-4 rounded-lg border border-slate-200 p-4 sm:grid-cols-2';
        const description = field(card, prefix, 'description', 'Action description', 'textarea', values.description, true);
        field(card, prefix, 'officer', 'Responsible officer or agency', 'text', values.officer, true);
        field(card, prefix, 'due', 'Due date', 'date', values.due, true);
        field(card, prefix, 'status', 'Status', 'select', values.status, true);
        field(card, prefix, 'remarks', 'Completion remarks', 'textarea', values.remarks);
        field(card, prefix, 'activity', 'Related activity', 'text', values.activity);
        field(card, prefix, 'issue', 'Related issue', 'text', values.issue);
        card.append(button('Remove action', () => card.remove()));
        document.querySelector('#meeting-action-list').append(card);
        return description;
    }
    Object.values(window.meetingActions || []).forEach(addAction);
    document.querySelector('#add-meeting-action').addEventListener('click', () => addAction().focus());
}
const workItemForm = document.querySelector('#work-item-form');
if (workItemForm) {
    const type = workItemForm.elements.type;
    const project = workItemForm.elements.project_id;
    const stage = workItemForm.elements.stage;
    const parent = workItemForm.elements.parent_id;
    const template = workItemForm.elements.template_id;
    const name = workItemForm.elements.name;
    function filterOptions(select, matches) {
        if (!select) return;
        Array.from(select.options).forEach(option => {
            const available = !option.value || matches(option);
            option.hidden = !available;
            option.disabled = !available;
        });
        if (select.selectedOptions[0]?.disabled) select.value = '';
    }
    function updateWorkFields() {
        filterOptions(parent, option => option.dataset.project === project.value && option.dataset.stage === stage.value);
        const parentTemplate = parent?.selectedOptions[0]?.dataset.template;
        filterOptions(template, option => option.dataset.stage === stage.value && (!parent || option.dataset.parentTemplate === parentTemplate));
        const predefined = type.value === 'Predefined';
        template.closest('[data-work-field]').hidden = !predefined;
        template.disabled = !predefined;
        template.required = predefined;
        name.closest('[data-work-field]').hidden = predefined;
        name.disabled = predefined;
        name.required = !predefined;
    }
    [type, project, stage, parent].filter(Boolean).forEach(input => input.addEventListener('change', updateWorkFields));
    updateWorkFields();
}
