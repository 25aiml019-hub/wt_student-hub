# StudentHub WT19 API guide

Author: Daksh (25AIML019). All endpoints live in `api/wt19/` and return JSON `{ "success": bool, "message": "..." }`.
Every POST needs the session CSRF token (`csrf_token` in the JSON body or an `X-CSRF-Token` header). Get it from `csrf.php`.

| Endpoint | Method | Auth | Purpose |
|---|---|---|---|
| `csrf.php` | GET | none | Returns `csrf_token` for the session. |
| `register.php` | POST | none | Create a student account. Fields: `name, email, enrolment, mobile (optional), password, confirm_password, gender, department, address, hobbies[]`. |
| `login.php` | POST | none | `identifier` (email or enrollment number) + `password`. Returns `redirect` and `role`. Throttled to 8 tries / 10 min. |
| `logout.php` | POST | session | Ends the session. |
| `me.php` | GET | none | Current user or 401. |
| `events.php` | GET | none | Upcoming events with `registered_count` and `is_registered`. |
| `events.php` | POST | student | `event_id`, `action` = `register` (default) or `cancel`. |
| `profile.php` | GET | login | Own student profile. |
| `profile.php` | POST | login | Update `mobile, department, address, hobbies[]`. |
| `change_password.php` | POST | login | `current_password, new_password, confirm_password`. |
| `dashboard.php` | GET | login | Own registrations and counters (admins also get site totals). |
| `feedback.php` | POST | none | Save feedback (`name`, `feedback`). Throttled. |
| `feedback.php` | GET | admin | List feedback. |
| `contact.php` | POST | none | Save a contact message (`name, email, subject, message`). Throttled. |
| `contact.php` | GET | admin | List contact messages. |

Status codes: 401 not logged in, 403 forbidden or bad CSRF token, 404 not found, 409 duplicate, 422 validation, 429 throttled.
