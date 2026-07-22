/* IPMS AI Chat Widget — shared across the landing page and citizen dashboard.
   Expects window.CHATBOT_ENDPOINT to be set by the including page. Injects
   its own markup, so the host page only needs the CSS link + this script. */
(function () {
  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  // Quick-reply chips shown once, right after the greeting — cover the
  // questions citizens ask most often so they don't have to type them.
  var SUGGESTIONS = [
    { label: 'About IPMS', message: 'What is IPMS and what can I do here?' },
    { label: 'How to register', message: 'How do I register for a citizen account?' },
    { label: 'How to get verified', message: 'How do I verify my citizen account?' },
    { label: 'How to submit feedback', message: 'How do I submit feedback or a complaint?' },
    { label: 'Track my complaint', message: 'How do I track a complaint I already submitted?' },
  ];

  function buildWidget() {
    const launcher = document.createElement('button');
    launcher.type = 'button';
    launcher.className = 'ipms-cw-launcher';
    launcher.setAttribute('aria-label', 'Open IPMS chat assistant');
    launcher.innerHTML =
      '<svg class="ipms-cw-launcher-chat" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7z" clip-rule="evenodd"/></svg>' +
      '<svg class="ipms-cw-launcher-close" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>';

    const panel = document.createElement('div');
    panel.className = 'ipms-cw-panel';
    panel.innerHTML =
      '<div class="ipms-cw-head">' +
      '  <div class="ipms-cw-head-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7z" clip-rule="evenodd"/></svg></div>' +
      '  <div class="ipms-cw-head-text">' +
      '    <div class="ipms-cw-head-title">IPMS Assistant</div>' +
      '    <div class="ipms-cw-head-sub">Ask about projects, feedback, or the portal</div>' +
      '  </div>' +
      '  <button type="button" class="ipms-cw-head-close" aria-label="Close chat">&times;</button>' +
      '</div>' +
      '<div class="ipms-cw-body" id="ipmsCwBody"></div>' +
      '<form class="ipms-cw-foot" id="ipmsCwForm">' +
      '  <textarea class="ipms-cw-input" id="ipmsCwInput" rows="1" placeholder="Type a message..." maxlength="1500"></textarea>' +
      '  <button type="submit" class="ipms-cw-send" id="ipmsCwSend" aria-label="Send">' +
      '    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M3.4 20l17.6-8.6a1 1 0 000-1.8L3.4 1a1 1 0 00-1.4 1.1L4.6 10l-2.6 7.9A1 1 0 003.4 20z"/></svg>' +
      '  </button>' +
      '</form>';

    document.body.appendChild(launcher);
    document.body.appendChild(panel);

    const body = panel.querySelector('#ipmsCwBody');
    const form = panel.querySelector('#ipmsCwForm');
    const input = panel.querySelector('#ipmsCwInput');
    const sendBtn = panel.querySelector('#ipmsCwSend');
    const closeBtn = panel.querySelector('.ipms-cw-head-close');

    let greeted = false;

    function addMessage(role, text) {
      const row = document.createElement('div');
      row.className = 'ipms-cw-msg ' + role;
      const bubble = document.createElement('div');
      bubble.className = 'ipms-cw-bubble';
      bubble.innerHTML = escapeHtml(text).replace(/\n/g, '<br>');
      row.appendChild(bubble);
      body.appendChild(row);
      body.scrollTop = body.scrollHeight;
    }

    function showTyping() {
      const row = document.createElement('div');
      row.className = 'ipms-cw-msg bot';
      row.id = 'ipmsCwTyping';
      row.innerHTML = '<div class="ipms-cw-bubble"><div class="ipms-cw-typing"><span></span><span></span><span></span></div></div>';
      body.appendChild(row);
      body.scrollTop = body.scrollHeight;
    }

    function hideTyping() {
      const el = document.getElementById('ipmsCwTyping');
      if (el) el.remove();
    }

    function removeSuggestions() {
      const el = document.getElementById('ipmsCwSuggestions');
      if (el) el.remove();
    }

    function showSuggestions() {
      const wrap = document.createElement('div');
      wrap.className = 'ipms-cw-suggestions';
      wrap.id = 'ipmsCwSuggestions';
      SUGGESTIONS.forEach(function (item) {
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'ipms-cw-chip';
        chip.textContent = item.label;
        chip.addEventListener('click', function () { sendMessage(item.message); });
        wrap.appendChild(chip);
      });
      body.appendChild(wrap);
      body.scrollTop = body.scrollHeight;
    }

    function sendMessage(text) {
      text = text.trim();
      if (!text) return;

      removeSuggestions();
      addMessage('user', text);
      input.value = '';
      input.style.height = 'auto';
      input.disabled = true;
      sendBtn.disabled = true;
      showTyping();

      fetch(window.CHATBOT_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text }),
      })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
          hideTyping();
          if (result.ok && result.data.success) {
            addMessage('bot', result.data.reply);
          } else {
            addMessage('error', result.data.message || 'Something went wrong. Please try again.');
          }
        })
        .catch(function () {
          hideTyping();
          addMessage('error', 'Could not reach the assistant. Please check your connection and try again.');
        })
        .finally(function () {
          input.disabled = false;
          sendBtn.disabled = false;
          input.focus();
        });
    }

    function openPanel() {
      panel.classList.add('is-open');
      launcher.classList.add('is-open');
      if (!greeted) {
        greeted = true;
        addMessage('bot', "Hi! I'm the IPMS Assistant. Ask me about tracking a project, submitting feedback, or how this portal works.");
        showSuggestions();
      }
      setTimeout(function () { input.focus(); }, 50);
    }

    function closePanel() {
      panel.classList.remove('is-open');
      launcher.classList.remove('is-open');
    }

    launcher.addEventListener('click', function () {
      if (panel.classList.contains('is-open')) closePanel();
      else openPanel();
    });
    closeBtn.addEventListener('click', closePanel);

    input.addEventListener('input', function () {
      input.style.height = 'auto';
      input.style.height = Math.min(input.scrollHeight, 80) + 'px';
    });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        form.requestSubmit();
      }
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      sendMessage(input.value);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', buildWidget);
  } else {
    buildWidget();
  }
})();
