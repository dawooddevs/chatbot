/*!
 * Admin panel behaviour: tab switching without a page load, the FAQ manager
 * and the embed-code copy button. Everything degrades to plain links and
 * form posts when JavaScript is unavailable.
 */
(function () {
  'use strict';

  var panel = document.getElementById('tab-panel');
  var tabs = document.getElementById('site-tabs');

  function csrf() {
    var field = document.querySelector('input[name="_csrf"]');
    return field ? field.value : '';
  }

  /* ---------- tabs ---------- */
  function swap(url, push) {
    if (!panel) {
      window.location.href = url;
      return;
    }
    var separator = url.indexOf('?') === -1 ? '?' : '&';
    panel.classList.add('is-loading');

    fetch(url + separator + 'partial=1', { credentials: 'same-origin' })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('HTTP ' + response.status);
        }
        return response.text();
      })
      .then(function (html) {
        panel.innerHTML = html;
        panel.classList.remove('is-loading');
        panel.classList.remove('is-entering');
        // Restart the entry animation.
        void panel.offsetWidth;
        panel.classList.add('is-entering');
        if (push) {
          history.pushState({ tabUrl: url }, '', url);
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
      })
      .catch(function () {
        window.location.href = url;
      });
  }

  if (tabs) {
    tabs.addEventListener('click', function (event) {
      var link = event.target.closest('a[data-tab]');
      if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
        return;
      }
      event.preventDefault();
      if (link.classList.contains('active')) {
        return;
      }
      Array.prototype.forEach.call(tabs.querySelectorAll('a[data-tab]'), function (item) {
        item.classList.toggle('active', item === link);
      });
      swap(link.href, true);
    });

    window.addEventListener('popstate', function () {
      var url = window.location.href;
      var match = url.match(/[?&]tab=([a-z]+)/);
      var active = match ? match[1] : 'general';
      Array.prototype.forEach.call(tabs.querySelectorAll('a[data-tab]'), function (item) {
        item.classList.toggle('active', item.getAttribute('data-tab') === active);
      });
      swap(url, false);
    });
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
  function faqCard() {
    return document.querySelector('[data-faq-site]');
  }

  function faqRequest(fields) {
    var card = faqCard();
    var body = new FormData();
    body.append('_csrf', csrf());
    body.append('site_id', card.getAttribute('data-faq-site'));
    Object.keys(fields).forEach(function (key) {
      body.append(key, fields[key]);
    });

    return fetch('index.php?r=faq', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (data.error) {
          throw new Error(data.error);
        }
        return data;
      });
  }

  function status(message, isError) {
    var node = document.querySelector('.faq-status');
    if (!node) {
      return;
    }
    node.textContent = message || '';
    node.classList.toggle('error', !!isError);
    if (message) {
      setTimeout(function () {
        if (node.textContent === message) {
          node.textContent = '';
        }
      }, 4000);
    }
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

  function resetForm() {
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
    status('Saving…');

    faqRequest({
      action: id ? 'update' : 'create',
      id: id,
      question: form.querySelector('[name="question"]').value,
      answer: form.querySelector('[name="answer"]').value,
      show_as_chip: form.querySelector('[name="show_as_chip"]').checked ? '1' : '0'
    }).then(function (data) {
      renderFaqs(data.faqs);
      resetForm();
      status(id ? 'Updated.' : 'Added.');
    }).catch(function (error) {
      status(error.message, true);
    }).then(function () {
      button.disabled = false;
    });
  });

  document.addEventListener('click', function (event) {
    var item = event.target.closest('.faq-item');

    if (event.target.closest('[data-faq-delete]')) {
      if (!window.confirm('Delete this FAQ?')) {
        return;
      }
      status('Deleting…');
      faqRequest({ action: 'delete', id: item.getAttribute('data-faq-id') })
        .then(function (data) {
          renderFaqs(data.faqs);
          status('Deleted.');
        })
        .catch(function (error) { status(error.message, true); });
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
      form.querySelector('[name="question"]').focus();
      return;
    }

    if (event.target.closest('[data-faq-cancel]')) {
      resetForm();
    }
  });
})();
