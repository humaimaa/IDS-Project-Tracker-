import './detail-chat';
import './pipeline-components';
import './dashboard-updates';
import './project-form';
import './project-chat';
const sidebar = document.querySelector('#sidebar');
const shell = document.querySelector('#app-shell');
const backdrop = document.querySelector('#mobile-backdrop');
const desktopViewport = window.matchMedia('(min-width: 768px)');
const desktopToggle = document.querySelector('#collapse-sidebar');
const mobileToggle = document.querySelector('#mobile-menu');
const closeToggle = document.querySelector('#close-sidebar');
let desktopCollapsed = false;
try { desktopCollapsed = localStorage.getItem('ids-sidebar-collapsed') === 'true'; } catch {}

function syncSidebar() {
 const mobileOpen = !desktopViewport.matches && sidebar.classList.contains('is-open');
 sidebar.classList.toggle('is-collapsed', desktopViewport.matches ? desktopCollapsed : !mobileOpen);
 sidebar.inert = false;
 sidebar.setAttribute('aria-hidden', 'false');
 shell.inert = mobileOpen;
 backdrop.classList.toggle('is-visible', mobileOpen);
 document.body.classList.toggle('sidebar-open', mobileOpen);
 desktopToggle.setAttribute('aria-expanded', String(!desktopCollapsed));
 desktopToggle.setAttribute('aria-label', desktopCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
 desktopToggle.title = desktopCollapsed ? 'Expand sidebar' : 'Collapse sidebar';
 mobileToggle.setAttribute('aria-expanded', String(mobileOpen));
 sidebar.querySelectorAll('[data-toggle-group]').forEach(button => {
  const content = sidebar.querySelector('[data-content="'+button.dataset.toggleGroup+'"]');
  button.setAttribute('aria-expanded', String(!sidebar.classList.contains('is-collapsed') && !content.classList.contains('hidden')));
 });
}

function expandSidebar() {
 if (desktopViewport.matches) {
  desktopCollapsed = false;
  try { localStorage.setItem('ids-sidebar-collapsed', 'false'); } catch {}
 } else {
  sidebar.classList.add('is-open');
 }
 syncSidebar();
}

function closeMobileNav() {
 const wasOpen = sidebar.classList.contains('is-open');
 sidebar.classList.remove('is-open');
 syncSidebar();
 if (wasOpen && !desktopViewport.matches) mobileToggle.focus();
}

desktopToggle.addEventListener('click', () => {
 desktopCollapsed = !desktopCollapsed;
 try { localStorage.setItem('ids-sidebar-collapsed', String(desktopCollapsed)); } catch {}
 syncSidebar();
});
mobileToggle.addEventListener('click', () => {
 expandSidebar();
 closeToggle.focus();
});
closeToggle.addEventListener('click', closeMobileNav);
backdrop.addEventListener('click', closeMobileNav);
sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', event => {
 if (sidebar.classList.contains('is-collapsed') && link.matches('.nav-link')) {
  event.preventDefault();
  expandSidebar();
 } else {
  closeMobileNav();
 }
}));
desktopViewport.addEventListener('change', () => {
 const focusWasInSidebar = sidebar.contains(document.activeElement);
 sidebar.classList.remove('is-open');
 syncSidebar();
 if (focusWasInSidebar) (desktopViewport.matches ? desktopToggle : mobileToggle).focus();
});
document.addEventListener('keydown', event => {
 if (desktopViewport.matches || !sidebar.classList.contains('is-open')) return;
 if (event.key === 'Escape') {
  event.preventDefault();
  closeMobileNav();
 }
 if (event.key === 'Tab') {
  const focusable = [...sidebar.querySelectorAll('a[href], button, summary, [tabindex="0"]')]
   .filter(element => !element.disabled && element.getClientRects().length > 0);
  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
 }
});
syncSidebar();
sidebar.querySelectorAll('.nav-link').forEach(link => link.setAttribute('aria-label', link.title));
sidebar.querySelectorAll('.sidebar-foot summary').forEach(control => {
 control.addEventListener('click', event => {
  if (!sidebar.classList.contains('is-collapsed')) return;
  event.preventDefault();
  expandSidebar();
 });
});

document.querySelectorAll('[data-toggle-group]').forEach(button => {
 const content = document.querySelector('[data-content="'+button.dataset.toggleGroup+'"]');
 if(content.querySelector('.active')) content.classList.remove('hidden');
 const sync = () => { const open = !content.classList.contains('hidden'); button.setAttribute('aria-expanded', String(open)); button.querySelector('.nav-chevron').textContent = open ? '⌃' : '⌄'; }; sync();
 button.addEventListener('click', () => {
  if (sidebar.classList.contains('is-collapsed')) {
   expandSidebar();
   content.classList.remove('hidden');
  } else {
   content.classList.toggle('hidden');
  }
  sync();
 });
});
syncSidebar();
const globalSearch = document.querySelector('#global-search');
const isDashboard = Boolean(document.querySelector('#dashboard-page'));
 globalSearch.value = isDashboard ? new URLSearchParams(window.location.search).get('search') || '' : '';
 globalSearch.addEventListener('keydown', event => {
  if (event.key === 'Enter') {
   event.preventDefault();
   const url = new URL(isDashboard ? window.location.href : globalSearch.dataset.searchUrl);
   url.searchParams.set('search', globalSearch.value);
   window.location.assign(url);
  }
 });
document.querySelectorAll('[data-delete-form]').forEach(form => form.addEventListener('submit', event => { if (!confirm('Delete this record?')) event.preventDefault(); }));

document.addEventListener('click', event => {
 document.querySelectorAll('[data-account-menu][open]').forEach(menu => {
  if (!menu.contains(event.target)) menu.open = false;
 });
});
document.addEventListener('keydown', event => {
 if (event.key === 'Escape') {
  document.querySelectorAll('[data-account-menu][open]').forEach(menu => {
   menu.open = false;
   menu.querySelector('summary').focus();
  });
 }
});

 document.querySelectorAll('[data-dashboard-filters] select').forEach(select => {
  select.addEventListener('change', () => select.form.requestSubmit());
 });

document.querySelectorAll('[data-project-url]').forEach(row => {
 row.addEventListener('click', event => {
  if (event.target.closest('a, button, input, select') || window.getSelection()?.toString()) return;
  if (event.ctrlKey || event.metaKey) window.open(row.dataset.projectUrl, '_blank', 'noopener');
  else window.location.assign(row.dataset.projectUrl);
 });
});
