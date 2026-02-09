(() => {
  const restUrl = netctrlConsole?.restUrl;
  const nonce = netctrlConsole?.nonce;

  const sessionsList = document.getElementById('netctrl-sessions');
  const entriesList = document.getElementById('netctrl-entries');
  const activeSessionEl = document.getElementById('netctrl-active-session');

  let activeSessionId = null;

  const headers = {
    'Content-Type': 'application/json',
    'X-WP-Nonce': nonce,
  };

  const fetchJson = async (url, options = {}) => {
    const response = await fetch(url, {
      ...options,
      headers: {
        ...headers,
        ...(options.headers || {}),
      },
    });

    if (!response.ok) {
      throw new Error('Request failed');
    }

    return response.json();
  };

  const renderSessions = (sessions) => {
    sessionsList.innerHTML = '';
    sessions.forEach((session) => {
      const item = document.createElement('li');
      item.textContent = `${session.net_name} (${session.status})`;
      item.dataset.sessionId = session.id;
      item.addEventListener('click', () => setActiveSession(session));
      sessionsList.appendChild(item);
    });
  };

  const renderEntries = (entries) => {
    entriesList.innerHTML = '';
    entries.forEach((entry) => {
      const item = document.createElement('li');
      item.textContent = `${entry.callsign} ${entry.name || ''} ${entry.location || ''} ${entry.comments || ''}`;
      entriesList.appendChild(item);
    });
  };

  const setActiveSession = async (session) => {
    activeSessionId = session.id;
    activeSessionEl.textContent = `${session.net_name} (${session.status})`;
    await loadEntries(activeSessionId);
  };

  const loadSessions = async () => {
    const sessions = await fetchJson(`${restUrl}/sessions`);
    renderSessions(sessions);
    const active = sessions.find((session) => session.status === 'open');
    if (active) {
      setActiveSession(active);
    }
  };

  const loadEntries = async (sessionId) => {
    if (!sessionId) {
      entriesList.innerHTML = '';
      return;
    }
    const entries = await fetchJson(`${restUrl}/sessions/${sessionId}/entries`);
    renderEntries(entries);
  };

  document.getElementById('netctrl-start-session').addEventListener('click', async () => {
    const netName = document.getElementById('netctrl-net-name').value.trim();
    if (!netName) {
      return;
    }

    const data = await fetchJson(`${restUrl}/sessions`, {
      method: 'POST',
      body: JSON.stringify({ net_name: netName }),
    });

    if (data.session) {
      await loadSessions();
      setActiveSession(data.session);
    }
  });

  document.getElementById('netctrl-add-entry').addEventListener('click', async () => {
    if (!activeSessionId) {
      return;
    }

    const payload = {
      callsign: document.getElementById('netctrl-callsign').value.trim(),
      name: document.getElementById('netctrl-name').value.trim(),
      location: document.getElementById('netctrl-location').value.trim(),
      comments: document.getElementById('netctrl-comments').value.trim(),
    };

    if (!payload.callsign) {
      return;
    }

    await fetchJson(`${restUrl}/sessions/${activeSessionId}/entries`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });

    await loadEntries(activeSessionId);
  });

  document.getElementById('netctrl-close-session').addEventListener('click', async () => {
    if (!activeSessionId) {
      return;
    }

    await fetchJson(`${restUrl}/sessions/${activeSessionId}/close`, {
      method: 'POST',
    });

    activeSessionId = null;
    activeSessionEl.textContent = '';
    await loadSessions();
  });

  loadSessions().catch(() => {
    sessionsList.innerHTML = '<li>Unable to load sessions.</li>';
  });
})();
