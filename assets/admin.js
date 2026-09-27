/*!
 * Admin app shell. After the first load, every link and form in the panel
 * runs through fetch(): the server answers with just the page (or a redirect
 * instruction), and the shell swaps it in with a transition. Addresses still
 * change, so refresh, bookmarks and the back button keep working.
 */
(function () {
  'use strict';

  var main = document.getElementById('app-main');
  var progress = document.getElementById('app-progress');
  var toastHost = document.getElementById('toasts');
  var reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  var navToken = 0;

  /* ---------- helpers ---------- */
  function wait(ms) {
    return new Promise(function (resolve) { setTimeout(resolve, reduceMotion ? 0 : ms); });
  }

  function toUrl(value) {
    try {
      return new URL(value, window.location.href);
    } catch (e) {
      return null;
    }
  }

  function param(value, key) {
    var url = toUrl(value);
    return url ? url.searchParams.get(key) : null;
  }

  /** Pages the shell can load: same origin, the admin front controller. */
  function isAppUrl(value) {
    var url = toUrl(value);
    return !!url && url.origin === window.location.origin && /\/index\.php$|\/$/.test(url.pathname);
  }

  /** Pages that live outside the shell and need a real browser load. */
  function needsFullLoad(value) {
    var route = param(value, 'r');
    return route === 'login' || route === 'preview' || route === 'attachment' || /install\.php/.test(value);
  }

  function restartAnimation(node, className) {
    if (!node || reduceMotion) {
      return;
    }
    node.classList.remove(className);
    void node.offsetWidth;
    node.classList.add(className);
  }

  /**
   * Forms here carry inputs named "action" and "id", which shadow form.action
   * and form.id in the DOM, so attributes are read directly.
   */
  function formAction(form) {
    var attr = form.getAttribute('action');
    return attr ? toUrl(attr).href : window.location.href;
  }

  function csrf() {
    var field = document.querySelector('input[name="_csrf"]');
    return field ? field.value : '';
  }

  /* ---------- progress bar ---------- */
  var progressUsers = 0;

  function progressStart() {
    if (!progress) {
      return;
    }
    progressUsers++;
    progress.style.transition = 'none';
    progress.style.width = '0';
    progress.style.opacity = '1';
    void progress.offsetWidth;
    progress.style.transition = 'width 6s cubic-bezier(.1,.7,.1,1)';
    progress.style.width = '85%';
  }

  function progressDone() {
    if (!progress) {
      return;
    }
    progressUsers = Math.max(0, progressUsers - 1);
    if (progressUsers > 0) {
      return;
    }
    progress.style.transition = 'width .2s ease, opacity .3s ease .2s';
    progress.style.width = '100%';
    progress.style.opacity = '0';
  }

  /* ---------- toasts ---------- */
  function toast(type, message) {
    if (!toastHost || !message) {
      return;
    }
    var node = document.createElement('div');
    node.className = 'toast ' + (type || 'info');
    node.setAttribute('role', type === 'error' ? 'alert' : 'status');
    node.innerHTML = '<span class="toast-text"></span><button type="button" class="toast-close" aria-label="Dismiss">&times;</button>';
    node.querySelector('.toast-text').textContent = message;
    toastHost.appendChild(node);
    requestAnimationFrame(function () { node.classList.add('show'); });

    var timer = setTimeout(dismiss, type === 'error' ? 7000 : 4000);
    function dismiss() {
      clearTimeout(timer);
      node.classList.remove('show');
      node.classList.add('hide');
      setTimeout(function () { node.remove(); }, 260);
    }
    node.querySelector('.toast-close').addEventListener('click', dismiss);
  }

  function toastsFrom(list) {
    (list || []).forEach(function (flash) { toast(flash.type, flash.message); });
  }

  function toastsFromHeader(response) {
    var raw = response.headers.get('X-App-Flash');
    if (!raw) {
      return;
    }
    try {
      var bytes = Uint8Array.from(atob(raw), function (c) { return c.charCodeAt(0); });
      toastsFrom(JSON.parse(new TextDecoder().decode(bytes)));
    } catch (e) { /* a malformed header is not worth breaking the page over */ }
  }

  /* ---------- confirm modal ---------- */
  function appConfirm(message, actionLabel) {
    return new Promise(function (resolve) {
      var overlay = document.createElement('div');
      overlay.className = 'modal-overlay';
      overlay.innerHTML =
        '<div class="modal" role="dialog" aria-modal="true">' +
        '<p class="modal-text"></p>' +
        '<div class="modal-actions">' +
        '<button type="button" class="btn secondary" data-modal-cancel>Cancel</button>' +
        '<button type="button" class="btn danger" data-modal-ok></button>' +
        '</div></div>';
      overlay.querySelector('.modal-text').textContent = message;
      overlay.querySelector('[data-modal-ok]').textContent = actionLabel || 'Confirm';
      document.body.appendChild(overlay);
      requestAnimationFrame(function () { overlay.classList.add('open'); });
      overlay.querySelector('[data-modal-ok]').focus();

      function close(result) {
        document.removeEventListener('keydown', onKey);
        overlay.classList.remove('open');
        setTimeout(function () { overlay.remove(); }, 200);
        resolve(result);
      }
      function onKey(event) {
        if (event.key === 'Escape') {
          close(false);
        }
      }
      document.addEventListener('keydown', onKey);
      overlay.addEventListener('click', function (event) {
        if (event.target === overlay || event.target.closest('[data-modal-cancel]')) {
          close(false);
        } else if (event.target.closest('[data-modal-ok]')) {
          close(true);
        }
      });
    });
  }

  /* ---------- requests ---------- */
  function appFetch(url, options) {
    options = options || {};
    options.credentials = 'same-origin';
    options.headers = Object.assign({ 'X-App-Request': '1' }, options.headers || {});

    return fetch(url, options).then(function (response) {
      toastsFromHeader(response);

      var redirectTo = response.headers.get('X-App-Redirect');
      if (redirectTo) {
        return { redirect: redirectTo };
      }
      if (response.status === 419) {
        // The session timed out; a fresh load issues a new token.
        window.location.reload();
        throw new Error('Session expired');
      }
      if (!response.ok) {
        throw new Error('HTTP ' + response.status);
      }
      return response.text().then(function (html) { return { html: html }; });
    });
  }

  function recordHistory(url, mode) {
    if (mode === 'none') {
      return;
    }
    var same = toUrl(url).href === window.location.href;
    if (mode === 'replace' || same) {
      history.replaceState({ app: 1 }, '', url);
    } else {
      history.pushState({ app: 1 }, '', url);
    }
  }

  /* ---------- whole-page swaps ---------- */
  function applyMeta() {
    var meta = main.querySelector('#app-meta');
    if (!meta) {
      return;
    }
    document.title = meta.getAttribute('data-title') || document.title;
    var section = meta.getAttribute('data-section');
    document.querySelectorAll('.sidebar a.nav').forEach(function (link) {
      link.classList.toggle('active', link.getAttribute('data-section') === section);
    });
  }

  function renderPage(html, url, mode) {
    main.innerHTML = html;
    applyMeta();
    recordHistory(url, mode);
    window.scrollTo(0, 0);
    restartAnimation(main.querySelector('.page'), 'is-entering');
  }

  function navigate(url, options) {
    options = options || {};
    if (!isAppUrl(url) || needsFullLoad(url)) {
      window.location.href = url;
      return Promise.resolve();
    }
    if (canSwapTab(url)) {
      return swapTab(url, options.mode || 'push');
    }

    var token = ++navToken;
    var page = main.querySelector('.page');
    if (page && !reduceMotion) {
      page.classList.remove('is-entering');
      page.classList.add('is-leaving');
    }
    progressStart();

    return Promise.all([appFetch(url), wait(140)])
      .then(function (results) {
        if (token !== navToken) {
          return null;
        }
        var result = results[0];
        if (result.redirect) {
          return navigate(result.redirect, { mode: 'push' });
        }
        renderPage(result.html, url, options.mode || 'push');
        return null;
      })
      .catch(function () {
        if (token === navToken) {
          window.location.href = url;
        }
      })
      .then(progressDone);
  }

  /* ---------- website tabs: swap only the panel ---------- */
  function currentSiteId() {
    var link = document.querySelector('#site-tabs a[data-tab]');
    return link ? param(link.href, 'id') : null;
  }

  function canSwapTab(url) {
    return !!document.getElementById('tab-panel') &&
      param(url, 'r') === 'site' &&
      param(url, 'id') !== null &&
      param(url, 'id') === currentSiteId();
  }

  function setActiveTab(tab) {
    document.querySelectorAll('#site-tabs a[data-tab]').forEach(function (link) {
      link.classList.toggle('active', link.getAttribute('data-tab') === tab);
    });
  }

  function swapTab(url, mode) {
    var panel = document.getElementById('tab-panel');
    var tab = param(url, 'tab') || 'general';
    var token = ++navToken;
    var partialUrl = toUrl(url);
    partialUrl.searchParams.set('partial', '1');

    setActiveTab(tab);
    panel.classList.add('is-loading');
    progressStart();

    return appFetch(partialUrl.href)
      .then(function (result) {
        if (token !== navToken) {
          return null;
        }
        if (result.redirect) {
          return navigate(result.redirect, { mode: 'push' });
        }
        panel.innerHTML = result.html;
        panel.setAttribute('data-tab', tab);
        panel.classList.remove('is-loading');
        recordHistory(url, mode);
        restartAnimation(panel, 'is-entering');
        return null;
      })
      .catch(function () {
        if (token === navToken) {
          window.location.href = url;
        }
      })
      .then(progressDone);
  }

  /* ---------- forms ---------- */
  function setBusy(form, busy) {
    form.querySelectorAll('button[type="submit"], button:not([type])').forEach(function (button) {
      button.disabled = busy;
      button.classList.toggle('is-busy', busy);
    });
  }

  function collapse(node) {
    if (!node) {
      return;
    }
    node.style.height = node.offsetHeight + 'px';
    node.classList.add('is-collapsing');
    requestAnimationFrame(function () { node.style.height = '0px'; });
    setTimeout(function () { node.remove(); }, reduceMotion ? 0 : 300);
  }

  function submitForm(form, submitter) {
    var method = (form.getAttribute('method') || 'get').toLowerCase();
    var action = formAction(form);
    var data = new FormData(form);
    if (submitter && submitter.name) {
      data.append(submitter.name, submitter.value);
    }

    if (method === 'get') {
      var target = toUrl(action);
      data.forEach(function (value, key) { target.searchParams.set(key, value); });
      return navigate(target.href);
    }

    // Small actions that just make something go away, like the release notice.
    var inline = form.getAttribute('data-inline-remove');
    if (inline) {
      collapse(form.closest(inline));
      appFetch(action, { method: 'POST', body: data }).catch(function () {
        toast('error', 'That did not save. Please try again.');
      });
      return Promise.resolve();
    }

    setBusy(form, true);
    progressStart();
    return appFetch(action, { method: 'POST', body: data })
      .then(function (result) {
        if (result.redirect) {
          return navigate(result.redirect, { mode: 'push' });
        }
        // Some forms answer with a page directly (validation errors, a test result).
        renderPage(result.html, action, 'replace');
        return null;
      })
      .catch(function () {
        toast('error', 'That did not go through. Please try again.');
      })
      .then(function () {
        setBusy(form, false);
        progressDone();
      });
  }

  /* ---------- wiring ---------- */
  if (main) {
    document.addEventListener('click', function (event) {
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
      }
      var link = event.target.closest('a[href]');
      if (!link || link.hasAttribute('download') || link.hasAttribute('data-no-app')) {
        return;
      }
      if (link.target && link.target !== '_self') {
        return;
      }
      var href = link.href;
      if (!isAppUrl(href) || (link.getAttribute('href') || '').charAt(0) === '#') {
        return;
      }
      event.preventDefault();
      if (link.classList.contains('active') && link.closest('#site-tabs')) {
        return;
      }
      navigate(href);
    });

    document.addEventListener('submit', function (event) {
      var form = event.target;
      // Forms with their own script (FAQs, website sync) handle themselves.
      if (event.defaultPrevented || form.hasAttribute('data-js-form')) {
        return;
      }
      var submitter = event.submitter;
      var intercept = isAppUrl(formAction(form)) && !form.hasAttribute('data-no-app');

      // Anything destructive is confirmed first, whichever way it is then sent.
      var message = form.getAttribute('data-confirm');
      if (message) {
        event.preventDefault();
        appConfirm(message, form.getAttribute('data-confirm-action')).then(function (ok) {
          if (!ok) {
            return;
          }
          if (intercept) {
            submitForm(form, submitter);
          } else {
            HTMLFormElement.prototype.submit.call(form);
          }
        });
        return;
      }

      if (!intercept) {
        return;
      }
      event.preventDefault();
      submitForm(form, submitter);
    });

    window.addEventListener('popstate', function () {
      navigate(window.location.href, { mode: 'none' });
    });

    history.replaceState({ app: 1 }, '', window.location.href);

    var initial = document.getElementById('app-flashes');
    if (initial) {
      try {
        toastsFrom(JSON.parse(initial.textContent || '[]'));
      } catch (e) { /* ignore */ }
    }
    restartAnimation(main.querySelector('.page'), 'is-entering');
  }

  /* ---------- embed code ---------- */
  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-copy-embed]');
    if (!button) {
      return;
    }
    var code = document.getElementById('embed-code');
    if (!code) {
      return;
    }
    var done = function () {
      var original = button.textContent;
      button.textContent = 'Copied!';
      setTimeout(function () { button.textContent = original; }, 1600);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(code.textContent).then(done, function () {
        window.prompt('Copy the code:', code.textContent);
      });
    } else {
      window.prompt('Copy the code:', code.textContent);
    }
  });

  /* ---------- FAQs ---------- */
  function faqRequest(fields) {
    var card = document.querySelector('[data-faq-site]');
    var body = new FormData();
    body.append('_csrf', csrf());
    body.append('site_id', card.getAttribute('data-faq-site'));
    Object.keys(fields).forEach(function (key) { body.append(key, fields[key]); });

    return fetch('index.php?r=faq', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (data.error) {
          throw new Error(data.error);
        }
        return data;
      });
  }

  function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = value;
    return div.innerHTML;
  }

  function renderFaqs(faqs) {
    var list = document.getElementById('faq-list');
    var empty = document.querySelector('.faq-empty');
    if (!list) {
      return;
    }
    list.innerHTML = faqs.map(function (faq) {
      return '<div class="faq-item is-new" data-faq-id="' + faq.id + '">' +
        '<div class="faq-head"><strong class="faq-question">' + escapeHtml(faq.question) + '</strong>' +
        (faq.show_as_chip ? '<span class="badge">chip</span>' : '') +
        '<span class="spacer"></span>' +
        '<button type="button" class="btn secondary small" data-faq-edit>Edit</button>' +
        '<button type="button" class="btn danger small" data-faq-delete>Delete</button></div>' +
        '<div class="faq-answer muted">' + escapeHtml(faq.answer).replace(/\n/g, '<br>') + '</div>' +
        '</div>';
    }).join('');
    if (empty) {
      empty.hidden = faqs.length > 0;
    }
  }

  function resetFaqForm() {
    var form = document.getElementById('faq-form');
    if (!form) {
      return;
    }
    form.reset();
    form.querySelector('[name="faq_id"]').value = '';
    form.querySelector('[data-faq-submit]').textContent = 'Add FAQ';
    form.querySelector('[data-faq-cancel]').hidden = true;
    form.querySelector('[name="show_as_chip"]').checked = true;
  }

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('#faq-form');
    if (!form) {
      return;
    }
    event.preventDefault();
    var id = form.querySelector('[name="faq_id"]').value;
    var button = form.querySelector('[data-faq-submit]');
    button.disabled = true;
    button.classList.add('is-busy');

    faqRequest({
      action: id ? 'update' : 'create',
      id: id,
      question: form.querySelector('[name="question"]').value,
      answer: form.querySelector('[name="answer"]').value,
      show_as_chip: form.querySelector('[name="show_as_chip"]').checked ? '1' : '0'
    }).then(function (data) {
      renderFaqs(data.faqs);
      resetFaqForm();
      toast('success', id ? 'FAQ updated.' : 'FAQ added.');
    }).catch(function (error) {
      toast('error', error.message);
    }).then(function () {
      button.disabled = false;
      button.classList.remove('is-busy');
    });
  });

  document.addEventListener('click', function (event) {
    var item = event.target.closest('.faq-item');

    if (event.target.closest('[data-faq-delete]')) {
      appConfirm('Delete this FAQ?', 'Delete').then(function (ok) {
        if (!ok) {
          return;
        }
        collapse(item);
        faqRequest({ action: 'delete', id: item.getAttribute('data-faq-id') })
          .then(function (data) {
            setTimeout(function () { renderFaqs(data.faqs); }, 320);
            toast('success', 'FAQ deleted.');
          })
          .catch(function (error) { toast('error', error.message); });
      });
      return;
    }

    if (event.target.closest('[data-faq-edit]')) {
      var form = document.getElementById('faq-form');
      form.querySelector('[name="faq_id"]').value = item.getAttribute('data-faq-id');
      form.querySelector('[name="question"]').value = item.querySelector('.faq-question').textContent;
      form.querySelector('[name="answer"]').value = item.querySelector('.faq-answer').innerText;
      form.querySelector('[name="show_as_chip"]').checked = !!item.querySelector('.badge');
      form.querySelector('[data-faq-submit]').textContent = 'Save changes';
      form.querySelector('[data-faq-cancel]').hidden = false;
      form.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
      form.querySelector('[name="question"]').focus({ preventScroll: true });
      return;
    }

    if (event.target.closest('[data-faq-cancel]')) {
      resetFaqForm();
    }
  });
  /* ---------- website sync ---------- */
  function storeRequest(siteId, fields, method) {
    if (method === 'GET') {
      var url = toUrl('index.php');
      url.searchParams.set('r', 'store');
      url.searchParams.set('site_id', siteId);
      Object.keys(fields).forEach(function (key) { url.searchParams.set(key, fields[key]); });
      return fetch(url.href, { credentials: 'same-origin' }).then(readJson);
    }
    var body = new FormData();
    body.append('_csrf', csrf());
    body.append('site_id', siteId);
    Object.keys(fields).forEach(function (key) {
      var value = fields[key];
      if (Array.isArray(value)) {
        value.forEach(function (item) { body.append(key + '[]', item); });
      } else {
        body.append(key, value);
      }
    });
    return fetch('index.php?r=store', { method: 'POST', body: body, credentials: 'same-origin' }).then(readJson);
  }

  function readJson(response) {
    return response.json().catch(function () {
      throw new Error('The server returned an unexpected answer (HTTP ' + response.status + ').');
    }).then(function (data) {
      if (data.error) {
        throw new Error(data.error);
      }
      return data;
    });
  }

  var PHASE_LABELS = { pages: 'Pages', posts: 'Posts', products: 'Products' };

  function syncUi() {
    var card = document.getElementById('store-sync');
    if (!card) {
      return null;
    }
    return {
      card: card,
      button: card.querySelector('[data-sync-button]'),
      progress: card.querySelector('.sync-progress'),
      bar: card.querySelector('.sync-bar span'),
      label: card.querySelector('.sync-label'),
      status: card.querySelector('.sync-status'),
      error: card.querySelector('.sync-error')
    };
  }

  function showProgress(phase, page, totalPages, total) {
    var ui = syncUi();
    if (!ui) {
      return;
    }
    ui.progress.hidden = false;
    var pct = totalPages > 0 ? Math.min(100, Math.round(page / totalPages * 100)) : 100;
    ui.bar.style.width = pct + '%';
    ui.label.textContent = PHASE_LABELS[phase] + ' · ' +
      (totalPages > 0 ? 'page ' + page + ' of ' + totalPages + ' · ' + total.toLocaleString() + ' total' : 'none found');
  }

  function runSync(form) {
    var ui = syncUi();
    var siteId = ui.card.getAttribute('data-site');
    var fields = {
      action: 'start',
      url: form.querySelector('[name="url"]').value,
      pages: form.querySelector('[name="pages"]').checked ? '1' : '0',
      posts: form.querySelector('[name="posts"]').checked ? '1' : '0',
      products: form.querySelector('[name="products"]').checked ? '1' : '0'
    };
    var token;
    var completed = [];
    var warnings = [];

    ui.button.disabled = true;
    ui.button.classList.add('is-busy');
    ui.button.textContent = 'Syncing…';
    ui.card.querySelectorAll('.sync-error').forEach(function (node) { node.remove(); });
    progressStart();

    // Resolves true when the phase ran to its last page, false when the site
    // refused it - the other phases still run.
    function runPhase(phase, page) {
      return storeRequest(siteId, { action: 'step', token: token, phase: phase, page: page }).then(function (result) {
        if (result.skipped) {
          warnings.push(result.reason);
          toast('warning', result.reason);
          return false;
        }
        showProgress(phase, result.page, result.total_pages, result.total);
        return result.next_page ? runPhase(phase, result.next_page) : true;
      });
    }

    storeRequest(siteId, fields)
      .then(function (start) {
        token = start.token;
        form.querySelector('[name="url"]').value = start.url;
        return start.phases.reduce(function (chain, phase) {
          return chain.then(function () {
            showProgress(phase, 0, 1, 0);
            return runPhase(phase, 1).then(function (finished) {
              if (finished) {
                completed.push(phase);
              }
            });
          });
        }, Promise.resolve());
      })
      .then(function () {
        return storeRequest(siteId, { action: 'finish', token: token, completed: completed, notes: warnings });
      })
      .then(function (done) {
        var stats = done.stats;
        var live = syncUi();
        if (live) {
          if (done.synced) {
            live.status.textContent = 'Last sync just now · ' + stats.products.toLocaleString() + ' products · ' +
              stats.pages.toLocaleString() + ' pages · ' + stats.posts.toLocaleString() + ' posts';
          }
          live.label.textContent = !done.synced ? 'Nothing synced'
            : (warnings.length ? 'Done, with ' + warnings.length + ' skipped' : 'Done');
          live.bar.style.width = '100%';
          var anchor = live.status;
          warnings.forEach(function (reason) {
            var line = document.createElement('p');
            line.className = 'sync-error';
            line.textContent = reason;
            anchor.after(line);
            anchor = line;
          });
        }
        if (completed.length) {
          toast('success', 'Sync complete: ' + stats.products.toLocaleString() + ' products, ' +
            stats.pages + ' pages, ' + stats.posts + ' posts.');
        }
        loadProducts('');
      })
      .catch(function (error) {
        toast('error', error.message);
        if (token) {
          storeRequest(siteId, { action: 'fail', message: error.message }).catch(function () {});
        }
        var live = syncUi();
        if (live) {
          live.label.textContent = 'Stopped';
          var line = document.createElement('p');
          line.className = 'sync-error';
          line.textContent = error.message;
          live.status.after(line);
        }
      })
      .then(function () {
        var live = syncUi();
        if (live) {
          live.button.disabled = false;
          live.button.classList.remove('is-busy');
          live.button.textContent = 'Sync now';
        }
        progressDone();
      });
  }

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('#store-sync-form');
    if (!form) {
      return;
    }
    event.preventDefault();
    runSync(form);
  });

  /* ---------- product list ---------- */
  var productTimer = null;

  function loadProducts(query) {
    var card = document.getElementById('products-card');
    if (!card) {
      return;
    }
    storeRequest(card.getAttribute('data-site'), { action: 'products', q: query }, 'GET').then(function (data) {
      var list = card.querySelector('.product-list');
      card.querySelector('.product-total').textContent = data.total.toLocaleString();
      card.querySelector('.product-search').hidden = data.total === 0;
      card.querySelector('.product-empty').hidden = data.products.length > 0;
      card.querySelector('.product-empty').textContent = data.total === 0 ? 'No products yet.' : 'No matches.';
      list.innerHTML = data.products.map(function (p) {
        var name = p.url
          ? '<a href="' + escapeHtml(p.url) + '" target="_blank" rel="noopener">' + escapeHtml(p.name) + '</a>'
          : escapeHtml(p.name);
        return '<div class="product-row is-new"><div class="product-name">' + name +
          (p.sku ? ' <span class="mono muted">' + escapeHtml(p.sku) + '</span>' : '') + '</div>' +
          '<div class="product-price">' + escapeHtml(p.price) + '</div>' +
          '<span class="badge ' + (p.in_stock ? 'green' : 'grey') + '">' +
          escapeHtml(p.stock || (p.in_stock ? 'In stock' : 'Out of stock')) + '</span></div>';
      }).join('');
    }).catch(function (error) { toast('error', error.message); });
  }

  document.addEventListener('input', function (event) {
    if (!event.target.classList.contains('product-search')) {
      return;
    }
    clearTimeout(productTimer);
    var query = event.target.value;
    productTimer = setTimeout(function () { loadProducts(query); }, 250);
  });
})();
