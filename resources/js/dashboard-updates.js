const dashboard = document.querySelector('[data-dashboard-updates]');
if (dashboard) {
 let refreshing = false;
 let timer;
 const fragments = { comments: dashboard.querySelector('[data-dashboard-comments]'), meetings: dashboard.querySelector('[data-dashboard-meetings]'), upcoming: dashboard.querySelector('[data-dashboard-upcoming]') };
 const previous = {};
 function syncCommentScroll() {
  dashboard.querySelectorAll('[data-comment-notifications]').forEach(panel => {
   const viewport = panel.querySelector('.comment-notification-window');
   const group = panel.querySelector('.comment-notification-group');
   if (!viewport || !group) return;
   const overflow = group.scrollHeight > viewport.clientHeight;
   panel.classList.toggle('has-overflow', overflow);
   panel.style.setProperty('--comment-scroll-duration', Math.max(15, group.children.length * 5) + 's');
  });
 }
 const resizeObserver = new ResizeObserver(syncCommentScroll);
 resizeObserver.observe(fragments.comments);
 syncCommentScroll();
 dashboard.addEventListener('click', event => {
  const button = event.target.closest('[data-upcoming-pause]');
  if (!button) return;
  const paused = button.getAttribute('aria-pressed') !== 'true';
  button.setAttribute('aria-pressed', String(paused));
  button.textContent = paused ? 'Resume scrolling' : 'Pause scrolling';
  button.closest('.upcoming-meeting-panel').classList.toggle('is-paused', paused);
 });
 async function refresh() {
  if (refreshing || document.hidden) return;
  refreshing = true;
  try {
   const response = await fetch(dashboard.dataset.dashboardUpdates, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store', signal: AbortSignal.timeout(10000) });
   if (!response.ok) return;
   const data = await response.json();
   for (const [key, container] of Object.entries(fragments)) {
    if (typeof data[key] !== 'string' || previous[key] === data[key] || container.contains(document.activeElement) || container.matches(':hover') || container.querySelector('.is-paused')) continue;
    const scroll = container.firstElementChild?.scrollTop || 0;
    container.innerHTML = data[key];
    if (container.firstElementChild) container.firstElementChild.scrollTop = scroll;
    previous[key] = data[key];
    if (key === 'comments') syncCommentScroll();
   }
   const issueList = document.querySelector('[data-issue-project-list]');
   if (issueList && typeof data.issueProjects === 'string' && previous.issueProjects !== data.issueProjects && !issueList.contains(document.activeElement) && !issueList.matches(':hover')) {
    const scroll = issueList.scrollTop;
    issueList.innerHTML = data.issueProjects;
    issueList.scrollTop = scroll;
    previous.issueProjects = data.issueProjects;
   }
   const notification = document.querySelector('[data-issue-notification]');
   const badge = notification?.querySelector('[data-issue-count]');
   if (badge && Number.isInteger(data.issues)) {
    badge.textContent = data.issues > 99 ? '99+' : String(data.issues);
    badge.hidden = data.issues === 0;
    notification.dataset.hasIssues = String(data.issues > 0);
    notification.setAttribute('aria-label', `${data.issues} projects with issues`);
   }
  } catch { /* Keep the current content if the network is temporarily unavailable. */ }
  finally { refreshing = false; }
 }
 function start() { window.clearInterval(timer); timer = window.setInterval(refresh, 30000); }
 window.addEventListener('focus', refresh);
 document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
 window.addEventListener('pagehide', () => window.clearInterval(timer));
 window.addEventListener('pageshow', () => {
  window.requestAnimationFrame(syncCommentScroll);
  start();
  refresh();
 });
 start();
}

const issueDropdown = document.querySelector('[data-issue-dropdown]');
if (issueDropdown) {
 document.addEventListener('click', event => {
  if (issueDropdown.open && !issueDropdown.contains(event.target)) issueDropdown.open = false;
 });
 document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && issueDropdown.open) {
   issueDropdown.open = false;
   issueDropdown.querySelector('summary').focus();
  }
 });
}
