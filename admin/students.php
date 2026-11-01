<?php
/**
 * Student Management CRUD Module (Practical 11)
 * StudentHub - CSPIT, CHARUSAT
 * Course: AIUE203 - Web Development Fundamentals
 * Author: Daksh (25AIML019)
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/security.php';
require_once __DIR__ . '/../app/middleware/role_middleware.php';
require_once __DIR__ . '/../app/services/student_service.php';

// Enforce admin role
checkRole('admin', '../pages/login.html');

$user = currentUser();
$csrfToken = getCsrfToken();
$departments = StudentService::getDepartments();
$academicYears = StudentService::getAcademicYears();
$totalStudents = StudentService::count();

$pageTitle = 'Student Management CRUD - StudentHub Admin';
$currentPage = 'admin';
$adminActiveTab = 'students';
$basePath = '../';
$extraCss = ['admin.css'];

require_once __DIR__ . '/../app/includes/header.php';
require_once __DIR__ . '/../app/includes/nav.php';
require_once __DIR__ . '/../app/includes/admin_subnav.php';
?>

    <main class="admin-container">
        <!-- Breadcrumb & Header Panel -->
        <div class="admin-header-panel">
            <div class="admin-title-area">
                <nav aria-label="Breadcrumb" style="font-size: 13px; margin-bottom: 6px;">
                    <a href="index.php" style="color: #64748B; text-decoration: none;">Dashboard</a> &gt;
                    <span style="color: #1E3A8A; font-weight: 600;">Student Management</span>
                </nav>
                <h2>Practical 11: Student Management CRUD</h2>
                <p>Manage campus student directory with server-side validation, pagination, and transactional integrity.</p>
            </div>
            <div class="admin-quick-actions">
                <button type="button" class="btn btn-success" id="btn-open-create-modal">+ Add New Student</button>
                <a href="#" class="btn btn-secondary" id="btn-export-csv" style="text-decoration: none;">Export CSV</a>
                <a href="index.php" class="btn btn-outline" style="text-decoration: none; border: 1px solid #CBD5E1; color: #334155; background: #fff;">Dashboard</a>
            </div>
        </div>

        <!-- Global Alert Container -->
        <div id="admin-alert" style="display: none; padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; font-weight: 500;"></div>

        <!-- Control Bar: Search & Filters -->
        <div class="admin-controls-bar" role="search" aria-label="Student search and filter bar">
            <div class="control-item" style="flex: 2 1 240px;">
                <label for="filter-search">Search Students</label>
                <input type="text" id="filter-search" placeholder="Search by name, email, or course...">
            </div>

            <div class="control-item">
                <label for="filter-department">Department</label>
                <select id="filter-department">
                    <option value="all">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="control-item">
                <label for="filter-academic-year">Academic Year</label>
                <select id="filter-academic-year">
                    <option value="all">All Academic Years</option>
                    <?php foreach ($academicYears as $year): ?>
                        <option value="<?= htmlspecialchars($year) ?>"><?= htmlspecialchars($year) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="control-item" style="flex: 1 1 120px;">
                <label for="filter-status">Status</label>
                <select id="filter-status">
                    <option value="all">All Status</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive Only</option>
                </select>
            </div>

            <div class="control-item" style="flex: 0 1 90px;">
                <label for="filter-limit">Per Page</label>
                <select id="filter-limit">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                </select>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn btn-secondary" id="btn-reset-filters" style="height: 38px;">Reset</button>
            </div>
        </div>

        <!-- Student Records Card -->
        <div class="admin-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <div id="table-summary-text" style="font-size: 14px; color: #475569; font-weight: 500;">
                    Loading student records...
                </div>
            </div>

            <div class="admin-table-wrapper">
                <table class="admin-table" id="students-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>Student Name</th>
                            <th>Email Address</th>
                            <th>Mobile</th>
                            <th>Department</th>
                            <th>Course</th>
                            <th>Academic Year</th>
                            <th>Status</th>
                            <th style="text-align: right; width: 170px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="students-tbody">
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px; color: #64748B;">
                                Connecting to MySQL database via Fetch API...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Server-Side Pagination Bar -->
            <div class="table-pagination" id="pagination-controls" style="display: none;">
                <div id="pagination-info" style="font-size: 13px; color: #64748B;"></div>
                <div class="pagination-pages" id="pagination-buttons"></div>
            </div>
        </div>
    </main>

    <!-- Modal: Create / Edit Student -->
    <div class="modal-overlay" id="student-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 id="modal-title">Add New Student</h3>
                <button type="button" class="modal-close-btn" id="modal-close-btn" aria-label="Close dialog">&times;</button>
            </div>
            <form id="student-form">
                <input type="hidden" name="csrf_token" id="form-csrf-token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="id" id="form-student-id" value="">
                <input type="hidden" name="action" id="form-action" value="create">

                <div class="modal-body">
                    <div id="form-alert" style="display: none; padding: 10px 14px; border-radius: 6px; margin-bottom: 15px; font-size: 13px;"></div>

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="form-name">Full Name *</label>
                            <input type="text" id="form-name" name="name" required placeholder="e.g. Daksh">
                        </div>

                        <div class="form-group">
                            <label for="form-email">Email Address *</label>
                            <input type="email" id="form-email" name="email" required placeholder="student@charusat.edu.in">
                        </div>

                        <div class="form-group">
                            <label for="form-mobile">Mobile Number (10 digits) *</label>
                            <input type="tel" id="form-mobile" name="mobile" pattern="[0-9]{10}" maxlength="10" required placeholder="9876543210">
                        </div>

                        <div class="form-group">
                            <label for="form-course">Course *</label>
                            <input type="text" id="form-course" name="course" required placeholder="e.g. B.Tech">
                        </div>

                        <div class="form-group">
                            <label for="form-dept">Department *</label>
                            <input type="text" id="form-dept" name="department" required placeholder="e.g. Information Technology">
                        </div>

                        <div class="form-group">
                            <label for="form-year">Academic Year *</label>
                            <select id="form-year" name="academic_year" required>
                                <option value="2025-2026">2025-2026</option>
                                <option value="2024-2025">2024-2025</option>
                                <option value="2023-2024">2023-2024</option>
                                <option value="2022-2023">2022-2023</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="form-gender">Gender *</label>
                            <select id="form-gender" name="gender" required>
                                <option value="Female">Female</option>
                                <option value="Male">Male</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="form-status">Status</label>
                            <select id="form-status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label for="form-hobbies">Hobbies</label>
                            <input type="text" id="form-hobbies" name="hobbies" placeholder="e.g. Coding, Reading, Sports">
                        </div>

                        <div class="form-group full-width">
                            <label for="form-address">Address</label>
                            <textarea id="form-address" name="address" rows="2" placeholder="Residential or hostel address"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="btn-cancel-form">Cancel</button>
                    <button type="submit" class="btn btn-success" id="btn-save-student">Save Student</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: View Student Details -->
    <div class="modal-overlay" id="view-modal" role="dialog" aria-modal="true" aria-labelledby="view-modal-title">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 id="view-modal-title">Student Profile Details</h3>
                <button type="button" class="modal-close-btn" id="view-modal-close-btn">&times;</button>
            </div>
            <div class="modal-body" id="view-modal-body">
                <!-- Dynamically populated details -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btn-close-view">Close</button>
            </div>
        </div>
    </div>

    <!-- Modal: Delete / Deactivate Confirmation -->
    <div class="modal-overlay" id="delete-modal" role="dialog" aria-modal="true">
        <div class="modal-dialog" style="max-width: 480px;">
            <div class="modal-header">
                <h3 style="color: #DC2626;">Confirm Action</h3>
                <button type="button" class="modal-close-btn" id="delete-modal-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <p id="delete-modal-text" style="font-size: 15px; color: #1E293B;">
                    Are you sure you want to change the status or delete this student?
                </p>
                <div style="background: #FEF2F2; border-left: 4px solid #DC2626; padding: 12px; margin-top: 15px; border-radius: 4px; font-size: 13px; color: #991B1B;">
                    <strong>Note:</strong> Deactivation preserves student history and registrations. Permanent deletion removes the record from the database.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btn-cancel-delete">Cancel</button>
                <button type="button" class="btn" id="btn-confirm-deactivate" style="background-color: #D97706;">Deactivate / Toggle</button>
                <button type="button" class="btn btn-danger" id="btn-confirm-delete">Delete Permanently</button>
            </div>
        </div>
    </div>

    <footer class="site-footer">
        <p class="footer-text">
            &copy; 2026 StudentHub | Designed &amp; Developed by 
            <strong>Daksh</strong> (25AIML019, CSPIT CHARUSAT)
        </p>
    </footer>

    <script src="../public/assets/js/main.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const API_URL = "../api/students/";
        const CSRF_TOKEN = document.getElementById("form-csrf-token").value;

        // State
        let state = {
            page: 1,
            limit: 10,
            search: "",
            department: "all",
            academic_year: "all",
            status: "all",
            totalPages: 1,
            totalRecords: 0
        };

        let activeTargetStudent = null;

        // DOM elements
        const tbody = document.getElementById("students-tbody");
        const tableSummary = document.getElementById("table-summary-text");
        const paginationControls = document.getElementById("pagination-controls");
        const paginationInfo = document.getElementById("pagination-info");
        const paginationButtons = document.getElementById("pagination-buttons");
        const adminAlert = document.getElementById("admin-alert");

        const filterSearch = document.getElementById("filter-search");
        const filterDept = document.getElementById("filter-department");
        const filterYear = document.getElementById("filter-academic-year");
        const filterStatus = document.getElementById("filter-status");
        const filterLimit = document.getElementById("filter-limit");
        const btnReset = document.getElementById("btn-reset-filters");
        const btnExport = document.getElementById("btn-export-csv");

        // Modals
        const studentModal = document.getElementById("student-modal");
        const studentForm = document.getElementById("student-form");
        const modalTitle = document.getElementById("modal-title");
        const formAlert = document.getElementById("form-alert");

        const viewModal = document.getElementById("view-modal");
        const viewBody = document.getElementById("view-modal-body");

        const deleteModal = document.getElementById("delete-modal");
        const deleteText = document.getElementById("delete-modal-text");

        // Flash message helper
        function showAlert(msg, type = "success") {
            adminAlert.textContent = msg;
            adminAlert.style.display = "block";
            adminAlert.style.background = type === "success" ? "#DEF7EC" : "#FDE8E8";
            adminAlert.style.color = type === "success" ? "#03543F" : "#9B1C1C";
            adminAlert.style.border = `1px solid ${type === "success" ? "#84E1BC" : "#F8B4B4"}`;
            window.scrollTo({ top: 0, behavior: "smooth" });
            setTimeout(() => {
                adminAlert.style.display = "none";
            }, 6000);
        }

        // Fetch students from API
        async function fetchStudents() {
            tbody.innerHTML = `<tr><td colspan="9" style="text-align: center; padding: 25px; color: #64748B;">Loading students...</td></tr>`;

            const params = new URLSearchParams({
                page: state.page,
                limit: state.limit,
                search: state.search,
                department: state.department,
                academic_year: state.academic_year,
                status: state.status
            });

            // Update CSV export link
            btnExport.href = `${API_URL}?export=csv&${params.toString()}`;

            try {
                const response = await fetch(`${API_URL}?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' }
                });

                if (!response.ok) {
                    throw new Error(`HTTP Error: ${response.status}`);
                }

                const json = await response.json();
                if (!json.success) {
                    throw new Error(json.message || "Failed to load students.");
                }

                state.totalRecords = json.total;
                state.totalPages = json.totalPages;
                state.page = json.page;

                renderTable(json.data);
                renderPagination(json);
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="9" style="text-align: center; padding: 25px; color: #DC2626;">Error: ${escapeHtml(err.message)}</td></tr>`;
                tableSummary.textContent = "Failed to load records.";
            }
        }

        // Safe HTML escaper
        function escapeHtml(str) {
            if (str === null || str === undefined) return "";
            return String(str)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Render data table rows safely
        function renderTable(students) {
            tbody.innerHTML = "";

            if (!students || students.length === 0) {
                tbody.innerHTML = `<tr><td colspan="9" style="text-align: center; padding: 30px; color: #64748B;">No students found matching the selected filter criteria.</td></tr>`;
                tableSummary.textContent = "Showing 0 students";
                paginationControls.style.display = "none";
                return;
            }

            const startIdx = (state.page - 1) * state.limit + 1;
            const endIdx = Math.min(startIdx + students.length - 1, state.totalRecords);
            tableSummary.textContent = `Showing ${startIdx}–${endIdx} of ${state.totalRecords} registered students`;

            students.forEach((stu) => {
                const tr = document.createElement("tr");

                const statusClass = (stu.status === "active") ? "active" : "inactive";
                const statusLabel = (stu.status === "active") ? "Active" : "Inactive";

                tr.innerHTML = `
                    <td>#${escapeHtml(stu.id)}</td>
                    <td><strong>${escapeHtml(stu.name)}</strong></td>
                    <td>${escapeHtml(stu.email)}</td>
                    <td><code>${escapeHtml(stu.mobile)}</code></td>
                    <td>${escapeHtml(stu.department)}</td>
                    <td>${escapeHtml(stu.course)}</td>
                    <td>${escapeHtml(stu.academic_year || "2025-2026")}</td>
                    <td><span class="status-badge ${statusClass}">${statusLabel}</span></td>
                    <td style="text-align: right;">
                        <div class="action-buttons" style="justify-content: flex-end;">
                            <button type="button" class="btn btn-secondary btn-sm btn-view-stu" data-id="${escapeHtml(stu.id)}">View</button>
                            <button type="button" class="btn btn-sm btn-edit-stu" data-id="${escapeHtml(stu.id)}">Edit</button>
                            <button type="button" class="btn btn-danger btn-sm btn-del-stu" data-id="${escapeHtml(stu.id)}" data-name="${escapeHtml(stu.name)}" data-status="${escapeHtml(stu.status)}">Status</button>
                        </div>
                    </td>
                `;

                // Attach event listeners
                tr.querySelector(".btn-view-stu").addEventListener("click", () => openViewModal(stu));
                tr.querySelector(".btn-edit-stu").addEventListener("click", () => openEditModal(stu));
                tr.querySelector(".btn-del-stu").addEventListener("click", () => openDeleteModal(stu));

                tbody.appendChild(tr);
            });
        }

        // Render pagination controls
        function renderPagination(data) {
            if (data.totalPages <= 1) {
                paginationControls.style.display = "none";
                return;
            }

            paginationControls.style.display = "flex";
            paginationInfo.textContent = `Page ${data.page} of ${data.totalPages} (${data.total} total items)`;
            paginationButtons.innerHTML = "";

            // Previous Button
            const prevBtn = document.createElement("button");
            prevBtn.textContent = "« Previous";
            prevBtn.disabled = data.page <= 1;
            prevBtn.addEventListener("click", () => {
                if (state.page > 1) {
                    state.page--;
                    fetchStudents();
                }
            });
            paginationButtons.appendChild(prevBtn);

            // Numeric page buttons
            for (let i = 1; i <= data.totalPages; i++) {
                if (i === 1 || i === data.totalPages || (i >= data.page - 2 && i <= data.page + 2)) {
                    const pageBtn = document.createElement("button");
                    pageBtn.textContent = i;
                    if (i === data.page) pageBtn.className = "active";
                    pageBtn.addEventListener("click", () => {
                        state.page = i;
                        fetchStudents();
                    });
                    paginationButtons.appendChild(pageBtn);
                } else if (i === data.page - 3 || i === data.page + 3) {
                    const span = document.createElement("span");
                    span.textContent = "...";
                    span.style.padding = "6px 8px";
                    paginationButtons.appendChild(span);
                }
            }

            // Next Button
            const nextBtn = document.createElement("button");
            nextBtn.textContent = "Next »";
            nextBtn.disabled = data.page >= data.totalPages;
            nextBtn.addEventListener("click", () => {
                if (state.page < data.totalPages) {
                    state.page++;
                    fetchStudents();
                }
            });
            paginationButtons.appendChild(nextBtn);
        }

        // View Modal
        function openViewModal(stu) {
            viewBody.innerHTML = `
                <div style="display: grid; grid-template-columns: 140px 1fr; gap: 12px; font-size: 14px;">
                    <strong>Student ID:</strong> <span>#${escapeHtml(stu.id)}</span>
                    <strong>Full Name:</strong> <span>${escapeHtml(stu.name)}</span>
                    <strong>Email Address:</strong> <span>${escapeHtml(stu.email)}</span>
                    <strong>Mobile Number:</strong> <span>${escapeHtml(stu.mobile)}</span>
                    <strong>Course:</strong> <span>${escapeHtml(stu.course)}</span>
                    <strong>Department:</strong> <span>${escapeHtml(stu.department)}</span>
                    <strong>Academic Year:</strong> <span>${escapeHtml(stu.academic_year || "2025-2026")}</span>
                    <strong>Gender:</strong> <span>${escapeHtml(stu.gender)}</span>
                    <strong>Hobbies:</strong> <span>${escapeHtml(stu.hobbies || "None specified")}</span>
                    <strong>Address:</strong> <span>${escapeHtml(stu.address || "N/A")}</span>
                    <strong>Status:</strong> <span class="status-badge ${stu.status === "active" ? "active" : "inactive"}">${escapeHtml(stu.status)}</span>
                    <strong>Registered At:</strong> <span>${escapeHtml(stu.created_at || "N/A")}</span>
                </div>
            `;
            viewModal.classList.add("active");
        }

        // Open Create Modal
        document.getElementById("btn-open-create-modal").addEventListener("click", () => {
            studentForm.reset();
            document.getElementById("form-student-id").value = "";
            document.getElementById("form-action").value = "create";
            modalTitle.textContent = "Add New Student";
            formAlert.style.display = "none";
            studentModal.classList.add("active");
        });

        // Open Edit Modal
        function openEditModal(stu) {
            studentForm.reset();
            formAlert.style.display = "none";
            document.getElementById("form-student-id").value = stu.id;
            document.getElementById("form-action").value = "update";
            modalTitle.textContent = `Edit Student #${stu.id}: ${stu.name}`;

            document.getElementById("form-name").value = stu.name || "";
            document.getElementById("form-email").value = stu.email || "";
            document.getElementById("form-mobile").value = stu.mobile || "";
            document.getElementById("form-course").value = stu.course || "";
            document.getElementById("form-dept").value = stu.department || "";
            document.getElementById("form-year").value = stu.academic_year || "2025-2026";
            document.getElementById("form-gender").value = stu.gender || "Female";
            document.getElementById("form-status").value = stu.status || "active";
            document.getElementById("form-hobbies").value = stu.hobbies || "";
            document.getElementById("form-address").value = stu.address || "";

            studentModal.classList.add("active");
        }

        // Open Delete/Deactivate Modal
        function openDeleteModal(stu) {
            activeTargetStudent = stu;
            deleteText.innerHTML = `Student: <strong>${escapeHtml(stu.name)}</strong> (ID #${escapeHtml(stu.id)})<br>Current Status: <strong>${escapeHtml(stu.status)}</strong>`;
            deleteModal.classList.add("active");
        }

        // Handle Create / Edit Submission
        studentForm.addEventListener("submit", async function (e) {
            e.preventDefault();
            formAlert.style.display = "none";

            const submitBtn = document.getElementById("btn-save-student");
            submitBtn.disabled = true;
            submitBtn.textContent = "Saving...";

            const formData = new FormData(studentForm);
            const action = formData.get("action");

            try {
                const response = await fetch(API_URL, {
                    method: "POST",
                    body: formData,
                    headers: {
                        'X-CSRF-Token': CSRF_TOKEN,
                        'Accept': 'application/json'
                    }
                });

                const res = await response.json();

                if (!response.ok || !res.success) {
                    throw new Error(res.message || "Failed to save student record.");
                }

                studentModal.classList.remove("active");
                showAlert(res.message || "Student saved successfully!");
                fetchStudents();
            } catch (err) {
                formAlert.textContent = err.message;
                formAlert.style.display = "block";
                formAlert.style.background = "#FDE8E8";
                formAlert.style.color = "#9B1C1C";
                formAlert.style.border = "1px solid #F8B4B4";
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = "Save Student";
            }
        });

        // Handle Delete / Status Actions
        document.getElementById("btn-confirm-deactivate").addEventListener("click", async () => {
            if (!activeTargetStudent) return;
            await executeDeleteAction(activeTargetStudent.id, false);
        });

        document.getElementById("btn-confirm-delete").addEventListener("click", async () => {
            if (!activeTargetStudent) return;
            await executeDeleteAction(activeTargetStudent.id, true);
        });

        async function executeDeleteAction(id, permanent) {
            try {
                const formData = new FormData();
                formData.append("action", "delete");
                formData.append("csrf_token", CSRF_TOKEN);
                formData.append("id", id);
                if (permanent) formData.append("permanent", "1");

                const response = await fetch(API_URL, {
                    method: "POST",
                    body: formData,
                    headers: {
                        'X-CSRF-Token': CSRF_TOKEN,
                        'Accept': 'application/json'
                    }
                });

                const res = await response.json();
                if (!response.ok || !res.success) {
                    throw new Error(res.message || "Action failed.");
                }

                deleteModal.classList.remove("active");
                showAlert(res.message);
                fetchStudents();
            } catch (err) {
                alert("Error: " + err.message);
            }
        }

        // Close Modals
        document.getElementById("modal-close-btn").addEventListener("click", () => studentModal.classList.remove("active"));
        document.getElementById("btn-cancel-form").addEventListener("click", () => studentModal.classList.remove("active"));
        document.getElementById("view-modal-close-btn").addEventListener("click", () => viewModal.classList.remove("active"));
        document.getElementById("btn-close-view").addEventListener("click", () => viewModal.classList.remove("active"));
        document.getElementById("delete-modal-close-btn").addEventListener("click", () => deleteModal.classList.remove("active"));
        document.getElementById("btn-cancel-delete").addEventListener("click", () => deleteModal.classList.remove("active"));

        // Filter event listeners (reset page to 1 when filters change)
        filterSearch.addEventListener("input", debounce(() => {
            state.search = filterSearch.value.trim();
            state.page = 1;
            fetchStudents();
        }, 300));

        filterDept.addEventListener("change", () => {
            state.department = filterDept.value;
            state.page = 1;
            fetchStudents();
        });

        filterYear.addEventListener("change", () => {
            state.academic_year = filterYear.value;
            state.page = 1;
            fetchStudents();
        });

        filterStatus.addEventListener("change", () => {
            state.status = filterStatus.value;
            state.page = 1;
            fetchStudents();
        });

        filterLimit.addEventListener("change", () => {
            state.limit = parseInt(filterLimit.value, 10);
            state.page = 1;
            fetchStudents();
        });

        btnReset.addEventListener("click", () => {
            filterSearch.value = "";
            filterDept.value = "all";
            filterYear.value = "all";
            filterStatus.value = "all";
            filterLimit.value = "10";

            state.search = "";
            state.department = "all";
            state.academic_year = "all";
            state.status = "all";
            state.limit = 10;
            state.page = 1;
            fetchStudents();
        });

        // Simple debounce utility
        function debounce(func, wait) {
            let timeout;
            return function (...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }

        // Initial Load
        fetchStudents();
    });
    </script>
</body>
</html>
