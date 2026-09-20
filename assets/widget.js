/*!
 * Chatbot widget. Loaded by embed.php, which defines window.ChatbotWidgetConfig
 * with the site key, endpoint and design settings before this file runs.
 */
(function () {
  'use strict';

  var config = window.ChatbotWidgetConfig;
  if (!config || !config.key || document.getElementById('chatbot-widget-root')) {
    return;
  }

  var design = config.design || {};
  var storageKey = 'chatbot:' + config.key;
  var FONTS = {
    system: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
    inter: 'Inter, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
    georgia: 'Georgia, "Times New Roman", serif',
    mono: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace'
  };
  var ICONS = {
    chat: '<path d="M12 3C6.99 3 3 6.36 3 10.5c0 2.3 1.23 4.35 3.17 5.72-.13 1.2-.6 2.3-1.4 3.2-.24.27-.06.7.3.66 1.9-.2 3.5-.9 4.72-1.8.7.13 1.44.2 2.21.2 5.01 0 9-3.36 9-7.5S17.01 3 12 3Z"/>',
    question: '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm.1 15.5a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4Zm1.7-5.7c-.7.5-.9.8-.9 1.4v.3h-1.9v-.4c0-1.2.5-1.9 1.4-2.5.8-.6 1.1-.9 1.1-1.5 0-.7-.5-1.1-1.3-1.1-.8 0-1.4.5-1.5 1.4H8.8C8.9 7.6 10.2 6.5 12.2 6.5c2 0 3.3 1.1 3.3 2.7 0 1.1-.5 1.8-1.7 2.6Z"/>',
    sparkle: '<path d="M12 2.5 13.9 8l5.6 1.9-5.6 2L12 17.5 10.1 11.9 4.5 9.9 10.1 8 12 2.5ZM19 15l.9 2.6 2.6.9-2.6.9-.9 2.6-.9-2.6-2.6-.9 2.6-.9L19 15Z"/>',
    support: '<path d="M12 2a8 8 0 0 0-8 8v5a3 3 0 0 0 3 3h1v-7H6v-1a6 6 0 1 1 12 0v1h-2v7h1a3 3 0 0 0 3-3v-5a8 8 0 0 0-8-8Z"/>'
  };

  function readStore() {
    try {
      return JSON.parse(localStorage.getItem(storageKey) || '{}') || {};
    } catch (e) {
      return {};
    }
  }

  function writeStore(data) {
    try {
      localStorage.setItem(storageKey, JSON.stringify(data));
    } catch (e) { /* private mode - stay session-only */ }
  }

  var store = readStore();
  if (!store.visitorId) {
    store.visitorId = 'v_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
    writeStore(store);
  }

  var dark = design.theme === 'dark';
  var surface = dark ? '#111827' : (design.background || '#ffffff');
  var textColor = dark ? '#e5e7eb' : '#0f172a';
  var mutedColor = dark ? '#9ca3af' : '#64748b';
  var borderColor = dark ? '#1f2937' : '#e2e8f0';
  var agentBubble = dark ? '#1f2937' : (design.agent_bubble || '#f1f5f9');
  var userBubble = design.user_bubble || design.primary_color || '#4f46e5';
  var side = design.position === 'left' ? 'left' : 'right';

  var host = document.createElement('div');
  host.id = 'chatbot-widget-root';
  host.style.cssText = 'position:fixed;z-index:2147483000;' + side + ':0;bottom:0;width:0;height:0;';
  document.body.appendChild(host);
  var root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;

  var style = document.createElement('style');
  style.textContent = [
    ':host,*{box-sizing:border-box}',
    '.launcher{position:fixed;' + side + ':' + (design.offset_x || 20) + 'px;bottom:' + (design.offset_y || 20) + 'px;',
    'display:flex;align-items:center;gap:8px;border:0;cursor:pointer;border-radius:999px;padding:0 18px;height:56px;',
    'background:' + (design.bubble_color || design.primary_color || '#4f46e5') + ';color:' + (design.text_on_primary || '#fff') + ';',
    'font:600 15px/1 ' + (FONTS[design.font] || FONTS.system) + ';box-shadow:0 10px 25px rgba(15,23,42,.25);transition:transform .15s ease}',
    '.launcher:hover{transform:translateY(-2px)}',
    '.launcher svg{width:26px;height:26px;fill:currentColor;flex:none}',
    '.launcher.icon-only{padding:0;width:56px;justify-content:center}',
    '.panel{position:fixed;' + side + ':' + (design.offset_x || 20) + 'px;bottom:' + ((design.offset_y || 20) + 72) + 'px;',
    'width:380px;max-width:calc(100vw - 32px);height:560px;max-height:calc(100vh - 120px);display:none;flex-direction:column;',
    'background:' + surface + ';color:' + textColor + ';border:1px solid ' + borderColor + ';border-radius:' + (design.radius || 16) + 'px;',
    'overflow:hidden;box-shadow:0 24px 60px rgba(15,23,42,.28);font:400 15px/1.5 ' + (FONTS[design.font] || FONTS.system) + '}',
    '.panel.open{display:flex}',
    '.header{display:flex;align-items:center;gap:12px;padding:14px 16px;background:' + (design.primary_color || '#4f46e5') + ';color:' + (design.text_on_primary || '#fff') + '}',
    '.header .avatar{width:38px;height:38px;border-radius:50%;object-fit:cover;background:rgba(255,255,255,.2);flex:none}',
    '.header .meta{flex:1;min-width:0}',
    '.header .title{font-weight:700;font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}',
    '.header .subtitle{font-size:12px;opacity:.85;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}',
    '.header button{background:transparent;border:0;color:inherit;cursor:pointer;padding:6px;border-radius:8px;line-height:0}',
    '.header button:hover{background:rgba(255,255,255,.18)}',
    '.header svg{width:18px;height:18px;fill:currentColor}',
    '.messages{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px}',
    '.msg{max-width:85%;padding:10px 14px;border-radius:14px;white-space:pre-wrap;word-wrap:break-word;font-size:14.5px}',
    '.msg.bot{background:' + agentBubble + ';color:' + textColor + ';border-bottom-left-radius:4px;align-self:flex-start}',
    '.msg.user{background:' + userBubble + ';color:' + (design.text_on_primary || '#fff') + ';border-bottom-right-radius:4px;align-self:flex-end}',
    '.msg a{color:inherit;text-decoration:underline}',
    '.suggestions{display:flex;flex-wrap:wrap;gap:8px;padding:0 16px 8px}',
    '.suggestions button{border:1px solid ' + borderColor + ';background:transparent;color:' + textColor + ';border-radius:999px;',
    'padding:7px 12px;font-size:13px;cursor:pointer}',
    '.suggestions button:hover{border-color:' + (design.primary_color || '#4f46e5') + '}',
    '.typing{display:flex;gap:4px;align-self:flex-start;background:' + agentBubble + ';padding:12px 14px;border-radius:14px}',
    '.typing i{width:7px;height:7px;border-radius:50%;background:' + mutedColor + ';display:block;animation:bounce 1.2s infinite}',
    '.typing i:nth-child(2){animation-delay:.15s}.typing i:nth-child(3){animation-delay:.3s}',
    '@keyframes bounce{0%,60%,100%{opacity:.35;transform:translateY(0)}30%{opacity:1;transform:translateY(-4px)}}',
    '.composer{display:flex;gap:8px;padding:12px;border-top:1px solid ' + borderColor + '}',
    '.composer textarea{flex:1;resize:none;max-height:110px;border:1px solid ' + borderColor + ';border-radius:12px;padding:10px 12px;',
    'font:inherit;font-size:14.5px;background:transparent;color:' + textColor + ';outline:none}',
    '.composer textarea:focus{border-color:' + (design.primary_color || '#4f46e5') + '}',
    '.composer button{border:0;border-radius:12px;width:44px;cursor:pointer;background:' + (design.primary_color || '#4f46e5') + ';color:' + (design.text_on_primary || '#fff') + '}',
    '.composer button:disabled{opacity:.5;cursor:not-allowed}',
    '.composer svg{width:20px;height:20px;fill:currentColor}',
    '.branding{text-align:center;font-size:11px;color:' + mutedColor + ';padding:0 0 10px}',
    '.branding a{color:inherit}',
    '@media (max-width:480px){.panel{' + side + ':8px;bottom:8px;width:calc(100vw - 16px);height:calc(100vh - 16px);max-height:none}}'
  ].join('');
  root.appendChild(style);

  function el(tag, className, html) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (html !== undefined) node.innerHTML = html;
    return node;
  }

  var launcher = el('button', 'launcher' + (design.launcher_label ? '' : ' icon-only'));
  launcher.type = 'button';
  launcher.setAttribute('aria-label', design.title || 'Open chat');
  launcher.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true">' + (ICONS[design.launcher_icon] || ICONS.chat) + '</svg>' +
    (design.launcher_label ? '<span></span>' : '');
  if (design.launcher_label) {
    launcher.querySelector('span').textContent = design.launcher_label;
  }

  var panel = el('div', 'panel');
  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-label', design.title || 'Chat');

  var header = el('div', 'header');
  if (design.avatar_url) {
    var avatar = document.createElement('img');
    avatar.className = 'avatar';
    avatar.src = design.avatar_url;
    avatar.alt = '';
    header.appendChild(avatar);
  }
  var meta = el('div', 'meta');
  var titleNode = el('div', 'title');
  titleNode.textContent = design.title || config.name || 'Chat with us';
  meta.appendChild(titleNode);
  if (design.subtitle) {
    var subtitleNode = el('div', 'subtitle');
    subtitleNode.textContent = design.subtitle;
    meta.appendChild(subtitleNode);
  }
  header.appendChild(meta);

  var closeBtn = el('button', null, '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18.3 5.7 12 12l6.3 6.3-1.4 1.4L10.6 13.4 4.3 19.7 2.9 18.3 9.2 12 2.9 5.7 4.3 4.3l6.3 6.3 6.3-6.3z"/></svg>');
  closeBtn.type = 'button';
  closeBtn.setAttribute('aria-label', 'Close chat');
  header.appendChild(closeBtn);

  var messages = el('div', 'messages');
  messages.setAttribute('aria-live', 'polite');
  var suggestions = el('div', 'suggestions');

  var composer = el('div', 'composer');
  var input = document.createElement('textarea');
  input.rows = 1;
  input.placeholder = design.placeholder || 'Type your message…';
  input.setAttribute('aria-label', 'Message');
  var sendBtn = el('button', null, '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.4 20.4 21 12 3.4 3.6 3.4 10.2 15 12 3.4 13.8z"/></svg>');
  sendBtn.type = 'button';
  sendBtn.setAttribute('aria-label', 'Send message');
  composer.appendChild(input);
  composer.appendChild(sendBtn);

  panel.appendChild(header);
  panel.appendChild(messages);
  panel.appendChild(suggestions);
  panel.appendChild(composer);
  if (Number(design.show_branding) === 1) {
    panel.appendChild(el('div', 'branding', 'Powered by AI'));
  }
  root.appendChild(launcher);
  root.appendChild(panel);

  function linkify(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
  }

  function addMessage(role, text) {
    var node = el('div', 'msg ' + (role === 'user' ? 'user' : 'bot'));
    node.innerHTML = linkify(text);
    messages.appendChild(node);
    messages.scrollTop = messages.scrollHeight;
    return node;
  }

  function renderSuggestions() {
    suggestions.innerHTML = '';
    var list = design.suggestions || [];
    if (!list.length || history.length > 1) {
      return;
    }
    list.forEach(function (text) {
      var chip = document.createElement('button');
      chip.type = 'button';
      chip.textContent = text;
      chip.addEventListener('click', function () {
        send(text);
      });
      suggestions.appendChild(chip);
    });
  }

  var history = store.history || [];
  var busy = false;

  function persist() {
    store.history = history.slice(-40);
    store.conversationId = conversationId;
    writeStore(store);
  }

  var conversationId = store.conversationId || 0;

  if (history.length) {
    history.forEach(function (item) {
      addMessage(item.role, item.text);
    });
  } else if (design.welcome_message) {
    addMessage('bot', design.welcome_message);
    history.push({ role: 'bot', text: design.welcome_message });
  }
  renderSuggestions();

  function setBusy(state) {
    busy = state;
    sendBtn.disabled = state;
  }

  function send(text) {
    text = (text || '').trim();
    if (!text || busy) {
      return;
    }
    addMessage('user', text);
    history.push({ role: 'user', text: text });
    suggestions.innerHTML = '';
    input.value = '';
    input.style.height = 'auto';
    setBusy(true);

    var typing = el('div', 'typing', '<i></i><i></i><i></i>');
    messages.appendChild(typing);
    messages.scrollTop = messages.scrollHeight;

    fetch(config.endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        key: config.key,
        message: text,
        conversation_id: conversationId,
        visitor_id: store.visitorId,
        page_url: location.href,
        referrer: document.referrer
      })
    }).then(function (response) {
      return response.json().then(function (data) {
        return { ok: response.ok, data: data };
      });
    }).then(function (result) {
      typing.remove();
      var reply = (result.data && (result.data.reply || result.data.error)) || 'Something went wrong. Please try again.';
      conversationId = (result.data && result.data.conversation_id) || conversationId;
      addMessage('bot', reply);
      history.push({ role: 'bot', text: reply });
      persist();
    }).catch(function () {
      typing.remove();
      addMessage('bot', 'I could not reach the server. Please try again in a moment.');
    }).then(function () {
      setBusy(false);
      input.focus();
    });
  }

  function toggle(open) {
    var isOpen = open === undefined ? !panel.classList.contains('open') : open;
    panel.classList.toggle('open', isOpen);
    launcher.style.display = isOpen ? 'none' : 'flex';
    if (isOpen) {
      input.focus();
      messages.scrollTop = messages.scrollHeight;
    }
  }

  launcher.addEventListener('click', function () { toggle(true); });
  closeBtn.addEventListener('click', function () { toggle(false); });
  sendBtn.addEventListener('click', function () { send(input.value); });
  input.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      send(input.value);
    }
  });
  input.addEventListener('input', function () {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 110) + 'px';
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && panel.classList.contains('open')) {
      toggle(false);
    }
  });

  if (Number(design.auto_open) === 1 && !store.opened) {
    setTimeout(function () {
      store.opened = true;
      writeStore(store);
      toggle(true);
    }, Math.max(0, Number(design.auto_open_delay || 5)) * 1000);
  }

  window.Chatbot = {
    open: function () { toggle(true); },
    close: function () { toggle(false); },
    send: send,
    reset: function () {
      history = [];
      conversationId = 0;
      messages.innerHTML = '';
      store = { visitorId: store.visitorId };
      writeStore(store);
      if (design.welcome_message) addMessage('bot', design.welcome_message);
      renderSuggestions();
    }
  };
})();
