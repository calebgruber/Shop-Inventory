(() => {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
    const text = (tag, value, className = '') => {
        const node = document.createElement(tag);
        node.textContent = value ?? '';
        if (className) node.className = className;
        return node;
    };
    let csrfToken = $('meta[name="csrf-token"]')?.content || '';
    let loadingCount = 0;
    let navigationController;
    let tabs = [];
    let activeKey = '';
    let workspaceTimer;
    const userId = document.body.dataset.userId || 'guest';
    const accentCache = new Map();
    let storageWarning = false;

    function buttonLoader(button, message) {
        const loader = text('span', message, 'component-loader');
        loader.setAttribute('role', 'status');
        button.disabled = true;
        button.append(loader);
        return () => { loader.remove(); button.disabled = false; };
    }

    function notify(message, error = false) {
        const root = $('#notifications');
        if (!root) return;
        const notification = text('div', '', `notification${error ? ' error' : ''}`);
        notification.setAttribute('role', error ? 'alert' : 'status');
        notification.append(text('span', message));
        const close = text('button', 'close', 'material-symbols-rounded');
        close.type = 'button';
        close.setAttribute('aria-label', 'Dismiss notification');
        close.addEventListener('click', () => notification.remove());
        notification.append(close);
        root.append(notification);
        if (!error) setTimeout(() => notification.remove(), 7000);
    }

    function globalLoading(start) {
        loadingCount = Math.max(0, loadingCount + (start ? 1 : -1));
        const loader = $('#page-loader');
        if (loader) loader.hidden = loadingCount === 0;
    }

    async function api(url, options = {}) {
        const headers = { Accept: 'application/json', ...options.headers };
        if (options.method && options.method !== 'GET') headers['X-CSRF-Token'] = csrfToken;
        let body = options.body;
        if (body && !(body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(body);
        }
        const response = await fetch(url, { ...options, body, headers, credentials: 'same-origin' });
        if (!response.headers.get('content-type')?.includes('application/json')) {
            throw new Error('The server returned an unexpected response. Refresh or contact your shop administrator.');
        }
        const data = await response.json();
        if (!response.ok) {
            if (response.status === 401) {
                location.assign('/login');
            }
            throw new Error(data.error || `Request failed (${response.status}).`);
        }
        return data;
    }

    function accent(node, key) {
        const storageKey = `theatre-accent:${userId}:${key}`;
        let value = accentCache.get(storageKey);
        if (value === undefined) {
            try {
                const stored = localStorage.getItem(storageKey);
                value = /^[0-7]$/.test(stored || '') ? Number(stored) : crypto.getRandomValues(new Uint8Array(1))[0] % 8;
                localStorage.setItem(storageKey, String(value));
            } catch {
                value = crypto.getRandomValues(new Uint8Array(1))[0] % 8;
                if (!storageWarning) {
                    notify('Browser storage is unavailable. Accent colors will persist only while this page is open.', true);
                    storageWarning = true;
                }
            }
            accentCache.set(storageKey, value);
        }
        node.classList.add(`accent-${value}`);
    }

    function renderTabs() {
        const root = $('#workspace-tabs');
        if (!root) return;
        root.replaceChildren();
        for (const tab of tabs) {
            const wrapper = text('div', '', `workspace-tab${tab.key === activeKey ? ' active' : ''}`);
            const link = text('a', tab.label);
            link.href = tab.url;
            link.dataset.workspace = '';
            if (tab.key === activeKey) link.setAttribute('aria-current', 'page');
            wrapper.append(link);
            if (tab.key !== 'dashboard') {
                const close = text('button', 'close', 'material-symbols-rounded');
                close.type = 'button';
                close.setAttribute('aria-label', `Close ${tab.label} workspace`);
                close.addEventListener('click', () => {
                    const index = tabs.findIndex(item => item.key === tab.key);
                    tabs = tabs.filter(item => item.key !== tab.key);
                    if (activeKey === tab.key) navigate(tabs[Math.max(0, index - 1)]?.url || '/dashboard');
                    renderTabs();
                    persistTabs();
                });
                wrapper.append(close);
            }
            root.append(wrapper);
        }
    }

    function persistTabs() {
        clearTimeout(workspaceTimer);
        workspaceTimer = setTimeout(async () => {
            try { await api('/api/workspaces', { method: 'POST', body: { tabs } }); }
            catch (error) { notify(`Workspace persistence failed: ${error.message}`, true); }
        }, 350);
    }

    function openTab(key, label, url) {
        const existing = tabs.find(tab => tab.key === key);
        if (existing) {
            existing.label = label;
            existing.url = url;
        } else if (tabs.length < 30) {
            tabs.push({ key, label, url });
        } else {
            notify('Close a workspace before opening another. The limit is 30 tabs.', true);
            return false;
        }
        activeKey = key;
        renderTabs();
        persistTabs();
        return true;
    }

    async function navigate(url, push = true) {
        if (!$('#workspace-content') || /^\/(?:login|forgot-password|reset-password)(?:[/?]|$)/.test(url)) {
            location.assign(url);
            return;
        }
        navigationController?.abort();
        const controller = new AbortController();
        navigationController = controller;
        globalLoading(true);
        try {
            const result = await api(url, { headers: { 'X-Workspace': '1' }, signal: controller.signal });
            if (controller.signal.aborted) return;
            if (!openTab(result.key, result.label, url)) return;
            csrfToken = result.csrf;
            $('meta[name="csrf-token"]').content = csrfToken;
            const root = $('#workspace-content');
            root.innerHTML = result.html;
            root.dataset.key = result.key;
            root.dataset.title = result.title;
            root.dataset.label = result.label;
            document.title = `${result.title} | Theatre Shop`;
            if (push) history.pushState({}, '', url);
            initialize(root);
            root.focus({ preventScroll: true });
            window.scrollTo({ top: 0, behavior: 'instant' });
            highlightNavigation();
        } catch (error) {
            if (error.name !== 'AbortError') notify(error.message, true);
        } finally {
            globalLoading(false);
        }
    }

    function highlightNavigation() {
        $$('.primary-nav a').forEach(link => {
            const url = new URL(link.href).pathname;
            link.classList.toggle('active', url === '/dashboard' ? location.pathname === url : location.pathname.startsWith(url));
        });
    }

    function collectLines(form) {
        return $$('[data-line-rows] > .equipment-line', form).map(row => {
            const [kind, id] = $('[data-line-equipment]', row).value.split(':');
            const result = { [kind === 'package' ? 'package_id' : 'item_type_id']: id, quantity: $('[data-line-quantity]', row).value };
            if ($('[data-line-start]', row)) result.start_date = $('[data-line-start]', row).value;
            if ($('[data-line-return]', row)) result.return_date = $('[data-line-return]', row).value;
            const overrides = {};
            $$('[data-component-return]', row).forEach(field => {
                if (field.value) overrides[field.dataset.componentReturn] = field.value;
            });
            if (Object.keys(overrides).length) result.item_return_dates = overrides;
            return result;
        });
    }

    function lineEditor(editor) {
        const template = $('[data-line-template]', editor);
        const rows = $('[data-line-rows]', editor);
        const add = () => {
            rows.append(template.content.cloneNode(true));
            const row = rows.lastElementChild;
            $('[data-remove-line]', row).addEventListener('click', () => row.remove());
            $('[data-line-equipment]', row).addEventListener('change', event => {
                const overrides = $('[data-component-overrides]', row);
                overrides.replaceChildren();
                if (!event.target.value.startsWith('package:')) return;
                const components = JSON.parse(event.target.selectedOptions[0].dataset.components);
                components.forEach(component => {
                    const label = text('label', `${component.name} return override`, 'form-label');
                    const field = document.createElement('input');
                    field.type = 'date';
                    field.className = 'form-control';
                    field.dataset.componentReturn = component.item_type_id;
                    label.append(field);
                    overrides.append(label);
                });
            });
        };
        $('[data-add-line]', editor).addEventListener('click', add);
        add();
    }

    async function submitForm(form, submitter) {
        const errorBox = $('.form-error', form);
        if (errorBox) errorBox.hidden = true;
        const buttons = $$('button[type="submit"], button:not([type])', form);
        buttons.forEach(button => button.disabled = true);
        form.setAttribute('aria-busy', 'true');
        const loader = text('div', 'Saving...', 'component-loader');
        loader.setAttribute('role', 'status');
        form.append(loader);
        globalLoading(true);
        try {
            const data = new FormData(form);
            if (submitter?.name) data.set(submitter.name, submitter.value);
            let body = data;
            if ($('[data-line-editor]', form)) {
                body = Object.fromEntries(data.entries());
                body.lines = collectLines(form);
            }
            const result = await api(form.action, { method: 'POST', body });
            if (result.message) notify(result.message);
            if (result.redirect) await navigate(result.redirect);
            else if (form.hasAttribute('data-refresh-on-save')) await navigate(location.pathname + location.search, false);
        } catch (error) {
            if (errorBox) {
                errorBox.textContent = error.message;
                errorBox.hidden = false;
            } else notify(error.message, true);
        } finally {
            buttons.forEach(button => button.disabled = false);
            form.removeAttribute('aria-busy');
            loader.remove();
            globalLoading(false);
        }
    }

    function initializeTable(root) {
        const resource = root.dataset.table;
        let state = { q: '', page: 1, limit: 25, sort: '', direction: 'asc', filter: {}, hidden: [] };
        let controller;
        let timer;
        const fixed = root.dataset.fixedFilter;
        const syncControls = () => {
            $('[data-table-search]', root).value = state.q;
            $('[data-table-limit]', root).value = state.limit;
            const [column, value] = Object.entries(state.filter)[0] || ['', ''];
            $('[data-filter-column]', root).value = column;
            $('[data-filter-value]', root).value = value;
            $$('[data-column]', root).forEach(box => box.checked = !state.hidden.includes(box.dataset.column));
        };
        const visibility = () => {
            $$('[data-column-name]', root).forEach(node => node.hidden = state.hidden.includes(node.dataset.columnName));
        };
        async function load() {
            controller?.abort();
            controller = new AbortController();
            const current = controller;
            root.setAttribute('aria-busy', 'true');
            $('.component-loader', root).hidden = false;
            $('.table-error', root).hidden = true;
            const query = new URLSearchParams({ resource, q: state.q, page: state.page, limit: state.limit, sort: state.sort, direction: state.direction });
            for (const [key, value] of Object.entries(state.filter)) if (value !== '') query.set(`filter[${key}]`, value);
            if (root.dataset.showId) query.set('show_id', root.dataset.showId);
            if (root.dataset.orderId) query.set('order_id', root.dataset.orderId);
            if (root.dataset.revisionId) query.set('revision_id', root.dataset.revisionId);
            if (root.dataset.documentType) query.set('document_type', root.dataset.documentType);
            if (root.dataset.entityId) query.set('entity_id', root.dataset.entityId);
            if (fixed) {
                const [column, value] = fixed.split(':');
                query.set(`filter[${column}]`, value);
            }
            if (resource === 'assets' && new URLSearchParams(location.search).has('type')) {
                query.set('item_type_id', new URLSearchParams(location.search).get('type'));
            }
            try {
                const result = await api(`/api/table?${query}`, { signal: current.signal });
                if (!root.isConnected || current.signal.aborted) return;
                state.page = result.page;
                const body = $('tbody', root);
                body.replaceChildren();
                if (!result.rows.length) {
                    const row = document.createElement('tr');
                    const cell = text('td', 'No matching records. Try another filter, or add your first record.', 'text-secondary py-4');
                    cell.colSpan = result.columns.length;
                    row.append(cell);
                    body.append(row);
                }
                result.rows.forEach(record => {
                    const row = document.createElement('tr');
                    result.columns.forEach((column, index) => {
                        const cell = document.createElement('td');
                        cell.dataset.columnName = column;
                        const value = record[column] ?? '-';
                        if (index === 0 && result.path) {
                            const link = text('a', value);
                            link.href = result.path + record.id;
                            if (!['files','documents'].includes(resource)) link.dataset.workspace = '';
                            cell.append(link);
                        } else if (resource === 'rules' && column === 'active' && document.body.dataset.canInventoryEdit === '1') {
                            const button = text('button', Number(value) ? 'Enabled - disable' : 'Disabled - enable', 'btn btn-sm');
                            button.type = 'button';
                            button.dataset.ruleStatus = record.id;
                            button.dataset.active = Number(value) ? '0' : '1';
                            cell.append(button);
                        } else if (['status', 'state', 'mode'].includes(column)) {
                            cell.append(text('span', value, 'badge bg-secondary-lt'));
                        } else cell.textContent = value;
                        row.append(cell);
                    });
                    body.append(row);
                });
                visibility();
                $('[data-table-total]', root).textContent = `${result.total} records`;
                const pages = Math.max(1, Math.ceil(result.total / result.limit));
                $('[data-table-page]', root).textContent = `${result.page} / ${pages}`;
                $('[data-table-prev]', root).disabled = result.page <= 1;
                $('[data-table-next]', root).disabled = result.page >= pages;
                $$('[data-sort]', root).forEach(button => {
                    button.parentElement.setAttribute('aria-sort', button.dataset.sort === state.sort ? (state.direction === 'asc' ? 'ascending' : 'descending') : 'none');
                });
            } catch (error) {
                if (error.name !== 'AbortError') {
                    $('.table-error', root).textContent = error.message;
                    $('.table-error', root).hidden = false;
                }
            } finally {
                if (controller === current) {
                    root.removeAttribute('aria-busy');
                    $('.component-loader', root).hidden = true;
                }
            }
        }
        const schedule = () => { clearTimeout(timer); timer = setTimeout(load, 250); };
        $('[data-table-search]', root).addEventListener('input', event => { state.q = event.target.value; state.page = 1; schedule(); });
        const filter = () => {
            const column = $('[data-filter-column]', root).value;
            state.filter = column ? { [column]: $('[data-filter-value]', root).value } : {};
            state.page = 1;
            schedule();
        };
        $('[data-filter-column]', root).addEventListener('change', filter);
        $('[data-filter-value]', root).addEventListener('input', filter);
        $('[data-table-limit]', root).addEventListener('change', event => { state.limit = Number(event.target.value); state.page = 1; load(); });
        $('[data-table-prev]', root).addEventListener('click', () => { state.page--; load(); });
        $('[data-table-next]', root).addEventListener('click', () => { state.page++; load(); });
        $$('[data-sort]', root).forEach(button => button.addEventListener('click', () => {
            state.direction = state.sort === button.dataset.sort && state.direction === 'asc' ? 'desc' : 'asc';
            state.sort = button.dataset.sort;
            state.page = 1;
            load();
        }));
        $$('[data-column]', root).forEach(box => box.addEventListener('change', () => {
            state.hidden = $$('[data-column]', root).filter(item => !item.checked).map(item => item.dataset.column);
            visibility();
        }));
        let saved = [];
        async function loadViews() {
            try {
                saved = (await api(`/api/views?resource=${encodeURIComponent(resource)}`)).views;
                const selector = $('[data-saved-views]', root);
                selector.replaceChildren(new Option('Saved views', ''));
                saved.forEach(item => selector.add(new Option(item.name, item.id)));
            } catch (error) { notify(`Saved views unavailable: ${error.message}`, true); }
        }
        $('[data-saved-views]', root).addEventListener('change', event => {
            const selected = saved.find(item => String(item.id) === event.target.value);
            if (!selected) return;
            const config = JSON.parse(selected.configuration);
            state = { ...state, ...config, page: 1 };
            syncControls();
            load();
        });
        $('[data-save-view]', root).addEventListener('click', () => {
            $('.view-save', root).hidden = !$('.view-save', root).hidden;
            $('[data-view-name]', root).focus();
        });
        $('[data-confirm-view]', root).addEventListener('click', async event => {
            const name = $('[data-view-name]', root).value.trim();
            if (!name) { notify('Enter a name for this view.', true); return; }
            event.target.disabled = true;
            try {
                await api('/api/views', { method: 'POST', body: { resource, name, configuration: state } });
                $('.view-save', root).hidden = true;
                await loadViews();
                notify('View saved.');
            } catch (error) { notify(error.message, true); }
            finally { event.target.disabled = false; }
        });
        load();
        loadViews();
    }

    async function passkey(register, button) {
        if (!window.PublicKeyCredential || !navigator.credentials || !window.isSecureContext) {
            notify('Passkeys require HTTPS and a browser supporting WebAuthn. Use password sign-in on this device.', true);
            return;
        }
        const fromBase64 = value => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/')), char => char.charCodeAt(0));
        const toBase64 = buffer => btoa(String.fromCharCode(...new Uint8Array(buffer))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
        const action = register ? 'register' : 'login';
        const finishLoading = buttonLoader(button, 'Waiting for device...');
        globalLoading(true);
        try {
            const args = await api(`/passkeys/${action}/begin`, { method: 'POST', body: {} });
            const options = args.publicKey;
            options.challenge = fromBase64(options.challenge);
            if (options.user) options.user.id = fromBase64(options.user.id);
            for (const key of ['allowCredentials', 'excludeCredentials']) {
                if (options[key]) options[key] = options[key].map(item => ({ ...item, id: fromBase64(item.id) }));
            }
            const credential = await navigator.credentials[register ? 'create' : 'get']({ publicKey: options });
            if (!credential) throw new Error('No passkey response was received.');
            const response = credential.response;
            const body = { rawId: toBase64(credential.rawId), clientDataJSON: toBase64(response.clientDataJSON) };
            if (register) {
                body.attestationObject = toBase64(response.attestationObject);
                body.label = $('#passkey-label')?.value || 'My device';
            } else {
                body.authenticatorData = toBase64(response.authenticatorData);
                body.signature = toBase64(response.signature);
                body.userHandle = response.userHandle ? toBase64(response.userHandle) : '';
            }
            const result = await api(`/passkeys/${action}/finish`, { method: 'POST', body });
            if (result.redirect) location.assign(result.redirect);
            else { notify(result.message); await navigate('/profile'); }
        } catch (error) {
            notify(error.name === 'NotAllowedError' ? 'Passkey sign-in was cancelled or timed out. Try again or use your password.' : error.message, true);
        } finally {
            finishLoading();
            globalLoading(false);
        }
    }

    let scanning = false;
    async function scan(station, tag) {
        const feedback = $('[data-scan-feedback]', station);
        const showFeedback = (message, error) => {
            feedback.textContent = message;
            feedback.className = `scan-feedback ${error ? 'error' : 'success'}`;
        };
        if (!tag.trim()) return;
        if (scanning) { showFeedback('The previous scan is still saving. Wait for confirmation, then scan again.', true); return; }
        scanning = true;
        $('.component-loader', station).hidden = false;
        globalLoading(true);
        try {
            const body = { asset_tag: tag.trim() };
            if (station.dataset.kind === 'return') {
                body.condition = $('[data-return-condition]', station).value;
                body.description = $('[data-return-description]', station).value;
            }
            const result = await api(station.dataset.action, { method: 'POST', body });
            showFeedback(`${tag}: recorded. ${result.partial ? 'Partial Pull.' : ''} Receipt #${result.operation_id}.`, false);
            $('[data-scan-input]', station).value = '';
            if (result.lines) {
                const pulled = result.lines.reduce((sum, line) => sum + Number(line.pulled), 0);
                $('[data-pulled-total]', station).textContent = pulled;
                $('.pull-progress', station).value = pulled;
                $('[data-pull-status]', station).textContent = result.partial ? 'Partial Pull' : 'Complete Pull';
                result.lines.forEach(line => {
                    const value = $(`[data-progress-line="${line.id}"] [data-line-pulled]`, station);
                    if (value) value.textContent = line.pulled;
                });
            }
            if (station.dataset.kind === 'return') {
                $$('[data-outstanding-type]', station).forEach(row => {
                    const remaining = result.remaining.find(item => String(item.item_type_id) === row.dataset.outstandingType);
                    $('[data-outstanding-quantity]', row).textContent = remaining?.quantity || 0;
                });
                notify(`Returned ${tag}. ${body.condition === 'Good' ? 'Available to the shop.' : body.condition === 'Missing' ? 'Recorded as missing.' : 'Repair ticket created.'}`);
            }
        } catch (error) { showFeedback(error.message, true); }
        finally { scanning = false; $('.component-loader', station).hidden = true; globalLoading(false); }
    }
    let barcode = '';
    let lastKeyTime = 0;
    document.addEventListener('keydown', event => {
        const station = $('[data-scanner-workspace]');
        if (!station || !$('[data-scan-form]', station) || event.ctrlKey || event.metaKey || event.altKey) return;
        if (event.target.closest('input,textarea,select,[contenteditable="true"]')) return;
        if (event.key === 'Enter') {
            if (barcode) { event.preventDefault(); scan(station, barcode); barcode = ''; }
        } else if (event.key.length === 1) {
            event.preventDefault();
            if (Date.now() - lastKeyTime > 150) barcode = '';
            if (barcode.length < 80) barcode += event.key;
            lastKeyTime = Date.now();
        }
    });

    function initializeTimeline(root) {
        let page = 1;
        let controller;
        let timer;
        const form = $('[data-timeline-filters]', root);
        async function load() {
            controller?.abort();
            controller = new AbortController();
            const current = controller;
            $('.component-loader', root).hidden = false;
            $('.form-error', root).hidden = true;
            root.setAttribute('aria-busy', 'true');
            const query = new URLSearchParams(new FormData(form));
            query.set('page', page);
            try {
                const data = await api(`/api/timeline?${query}`, { signal: current.signal });
                if (!root.isConnected || current.signal.aborted) return;
                page = data.page;
                const dates = [];
                for (let date = new Date(`${data.start}T00:00:00Z`); date <= new Date(`${data.end}T00:00:00Z`); date.setUTCDate(date.getUTCDate() + 1)) dates.push(date.toISOString().slice(0, 10));
                const grid = $('[data-timeline-grid]', root);
                grid.replaceChildren();
                function row(label, records = [], repairRecords = [], assetEvents = [], url = null, currentState = null) {
                    const line = text('div', '', 'timeline-row');
                    const title = text(url ? 'a' : 'span', label, 'timeline-label');
                    if (url) { title.href = url; title.dataset.workspace = ''; }
                    title.title = label;
                    line.append(title);
                    dates.forEach(date => {
                        const cell = text('span', '', 'timeline-cell');
                        let state = 'available';
                        let detail = `${label}: available on ${date}`;
                        records.forEach(record => {
                            const end = record.ended_at ? record.ended_at.slice(0, 10) : record.return_date < new Date().toISOString().slice(0, 10) ? data.end : record.return_date;
                            if (record.start_date <= date && date <= end) {
                                state = record.planned ? 'planned' : record.type === 'Transfer' ? 'transfer' : Number(record.revision_number) > 0 ? 'revision' : 'assigned';
                                detail = `${label}: ${record.show_name}, ${record.start_date} through ${end}${record.planned ? `; ${record.quantity} committed` : ''}`;
                            }
                        });
                        repairRecords.forEach(repair => {
                            if (repair.created_at.slice(0, 10) <= date && (!repair.completed_at || repair.completed_at.slice(0, 10) >= date)) {
                                state = 'repair';
                                detail = `${label}: repair #${repair.id} (${repair.status})`;
                            }
                        });
                        if (currentState === 'Missing' && date >= new Date().toISOString().slice(0, 10)) {
                            state = 'missing';
                            detail = `${label}: missing; unavailable until recovered`;
                        }
                        const events = assetEvents.filter(event => event.created_at.slice(0, 10) === date);
                        if (events.length) {
                            cell.classList.add('event');
                            detail += ` | ${events.map(event => event.state).join(', ')}`;
                        }
                        cell.classList.add('segment', state);
                        cell.title = detail;
                        cell.setAttribute('aria-label', detail);
                        line.append(cell);
                    });
                    grid.append(line);
                }
                const header = text('div', '', 'timeline-row header');
                header.append(text('span', 'Asset / planned commitment', 'timeline-label'));
                dates.forEach(date => {
                    const cell = text('span', date.slice(5), 'timeline-cell');
                    cell.title = date;
                    header.append(cell);
                });
                grid.append(header);
                const summary = $('[data-planning-summary]', root);
                summary.replaceChildren();
                data.planning.forEach(type => {
                    const name = data.assets.find(asset => Number(asset.item_type_id) === Number(type.item_type_id))?.item_name || 'Equipment';
                    const tile = text('div', '', `planning-tile${type.availability.shortage ? ' shortage' : ''}`);
                    tile.append(text('strong', name), text('span', `${type.availability.total} total | ${type.availability.committed} peak committed | ${type.availability.available} available | ${type.availability.shortage} short`));
                    summary.append(tile);
                    type.commitments.filter(item => item.quantity > 0 && item.start_date <= data.end && item.return_date >= data.start).forEach(item => {
                        row(`${name} / ${item.show_name}: ${item.quantity} planned`, [{ ...item, planned: true }]);
                    });
                });
                data.assets.forEach(asset => row(`${asset.asset_tag} | ${asset.item_name}`, data.assignments.filter(item => item.asset_id === asset.id),
                    data.repairs.filter(item => item.asset_id === asset.id), data.events.filter(item => item.asset_id === asset.id), `/inventory/assets/${asset.id}`, asset.state));
                if (!data.assets.length) grid.append(text('p', 'No assets match these filters.', 'p-4 text-secondary'));
                $('[data-timeline-total]', root).textContent = `${data.total} matching assets | ${data.start} through ${data.end}`;
                $('[data-timeline-page]', root).textContent = `${page} / ${Math.max(1, Math.ceil(data.total / 50))}`;
                $('[data-timeline-prev]', root).disabled = page <= 1;
                $('[data-timeline-next]', root).disabled = page >= Math.ceil(data.total / 50);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    $('.form-error', root).textContent = error.message;
                    $('.form-error', root).hidden = false;
                }
            } finally {
                if (controller === current) {
                    $('.component-loader', root).hidden = true;
                    root.removeAttribute('aria-busy');
                }
            }
        }
        form.addEventListener('submit', event => event.preventDefault());
        form.addEventListener('input', () => { page = 1; clearTimeout(timer); timer = setTimeout(load, 300); });
        $('[data-timeline-prev]', root).addEventListener('click', () => { page--; load(); });
        $('[data-timeline-next]', root).addEventListener('click', () => { page++; load(); });
        load();
    }

    function initialize(root) {
        const key = $('#workspace-content')?.dataset.key || 'login';
        $$('.card', root).forEach((card, index) => accent(card, `${key}:card:${card.dataset.cardKey || index}`));
        $$('.working-tabs a', root).forEach((tab, index) => accent(tab, `${key}:working:${tab.dataset.workingKey || index}`));
        $$('[data-line-editor]', root).forEach(lineEditor);
        $$('[data-table]', root).forEach(initializeTable);
        $$('[data-timeline]', root).forEach(initializeTimeline);
        const sortableLists = $$('[data-sortable-list]', root).filter(list => list.closest('[data-can-sort]')?.dataset.canSort === '1');
        if (sortableLists.length && !window.Sortable) {
            notify('SortableJS could not load from its CDN. Inventory order is unchanged; drag-and-drop is unavailable.', true);
        }
        if (window.Sortable) sortableLists.forEach(list => {
            new Sortable(list, {
                animation: matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 150,
                handle: '.drag-handle', draggable: '.sortable-item', ghostClass: 'sortable-ghost',
                onEnd: async () => {
                    list.setAttribute('aria-busy', 'true');
                    globalLoading(true);
                    try {
                        await api('/inventory/reorder', { method: 'POST', body: {
                            resource: list.dataset.sortableList, parent_id: list.dataset.parentId,
                            ids: [...list.children].filter(child => child.classList.contains('sortable-item')).map(child => child.dataset.id),
                        } });
                        notify('Inventory sort order saved.');
                    } catch (error) { notify(error.message, true); await navigate('/inventory/categories', false); }
                    finally { list.removeAttribute('aria-busy'); globalLoading(false); }
                },
            });
        });
    }

    document.addEventListener('submit', event => {
        if (event.target.matches('[data-ajax-form]')) {
            event.preventDefault();
            if (event.target.dataset.confirm && !window.confirm(event.target.dataset.confirm)) return;
            if (event.target.checkValidity()) submitForm(event.target, event.submitter);
        } else if (event.target.matches('[data-scan-form]')) {
            event.preventDefault();
            const station = event.target.closest('[data-scanner-workspace]');
            scan(station, $('[data-scan-input]', station).value);
        }
    });
    document.addEventListener('click', async event => {
        const link = event.target.closest('a[data-workspace]');
        if (link && !event.ctrlKey && !event.metaKey && !event.shiftKey && event.button === 0) {
            event.preventDefault();
            navigate(link.pathname + link.search);
            return;
        }
        const theme = event.target.closest('[data-theme-toggle]');
        if (theme) {
            document.documentElement.dataset.bsTheme = document.documentElement.dataset.bsTheme === 'dark' ? 'light' : 'dark';
            try { localStorage.setItem('theatre-theme', document.documentElement.dataset.bsTheme); }
            catch { notify('Theme changed, but browser storage could not save the preference.', true); }
        }
        const rule = event.target.closest('[data-rule-status]');
        if (rule) {
            rule.disabled = true;
            globalLoading(true);
            try {
                const result = await api(`/inventory/rules/${rule.dataset.ruleStatus}/status`, { method: 'POST', body: { active: rule.dataset.active } });
                await navigate(result.redirect);
            } catch (error) { notify(error.message, true); }
            finally { rule.disabled = false; globalLoading(false); }
        }
        const login = event.target.closest('[data-passkey-login]');
        const register = event.target.closest('[data-passkey-register]');
        if (login || register) await passkey(Boolean(register), register || login);
        const pdf = event.target.closest('[data-pdf]');
        if (pdf) {
            pdf.disabled = true;
            globalLoading(true);
            const loader = text('span', ' Generating...', 'component-loader');
            pdf.append(loader);
            try {
                const body = { type: pdf.dataset.pdf, entity_id: pdf.dataset.entityId };
                if (pdf.dataset.pdf === 'timeline' && $('[data-timeline-filters]')) {
                    body.filters = Object.fromEntries(new FormData($('[data-timeline-filters]')));
                }
                const result = await api('/reports/generate', { method: 'POST', body });
                notify(result.message);
                location.assign(result.download);
            } catch (error) { notify(error.message, true); }
            finally { pdf.disabled = false; loader.remove(); globalLoading(false); }
        }
        if (event.target.closest('[data-avatar-open]')) $('#avatar-dialog').showModal();
        const avatar = event.target.closest('[data-regenerate-avatar]');
        if (avatar) {
            const finishLoading = buttonLoader(avatar, 'Generating...');
            globalLoading(true);
            try {
                const result = await api('/profile/avatar', { method: 'POST', body: {} });
                $$(`img[src^="/avatars/${userId}"]`).forEach(image => image.src = `/avatars/${userId}?v=${Date.now()}`);
                notify(result.message);
            } catch (error) { notify(error.message, true); }
            finally { finishLoading(); globalLoading(false); }
        }
        const fullscreen = event.target.closest('[data-fullscreen]');
        if (fullscreen) {
            try {
                if (document.fullscreenElement) await document.exitFullscreen();
                else await fullscreen.closest('[data-scanner-workspace]').requestFullscreen();
            } catch { notify('Fullscreen is unavailable in this browser. The scanner station still works.', true); }
        }
    });
    window.addEventListener('popstate', () => navigate(location.pathname + location.search, false));

    let searchTimer;
    let searchController;
    $('#global-search')?.addEventListener('input', event => {
        clearTimeout(searchTimer);
        searchController?.abort();
        const term = event.target.value.trim();
        const root = $('#search-results');
        if (term.length < 2) { root.hidden = true; root.replaceChildren(); return; }
        searchTimer = setTimeout(async () => {
            searchController = new AbortController();
            root.hidden = false;
            root.replaceChildren(text('p', 'Searching...', 'text-secondary'));
            try {
                const result = await api(`/api/search?q=${encodeURIComponent(term)}`, { signal: searchController.signal });
                root.replaceChildren();
                if (!result.results.length) root.append(text('p', 'No matching records.'));
                result.results.forEach(record => {
                    const link = text('a', record.label);
                    link.href = record.url;
                    if (record.resource !== 'files') link.dataset.workspace = '';
                    link.append(text('small', record.resource.replace(/_/g, ' ')));
                    link.addEventListener('click', () => root.hidden = true);
                    root.append(link);
                });
            } catch (error) {
                if (error.name !== 'AbortError') root.replaceChildren(text('p', error.message, 'form-error'));
            }
        }, 250);
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('.global-search') && $('#search-results')) $('#search-results').hidden = true;
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && $('#search-results')) $('#search-results').hidden = true;
    });

    const workspace = $('#workspace-content');
    if (workspace) {
        try { tabs = JSON.parse($('#workspace-tabs').dataset.tabs); }
        catch { notify('Saved workspace data could not be read. Resetting this page to the dashboard tab.', true); }
        if (!tabs.some(tab => tab.key === 'dashboard')) tabs.unshift({ key: 'dashboard', label: 'Dashboard', url: '/dashboard' });
        openTab(workspace.dataset.key, workspace.dataset.label, location.pathname + location.search);
        highlightNavigation();
    }
    initialize(document);
    document.fonts.load('22px "Material Symbols Rounded"').then(faces => {
        if (!faces.length) {
            document.body.classList.add('icons-unavailable');
            notify('Material Symbols could not load from Google Fonts. Text controls remain available; check CDN connectivity.', true);
        }
    }).catch(() => {
        document.body.classList.add('icons-unavailable');
        notify('Material Symbols could not load from Google Fonts. Text controls remain available; check CDN connectivity.', true);
    });
})();
