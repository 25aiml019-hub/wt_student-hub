// StudentHub WT19 end-to-end test (Daksh, 25AIML019). Needs: a running site, an admin admin@example.com / AdminPass12345, and Playwright.
const { chromium } = require('playwright');
const B = process.env.BASE_URL || 'http://127.0.0.1:8080/25aiml019/';
let fails = 0;
const ok = (c, m) => { console.log((c ? 'PASS ' : 'FAIL ') + m); if (!c) fails++; };
(async () => {
  const br = await chromium.launch(); const ctx = await br.newContext({ viewport: { width: 1280, height: 800 } });
  pg.on('pageerror', e => errs.push('pageerror ' + e.message));
  pg.on('console', m => { if (m.type() === 'error' && !/401|403|404|409|422/.test(m.text())) errs.push('console ' + m.text()); });
  // all pages load, no 404 assets
  const bad = []; pg.on('response', r => { if (r.status() >= 400 && /\.(css|js|jpg|avif|png)$/.test(r.url())) bad.push(r.url()); });
  for (const p of ['index','about','event','campus','faq','contact','login','register','feedback','first']) { await pg.goto(B + p + '.html'); await pg.waitForTimeout(250); }
  ok(bad.length === 0, 'no broken assets ' + bad.join(','));
  // home events from API
  await pg.goto(B + 'index.html'); await pg.waitForSelector('#homeEvents .event');
  ok(await pg.locator('#homeEvents .event').count() === 3, 'home shows 3 live events');
  // register validation
  await pg.goto(B + 'register.html'); await pg.click('#registerForm button[type=submit]');
  ok(await pg.locator('.field.invalid').count() >= 5, 'register shows inline errors');
  await pg.fill('#fullname', 'Test Student'); await pg.fill('#email', 'test@example.com'); await pg.fill('#enrolment', '25aiml077');
  await pg.fill('#mobile', '9876543210'); await pg.fill('#password', 'Passw0rd'); await pg.fill('#confirm_password', 'Passw0rd');
  await pg.check('input[name=gender][value=Male]'); await pg.selectOption('#department', 'AIML'); await pg.check('input[name=hobby][value=Music]');
  await pg.fill('#address', '1 Test Street <b>x</b>'); await pg.click('#registerForm button[type=submit]');
  await pg.waitForURL('**/login.html', { timeout: 6000 }); ok(true, 'register -> login redirect');
  // duplicate register
  await pg.goto(B + 'register.html');
  await pg.fill('#fullname', 'Test Student'); await pg.fill('#email', 'test@example.com'); await pg.fill('#enrolment', '25aiml077');
  await pg.fill('#password', 'Passw0rd'); await pg.fill('#confirm_password', 'Passw0rd'); await pg.check('input[name=gender][value=Male]'); await pg.selectOption('#department', 'AIML'); await pg.fill('#address', 'x');
  await pg.click('#registerForm button[type=submit]'); await pg.waitForSelector('.alert.bad');
  ok(/already registered/.test(await pg.textContent('.alert.bad')), 'duplicate registration rejected');
  // bad login
  await pg.goto(B + 'login.html'); await pg.fill('#identifier', '25AIML077'); await pg.fill('#password', 'wrong'); await pg.click('#loginForm button[type=submit]');
  await pg.waitForSelector('.alert.bad'); ok(/Invalid/.test(await pg.textContent('.alert.bad')), 'wrong password rejected');
  // good login (case-insensitive enrollment)
  await pg.fill('#password', 'Passw0rd'); await pg.click('#loginForm button[type=submit]'); await pg.waitForURL('**/dashboard.html');
  await pg.waitForFunction(() => document.querySelector('#hello').textContent.includes('Test Student'));
  ok(true, 'login -> dashboard with name');
  // protected redirect when logged out is tested later; events register
  await pg.goto(B + 'event.html'); await pg.waitForSelector('#eventList .event');
  ok(await pg.locator('#eventList .event').count() === 4, 'events page lists 4');
  await pg.click('#eventChips >> text=Sports'); ok(await pg.locator('#eventList .event').count() === 1, 'category filter works');
  await pg.click('#eventChips >> text=All'); await pg.fill('#eventSearch', 'coding'); ok(await pg.locator('#eventList .event').count() === 1, 'search works');
  await pg.locator('#eventList .event button:has-text("Register")').first().click(); await pg.waitForSelector('.toast.good');
  await pg.waitForSelector('#eventList .event button:has-text("Cancel registration")'); ok(true, 'event registration + button flips');
  await pg.goto(B + 'dashboard.html'); await pg.waitForFunction(() => document.querySelector('#tMine').textContent === '1'); ok(true, 'dashboard shows 1 registration');
  await pg.click('#myEvents button:has-text("Cancel")'); await pg.waitForFunction(() => document.querySelector('#tMine').textContent === '0'); ok(true, 'cancel from dashboard');
  // profile
  await pg.goto(B + 'profile.html'); await pg.waitForFunction(() => document.querySelector('#pName').textContent === 'Test Student');
  ok((await pg.textContent('#details')).includes('9876543210'), 'profile details load');
  ok(!(await pg.innerHTML('#details')).includes('<b>x</b>'), 'address is HTML-escaped');
  await pg.fill('#profileForm #mobile', '1234567890'); await pg.click('#profileForm button[type=submit]'); await pg.waitForSelector('.toast.good');
  await pg.waitForFunction(() => document.querySelector('#details').textContent.includes('1234567890')); ok(true, 'profile update');
  await pg.fill('#cp', 'Passw0rd'); await pg.fill('#np', 'Newpass1'); await pg.fill('#np2', 'Newpass1'); await pg.click('#pwForm button[type=submit]');
  await pg.waitForFunction(() => [...document.querySelectorAll('.toast')].some(t => /Password changed/.test(t.textContent))); ok(true, 'password change');
  // feedback + contact
  await pg.goto(B + 'feedback.html'); await pg.waitForFunction(() => document.querySelector('#fn').value === 'Test Student');
  await pg.fill('#ft', 'Great portal <script>alert(1)</script>'); await pg.click('#feedbackForm button[type=submit]'); await pg.waitForSelector('.toast.good'); ok(true, 'feedback saved');
  await pg.goto(B + 'contact.html'); await pg.fill('#cn', 'Visitor'); await pg.fill('#ce', 'v@example.com'); await pg.fill('#cs', 'Hello'); await pg.fill('#cm', 'Question here');
  await pg.click('#contactForm button[type=submit]'); await pg.waitForSelector('.toast.good'); ok(true, 'contact message saved');
  // faq
  await pg.goto(B + 'faq.html'); await pg.click('.faq-q >> nth=1'); ok(await pg.locator('.faq-a:visible').count() === 1, 'faq opens one answer');
  await pg.fill('#faqSearch', 'password'); ok(await pg.locator('.faq-item:visible').count() >= 1 && (await pg.locator('.faq-item:visible').count()) < 8, 'faq search');
  // student cannot see admin data
  const r = await pg.evaluate(async () => (await fetch('../api/wt19/feedback.php')).status); ok(r === 403, 'feedback list forbidden for student (' + r + ')');
  await pg.goto(B + 'admin.html'); await pg.waitForURL('**/dashboard.html'); ok(true, 'student bounced from admin.html');
  // logout
  await pg.click('#authBox button:has-text("Logout")'); await pg.waitForURL('**/login.html'); ok(true, 'logout');
  await pg.goto(B + 'dashboard.html'); await pg.waitForURL('**/login.html**'); ok(true, 'dashboard needs login');
  // csrf enforced
  const c = await pg.evaluate(async () => (await fetch('../api/wt19/feedback.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{"name":"a","feedback":"b"}' })).status); ok(c === 403, 'POST without csrf = 403');
  // admin
  await pg.goto(B + 'login.html'); await pg.fill('#identifier', 'admin@example.com'); await pg.fill('#password', 'AdminPass12345'); await pg.click('#loginForm button[type=submit]');
  await pg.waitForURL('**/admin.html'); await pg.waitForSelector('#fbRows td:has-text("Great portal")');
  ok((await pg.innerHTML('#fbRows')).includes('&lt;script&gt;'), 'admin sees escaped feedback');
  await pg.click('[data-tab=messages]'); await pg.waitForSelector('#msgRows td:has-text("Question here")'); ok(true, 'admin sees contact message');
  ok(await pg.textContent('#aStudents') === '1', 'admin stats students=1');
  // backend admin page still reachable
  const ar = await pg.goto(B + '../public/admin/students.php'); ok(ar.status() === 200, 'legacy admin students.php 200 (' + ar.status() + ')');
  // mobile layout
  await pg.setViewportSize({ width: 375, height: 700 }); await pg.goto(B + 'index.html');
  ok(await pg.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'no horizontal scroll at 375px');
  await pg.click('#menuBtn'); ok(await pg.locator('#mainNav.open').count() === 1, 'mobile menu opens');
  await pg.screenshot({ path: process.argv[2] + '/m_home.png' });
  await pg.setViewportSize({ width: 1280, height: 800 });
  for (const p of ['index','event','register','dashboard','faq','contact']) { await pg.goto(B + p + '.html'); await pg.waitForTimeout(500); await pg.screenshot({ path: `${process.argv[2]}/d_${p}.png` }); }
  console.log(errs.length ? 'JS ERRORS:\n' + errs.join('\n') : 'no JS errors');
  await br.close(); console.log(fails ? fails + ' FAILED' : 'ALL PASSED'); process.exit(fails ? 1 : 0);
})();
