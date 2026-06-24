/* collab.js - SSE client + presence */
(function () {
    'use strict';
    const state = { es: null, presenceTimer: null, currentNoteId: null };

    function dispatch(name, detail) {
        document.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
    }

    function startStream() {
        if (!window.__user || state.es) return;
        const es = new EventSource('stream.php');
        state.es = es;
        es.onmessage = (evt) => {
            let msg = null;
            try { msg = JSON.parse(evt.data); } catch (_) {}
            if (!msg || !msg.type) return;
            if (msg.type === 'note.updated' || msg.type === 'note.created' || msg.type === 'note.deleted') {
                dispatch('ih4x:refresh-notes', msg);
            }
            if (msg.type === 'folder.updated') {
                dispatch('ih4x:refresh-folders', msg);
            }
            if (msg.type === 'note.updated' && state.currentNoteId && msg.entity_id === state.currentNoteId) {
                window.postMessage({ type: 'ih4x:note-remote-update', payload: msg }, '*');
            }
        };
    }

    function stopStream() {
        if (!state.es) return;
        state.es.close();
        state.es = null;
    }

    async function ping(action) {
        if (!state.currentNoteId) return;
        try {
            await iH4x.fetchJson('api.php?action=presence.ping', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ entity_type: 'note', entity_id: state.currentNoteId, action: action || 'editing' })
            });
            const list = await iH4x.fetchJson('api.php?action=presence.list&id=' + encodeURIComponent(state.currentNoteId));
            window.postMessage({ type: 'ih4x:presence-update', noteId: state.currentNoteId, presence: list.presence || [] }, '*');
        } catch (_) {}
    }

    async function leave() {
        if (!state.currentNoteId) return;
        try {
            await iH4x.fetchJson('api.php?action=presence.leave', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ entity_id: state.currentNoteId })
            });
        } catch (_) {}
    }

    function startPresence(noteId) {
        state.currentNoteId = noteId;
        clearInterval(state.presenceTimer);
        ping('editing');
        state.presenceTimer = setInterval(() => ping('editing'), 10000);
    }

    function stopPresence() {
        clearInterval(state.presenceTimer);
        state.presenceTimer = null;
        leave();
        state.currentNoteId = null;
    }

    window.addEventListener('message', (e) => {
        if (e.data && e.data.type === 'ih4x:editor-opened' && e.data.id) startPresence(String(e.data.id));
        if (e.data && e.data.type === 'ih4x:editor-back') stopPresence();
    });
    document.addEventListener('ih4x:refresh-notes', () => {});
    window.addEventListener('beforeunload', () => { stopPresence(); stopStream(); });
    startStream();
    window.iH4xCollab = { startPresence, stopPresence };
})();
