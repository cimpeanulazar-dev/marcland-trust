/* Classic multipart editor. Persistent IDs originate exclusively in PHP/database data. */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('marclandtrust-editor');
    if (!root) return;
    const config = JSON.parse(root.dataset.config);
    const form = root.querySelector('form');
    const list = root.querySelector('#marclandtrust-items');
    const template = root.querySelector('template');
    const languageSelect = root.querySelector('#marclandtrust-language');
    const add = root.querySelector('#marclandtrust-add-item');
    const serialized = root.querySelector('#marclandtrust-serialized');
    const status = root.querySelector('#marclandtrust-status');
    const icons = ['truck', 'returns', 'shield', 'lock', 'support', 'verified'];
    const deleted = new Set((config.deletedIds || []).map(String));
    const placementNames = config.placements;
    let nextKey = 0;
    let language = String(config.language);
    const normalizePlacements = (values, position) => Object.fromEntries(placementNames.map(name => {
        const p = values && values[name] || {};
        return [name, {enabled: p.enabled === true || String(p.enabled) === '1', position: p.position ?? position,
            link_type: p.link_type || 'none', link_target: p.link_target || null}];
    }));
    const zones = config.zones.map((zone, index) => ({
        ...zone,
        // Separate namespace: never serialize this temporary UI key.
        client_key: `zone-${++nextKey}`,
        texts: {...zone.texts},
        placements: normalizePlacements(zone.placements, index + 1),
        icon_name: zone.icon_name || 'truck',
        file: null,
    }));
    config.languages.forEach(lang => {
        languageSelect.add(new Option(lang.name, String(lang.id_lang)));
    });
    if (!config.languages.some(lang => String(lang.id_lang) === language)) {
        language = config.languages.length ? String(config.languages[0].id_lang) : '';
    }
    languageSelect.value = language;

    function captureTexts() {
        const byKey = new Map(zones.map(zone => [zone.client_key, zone]));
        list.querySelectorAll('[data-client-key]').forEach(card => {
            const zone = byKey.get(card.dataset.clientKey);
            if (zone && language) zone.texts[language] = card.querySelector('[data-field="text"]').value;
        });
    }

    function localizeCard(card, zone) {
        const text = card.querySelector('[data-field="text"]');
        text.value = zone.texts[language] || '';
        text.disabled = !language;
        const lang = config.languages.find(item => String(item.id_lang) === language);
        text.lang = lang ? lang.language_code || lang.iso_code : '';
        text.dir = lang && Number(lang.is_rtl) ? 'rtl' : 'ltr';
        card.querySelectorAll('[data-placement]').forEach(panel => {
            const p = zone.placements[panel.dataset.placement];
            const cms = panel.querySelector('[data-p-field="cms"]');
            while (cms.options.length > 1) cms.remove(1);
            config.cmsPages.forEach(page => {
                const title = page.titles[language] || Object.values(page.titles)[0] || String(page.id);
                cms.add(new Option(`${title} (#${page.id})`, String(page.id)));
            });
            if (p.link_type === 'cms' && p.link_target
                && !Array.from(cms.options).some(option => option.value === String(p.link_target))) {
                cms.add(new Option(`#${p.link_target}`, String(p.link_target)));
            }
            cms.value = p.link_type === 'cms' ? String(p.link_target || '') : '';
        });
    }

    function render() {
        list.replaceChildren();
        zones.forEach((zone, index) => {
            zone.position = index + 1;
            const card = template.content.firstElementChild.cloneNode(true);
            card.dataset.clientKey = zone.client_key;
            card.querySelector('[data-role="number"]').textContent = String(index + 1);
            const field = name => card.querySelector(`[data-field="${name}"]`);
            const role = name => card.querySelector(`[data-role="${name}"]`);
            field('active').checked = Boolean(zone.active);
            field('text').addEventListener('input', event => { zone.texts[language] = event.target.value; });
            field('active').addEventListener('change', event => { zone.active = event.target.checked; });
            field('icon_type').value = zone.icon_type;
            const updateIcons = () => {
                role('icons').hidden = zone.icon_type !== 'predefined';
                role('custom').hidden = zone.icon_type !== 'custom';
                field('file').disabled = zone.icon_type !== 'custom';
                card.querySelectorAll('[data-icon]').forEach(button => {
                    button.setAttribute('aria-pressed', String(button.dataset.icon === zone.icon_name));
                });
            };
            field('icon_type').addEventListener('change', event => {
                zone.icon_type = event.target.value;
                updateIcons();
            });
            card.querySelectorAll('[data-icon]').forEach(button => {
                const name = button.dataset.icon;
                if (icons.includes(name)) button.querySelector('img').src = `${config.iconBase}${name}.svg`;
                button.addEventListener('click', () => { zone.icon_name = name; updateIcons(); });
            });
            // Move the original file input rather than recreating it: native multipart retains its FileList.
            if (zone.fileInput) {
                field('file').replaceWith(zone.fileInput);
            } else {
                zone.fileInput = field('file');
                field('file').addEventListener('change', event => {
                    zone.file = event.target.files[0] || null;
                    event.target.closest('[data-client-key]').querySelector('[data-role="file-status"]').textContent
                        = zone.file ? zone.file.name : (zone.custom_icon_path || '');
                });
            }
            const preview = role('custom-preview');
            if (/^uploads\/[a-f0-9]{32}\.svg$/.test(zone.custom_icon_path || '')) {
                preview.src = config.customBase + zone.custom_icon_path;
                preview.hidden = false;
            }
            // Only previously saved sanitized files get an image reference. No local SVG previews.
            role('file-status').textContent = zone.file ? zone.file.name : (zone.custom_icon_path || '');
            updateIcons();
            card.querySelectorAll('[data-placement]').forEach(panel => {
                const p = zone.placements[panel.dataset.placement];
                const input = name => panel.querySelector(`[data-p-field="${name}"]`);
                const part = name => panel.querySelector(`[data-p-role="${name}"]`);
                input('enabled').checked = p.enabled;
                input('position').value = p.position;
                input('link_type').value = p.link_type;
                input('anchor').value = p.link_type === 'anchor' ? p.link_target || '' : '';
                panel.open = p.enabled;
                const update = () => {
                    part('settings').hidden = !p.enabled;
                    part('status').textContent = p.enabled ? part('status').dataset.enabled : part('status').dataset.disabled;
                    input('position').disabled = !p.enabled;
                    input('link_type').disabled = !p.enabled;
                    part('cms-label').hidden = p.link_type !== 'cms';
                    part('anchor-label').hidden = p.link_type !== 'anchor';
                    input('cms').disabled = !p.enabled || p.link_type !== 'cms';
                    input('cms').required = p.enabled && p.link_type === 'cms';
                    input('anchor').disabled = !p.enabled || p.link_type !== 'anchor';
                    input('anchor').required = p.enabled && p.link_type === 'anchor';
                };
                input('enabled').addEventListener('change', event => { p.enabled = event.target.checked; update(); });
                input('position').addEventListener('input', event => { p.position = event.target.value; });
                input('link_type').addEventListener('change', event => {
                    p.link_type = event.target.value;
                    p.link_target = p.link_type === 'cms' ? input('cms').value : p.link_type === 'anchor' ? input('anchor').value : null;
                    update();
                });
                input('cms').addEventListener('change', event => { p.link_target = event.target.value; });
                input('anchor').addEventListener('input', event => { p.link_target = event.target.value; });
                update();
            });
            localizeCard(card, zone);
            card.querySelector('[data-action="remove"]').addEventListener('click', () => {
                captureTexts();
                if (zone.id_zone !== null) deleted.add(String(zone.id_zone));
                zones.splice(index, 1);
                render();
            });
            ['up', 'down'].forEach(action => {
                const button = card.querySelector(`[data-action="${action}"]`);
                const destination = index + (action === 'up' ? -1 : 1);
                button.disabled = destination < 0 || destination >= zones.length;
                button.addEventListener('click', () => {
                    captureTexts();
                    [zones[index], zones[destination]] = [zones[destination], zones[index]];
                    render();
                    const moved = list.children[destination];
                    const preferred = moved.querySelector(`[data-action="${action}"]`);
                    // At an edge the move button becomes disabled; keep focus in the moved zone.
                    (preferred.disabled ? moved.querySelector('[data-action="remove"]') : preferred).focus();
                });
            });
            list.appendChild(card);
        });
        add.disabled = !language;
        form.querySelector('[type="submit"]').disabled = !language;
        status.textContent = String(zones.length);
    }

    languageSelect.addEventListener('change', () => {
        captureTexts();
        language = languageSelect.value;
        // Keep each existing text input and file control: only its translation changes.
        Array.from(list.children).forEach((card, index) => localizeCard(card, zones[index]));
    });
    add.addEventListener('click', () => {
        if (!language) return;
        captureTexts();
        zones.push({
            id_zone: null,
            client_key: `zone-${++nextKey}`,
            position: zones.length + 1,
            active: true,
            icon_type: 'predefined', icon_name: 'truck', custom_icon_path: null,
            placements: normalizePlacements(null, zones.length + 1), texts: {}, file: null,
        });
        render();
        list.lastElementChild.querySelector('[data-field="text"]').focus();
    });
    form.addEventListener('submit', event => {
        captureTexts();
        serialized.replaceChildren();
        if (!language) {
            event.preventDefault();
            return;
        }
        const hidden = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = name;
            input.value = value == null ? '' : String(value);
            serialized.appendChild(input);
        };
        // Explicit marker distinguishes intentional removal of all zones from an absent payload.
        hidden('trust_signals_present', '1');
        deleted.forEach(id => hidden('trust_deleted[]', id));
        zones.forEach((zone, index) => {
            // File field indices match serialized zone indices after all edits/reordering.
            zone.fileInput.name = `trust_icons[${index}]`;
            const prefix = `trust_signals[${index}]`;
            ['id_zone', 'position', 'icon_type', 'icon_name', 'custom_icon_path'].forEach(key => {
                hidden(`${prefix}[${key}]`, zone[key]);
            });
            hidden(`${prefix}[active]`, zone.active ? '1' : '0');
            hidden(`${prefix}[upload_selected]`, zone.icon_type === 'custom' && zone.file ? '1' : '0');
            placementNames.forEach(name => {
                const p = zone.placements[name];
                ['position', 'link_type', 'link_target'].forEach(key => hidden(`${prefix}[placements][${name}][${key}]`, p[key]));
                hidden(`${prefix}[placements][${name}][enabled]`, p.enabled ? '1' : '0');
            });
            config.languages.forEach(lang => {
                hidden(`${prefix}[text][${lang.id_lang}]`, zone.texts[String(lang.id_lang)] || '');
            });
        });
        hidden('trust_payload_complete', '1');
        // Browser performs a normal multipart POST with the original file controls.
    });
    render();
});
