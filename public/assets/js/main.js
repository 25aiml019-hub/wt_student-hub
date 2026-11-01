/**
 * StudentHub - Global Interactive Utilities & Design System Controller
 * Course: AIUE203 - Web Development Fundamentals
 * Author: Daksh (25AIML019), CSPIT, CHARUSAT
 */

document.addEventListener("DOMContentLoaded", function () {
    const currentPath = window.location.pathname.split("/").pop();
    const isPagesSubdir = window.location.pathname.includes("/pages/");
    const isAdminSubdir = window.location.pathname.includes("/admin/");
    const isStudentSubdir = window.location.pathname.includes("/student/");

    // 1. Highlight active navigation link based on current URL path
    const navLinks = document.querySelectorAll(".main-nav a");
    navLinks.forEach((link) => {
        const linkHref = link.getAttribute("href");
        if (linkHref && (linkHref === currentPath || linkHref.endsWith("/" + currentPath))) {
            link.classList.add("active");
        }
    });

    // 2. Mobile Navigation Toggle Drawer
    const navToggleBtn = document.getElementById("nav-toggle-btn");
    const mainNav = document.getElementById("main-nav") || document.querySelector(".main-nav");
    if (navToggleBtn && mainNav) {
        navToggleBtn.addEventListener("click", function () {
            mainNav.classList.toggle("nav-open");
            const isOpen = mainNav.classList.contains("nav-open");
            navToggleBtn.setAttribute("aria-expanded", isOpen ? "true" : "false");
        });
    }

    // 3. Automatically populate CSRF token if form field exists and is empty
    const csrfInput = document.getElementById("csrf_token") || document.getElementById("form-csrf-token");
    if (csrfInput && !csrfInput.value) {
        let tokenUrl = "csrf-token.php";
        if (isPagesSubdir || isAdminSubdir || isStudentSubdir) {
            tokenUrl = "../csrf-token.php";
        }
        fetch(tokenUrl)
            .then(res => res.ok ? res.json() : null)
            .then(data => {
                if (data && data.csrf_token) {
                    csrfInput.value = data.csrf_token;
                }
            })
            .catch(() => {
                // Silently ignore in static preview mode
            });
    }

    // 4. Synchronize Header User Status & Navigation Across Static HTML & PHP Pages
    syncUserAuthenticationState(isPagesSubdir, isAdminSubdir, isStudentSubdir);

    // 5. Wrap uncontained tables in .table-responsive for seamless mobile scrolling
    document.querySelectorAll("table.data-table, main table").forEach((tbl) => {
        if (!tbl.parentElement.classList.contains("table-responsive") &&
            !tbl.parentElement.classList.contains("admin-table-wrapper")) {
            const wrapper = document.createElement("div");
            wrapper.className = "table-responsive";
            tbl.parentNode.insertBefore(wrapper, tbl);
            wrapper.appendChild(tbl);
        }
    });

    console.log("StudentHub initialized successfully.");
});

/**
 * Synchronize header user status bar and navigation links based on active session
 */
function syncUserAuthenticationState(isPagesSubdir, isAdminSubdir, isStudentSubdir) {
    let apiMeUrl = "../api/auth/me.php";
    if (isPagesSubdir) {
        apiMeUrl = "../../api/auth/me.php";
    } else if (isAdminSubdir || isStudentSubdir) {
        apiMeUrl = "../api/auth/me.php";
    }

    let publicPrefix = (isPagesSubdir || isAdminSubdir || isStudentSubdir) ? "../" : "";
    let pagesPrefix = isPagesSubdir ? "" : ((isAdminSubdir || isStudentSubdir) ? "../pages/" : "pages/");
    let adminUrl = isAdminSubdir ? "index.php" : (isPagesSubdir ? "../../admin/index.php" : "../admin/index.php");
    let studentUrl = isStudentSubdir ? "index.php" : (isPagesSubdir ? "../../student/index.php" : "../student/index.php");
    let logoutUrl = publicPrefix + "process-logout.php";
    let loginUrl = pagesPrefix + "login.html";
    let registerUrl = pagesPrefix + "registration.html";

    fetch(apiMeUrl)
        .then(res => res.ok ? res.json() : null)
        .then(data => {
            let headerStatus = document.getElementById("user-header-status");

            // If header status container doesn't exist, create it inside .site-header
            if (!headerStatus) {
                const siteHeader = document.querySelector(".site-header");
                if (siteHeader) {
                    let headerInner = siteHeader.querySelector(".site-header-inner");
                    if (!headerInner) {
                        headerInner = document.createElement("div");
                        headerInner.className = "site-header-inner";
                        while (siteHeader.firstChild) {
                            headerInner.appendChild(siteHeader.firstChild);
                        }
                        siteHeader.appendChild(headerInner);
                    }
                    headerStatus = document.createElement("div");
                    headerStatus.className = "user-header-status";
                    headerStatus.id = "user-header-status";
                    headerInner.appendChild(headerStatus);
                }
            }

            if (!headerStatus) return;

            // Only update DOM if the container is currently empty or static
            if (data && data.authenticated && data.user) {
                const user = data.user;
                const displayName = user.displayName || user.username || "User";
                const initial = displayName.charAt(0).toUpperCase();
                const role = user.role || "student";
                const portalUrl = (role === "admin") ? adminUrl : studentUrl;
                const portalLabel = (role === "admin") ? "Admin Portal" : "My Portal";

                headerStatus.innerHTML = `
                    <div class="user-badge-wrap">
                        <div class="user-avatar-circle ${role === 'admin' ? 'admin' : ''}">${escapeHtml(initial)}</div>
                        <div class="user-meta-text">
                            <span class="user-display-name">${escapeHtml(displayName)}</span>
                            <span class="user-role-badge ${role === 'admin' ? 'admin' : 'student'}">${escapeHtml(role.toUpperCase())}</span>
                        </div>
                    </div>
                    <a href="${escapeHtml(portalUrl)}" class="btn-header-action btn-header-portal">${portalLabel}</a>
                    <a href="${escapeHtml(logoutUrl)}" class="btn-header-action btn-header-logout">Sign Out</a>
                `;

                // Update .main-nav links
                const mainNav = document.querySelector(".main-nav");
                if (mainNav) {
                    // Update or remove Login link
                    const loginLink = Array.from(mainNav.querySelectorAll("a")).find(a => 
                        a.textContent.trim().toLowerCase() === "login" || a.getAttribute("href")?.includes("login")
                    );
                    if (loginLink) {
                        loginLink.textContent = `Logout (${user.username})`;
                        loginLink.setAttribute("href", logoutUrl);
                    }

                    // Ensure Course Enrollment link is present for authenticated students
                    if (role === "student") {
                        const hasCourseLink = Array.from(mainNav.querySelectorAll("a")).some(a => 
                            a.getAttribute("href")?.includes("course-enrollment")
                        );
                        if (!hasCourseLink) {
                            const coursesNav = document.createElement("a");
                            coursesNav.href = pagesPrefix + "course-enrollment.html";
                            coursesNav.textContent = "Course Enrollment";
                            if (loginLink) {
                                mainNav.insertBefore(coursesNav, loginLink);
                            } else {
                                mainNav.appendChild(coursesNav);
                            }
                        }
                    }

                    // Ensure Portal link is present
                    const hasPortalLink = Array.from(mainNav.querySelectorAll("a")).some(a => 
                        a.getAttribute("href")?.includes("admin/index.php") || a.getAttribute("href")?.includes("student/index.php")
                    );
                    if (!hasPortalLink && loginLink) {
                        const portalNav = document.createElement("a");
                        portalNav.href = portalUrl;
                        portalNav.textContent = portalLabel;
                        mainNav.insertBefore(portalNav, loginLink);
                    }
                }
            } else {
                // Not authenticated: hide Course Enrollment from public visitor navigation
                const mainNav = document.querySelector(".main-nav");
                if (mainNav) {
                    const courseLink = Array.from(mainNav.querySelectorAll("a")).find(a => 
                        a.getAttribute("href")?.includes("course-enrollment")
                    );
                    if (courseLink) {
                        courseLink.remove();
                    }
                }

                if (!headerStatus.children.length) {
                    // Render guest controls
                    headerStatus.innerHTML = `
                        <a href="${escapeHtml(loginUrl)}" class="btn-header-action btn-header-login">Sign In</a>
                        <a href="${escapeHtml(registerUrl)}" class="btn-header-action btn-header-register">Register</a>
                    `;
                }
            }
        })
        .catch(() => {
            // Silently ignore network / non-PHP server errors in static preview
        });
}

function escapeHtml(str) {
    if (!str) return "";
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}