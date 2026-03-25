(() => {
  const config = window.netctrlConsole || {};
  const restUrl = config.restUrl;
  const nonce = config.nonce;
  const pollInterval = Number(config.pollInterval) || 4000;
  const canDeleteSessions = Boolean(config.canDeleteSessions);
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
    lookupRoster: 'Populated from roster',
    lookupQrz: 'Populated from QRZ',
    sessionActive: 'Session active',
    liveSessionInProgress: 'Live session in progress',
    startDisabled: 'Start Session is unavailable while another live session is open.',
    sessionClosed: 'Session closed.',
    sessionReopened: 'Session reopened.',
    monitoringLive: 'Polling live updates every few seconds.',
    statusLive: 'Live',
    statusClosed: 'Closed',
    reopenSession: 'Reopen Session',
    deleteSession: 'Delete Session',
    deleteSessionConfirm: 'Delete this session and all of its entries?',
    checkinTypeShort: 'Short Time / No Traffic',
    checkinTypeRegular: 'Regular',
    announcementLabel: 'Announcement',
    trafficLabel: 'Traffic',
    announcementDetailsLabel: 'Announcement Details',
    trafficDetailsLabel: 'Traffic Details',
    legacyCommentsLabel: 'Legacy Comments',
    commentsLabel: 'Comments',
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
        // Keep generic message.
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

  const stableStringify = (value) => JSON.stringify(value || null);

  const initConsole = (root) => {
    const sessionsList = root.querySelector('#netctrl-sessions');
    const entriesList = root.querySelector('#netctrl-entries');
    const activeSessionEl = root.querySelector('#netctrl-active-session');
    const activeSessionMetaEl = root.querySelector('#netctrl-active-session-meta');
    const activeStatusBadgeEl = root.querySelector('#netctrl-active-status-badge');
    const messagesEl = root.querySelector('.netctrl-console__messages');
    const sessionDateInput = root.querySelector('#netctrl-session-date');
    const sessionPreviewInput = root.querySelector('#netctrl-session-preview');
    const specialEventWrap = root.querySelector('[data-netctrl-special-event]');
    const eventDescriptionInput = root.querySelector('#netctrl-event-description');
    const sessionTypeInputs = Array.from(root.querySelectorAll('input[name="netctrl-session-type"]'));
    const callsignInput = root.querySelector('#netctrl-callsign');
    const firstNameInput = root.querySelector('#netctrl-first-name');
    const lastNameInput = root.querySelector('#netctrl-last-name');
    const locationInput = root.querySelector('#netctrl-location');
    const checkinTypeInput = root.querySelector('#netctrl-checkin-type');
    const regularFieldsWrap = root.querySelector('#netctrl-regular-fields');
    const hasAnnouncementInput = root.querySelector('#netctrl-has-announcement');
    const hasTrafficInput = root.querySelector('#netctrl-has-traffic');
    const announcementDetailsInput = root.querySelector('#netctrl-announcement-details');
    const trafficDetailsInput = root.querySelector('#netctrl-traffic-details');
    const commentsInput = root.querySelector('#netctrl-comments');
    const lookupStatusEl = root.querySelector('#netctrl-lookup-status');
    const startButton = root.querySelector('#netctrl-start-session');
    const addEntryButton = root.querySelector('#netctrl-add-entry');
    const closeSessionButton = root.querySelector('#netctrl-close-session');
    const reopenSessionButton = root.querySelector('#netctrl-reopen-session');
    const startPanel = root.querySelector('[data-netctrl-start-panel]');
    const startStatusEl = root.querySelector('[data-netctrl-start-status]');
    const startNoteEl = root.querySelector('[data-netctrl-start-note]');
    const activePanel = root.querySelector('[data-netctrl-active-panel]');

    if (
      !sessionsList ||
      !entriesList ||
      !activeSessionEl ||
      !activeStatusBadgeEl ||
      !sessionDateInput ||
      !sessionPreviewInput ||
      !startButton ||
      !addEntryButton ||
      !closeSessionButton ||
      !reopenSessionButton ||
      !callsignInput ||
      !firstNameInput ||
      !lastNameInput ||
      !locationInput ||
      !checkinTypeInput ||
      !regularFieldsWrap ||
      !hasAnnouncementInput ||
      !hasTrafficInput ||
      !announcementDetailsInput ||
      !trafficDetailsInput ||
      !commentsInput ||
      !startPanel ||
      !startStatusEl ||
      !startNoteEl ||
      !activePanel
    ) {
      return;
    }

    let activeSessionId = null;
    let editingEntryId = null;
    let userDismissedActiveSession = false;
    let currentEntries = [];
    let currentSession = null;
    let currentSessions = [];
    let sessionsSignature = '';
    let entriesSignature = '';
    let pollingHandle = null;
    let pollInFlight = false;
    let suppressFieldTracking = false;
    let lookupSequence = 0;
    const fieldState = {
      firstName: { manual: false },
      lastName: { manual: false },
      location: { manual: false },
    };
    const today = formatSessionDate(new Date());

    sessionDateInput.value = today;

    const setMessage = (message = '', type = '') => {
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

    const clearLookupStatus = () => {
      lookupStatusEl.textContent = '';
    };

    const resetFieldState = (key) => {
      fieldState[key].manual = false;
    };

    const clearEntryInputs = () => {
      lookupSequence += 1;
      setTrackedFieldValue(callsignInput, '');
      setTrackedFieldValue(firstNameInput, '');
      setTrackedFieldValue(lastNameInput, '');
      setTrackedFieldValue(locationInput, '');
      checkinTypeInput.value = 'short_time_no_traffic';
      hasAnnouncementInput.checked = false;
      hasTrafficInput.checked = false;
      announcementDetailsInput.value = '';
      trafficDetailsInput.value = '';
      commentsInput.value = '';
      refreshCheckinForm();
      resetFieldState('firstName');
      resetFieldState('lastName');
      resetFieldState('location');
      clearLookupStatus();
      callsignInput.focus();
    };

    const setStatusBadge = (element, status) => {
      element.className = 'netctrl-status-badge';

      if (status === 'open') {
        element.classList.add('netctrl-status-badge--live');
        element.textContent = strings.statusLive;
        return;
      }

      if (status === 'closed') {
        element.classList.add('netctrl-status-badge--closed');
        element.textContent = strings.statusClosed;
        return;
      }

      element.classList.add('netctrl-status-badge--idle');
      element.textContent = 'Idle';
    };

    const updateControlState = (session) => {
      const hasLiveSession = currentSessions.some((item) => item.status === 'open');
      const isOpen = session?.status === 'open';
      const isClosed = session?.status === 'closed';

      startButton.disabled = hasLiveSession;
      sessionTypeInputs.forEach((input) => {
        input.disabled = hasLiveSession;
      });
      if (eventDescriptionInput) {
        eventDescriptionInput.disabled = hasLiveSession;
      }

      addEntryButton.disabled = !isOpen;
      closeSessionButton.disabled = !isOpen;
      reopenSessionButton.disabled = !isClosed || hasLiveSession;
      reopenSessionButton.hidden = !isClosed;
      [callsignInput, firstNameInput, lastNameInput, locationInput, checkinTypeInput, hasAnnouncementInput, hasTrafficInput, announcementDetailsInput, trafficDetailsInput, commentsInput].forEach((input) => {
        input.disabled = !isOpen;
      });

      startPanel.classList.toggle('is-live', hasLiveSession);
      activePanel.classList.toggle('is-live', isOpen);
      activePanel.classList.toggle('is-closed', Boolean(session && session.status === 'closed'));

      setStatusBadge(startStatusEl, hasLiveSession ? 'open' : null);
      setStatusBadge(activeStatusBadgeEl, session?.status || null);

      startNoteEl.textContent = hasLiveSession ? strings.liveSessionInProgress : 'Start a session to begin live logging.';

      if (hasLiveSession) {
        startButton.textContent = strings.sessionActive;
        startButton.setAttribute('aria-disabled', 'true');
      } else {
        startButton.textContent = 'Start Session';
        startButton.removeAttribute('aria-disabled');
      }

      activeSessionMetaEl.textContent = session
        ? (session.status_description || strings.monitoringLive)
        : strings.monitoringLive;
    };

    const reopenSession = async (session) => {
      if (!session || session.status !== 'closed') {
        return null;
      }

      const reopened = await fetchJson(`${restUrl}/sessions/${session.id}/reopen`, {
        method: 'POST',
      });

      await setActiveSession(reopened, { forceEntriesReload: true });
      await loadSessions(true);

      return reopened;
    };

    const deleteSession = async (session) => {
      if (!session || !canDeleteSessions) {
        return;
      }

      if (!window.confirm(strings.deleteSessionConfirm)) {
        return;
      }

      await fetchJson(`${restUrl}/sessions/${session.id}`, {
        method: 'DELETE',
      });

      if (Number(activeSessionId) === Number(session.id)) {
        resetActiveSession();
      }

      await loadSessions(true);
    };

    const renderSessions = (sessions) => {
      const nextSignature = stableStringify(sessions);
      currentSessions = Array.isArray(sessions) ? sessions : [];

      if (nextSignature === sessionsSignature) {
        updateControlState(currentSession);
        return;
      }

      sessionsSignature = nextSignature;
      sessionsList.innerHTML = '';

      if (!currentSessions.length) {
        const item = document.createElement('li');
        item.textContent = strings.noRecentSessions;
        item.className = 'netctrl-list__empty';
        sessionsList.appendChild(item);
        updateControlState(currentSession);
        return;
      }

      currentSessions.forEach((session) => {
        const item = document.createElement('li');
        const title = document.createElement('div');
        const auditLines = Array.isArray(session.recent_session_audit) ? session.recent_session_audit : [];

        item.className = 'netctrl-list__item netctrl-session-list__item';
        item.dataset.sessionId = session.id;
        item.classList.toggle('is-selected', Number(activeSessionId) === Number(session.id));

        title.className = 'netctrl-session-list__title';
        title.textContent = session.net_name;
        item.appendChild(title);

        const meta = document.createElement('div');
        meta.className = 'netctrl-session-list__meta';
        meta.textContent = `${session.status_label || session.status} · ${session.status_description || ''}`;
        item.appendChild(meta);

        const actions = document.createElement('div');
        actions.className = 'netctrl-session-list__actions';

        if (session.status === 'closed') {
          const reopenButton = document.createElement('button');
          reopenButton.type = 'button';
          reopenButton.className = 'button button-small netctrl-session-list__action netctrl-session-list__action--reopen';
          reopenButton.textContent = strings.reopenSession;
          reopenButton.addEventListener('click', async (event) => {
            event.stopPropagation();

            try {
              setMessage('');
              await reopenSession(session);
              setMessage(strings.sessionReopened, 'success');
            } catch (error) {
              setMessage(error.message || strings.requestFailed, 'error');
            }
          });
          actions.appendChild(reopenButton);
        }

        if (canDeleteSessions) {
          const deleteButton = document.createElement('button');
          deleteButton.type = 'button';
          deleteButton.className = 'button button-small netctrl-session-list__action netctrl-session-list__action--delete';
          deleteButton.textContent = strings.deleteSession;
          deleteButton.addEventListener('click', async (event) => {
            event.stopPropagation();

            try {
              setMessage('');
              await deleteSession(session);
            } catch (error) {
              setMessage(error.message || strings.requestFailed, 'error');
            }
          });
          actions.appendChild(deleteButton);
        }

        if (actions.children.length) {
          item.appendChild(actions);
        }

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
          setActiveSession(session, { forceEntriesReload: true }).catch((error) => {
            setMessage(error.message || strings.unableToLoadEntries, 'error');
          });
        });
        sessionsList.appendChild(item);
      });

      updateControlState(currentSession);
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

    const createSummaryNode = (entry) => {
      const node = document.createElement('div');
      node.className = 'netctrl-checkin-summary';

      const appendLine = (label, value) => {
        const line = document.createElement('div');
        const strong = document.createElement('strong');
        strong.textContent = `${label}: `;
        line.appendChild(strong);
        line.appendChild(document.createTextNode(value));
        node.appendChild(line);
      };

      appendLine('Type', entry.checkin_type === 'regular' ? strings.checkinTypeRegular : strings.checkinTypeShort);

      if (entry.checkin_type === 'regular') {
        if (entry.has_announcement) {
          appendLine(strings.announcementLabel, (entry.announcement_details || '').trim() || 'Yes');
        }

        if (entry.has_traffic) {
          appendLine(strings.trafficLabel, (entry.traffic_details || '').trim() || 'Yes');
        }
      }

      const legacyComments = (entry.legacy_comments || entry.comments || '').trim();
      if (legacyComments) {
        appendLine(strings.legacyCommentsLabel, legacyComments);
      }

      return node;
    };

    const createEditButton = (entry) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'button button-small netctrl-entry-action netctrl-entry-action--edit';
      button.setAttribute('aria-label', `${strings.editEntry}: ${entry.callsign}`);
      button.title = strings.editEntry;
      button.textContent = strings.editEntry;
      button.disabled = currentSession?.status !== 'open';
      button.addEventListener('click', () => {
        editingEntryId = entry.id;
        renderEntries(currentEntries);
      });
      return button;
    };

    const loadEntries = async (sessionId, force = false) => {
      if (!sessionId) {
        editingEntryId = null;
        renderEntries([]);
        return;
      }

      const entries = await fetchJson(`${restUrl}/sessions/${sessionId}/entries`);
      const nextSignature = stableStringify(entries);

      if (!force && nextSignature === entriesSignature) {
        return;
      }

      entriesSignature = nextSignature;
      renderEntries(entries);
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

        await loadEntries(activeSessionId, true);
        await loadSessions(true);
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
      button.disabled = currentSession?.status !== 'open';
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

      const checkinTypeEditor = document.createElement('select');
      checkinTypeEditor.innerHTML = `
        <option value="short_time_no_traffic">${strings.checkinTypeShort}</option>
        <option value="regular">${strings.checkinTypeRegular}</option>
      `;
      checkinTypeEditor.value = entry.checkin_type === 'regular' ? 'regular' : 'short_time_no_traffic';

      const regularToggles = document.createElement('div');
      regularToggles.className = 'netctrl-inline-flags';

      const announcementToggle = document.createElement('label');
      announcementToggle.className = 'netctrl-entry-form__checkbox';
      const announcementCheckbox = document.createElement('input');
      announcementCheckbox.type = 'checkbox';
      announcementCheckbox.checked = Boolean(entry.has_announcement);
      const announcementLabel = document.createElement('span');
      announcementLabel.textContent = strings.announcementLabel;
      announcementToggle.appendChild(announcementCheckbox);
      announcementToggle.appendChild(announcementLabel);

      const trafficToggle = document.createElement('label');
      trafficToggle.className = 'netctrl-entry-form__checkbox';
      const trafficCheckbox = document.createElement('input');
      trafficCheckbox.type = 'checkbox';
      trafficCheckbox.checked = Boolean(entry.has_traffic);
      const trafficLabel = document.createElement('span');
      trafficLabel.textContent = strings.trafficLabel;
      trafficToggle.appendChild(trafficCheckbox);
      trafficToggle.appendChild(trafficLabel);

      regularToggles.appendChild(announcementToggle);
      regularToggles.appendChild(trafficToggle);

      const announcementDetailsEditor = document.createElement('input');
      announcementDetailsEditor.type = 'text';
      announcementDetailsEditor.placeholder = strings.announcementDetailsLabel;
      announcementDetailsEditor.value = entry.announcement_details || '';

      const trafficDetailsEditor = document.createElement('input');
      trafficDetailsEditor.type = 'text';
      trafficDetailsEditor.placeholder = strings.trafficDetailsLabel;
      trafficDetailsEditor.value = entry.traffic_details || '';

      const commentsEditor = document.createElement('input');
      commentsEditor.type = 'text';
      commentsEditor.placeholder = strings.commentsLabel;
      commentsEditor.value = entry.comments || '';

      const detailsEditorWrap = document.createElement('div');
      detailsEditorWrap.className = 'netctrl-inline-details';
      detailsEditorWrap.appendChild(regularToggles);
      detailsEditorWrap.appendChild(announcementDetailsEditor);
      detailsEditorWrap.appendChild(trafficDetailsEditor);
      detailsEditorWrap.appendChild(commentsEditor);

      const refreshInlineFields = () => {
        const isRegular = checkinTypeEditor.value === 'regular';
        regularToggles.hidden = !isRegular;
        announcementDetailsEditor.hidden = !isRegular || !announcementCheckbox.checked;
        trafficDetailsEditor.hidden = !isRegular || !trafficCheckbox.checked;
      };

      checkinTypeEditor.addEventListener('change', refreshInlineFields);
      announcementCheckbox.addEventListener('change', refreshInlineFields);
      trafficCheckbox.addEventListener('change', refreshInlineFields);
      refreshInlineFields();

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
          checkin_type: checkinTypeEditor.value,
          has_announcement: checkinTypeEditor.value === 'regular' ? announcementCheckbox.checked : false,
          has_traffic: checkinTypeEditor.value === 'regular' ? trafficCheckbox.checked : false,
          announcement_details: checkinTypeEditor.value === 'regular' && announcementCheckbox.checked ? announcementDetailsEditor.value.trim() : '',
          traffic_details: checkinTypeEditor.value === 'regular' && trafficCheckbox.checked ? trafficDetailsEditor.value.trim() : '',
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
          await loadEntries(activeSessionId, true);
          await loadSessions(true);
        } catch (error) {
          setMessage(error.message || strings.requestFailed, 'error');
        }
      };

      saveButton.addEventListener('click', saveEdit);
      cancelButton.addEventListener('click', () => {
        editingEntryId = null;
        renderEntries(currentEntries);
      });

      [callsignEditor, nameEditor, locationEditor, checkinTypeEditor, announcementDetailsEditor, trafficDetailsEditor, commentsEditor].forEach((input) => {
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
      row.appendChild(createCell(detailsEditorWrap));
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
        row.appendChild(createCell(createSummaryNode(entry)));
        row.appendChild(createCell(actions, 'netctrl-entries-table__cell--actions'));
        entriesList.appendChild(row);
      });
    };

    const updateActiveSessionText = (session) => {
      currentSession = session || null;

      if (!session) {
        activeSessionEl.textContent = strings.selectSession;
        activeSessionMetaEl.textContent = strings.monitoringLive;
        setStatusBadge(activeStatusBadgeEl, null);
        updateControlState(null);
        return;
      }

      activeSessionEl.textContent = `${strings.activeSessionLabel} ${session.net_name}`;
      activeSessionMetaEl.textContent = session.status_description || strings.monitoringLive;
      setStatusBadge(activeStatusBadgeEl, session.status || null);
      updateControlState(session);
    };

    const setActiveSession = async (session, options = {}) => {
      const hasSessionChanged = Number(activeSessionId) !== Number(session.id);
      activeSessionId = session.id;
      userDismissedActiveSession = false;
      if (hasSessionChanged) {
        editingEntryId = null;
      }
      updateActiveSessionText(session);
      await loadEntries(activeSessionId, Boolean(options.forceEntriesReload));
      renderSessions(currentSessions);
    };

    const resetActiveSession = () => {
      activeSessionId = null;
      editingEntryId = null;
      currentSession = null;
      userDismissedActiveSession = true;
      entriesSignature = '';
      clearEntryInputs();
      updateActiveSessionText(null);
      renderEntries([]);
      renderSessions(currentSessions);
    };

    const loadSessions = async (force = false) => {
      const sessions = await fetchJson(`${restUrl}/sessions`);
      renderSessions(sessions);
      const selectedSession = activeSessionId ? sessions.find((session) => Number(session.id) === Number(activeSessionId)) : null;
      const openSession = sessions.find((session) => session.status === 'open') || null;

      if (selectedSession) {
        const changedSelection = stableStringify(selectedSession) !== stableStringify(currentSession);
        await setActiveSession(selectedSession, { forceEntriesReload: force || changedSelection });
        return;
      }

      if (!userDismissedActiveSession && openSession) {
        await setActiveSession(openSession, { forceEntriesReload: force || true });
        return;
      }

      if (!userDismissedActiveSession) {
        updateActiveSessionText(null);
        renderEntries([]);
      } else {
        updateControlState(currentSession);
      }
    };

    const applyLookupValue = (input, key, value) => {
      if (!value) {
        return;
      }

      const state = fieldState[key];
      const currentValue = input.value.trim();
      const canOverwrite = currentValue === '' || !state.manual;

      if (!canOverwrite) {
        return;
      }

      setTrackedFieldValue(input, value);
      state.manual = false;
    };

    const lookupCallsign = async () => {
      const normalizedCallsign = normalizeCallsign(callsignInput.value);
      setTrackedFieldValue(callsignInput, normalizedCallsign);
      clearLookupStatus();

      if (!normalizedCallsign) {
        return;
      }

      const requestId = ++lookupSequence;

      try {
        const result = await fetchJson(`${restUrl}/lookup/callsign?callsign=${encodeURIComponent(normalizedCallsign)}`, {
          method: 'GET',
        });

        if (requestId !== lookupSequence || normalizeCallsign(callsignInput.value) !== normalizedCallsign || !result?.found) {
          return;
        }

        applyLookupValue(firstNameInput, 'firstName', result.first_name || '');
        applyLookupValue(lastNameInput, 'lastName', result.last_name || '');
        applyLookupValue(locationInput, 'location', result.location || '');

        lookupStatusEl.textContent = result.source === 'qrz' ? strings.lookupQrz : strings.lookupRoster;
      } catch (error) {
        clearLookupStatus();
      }
    };

    const startSession = async () => {
      const netName = getSessionPreview();
      if (!netName || startButton.disabled) {
        if (startButton.disabled) {
          setMessage(strings.startDisabled, 'error');
        }
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
          await setActiveSession(data.session, { forceEntriesReload: true });
          await loadSessions(true);
          setMessage(strings.liveSessionInProgress, 'success');
        }
      } catch (error) {
        setMessage(error.message || strings.requestFailed, 'error');
      }
    };

    const addEntry = async () => {
      if (!activeSessionId || currentSession?.status !== 'open') {
        setMessage(strings.selectSession, 'error');
        return;
      }

      const payload = {
        callsign: normalizeCallsign(callsignInput.value),
        name: [firstNameInput.value.trim(), lastNameInput.value.trim()].filter(Boolean).join(' '),
        location: locationInput.value.trim(),
        checkin_type: checkinTypeInput.value,
        has_announcement: checkinTypeInput.value === 'regular' ? hasAnnouncementInput.checked : false,
        has_traffic: checkinTypeInput.value === 'regular' ? hasTrafficInput.checked : false,
        announcement_details: checkinTypeInput.value === 'regular' && hasAnnouncementInput.checked ? announcementDetailsInput.value.trim() : '',
        traffic_details: checkinTypeInput.value === 'regular' && hasTrafficInput.checked ? trafficDetailsInput.value.trim() : '',
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
        await loadEntries(activeSessionId, true);
        await loadSessions(true);
      } catch (error) {
        setMessage(error.message || strings.requestFailed, 'error');
      }
    };

    const poll = async () => {
      if (pollInFlight) {
        return;
      }

      pollInFlight = true;

      try {
        await loadSessions(false);
        if (activeSessionId) {
          await loadEntries(activeSessionId, false);
        }
      } catch (error) {
        setMessage(error.message || strings.unableToLoadSessions, 'error');
      } finally {
        pollInFlight = false;
      }
    };

    const startPolling = () => {
      if (pollingHandle) {
        window.clearInterval(pollingHandle);
      }

      pollingHandle = window.setInterval(poll, pollInterval);
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

    const markFieldAsManual = (key, input) => {
      if (suppressFieldTracking) {
        return;
      }

      fieldState[key].manual = input.value.trim() !== '';
    };

    firstNameInput.addEventListener('input', () => {
      markFieldAsManual('firstName', firstNameInput);
    });

    lastNameInput.addEventListener('input', () => {
      markFieldAsManual('lastName', lastNameInput);
    });

    locationInput.addEventListener('input', () => {
      markFieldAsManual('location', locationInput);
    });

    callsignInput.addEventListener('input', clearLookupStatus);
    callsignInput.addEventListener('blur', lookupCallsign);
    callsignInput.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        lookupCallsign();
      }
    });

    startButton.addEventListener('click', startSession);
    addEntryButton.addEventListener('click', addEntry);

    const refreshCheckinForm = () => {
      const isRegular = checkinTypeInput.value === 'regular';
      regularFieldsWrap.hidden = !isRegular;
      announcementDetailsInput.hidden = !isRegular || !hasAnnouncementInput.checked;
      trafficDetailsInput.hidden = !isRegular || !hasTrafficInput.checked;

      if (!isRegular) {
        hasAnnouncementInput.checked = false;
        hasTrafficInput.checked = false;
        announcementDetailsInput.value = '';
        trafficDetailsInput.value = '';
      } else {
        if (!hasAnnouncementInput.checked) {
          announcementDetailsInput.value = '';
        }

        if (!hasTrafficInput.checked) {
          trafficDetailsInput.value = '';
        }
      }
    };

    checkinTypeInput.addEventListener('change', refreshCheckinForm);
    hasAnnouncementInput.addEventListener('change', refreshCheckinForm);
    hasTrafficInput.addEventListener('change', refreshCheckinForm);

    [firstNameInput, lastNameInput, locationInput, announcementDetailsInput, trafficDetailsInput, commentsInput].forEach((input) => {
      input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          addEntry();
        }
      });
    });

    closeSessionButton.addEventListener('click', async () => {
      if (!activeSessionId || currentSession?.status !== 'open') {
        return;
      }

      try {
        setMessage('');
        await fetchJson(`${restUrl}/sessions/${activeSessionId}/close`, {
          method: 'POST',
        });

        setMessage(strings.sessionClosed, 'success');
        resetActiveSession();
        await loadSessions(true);
      } catch (error) {
        setMessage(error.message || strings.requestFailed, 'error');
      }
    });

    reopenSessionButton.addEventListener('click', async () => {
      if (!currentSession || currentSession.status !== 'closed') {
        return;
      }

      try {
        setMessage('');
        await reopenSession(currentSession);
        setMessage(strings.sessionReopened, 'success');
      } catch (error) {
        setMessage(error.message || strings.requestFailed, 'error');
      }
    });

    refreshSessionPreview();
    refreshCheckinForm();
    updateControlState(null);
    startPolling();

    loadSessions(true).catch((error) => {
      setMessage(error.message || strings.unableToLoadSessions, 'error');
      sessionsList.innerHTML = `<li class="netctrl-list__empty">${strings.unableToLoadSessions}</li>`;
      updateActiveSessionText(null);
      renderEntries([]);
    });
  };

  document.querySelectorAll('[data-netctrl-console-root]').forEach(initConsole);
})();
