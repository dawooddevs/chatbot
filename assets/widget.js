/*!
 * Chatbot widget. Loaded by embed.php, which defines window.ChatbotWidgetConfig
 * with the site key, endpoints and design settings before this file runs.
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
    support: '<path d="M12 2a8 8 0 0 0-8 8v5a3 3 0 0 0 3 3h1v-7H6v-1a6 6 0 1 1 12 0v1h-2v7h1a3 3 0 0 0 3-3v-5a8 8 0 0 0-8-8Z"/>',
    close: '<path d="M18.3 5.7 12 12l6.3 6.3-1.4 1.4L10.6 13.4 4.3 19.7 2.9 18.3 9.2 12 2.9 5.7 4.3 4.3l6.3 6.3 6.3-6.3z"/>',
    reset: '<path d="M12 5V2L7 6l5 4V7a5 5 0 1 1-5 5H5a7 7 0 1 0 7-7Z"/>',
    send: '<path d="M12 4l7 7-1.4 1.4L13 7.8V20h-2V7.8L6.4 12.4 5 11l7-7Z"/>',
    clip: '<path d="M16.5 6.5v8.8a4.5 4.5 0 0 1-9 0V6a3 3 0 1 1 6 0v9a1.5 1.5 0 0 1-3 0V6.5H9V15a3 3 0 0 0 6 0V6a4.5 4.5 0 1 0-9 0v9.3a6 6 0 0 0 12 0V6.5h-1.5Z"/>',
    mic: '<path d="M12 14a3 3 0 0 0 3-3V6a3 3 0 0 0-6 0v5a3 3 0 0 0 3 3Zm5-3a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.9V21h2v-3.1A7 7 0 0 0 19 11h-2Z"/>',
    stop: '<rect x="7" y="7" width="10" height="10" rx="2"/>',
    file: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm0 2.5L17.5 8H14V4.5Z"/>',
    user: '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0 2c-3.3 0-8 1.7-8 4.5V21h16v-2.5c0-2.8-4.7-4.5-8-4.5Z"/>'
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
  var primary = design.primary_color || '#4f46e5';
  var onPrimary = design.text_on_primary || '#ffffff';
  var surface = dark ? '#111827' : (design.background || '#ffffff');
  var textColor = dark ? '#e5e7eb' : '#0f172a';
  var mutedColor = dark ? '#9ca3af' : '#64748b';
  var borderColor = dark ? '#1f2937' : '#e2e8f0';
  var agentBubble = dark ? '#1f2937' : (design.agent_bubble || '#f1f5f9');
  var userBubble = design.user_bubble || primary;
  var side = design.position === 'left' ? 'left' : 'right';
  var offsetX = Number(design.offset_x || 20);
  var offsetY = Number(design.offset_y || 20);
  var radius = Number(design.radius || 16);
  var font = FONTS[design.font] || FONTS.system;
  var launcherRadius = Math.max(8, Math.min(27, Number(design.launcher_radius === undefined ? 26 : design.launcher_radius)));

  var host = document.createElement('div');
  host.id = 'chatbot-widget-root';
  host.style.cssText = 'position:fixed;z-index:2147483000;' + side + ':0;bottom:0;width:0;height:0;';
  document.body.appendChild(host);
  var root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;

  var style = document.createElement('style');
  style.textContent = [
    ':host,*{box-sizing:border-box}',
    'button{font:inherit}',

    /* launcher */
    '.launcher{position:fixed;' + side + ':' + offsetX + 'px;bottom:' + offsetY + 'px;display:flex;align-items:center;gap:9px;',
    'border:0;cursor:pointer;border-radius:' + launcherRadius + 'px;height:54px;padding:0 24px;background:' + (design.bubble_color || primary) + ';',
    'color:' + onPrimary + ';font:600 15px/1 ' + font + ';box-shadow:0 8px 22px rgba(15,23,42,.22);',
    'transition:transform .15s ease,box-shadow .15s ease}',
    '.launcher:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(15,23,42,.28)}',
    '.launcher svg{width:22px;height:22px;fill:currentColor;flex:none}',
    '.launcher.icon-only{padding:0;width:54px;justify-content:center}',
    '.launcher .badge{position:absolute;top:-5px;' + (side === 'left' ? 'left' : 'right') + ':-5px;min-width:23px;height:23px;',
    'padding:0 6px;border-radius:999px;background:' + (design.badge_color || '#ef4444') + ';color:#fff;',
    'font:700 12px/23px ' + font + ';text-align:center;box-shadow:0 0 0 2px rgba(255,255,255,.92)}',

    /* panel - anchored at the launcher, opening upwards from it */
    '.panel{position:fixed;' + side + ':' + offsetX + 'px;bottom:' + offsetY + 'px;width:384px;max-width:calc(100vw - 24px);',
    'height:min(620px,calc(100vh - ' + (offsetY + 24) + 'px));display:none;flex-direction:column;background:' + surface + ';',
    'color:' + textColor + ';border-radius:' + radius + 'px;overflow:hidden;box-shadow:0 18px 50px rgba(15,23,42,.24),0 2px 8px rgba(15,23,42,.08);',
    'font:400 15px/1.5 ' + font + ';opacity:0;transform:translateY(12px) scale(.98);transition:opacity .16s ease,transform .16s ease}',
    '.panel.open{display:flex}',
    '.panel.shown{opacity:1;transform:none}',

    /* header */
    '.header{display:flex;align-items:center;gap:12px;padding:16px 16px;background:' + primary + ';color:' + onPrimary + '}',
    '.header .avatar{width:40px;height:40px;border-radius:50%;object-fit:cover;flex:none;background:rgba(255,255,255,.18);',
    'display:flex;align-items:center;justify-content:center}',
    '.header .avatar svg{width:24px;height:24px;fill:currentColor;opacity:.9}',
    '.header .meta{flex:1;min-width:0}',
    '.header .title{font-weight:700;font-size:17px;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}',
    '.header .status{display:flex;align-items:center;gap:6px;font-size:12.5px;opacity:.92;white-space:nowrap;overflow:hidden}',
    '.header .status .dot{width:8px;height:8px;border-radius:50%;background:#4ade80;flex:none}',
    '.icon-btn{width:36px;height:36px;border-radius:50%;border:0;cursor:pointer;background:rgba(255,255,255,.16);color:inherit;',
    'display:flex;align-items:center;justify-content:center;flex:none;transition:background .15s ease}',
    '.icon-btn:hover{background:rgba(255,255,255,.3)}',
    '.icon-btn svg{width:17px;height:17px;fill:currentColor}',

    /* messages */
    '.messages{flex:1;overflow-y:auto;padding:18px 16px 6px;display:flex;flex-direction:column;gap:10px}',
    '.msg{max-width:86%;padding:11px 15px;border-radius:16px;white-space:pre-wrap;word-wrap:break-word;font-size:14.5px}',
    '.msg.bot{background:' + agentBubble + ';color:' + textColor + ';border-bottom-left-radius:6px;align-self:flex-start}',
    '.msg.user{background:' + userBubble + ';color:' + onPrimary + ';border-bottom-right-radius:6px;align-self:flex-end}',
    '.msg a{color:inherit;text-decoration:underline}',
    '.msg .file{display:flex;align-items:center;gap:8px;font-size:13px;opacity:.95;margin-top:6px}',
    '.msg .file svg{width:15px;height:15px;fill:currentColor;flex:none}',
    '.msg img.shot{display:block;max-width:190px;border-radius:10px;margin-top:8px}',

    /* suggestions */
    '.suggestions{display:flex;flex-wrap:wrap;gap:8px;margin:-2px 0 4px;align-self:flex-start;max-width:96%}',
    '.suggestions button{border:1px solid ' + borderColor + ';background:transparent;color:' + textColor + ';border-radius:999px;',
    'padding:9px 15px;font-size:13.5px;cursor:pointer;transition:border-color .15s ease,background .15s ease}',
    '.suggestions button:hover{border-color:' + primary + ';background:rgba(0,0,0,.02)}',

    /* typing */
    '.typing{display:flex;gap:4px;align-self:flex-start;background:' + agentBubble + ';padding:13px 15px;border-radius:16px}',
    '.typing i{width:7px;height:7px;border-radius:50%;background:' + mutedColor + ';display:block;animation:bounce 1.2s infinite}',
    '.typing i:nth-child(2){animation-delay:.15s}.typing i:nth-child(3){animation-delay:.3s}',
    '@keyframes bounce{0%,60%,100%{opacity:.35;transform:translateY(0)}30%{opacity:1;transform:translateY(-4px)}}',

    /* composer */
    '.composer-wrap{padding:10px 14px 12px}',
    '.chip{display:flex;align-items:center;gap:8px;margin-bottom:8px;padding:7px 10px;border:1px solid ' + borderColor + ';',
    'border-radius:10px;font-size:13px;color:' + textColor + ';background:' + (dark ? '#0b1220' : '#f8fafc') + '}',
    '.chip svg{width:15px;height:15px;fill:' + mutedColor + ';flex:none}',
    '.chip .name{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}',
    '.chip button{border:0;background:transparent;color:' + mutedColor + ';cursor:pointer;line-height:0;padding:2px}',
    '.chip button svg{width:12px;height:12px;fill:currentColor}',
    '.composer{border:1px solid ' + borderColor + ';border-radius:14px;background:' + surface + ';padding:10px 10px 8px;',
    'box-shadow:0 1px 2px rgba(15,23,42,.05),0 6px 16px rgba(15,23,42,.06);transition:border-color .15s ease,box-shadow .15s ease}',
    '.composer.focus{border-color:' + primary + ';box-shadow:0 0 0 3px ' + hexToRgba(primary, .14) + '}',
    '.composer textarea{width:100%;border:0;outline:none;resize:none;background:transparent;color:' + textColor + ';',
    'font:inherit;font-size:14.5px;line-height:1.45;max-height:120px;padding:4px 4px 2px}',
    '.composer textarea::placeholder{color:' + mutedColor + '}',
    '.tools{display:flex;align-items:center;gap:0;padding-top:6px}',
    '.tool{width:30px;height:30px;border-radius:50%;border:0;background:transparent;color:' + mutedColor + ';cursor:pointer;',
    'display:flex;align-items:center;justify-content:center;transition:background .15s ease,color .15s ease}',
    '.tool:hover{background:' + (dark ? 'rgba(255,255,255,.08)' : 'rgba(15,23,42,.06)') + ';color:' + textColor + '}',
    '.tool svg{width:18px;height:18px;fill:currentColor}',
    '.tool.recording{background:#fee2e2;color:#dc2626}',
    '.rec{flex:1;font-size:12.5px;color:#dc2626;display:none;align-items:center;gap:6px}',
    '.rec.on{display:flex}',
    '.rec .blink{width:8px;height:8px;border-radius:50%;background:#dc2626;animation:blink 1s infinite}',
    '@keyframes blink{50%{opacity:.25}}',
    '.spacer{flex:1}',
    '.send{width:38px;height:38px;border-radius:50%;border:0;cursor:pointer;background:' + primary + ';color:' + onPrimary + ';',
    'display:flex;align-items:center;justify-content:center;flex:none;transition:opacity .15s ease,transform .15s ease}',
    '.send:hover{transform:translateY(-1px)}',
    '.send:disabled{opacity:.45;cursor:not-allowed;transform:none}',
    '.send svg{width:20px;height:20px;fill:currentColor}',
    '.branding{text-align:center;font-size:11px;color:' + mutedColor + ';padding-top:8px}',
    '.error{font-size:12.5px;color:#dc2626;padding:6px 2px 0}',

    '@media (max-width:480px){.panel{' + side + ':8px;bottom:8px;width:calc(100vw - 16px);height:calc(100vh - 16px)}}'
  ].join('');
  root.appendChild(style);

  function hexToRgba(hex, alpha) {
    var value = String(hex).replace('#', '');
    if (value.length !== 6) {
      return 'rgba(79,70,229,' + alpha + ')';
    }
    return 'rgba(' + parseInt(value.slice(0, 2), 16) + ',' + parseInt(value.slice(2, 4), 16) + ',' +
      parseInt(value.slice(4, 6), 16) + ',' + alpha + ')';
  }

  function el(tag, className, html) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (html !== undefined) node.innerHTML = html;
    return node;
  }

  function svg(name) {
    return '<svg viewBox="0 0 24 24" aria-hidden="true">' + (ICONS[name] || '') + '</svg>';
  }

  /* ---------- launcher ---------- */
  var launcher = el('button', 'launcher' + (design.launcher_label ? '' : ' icon-only'));
  launcher.type = 'button';
  launcher.setAttribute('aria-label', design.title || 'Open chat');
  launcher.innerHTML = svg(design.launcher_icon || 'chat') + (design.launcher_label ? '<span class="label"></span>' : '');
  if (design.launcher_label) {
    launcher.querySelector('.label').textContent = design.launcher_label;
  }

  // Unread badge, shown until this visitor opens the chat for the first time.
  var badgeCount = Math.max(0, Math.min(99, Number(design.launcher_badge || 0)));
  var badge = null;
  if (badgeCount > 0) {
    badge = el('span', 'badge');
    badge.textContent = String(badgeCount);
    launcher.appendChild(badge);
  }

  function hideBadge() {
    if (badge && badge.parentNode) {
      badge.parentNode.removeChild(badge);
      badge = null;
    }
  }

  /* ---------- panel ---------- */
  var panel = el('div', 'panel');
  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-label', design.title || 'Chat');

  var header = el('div', 'header');
  var avatar;
  if (design.avatar_url) {
    avatar = document.createElement('img');
    avatar.className = 'avatar';
    avatar.src = design.avatar_url;
    avatar.alt = '';
  } else {
    avatar = el('div', 'avatar', svg('user'));
  }
  header.appendChild(avatar);

  var meta = el('div', 'meta');
  var titleNode = el('div', 'title');
  titleNode.textContent = design.title || config.name || 'Chat with us';
  meta.appendChild(titleNode);
  if (design.subtitle) {
    var status = el('div', 'status', Number(design.show_status_dot) === 1 ? '<i class="dot"></i>' : '');
    var statusText = document.createElement('span');
    statusText.textContent = design.subtitle;
    status.appendChild(statusText);
    meta.appendChild(status);
  }
  header.appendChild(meta);

  var resetBtn = null;
  if (Number(design.show_reset) === 1) {
    resetBtn = el('button', 'icon-btn', svg('reset'));
    resetBtn.type = 'button';
    resetBtn.title = 'Start a new chat';
    resetBtn.setAttribute('aria-label', 'Start a new chat');
    header.appendChild(resetBtn);
  }
  var closeBtn = el('button', 'icon-btn', svg('close'));
  closeBtn.type = 'button';
  closeBtn.title = 'Close';
  closeBtn.setAttribute('aria-label', 'Close chat');
  header.appendChild(closeBtn);

  var messages = el('div', 'messages');
  messages.setAttribute('aria-live', 'polite');
  var suggestions = el('div', 'suggestions');

  var composerWrap = el('div', 'composer-wrap');
  var chip = null;
  var composer = el('div', 'composer');
  var input = document.createElement('textarea');
  input.rows = 1;
  input.placeholder = design.placeholder || 'Ask a question…';
  input.setAttribute('aria-label', 'Message');

  var tools = el('div', 'tools');
  var fileInput = null;
  var attachBtn = null;
  if (Number(design.show_attachments) === 1) {
    attachBtn = el('button', 'tool', svg('clip'));
    attachBtn.type = 'button';
    attachBtn.title = 'Attach a file';
    attachBtn.setAttribute('aria-label', 'Attach a file');
    fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.style.display = 'none';
    fileInput.accept = '.png,.jpg,.jpeg,.gif,.webp,.pdf,.txt,.md,.csv,.json';
    tools.appendChild(attachBtn);
  }

  var micBtn = null;
  var recStatus = el('div', 'rec', '<i class="blink"></i><span>Recording…</span>');
  if (Number(design.show_voice) === 1 && navigator.mediaDevices && window.MediaRecorder) {
    micBtn = el('button', 'tool', svg('mic'));
    micBtn.type = 'button';
    micBtn.title = 'Record a voice message';
    micBtn.setAttribute('aria-label', 'Record a voice message');
    tools.appendChild(micBtn);
  }
  tools.appendChild(recStatus);
  tools.appendChild(el('div', 'spacer'));

  var sendBtn = el('button', 'send', svg('send'));
  sendBtn.type = 'button';
  sendBtn.setAttribute('aria-label', 'Send message');
  tools.appendChild(sendBtn);

  composer.appendChild(input);
  composer.appendChild(tools);
  composerWrap.appendChild(composer);
  var errorLine = el('div', 'error');
  errorLine.style.display = 'none';
  composerWrap.appendChild(errorLine);
  if (Number(design.show_branding) === 1) {
    composerWrap.appendChild(el('div', 'branding', 'Powered by AI'));
  }

  panel.appendChild(header);
  panel.appendChild(messages);
  panel.appendChild(composerWrap);
  root.appendChild(launcher);
  root.appendChild(panel);
  if (fileInput) {
    root.appendChild(fileInput);
  }

  /* ---------- messages ---------- */
  function linkify(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
  }

  function addMessage(role, text, file) {
    var node = el('div', 'msg ' + (role === 'user' ? 'user' : 'bot'));
    node.innerHTML = text ? linkify(text) : '';
    if (file && file.name) {
      if (file.preview) {
        var image = document.createElement('img');
        image.className = 'shot';
        image.src = file.preview;
        image.alt = file.name;
        node.appendChild(image);
      }
      var line = el('div', 'file', svg('file'));
      var label = document.createElement('span');
      label.textContent = file.name;
      line.appendChild(label);
      node.appendChild(line);
    }
    messages.appendChild(node);
    messages.scrollTop = messages.scrollHeight;
    return node;
  }

  function showError(text) {
    errorLine.textContent = text;
    errorLine.style.display = text ? 'block' : 'none';
    if (text) {
      setTimeout(function () {
        errorLine.style.display = 'none';
      }, 6000);
    }
  }

  var history = store.history || [];
  var conversationId = store.conversationId || 0;
  var pendingAttachment = null;
  var busy = false;

  function persist() {
    store.history = history.slice(-40);
    store.conversationId = conversationId;
    writeStore(store);
  }

  function renderSuggestions() {
    suggestions.innerHTML = '';
    if (suggestions.parentNode) {
      suggestions.parentNode.removeChild(suggestions);
    }
    var list = design.suggestions || [];
    if (!list.length || history.length > 1) {
      return;
    }
    // Sits in the message flow, right under the welcome message.
    messages.appendChild(suggestions);
    list.forEach(function (text) {
      var button = document.createElement('button');
      button.type = 'button';
      button.textContent = text;
      button.addEventListener('click', function () {
        send(text);
      });
      suggestions.appendChild(button);
    });
  }

  if (history.length) {
    history.forEach(function (item) {
      addMessage(item.role, item.text, item.file);
    });
  } else if (design.welcome_message) {
    addMessage('bot', design.welcome_message);
    history.push({ role: 'bot', text: design.welcome_message });
  }
  renderSuggestions();

  function hideSuggestions() {
    suggestions.innerHTML = '';
    if (suggestions.parentNode) {
      suggestions.parentNode.removeChild(suggestions);
    }
  }

  function setBusy(state) {
    busy = state;
    sendBtn.disabled = state;
  }

  /* ---------- attachments ---------- */
  function showChip(file) {
    clearChip();
    chip = el('div', 'chip', svg('file'));
    var name = el('div', 'name');
    name.textContent = file.name;
    var remove = el('button', null, svg('close'));
    remove.type = 'button';
    remove.setAttribute('aria-label', 'Remove file');
    remove.addEventListener('click', function () {
      pendingAttachment = null;
      clearChip();
    });
    chip.appendChild(name);
    chip.appendChild(remove);
    composerWrap.insertBefore(chip, composer);
  }

  function clearChip() {
    if (chip && chip.parentNode) {
      chip.parentNode.removeChild(chip);
    }
    chip = null;
  }

  if (attachBtn) {
    attachBtn.addEventListener('click', function () {
      fileInput.click();
    });
    fileInput.addEventListener('change', function () {
      var file = fileInput.files && fileInput.files[0];
      fileInput.value = '';
      if (!file) {
        return;
      }
      if (file.size > 5 * 1024 * 1024) {
        showError('Files must be 5 MB or smaller.');
        return;
      }
      var body = new FormData();
      body.append('key', config.key);
      body.append('conversation_id', conversationId || 0);
      body.append('file', file);
      attachBtn.disabled = true;
      fetch(config.uploadEndpoint, { method: 'POST', body: body })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data.error) {
            showError(data.error);
            return;
          }
          pendingAttachment = {
            id: data.attachment_id,
            name: data.name,
            preview: file.type.indexOf('image/') === 0 ? URL.createObjectURL(file) : null
          };
          showChip(pendingAttachment);
        })
        .catch(function () { showError('The file could not be uploaded.'); })
        .then(function () { attachBtn.disabled = false; });
    });
  }

  /* ---------- voice ---------- */
  function microphoneMessage(error) {
    if (!window.isSecureContext) {
      return 'Voice needs a secure (https) page. Open this site over https and try again.';
    }
    switch (error && error.name) {
      case 'NotAllowedError':
      case 'PermissionDeniedError':
        return 'Microphone permission was refused. Click the lock icon in the address bar, set Microphone to Allow, reload, then tap the mic again.';
      case 'NotFoundError':
      case 'DevicesNotFoundError':
      case 'OverconstrainedError':
        return 'No microphone was found. Plug one in, or check your system sound settings, then reload the page.';
      case 'NotReadableError':
      case 'TrackStartError':
        return 'The microphone is already in use by another app.';
      case 'SecurityError':
        return 'This page is not allowed to use the microphone.';
      default:
        return 'The microphone could not be started' + (error && error.name ? ' (' + error.name + ')' : '') + '.';
    }
  }

  var recorder = null;
  var chunks = [];

  function stopRecording() {
    if (recorder && recorder.state !== 'inactive') {
      recorder.stop();
    }
  }

  function startRecording() {
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
      chunks = [];
      recorder = new MediaRecorder(stream);
      recorder.addEventListener('dataavailable', function (event) {
        if (event.data && event.data.size) {
          chunks.push(event.data);
        }
      });
      recorder.addEventListener('stop', function () {
        stream.getTracks().forEach(function (track) { track.stop(); });
        micBtn.classList.remove('recording');
        recStatus.classList.remove('on');
        micBtn.innerHTML = svg('mic');
        if (!chunks.length) {
          return;
        }
        var blob = new Blob(chunks, { type: recorder.mimeType || 'audio/webm' });
        var body = new FormData();
        body.append('key', config.key);
        body.append('audio', blob, 'voice.webm');
        micBtn.disabled = true;
        recStatus.querySelector('span').textContent = 'Transcribing…';
        recStatus.classList.add('on');
        fetch(config.transcribeEndpoint, { method: 'POST', body: body })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            if (data.error) {
              showError(data.error);
              return;
            }
            input.value = (input.value ? input.value + ' ' : '') + (data.text || '');
            input.focus();
            resize();
          })
          .catch(function () { showError('The recording could not be sent.'); })
          .then(function () {
            micBtn.disabled = false;
            recStatus.classList.remove('on');
            recStatus.querySelector('span').textContent = 'Recording…';
          });
      });
      recorder.start();
      micBtn.classList.add('recording');
      micBtn.innerHTML = svg('stop');
      recStatus.classList.add('on');
    }).catch(function (error) {
      if (window.console && console.warn) {
        console.warn('[chatbot] microphone unavailable:', error && error.name, error && error.message);
      }
      showError(microphoneMessage(error));
    });
  }

  // A button that cannot possibly work is worse than no button.
  if (micBtn && navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
    navigator.mediaDevices.enumerateDevices().then(function (devices) {
      var hasInput = devices.some(function (device) { return device.kind === 'audioinput'; });
      if (!hasInput && micBtn && micBtn.parentNode) {
        micBtn.parentNode.removeChild(micBtn);
        micBtn = null;
      }
    }).catch(function () { /* keep the button and let the click report the reason */ });
  }

  if (micBtn) {
    micBtn.addEventListener('click', function () {
      if (recorder && recorder.state === 'recording') {
        stopRecording();
      } else {
        startRecording();
      }
    });
  }

  /* ---------- sending ---------- */
  function send(text) {
    text = (text || '').trim();
    if ((!text && !pendingAttachment) || busy) {
      return;
    }
    var file = pendingAttachment;
    hideSuggestions();
    addMessage('user', text, file);
    history.push({ role: 'user', text: text, file: file ? { name: file.name } : null });
    input.value = '';
    resize();
    pendingAttachment = null;
    clearChip();
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
        attachment_id: file ? file.id : 0,
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

  function reset() {
    history = [];
    conversationId = 0;
    pendingAttachment = null;
    clearChip();
    messages.innerHTML = '';
    store = { visitorId: store.visitorId };
    writeStore(store);
    if (design.welcome_message) {
      addMessage('bot', design.welcome_message);
      history.push({ role: 'bot', text: design.welcome_message });
    }
    renderSuggestions();
  }

  function resize() {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 120) + 'px';
  }

  function toggle(open) {
    var isOpen = open === undefined ? !panel.classList.contains('open') : open;
    if (isOpen) {
      hideBadge();
      panel.classList.add('open');
      launcher.style.display = 'none';
      requestAnimationFrame(function () { panel.classList.add('shown'); });
      input.focus();
      messages.scrollTop = messages.scrollHeight;
    } else {
      panel.classList.remove('shown');
      setTimeout(function () {
        panel.classList.remove('open');
        launcher.style.display = 'flex';
      }, 160);
    }
  }

  launcher.addEventListener('click', function () { toggle(true); });
  closeBtn.addEventListener('click', function () { toggle(false); });
  if (resetBtn) {
    resetBtn.addEventListener('click', reset);
  }
  sendBtn.addEventListener('click', function () { send(input.value); });
  input.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      send(input.value);
    }
  });
  input.addEventListener('input', resize);
  input.addEventListener('focus', function () { composer.classList.add('focus'); });
  input.addEventListener('blur', function () { composer.classList.remove('focus'); });
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
    reset: reset
  };
})();
