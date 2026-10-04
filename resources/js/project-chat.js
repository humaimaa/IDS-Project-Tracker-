export function unreadCommentCount(comments, readMarkers, userId) {
 return comments.filter(comment => String(comment.author_id) !== userId && comment.id > Math.max(Number(readMarkers.project) || 0, Number(readMarkers[comment.context]) || 0)).length;
}

export function markDiscussionRead(comments, readMarkers, context) {
 const latest = comments.filter(comment => context === 'project' || comment.context === context).reduce((id, comment) => Math.max(id, comment.id), Number(readMarkers[context]) || 0);
 return { ...readMarkers, [context]: latest };
}

const popup = document.querySelector('[data-project-chat]');
if (popup) {
 const toggle = document.querySelector('#project-chat-toggle');
 const close = popup.querySelector('[data-chat-close]');
 const badge = toggle.querySelector('[data-chat-badge]');
 const announcement = document.querySelector('[data-chat-announcement]');
 const status = popup.querySelector('[data-chat-status]');
 const threadContainer = popup.querySelector('[data-chat-threads]');
 const refreshButton = popup.querySelector('[data-chat-refresh]');
 const storageKey = `ids-project-chat:${popup.dataset.projectKey}:${popup.dataset.userId}`;
 let comments = JSON.parse(popup.querySelector('[data-chat-metadata]').textContent);
 let renderedComments = comments;
 let readMarkers = loadReadMarkers();
 let refreshing = false;
 let previousUnread = -1;
 let returnFocus = toggle;
 const savedReadIds = new Set();
 let savingReads = false;

 function loadReadMarkers() {
  try {
   const value = JSON.parse(localStorage.getItem(storageKey) || '{}');
   return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
  } catch { return {}; }
 }

 function updateBadge() {
  const count = unreadCommentCount(comments, readMarkers, popup.dataset.userId);
  badge.hidden = count === 0;
  badge.textContent = count > 99 ? '99+' : String(count);
  toggle.setAttribute('aria-label', `${popup.hidden ? 'Open' : 'Close'} project comments${count ? `, ${count} unread` : ''}`);
  if (count !== previousUnread) announcement.textContent = count ? `${count} unread project ${count === 1 ? 'message' : 'messages'}` : '';
  previousUnread = count;
 }

 function markRead() {
  if (popup.hidden || document.hidden) return;
  readMarkers = markDiscussionRead(renderedComments, readMarkers, popup.dataset.context);
  try { localStorage.setItem(storageKey, JSON.stringify(readMarkers)); } catch {}
  updateBadge();
  saveReadState();
 }

 async function saveReadState() {
  if (savingReads || popup.hidden || document.hidden || !popup.dataset.readUrl) return;
  const ids = renderedComments.filter(comment => (popup.dataset.context === 'project' || comment.context === popup.dataset.context) && !savedReadIds.has(comment.id)).map(comment => comment.id);
  if (!ids.length) return;
  savingReads = true;
  try {
   for (let offset = 0; offset < ids.length; offset += 200) {
    const batch = ids.slice(offset, offset + 200);
    const response = await fetch(popup.dataset.readUrl, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': popup.dataset.readToken }, body: JSON.stringify({ ids: batch }), signal: AbortSignal.timeout(10000) });
    if (!response.ok) break;
    batch.forEach(id => savedReadIds.add(id));
   }
  } catch { /* Retry on the next discussion refresh. */ }
  finally { savingReads = false; }
 }

 function setOpen(open, restoreFocus = true) {
  if (open && popup.hidden) returnFocus = document.activeElement;
  popup.hidden = !open;
  toggle.setAttribute('aria-expanded', String(open));
  if (open) {
   markRead();
   close.focus({ preventScroll: true });
   refreshComments();
  } else if (restoreFocus) {
   (returnFocus?.isConnected ? returnFocus : toggle).focus({ preventScroll: true });
  }
  updateBadge();
 }

 async function refreshComments() {
  if (refreshing || document.hidden) return;
  refreshing = true;
  refreshButton.disabled = true;
  try {
   const url = new URL(popup.dataset.refreshUrl, window.location.origin);
   url.searchParams.set('context', popup.dataset.context);
   const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store', signal: AbortSignal.timeout(10000) });
   if (!response.ok) throw new Error('Comment refresh failed');
   const result = await response.json();
   if (!Array.isArray(result.comments) || typeof result.html !== 'string') throw new Error('Invalid comment response');
   comments = result.comments;
   const changed = JSON.stringify(renderedComments) !== JSON.stringify(comments);
   const replyInProgress = threadContainer.contains(document.activeElement) || [...threadContainer.querySelectorAll('textarea')].some(input => input.value.trim() !== '') || Boolean(threadContainer.querySelector('details[open]'));
   if (changed && !replyInProgress) {
    threadContainer.innerHTML = result.html;
    renderedComments = comments;
   }
   status.textContent = changed && replyInProgress ? 'New messages available. Finish your reply to refresh.' : 'Up to date';
   markRead();
   updateBadge();
  } catch {
   status.textContent = 'Unable to check for new messages. Try Refresh.';
  } finally {
   refreshing = false;
   refreshButton.disabled = false;
  }
 }

 toggle.addEventListener('click', () => setOpen(popup.hidden));
 close.addEventListener('click', () => setOpen(false));
 refreshButton.addEventListener('click', refreshComments);
 document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && !popup.hidden) {
   event.preventDefault();
   setOpen(false);
  }
 });
 document.addEventListener('pointerdown', event => {
  if (!popup.hidden && !popup.contains(event.target) && !toggle.contains(event.target)) setOpen(false, false);
 });
 document.addEventListener('visibilitychange', () => {
  if (!document.hidden) refreshComments();
 });
 window.addEventListener('focus', refreshComments);
 window.addEventListener('storage', event => {
  if (event.key === storageKey) {
   readMarkers = loadReadMarkers();
   updateBadge();
  }
 });
 const pollInterval = window.setInterval(refreshComments, 15000);
 window.addEventListener('pagehide', () => window.clearInterval(pollInterval));
 window.addEventListener('hashchange', () => {
  if (window.location.hash === '#project-comments') setOpen(true);
 });
 updateBadge();
 if (window.location.hash === '#project-comments' || popup.dataset.initialOpen === 'true') setOpen(true);
 else refreshComments();
}
