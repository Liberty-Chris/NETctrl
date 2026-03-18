(() => {
  const config = window.netctrlConsole || {};
  const restUrl = config.restUrl;
  const nonce = config.nonce;
  const strings = {
    unableToLoadSessions: 'Unable to load sessions.',
    unableToLoadEntries: 'Unable to load entries.',
    requestFailed: 'Request failed.',
    selectSession: 'Select or start a session to begin logging.',
    activeSessionLabel: 'Current session:',
    noRecentSessions: 'No recent sessions found.',
    noEntries: 'No entries recorded yet.',
    ...(config.strings || {}),
  };

  if (!restUrl || !nonce) {
    return;
  }

  const defaultHeaders = {
    'Content-Type': 'application/json',
    'X-WP-Nonce': nonce,
  };

  const fetchJson = async (url, options = {}) => {
    const response = await fetch(url, {
      ...options,
      headers: {
        ...defaultHeaders,
        ...(options.headers || {}),
      },
    });

    if (!response.ok) {
      let message = strings.requestFailed;

      try {
        const error = await response.json();
        message = error?.message || message;
      } catch (err) {
        // Ignore JSON parsing failures and keep the generic message.
      }

      throw new Error(message);
    }

    return response.json();
  };

  const initConsole = (root) => {
    const sessionsList = root.querySelector('#netctrl-sessions');
    const entriesList = root.querySelector('#netctrl-entries');
    const activeSessionEl = root.querySelector('#netctrl-active-session');
    const messagesEl = root.querySelector('.netctrl-console__messages');
    const netNameInput = root.querySelector('#netctrl-net-name');
    const callsignInput = root.querySelector('#netctrl-callsign');
    const nameInput = root.querySelector('#netctrl-name');
    const locationInput = root.querySelector('#netctrl-location');
    const commentsInput = root.querySelector('#netctrl-comments');
    const startButton = root.querySelector('#netctrl-start-session');
    const addEntryButton = root.querySelector('#netctrl-add-entry');
    const closeSessionButton = root.querySelector('#netctrl-close-session');

    if (!sessionsList || !entriesList || !activeSessionEl || !startButton || !addEntryButton || !closeSessionButton) {
      return;
    }

    let activeSessionId = null;

    const setMessage = (message = '', type = '') => {
      if (!messagesEl) {
        return;
      }

      messagesEl.textContent = message;
      messagesEl.className = 'netctrl-console__messages';

      if (message && type) {
        messagesEl.classList.add(`netctrl-console__messages--${type}`);
      }
    };

    const renderSessions = (sessions) => {
      sessionsList.innerHTML = '';

      if (!sessions.length) {
        const item = document.createElement('li');
        item.textContent = strings.noRecentSessions;
        item.className = 'netctrl-list__empty';
        sessionsList.appendChild(item);
        return;
      }

      sessions.forEach((session) => {
        const item = document.createElement('li');
        item.className = 'netctrl-list__item';
        item.textContent = `${session.net_name} (${session.status})`;
        item.dataset.sessionId = session.id;
        item.addEventListener('click', () => {
          setActiveSession(session).catch((error) => {
            setMessage(error.message || strings.unableToLoadEntries, 'error');
          });
        });
        sessionsList.appendChild(item);
      });
    };

    const renderEntries = (entries) => {
      entriesList.innerHTML = '';

      if (!entries.length) {
        const item = document.createElement('li');
        item.textContent = strings.noEntries;
        item.className = 'netctrl-list__empty';
        entriesList.appendChild(item);
        return;
      }

      entries.forEach((entry) => {
        const item = document.createElement('li');
        item.className = 'netctrl-list__item';
        item.textContent = `${entry.callsign} ${entry.name || ''} ${entry.location || ''} ${entry.comments || ''}`.trim();
        entriesList.appendChild(item);
      });
    };

    const updateActiveSessionText = (session) => {
      if (!session) {
        activeSessionEl.textContent = strings.selectSession;
        return;
      }

      activeSessionEl.textContent = `${strings.activeSessionLabel} ${session.net_name} (${session.status})`;
    };

    const loadEntries = async (sessionId) => {
      if (!sessionId) {
        renderEntries([]);
        return;
      }

      const entries = await fetchJson(`${restUrl}/sessions/${sessionId}/entries`);
      renderEntries(entries);
    };

    const setActiveSession = async (session) => {
      activeSessionId = session.id;
      updateActiveSessionText(session);
      await loadEntries(activeSessionId);
    };

    const loadSessions = async () => {
      const sessions = await fetchJson(`${restUrl}/sessions`);
      renderSessions(sessions);

      const active = sessions.find((session) => session.status === 'open');
      if (active) {
        await setActiveSession(active);
      } else {
        activeSessionId = null;
        updateActiveSessionText(null);
        renderEntries([]);
      }
    };

    startButton.addEventListener('click', async () => {
      const netName = netNameInput.value.trim();
      if (!netName) {
        return;
      }

      try {
        setMessage('');
        const data = await fetchJson(`${restUrl}/sessions`, {
          method: 'POST',
          body: JSON.stringify({ net_name: netName }),
        });

        netNameInput.value = '';

        if (data.session) {
          await loadSessions();
          await setActiveSession(data.session);
        }
      } catch (error) {
        setMessage(error.message || strings.requestFailed, 'error');
      }
    });

    addEntryButton.addEventListener('click', async () => {
      if (!activeSessionId) {
        setMessage(strings.selectSession, 'error');
        return;
      }

      const payload = {
        callsign: callsignInput.value.trim(),
        name: nameInput.value.trim(),
        location: locationInput.value.trim(),
        comments: commentsInput.value.trim(),
      };

      if (!payload.callsign) {
        return;
      }

      try {
        setMessage('');
        await fetchJson(`${restUrl}/sessions/${activeSessionId}/entries`, {
          method: 'POST',
          body: JSON.stringify(payload),
        });

        callsignInput.value = '';
        nameInput.value = '';
        locationInput.value = '';
        commentsInput.value = '';

        await loadEntries(activeSessionId);
      } catch (error) {
        setMessage(error.message || strings.requestFailed, 'error');
      }
    });

    closeSessionButton.addEventListener('click', async () => {
      if (!activeSessionId) {
        return;
      }

      try {
        setMessage('');
        await fetchJson(`${restUrl}/sessions/${activeSessionId}/close`, {
          method: 'POST',
        });

        activeSessionId = null;
        updateActiveSessionText(null);
        await loadSessions();
      } catch (error) {
        setMessage(error.message || strings.requestFailed, 'error');
      }
    });

    loadSessions().catch((error) => {
      setMessage(error.message || strings.unableToLoadSessions, 'error');
      sessionsList.innerHTML = `<li class="netctrl-list__empty">${strings.unableToLoadSessions}</li>`;
      updateActiveSessionText(null);
      renderEntries([]);
    });
  };

  document.querySelectorAll('[data-netctrl-console-root]').forEach(initConsole);
})();
