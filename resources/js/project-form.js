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
