(function () {
  function escapeHtml(value) {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;');
  }

  function createSupportBot(config) {
    const pageName = config.pageName || 'this page';
    const bookingUrl = config.bookingUrl || 'TwoPL/index.php';
    const loginUrl = config.loginUrl || 'login.php';
    const historyTarget = config.historyTarget || '#history';
    const helpTarget = config.helpTarget || '#help-center';
    const searchTarget = config.searchTarget || '#top';

    const wrapper = document.createElement('div');
    wrapper.innerHTML = `
      <button class="support-bot-fab" type="button" id="supportBotFab" aria-label="Open support assistant">
        <i class="fa-solid fa-robot"></i>
      </button>
      <div class="support-bot-panel" id="supportBotPanel">
        <div class="support-bot-title">
          <div>
            <div style="font-weight:800; font-size:1.05rem; color:#0f172a;">RailOps Assistant</div>
            <div class="support-bot-chip">Quick support</div>
          </div>
          <button type="button" id="supportBotClose" style="border:none;background:none;color:#64748b;font-size:1.1rem;cursor:pointer;">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
        <div class="support-bot-thread" id="supportBotThread">
          <div class="support-bot-bubble bot">
            Type a quick question like <strong>cancel my ticket</strong>, <strong>show history</strong>, <strong>book train</strong>, <strong>seat issue</strong>, or <strong>login problem</strong>. I will keep it short and redirect only when it helps.
          </div>
        </div>
        <div class="support-bot-actions">
          <button type="button" data-intent="cancel">I need to cancel a ticket</button>
          <button type="button" data-intent="book">I want to book a new trip</button>
          <button type="button" data-intent="history">Show my booking history</button>
          <button type="button" data-intent="search">Take me to the main action here</button>
        </div>
        <div class="support-bot-input-wrap">
          <input type="text" id="supportBotInput" class="support-bot-input" placeholder="Type your support question">
          <button type="button" id="supportBotSend" class="support-bot-send">Send</button>
        </div>
        <div class="support-bot-status" id="supportBotStatus"></div>
      </div>
    `;
    document.body.appendChild(wrapper);

    const botFab = document.getElementById('supportBotFab');
    const botPanel = document.getElementById('supportBotPanel');
    const botClose = document.getElementById('supportBotClose');
    const botThread = document.getElementById('supportBotThread');
    const botInput = document.getElementById('supportBotInput');
    const botSend = document.getElementById('supportBotSend');
    const botStatus = document.getElementById('supportBotStatus');

    function appendBotMessage(role, message) {
      const bubble = document.createElement('div');
      bubble.className = `support-bot-bubble ${role}`;
      bubble.innerHTML = message;
      botThread.appendChild(bubble);
      botThread.scrollTop = botThread.scrollHeight;
    }

    function showTyping() {
      botStatus.innerHTML = '<span class="support-bot-typing"><span></span><span></span><span></span></span> Assistant is typing';
    }

    function hideTyping() {
      botStatus.textContent = '';
    }

    function resolveAction(intent) {
      const actions = {
        cancel: {
          message: helpTarget.startsWith('#')
            ? 'For cancellations, use the Help Center form with your transaction ID. I can take you there now.'
            : 'For cancellations, open the passenger help center and submit the request with your transaction ID.',
          target: helpTarget
        },
        book: {
          message: 'For a new booking, the fastest route is the 2PL booking page. I can open it now.',
          target: bookingUrl
        },
        history: {
          message: historyTarget.startsWith('#')
            ? 'Your confirmed and cancelled trips are listed in Booking History. I can jump there now.'
            : 'Booking history is available after you sign in to the passenger dashboard.',
          target: historyTarget
        },
        search: {
          message: `The main action on ${pageName} is ready. I can take you there now.`,
          target: searchTarget
        },
        seat: {
          message: 'Seat selection issues usually happen because another user is holding the seat or because a session timed out. Reopen the booking flow and choose a seat that is not marked held or booked.',
          target: bookingUrl
        },
        login: {
          message: 'If login is failing, verify the correct portal credentials first. If you are stuck in a session loop, signing out and signing in again usually resets it cleanly.',
          target: loginUrl
        },
        register: {
          message: 'If you need a new account, the registration page is the right place to start.',
          target: config.registerUrl || 'register.php'
        },
        fallback: {
          message: 'I can help best with booking, cancellations, search, seat issues, login problems, and booking history. Try asking one of those directly.',
          target: ''
        }
      };
      return actions[intent] || actions.fallback;
    }

    function detectIntent(text) {
      const value = text.toLowerCase();
      if (value.includes('cancel')) return 'cancel';
      if (value.includes('book') || value.includes('ticket') || value.includes('train')) return 'book';
      if (value.includes('history') || value.includes('my bookings') || value.includes('past')) return 'history';
      if (value.includes('search') || value.includes('find') || value.includes('route')) return 'search';
      if (value.includes('seat') || value.includes('locked') || value.includes('hold')) return 'seat';
      if (value.includes('login') || value.includes('sign in') || value.includes('password') || value.includes('session')) return 'login';
      if (value.includes('register') || value.includes('signup') || value.includes('sign up') || value.includes('create account')) return 'register';
      return 'fallback';
    }

    function performNavigation(target) {
      if (!target) return;
      setTimeout(() => {
        if (target.startsWith('#')) {
          if (target === '#top') {
            window.scrollTo({ top: 0, behavior: 'smooth' });
          } else {
            document.querySelector(target)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        } else {
          window.location.href = target;
        }
      }, 700);
    }

    function handleIntent(intent, userText) {
      if (userText) {
        appendBotMessage('user', escapeHtml(userText));
      }
      showTyping();
      const action = resolveAction(intent);
      setTimeout(() => {
        hideTyping();
        appendBotMessage('bot', action.message);
        performNavigation(action.target);
      }, 500);
    }

    function submitQuery() {
      const value = botInput.value.trim();
      if (!value) return;
      botInput.value = '';
      handleIntent(detectIntent(value), value);
    }

    botFab.addEventListener('click', () => {
      botPanel.classList.toggle('open');
      if (botPanel.classList.contains('open')) {
        botInput.focus();
      }
    });
    botClose.addEventListener('click', () => botPanel.classList.remove('open'));
    botSend.addEventListener('click', submitQuery);
    botInput.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        submitQuery();
      }
    });
    botPanel.querySelectorAll('[data-intent]').forEach((button) => {
      button.addEventListener('click', () => handleIntent(button.dataset.intent, ''));
    });
  }

  window.mountSupportBot = createSupportBot;
})();
