(() => {
    'use strict';
    const picker = document.getElementById('nescal-group');
    if (picker) {
        const panel = document.getElementById('nescal-new-group');
        const name = document.getElementById('nescal-group-name');
        function updatePicker() {
            panel.hidden = picker.value !== 'new';
            name.required = !panel.hidden;
            if (!panel.hidden) name.focus();
        }
        picker.addEventListener('change', updatePicker);
        updatePicker();
    }

    const results = document.querySelector('.nescal-search-results');
    if (!results) return;
    const input = document.getElementById('nescal-search');
    const button = document.getElementById('nescal-find');
    const choices = document.createElement('div');
    const selected = document.createElement('div');
    results.append(choices, selected);
    async function search() {
        if (!input.value.trim()) { choices.textContent = 'Enter part of an event title.'; return; }
        button.disabled = true;
        choices.textContent = 'Searching…';
        try {
            const response = await fetch(NESCalendarAdmin.ajax, {method: 'POST', body: new URLSearchParams({
                action: 'nescal_find_events', nonce: NESCalendarAdmin.nonce, group: results.dataset.group, term: input.value.trim()
            })});
            const result = await response.json();
            if (!result.success) throw new Error();
            choices.replaceChildren();
            for (const event of result.data) {
                if (selected.querySelector('input[value="' + Number(event.id) + '"]')) continue;
                const add = document.createElement('button');
                add.type = 'button';
                add.className = 'button-link';
                add.textContent = 'Add ' + event.label;
                const row = document.createElement('p');
                row.append(add);
                choices.append(row);
                add.addEventListener('click', () => {
                    row.remove();
                    const label = document.createElement('label');
                    const check = document.createElement('input');
                    check.type = 'checkbox'; check.checked = true;
                    check.name = 'nescal_add[]'; check.value = event.id;
                    label.append(check, ' ' + event.label);
                    const entry = document.createElement('p');
                    entry.append(label); selected.append(entry);
                });
            }
            if (!choices.children.length) choices.textContent = 'No unassigned events match this title.';
            else choices.prepend('Choose an event, then save the series.');
        } catch { choices.textContent = 'Search could not finish. Try again.'; }
        finally { button.disabled = false; }
    }
    button.addEventListener('click', search);
    input.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); search(); } });
})();
