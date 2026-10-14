document.querySelectorAll('[data-issue-targets]').forEach((container) => {
    const projects = JSON.parse(container.querySelector('[data-issue-projects]').textContent);
    const project = container.querySelector('[data-issue-project]');
    const component = container.querySelector('[data-issue-component]');
    const subcomponent = container.querySelector('[data-issue-subcomponent]');
    const componentId = container.querySelector('[data-issue-component-id]');
    const pipelineId = container.querySelector('[data-issue-pipeline-id]');
    const populate = (select, items, placeholder) => {
        select.replaceChildren(new Option(placeholder, ''));
        items.forEach((item) => select.add(new Option(item.name, item.id)));
    };
    const syncComponent = () => {
        const selectedProject = projects.find((item) => String(item.id) === project.value);
        const selectedComponent = selectedProject?.components.find((item) => String(item.id) === component.value);
        componentId.value = selectedProject?.phase === 'Ongoing' ? component.value : '';
        pipelineId.value = selectedProject?.phase === 'Pipeline' ? component.value : '';
        populate(subcomponent, selectedComponent?.subcomponents ?? [], selectedProject?.phase === 'Pipeline' ? 'Not applicable for pipeline' : 'Select subcomponent (optional)');
        subcomponent.disabled = !selectedComponent?.subcomponents.length;
    };
    const syncProject = () => {
        const selected = projects.find((item) => String(item.id) === project.value);
        populate(component, selected?.components ?? [], selected ? 'Select component (optional)' : 'Select project first');
        component.disabled = !selected;
        syncComponent();
    };
    project.addEventListener('change', syncProject);
    component.addEventListener('change', syncComponent);
    project.value = container.dataset.project;
    syncProject();
    component.value = container.dataset.component;
    syncComponent();
    subcomponent.value = container.dataset.subcomponent;
});
