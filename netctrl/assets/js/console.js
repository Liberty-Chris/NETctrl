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
    sessionPreviewFallback: 'Choose a session type to generate the session name.',
    editEntry: 'Edit entry',
    deleteEntry: 'Delete entry',
    deleteEntryConfirm: 'Delete this entry?',
    saveEntry: 'Save',
    cancelEdit: 'Cancel',
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

  const formatSessionDate = (date) => {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const year = String(date.getFullYear()).slice(-2);
    return `${month}/${day}/${year}`;
  };

  const initConsole = (root) => {
    const sessionsList = root.querySelector('#netctrl-sessions');
    const entriesList = root.querySelector('#netctrl-entries');
    const activeSessionEl = root.querySelector('#netctrl-active-session');
    const messagesEl = root.querySelector('.netctrl-console__messages');
    const sessionDateInput = root.querySelector('#netctrl-session-date');
    const sessionPreviewInput = root.querySelector('#netctrl-session-preview');
    const specialEventWrap = root.querySelector('[data-netctrl-special-event]');
    const eventDescriptionInput = root.querySelector('#netctrl-event-description');
    const sessionTypeInputs = Array.from(root.querySelectorAll('input[name="netctrl-session-type"]'));
    const callsignInput = root.querySelector('#netctrl-callsign');
    const nameInput = root.querySelector('#netctrl-name');
    const locationInput = root.querySelector('#netctrl-location');
    const commentsInput = root.querySelector('#netctrl-comments');
    const startButton = root.querySelector('#netctrl-start-session');
    const addEntryButton = root.querySelector('#netctrl-add-entry');
    const closeSessionButton = root.querySelector('#netctrl-close-session');

    if (
      !sessionsList ||
      !entriesList ||
      !activeSessionEl ||
      !sessionDateInput ||
      !sessionPreviewInput ||
      !startButton ||
      !addEntryButton ||
      !closeSessionButton ||
      !callsignInput ||
      !nameInput ||
      !locationInput ||
      !commentsInput
    ) {
      return;
    }

    let activeSessionId = null;
    let editingEntryId = null;
    let userDismissedActiveSession = false;
    let currentEntries = [];
    let suppressFieldTracking = false;
    let nameEditToken = 0;
    let locationEditToken = 0;
    let lookupSequence = 0;
    const today = formatSessionDate(new Date());

    sessionDateInput.value = today;

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

    const getSelectedSessionType = () => sessionTypeInputs.find((input) => input.checked)?.value || '';

    const getSessionPreview = () => {
      const type = getSelectedSessionType();
      const description = (eventDescriptionInput?.value || '').trim().replace(/\s+/g, ' ');

      if (!type) {
        return '';
      }

      const suffix = type === 'SE' && description ? ` ${description}` : '';
      return `${today} ${type}${suffix}`.trim();
    };

    const refreshSessionPreview = () => {
      const type = getSelectedSessionType();
      const preview = getSessionPreview();
      const showSpecialEvent = type === 'SE';

      sessionTypeInputs.forEach((input) => {
        input.closest('.netctrl-session-types__option')?.classList.toggle('is-selected', input.checked);
      });

      if (specialEventWrap) {
        specialEventWrap.hidden = !showSpecialEvent;
      }

      if (!showSpecialEvent && eventDescriptionInput) {
        eventDescriptionInput.value = '';
      }

      sessionPreviewInput.value = preview || strings.sessionPreviewFallback;
    };

    const normalizeCallsign = (value) => value.trim().toUpperCase();

    const setTrackedFieldValue = (input, value) => {
      suppressFieldTracking = true;
      input.value = value;
      suppressFieldTracking = false;
    };

    const clearEntryInputs = () => {
      lookupSequence += 1;
      setTrackedFieldValue(callsignInput, '');
      setTrackedFieldValue(nameInput, '');
      setTrackedFieldValue(locationInput, '');
      commentsInput.value = '';
      nameEditToken = 0;
      locationEditToken = 0;
      callsignInput.focus();
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
        const title = document.createElement('div');
        const auditLines = Array.isArray(session.recent_session_audit) ? session.recent_session_audit : [];

        item.className = 'netctrl-list__item netctrl-session-list__item';
        item.dataset.sessionId = session.id;

        title.className = 'netctrl-session-list__title';
        title.textContent = `${session.net_name} (${session.status})`;
        item.appendChild(title);

        if (auditLines.length) {
          const auditList = document.createElement('div');
          auditList.className = 'netctrl-session-list__audit';

          auditLines.forEach((line) => {
            const auditLine = document.createElement('div');
            auditLine.className = 'netctrl-session-list__audit-line';
            auditLine.textContent = line;
            auditList.appendChild(auditLine);
          });

          item.appendChild(auditList);
        }

        item.addEventListener('click', () => {
          setActiveSession(session).catch((error) => {
            setMessage(error.message || strings.unableToLoadEntries, 'error');
          });
        });
        sessionsList.appendChild(item);
      });
    };

    const createCell = (content, className = '') => {
      const cell = document.createElement('div');
      cell.className = `netctrl-entries-table__cell${className ? ` ${className}` : ''}`;
      if (content instanceof Node) {
        cell.appendChild(content);
      } else {
        cell.textContent = content || '—';
      }
      return cell;
    };

    const createEditButton = (entry) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'button button-small netctrl-entry-action netctrl-entry-action--edit';
      button.setAttribute('aria-label', `${strings.editEntry}: ${entry.callsign}`);
      button.title = strings.editEntry;
      button.textContent = strings.editEntry;
      button.addEventListener('click', () => {
        editingEntryId = entry.id;
        renderEntries(currentEntries);
      });
      return button;
    };

    const deleteEntry = async (entry) => {
      if (!window.confirm(strings.deleteEntryConfirm)) {
        return;
      }

      try {
        setMessage('');
        await fetchJson(`${restUrl}/entries/${entry.id}`, {
          method: 'DELETE',
        });

        if (editingEntryId === entry.id) {
          editingEntryId = null;
        }

        await loadEntries(activeSessionId);
      } catch (error) {
        setMessage(error.message || strings.requestFailed, 'error');
      }
    };

    const createDeleteButton = (entry) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'button button-small netctrl-entry-action netctrl-entry-action--delete';
      button.setAttribute('aria-label', `${strings.deleteEntry}: ${entry.callsign}`);
      button.title = strings.deleteEntry;
      button.textContent = strings.deleteEntry;
      button.addEventListener('click', () => {
        deleteEntry(entry);
      });
      return button;
    };

    const createInlineEditor = (entry) => {
      const row = document.createElement('li');
      row.className = 'netctrl-entries-table__row netctrl-entries-table__row--editing';
      row.setAttribute('role', 'row');
      row.dataset.entryId = entry.id;

      const callsignEditor = document.createElement('input');
      callsignEditor.type = 'text';
      callsignEditor.value = entry.callsign || '';

      const nameEditor = document.createElement('input');
      nameEditor.type = 'text';
      nameEditor.value = entry.name || '';

      const locationEditor = document.createElement('input');
      locationEditor.type = 'text';
      locationEditor.value = entry.location || '';

      const commentsEditor = document.createElement('input');
      commentsEditor.type = 'text';
      commentsEditor.value = entry.comments || '';

      const actions = document.createElement('div');
      actions.className = 'netctrl-entry-actions';

      const saveButton = document.createElement('button');
      saveButton.type = 'button';
      saveButton.className = 'button button-small button-primary';
      saveButton.textContent = strings.saveEntry;

      const cancelButton = document.createElement('button');
      cancelButton.type = 'button';
      cancelButton.className = 'button button-small';
      cancelButton.textContent = strings.cancelEdit;

      const saveEdit = async () => {
        const payload = {
          callsign: normalizeCallsign(callsignEditor.value),
          name: nameEditor.value.trim(),
          location: locationEditor.value.trim(),
          comments: commentsEditor.value.trim(),
        };

        if (!payload.callsign) {
          callsignEditor.focus();
          return;
        }

        callsignEditor.value = payload.callsign;

        try {
          setMessage('');
          await fetchJson(`${restUrl}/entries/${entry.id}`, {
            method: 'PUT',
            body: JSON.stringify(payload),
          });
          editingEntryId = null;
          await loadEntries(activeSessionId);
        } catch (error) {
          setMessage(error.message || strings.requestFailed, 'error');
        }
      };

      saveButton.addEventListener('click', saveEdit);
      cancelButton.addEventListener('click', () => {
        editingEntryId = null;
        renderEntries(currentEntries);
      });

      [callsignEditor, nameEditor, locationEditor, commentsEditor].forEach((input) => {
        input.addEventListener('keydown', (event) => {
          if (event.key === 'Enter') {
            event.preventDefault();
            saveEdit();
          }

          if (event.key === 'Escape') {
            editingEntryId = null;
            renderEntries(currentEntries);
          }
        });
      });

      actions.appendChild(saveButton);
      actions.appendChild(cancelButton);

      row.appendChild(createCell(callsignEditor));
      row.appendChild(createCell(nameEditor));
      row.appendChild(createCell(locationEditor));
      row.appendChild(createCell(commentsEditor));
      row.appendChild(createCell(actions, 'netctrl-entries-table__cell--actions'));

      return row;
    };

    const renderEntries = (entries) => {
      currentEntries = Array.isArray(entries) ? entries : [];
      entriesList.innerHTML = '';

      if (!currentEntries.length) {
        const item = document.createElement('li');
        item.textContent = strings.noEntries;
        item.className = 'netctrl-list__empty';
        entriesList.appendChild(item);
        return;
      }

      currentEntries.forEach((entry) => {
        if (editingEntryId === entry.id) {
          entriesList.appendChild(createInlineEditor(entry));
          return;
        }

        const row = document.createElement('li');
        row.className = 'netctrl-entries-table__row';
        row.setAttribute('role', 'row');
        row.dataset.entryId = entry.id;
        const actions = document.createElement('div');
        actions.className = 'netctrl-entry-actions';

        actions.appendChild(createEditButton(entry));
        actions.appendChild(createDeleteButton(entry));

        row.appendChild(createCell(entry.callsign));
        row.appendChild(createCell(entry.name));
        row.appendChild(createCell(entry.location));
        row.appendChild(createCell(entry.comments));
        row.appendChild(createCell(actions, 'netctrl-entries-table__cell--actions'));
        entriesList.appendChild(row);
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
        editingEntryId = null;
        renderEntries([]);
        return;
      }

      const entries = await fetchJson(`${restUrl}/sessions/${sessionId}/entries`);
      renderEntries(entries);
    };

    const setActiveSession = async (session) => {
      activeSessionId = session.id;
      userDismissedActiveSession = false;
      editingEntryId = null;
      updateActiveSessionText(session);
      await loadEntries(activeSessionId);
    };

    const resetActiveSession = () => {
      activeSessionId = null;
      editingEntryId = null;
      userDismissedActiveSession = true;
      clearEntryInputs();
      updateActiveSessionText(null);
      renderEntries([]);
    };

    const loadSessions = async () => {
      const sessions = await fetchJson(`${restUrl}/sessions`);
      renderSessions(sessions);
      const selectedSession = activeSessionId ? sessions.find((session) => session.id === activeSessionId) : null;

      if (selectedSession) {
        await setActiveSession(selectedSession);
        return;
      }

      if (!userDismissedActiveSession) {
        updateActiveSessionText(null);
        renderEntries([]);
      }
    };

    const lookupRosterEntry = async () => {
      const normalizedCallsign = normalizeCallsign(callsignInput.value);
      setTrackedFieldValue(callsignInput, normalizedCallsign);

      if (!normalizedCallsign) {
        return;
      }

      const requestId = ++lookupSequence;
      const nameTokenAtRequest = nameEditToken;
      const locationTokenAtRequest = locationEditToken;

      try {
        const result = await fetchJson(`${restUrl}/roster/lookup?callsign=${encodeURIComponent(normalizedCallsign)}`, {
          method: 'GET',
        });

        if (
          requestId !== lookupSequence ||
          normalizeCallsign(callsignInput.value) !== normalizedCallsign ||
          !result?.found
        ) {
          return;
        }

        if (nameEditToken === nameTokenAtRequest && result.name) {
          setTrackedFieldValue(nameInput, result.name);
        }

        if (locationEditToken === locationTokenAtRequest && result.location) {
          setTrackedFieldValue(locationInput, result.location);
        }
      } catch (error) {
        setMessage(error.message || strings.requestFailed, 'error');
      }
    };

    const startSession = async () => {
      const netName = getSessionPreview();
      if (!netName) {
        return;
      }

      try {
        setMessage('');
        const data = await fetchJson(`${restUrl}/sessions`, {
          method: 'POST',
          body: JSON.stringify({ net_name: netName }),
        });

        sessionTypeInputs.forEach((input) => {
          input.checked = false;
        });
        if (eventDescriptionInput) {
          eventDescriptionInput.value = '';
        }
        refreshSessionPreview();

        if (data.session) {
          await setActiveSession(data.session);
          await loadSessions();
        }
      } catch (error) {
        setMessage(error.message || strings.requestFailed, 'error');
      }
    };

    const addEntry = async () => {
      if (!activeSessionId) {
        setMessage(strings.selectSession, 'error');
        return;
      }

      const payload = {
        callsign: normalizeCallsign(callsignInput.value),
        name: nameInput.value.trim(),
        location: locationInput.value.trim(),
        comments: commentsInput.value.trim(),
      };

      if (!payload.callsign) {
        callsignInput.focus();
        return;
      }

      setTrackedFieldValue(callsignInput, payload.callsign);

      try {
        setMessage('');
        await fetchJson(`${restUrl}/sessions/${activeSessionId}/entries`, {
          method: 'POST',
          body: JSON.stringify(payload),
        });

        clearEntryInputs();
        await loadEntries(activeSessionId);
      } catch (error) {
        setMessage(error.message || strings.requestFailed, 'error');
      }
    };

    sessionTypeInputs.forEach((input) => {
      input.addEventListener('change', refreshSessionPreview);
    });

    if (eventDescriptionInput) {
      eventDescriptionInput.addEventListener('input', refreshSessionPreview);
      eventDescriptionInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          startSession();
        }
      });
    }

    nameInput.addEventListener('input', () => {
      if (!suppressFieldTracking) {
        nameEditToken += 1;
      }
    });

    locationInput.addEventListener('input', () => {
      if (!suppressFieldTracking) {
        locationEditToken += 1;
      }
    });

    callsignInput.addEventListener('blur', () => {
      lookupRosterEntry();
    });

    callsignInput.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        lookupRosterEntry();
      }
    });

    startButton.addEventListener('click', startSession);
    addEntryButton.addEventListener('click', addEntry);

    [nameInput, locationInput, commentsInput].forEach((input) => {
      input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          addEntry();
        }
      });
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

        resetActiveSession();
        await loadSessions();
      } catch (error) {
        setMessage(error.message || strings.requestFailed, 'error');
      }
    });

    refreshSessionPreview();

    loadSessions().catch((error) => {
      setMessage(error.message || strings.unableToLoadSessions, 'error');
      sessionsList.innerHTML = `<li class="netctrl-list__empty">${strings.unableToLoadSessions}</li>`;
      updateActiveSessionText(null);
      renderEntries([]);
    });
  };

  document.querySelectorAll('[data-netctrl-console-root]').forEach(initConsole);
})();
