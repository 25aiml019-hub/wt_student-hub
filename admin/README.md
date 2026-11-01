# Admin Module

This directory is reserved for the Administrative Portal and Management Dashboard.

## Practical Mapping
- **Practical 11:** Student Management CRUD (Search, Filter, Export)
- **Practical 12:** Event Management CRUD (Poster Upload, File Validation)
- **Practical 14:** Role-Based Access Control Dashboard, Dynamic Navigation, and Audit Log Viewer

## Access Control
Direct access is restricted to authenticated users with `role = 'admin'`. Middleware enforcement is provided via `app/middleware/role_middleware.php`.
