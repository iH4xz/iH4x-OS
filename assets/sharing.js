/* sharing.js - share panel logic */
(function () {
    'use strict';
    const state = { users: [] };
    function esc(s) { return (window.iH4x ? iH4x.escapeHtml(s) : String(s)); }

    async function loadUsers() {
        const payload = await iH4x.fetchJson('api.php?action=sharing.users');
        state.users = payload.users || [];
    }

    function panelEl() {
        return document.getElementById('shareDrawer');
    }

    function ensurePanel() {
        let el = panelEl();
        if (el) return el;
        el = document.createElement('div');
        el.className = 'drawer share-panel';
        el.id = 'shareDrawer';
        el.innerHTML = `
            <div class="drawer-head"><h3>${esc(iH4x.t('share.title'))}</h3><button class="icon-btn" data-close><i data-lucide="x"></i></button></div>
            <div class="share-section"><div class="share-title">${esc(iH4x.t('share.with_people'))}</div><div id="sharePeople"></div></div>
            <div class="share-section"><div class="share-title">${esc(iH4x.t('share.link'))}</div><div id="shareLink"></div></div>`;
        document.body.appendChild(el);
        el.addEventListener('click', (e) => {
            if (e.target.closest('[data-close]')) el.classList.remove('visible');
        });
        if (window.lucide) lucide.createIcons();
        return el;
    }

    async function render(noteId) {
        const payload = await iH4x.fetchJson('api.php?action=share.get&id=' + encodeURIComponent(noteId));
        const collaborators = payload.collaborators || [];
        const map = new Map(collaborators.map(c => [c.user_id, c]));
        const people = state.users.filter(u => window.__user && u.id !== window.__user.id).map(user => {
            const c = map.get(user.id);
            return `<div class="collaborator-row" data-user="${user.id}">
                <span class="user-avatar">${esc((user.display_name || user.username || '?').slice(0, 2).toUpperCase())}</span>
                <span style="flex:1"><strong>${esc(user.display_name)}</strong><br><small>@${esc(user.username)}</small></span>
                <select class="access-select" data-access>
                    <option value="">-</option>
                    <option value="view" ${c && c.access_level === 'view' ? 'selected' : ''}>${esc(iH4x.t('share.can_view'))}</option>
                    <option value="edit" ${c && c.access_level === 'edit' ? 'selected' : ''}>${esc(iH4x.t('share.can_edit'))}</option>
                </select>
                ${c ? `<button class="icon-btn" data-remove title="${esc(iH4x.t('share.remove'))}"><i data-lucide="x"></i></button>` : ''}
            </div>`;
        }).join('');
        document.getElementById('sharePeople').innerHTML = people || '<p class="state-text">-</p>';

        const linkToken = payload.link && payload.link.token ? payload.link.token : '';
        let base = payload.baseUrl;
        if (base) {
            base = base.replace(/\/+$/, '');
        } else {
            base = (location.origin + location.pathname.replace(/[^/]+$/, '')).replace(/\/+$/, '');
        }
        const linkUrl = linkToken ? (base + '/login.php?share=' + encodeURIComponent(linkToken)) : '';
        document.getElementById('shareLink').innerHTML = `
            <label class="collaborator-row"><input type="checkbox" id="shareEnableLink" ${linkToken ? 'checked' : ''}> ${esc(iH4x.t('share.enable_link'))}</label>
            <div class="share-link-row"><input id="shareLinkInput" value="${esc(linkUrl)}" readonly><button class="btn-ghost" id="copyShareLink">${esc(iH4x.t('share.copy_link'))}</button></div>`;
        if (window.lucide) lucide.createIcons();

        document.getElementById('sharePeople').onclick = async (e) => {
            const row = e.target.closest('.collaborator-row');
            if (!row) return;
            const userId = row.dataset.user;
            if (e.target.closest('[data-remove]')) {
                await iH4x.fetchJson('api.php?action=share.save', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ note_id: noteId, remove_user_id: userId }) });
                return render(noteId);
            }
        };
        document.getElementById('sharePeople').onchange = async (e) => {
            const select = e.target.closest('[data-access]');
            if (!select) return;
            const row = select.closest('.collaborator-row');
            if (!select.value) return;
            await iH4x.fetchJson('api.php?action=share.save', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ note_id: noteId, user_id: row.dataset.user, access_level: select.value }) });
            render(noteId);
        };
        document.getElementById('shareEnableLink').onchange = async (e) => {
            await iH4x.fetchJson('api.php?action=share.save', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ note_id: noteId, enable_link: e.target.checked ? 1 : 0 }) });
            render(noteId);
        };
        document.getElementById('copyShareLink').onclick = async () => {
            const input = document.getElementById('shareLinkInput');
            await navigator.clipboard.writeText(input.value || '');
            iH4x.toast(iH4x.t('share.link_copied'), { type: 'success' });
        };
    }

    async function openPanel(noteId) {
        await loadUsers();
        const panel = ensurePanel();
        panel.classList.add('visible');
        await render(noteId);
    }

    window.iH4xSharing = { openPanel };
})();
