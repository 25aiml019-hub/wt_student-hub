<?php
/**
 * Admin Portal Entry Point & Dashboard (RBAC Protected)
 * StudentHub - CSPIT, CHARUSAT
 * Course: AIUE203 - Web Development Fundamentals (Practical 10, 11 & 12)
 * Author: Daksh (25AIML019)
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/middleware/role_middleware.php';
require_once __DIR__ . '/../app/services/student_service.php';
require_once __DIR__ . '/../app/services/event_service.php';
require_once __DIR__ . '/../app/services/audit_service.php';

// Enforce admin role on every request (HTTP 403 / redirect if unauthorized)
checkRole('admin', '../pages/login.html');

$user = currentUser();
$pageTitle = 'Admin Portal Dashboard';
$currentPage = 'admin';
$adminActiveTab = 'dashboard';
$basePath = '../';
$extraCss = ['admin.css'];

$totalStudents = StudentService::count();
$totalEvents = EventService::count();
$totalLogs = AuditService::count();
$recentLogs = AuditService::getRecent(6);

require_once __DIR__ . '/../app/includes/header.php';
require_once __DIR__ . '/../app/includes/nav.php';
require_once __DIR__ . '/../app/includes/admin_subnav.php';
?>

    <main class="admin-container">
        <!-- Header Panel -->
        <div class="admin-header-panel">
            <div class="admin-title-area">
                <h2>Administrative Dashboard</h2>
                <p>Logged in as <strong><?= htmlspecialchars($user['username']) ?></strong> (Role: <span style="text-transform: uppercase; color: #1E3A8A; font-weight: bold;"><?= htmlspecialchars($user['role']) ?></span>) &bull; CSPIT, CHARUSAT</p>
            </div>
            <div class="admin-quick-actions">
                <a href="students.php" class="btn" style="text-decoration: none;">Student Management</a>
                <a href="events.php" class="btn btn-secondary" style="text-decoration: none;">Event Management</a>
                <a href="../public/index.html" class="btn btn-outline" style="text-decoration: none; border: 1px solid #CBD5E1; color: #334155; background: #fff;">Public Portal</a>
                <a href="../public/process-logout.php" class="btn btn-danger" style="text-decoration: none;">Sign Out</a>
            </div>
        </div>

        <!-- Metric Stat Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Registered Students</div>
                <div class="stat-value"><?= number_format($totalStudents) ?></div>
                <div style="margin-top: 10px;">
                    <a href="students.php" style="color: #1E3A8A; font-size: 13px; text-decoration: none; font-weight: 600;">Manage records &rarr;</a>
                </div>
            </div>

            <div class="stat-card accent">
                <div class="stat-label">Campus Events</div>
                <div class="stat-value"><?= number_format($totalEvents) ?></div>
                <div style="margin-top: 10px;">
                    <a href="events.php" style="color: #059669; font-size: 13px; text-decoration: none; font-weight: 600;">Manage schedules &rarr;</a>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-label">Audit Log Actions</div>
                <div class="stat-value"><?= number_format($totalLogs) ?></div>
                <div style="margin-top: 10px; font-size: 13px; color: #64748B;">System events logged</div>
            </div>
        </div>

        <!-- Practical Modules Section -->
        <div class="admin-card">
            <h3 style="margin-top: 0; color: #1E3A8A; margin-bottom: 15px;">Administrative Modules</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <div style="border: 1px solid #E2E8F0; border-radius: 8px; padding: 20px; background: #F8FAFC;">
                    <h4 style="margin: 0 0 8px 0; color: #0F172A;">Practical 11: Student Management CRUD</h4>
                    <p style="font-size: 14px; color: #64748B; margin-bottom: 16px;">
                        Full lifecycle student administration. Create new students, filter by department or academic year, edit profile attributes, soft delete/deactivate records, and export sanitized CSVs.
                    </p>
                    <a href="students.php" class="btn" style="text-decoration: none; display: inline-block;">Open Student Directory &rarr;</a>
                </div>

                <div style="border: 1px solid #E2E8F0; border-radius: 8px; padding: 20px; background: #F8FAFC;">
                    <h4 style="margin: 0 0 8px 0; color: #0F172A;">Practical 12: Event Management CRUD</h4>
                    <p style="font-size: 14px; color: #64748B; margin-bottom: 16px;">
                        Campus event scheduling and poster uploads. Add new events, filter by category and status, upload/replace event posters with MIME and size verification, and edit schedules.
                    </p>
                    <a href="events.php" class="btn btn-secondary" style="text-decoration: none; display: inline-block;">Open Event Manager &rarr;</a>
                </div>
            </div>
        </div>

        <!-- Recent Audit Activity Log -->
        <div class="admin-card">
            <h3 style="margin-top: 0; color: #1E3A8A; margin-bottom: 15px;">Recent Administrative Audit Log</h3>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>User</th>
                            <th>IP Address</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentLogs)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #94A3B8; padding: 20px;">No audit records available.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentLogs as $log): ?>
                                <tr>
                                    <td>#<?= (int)$log['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($log['action']) ?></strong></td>
                                    <td><?= htmlspecialchars($log['details'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
                                    <td><code><?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?></code></td>
                                    <td><?= htmlspecialchars($log['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

<?php
require_once __DIR__ . '/../app/includes/footer.php';
?>