(() => {
    'use strict';
    const root = document.querySelector('.nescal-recurrence');
    if (!root) return;
    const saved = JSON.parse(root.dataset.schedule);
    const existing = root.dataset.existing === '1';
    const scope = document.getElementById('nescal-scope');
    const output = document.getElementById('nescal-schedule');
    const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const positions = {1:'First',2:'Second',3:'Third',4:'Fourth',5:'Fifth','-1':'Last','-2':'Second to last','-3':'Third to last','-4':'Fourth to last','-5':'Fifth to last'};
    const frequencies = {daily:'Daily', weekly:'Weekly', monthly:'Monthly', yearly:'Yearly', once:'Once'};
    const units = {daily:'days', weekly:'weeks', monthly:'months', yearly:'years'};
    const rows = [];
    let active = !!saved.enabled;
    const element = (tag, text, className) => {
        const node = document.createElement(tag);
        if (text) node.textContent = text;
        if (className) node.className = className;
        return node;
    };
    function button(label, callback, primary = false) {
        const node = element('button', label, primary ? 'button' : 'button-link');
        node.type = 'button'; node.addEventListener('click', callback);
        return node;
    }
    function select(values, value) {
        const node = element('select');
        for (const [key, text] of Object.entries(values)) node.add(new Option(text, key));
        node.value = String(value);
        return node;
    }
    function input(type, value = '') {
        const node = element('input');
        node.type = type; node.value = value;
        if (type === 'number') { node.min = '1'; node.step = '1'; }
        return node;
    }
    function field(label, control) {
        const wrapper = element('label', '', 'nescal-field');
        wrapper.append(element('span', label), control);
        return wrapper;
    }
    function options(values) { return Object.fromEntries(values.map((value, i) => [i + 1, value])); }
    function checks(values, picked, label) {
        const wrapper = element('fieldset', '', 'nescal-checks');
        wrapper.append(element('legend', label));
        const boxes = values.map((value, index) => {
            const box = input('checkbox'); box.value = index + 1;
            box.checked = (picked || []).includes(index + 1);
            const text = element('label'); text.append(box, value.slice(0, 3)); wrapper.append(text);
            return box;
        });
        return {node: wrapper, values: () => boxes.filter(box => box.checked).map(box => Number(box.value))};
    }
    const current = element('p', '', 'nescal-current');
    const panel = element('div', '', 'nescal-panel');
    const rules = element('div');
    const exclusions = element('div');
    const extras = element('details');
    extras.append(element('summary', 'Extra dates and exceptions'), exclusions);
    const preview = element('div', '', 'nescal-preview');
    preview.setAttribute('role', 'status');

    function addRule(value = {}, exclude = false) {
        const once = value.frequency === 'once';
        const node = element('div', '', 'nescal-rule');
        const header = element('div', '', 'nescal-row');
        const freq = select(frequencies, value.frequency || 'weekly');
        const interval = input('number', value.interval || 1); interval.max = 99;
        const intervalField = field('Every', interval);
        const intervalUnit = element('span'); intervalField.append(intervalUnit);
        const remove = button('Remove', () => { rows.splice(rows.indexOf(row), 1); node.remove(); changed(); });
        header.append(field(exclude ? 'Exclude' : 'Frequency', freq), intervalField, remove);
        const weekly = checks(days, value.weekdays, 'On these days');
        const monthChoices = checks(months, value.months, 'In these months');
        const complex = (value.ordinal || []).length > 1 || (value.monthdays || []).length > 1 ||
            ((value.ordinal || []).length && (value.weekdays || []).length > 1) ||
            (!(value.ordinal || []).length && (value.weekdays || []).length > 0);
        const mode = select({date:'Day of the month', weekday:'Weekday of the month', custom:'Custom pattern'},
            complex ? 'custom' : (value.ordinal || []).length ? 'weekday' : 'date');
        const monthPattern = element('div', '', 'nescal-row'); monthPattern.append(field('Repeat on', mode));
        const monthDays = {'':'Event’s day of month'};
        for (let i = 1; i <= 31; i++) { monthDays[i] = 'Day ' + i; monthDays[-i] = i === 1 ? 'Last day' : i + ' days from the end'; }
        const day = select(monthDays, value.monthdays?.[0] || '');
        const ordinal = select(positions, value.ordinal?.[0] || 1);
        const weekday = select({'':'Event’s weekday', ...options(days)}, value.weekdays?.[0] || '');
        const dayField = field('Day', day), ordinalField = field('Week', ordinal), weekdayField = field('Weekday', weekday);
        const custom = element('div', '', 'nescal-row');
        const monthdayList = input('text', (value.monthdays || []).join(', '));
        const ordinalList = input('text', (value.ordinal || []).join(', '));
        custom.append(field('Days of month (1–31, or –1 for last)', monthdayList), field('Weeks (1–5, or –1 for last)', ordinalList));
        monthPattern.append(dayField, ordinalField, weekdayField, custom);
        const onceDate = input('date', (value.date || '').slice(0,10));
        const onceEnd = input('date', value.until || '');
        const onceTime = input('time', (value.date || '').slice(11,16));
        const onceBox = element('div', '', 'nescal-row');
        onceBox.append(field('Date', onceDate), exclude ? field('Through (optional)', onceEnd) : field('Start time (optional)', onceTime));
        const end = select({never:'Never',until:'On date',count:'After'}, value.end || 'never');
        const until = input('date', value.until || ''), count = input('number', value.count || 10); count.max = 2000;
        const untilField = field('Last date', until), countField = field('Occurrences', count);
        const endings = element('div', '', 'nescal-row'); endings.append(field('Ends', end), untilField, countField);
        const advanced = element('details');
        const starts = input('date', value.inherit_start ? '' : value.start || '');
        const time = input('time', value.inherit_time ? '' : (value.time || '').slice(0, 5));
        const duration = input('number', value.inherit_duration || !value.duration ? '' : value.duration / 60);
        duration.max = 44640; duration.step = 'any';
        const timing = element('div', '', 'nescal-row'); timing.append(field('Different start date', starts));
        if (!exclude) timing.append(field('Different start time', time), field('Duration in minutes', duration));
        const dailyFilter = checks(days, value.weekdays, 'Limit to these weekdays');
        advanced.append(element('summary', 'More options'), timing, dailyFilter.node);
        advanced.open = !!(starts.value || time.value || duration.value || (value.frequency === 'daily' && value.weekdays?.length));
        node.append(header, onceBox, monthChoices.node, monthPattern, weekly.node, endings, advanced);
        function redraw() {
            const f = freq.value, monthly = f === 'monthly' || f === 'yearly';
            onceBox.hidden = f !== 'once';
            intervalField.hidden = endings.hidden = advanced.hidden = f === 'once';
            intervalUnit.textContent = units[f] || '';
            monthChoices.node.hidden = f !== 'yearly';
            monthPattern.hidden = !monthly;
            weekly.node.hidden = f !== 'weekly' && !(monthly && mode.value === 'custom');
            dailyFilter.node.hidden = f !== 'daily';
            dayField.hidden = mode.value !== 'date';
            ordinalField.hidden = weekdayField.hidden = mode.value !== 'weekday';
            custom.hidden = mode.value !== 'custom';
            countField.hidden = end.value !== 'count'; untilField.hidden = end.value !== 'until';
        }
        const numbers = control => control.value.trim() ? control.value.split(',').map(item => Number(item.trim())) : [];
        function read() {
            if (freq.value === 'once') return {once: true, date: onceDate.value, end: exclude ? onceEnd.value : onceTime.value};
            const rule = {frequency: freq.value, interval: Number(interval.value || 1), end: end.value};
            if (freq.value === 'weekly') rule.weekdays = weekly.values();
            if (freq.value === 'daily') rule.weekdays = dailyFilter.values();
            if (freq.value === 'monthly' || freq.value === 'yearly') {
                if (mode.value === 'date') rule.monthdays = day.value ? [Number(day.value)] : [];
                if (mode.value === 'weekday') { rule.ordinal = [Number(ordinal.value)]; rule.weekdays = weekday.value ? [Number(weekday.value)] : []; }
                if (mode.value === 'custom') { rule.ordinal = numbers(ordinalList); rule.monthdays = numbers(monthdayList); rule.weekdays = weekly.values(); }
            }
            if (freq.value === 'yearly') rule.months = monthChoices.values();
            if (end.value === 'until') rule.until = until.value;
            if (end.value === 'count') rule.count = Number(count.value);
            if (starts.value) rule.start = starts.value;
            if (time.value && !exclude) rule.time = time.value;
            if (duration.value && !exclude) rule.duration = Number(duration.value) * 60;
            return rule;
        }
        const row = {read, exclude, node};
        rows.push(row); (exclude || once ? exclusions : rules).append(node);
        node.addEventListener('change', redraw); redraw();
        return freq;
    }
    function collect() {
        const result = {enabled: active, rules: [], additions: [], exclusions: [], exclude_rules: []};
        for (const row of rows) {
            const value = row.read();
            if (value.once) {
                if (row.exclude) result.exclusions.push(value.end ? [value.date, value.end] : [value.date, value.date]);
                else result.additions.push(value.date + (value.end ? ' ' + value.end : ''));
            } else result[row.exclude ? 'exclude_rules' : 'rules'].push(value);
        }
        return result;
    }
    function describe() {
        const data = collect(), rule = data.rules[0];
        if (!active) return existing ? 'New dates are paused.' : '';
        if (!rule) return data.additions.length ? 'Repeats on selected dates' : 'Choose a repeat schedule';
        const unit = units[rule.frequency];
        let text = rule.interval === 1 ? frequencies[rule.frequency] : 'Every ' + rule.interval + ' ' + unit;
        if (rule.ordinal?.length === 1 && rule.weekdays?.length === 1) {
            text += ' on the ' + positions[rule.ordinal[0]].toLowerCase() + ' ' + days[rule.weekdays[0] - 1];
        } else if (rule.weekdays?.length) text += ' on ' + rule.weekdays.map(n => days[n-1]).join(', ');
        else if (rule.monthdays?.length) text += ' on ' + rule.monthdays.map(n => n === -1 ? 'the last day' : 'day ' + n).join(', ');
        if (rule.end === 'until') text += ' through ' + rule.until;
        if (rule.end === 'count') text += ' for ' + rule.count + ' occurrences';
        if (data.rules.length > 1) text += ' and ' + (data.rules.length - 1) + ' more repeat rule(s)';
        return text;
    }
    const begin = button('Schedule multiple events', () => {
        active = true; if (!rows.length) addRule(); changed(); draw();
    }, true);
    const note = element('p', '', 'description');
    function changed() { output.value = JSON.stringify(collect()); current.textContent = describe(); preview.hidden = true; }
    function draw() {
        const single = existing && scope?.value === 'single';
        begin.hidden = active || single;
        if (existing) begin.textContent = 'Resume schedule';
        current.hidden = !active && !existing;
        panel.hidden = !active || single;
        note.hidden = !existing;
        note.textContent = single ? 'To edit the repeat pattern, choose this and following events or all events above.' :
            (scope?.value === 'all' ? 'Includes past and future dates in this schedule. Individually edited dates are kept.' : 'Earlier dates keep their current schedule. Individually edited dates are kept.');
        // Only validate controls that can affect this save.
        for (const control of panel.querySelectorAll('input,select')) {
            control.disabled = panel.hidden || !!control.closest('[hidden]');
        }
    }
    const addPattern = button('Add repeat rule', () => { const control = addRule(); changed(); draw(); control.focus(); });
    const addOnce = button('Add one date', () => { const control = addRule({frequency:'once'}); changed(); draw(); control.focus(); });
    const addExcept = button('Add exception', () => { const control = addRule({frequency:'once'}, true); changed(); draw(); control.focus(); });
    extras.append(addOnce, document.createTextNode(' · '), addExcept, document.createTextNode(' · '), addPattern);
    const previewButton = button('Preview dates', async () => {
        previewButton.disabled = true; preview.hidden = false; preview.textContent = 'Loading dates…';
        try {
            const value = name => document.querySelector('[name="' + name + '"]')?.value || '';
            const date = value('EventStartDate');
            const body = new URLSearchParams({action:'nescal_preview', nonce:NESCalendarAdmin.nonce, event:root.dataset.event,
                scope:scope?.value || '', schedule:JSON.stringify(collect())});
            if (date) {
                body.set('event_fields', JSON.stringify({date, time:value('EventStartTime'), endDate:value('EventEndDate'), endTime:value('EventEndTime'),
                    allDay:!!document.querySelector('[name="EventAllDay"]:checked'), zone:value('EventTimezone')}));
            }
            const response = await fetch(NESCalendarAdmin.ajax, {method:'POST', body});
            const result = await response.json();
            if (!result.success) throw new Error(result.data?.message || 'Save the event dates and try again.');
            const dates = result.data.dates.slice(0, 8);
            preview.replaceChildren(element('strong', dates.length ? 'Next dates · ' + result.data.timezone : 'No matching dates.'));
            if (dates.length) {
                const list = element('ol');
                for (const date of dates) {
                    const local = new Date(date.start.replace(' ','T') + 'Z');
                    list.append(element('li', local.toLocaleString(undefined, {timeZone:'UTC', weekday:'short', month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit'})));
                }
                preview.append(list);
            }
        } catch (error) { preview.textContent = error.message; }
        finally { previewButton.disabled = false; }
    }, true);
    const pause = button(existing ? 'Pause new dates' : 'Cancel repeat schedule', () => { active = false; changed(); draw(); });
    const actions = element('div', '', 'nescal-actions'); actions.append(previewButton, pause);
    panel.append(rules, extras, actions, preview);
    root.append(current, note, begin, panel);
    for (const rule of saved.rules || []) addRule(rule);
    for (const date of saved.additions || []) addRule({frequency:'once',date});
    for (const range of saved.exclusions || []) addRule({frequency:'once',date:range[0],until:range[1] === range[0] ? '' : range[1]}, true);
    for (const rule of saved.exclude_rules || []) addRule(rule, true);
    root.addEventListener('change', () => { changed(); draw(); });
    root.addEventListener('input', changed);
    root.closest('form')?.addEventListener('submit', changed);
    scope?.addEventListener('change', draw);
    changed(); draw();
})();
