const pipelineNavigation = document.querySelector('[data-pipeline-navigation]');
const pipelineDetails = document.querySelector('[data-pipeline-details]');
if (pipelineNavigation && pipelineDetails) {
 const links = [...pipelineNavigation.querySelectorAll('[aria-controls]')];
 const details = [...pipelineDetails.querySelectorAll('.component-details')];
 function selectComponent(id, scroll = false) {
  const selected = details.find(panel => panel.id === id);
  if (!selected) return;
  for (const panel of details) {
   panel.hidden = panel !== selected;
   panel.open = panel === selected;
  }
  for (const link of links) {
   const active = link.getAttribute('aria-controls') === id;
   link.classList.toggle('is-selected', active);
   link.setAttribute('aria-expanded', String(active));
  }
  if (scroll) selected.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' });
 }
 pipelineNavigation.addEventListener('click', event => {
  const link = event.target.closest('[aria-controls]');
  if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
  event.preventDefault();
  const id = link.getAttribute('aria-controls');
  history.replaceState(null, '', '#' + id);
  selectComponent(id, true);
 });
 window.addEventListener('hashchange', () => selectComponent(window.location.hash.slice(1), true));
 for (const panel of details) panel.addEventListener('toggle', () => {
  const link = links.find(item => item.getAttribute('aria-controls') === panel.id);
  link?.setAttribute('aria-expanded', String(panel.open && !panel.hidden));
 });
 const hash = window.location.hash.slice(1);
 const initial = details.some(panel => panel.id === hash) ? hash : (pipelineNavigation.querySelector('[aria-current="step"]') || links[0])?.getAttribute('aria-controls');
 selectComponent(initial);
}
