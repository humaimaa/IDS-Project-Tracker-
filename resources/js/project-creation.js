document.querySelectorAll('[data-project-creation]').forEach((form) => {
    const phase = form.elements.phase;
    const pipeline = form.querySelector('[data-pipeline-creation]');
    const ongoing = form.querySelector('[data-ongoing-creation]');
    const list = form.querySelector('[data-component-list]');
    const pipelineList = form.querySelector('[data-pipeline-list]');
    const syncPipeline = () => {
        const selects = [...(pipelineList?.querySelectorAll('[data-pipeline-name]') ?? [])];
        const used = selects.map((select) => select.value);
        selects.forEach((select) => {
            [...select.options].forEach((option) => { option.disabled = used.includes(option.value) && option.value !== select.value; });
        });
        const current = form.elements.pipeline_template_id;
        if (current) {
            [...current.options].forEach((option) => { option.disabled = !used.includes(option.value); });
            if (!used.includes(current.value)) current.value = used[0] ?? '';
        }
        const add = form.querySelector('[data-add-pipeline]');
        if (add && current) add.disabled = phase.value !== 'Pipeline' || used.length >= current.options.length;
    };
    const sync = () => {
        [pipeline, ongoing].forEach((section) => {
            if (!section) return;
            const active = section === pipeline ? phase.value === 'Pipeline' : phase.value === 'Ongoing';
            section.hidden = !active;
            section.querySelectorAll('input, select, textarea, button').forEach((field) => { field.disabled = !active; });
        });
        syncPipeline();
        list?.querySelectorAll('[data-component-row]').forEach((row, index) => { row.querySelector('[data-component-number]').textContent = index + 1; });
    };
    let nextComponent = Math.max(-1, ...Array.from(list?.querySelectorAll('[data-component-row]') ?? [], (row) => Number(row.querySelector('input').name.match(/components\[(\d+)\]/)?.[1] ?? -1))) + 1;
    const appendTemplate = (id, parent, replacements) => {
        let markup = document.getElementById(id).innerHTML;
        Object.entries(replacements).forEach(([key, value]) => { markup = markup.replaceAll(key, String(value)); });
        const fragment = document.createElement('template');
        fragment.innerHTML = markup;
        parent.append(fragment.content.cloneNode(true));
        sync();
        parent.lastElementChild.querySelector('input:not([type=hidden]), select')?.focus();
    };
    form.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.matches('[data-remove-pipeline]')) {
            button.closest('[data-pipeline-row]').remove(); sync();
        }
        if (button.matches('[data-add-pipeline]')) {
            const used = [...pipelineList.querySelectorAll('[data-pipeline-name]')].map((select) => select.value);
            const available = [...form.elements.pipeline_template_id.options].find((option) => !used.includes(option.value));
            if (!available) return;
            const indices = [...pipelineList.querySelectorAll('[data-pipeline-name]')].map((select) => Number(select.name.match(/pipeline_components\[(\d+)\]/)[1]));
            appendTemplate('new-pipeline-component', pipelineList, { __PIPELINE__: Math.max(-1, ...indices) + 1 });
            const select = pipelineList.lastElementChild.querySelector('[data-pipeline-name]');
            select.value = available.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (button.matches('[data-add-component]') && list.children.length < 30) {
            appendTemplate('new-project-component', list, { __COMPONENT__: nextComponent++ });
        }
        if (button.matches('[data-remove-component]')) { button.closest('[data-component-row]').remove(); sync(); }
        if (button.matches('[data-add-subcomponent]')) {
            const row = button.closest('[data-component-row]');
            const children = row.querySelector('[data-subcomponent-list]');
            if (children.children.length >= 30) return;
            const component = row.querySelector('input').name.match(/components\[(\d+)\]/)[1];
            const nextSub = Math.max(-1, ...Array.from(children.querySelectorAll('input[name$="[name]"]'), (input) => Number(input.name.match(/subcomponents\[(\d+)\]/)[1]))) + 1;
            appendTemplate('new-project-subcomponent', children, { __COMPONENT__: component, __SUB__: nextSub });
        }
        if (button.matches('[data-remove-subcomponent]')) button.closest('[data-subcomponent-row]').remove();
    });
    pipelineList?.addEventListener('change', (event) => {
        if (!event.target.matches('[data-pipeline-name]')) return;
        [...pipelineList.children].sort((a, b) => Number(a.querySelector('[data-pipeline-name]').selectedOptions[0].dataset.order) - Number(b.querySelector('[data-pipeline-name]').selectedOptions[0].dataset.order)).forEach((row) => pipelineList.append(row));
        sync();
    });
    phase.addEventListener('change', sync);
    window.addEventListener('pageshow', sync);
    sync();
});

document.querySelectorAll('[data-financial-filter]').forEach((form) => {
    form.elements.year.addEventListener('change', () => { form.elements.report.value = ''; });
});

document.querySelectorAll('[data-multi-select]').forEach((container) => {
    const summary = container.querySelector('[data-selection-summary]');
    const placeholder = summary.textContent;
    const sync = () => {
        const selected = [...container.querySelectorAll('input:checked')].map((input) => input.nextElementSibling.textContent.trim());
        summary.textContent = selected.length ? (selected.length > 2 ? selected.length + ' selected' : selected.join(', ')) : placeholder;
    };
    container.addEventListener('change', sync); sync();
});
const director = document.querySelector('[name=project_director_id]');
if (director) {
    const syncApprovers = () => document.querySelectorAll('input[name="approvers[]"]').forEach((input) => {
        input.disabled = input.value === director.value;
        if (input.disabled) input.checked = false;
        input.closest('label').hidden = input.disabled;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    director.addEventListener('change', syncApprovers); syncApprovers();
}
const financialYearsForm = document.querySelector('[data-financial-years-form]');
if (financialYearsForm) {
 const projectChoice = financialYearsForm.querySelector('[name="project_id"]');
 const list = financialYearsForm.querySelector('[data-financial-year-list]');
 const template = financialYearsForm.querySelector('[data-financial-year-template]');
 let nextIndex = Math.max(-1, ...Array.from(list.querySelectorAll('[name]'), input => Number(input.name.match(/^years\[(\d+)\]/)?.[1] ?? -1))) + 1;
 const syncYears = () => {
  const selects = [...list.querySelectorAll('[data-year-choice]')];
  const selected = selects.map(select => select.value).filter(Boolean);
  const existingYears = JSON.parse(projectChoice.selectedOptions[0]?.dataset.financialYears || '[]').map(String);
  for (const select of selects) {
   const editing = Boolean(select.closest('[data-financial-year-entry]').querySelector('[name$="[report_id]"]'));
   for (const option of select.options) option.disabled = Boolean(option.value && ((!editing && existingYears.includes(option.value)) || (option.value !== select.value && selected.includes(option.value))));
   if (select.selectedOptions[0]?.disabled) select.value = '';
  }
  list.querySelectorAll('[data-remove-financial-year]').forEach(button => button.hidden = selects.length <= 1);
 };
 financialYearsForm.querySelector('[data-add-financial-year]')?.addEventListener('click', () => {
  list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__YEAR__', String(nextIndex++)));
  syncYears();
 });
 list.addEventListener('click', event => {
  const button = event.target.closest('[data-remove-financial-year]');
  if (button && list.children.length > 1) { button.closest('[data-financial-year-entry]').remove(); syncYears(); }
 });
 list.addEventListener('change', syncYears);
 projectChoice.addEventListener('change', syncYears);
 syncYears();
}
