# StudentHub: Campus Portal (WT19)

**Author:** Daksh  |  **Enrollment:** 25AIML019  |  **Institute:** CSPIT, CHARUSAT
**Subject:** AIUE203 Web Development Fundamentals  |  **Email:** dakshharkhani18@gmail.com

StudentHub is a campus portal where students browse and register for events, manage their profile, send feedback and contact the team. Administrators review submissions and manage students and events.

**Stack:** HTML5, CSS3 (variables, grid, dark mode), vanilla JavaScript (fetch API), PHP 8.1+ (PDO), MySQL / MariaDB.

## Features
- **Public pages:** Home, About, Events, Campus life, FAQ, Contact, Feedback. Responsive, keyboard accessible, light and dark theme remembered between visits.
- **Events:** live list from the database with search, category filter, registration and cancellation.
- **Accounts:** register with enrollment number, login by email or enrollment number, inline validation, password strength meter, change password.
- **Dashboard and profile:** your registrations, counters, editable details.
- **Admin:** login as admin, see totals, read feedback and contact messages, open the student and event management pages in `public/admin/`.
- **Security:** bcrypt passwords, CSRF tokens on every POST, prepared statements, output escaped with `textContent`, session regeneration on login, throttling on login/register/feedback/contact, admin-only data endpoints, blocked access to `.sql`, `.md` and `.env` files.

## Setup (Windows, XAMPP)
1. Copy this folder to `C:\xampp\htdocs\WT_project-main`.
2. Start **Apache** and **MySQL** in XAMPP.
3. In phpMyAdmin (or the `mysql` client) import, in this order: `database/schema.sql`, `database/extended_schema.sql`, `database/wt19_integration.sql`.
4. Copy `.env.example` to `.env`. The XAMPP defaults (`DB_HOST=127.0.0.1`, `DB_USER=root`, empty password) already work.
5. Create the first administrator from a terminal in the project folder: `php database/create_admin.php` (password of at least 12 characters).
6. Open **http://localhost/WT_project-main/** (it redirects to `25aiml019/index.html`).

Opening the HTML files directly from disk still works, but the pages then show sample events and forms cannot save, because PHP and MySQL are needed.

## Project layout
| Path | What it holds |
|---|---|
| `25aiml019/` | The website: `*.html` pages, `style.css`, `app.js`, images, `Main.java` |
| `api/wt19/` | JSON API used by the pages (see `WT19_API_GUIDE.md`) |
| `public/admin/`, `admin/` | Server-rendered admin tools for students and events |
| `app/` | Config, session/CSRF helpers, services |
| `database/` | SQL schema, extensions, WT19 integration, `create_admin.php` |
| `tests/` | `e2e_wt19.js` Playwright test used during development |
| `storage/` | Protected upload folders |

## Testing
`tests/e2e_wt19.js` drives a real browser against the running site (register, login, events, profile, password change, feedback, contact, admin, CSRF and role checks, mobile layout). Run the site on a test database, then `node tests/e2e_wt19.js <screenshot-folder>` with Playwright installed. At hand-over all 33 checks passed against MariaDB 10.11 and PHP 8.3.

## Notes
- Seed events are dated in 2027 so they show as upcoming. Add or edit events from the admin pages.
- Never commit `.env` or real student data. Use HTTPS in production and set `APP_DEBUG=false`.
