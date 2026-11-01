/* StudentHub - WT19 | Daksh (25AIML019)
   Shared behaviour for every page: theme, nav, auth state, API calls and page logic.
   All user-provided text is inserted with textContent (never innerHTML). */
(function () {
  'use strict';
  const API = '../api/wt19/';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const page = document.body.dataset.page || '';

  function el(tag, attrs, ...kids) {
    const n = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs || {})) {
      if (k === 'class') n.className = v;
      else if (k === 'text') n.textContent = v;
      else if (k.startsWith('on')) n.addEventListener(k.slice(2), v);
      else if (v !== false && v != null) n.setAttribute(k, v === true ? '' : v);
    }
    kids.flat().forEach(k => n.append(k instanceof Node ? k : document.createTextNode(k ?? '')));
    return n;
  }

  /* ---------- theme ---------- */
  const store = {
    get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* storage blocked */ } }
  };
  function applyTheme(t) {
    document.documentElement.setAttribute('data-theme', t);
    const b = $('#themeBtn');
    if (b) { b.textContent = t === 'dark' ? '☀' : '☾'; b.setAttribute('aria-label', t === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'); }
  }
  applyTheme(store.get('sh-theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
  document.addEventListener('click', e => {
    if (e.target.closest('#themeBtn')) {
      const t = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      store.set('sh-theme', t); applyTheme(t);
    }
    if (e.target.closest('#menuBtn')) {
      const nav = $('#mainNav'); const open = nav.classList.toggle('open');
      $('#menuBtn').setAttribute('aria-expanded', open);
    }
  });

  /* ---------- toast ---------- */
  function toast(msg, kind) {
    let box = $('#toasts');
    if (!box) { box = el('div', { id: 'toasts', 'aria-live': 'polite' }); document.body.append(box); }
    const t = el('div', { class: 'toast ' + (kind || ''), role: kind === 'bad' ? 'alert' : 'status', text: msg });
    box.append(t); setTimeout(() => t.remove(), 4500);
  }

  /* ---------- API ---------- */
  let csrf = '';
  async function token() {
    if (csrf) return csrf;
    const r = await fetch(API + 'csrf.php', { credentials: 'same-origin' });
    const j = await r.json(); csrf = j.csrf_token || ''; return csrf;
  }
  async function parse(r) {
    let j; try { j = await r.json(); } catch (e) { throw new Error('The server did not return a valid response. Is Apache/PHP running?'); }
    return j;
  }
  async function get(file) {
    const r = await fetch(API + file, { credentials: 'same-origin' });
    const j = await parse(r);
    if (!r.ok || j.success === false) { const e = new Error(j.message || 'Request failed'); e.status = r.status; throw e; }
    return j;
  }
  async function post(file, data) {
    await token();
    const r = await fetch(API + file, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...data, csrf_token: csrf })
    });
    const j = await parse(r);
    if (!r.ok || !j.success) { const e = new Error(j.message || 'Request failed'); e.status = r.status; throw e; }
    return j;
  }
  const offlineHint = e => (e instanceof TypeError ? 'Cannot reach the server. Start Apache and MySQL in XAMPP and open the site through http://localhost/.' : e.message);

  /* ---------- auth state ---------- */
  let me = null;
  async function loadMe() {
    try { const j = await get('me.php'); me = j.user; if (j.csrf_token) csrf = j.csrf_token; }
    catch (e) { me = null; }
    return me;
  }
  function authItems() {
    if (me) return [
      el('span', { class: 'user-chip', text: me.name || me.username }),
      el('a', { class: 'btn btn-ghost btn-sm', href: me.role === 'admin' ? 'admin.html' : 'dashboard.html', text: me.role === 'admin' ? 'Admin' : 'Dashboard' }),
      el('button', { class: 'btn btn-sm', type: 'button', onclick: logout, text: 'Logout' })
    ];
    return [
      el('a', { class: 'btn btn-ghost btn-sm', href: 'login.html', text: 'Login' }),
      el('a', { class: 'btn btn-sm', href: 'register.html', text: 'Register' })
    ];
  }
  function renderAuthNav() {
    const box = $('#authBox'); if (!box) return;
    box.replaceChildren(...authItems());
    // the header buttons are hidden on small screens, so the menu drawer carries a copy
    const nav = $('#mainNav'); if (nav) nav.append(el('div', { class: 'nav-auth' }, ...authItems()));
  }
  async function logout() {
    try { await post('logout.php', {}); } catch (e) { /* already logged out */ }
    location.href = 'login.html';
  }
  function requireLogin(role) {
    if (!me) { location.replace('login.html?next=' + encodeURIComponent(location.pathname.split('/').pop())); return false; }
    if (role && me.role !== role) { toast('Administrator access required.', 'bad'); setTimeout(() => location.replace('dashboard.html'), 900); return false; }
    return true;
  }

  /* ---------- helpers ---------- */
  const fmtDate = d => new Date(d + 'T00:00:00').toLocaleDateString('en-IN', { day: 'numeric', month: 'long', year: 'numeric' });
  function setBusy(btn, busy, label) { btn.disabled = busy; if (label) { btn.dataset.label = btn.dataset.label || btn.textContent; btn.textContent = busy ? label : btn.dataset.label; } }
  function fieldError(input, msg) {
    const f = input.closest('.field'); if (!f) return;
    f.classList.toggle('invalid', !!msg);
    let e = $('.err', f); if (!e) { e = el('div', { class: 'err', role: 'alert' }); f.append(e); }
    e.textContent = msg || '';
    input.setAttribute('aria-invalid', msg ? 'true' : 'false');
  }
  function banner(form, msg, kind) {
    let b = $('.alert', form);
    if (!msg) { if (b) b.remove(); return; }
    if (!b) { b = el('div', { class: 'alert', role: 'alert' }); form.prepend(b); }
    b.className = 'alert ' + (kind || 'bad'); b.textContent = msg;
  }
  const FALLBACK_EVENTS = [
    { id: 0, title: 'Tech Workshop', category: 'Academic', event_date: '2027-08-10', event_time: '10:00 AM', location: 'Seminar Hall', description: 'Hands-on workshop on web development and AI tools for first and second year students.', registered_count: 0, is_registered: false },
    { id: 0, title: 'Coding Competition', category: 'Technical', event_date: '2027-08-15', event_time: '10:00 AM', location: 'Computer Lab', description: 'Individual coding contest with problem solving rounds and prizes for the top three.', registered_count: 0, is_registered: false },
    { id: 0, title: 'Sports Day', category: 'Sports', event_date: '2027-08-22', event_time: '09:00 AM', location: 'College Ground', description: 'Annual campus sports day with track, field and team events.', registered_count: 0, is_registered: false },
    { id: 0, title: 'Cultural Night', category: 'Cultural', event_date: '2027-09-05', event_time: '06:00 PM', location: 'Open Air Theatre', description: 'An evening of music, dance and drama performed by students.', registered_count: 0, is_registered: false }
  ];
  async function loadEvents() {
    try { const j = await get('events.php'); return { events: j.events, live: true }; }
    catch (e) { return { events: FALLBACK_EVENTS, live: false }; }
  }

  function eventCard(ev, live) {
    const d = new Date(ev.event_date + 'T00:00:00');
    const btn = el('button', { class: 'btn btn-sm' + (ev.is_registered ? ' btn-danger' : ''), type: 'button', text: ev.is_registered ? 'Cancel registration' : 'Register' });
    btn.addEventListener('click', async () => {
      if (!live) { toast('Backend not connected. Start Apache and MySQL to register.', 'bad'); return; }
      if (!me) { location.href = 'login.html?next=event.html'; return; }
      setBusy(btn, true);
      try {
        const j = await post('events.php', { event_id: ev.id, action: ev.is_registered ? 'cancel' : 'register' });
        ev.is_registered = !ev.is_registered; ev.registered_count += ev.is_registered ? 1 : -1;
        toast(j.message, 'good'); if (typeof renderEvents === 'function') renderEvents();
      } catch (e) { toast(offlineHint(e), 'bad'); }
      setBusy(btn, false);
    });
    if (live && !me) btn.textContent = 'Log in to register';
    return el('article', { class: 'card event' },
      el('div', { class: 'date-box', 'aria-hidden': 'true' }, el('b', { text: String(d.getDate()) }), el('span', { text: d.toLocaleDateString('en-IN', { month: 'short' }) })),
      el('div', { class: 'event-body' },
        el('span', { class: 'badge', text: ev.category }),
        el('h3', { text: ev.title }),
        el('div', { class: 'meta' }, el('span', { text: '📅 ' + fmtDate(ev.event_date) }), el('span', { text: '🕘 ' + (ev.event_time || 'TBA') }), el('span', { text: '📍 ' + ev.location })),
        el('p', { class: 'muted', text: ev.description || '' }),
        el('div', { style: 'display:flex;gap:12px;align-items:center;flex-wrap:wrap' }, btn, live ? el('small', { class: 'muted', text: ev.registered_count + ' registered' }) : '')
      ));
  }

  /* ---------- page: events (and home preview) ---------- */
  let renderEvents;
  async function eventsPage() {
    const list = $('#eventList'); const q = $('#eventSearch'); const chips = $('#eventChips');
    const { events, live } = await loadEvents();
    if (!live) { const b = $('#offline'); if (b) b.hidden = false; }
    let cat = new URLSearchParams(location.search).get('category') || 'All';
    const cats = ['All', ...new Set(events.map(e => e.category))];
    if (!cats.includes(cat)) cat = 'All';
    renderEvents = function () {
      chips.replaceChildren(...cats.map(c => el('button', { class: 'chip', type: 'button', 'aria-pressed': String(c === cat), text: c, onclick: () => { cat = c; renderEvents(); } })));
      const term = q.value.trim().toLowerCase();
      const shown = events.filter(e => (cat === 'All' || e.category === cat) && (!term || (e.title + ' ' + e.location + ' ' + (e.description || '')).toLowerCase().includes(term)));
      list.replaceChildren(...(shown.length ? shown.map(e => eventCard(e, live)) : [el('div', { class: 'card empty', text: 'No events match your search.' })]));
      $('#eventCount').textContent = shown.length + (shown.length === 1 ? ' event' : ' events');
    };
    q.addEventListener('input', () => renderEvents());
    renderEvents();
  }
  async function homePage() {
    const { events, live } = await loadEvents();
    const box = $('#homeEvents');
    renderEvents = () => box.replaceChildren(...events.slice(0, 3).map(e => eventCard(e, live)));
    renderEvents();
    $('#statEvents').textContent = events.length;
  }

  /* ---------- page: faq ---------- */
  function faqPage() {
    const items = $$('.faq-item');
    items.forEach(it => {
      const q = $('.faq-q', it), a = $('.faq-a', it);
      q.addEventListener('click', () => { const open = q.getAttribute('aria-expanded') === 'true'; q.setAttribute('aria-expanded', String(!open)); a.hidden = open; });
    });
    $('#faqSearch').addEventListener('input', e => {
      const t = e.target.value.trim().toLowerCase(); let n = 0;
      items.forEach(it => { const hit = !t || it.textContent.toLowerCase().includes(t); it.hidden = !hit; if (hit) n++; });
      $('#faqEmpty').hidden = n > 0;
    });
  }

  /* ---------- page: contact ---------- */
  function contactPage() {
    const f = $('#contactForm');
    f.addEventListener('submit', async e => {
      e.preventDefault(); banner(f);
      const v = Object.fromEntries(new FormData(f)); let bad = false;
      for (const n of ['name', 'email', 'subject', 'message']) {
        const i = f.elements[n]; let m = '';
        if (!v[n].trim()) m = 'This field is required.';
        else if (n === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v[n])) m = 'Enter a valid email address.';
        fieldError(i, m); if (m) bad = true;
      }
      if (bad) return;
      const b = $('button[type=submit]', f); setBusy(b, true, 'Sending…');
      try { const j = await post('contact.php', v); toast(j.message, 'good'); f.reset(); }
      catch (x) { banner(f, offlineHint(x)); }
      setBusy(b, false);
    });
  }

  /* ---------- page: login ---------- */
  function loginPage() {
    if (me) { location.replace(me.role === 'admin' ? 'admin.html' : 'dashboard.html'); return; }
    const f = $('#loginForm');
    const next = new URLSearchParams(location.search).get('next');
    $('#pwToggle').addEventListener('click', togglePw('password', '#pwToggle'));
    f.addEventListener('submit', async e => {
      e.preventDefault(); banner(f);
      const id = f.elements.identifier, pw = f.elements.password; let bad = false;
      fieldError(id, id.value.trim() ? '' : 'Enter your email or enrollment number.'); if (!id.value.trim()) bad = true;
      fieldError(pw, pw.value ? '' : 'Enter your password.'); if (!pw.value) bad = true;
      if (bad) return;
      const b = $('button[type=submit]', f); setBusy(b, true, 'Signing in…');
      try {
        const j = await post('login.php', { identifier: id.value.trim(), password: pw.value });
        const safe = next && /^[a-z0-9_-]+\.html$/i.test(next) && !(next === 'admin.html' && j.role !== 'admin');
        location.href = safe ? next : j.redirect;
      } catch (x) { banner(f, offlineHint(x)); setBusy(b, false); }
    });
  }
  function togglePw(id, btnSel) {
    return () => { const i = document.getElementById(id), b = $(btnSel); const show = i.type === 'password'; i.type = show ? 'text' : 'password'; b.textContent = show ? 'Hide' : 'Show'; };
  }

  /* ---------- page: register ---------- */
  function strength(p) {
    let s = 0; if (p.length >= 6) s++; if (p.length >= 10) s++; if (/[A-Z]/.test(p) && /[a-z]/.test(p)) s++; if (/[0-9]/.test(p)) s++; if (/[^A-Za-z0-9]/.test(p)) s++;
    return s;
  }
  function registerPage() {
    const f = $('#registerForm'); const pw = f.elements.password;
    const bar = $('#meter i');
    pw.addEventListener('input', () => { const s = strength(pw.value); bar.style.width = (s * 20) + '%'; bar.style.background = s <= 2 ? 'var(--danger)' : s <= 3 ? 'var(--accent)' : 'var(--ok)'; });
    $('#pwToggle').addEventListener('click', togglePw('password', '#pwToggle'));
    const rules = {
      name: v => v.trim().length < 2 ? 'Enter your full name.' : '',
      email: v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? '' : 'Enter a valid email address.',
      enrolment: v => /^[0-9]{2}aiml[0-9]{3}$/i.test(v.trim()) ? '' : 'Use the format 25aiml019.',
      mobile: v => !v.trim() || /^[0-9]{10}$/.test(v.trim()) ? '' : 'Mobile number must be exactly 10 digits.',
      password: v => /^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9]).{6,}$/.test(v) ? '' : 'At least 6 characters with an uppercase letter, a lowercase letter and a number.',
      confirm_password: v => v === f.elements.password.value && v ? '' : 'Passwords do not match.',
      department: v => v ? '' : 'Please select a department.',
      address: v => v.trim() ? '' : 'Please enter your address.'
    };
    Object.keys(rules).forEach(n => f.elements[n].addEventListener('blur', () => fieldError(f.elements[n], rules[n](f.elements[n].value))));
    f.addEventListener('submit', async e => {
      e.preventDefault(); banner(f); let bad = false;
      Object.keys(rules).forEach(n => { const m = rules[n](f.elements[n].value); fieldError(f.elements[n], m); if (m) bad = true; });
      const g = f.querySelector('input[name=gender]:checked'); const gerr = $('#genderErr'); gerr.textContent = g ? '' : 'Please select a gender.'; if (!g) bad = true;
      if (bad) { const first = $('.invalid input, .invalid select, .invalid textarea', f); if (first) first.focus(); return; }
      const b = $('button[type=submit]', f); setBusy(b, true, 'Creating account…');
      try {
        const j = await post('register.php', {
          name: f.elements.name.value.trim(), email: f.elements.email.value.trim(), enrolment: f.elements.enrolment.value.trim(),
          mobile: f.elements.mobile.value.trim(), password: f.elements.password.value, confirm_password: f.elements.confirm_password.value,
          gender: g.value, department: f.elements.department.value, address: f.elements.address.value.trim(),
          hobbies: $$('input[name=hobby]:checked', f).map(x => x.value)
        });
        toast(j.message, 'good'); setTimeout(() => location.href = 'login.html', 1200);
      } catch (x) { banner(f, offlineHint(x)); setBusy(b, false); }
    });
  }

  /* ---------- page: dashboard ---------- */
  async function dashboardPage() {
    if (!requireLogin()) return;
    try {
      const j = await get('dashboard.php');
      $('#hello').textContent = 'Welcome back, ' + (j.user.name || j.user.username);
      $('#tMine').textContent = j.my_events.length; $('#tUp').textContent = j.upcoming_total; $('#tFb').textContent = j.my_feedback_count;
      $('#tRole').textContent = j.user.role === 'admin' ? 'Admin' : 'Student';
      const list = $('#myEvents');
      list.replaceChildren(...(j.my_events.length ? j.my_events.map(ev => el('tr', {},
        el('td', { text: ev.title }), el('td', { text: fmtDate(ev.event_date) }), el('td', { text: ev.location }),
        el('td', {}, el('button', { class: 'btn btn-danger btn-sm', type: 'button', text: 'Cancel', onclick: async () => {
          try { const r = await post('events.php', { event_id: ev.id, action: 'cancel' }); toast(r.message, 'good'); dashboardPage(); } catch (x) { toast(x.message, 'bad'); }
        } })))) : [el('tr', {}, el('td', { colspan: '4', class: 'empty', text: 'You have not registered for any event yet.' }))]));
      if (j.admin) {
        $('#adminTiles').hidden = false;
        $('#aStudents').textContent = j.admin.students; $('#aEvents').textContent = j.admin.events; $('#aFb').textContent = j.admin.feedback; $('#aMsg').textContent = j.admin.messages;
      }
    } catch (e) { if (e.status === 401) return requireLogin(); toast(offlineHint(e), 'bad'); }
  }

  /* ---------- page: profile ---------- */
  async function profilePage() {
    if (!requireLogin()) return;
    const f = $('#profileForm'), pf = $('#pwForm');
    async function load() {
      const j = await get('profile.php'); const p = j.profile;
      $('#avatar').textContent = (p.name || '?').trim().charAt(0).toUpperCase();
      $('#pName').textContent = p.name; $('#pId').textContent = p.student_id || '';
      const rows = [['Email', p.email], ['Enrollment', (p.student_id || '').toUpperCase()], ['Mobile', p.mobile || 'Not added'], ['Course', p.course], ['Department', p.department], ['Academic year', p.academic_year], ['Gender', p.gender], ['Hobbies', p.hobbies || 'None'], ['Address', p.address || '']];
      $('#details').replaceChildren(...rows.flatMap(([k, v]) => [el('dt', { text: k }), el('dd', { text: v })]));
      f.elements.mobile.value = p.mobile || ''; f.elements.department.value = p.department; f.elements.address.value = p.address || '';
      const h = (p.hobbies || '').split(',').map(s => s.trim());
      $$('input[name=hobby]', f).forEach(c => c.checked = h.includes(c.value));
    }
    try { await load(); } catch (e) { if (e.status === 401) return requireLogin(); $('#details').replaceChildren(el('dd', { text: e.status === 404 ? 'Admin accounts have no student profile.' : offlineHint(e) })); }
    f.addEventListener('submit', async e => {
      e.preventDefault(); banner(f); const b = $('button[type=submit]', f); setBusy(b, true, 'Saving…');
      try {
        const j = await post('profile.php', { mobile: f.elements.mobile.value.trim(), department: f.elements.department.value, address: f.elements.address.value.trim(), hobbies: $$('input[name=hobby]:checked', f).map(x => x.value) });
        toast(j.message, 'good'); await load();
      } catch (x) { banner(f, offlineHint(x)); }
      setBusy(b, false);
    });
    pf.addEventListener('submit', async e => {
      e.preventDefault(); banner(pf); const b = $('button[type=submit]', pf); setBusy(b, true, 'Updating…');
      try {
        const j = await post('change_password.php', { current_password: pf.elements.current_password.value, new_password: pf.elements.new_password.value, confirm_password: pf.elements.confirm_password.value });
        toast(j.message, 'good'); pf.reset();
      } catch (x) { banner(pf, offlineHint(x)); }
      setBusy(b, false);
    });
  }

  /* ---------- page: feedback ---------- */
  function feedbackPage() {
    const f = $('#feedbackForm'); const msg = f.elements.feedback; const cnt = $('#fbCount');
    if (me && me.name) f.elements.name.value = me.name;
    msg.addEventListener('input', () => cnt.textContent = msg.value.length + ' / 2000');
    f.addEventListener('submit', async e => {
      e.preventDefault(); banner(f); let bad = false;
      ['name', 'feedback'].forEach(n => { const m = f.elements[n].value.trim() ? '' : 'This field is required.'; fieldError(f.elements[n], m); if (m) bad = true; });
      if (bad) return;
      const b = $('button[type=submit]', f); setBusy(b, true, 'Sending…');
      try { const j = await post('feedback.php', { name: f.elements.name.value.trim(), feedback: msg.value.trim() }); toast(j.message, 'good'); msg.value = ''; cnt.textContent = '0 / 2000'; }
      catch (x) { banner(f, offlineHint(x)); }
      setBusy(b, false);
    });
  }

  /* ---------- page: admin ---------- */
  async function adminPage() {
    if (!requireLogin('admin')) return;
    try {
      const d = await get('dashboard.php');
      $('#aStudents').textContent = d.admin.students; $('#aEvents').textContent = d.admin.events; $('#aFb').textContent = d.admin.feedback; $('#aMsg').textContent = d.admin.messages;
    } catch (e) { toast(offlineHint(e), 'bad'); }
    const panes = { feedback: $('#paneFeedback'), messages: $('#paneMessages') };
    async function fill(kind) {
      const body = kind === 'feedback' ? $('#fbRows') : $('#msgRows');
      try {
        const j = await get(kind === 'feedback' ? 'feedback.php' : 'contact.php');
        const rows = kind === 'feedback' ? j.feedback : j.messages;
        body.replaceChildren(...(rows.length ? rows.map(r => kind === 'feedback'
          ? el('tr', {}, el('td', { text: r.name }), el('td', { text: r.message }), el('td', { text: r.created_at }))
          : el('tr', {}, el('td', { text: r.name }), el('td', {}, el('a', { href: 'mailto:' + r.email, text: r.email })), el('td', { text: r.subject }), el('td', { text: r.message }), el('td', { text: r.created_at })))
          : [el('tr', {}, el('td', { colspan: '5', class: 'empty', text: 'Nothing here yet.' }))]));
      } catch (e) { body.replaceChildren(el('tr', {}, el('td', { colspan: '5', class: 'empty', text: offlineHint(e) }))); }
    }
    $$('[data-tab]').forEach(t => t.addEventListener('click', () => {
      $$('[data-tab]').forEach(x => x.setAttribute('aria-pressed', String(x === t)));
      Object.entries(panes).forEach(([k, p]) => p.hidden = k !== t.dataset.tab);
    }));
    fill('feedback'); fill('messages');
  }

  /* ---------- boot ---------- */
  (async function () {
    await loadMe(); renderAuthNav();
    const pages = { home: homePage, events: eventsPage, faq: faqPage, contact: contactPage, login: loginPage, register: registerPage, dashboard: dashboardPage, profile: profilePage, feedback: feedbackPage, admin: adminPage };
    if (page === 'register' && me) { location.replace(me.role === 'admin' ? 'admin.html' : 'dashboard.html'); return; }
    if (pages[page]) pages[page]();
  })();
})();
