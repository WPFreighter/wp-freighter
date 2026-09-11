/* WP Freighter admin. Plain JS against the wp-freighter/v1 REST routes. State comes from wpFreighterSettings. */
(function () {
	'use strict';

	var S = window.wpFreighterSettings || {};
	var root = document.getElementById('wpf');
	if (!root) return;

	var state = {
		config: Object.assign({ files: 'shared', domain_mapping: 'off', errors: {} }, S.configurations || {}),
		sites: (S.stacked_sites || []).map(function (s) { return Object.assign({}, s); }),
		currentId: String(S.current_site_id || ''),
		saved: null, // snapshot for Discard
		dirty: false,
		dirtyIds: {}
	};
	snapshot();

	var $ = function (id) { return document.getElementById(id); };
	var el = {
		status: $('wpf-status'), notices: $('wpf-notices'), thead: $('wpf-thead'), rows: $('wpf-rows'), empty: $('wpf-empty'),
		count: $('wpf-count'), sitesHint: $('wpf-sites-hint'), savebar: $('wpf-savebar'), savebarText: $('wpf-savebar-text'),
		progress: $('wpf-progress'), toast: $('wpf-toast'), loginMain: $('wpf-login-main'),
		dNew: $('wpf-dialog-new'), fNew: $('wpf-form-new'), dClone: $('wpf-dialog-clone'), fClone: $('wpf-form-clone'),
		dDelete: $('wpf-dialog-delete'), fDelete: $('wpf-form-delete')
	};

	// ---------- helpers ----------
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function mapping() { return state.config.domain_mapping === 'on'; }
	function siteLabel(s) { return (mapping() ? s.domain : s.name) || 'Site ' + s.stacked_site_id; }
	function fmtDate(ts) { var d = new Date(Number(ts) * 1000); return isNaN(d) ? '' : d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }); }
	function password() { var a = new Uint8Array(12); crypto.getRandomValues(a); return Array.prototype.map.call(a, function (b) { return 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789'[b % 54]; }).join(''); }
	function snapshot() { state.saved = JSON.stringify({ config: stripErrors(state.config), sites: state.sites }); }
	function stripErrors(c) { var o = Object.assign({}, c); delete o.errors; return o; }
	function setDirty() {
		state.dirty = JSON.stringify({ config: stripErrors(state.config), sites: state.sites }) !== state.saved;
		if (!state.dirty) state.dirtyIds = {};
		el.savebar.hidden = !state.dirty;
		var n = Object.keys(state.dirtyIds).length;
		var cfgChanged = JSON.stringify(stripErrors(state.config)) !== JSON.stringify(JSON.parse(state.saved).config);
		el.savebarText.textContent = [n ? n + ' site' + (n === 1 ? '' : 's') + ' edited' : '', cfgChanged ? 'settings changed' : ''].filter(Boolean).join(', ') || 'Unsaved changes';
	}

	var toastTimer;
	function toast(msg, isError) {
		el.toast.textContent = msg; el.toast.classList.toggle('is-error', !!isError); el.toast.hidden = false;
		clearTimeout(toastTimer); toastTimer = setTimeout(function () { el.toast.hidden = true; }, isError ? 6000 : 2600);
	}

	var busyCount = 0;
	function busy(on) {
		busyCount = Math.max(0, busyCount + (on ? 1 : -1));
		if (busyCount) { el.progress.classList.remove('is-done'); el.progress.classList.add('is-busy'); }
		else { el.progress.classList.remove('is-busy'); el.progress.classList.add('is-done'); }
	}

	function api(path, body, btn) {
		busy(true); if (btn) btn.classList.add('is-busy');
		return fetch(S.root + path, {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': S.nonce },
			body: JSON.stringify(body || {})
		}).then(function (r) {
			return r.json().catch(function () { return null; }).then(function (json) {
				if (!r.ok) throw new Error((json && json.message) || ('Request failed (' + r.status + ')'));
				if (json && json.code && json.message && json.data) throw new Error(json.message); // WP_Error
				return json;
			});
		}).finally(function () { busy(false); if (btn) btn.classList.remove('is-busy'); });
	}

	// merge a fresh list from the server with unsaved renames still in the table
	function replaceSites(list) {
		var edits = {};
		state.sites.forEach(function (s) { if (state.dirtyIds[s.stacked_site_id]) edits[s.stacked_site_id] = s; });
		state.sites = (list || []).map(function (s) {
			var e = edits[s.stacked_site_id];
			return e ? Object.assign({}, s, { name: e.name, domain: e.domain }) : Object.assign({}, s);
		});
		if (!Object.keys(edits).length) snapshot();
		render();
	}

	// ---------- render ----------
	function render() {
		var c = state.config, on = mapping();
		var main = S.main_url || '';
		el.status.innerHTML = 'Main site <b>' + esc(main.replace(/^https?:\/\//, '')) + '</b>' +
			' &nbsp;·&nbsp; files <b>' + esc(c.files) + '</b>' +
			' &nbsp;·&nbsp; domain mapping <b>' + (on ? 'on' : 'off') + '</b>' +
			(state.currentId ? ' &nbsp;·&nbsp; viewing tenant <b>' + esc(state.currentId) + '</b>' : '');
		el.loginMain.hidden = !(on && state.currentId);

		el.count.textContent = state.sites.length ? state.sites.length : '';
		el.sitesHint.textContent = on ? 'Domains take effect after you save. Point DNS and your host at this install first.' : 'Labels are yours to change; click one to edit.';

		var fileCol = c.files === 'dedicated' ? 'Files' : c.files === 'hybrid' ? 'Uploads' : '';
		el.thead.innerHTML = '<th class="wpf-id">ID</th><th>' + (on ? 'Domain' : 'Label') + '</th>' + (fileCol ? '<th>' + fileCol + '</th>' : '') + '<th>Created</th><th></th>';

		el.rows.innerHTML = state.sites.map(function (s) {
			var id = s.stacked_site_id, cur = String(id) === state.currentId;
			var path = c.files === 'dedicated' ? 'content/' + id + '/' : c.files === 'hybrid' ? 'content/' + id + '/uploads/' : '';
			var open = on
				? '<a class="wpf-btn wpf-btn-quiet" href="//' + esc(s.domain) + '" target="_blank" rel="noopener"' + (s.domain ? '' : ' hidden') + '>Open</a>' +
				  '<button type="button" class="wpf-btn wpf-btn-quiet" data-action="login" data-id="' + id + '">Log in</button>'
				: (cur ? '<span class="wpf-pill">Current</span>' : '<button type="button" class="wpf-btn wpf-btn-quiet" data-action="switch" data-id="' + id + '">Switch to</button>');
			var cls = [cur ? 'is-current' : '', state.dirtyIds[id] ? 'is-dirty' : ''].filter(Boolean).join(' ');
			return '<tr data-id="' + id + '"' + (cls ? ' class="' + cls + '"' : '') + '>' +
				'<td class="wpf-id">' + id + '</td>' +
				'<td class="wpf-name"><input type="text" value="' + esc(on ? s.domain : s.name) + '" placeholder="' + (on ? 'example.com' : 'Site ' + id) + '" data-field="' + (on ? 'domain' : 'name') + '" aria-label="' + (on ? 'Domain' : 'Label') + ' for site ' + id + '"></td>' +
				(fileCol ? '<td class="wpf-path">' + esc(path) + '</td>' : '') +
				'<td class="wpf-date">' + esc(fmtDate(s.created_at)) + '</td>' +
				'<td class="wpf-actions">' + open +
					'<button type="button" class="wpf-btn wpf-btn-quiet" data-action="clone" data-id="' + id + '">Clone</button>' +
					'<button type="button" class="wpf-btn wpf-btn-quiet is-danger" data-action="delete" data-id="' + id + '">Delete</button>' +
				'</td></tr>';
		}).join('');
		el.empty.hidden = state.sites.length > 0;
		$('wpf-table').hidden = !state.sites.length;

		root.querySelectorAll('input[name="files"]').forEach(function (r) { r.checked = r.value === c.files; });
		root.querySelectorAll('input[name="domain_mapping"]').forEach(function (r) { r.checked = r.value === c.domain_mapping; });
		root.querySelectorAll('[data-mode]').forEach(function (n) { n.hidden = n.dataset.mode !== c.domain_mapping; });

		renderNotices();
		setDirty();
	}

	function renderNotices() {
		var e = state.config.errors || {}, html = '';
		if (e.manual_bootstrap_required) {
			html += '<div class="wpf-notice is-error"><h2>Create wp-content/freighter.php by hand</h2>' +
				'<p>WP Freighter could not write its bootstrap file. Create <code>wp-content/freighter.php</code> with this exact content, then check again.</p>' +
				'<pre id="wpf-bootstrap-src">' + esc(e.manual_bootstrap_required) + '</pre>' +
				'<div class="wpf-notice-actions"><button type="button" class="wpf-btn" data-action="copy" data-target="wpf-bootstrap-src">Copy</button><button type="button" class="wpf-btn wpf-btn-primary" data-action="recheck">I created it, check again</button></div></div>';
		} else if (e.manual_config_required) {
			html += '<div class="wpf-notice"><h2>Add one line to wp-config.php</h2>' +
				'<p>WP Freighter could not write to <code>wp-config.php</code>. Paste this directly after the <code>$table_prefix</code> line, then check again.</p>' +
				'<pre id="wpf-config-src">' + esc([].concat(e.manual_config_required).join('\n')) + '</pre>' +
				'<div class="wpf-notice-actions"><button type="button" class="wpf-btn" data-action="copy" data-target="wpf-config-src">Copy</button><button type="button" class="wpf-btn wpf-btn-primary" data-action="recheck">I added it, check again</button></div></div>';
		}
		el.notices.innerHTML = html;
	}

	// ---------- actions ----------
	function save(btn) {
		return api('configurations', { sites: state.sites, configurations: stripErrors(state.config) }, btn).then(function (cfg) {
			state.config = Object.assign({ errors: {} }, cfg || {});
			state.dirtyIds = {};
			snapshot(); render();
			toast('Saved.');
		}).catch(function (err) { toast(err.message, true); });
	}

	function openDialog(d) { if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', ''); }
	function closeDialog(d) { if (d.open) d.close(); else d.removeAttribute('open'); }

	var pending = { cloneSource: null, deleteId: null };

	root.addEventListener('click', function (ev) {
		var t = ev.target.closest('[data-action]');
		if (!t) return;
		var a = t.dataset.action, id = t.dataset.id;
		var site = state.sites.filter(function (s) { return String(s.stacked_site_id) === String(id); })[0];

		if (a === 'switch') {
			api('switch', { site_id: Number(id) }, t).then(function (r) {
				if (r && r.url) window.location.href = r.url; else if (r && r.success === false) throw new Error(r.message || 'Could not switch.'); else window.location.reload();
			}).catch(function (err) { toast(err.message, true); });
		} else if (a === 'login') {
			api('sites/autologin', { site_id: Number(id) }, t).then(function (r) { if (r && r.url) window.open(r.url, '_blank', 'noopener'); })
				.catch(function (err) { toast(err.message, true); });
		} else if (a === 'login-main') {
			api('sites/autologin', { site_id: 'main' }, t).then(function (r) { if (r && r.url) window.location.href = r.url; })
				.catch(function (err) { toast(err.message, true); });
		} else if (a === 'clone' || a === 'clone-main') {
			pending.cloneSource = a === 'clone-main' ? 'main' : Number(id);
			$('wpf-clone-source').textContent = a === 'clone-main' ? 'the main site' : siteLabel(site);
			el.fClone.name.value = a === 'clone-main' ? '' : (site.name ? site.name + ' copy' : '');
			el.fClone.domain.value = '';
			openDialog(el.dClone);
			setTimeout(function () { var f = el.fClone.querySelector('label:not([hidden]) input'); if (f) f.focus(); }, 30);
		} else if (a === 'new') {
			el.fNew.reset();
			el.fNew.email.value = (S.currentUser && S.currentUser.email) || '';
			el.fNew.username.value = (S.currentUser && S.currentUser.username) || '';
			el.fNew.password.value = password();
			openDialog(el.dNew);
			setTimeout(function () { var f = el.fNew.querySelector('label:not([hidden]) input'); if (f) f.focus(); }, 30);
		} else if (a === 'regen-password') {
			el.fNew.password.value = password();
		} else if (a === 'delete') {
			pending.deleteId = Number(id);
			$('wpf-delete-name').textContent = siteLabel(site);
			var files = $('wpf-delete-files'); files.hidden = true;
			api('sites/stats', { site_id: Number(id) }, t).then(function (r) {
				if (r && r.has_dedicated_content) { files.innerHTML = 'Its folder <code>' + esc(r.path) + '</code> (' + esc(r.size) + ') is removed too.'; files.hidden = false; }
			}).catch(function () {}).finally(function () { openDialog(el.dDelete); });
		} else if (a === 'save') {
			save(t);
		} else if (a === 'discard') {
			var s = JSON.parse(state.saved); state.config = Object.assign({ errors: state.config.errors }, s.config); state.sites = s.sites; state.dirtyIds = {}; render();
		} else if (a === 'recheck') {
			save(t);
		} else if (a === 'copy') {
			var src = $(t.dataset.target);
			if (src && navigator.clipboard) navigator.clipboard.writeText(src.textContent).then(function () { toast('Copied.'); }, function () { toast('Could not copy.', true); });
			else toast('Clipboard needs a secure (https) page.', true);
		} else if (a === 'close') {
			closeDialog(t.closest('dialog'));
		}
	});

	// inline label / domain edits
	root.addEventListener('input', function (ev) {
		var inp = ev.target;
		if (!inp.matches('.wpf-name input')) return;
		var id = inp.closest('tr').dataset.id;
		state.sites.forEach(function (s) { if (String(s.stacked_site_id) === id) s[inp.dataset.field] = inp.value; });
		state.dirtyIds[id] = true;
		inp.closest('tr').classList.add('is-dirty');
		setDirty();
	});
	root.addEventListener('change', function (ev) {
		var r = ev.target;
		if (r.matches('input[name="files"]') && r.checked) { state.config.files = r.value; render(); }
		if (r.matches('input[name="domain_mapping"]') && r.checked) { state.config.domain_mapping = r.value; render(); }
	});

	// dialogs
	el.fNew.addEventListener('submit', function (ev) {
		ev.preventDefault();
		var f = el.fNew, btn = f.querySelector('[type="submit"]');
		api('sites', { name: f.name.value, domain: f.domain.value, title: f.title.value, email: f.email.value, username: f.username.value, password: f.password.value }, btn)
			.then(function (list) { closeDialog(el.dNew); replaceSites(list); toast('Site created.'); })
			.catch(function (err) { toast(err.message, true); });
	});
	el.fClone.addEventListener('submit', function (ev) {
		ev.preventDefault();
		var btn = el.fClone.querySelector('[type="submit"]');
		api('sites/clone', { source_id: pending.cloneSource, name: el.fClone.name.value, domain: el.fClone.domain.value }, btn)
			.then(function (list) { closeDialog(el.dClone); replaceSites(list); toast('Site cloned.'); })
			.catch(function (err) { toast(err.message, true); });
	});
	el.fDelete.addEventListener('submit', function (ev) {
		ev.preventDefault();
		var btn = el.fDelete.querySelector('[type="submit"]'), id = pending.deleteId;
		api('sites/delete', { site_id: id }, btn).then(function (r) {
			if (r && r.url) { window.location.href = r.url; return; }
			if (String(id) === state.currentId) { window.location.reload(); return; }
			closeDialog(el.dDelete); delete state.dirtyIds[id]; replaceSites(r); toast('Site deleted.');
		}).catch(function (err) { toast(err.message, true); });
	});
	root.querySelectorAll('dialog').forEach(function (d) { d.addEventListener('click', function (ev) { if (ev.target === d) closeDialog(d); }); });

	// leave-page guard while dirty
	window.addEventListener('beforeunload', function (ev) { if (state.dirty) { ev.preventDefault(); ev.returnValue = ''; } });

	// ---------- theme: left-click flips, right-click picks light / dark / system ----------
	(function () {
		var KEY = 'wpFreighterTheme', btn = $('wpf-theme'), menu = $('wpf-theme-menu');
		function current() { try { var v = localStorage.getItem(KEY); return v === 'light' || v === 'dark' ? v : 'system'; } catch (e) { return 'system'; } }
		function effectiveDark(pick) { return pick === 'dark' || (pick === 'system' && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches); }
		function apply(pick) {
			try { pick === 'system' ? localStorage.removeItem(KEY) : localStorage.setItem(KEY, pick); } catch (e) {}
			var dark = effectiveDark(pick);
			root.classList.toggle('is-dark', dark); document.body.classList.toggle('wpf-dark', dark);
			menu.querySelectorAll('[data-theme-pick]').forEach(function (b) { b.setAttribute('aria-checked', String(b.dataset.themePick === pick)); });
		}
		function open() { menu.hidden = false; btn.setAttribute('aria-expanded', 'true'); }
		function close() { menu.hidden = true; btn.setAttribute('aria-expanded', 'false'); }
		apply(current());
		if (window.matchMedia) matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () { if (current() === 'system') apply('system'); });
		btn.addEventListener('click', function () { apply(effectiveDark(current()) ? 'light' : 'dark'); });
		btn.addEventListener('contextmenu', function (e) { e.preventDefault(); menu.hidden ? open() : close(); });
		menu.addEventListener('click', function (e) { var b = e.target.closest('[data-theme-pick]'); if (b) { apply(b.dataset.themePick); close(); btn.focus(); } });
		document.addEventListener('click', function (e) { if (!menu.hidden && !e.target.closest('.wpf-theme-wrap')) close(); });
		document.addEventListener('keydown', function (e) { if (!menu.hidden && e.key === 'Escape') { close(); btn.focus(); } });
	})();

	render();
	root.hidden = false;
})();
