<?php
/**
 * Event Management CRUD Module (Practical 12)
 * StudentHub - CSPIT, CHARUSAT
 * Course: AIUE203 - Web Development Fundamentals
 * Author: Daksh (25AIML019)
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/security.php';
require_once __DIR__ . '/../app/middleware/role_middleware.php';
require_once __DIR__ . '/../app/services/event_service.php';

// Enforce admin role
checkRole('admin', '../pages/login.html');

$user = currentUser();
$csrfToken = getCsrfToken();
$categories = EventService::getCategories();
$totalEvents = EventService::count();

$pageTitle = 'Event Management CRUD - StudentHub Admin';
$currentPage = 'admin';
$adminActiveTab = 'events';
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
                    <span style="color: #1E3A8A; font-weight: 600;">Event Management</span>
                </nav>
                <h2>Practical 12: Event Management CRUD & Poster Upload</h2>
                <p>Manage campus schedules, categories, status states, and secure validated image uploads.</p>
            </div>
            <div class="admin-quick-actions">
                <button type="button" class="btn btn-success" id="btn-open-create-modal">+ Add New Event</button>
                <a href="index.php" class="btn btn-outline" style="text-decoration: none; border: 1px solid #CBD5E1; color: #334155; background: #fff;">Dashboard</a>
            </div>
        </div>

        <!-- Global Alert Container -->
        <div id="admin-alert" style="display: none; padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; font-weight: 500;"></div>

        <!-- Control Bar: Search & Filters -->
        <div class="admin-controls-bar" role="search" aria-label="Event search and filter bar">
            <div class="control-item" style="flex: 2 1 240px;">
                <label for="filter-search">Search Events</label>
                <input type="text" id="filter-search" placeholder="Search by title, description, or venue...">
            </div>

            <div class="control-item">
                <label for="filter-category">Category</label>
                <select id="filter-category">
                    <option value="all">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="control-item" style="flex: 1 1 120px;">
                <label for="filter-status">Status</label>
                <select id="filter-status">
                    <option value="all">All Statuses</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
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

        <!-- Event Records Card -->
        <div class="admin-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <div id="table-summary-text" style="font-size: 14px; color: #475569; font-weight: 500;">
                    Loading events...
                </div>
            </div>

            <div class="admin-table-wrapper">
                <table class="admin-table" id="events-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">Poster</th>
                            <th>Event Details</th>
                            <th>Category</th>
                            <th>Date & Time</th>
                            <th>Venue / Location</th>
                            <th>Status</th>
                            <th style="text-align: right; width: 170px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="events-tbody">
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: #64748B;">
                                Connecting to MySQL database via Fetch API...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div class="table-pagination" id="pagination-controls" style="display: none;">
                <div id="pagination-info" style="font-size: 13px; color: #64748B;"></div>
                <div class="pagination-pages" id="pagination-buttons"></div>
            </div>
        </div>
    </main>

    <!-- Modal: Create / Edit Event -->
    <div class="modal-overlay" id="event-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 id="modal-title">Add New Event</h3>
                <button type="button" class="modal-close-btn" id="modal-close-btn" aria-label="Close dialog">&times;</button>
            </div>
            <form id="event-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" id="form-csrf-token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="id" id="form-event-id" value="">
                <input type="hidden" name="action" id="form-action" value="create">

                <div class="modal-body">
                    <div id="form-alert" style="display: none; padding: 10px 14px; border-radius: 6px; margin-bottom: 15px; font-size: 13px;"></div>

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="form-title">Event Title *</label>
                            <input type="text" id="form-title" name="title" required placeholder="e.g. AI & Web Prototyping Workshop">
                        </div>

                        <div class="form-group">
                            <label for="form-category">Category *</label>
                            <select id="form-category" name="category" required>
                                <option value="Technical">Technical</option>
                                <option value="Workshop">Workshop</option>
                                <option value="Cultural">Cultural</option>
                                <option value="Sports">Sports</option>
                                <option value="Academic">Academic</option>
                                <option value="General">General</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="form-status">Status *</label>
                            <select id="form-status" name="status" required>
                                <option value="upcoming">Upcoming</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="form-date">Event Date *</label>
                            <input type="date" id="form-date" name="event_date" required>
                        </div>

                        <div class="form-group">
                            <label for="form-time">Event Time</label>
                            <input type="text" id="form-time" name="event_time" placeholder="e.g. 10:00 AM">
                        </div>

                        <div class="form-group full-width">
                            <label for="form-location">Location / Venue *</label>
                            <input type="text" id="form-location" name="location" required placeholder="e.g. Auditorium Hall A, CSPIT Campus">
                        </div>

                        <div class="form-group full-width">
                            <label for="form-desc">Event Description</label>
                            <textarea id="form-desc" name="description" rows="3" placeholder="Provide full event overview, schedule, and participation guidelines..."></textarea>
                        </div>

                        <!-- Poster File Upload (Practical 12 Security) -->
                        <div class="form-group full-width">
                            <label for="form-poster">Event Poster Image (Optional)</label>
                            <input type="file" id="form-poster" name="poster" accept=".jpg,.jpeg,.png,.webp">
                            <small style="color: #64748B; font-size: 12px; margin-top: 4px;">
                                Allowed formats: JPG, PNG, WebP (Max size: 2 MB). Script files are strictly rejected.
                            </small>
                            <div id="current-poster-container" style="display: none; margin-top: 10px;">
                                <div style="font-size: 12px; color: #475569; margin-bottom: 4px; font-weight: 600;">Current Poster:</div>
                                <img id="current-poster-preview" src="" alt="Poster Preview" class="event-poster-preview" style="max-height: 140px;">
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; margin-top: 6px; cursor: pointer;">
                                    <input type="checkbox" name="remove_poster" value="1" id="chk-remove-poster">
                                    <span>Remove current poster without uploading replacement</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="btn-cancel-form">Cancel</button>
                    <button type="submit" class="btn btn-success" id="btn-save-event">Save Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: View Event Details -->
    <div class="modal-overlay" id="view-modal" role="dialog" aria-modal="true" aria-labelledby="view-modal-title">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 id="view-modal-title">Event Schedule & Poster</h3>
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

    <!-- Modal: Delete Confirmation -->
    <div class="modal-overlay" id="delete-modal" role="dialog" aria-modal="true">
        <div class="modal-dialog" style="max-width: 480px;">
            <div class="modal-header">
                <h3 style="color: #DC2626;">Delete Event</h3>
                <button type="button" class="modal-close-btn" id="delete-modal-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <p id="delete-modal-text" style="font-size: 15px; color: #1E293B;">
                    Are you sure you want to delete this event?
                </p>
                <div style="background: #FEF2F2; border-left: 4px solid #DC2626; padding: 12px; margin-top: 15px; border-radius: 4px; font-size: 13px; color: #991B1B;">
                    <strong>Warning:</strong> Deleting will remove the event, registrations, and safely purge unreferenced poster images.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btn-cancel-delete">Cancel</button>
                <button type="button" class="btn btn-danger" id="btn-confirm-delete">Permanently Delete</button>
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
        const API_URL = "../api/events/";
        const POSTER_SERVER = "../public/serve-poster.php?file=";
        const CSRF_TOKEN = document.getElementById("form-csrf-token").value;

        // State
        let state = {
            page: 1,
            limit: 10,
            search: "",
            category: "all",
            status: "all",
            totalPages: 1,
            totalRecords: 0
        };

        let activeTargetEvent = null;

        // DOM elements
        const tbody = document.getElementById("events-tbody");
        const tableSummary = document.getElementById("table-summary-text");
        const paginationControls = document.getElementById("pagination-controls");
        const paginationInfo = document.getElementById("pagination-info");
        const paginationButtons = document.getElementById("pagination-buttons");
        const adminAlert = document.getElementById("admin-alert");

        const filterSearch = document.getElementById("filter-search");
        const filterCategory = document.getElementById("filter-category");
        const filterStatus = document.getElementById("filter-status");
        const filterLimit = document.getElementById("filter-limit");
        const btnReset = document.getElementById("btn-reset-filters");

        // Modals
        const eventModal = document.getElementById("event-modal");
        const eventForm = document.getElementById("event-form");
        const modalTitle = document.getElementById("modal-title");
        const formAlert = document.getElementById("form-alert");
        const currentPosterContainer = document.getElementById("current-poster-container");
        const currentPosterPreview = document.getElementById("current-poster-preview");
        const chkRemovePoster = document.getElementById("chk-remove-poster");

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

        // Fetch events from API
        async function fetchEvents() {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 25px; color: #64748B;">Loading events...</td></tr>`;

            const params = new URLSearchParams({
                page: state.page,
                limit: state.limit,
                search: state.search,
                category: state.category,
                status: state.status
            });

            try {
                const response = await fetch(`${API_URL}?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' }
                });

                if (!response.ok) {
                    throw new Error(`HTTP Error: ${response.status}`);
                }

                const json = await response.json();
                if (!json.success) {
                    throw new Error(json.message || "Failed to load events.");
                }

                state.totalRecords = json.total;
                state.totalPages = json.totalPages;
                state.page = json.page;

                renderTable(json.data);
                renderPagination(json);
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 25px; color: #DC2626;">Error: ${escapeHtml(err.message)}</td></tr>`;
                tableSummary.textContent = "Failed to load events.";
            }
        }

        function escapeHtml(str) {
            if (str === null || str === undefined) return "";
            return String(str)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Render events table
        function renderTable(events) {
            tbody.innerHTML = "";

            if (!events || events.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 30px; color: #64748B;">No campus events found matching the criteria.</td></tr>`;
                tableSummary.textContent = "Showing 0 events";
                paginationControls.style.display = "none";
                return;
            }

            const startIdx = (state.page - 1) * state.limit + 1;
            const endIdx = Math.min(startIdx + events.length - 1, state.totalRecords);
            tableSummary.textContent = `Showing ${startIdx}–${endIdx} of ${state.totalRecords} scheduled events`;

            events.forEach((ev) => {
                const tr = document.createElement("tr");

                const statusClass = (ev.status || "upcoming").toLowerCase();
                const posterSrc = ev.poster_image ? `${POSTER_SERVER}${encodeURIComponent(ev.poster_image)}` : `${POSTER_SERVER}placeholder.jpg`;

                tr.innerHTML = `
                    <td>
                        <img src="${posterSrc}" alt="Poster" class="poster-thumb">
                    </td>
                    <td>
                        <strong>${escapeHtml(ev.title)}</strong>
                        <div style="font-size: 13px; color: #64748B; margin-top: 4px; max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            ${escapeHtml(ev.description || "No description provided.")}
                        </div>
                    </td>
                    <td><span style="font-weight: 500;">${escapeHtml(ev.category || "General")}</span></td>
                    <td>
                        <div style="font-weight: 600;">${escapeHtml(ev.event_date)}</div>
                        <div style="font-size: 12px; color: #64748B;">${escapeHtml(ev.event_time || "10:00 AM")}</div>
                    </td>
                    <td>${escapeHtml(ev.location || "Campus")}</td>
                    <td><span class="status-badge ${statusClass}">${escapeHtml(ev.status || "upcoming")}</span></td>
                    <td style="text-align: right;">
                        <div class="action-buttons" style="justify-content: flex-end;">
                            <button type="button" class="btn btn-secondary btn-sm btn-view-ev" data-id="${escapeHtml(ev.id)}">View</button>
                            <button type="button" class="btn btn-sm btn-edit-ev" data-id="${escapeHtml(ev.id)}">Edit</button>
                            <button type="button" class="btn btn-danger btn-sm btn-del-ev" data-id="${escapeHtml(ev.id)}">Delete</button>
                        </div>
                    </td>
                `;

                tr.querySelector(".btn-view-ev").addEventListener("click", () => openViewModal(ev));
                tr.querySelector(".btn-edit-ev").addEventListener("click", () => openEditModal(ev));
                tr.querySelector(".btn-del-ev").addEventListener("click", () => openDeleteModal(ev));

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
            paginationInfo.textContent = `Page ${data.page} of ${data.totalPages} (${data.total} total events)`;
            paginationButtons.innerHTML = "";

            const prevBtn = document.createElement("button");
            prevBtn.textContent = "« Previous";
            prevBtn.disabled = data.page <= 1;
            prevBtn.addEventListener("click", () => {
                if (state.page > 1) {
                    state.page--;
                    fetchEvents();
                }
            });
            paginationButtons.appendChild(prevBtn);

            for (let i = 1; i <= data.totalPages; i++) {
                if (i === 1 || i === data.totalPages || (i >= data.page - 2 && i <= data.page + 2)) {
                    const pageBtn = document.createElement("button");
                    pageBtn.textContent = i;
                    if (i === data.page) pageBtn.className = "active";
                    pageBtn.addEventListener("click", () => {
                        state.page = i;
                        fetchEvents();
                    });
                    paginationButtons.appendChild(pageBtn);
                } else if (i === data.page - 3 || i === data.page + 3) {
                    const span = document.createElement("span");
                    span.textContent = "...";
                    span.style.padding = "6px 8px";
                    paginationButtons.appendChild(span);
                }
            }

            const nextBtn = document.createElement("button");
            nextBtn.textContent = "Next »";
            nextBtn.disabled = data.page >= data.totalPages;
            nextBtn.addEventListener("click", () => {
                if (state.page < data.totalPages) {
                    state.page++;
                    fetchEvents();
                }
            });
            paginationButtons.appendChild(nextBtn);
        }

        // Open View Modal
        function openViewModal(ev) {
            const posterSrc = ev.poster_image ? `${POSTER_SERVER}${encodeURIComponent(ev.poster_image)}` : `${POSTER_SERVER}placeholder.jpg`;

            viewBody.innerHTML = `
                <div style="margin-bottom: 20px; text-align: center;">
                    <img src="${posterSrc}" alt="Poster" class="event-poster-preview" style="max-height: 220px; width: auto; border-radius: 8px;">
                </div>
                <div style="display: grid; grid-template-columns: 130px 1fr; gap: 12px; font-size: 14px;">
                    <strong>Event Title:</strong> <span><strong>${escapeHtml(ev.title)}</strong></span>
                    <strong>Category:</strong> <span>${escapeHtml(ev.category || "General")}</span>
                    <strong>Date & Time:</strong> <span>${escapeHtml(ev.event_date)} at ${escapeHtml(ev.event_time || "10:00 AM")}</span>
                    <strong>Venue / Location:</strong> <span>${escapeHtml(ev.location)}</span>
                    <strong>Status:</strong> <span class="status-badge ${escapeHtml(ev.status)}">${escapeHtml(ev.status)}</span>
                    <strong>Description:</strong> <span>${escapeHtml(ev.description || "None provided.")}</span>
                    <strong>Created At:</strong> <span>${escapeHtml(ev.created_at || "N/A")}</span>
                </div>
            `;
            viewModal.classList.add("active");
        }

        // Open Create Modal
        document.getElementById("btn-open-create-modal").addEventListener("click", () => {
            eventForm.reset();
            document.getElementById("form-event-id").value = "";
            document.getElementById("form-action").value = "create";
            modalTitle.textContent = "Add New Campus Event";
            formAlert.style.display = "none";
            currentPosterContainer.style.display = "none";
            eventModal.classList.add("active");
        });

        // Open Edit Modal
        function openEditModal(ev) {
            eventForm.reset();
            formAlert.style.display = "none";
            document.getElementById("form-event-id").value = ev.id;
            document.getElementById("form-action").value = "update";
            modalTitle.textContent = `Edit Event #${ev.id}: ${ev.title}`;

            document.getElementById("form-title").value = ev.title || "";
            document.getElementById("form-category").value = ev.category || "Technical";
            document.getElementById("form-status").value = ev.status || "upcoming";
            document.getElementById("form-date").value = ev.event_date || "";
            document.getElementById("form-time").value = ev.event_time || "10:00 AM";
            document.getElementById("form-location").value = ev.location || "";
            document.getElementById("form-desc").value = ev.description || "";

            if (ev.poster_image) {
                currentPosterContainer.style.display = "block";
                currentPosterPreview.src = `${POSTER_SERVER}${encodeURIComponent(ev.poster_image)}`;
                chkRemovePoster.checked = false;
            } else {
                currentPosterContainer.style.display = "none";
            }

            eventModal.classList.add("active");
        }

        // Open Delete Modal
        function openDeleteModal(ev) {
            activeTargetEvent = ev;
            deleteText.innerHTML = `Event: <strong>${escapeHtml(ev.title)}</strong> (ID #${escapeHtml(ev.id)})<br>Date: <strong>${escapeHtml(ev.event_date)}</strong>`;
            deleteModal.classList.add("active");
        }

        // Handle Event Form Submit (Create / Update with Poster File Upload)
        eventForm.addEventListener("submit", async function (e) {
            e.preventDefault();
            formAlert.style.display = "none";

            const submitBtn = document.getElementById("btn-save-event");
            submitBtn.disabled = true;
            submitBtn.textContent = "Saving...";

            const formData = new FormData(eventForm);

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
                    throw new Error(res.message || "Failed to save event.");
                }

                eventModal.classList.remove("active");
                showAlert(res.message || "Event saved successfully!");
                fetchEvents();
            } catch (err) {
                formAlert.textContent = err.message;
                formAlert.style.display = "block";
                formAlert.style.background = "#FDE8E8";
                formAlert.style.color = "#9B1C1C";
                formAlert.style.border = "1px solid #F8B4B4";
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = "Save Event";
            }
        });

        // Handle Delete Confirm
        document.getElementById("btn-confirm-delete").addEventListener("click", async () => {
            if (!activeTargetEvent) return;

            try {
                const formData = new FormData();
                formData.append("action", "delete");
                formData.append("csrf_token", CSRF_TOKEN);
                formData.append("id", activeTargetEvent.id);

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
                    throw new Error(res.message || "Failed to delete event.");
                }

                deleteModal.classList.remove("active");
                showAlert(res.message);
                fetchEvents();
            } catch (err) {
                alert("Error: " + err.message);
            }
        });

        // Close Modals
        document.getElementById("modal-close-btn").addEventListener("click", () => eventModal.classList.remove("active"));
        document.getElementById("btn-cancel-form").addEventListener("click", () => eventModal.classList.remove("active"));
        document.getElementById("view-modal-close-btn").addEventListener("click", () => viewModal.classList.remove("active"));
        document.getElementById("btn-close-view").addEventListener("click", () => viewModal.classList.remove("active"));
        document.getElementById("delete-modal-close-btn").addEventListener("click", () => deleteModal.classList.remove("active"));
        document.getElementById("btn-cancel-delete").addEventListener("click", () => deleteModal.classList.remove("active"));

        // Filter Handlers
        filterSearch.addEventListener("input", debounce(() => {
            state.search = filterSearch.value.trim();
            state.page = 1;
            fetchEvents();
        }, 300));

        filterCategory.addEventListener("change", () => {
            state.category = filterCategory.value;
            state.page = 1;
            fetchEvents();
        });

        filterStatus.addEventListener("change", () => {
            state.status = filterStatus.value;
            state.page = 1;
            fetchEvents();
        });

        filterLimit.addEventListener("change", () => {
            state.limit = parseInt(filterLimit.value, 10);
            state.page = 1;
            fetchEvents();
        });

        btnReset.addEventListener("click", () => {
            filterSearch.value = "";
            filterCategory.value = "all";
            filterStatus.value = "all";
            filterLimit.value = "10";

            state.search = "";
            state.category = "all";
            state.status = "all";
            state.limit = 10;
            state.page = 1;
            fetchEvents();
        });

        function debounce(func, wait) {
            let timeout;
            return function (...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }

        // Initial Load
        fetchEvents();
    });
    </script>
</body>
</html>
