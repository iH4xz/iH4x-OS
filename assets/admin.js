/* admin.js - admin panel logic */
(function () {
    'use strict';
    const $ = (s, r = document) => r.querySelector(s);
    let users = [], roles = [], permissions = [];

    function initials(name) {
        return String(name || '?').trim().split(/\s+/).slice(0, 2).map(x => x[0]).join('').toUpperCase();
    }

    async function load() {
        const [u, r] = await Promise.all([
            iH4x.fetchJson('api.php?action=users.list'),
            iH4x.fetchJson('api.php?action=roles.list')
        ]);
        users = u.users || [];
        roles = r.roles || [];
        permissions = r.permissions || [];
        renderUsers();
        renderRoles();
        renderSettings();
    }

    function renderUsers() {
        $('#usersBody').innerHTML = users.map(user => `
            <tr>
                <td><div class="user-cell"><span class="user-avatar">${iH4x.escapeHtml(initials(user.display_name))}</span><span class="user-meta"><strong>${iH4x.escapeHtml(user.display_name)}</strong><small>@${iH4x.escapeHtml(user.username)}</small></span></div></td>
                <td><span class="role-badge">${iH4x.escapeHtml(user.role_id)}</span></td>
                <td>${iH4x.escapeHtml(iH4x.relTime(user.last_seen) || '-')}</td>
                <td><span class="status-badge ${user.is_active ? 'active' : 'inactive'}">${user.is_active ? 'Active' : 'Inactive'}</span></td>
                <td><div class="row-actions"><button class="mini-btn" data-act="edit" data-id="${user.id}">${iH4x.t('common.rename')}</button><button class="mini-btn" data-act="reset" data-id="${user.id}">${iH4x.t('admin.reset_link')}</button><button class="mini-btn" data-act="toggle" data-id="${user.id}">${iH4x.t('admin.deactivate')}</button></div></td>
            </tr>`).join('');
    }

    function groupedPermissions(selected) {
        const groups = {};
        permissions.forEach(p => { (groups[p.group] ||= []).push(p); });
        return Object.entries(groups).map(([group, rows]) => `
            <div class="perm-group"><div class="perm-group-title">${iH4x.escapeHtml(group)}</div>
            ${rows.map(p => `<label class="perm-row"><span>${iH4x.escapeHtml(p.label)}</span><input type="checkbox" value="${p.key}" ${selected.includes(p.key) ? 'checked' : ''}></label>`).join('')}</div>`).join('');
    }

    function renderRoles() {
        $('#rolesHost').innerHTML = roles.map(role => `
            <div class="role-card" data-role="${role.id}">
                <div class="role-head"><input value="${iH4x.escapeHtml(role.name)}" ${role.is_system ? 'disabled' : ''}><button class="mini-btn" data-act="save-role">${iH4x.t('admin.saved')}</button></div>
                <div class="perm-grid">${groupedPermissions(role.permissions || [])}</div>
            </div>`).join('') + `<button class="btn-primary" id="newRoleBtn">+ ${iH4x.t('admin.roles')}</button>`;
    }

    async function renderSettings() {
        const payload = await iH4x.fetchJson('api.php?action=admin.settings');
        const s = payload.settings || {};
        $('#settingsHost').innerHTML = `
            <div class="settings-card">
                <label class="settings-row"><span>Workspace name</span><input id="workspaceName" value="${iH4x.escapeHtml(s.workspace_name || 'iH4x OS')}"></label>
                <label class="settings-row"><span>Default language</span><select id="defaultLanguage"><option value="en">English</option><option value="ar">العربية</option></select></label>
                <label class="settings-row"><span>Default role</span><select id="defaultRole">${roles.map(r => `<option value="${r.id}">${iH4x.escapeHtml(r.name)}</option>`).join('')}</select></label>
                <label class="settings-row"><span>Max upload MB</span><input id="maxUpload" type="number" min="1" value="${iH4x.escapeHtml(s.max_upload_mb || '20')}"></label>
                <div class="panel-actions"><a class="btn-ghost" href="export.php">Backup workspace</a><button class="btn-primary" id="saveSettings">${iH4x.t('admin.saved')}</button></div>
            </div>`;
        $('#defaultLanguage').value = s.default_language || s.language || 'en';
        $('#defaultRole').value = s.default_role || 'editor';
    }

    function openUser(user) {
        const panel = $('#detailPanel');
        const roleOptions = roles.map(r => `<option value="${r.id}">${iH4x.escapeHtml(r.name)}</option>`).join('');
        panel.innerHTML = `
            <div class="panel-head"><h2>${iH4x.escapeHtml(user ? user.display_name : iH4x.t('admin.invite'))}</h2><button class="icon-btn" data-close><i data-lucide="x"></i></button></div>
            <form class="panel-form" id="userForm">
                <input type="hidden" name="id" value="${user ? user.id : ''}">
                <label>${iH4x.t('auth.username')}<input name="username" value="${iH4x.escapeHtml(user ? user.username : '')}" required></label>
                <label>Display name<input name="display_name" value="${iH4x.escapeHtml(user ? user.display_name : '')}" required></label>
                <label>Email<input name="email" value="${iH4x.escapeHtml(user ? user.email || '' : '')}"></label>
                <label>${iH4x.t('admin.roles')}<select name="role_id">${roleOptions}</select></label>
                <label>${iH4x.t('auth.password')}<input name="password" type="password" placeholder="${user ? 'Leave blank to keep' : ''}"></label>
                ${user ? `<div class="perm-grid user-overrides">${groupedPermissions(user.permissions || [])}</div>` : ''}
                <div class="panel-actions"><button type="button" class="btn-ghost" data-close>${iH4x.t('common.cancel')}</button><button class="btn-primary">${iH4x.t('admin.saved')}</button></div>
            </form>`;
        if (user) panel.querySelector('[name="role_id"]').value = user.role_id;
        panel.classList.add('visible');
        if (window.lucide) lucide.createIcons();
    }

    document.addEventListener('click', async (e) => {
        const tab = e.target.closest('#adminNav [data-tab]');
        if (tab) {
            $('#adminNav').querySelectorAll('li').forEach(li => li.classList.toggle('active', li === tab));
            document.querySelectorAll('.admin-panel').forEach(p => p.classList.toggle('active', p.id === 'tab-' + tab.dataset.tab));
        }
        if (e.target.closest('#inviteBtn')) {
            const role = prompt(iH4x.t('admin.roles') + ' (owner/admin/editor/viewer)', 'editor') || 'editor';
            const email = prompt('Email (optional)', '') || '';
            const display = prompt('Display name (optional)', '') || '';
            const r = await iH4x.fetchJson('api.php?action=users.invite', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ role_id: role, email, display_name: display })
            });
            prompt(iH4x.t('admin.invite'), r.inviteLink);
        }
        if (e.target.closest('[data-close]')) $('#detailPanel').classList.remove('visible');
        const action = e.target.closest('[data-act]');
        if (action) {
            const user = users.find(u => u.id === action.dataset.id);
            if (action.dataset.act === 'edit') openUser(user);
            if (action.dataset.act === 'toggle' && user) {
                await iH4x.fetchJson('api.php?action=users.patch', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: user.id, is_active: !user.is_active }) });
                load();
            }
            if (action.dataset.act === 'reset' && user) {
                const r = await iH4x.fetchJson('api.php?action=users.reset', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: user.id }) });
                prompt(iH4x.t('admin.reset_link'), r.resetLink);
            }
            if (action.dataset.act === 'save-role') {
                const card = action.closest('.role-card');
                const perms = Array.from(card.querySelectorAll('input[type="checkbox"]:checked')).map(i => i.value);
                await iH4x.fetchJson('api.php?action=roles.save', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: card.dataset.role, name: card.querySelector('input').value, permissions: perms }) });
                load();
            }
        }
        if (e.target.closest('#newRoleBtn')) {
            await iH4x.fetchJson('api.php?action=roles.save', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name: 'Custom role', clone_from: 'editor' }) });
            load();
        }
        if (e.target.closest('#saveSettings')) {
            await iH4x.fetchJson('api.php?action=admin.settings', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ workspace_name: $('#workspaceName').value, default_language: $('#defaultLanguage').value, default_role: $('#defaultRole').value, max_upload_mb: $('#maxUpload').value }) });
            iH4x.toast(iH4x.t('admin.saved'), { type: 'success' });
        }
        if (e.target.closest('#sidebarFloatToggle')) $('#sidebar').classList.toggle('collapsed');
    });

    document.addEventListener('submit', async (e) => {
        if (e.target.id !== 'userForm') return;
        e.preventDefault();
        const data = Object.fromEntries(new FormData(e.target).entries());
        if (!data.password) delete data.password;
        await iH4x.fetchJson('api.php?action=users.save', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        if (data.id) {
            const overrides = {};
            e.target.querySelectorAll('.user-overrides input[type="checkbox"]').forEach(input => { overrides[input.value] = input.checked ? 1 : 0; });
            await iH4x.fetchJson('api.php?action=users.patch', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: data.id, permissions: overrides }) });
        }
        $('#detailPanel').classList.remove('visible');
        load();
    });

    iH4x.registerHotkey('mod+\\', () => $('#sidebar').classList.toggle('collapsed'), { allowInInputs: true });
    window.addEventListener('load', load);
})();
