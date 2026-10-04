const chat = document.querySelector('[data-demo-project-chat]');
if (chat) {
 const dialog = chat.querySelector('dialog');
 const launcher = chat.querySelector('[data-chat-launch]');
 const list = chat.querySelector('[data-chat-messages]');
 const input = chat.querySelector('textarea');
 const context = chat.querySelector('[data-chat-reply-context]');
 const key = 'ids-demo-chat:' + chat.dataset.projectKey;
 let replyingTo = null;
 let messages = [
  { id: 'initial', author: chat.dataset.projectOfficer, body: 'Project update shared for review.', parent: null },
  { id: 'initial-reply', author: 'Section Officer', body: 'Received. We will discuss progress at the next review meeting.', parent: 'initial' },
 ];
 try { const saved = JSON.parse(localStorage.getItem(key)); if (Array.isArray(saved) && saved.every(m => typeof m.id === 'string' && typeof m.author === 'string' && typeof m.body === 'string' && (m.parent === null || typeof m.parent === 'string'))) messages = saved; } catch {}
 function render() {
  list.replaceChildren();
  for (const message of messages) {
   const article = document.createElement('article');
   article.className = 'detail-chat-message' + (message.parent ? ' is-reply' : '');
   const author = document.createElement('strong'); author.textContent = message.author;
   const body = document.createElement('p'); body.textContent = message.body;
   if (message.parent) {
    const label = document.createElement('small');
    label.textContent = 'Reply to ' + (messages.find(m => m.id === message.parent)?.author || 'comment');
    article.append(label);
   }
   const reply = document.createElement('button'); reply.type = 'button'; reply.textContent = 'Reply';
   reply.addEventListener('click', () => { replyingTo = message.id; context.hidden = false; context.querySelector('span').textContent = 'Replying to ' + message.author; input.focus(); });
   article.append(author, body, reply); list.append(article);
  }
  chat.querySelector('[data-chat-count]').textContent = messages.length > 99 ? '99+' : String(messages.length);
 }
 function cancelReply() { replyingTo = null; context.hidden = true; }
 function openChat() { if (!dialog.open) dialog.showModal(); input.focus(); }
 launcher.addEventListener('click', openChat);
 chat.querySelector('[data-chat-close]').addEventListener('click', () => dialog.close());
 dialog.addEventListener('close', () => launcher.focus());
 dialog.addEventListener('click', event => { if (event.target === dialog) { const rect = dialog.getBoundingClientRect(); if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close(); } });
 chat.querySelector('[data-chat-cancel]').addEventListener('click', cancelReply);
 chat.querySelector('form').addEventListener('submit', event => {
  event.preventDefault(); const body = input.value.trim(); if (!body) return;
  messages.push({ id: crypto.randomUUID(), author: 'Demo user', body, parent: replyingTo });
  try { localStorage.setItem(key, JSON.stringify(messages)); } catch {}
  input.value = ''; cancelReply(); render(); list.scrollTop = list.scrollHeight; input.focus();
 });
 render();
 if (location.hash === '#project-discussion') openChat();
 window.addEventListener('hashchange', () => { if (location.hash === '#project-discussion') openChat(); });
}
