/* hq.js - HQ dashboard logic */
(function () {
    'use strict';
    const $ = (s, r = document) => r.querySelector(s);
    const fmt = (n) => {
        if (n > 1024 * 1024) return (n / 1024 / 1024).toFixed(1) + ' MB';
        if (n > 1024) return (n / 1024).toFixed(1) + ' KB';
        return n + ' B';
    };
    const initials = (name) => String(name || '?').trim().split(/\s+/).slice(0, 2).map(x => x[0]).join('').toUpperCase();

    async function load() {
        const payload = await iH4x.fetchJson('api.php?action=hq.stats');
        const users = payload.activeUsers || [];
        $('#activeUsers').innerHTML = users.length ? users.map(u => `
            <div class="presence-row"><span class="presence-dot"></span><span class="user-avatar">${initials(u.display_name)}</span><span class="presence-name"><strong>${iH4x.escapeHtml(u.display_name)}</strong><small>${iH4x.escapeHtml(u.role_id)} · ${iH4x.escapeHtml(iH4x.relTime(u.last_seen))}</small></span></div>
        `).join('') : '<p class="state-text">No active users.</p>';
        $('#activityFeed').innerHTML = (payload.activity || []).map(a => `
            <div class="activity-row"><span class="user-avatar">${initials(a.display_name || a.username || 'iH4x')}</span><span class="activity-main"><strong>${iH4x.escapeHtml(a.action)}</strong><small>${iH4x.escapeHtml(a.display_name || a.username || 'System')} · ${iH4x.escapeHtml(iH4x.relTime(a.created_at))}</small></span></div>
        `).join('') || '<p class="state-text">No activity yet.</p>';
        const s = payload.storage || { total: 0, database: 0, attachments: 0 };
        const dbPct = s.total ? Math.max(3, Math.round((s.database / s.total) * 100)) : 0;
        const attPct = s.total ? Math.max(3, 100 - dbPct) : 0;
        $('#storageBox').innerHTML = `
            <div class="storage-total">${fmt(s.total || 0)}</div>
            <div class="storage-bar"><span class="storage-seg-db" style="width:${dbPct}%"></span><span class="storage-seg-att" style="width:${attPct}%"></span></div>
            <div class="storage-legend"><span>Database: ${fmt(s.database || 0)}</span><span>Attachments: ${fmt(s.attachments || 0)}</span></div>`;
    }

    document.addEventListener('click', (e) => {
        if (e.target.closest('#sidebarFloatToggle')) $('#sidebar').classList.toggle('collapsed');
    });
    iH4x.registerHotkey('mod+\\', () => $('#sidebar').classList.toggle('collapsed'), { allowInInputs: true });
    window.addEventListener('load', load);
})();
