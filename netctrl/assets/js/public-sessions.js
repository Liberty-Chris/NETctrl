(() => {
  const config = window.netctrlPublic || {};
  const ajaxUrl = config.ajaxUrl;
  const pollInterval = Number(config.pollInterval) || 5000;
  const strings = {
    live: 'Live',
    closed: 'Closed',
    noOpenSessions: 'No live sessions right now.',
    noClosedSessions: 'No closed sessions available yet.',
    downloadPdf: 'Download PDF',
    liveUpdates: 'Live updates refresh automatically every few seconds.',
    commentsFallback: 'No comments',
    sessionUnavailable: 'Session unavailable.',
    ...(config.strings || {}),
  };

  if (!ajaxUrl) {
    return;
  }

  const fetchPayload = async (params) => {
    const url = new URL(ajaxUrl, window.location.origin);
    Object.entries(params).forEach(([key, value]) => {
      url.searchParams.set(key, value);
    });

    const response = await fetch(url.toString(), { credentials: 'same-origin' });
    const data = await response.json();

    if (!response.ok || !data?.success) {
      throw new Error(data?.data?.message || strings.sessionUnavailable);
    }

    return data.data;
  };

  const renderEntries = (entries) => {
    if (!Array.isArray(entries) || !entries.length) {
      return '<li class="netctrl-public-empty">No entries recorded yet.</li>';
    }

    return entries
      .map((entry) => `
        <li>
          <strong>${escapeHtml(entry.callsign || '—')}</strong>
          <span>${escapeHtml(entry.name || '—')}</span>
          <span>${escapeHtml(entry.location || '—')}</span>
          <span>${escapeHtml(entry.comments || '—')}</span>
          <span>${escapeHtml(entry.created_at || '—')}</span>
        </li>
      `)
      .join('');
  };

  const renderCard = (session) => {
    const statusClass = session.status === 'open' ? 'live' : 'closed';
    const closedMeta = session.closed_at ? `<span>Closed: ${escapeHtml(session.closed_at)}</span>` : '';
    const pdfLink = session.status === 'closed' && session.pdf_url
      ? `<a class="button button-secondary netctrl-public-session__pdf" href="${encodeURI(session.pdf_url)}">${escapeHtml(strings.downloadPdf)}</a>`
      : '';

    return `
      <article class="netctrl-public-session netctrl-public-session--${statusClass}" data-session-id="${session.id}">
        <div class="netctrl-public-session__header">
          <div>
            <h3>${escapeHtml(session.net_name || '')}</h3>
            <div class="netctrl-public-session__meta">
              <span>Status: ${escapeHtml(session.status_label || session.status || '')}</span>
              <span>Created: ${escapeHtml(session.created_at || session.started_at || '—')}</span>
              ${closedMeta}
            </div>
          </div>
          <span class="netctrl-status-badge netctrl-status-badge--${statusClass}">${escapeHtml(session.status_label || '')}</span>
        </div>
        <div class="netctrl-public-session__body">
          <div class="netctrl-public-session__description">${escapeHtml(session.status_description || strings.liveUpdates)}</div>
          <ul class="netctrl-public-session__entries">${renderEntries(session.entries || [])}</ul>
          ${pdfLink}
        </div>
      </article>
    `;
  };

  const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
  };

  const initPublicSessions = (root) => {
    const openContainer = root.querySelector('[data-netctrl-open-sessions]');
    const closedContainer = root.querySelector('[data-netctrl-closed-sessions]');
    let lastSignature = '';

    const render = (payload) => {
      const signature = JSON.stringify(payload || {});
      if (signature === lastSignature) {
        return;
      }
      lastSignature = signature;

      openContainer.innerHTML = (payload.open_sessions || []).length
        ? payload.open_sessions.map(renderCard).join('')
        : `<p class="netctrl-public-empty">${escapeHtml(strings.noOpenSessions)}</p>`;

      closedContainer.innerHTML = (payload.closed_sessions || []).length
        ? payload.closed_sessions.map(renderCard).join('')
        : `<p class="netctrl-public-empty">${escapeHtml(strings.noClosedSessions)}</p>`;
    };

    const poll = async () => {
      try {
        const payload = await fetchPayload({ action: 'netctrl_public_sessions' });
        render(payload);
      } catch (error) {
        // Keep the existing DOM if polling fails.
      }
    };

    window.setInterval(poll, pollInterval);
    poll();
  };

  const initPublicLog = (root) => {
    const sessionId = root.dataset.sessionId;
    let lastSignature = '';

    const render = (session) => {
      const signature = JSON.stringify(session || {});
      if (signature === lastSignature) {
        return;
      }
      lastSignature = signature;
      root.innerHTML = renderCard(session);
    };

    const poll = async () => {
      try {
        const payload = await fetchPayload({ action: 'netctrl_public_session', session_id: sessionId });
        render(payload.session);
      } catch (error) {
        root.innerHTML = `<p class="netctrl-public-empty">${escapeHtml(error.message || strings.sessionUnavailable)}</p>`;
      }
    };

    window.setInterval(poll, pollInterval);
    poll();
  };

  document.querySelectorAll('[data-netctrl-public-sessions]').forEach(initPublicSessions);
  document.querySelectorAll('[data-netctrl-public-log]').forEach(initPublicLog);
})();
