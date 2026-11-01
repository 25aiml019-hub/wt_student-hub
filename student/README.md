# Student Portal Module

This directory is reserved for student self-service features.

## Practical Mapping
- **Practical 7–10:** Student Profile and Session Tracking
- **Practical 13:** Asynchronous Event Registration & Course Status
- **Practical 15:** Integrated Student Portal Experience

## Access Control
Direct access is restricted to authenticated users with `role = 'student'` or `'admin'`. Middleware enforcement is provided via `app/middleware/auth_middleware.php`.
