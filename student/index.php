<?php
/**
 * Student Portal Entry Point (RBAC Protected)
 * StudentHub - CSPIT, CHARUSAT
 * Course: AIUE203 - Web Development Fundamentals (Practical 10, 11 & 12)
 * Author: Daksh (25AIML019)
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/middleware/auth_middleware.php';
require_once __DIR__ . '/../app/services/student_service.php';
require_once __DIR__ . '/../app/services/event_service.php';
require_once __DIR__ . '/../app/services/course_service.php';
require_once __DIR__ . '/../app/services/marksheet_service.php';
require_once __DIR__ . '/../app/services/timetable_service.php';

// Enforce student/authenticated access on every request
checkAuth('../pages/login.html');

$user = currentUser();
$pageTitle = 'Student Portal';
$currentPage = 'student';
$basePath = '../';
$extraCss = ['dashboard.css'];

// Query authentic student profile from MySQL database
$studentProfile = null;
try {
    if (!empty($user['student_id'])) {
        $studentProfile = StudentService::getById((int)$user['student_id']);
    }
    if (!$studentProfile && !empty($user['id'])) {
        $studentProfile = StudentService::findByUserId((int)$user['id']);
    }
    if (!$studentProfile && !empty($user['email'])) {
        $studentProfile = StudentService::findByEmail($user['email']);
    }
    if (!$studentProfile) {
        $studentProfile = StudentService::findById(1);
    }
} catch (Throwable $e) {
    error_log("Student profile fetch error: " . $e->getMessage());
}

$displayName = $studentProfile['name'] ?? $user['username'];
$collegeId = $studentProfile['student_id'] ?? '25AIML019';
$studentIdStr = !empty($collegeId) ? $collegeId : (!empty($studentProfile['id']) ? sprintf('STU-%04d', $studentProfile['id']) : '25AIML019');
$studentId = (int)($studentProfile['id'] ?? 1);

// Extended profile details: batch, counselor, CGPA
$batchName = 'Batch A';
$timetableBatch = 'A1';
$counselor = [
    'name' => 'Prof. Gaurav Kumar',
    'department' => 'Department of Artificial Intelligence and Machine Learning',
    'email' => 'gauravkumar.aiml@charusat.ac.in',
    'room_no' => 'Cabin 204'
];

try {
    $mysqli = getMySQLi();
    $stmt = $mysqli->prepare(
        "SELECT s.*, b.batch_name, c.name AS counselor_name, c.email AS counselor_email, " .
        "c.room_no AS counselor_room, c.department AS counselor_dept " .
        "FROM students s " .
        "LEFT JOIN batches b ON s.batch_id = b.id " .
        "LEFT JOIN counselors c ON s.counselor_id = c.id " .
        "WHERE s.id = ? LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $extRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($extRow) {
            $studentProfile = array_merge($studentProfile, $extRow);
            if (!empty($extRow['batch_name'])) {
                $batchName = $extRow['batch_name'];
                if (stripos($batchName, 'A') !== false) $timetableBatch = 'A1';
                elseif (stripos($batchName, 'B') !== false) $timetableBatch = 'B1';
                elseif (stripos($batchName, 'C') !== false) $timetableBatch = 'C1';
                elseif (stripos($batchName, 'D') !== false) $timetableBatch = 'D1';
            }
            if (!empty($extRow['counselor_name'])) {
                $counselor = [
                    'name' => $extRow['counselor_name'],
                    'department' => $extRow['counselor_dept'] ?? 'Department of Artificial Intelligence and Machine Learning',
                    'email' => $extRow['counselor_email'] ?? 'counselor@charusat.ac.in',
                    'room_no' => $extRow['counselor_room'] ?? 'Cabin 204'
                ];
            }
        }
    }
} catch (Throwable $e) {
    error_log("Extended profile fetch error: " . $e->getMessage());
}

// Query authentic registered campus events from MySQL database
$registeredEvents = [];
if ($studentProfile && !empty($studentProfile['id'])) {
    try {
        $registeredEvents = StudentService::getRegisteredEvents((int)$studentProfile['id']);
    } catch (Throwable $e) {
        error_log("Student registered events fetch error: " . $e->getMessage());
    }
}

// Fetch upcoming campus events for recommendations
$upcomingEvents = [];
try {
    $upcomingEvents = EventService::getAll('upcoming');
} catch (Throwable $e) {
    error_log("Upcoming events fetch error: " . $e->getMessage());
}

// Query Enrolled Courses
$enrolledCourses = [];
$totalCredits = 0;
try {
    $enrolledCourses = CourseService::getStudentEnrollments($studentId);
    foreach ($enrolledCourses as $ec) {
        $totalCredits += (int)($ec['credits'] ?? 0);
    }
} catch (Throwable $e) {
    error_log("Enrolled courses fetch error: " . $e->getMessage());
}

// Query Authentic Semester Results (SGPA & Weighted CGPA)
$semesterResults = [];
$totalEarnedCredits = 0;
$totalQualityPoints = 0;
$hasMissingSemester = false;
try {
    $mysqli = getMySQLi();
    $sStmt = $mysqli->prepare("
        SELECT semester, sgpa, credits, is_missing, remarks
        FROM semester_results
        WHERE student_id = ?
        ORDER BY semester ASC
    ");
    if ($sStmt) {
        $sStmt->bind_param("i", $studentId);
        $sStmt->execute();
        $sRes = $sStmt->get_result();
        while ($sRow = $sRes->fetch_assoc()) {
            $semesterResults[(int)$sRow['semester']] = $sRow;
            if ($sRow['is_missing']) {
                $hasMissingSemester = true;
            } else {
                $cr = (float)$sRow['credits'];
                $sg = (float)$sRow['sgpa'];
                $totalEarnedCredits += $cr;
                $totalQualityPoints += ($cr * $sg);
            }
        }
        $sStmt->close();
    }
} catch (Throwable $e) {
    error_log("Semester results query error: " . $e->getMessage());
}

$calculatedCgpa = ($totalEarnedCredits > 0 && !$hasMissingSemester) ? round($totalQualityPoints / $totalEarnedCredits, 2) : null;
$cgpaDisplay = $calculatedCgpa !== null ? number_format($calculatedCgpa, 2) : (!empty($studentProfile['cgpa']) ? number_format((float)$studentProfile['cgpa'], 2) : 'Pending');

// Query Timetable from TimetableService
$todaySchedule = TimetableService::getTodayClasses($timetableBatch);
$nextClass = TimetableService::getNextUpcomingClass($timetableBatch);
$weeklySchedule = TimetableService::getWeeklySchedule($timetableBatch);

// Query 78 FYAIML Classmates for Directory
$aimlClassmates = [];
$batchCounts = ['All' => 0, 'Batch A' => 0, 'Batch B' => 0, 'Batch C' => 0, 'Batch D' => 0];
try {
    $mysqli = getMySQLi();
    $dirQuery = "SELECT s.id, s.student_id, s.temp_student_id, s.name, s.course, " .
                "b.batch_name, c.name AS counselor_name " .
                "FROM students s " .
                "LEFT JOIN batches b ON s.batch_id = b.id " .
                "LEFT JOIN counselors c ON s.counselor_id = c.id " .
                "WHERE s.student_id LIKE '25AIML%' " .
                "ORDER BY s.student_id ASC";
    $dirRes = $mysqli->query($dirQuery);
    if ($dirRes) {
        while ($dRow = $dirRes->fetch_assoc()) {
            $aimlClassmates[] = $dRow;
            $batchCounts['All']++;
            $bKey = $dRow['batch_name'] ?? 'Batch A';
            if (isset($batchCounts[$bKey])) {
                $batchCounts[$bKey]++;
            }
        }
    }
} catch (Throwable $e) {
    error_log("Directory fetch error: " . $e->getMessage());
}

// Query Marksheets
$marksheetsList = [];
try {
    $marksheetsList = MarksheetService::getStudentMarksheets($studentId);
} catch (Throwable $e) {
    error_log("Marksheets fetch error: " . $e->getMessage());
}

require_once __DIR__ . '/../app/includes/header.php';
require_once __DIR__ . '/../app/includes/nav.php';
?>

    <main class="page-container dashboard-card">
        <!-- Student Portal Banner -->
        <div class="dashboard-header-banner">
            <div class="dashboard-welcome">
                <h2>Welcome back, <?= htmlspecialchars($displayName) ?>!</h2>
                <p>
                    College ID: <strong><?= htmlspecialchars($collegeId) ?></strong> &bull;
                    <?= htmlspecialchars($studentProfile['department'] ?? 'Department of AIML') ?> &bull;
                    <?= htmlspecialchars($studentProfile['course'] ?? 'B.Tech AIML') ?> &bull;
                    <span style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 4px;"><?= htmlspecialchars($batchName) ?> (<?= htmlspecialchars($timetableBatch) ?>)</span>
                </p>
            </div>
            <div class="dashboard-banner-actions">
                <a href="../public/pages/course-enrollment.php" class="btn btn-outline" style="background: rgba(255,255,255,0.15); color: #fff; border-color: rgba(255,255,255,0.4);">Course Enrollment</a>
                <a href="../public/pages/events.html" class="btn btn-outline" style="background: rgba(255,255,255,0.15); color: #fff; border-color: rgba(255,255,255,0.4);">Campus Events</a>
                <a href="../public/process-logout.php" class="btn btn-danger">Sign Out</a>
            </div>
        </div>

        <!-- Next Class Live Alert (If upcoming today) -->
        <?php if ($nextClass): ?>
            <div style="background: #ECFDF5; border: 1px solid #A7F3D0; border-left: 4px solid #10B981; border-radius: 8px; padding: 12px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 22px;">⏰</span>
                    <div>
                        <strong style="color: #065F46; font-size: 15px;">Next Class Today: <?= htmlspecialchars($nextClass['subject_name']) ?> (<?= htmlspecialchars($nextClass['subject_abbrev']) ?>)</strong>
                        <div style="color: #047857; font-size: 13px;">
                            Time: <strong><?= date('h:i A', strtotime($nextClass['start_time'])) ?> &ndash; <?= date('h:i A', strtotime($nextClass['end_time'])) ?></strong> &bull;
                            Room: <strong><?= htmlspecialchars($nextClass['room_no']) ?></strong> &bull;
                            Faculty: <strong><?= htmlspecialchars($nextClass['faculty_name']) ?></strong>
                        </div>
                    </div>
                </div>
                <span class="badge badge-active" style="background: #10B981; color: #fff;">Today &bull; <?= date('l') ?></span>
            </div>
        <?php endif; ?>

        <!-- Portal Tab Navigation -->
        <div class="portal-tabs-nav" role="tablist">
            <button type="button" class="portal-tab-btn active" data-tab="tab-profile">&#128100; Profile &amp; Counselor</button>
            <button type="button" class="portal-tab-btn" data-tab="tab-academics">&#128218; Academic &amp; SGPA Summary</button>
            <button type="button" class="portal-tab-btn" data-tab="tab-timetable">&#128197; Semester 3 Timetable</button>
            <button type="button" class="portal-tab-btn" data-tab="tab-directory">&#128101; Class Directory (<?= count($aimlClassmates) ?>)</button>
            <button type="button" class="portal-tab-btn" data-tab="tab-marksheets">&#128202; Marksheets &amp; OCR</button>
            <button type="button" class="portal-tab-btn" data-tab="tab-events">&#127942; Registered Events (<?= count($registeredEvents) ?>)</button>
        </div>

        <!-- ========================================== -->
        <!-- TAB 1: Profile & Counselor Allocation      -->
        <!-- ========================================== -->
        <div id="tab-profile" class="portal-tab-panel active">
            <section class="profile-details-section">
                <h3 style="color: var(--primary-color); display: flex; align-items: center; gap: 8px;">
                    <span>Official AIML Academic Profile</span>
                    <span class="badge badge-active" style="font-size: 11px;">Verified Institutional Record</span>
                </h3>

                <div class="student-info-grid">
                    <div class="student-info-item">
                        <strong>Full Name</strong>
                        <span><?= htmlspecialchars($studentProfile['name'] ?? $user['username']) ?></span>
                    </div>
                    <div class="student-info-item">
                        <strong>Official College ID</strong>
                        <span style="font-weight: 700; color: #1E3A8A;"><?= htmlspecialchars($collegeId) ?></span>
                    </div>
                    <div class="student-info-item">
                        <strong>Allocated Batch</strong>
                        <span style="font-weight: 700; color: #2563EB;"><?= htmlspecialchars($batchName) ?> (Practical: <?= htmlspecialchars($timetableBatch) ?>)</span>
                    </div>
                    <div class="student-info-item">
                        <strong>Admission Category ID</strong>
                        <span><?= htmlspecialchars($studentProfile['temp_student_id'] ?? '25TAIMLGGE') ?></span>
                    </div>
                    <div class="student-info-item">
                        <strong>Institutional Email</strong>
                        <span><?= htmlspecialchars($studentProfile['email'] ?? $user['email']) ?></span>
                    </div>
                    <div class="student-info-item">
                        <strong>Academic Department</strong>
                        <span><?= htmlspecialchars($studentProfile['department'] ?? 'Department of Artificial Intelligence and Machine Learning') ?></span>
                    </div>
                    <div class="student-info-item">
                        <strong>Enrolled Program</strong>
                        <span><?= htmlspecialchars($studentProfile['course'] ?? 'B.Tech in Artificial Intelligence & Machine Learning') ?></span>
                    </div>
                    <div class="student-info-item">
                        <strong>Academic Terms</strong>
                        <span>FY Roster (2025–26) &bull; Current Term (2026–27 Sem 3)</span>
                    </div>
                    <div class="student-info-item">
                        <strong>Cumulative GPA (CGPA)</strong>
                        <span style="font-weight: 700; color: #059669; font-size: 16px;">
                            <?= $cgpaDisplay ?> <?= is_numeric($cgpaDisplay) ? '/ 10.00 (Weighted)' : '' ?>
                        </span>
                    </div>
                    <div class="student-info-item">
                        <strong>Enrollment Status</strong>
                        <span>
                            <span class="badge badge-<?= ($studentProfile['status'] ?? 'active') === 'active' ? 'active' : 'inactive' ?>">
                                <?= htmlspecialchars(ucfirst($studentProfile['status'] ?? 'Active')) ?>
                            </span>
                        </span>
                    </div>
                    <div class="student-info-item">
                        <strong>Contact Mobile</strong>
                        <span><?= htmlspecialchars($studentProfile['mobile'] ?? '+91-9876543210') ?></span>
                    </div>
                    <div class="student-info-item">
                        <strong>Student Registration ID</strong>
                        <span><?= htmlspecialchars($studentIdStr) ?></span>
                    </div>
                    <div class="student-info-item" style="grid-column: span 3;">
                        <strong>Interests &amp; Technical Skills</strong>
                        <span><?= htmlspecialchars($studentProfile['hobbies'] ?? 'Artificial Intelligence, Deep Learning, Full Stack Web Development, Competitive Programming') ?></span>
                    </div>
                    <div class="student-info-item" style="grid-column: span 3;">
                        <strong>Campus Address</strong>
                        <span><?= htmlspecialchars($studentProfile['address'] ?? 'CSPIT, CHARUSAT Campus, 139, Changa, Anand, Gujarat - 388421') ?></span>
                    </div>
                </div>

                <!-- Assigned Faculty Counselor Card -->
                <div class="counselor-card" style="margin-top: 24px;">
                    <div class="counselor-avatar">&#128104;&#8205;&#127979;</div>
                    <div class="counselor-details">
                        <h4 style="margin: 0 0 6px; font-size: 16px; color: #1E3A8A;">Assigned Faculty Counselor: <?= htmlspecialchars($counselor['name']) ?></h4>
                        <p style="margin: 0 0 10px; color: #64748B; font-size: 13px;"><?= htmlspecialchars($counselor['department']) ?></p>
                        <div class="counselor-meta" style="display: flex; gap: 20px; flex-wrap: wrap; font-size: 13px;">
                            <span>&#128231; Email: <a href="mailto:<?= htmlspecialchars($counselor['email']) ?>" style="color: #2563EB; font-weight: 500;"><?= htmlspecialchars($counselor['email']) ?></a></span>
                            <span>&#127970; Location: <strong><?= htmlspecialchars($counselor['room_no']) ?></strong></span>
                            <span>&#128101; Assigned Cohort: <strong><?= htmlspecialchars($batchName) ?> (Students 25AIML001 &ndash; 25AIML020)</strong></span>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- ========================================== -->
        <!-- TAB 2: Academic & SGPA Summary             -->
        <!-- ========================================== -->
        <div id="tab-academics" class="portal-tab-panel">
            <section>
                <div style="margin-bottom: 20px;">
                    <h3 style="color: var(--primary-color); margin: 0 0 6px;">Academic Performance &amp; SGPA Breakdown</h3>
                    <p style="margin: 0; color: #64748B; font-size: 14px;">
                        Official verified semester examination results and mathematically weighted cumulative grade point average (CGPA = &Sigma;(SGPA &times; Credits) / &Sigma;Credits).
                    </p>
                </div>

                <div class="academic-stats-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
                    <div class="stat-overview-box" style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 8px; padding: 18px; text-align: center;">
                        <div class="stat-val" style="font-size: 32px; font-weight: 800; color: #1D4ED8;"><?= $cgpaDisplay ?></div>
                        <div class="stat-title" style="font-size: 13px; color: #1E40AF; text-transform: uppercase; font-weight: 600; margin-top: 4px;">Weighted CGPA (41 Credits)</div>
                    </div>
                    <div class="stat-overview-box" style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px; padding: 18px; text-align: center;">
                        <div class="stat-val" style="font-size: 32px; font-weight: 800; color: #15803D;">
                            <?= isset($semesterResults[1]) && !$semesterResults[1]['is_missing'] ? number_format((float)$semesterResults[1]['sgpa'], 2) : 'Pending' ?>
                        </div>
                        <div class="stat-title" style="font-size: 13px; color: #166534; text-transform: uppercase; font-weight: 600; margin-top: 4px;">Semester 1 SGPA (19 Credits)</div>
                    </div>
                    <div class="stat-overview-box" style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px; padding: 18px; text-align: center;">
                        <div class="stat-val" style="font-size: 32px; font-weight: 800; color: #15803D;">
                            <?= isset($semesterResults[2]) && !$semesterResults[2]['is_missing'] ? number_format((float)$semesterResults[2]['sgpa'], 2) : 'Pending' ?>
                        </div>
                        <div class="stat-title" style="font-size: 13px; color: #166534; text-transform: uppercase; font-weight: 600; margin-top: 4px;">Semester 2 SGPA (22 Credits)</div>
                    </div>
                    <div class="stat-overview-box" style="background: #FAF5FF; border: 1px solid #E9D5FF; border-radius: 8px; padding: 18px; text-align: center;">
                        <div class="stat-val" style="font-size: 32px; font-weight: 800; color: #7E22CE;"><?= $totalEarnedCredits ?> / 41</div>
                        <div class="stat-title" style="font-size: 13px; color: #6B21A8; text-transform: uppercase; font-weight: 600; margin-top: 4px;">Completed Credits</div>
                    </div>
                </div>

                <h4 style="color: var(--primary-color); margin: 0 0 12px;">Official Semester Performance Ledger</h4>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Academic Term</th>
                                <th>Curriculum Period</th>
                                <th>Total Credits</th>
                                <th>Term SGPA</th>
                                <th>Grade Points Earned</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Semester 1 -->
                            <tr>
                                <td><strong>Semester 1</strong></td>
                                <td>Academic Year 2025–26 (Odd Term)</td>
                                <td>19.00 Credits</td>
                                <td>
                                    <?php if (isset($semesterResults[1]) && !$semesterResults[1]['is_missing']): ?>
                                        <strong style="color: #15803D; font-size: 15px;"><?= number_format((float)$semesterResults[1]['sgpa'], 2) ?></strong>
                                    <?php else: ?>
                                        <span class="badge badge-pending">Pending / Missing Record</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($semesterResults[1]) && !$semesterResults[1]['is_missing']): ?>
                                        <?= number_format((float)$semesterResults[1]['sgpa'] * 19.00, 2) ?>
                                    <?php else: ?>
                                        &mdash;
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= (isset($semesterResults[1]) && !$semesterResults[1]['is_missing']) ? 'active' : 'pending' ?>">
                                        <?= (isset($semesterResults[1]) && !$semesterResults[1]['is_missing']) ? 'Verified' : 'Pending Verification' ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: #64748B;">
                                        <?= htmlspecialchars($semesterResults[1]['remarks'] ?? 'Official University Gazetted Result') ?>
                                    </span>
                                </td>
                            </tr>

                            <!-- Semester 2 -->
                            <tr>
                                <td><strong>Semester 2</strong></td>
                                <td>Academic Year 2025–26 (Even Term)</td>
                                <td>22.00 Credits</td>
                                <td>
                                    <?php if (isset($semesterResults[2]) && !$semesterResults[2]['is_missing']): ?>
                                        <strong style="color: #15803D; font-size: 15px;"><?= number_format((float)$semesterResults[2]['sgpa'], 2) ?></strong>
                                    <?php else: ?>
                                        <span class="badge badge-pending">Pending / Missing Record</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($semesterResults[2]) && !$semesterResults[2]['is_missing']): ?>
                                        <?= number_format((float)$semesterResults[2]['sgpa'] * 22.00, 2) ?>
                                    <?php else: ?>
                                        &mdash;
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= (isset($semesterResults[2]) && !$semesterResults[2]['is_missing']) ? 'active' : 'pending' ?>">
                                        <?= (isset($semesterResults[2]) && !$semesterResults[2]['is_missing']) ? 'Verified' : 'Pending Verification' ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: #64748B;">
                                        <?= htmlspecialchars($semesterResults[2]['remarks'] ?? 'Official University Gazetted Result') ?>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr style="background: #F8FAFC; font-weight: 700;">
                                <td colspan="2">Cumulative Total (1st Year)</td>
                                <td>41.00 Credits</td>
                                <td>
                                    <span style="color: #1D4ED8; font-size: 16px;"><?= $cgpaDisplay ?></span>
                                    <span style="font-size: 11px; font-weight: 400; color: #64748B;">(Weighted CGPA)</span>
                                </td>
                                <td><?= number_format($totalQualityPoints, 2) ?></td>
                                <td><span class="badge badge-active">Complete</span></td>
                                <td style="font-size: 12px; color: #059669;">Eligible for Semester 3 Promotion</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div style="margin-top: 30px; display: flex; justify-content: space-between; align-items: center;">
                    <h4 style="color: var(--primary-color); margin: 0;">Enrolled Semester 3 Courses</h4>
                    <a href="../pages/course-enrollment.php" class="btn btn-sm btn-primary" style="padding: 6px 14px; text-decoration:none;">Manage Course Enrollments &rarr;</a>
                </div>

                <?php if (!empty($enrolledCourses)): ?>
                    <div class="table-responsive" style="margin-top: 12px;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Course Name</th>
                                    <th>Department</th>
                                    <th>Credits</th>
                                    <th>Semester</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrolledCourses as $c): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($c['course_code']) ?></strong></td>
                                        <td><?= htmlspecialchars($c['course_name']) ?></td>
                                        <td><?= htmlspecialchars($c['department']) ?></td>
                                        <td><strong><?= (int)$c['credits'] ?></strong> Credits</td>
                                        <td>Semester <?= (int)($c['enrollment_semester'] ?? 3) ?></td>
                                        <td><span class="badge badge-active">&#10003; Enrolled</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <!-- ========================================== -->
        <!-- TAB 3: Semester 3 Timetable                -->
        <!-- ========================================== -->
        <div id="tab-timetable" class="portal-tab-panel">
            <section>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h3 style="color: var(--primary-color); margin: 0 0 4px;">Official Semester 3 Academic Timetable</h3>
                        <p style="margin: 0; color: #64748B; font-size: 14px;">
                            Academic Year 2026–27 &bull; Odd Term &bull; Division I &bull; Effective: <strong>06/07/2026</strong> &bull; Batch: <strong><?= htmlspecialchars($batchName) ?> (<?= htmlspecialchars($timetableBatch) ?>)</strong>
                        </p>
                    </div>
                    <span class="badge badge-active" style="padding: 6px 12px; font-size: 13px;">Classroom: Room 218</span>
                </div>

                <!-- Today's Schedule Card -->
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 18px; margin-bottom: 24px;">
                    <h4 style="margin: 0 0 12px; color: #1E3A8A; display: flex; align-items: center; gap: 8px;">
                        <span>📅</span> Today's Class Schedule (<?= date('l') ?>)
                    </h4>
                    <?php if (empty($todaySchedule)): ?>
                        <p style="margin: 0; color: #64748B; font-size: 14px;">No academic lectures scheduled for today. Enjoy your day or engage in self-study!</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table" style="margin: 0;">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Type</th>
                                        <th>Subject</th>
                                        <th>Room</th>
                                        <th>Faculty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($todaySchedule as $ts): ?>
                                        <tr>
                                            <td style="font-weight: 600; color: #2563EB;">
                                                <?= date('h:i A', strtotime($ts['start_time'])) ?> &ndash; <?= date('h:i A', strtotime($ts['end_time'])) ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= $ts['slot_type'] === 'practical' ? 'completed' : ($ts['slot_type'] === 'counseling' ? 'pending' : 'active') ?>">
                                                    <?= htmlspecialchars(ucfirst($ts['slot_type'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($ts['subject_name']) ?></strong>
                                                <span style="color: #64748B; font-size: 12px;">(<?= htmlspecialchars($ts['subject_abbrev']) ?>)</span>
                                            </td>
                                            <td><span style="background: #E2E8F0; padding: 2px 8px; border-radius: 4px; font-weight: 600;"><?= htmlspecialchars($ts['room_no']) ?></span></td>
                                            <td><?= htmlspecialchars($ts['faculty_name']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Full Weekly Schedule Table -->
                <h4 style="color: var(--primary-color); margin: 0 0 14px;">Full Weekly Schedule (Monday &ndash; Saturday)</h4>
                <?php foreach ($weeklySchedule as $dayName => $daySlots): ?>
                    <div class="timetable-day-block" style="margin-bottom: 20px;">
                        <div class="timetable-day-title" style="background: #1E3A8A; color: #fff; padding: 8px 16px; border-radius: 6px 6px 0 0; font-weight: 600;">
                            <?= htmlspecialchars($dayName) ?>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table" style="margin: 0;">
                                <thead>
                                    <tr>
                                        <th style="width: 22%;">Time Window</th>
                                        <th style="width: 14%;">Slot Type</th>
                                        <th style="width: 32%;">Subject / Course</th>
                                        <th style="width: 14%;">Room / Lab</th>
                                        <th style="width: 18%;">Assigned Faculty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($daySlots)): ?>
                                        <tr><td colspan="5" style="text-align:center; color:#94A3B8;">No classes scheduled</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($daySlots as $slot): ?>
                                            <tr>
                                                <td style="font-weight: 600; color: #2563EB;">
                                                    <?= date('h:i A', strtotime($slot['start_time'])) ?> &ndash; <?= date('h:i A', strtotime($slot['end_time'])) ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?= $slot['slot_type'] === 'practical' ? 'completed' : ($slot['slot_type'] === 'counseling' ? 'pending' : 'active') ?>">
                                                        <?= htmlspecialchars(ucfirst($slot['slot_type'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($slot['subject_name']) ?></strong>
                                                    <span style="color: #64748B; font-size: 12px; margin-left: 6px;">(<?= htmlspecialchars($slot['subject_abbrev']) ?>)</span>
                                                </td>
                                                <td>
                                                    <span style="background: #F1F5F9; padding: 4px 8px; border-radius: 4px; font-weight: 600;">
                                                        <?= htmlspecialchars($slot['room_no']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($slot['faculty_name']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
        </div>

        <!-- ========================================== -->
        <!-- TAB 4: FYAIML Classmate Directory (78)     -->
        <!-- ========================================== -->
        <div id="tab-directory" class="portal-tab-panel">
            <section>
                <div style="margin-bottom: 16px;">
                    <h3 style="color: var(--primary-color); margin: 0 0 6px;">FYAIML Class Directory &bull; Official Roster</h3>
                    <p style="margin: 0; color: #64748B; font-size: 14px;">
                        Official allocation roster for all 78 AIML students across 4 academic batches and assigned counselors.
                        <em>Student privacy notice: Contact phone numbers, personal emails, and passwords are protected and hidden from the public directory.</em>
                    </p>
                </div>

                <div class="directory-controls">
                    <div class="batch-filter-group" id="batch-filter-buttons">
                        <button type="button" class="batch-btn active" data-filter="All">All Batches (<?= $batchCounts['All'] ?>)</button>
                        <button type="button" class="batch-btn" data-filter="Batch A">Batch A (<?= $batchCounts['Batch A'] ?>)</button>
                        <button type="button" class="batch-btn" data-filter="Batch B">Batch B (<?= $batchCounts['Batch B'] ?>)</button>
                        <button type="button" class="batch-btn" data-filter="Batch C">Batch C (<?= $batchCounts['Batch C'] ?>)</button>
                        <button type="button" class="batch-btn" data-filter="Batch D">Batch D (<?= $batchCounts['Batch D'] ?>)</button>
                    </div>
                    <input type="text" id="directory-search" class="directory-search" placeholder="Search classmate name or ID...">
                </div>

                <div class="table-responsive">
                    <table class="data-table" id="directory-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>College ID</th>
                                <th>Student Name</th>
                                <th>Batch</th>
                                <th>Counselor</th>
                            </tr>
                        </thead>
                        <tbody id="directory-tbody">
                            <?php foreach ($aimlClassmates as $idx => $cm): ?>
                                <tr data-batch="<?= htmlspecialchars($cm['batch_name'] ?? 'Batch A') ?>">
                                    <td><?= $idx + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($cm['student_id'] ?? '25AIML000') ?></strong></td>
                                    <td>
                                        <?= htmlspecialchars($cm['name']) ?>
                                        <?php if ((int)$cm['id'] === $studentId): ?>
                                            <span class="badge badge-active" style="margin-left: 6px; font-size: 10px;">You</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span style="background: #EFF6FF; color: #2563EB; padding: 2px 8px; border-radius: 4px; font-weight: 600;"><?= htmlspecialchars($cm['batch_name'] ?? 'Batch A') ?></span></td>
                                    <td><?= htmlspecialchars($cm['counselor_name'] ?? 'Faculty Counselor') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- ========================================== -->
        <!-- TAB 5: Marksheet Upload & CGPA OCR Tracker -->
        <!-- ========================================== -->
        <div id="tab-marksheets" class="portal-tab-panel">
            <section>
                <div style="margin-bottom: 20px;">
                    <h3 style="color: var(--primary-color); margin: 0 0 6px;">Marksheet Upload &amp; Automated CGPA Extraction</h3>
                    <p style="margin: 0; color: #64748B; font-size: 14px;">
                        Upload your official semester marksheet (PDF or image). StudentHub automatically parses your SGPA / CGPA with intelligent regex pattern extraction.
                    </p>
                </div>

                <!-- Upload Dropzone Form -->
                <div class="marksheet-upload-card">
                    <div class="upload-icon">&#128196;</div>
                    <h4 style="margin: 0 0 8px; font-size: 18px; color: #1E3A8A;">Upload Semester Marksheet</h4>
                    <p style="margin: 0 0 16px; color: #64748B; font-size: 13px;">Supports PDF, PNG, JPG, or WEBP (Max 5 MB)</p>

                    <form id="marksheet-upload-form" enctype="multipart/form-data" style="max-width: 480px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                        <div style="display: flex; gap: 12px;">
                            <select name="semester" style="padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; flex: 1;">
                                <option value="1">Semester 1</option>
                                <option value="2" selected>Semester 2</option>
                                <option value="3">Semester 3</option>
                            </select>
                            <input type="text" name="academic_year" value="2025-2026" style="padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; flex: 1;">
                        </div>

                        <input type="file" name="marksheet_file" id="marksheet-file-input" accept=".pdf,image/png,image/jpeg,image/webp" required style="padding: 10px; background: #fff; border: 1px solid #CBD5E1; border-radius: 6px;">

                        <button type="submit" id="btn-upload-marksheet" class="btn btn-primary" style="padding: 10px 20px; font-weight: 600;">
                            Upload &amp; Scan CGPA
                        </button>
                    </form>
                    <div id="upload-feedback" style="display:none; margin-top: 14px; padding: 12px; border-radius: 6px; font-weight: 600; font-size: 14px; line-height: 1.5;"></div>
                </div>

                <!-- Marksheets Archive Table -->
                <h4 style="color: var(--primary-color); margin: 24px 0 12px;">Uploaded Marksheets History</h4>
                <div id="marksheets-history-container">
                <?php if (empty($marksheetsList)): ?>
                    <p style="color: #64748B;" id="no-marksheets-msg">No marksheets uploaded yet. Upload your Semester 2 marksheet above to verify your CGPA.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table" id="marksheets-table">
                            <thead>
                                <tr>
                                    <th>Semester</th>
                                    <th>Filename</th>
                                    <th>Uploaded On</th>
                                    <th>Extracted CGPA</th>
                                    <th>Confirmed CGPA</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($marksheetsList as $m): ?>
                                    <tr>
                                        <td>Semester <?= (int)$m['semester'] ?> (<?= htmlspecialchars($m['academic_year']) ?>)</td>
                                        <td><?= htmlspecialchars($m['original_filename']) ?></td>
                                        <td><?= htmlspecialchars(date('d M Y, h:i A', strtotime($m['uploaded_at']))) ?></td>
                                        <td>
                                            <?= $m['extracted_cgpa'] !== null ? number_format((float)$m['extracted_cgpa'], 2) : '<span style="color:#64748B;">N/A</span>' ?>
                                        </td>
                                        <td>
                                            <?php if ($m['confirmed_cgpa'] !== null): ?>
                                                <strong style="color: #059669; font-size: 15px;">
                                                    <?= number_format((float)$m['confirmed_cgpa'], 2) ?>
                                                </strong>
                                            <?php else: ?>
                                                <span style="color: #D97706; font-style: italic;">Pending Confirmation</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $m['status'] === 'confirmed' ? 'active' : ($m['status'] === 'rejected' ? 'danger' : 'info') ?>">
                                                <?= htmlspecialchars(ucfirst($m['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="../api/marksheets/view.php?id=<?= (int)$m['id'] ?>" target="_blank" class="btn btn-sm btn-outline" style="padding: 4px 10px; font-size: 12px; text-decoration: none;">View Document</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                </div>
            </section>
        </div>

        <!-- ========================================== -->
        <!-- TAB 6: Registered Campus Events            -->
        <!-- ========================================== -->
        <div id="tab-events" class="portal-tab-panel">
            <section class="student-events-section" style="margin-top: 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                    <h3 style="margin: 0; color: var(--primary-color);">Registered Campus Events</h3>
                    <a href="../pages/events.html" class="btn btn-sm btn-outline" style="text-decoration:none;">Browse All Events &rarr;</a>
                </div>

                <?php if (!empty($registeredEvents)): ?>
                    <div class="table-responsive">
                        <table class="data-table" aria-label="Registered Campus Events">
                            <thead>
                                <tr>
                                    <th>Event Title</th>
                                    <th>Category</th>
                                    <th>Date &amp; Time</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Registered On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registeredEvents as $ev): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($ev['title']) ?></strong></td>
                                        <td><span class="badge badge-completed"><?= htmlspecialchars(ucfirst($ev['category'])) ?></span></td>
                                        <td>
                                            <?= htmlspecialchars(date('d M Y', strtotime($ev['event_date']))) ?>
                                            <?php if (!empty($ev['event_time'])): ?>
                                                at <?= htmlspecialchars(date('h:i A', strtotime($ev['event_time']))) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($ev['location'] ?? 'Campus Auditorium') ?></td>
                                        <td><span class="badge badge-<?= htmlspecialchars($ev['status'] ?? 'upcoming') ?>"><?= htmlspecialchars(ucfirst($ev['status'] ?? 'Upcoming')) ?></span></td>
                                        <td><?= htmlspecialchars(date('d M Y', strtotime($ev['registered_at']))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert-box alert-info">
                        <p style="margin: 0 0 8px;"><strong>No active event registrations found.</strong></p>
                        <p style="margin: 0;">Explore upcoming campus competitions, workshops, and sports events below.</p>
                    </div>

                    <?php if (!empty($upcomingEvents)): ?>
                        <div class="table-responsive">
                            <table class="data-table" aria-label="Available Campus Events">
                                <thead>
                                    <tr>
                                        <th>Upcoming Event</th>
                                        <th>Category</th>
                                        <th>Date</th>
                                        <th>Venue</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($upcomingEvents, 0, 4) as $uev): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($uev['title']) ?></strong></td>
                                            <td><span class="badge badge-completed"><?= htmlspecialchars(ucfirst($uev['category'])) ?></span></td>
                                            <td><?= htmlspecialchars(date('d M Y', strtotime($uev['event_date']))) ?></td>
                                            <td><?= htmlspecialchars($uev['location'] ?? 'Campus') ?></td>
                                            <td>
                                                <a href="../pages/events.html" class="btn btn-sm btn-outline" style="padding: 4px 10px; font-size: 12px;">View &amp; Register</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>

        <!-- CGPA Confirmation Modal (Triggered after marksheet upload) -->
        <div id="cgpa-confirm-modal" class="marksheet-confirm-modal" style="display:none;">
            <div class="marksheet-modal-box">
                <h3 id="modal-title" style="color: #1E3A8A; margin: 0 0 10px;">Review &amp; Confirm Grade</h3>
                <p id="modal-desc" style="color: #64748B; font-size: 14px; margin: 0 0 20px;">
                    We scanned your uploaded document. Please verify or adjust your verified grade point before saving.
                </p>
                <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                    <div id="modal-field-label" style="font-size: 13px; color: #1E40AF; text-transform: uppercase; font-weight: 700; margin-bottom: 6px;">Verified SGPA / CGPA (Scale 0.00 - 10.00)</div>
                    <input type="number" step="0.01" min="0" max="10" id="modal-cgpa-input" value="" placeholder="0.00" style="font-size: 28px; font-weight: 800; text-align: center; color: #2563EB; width: 140px; border: 2px solid #2563EB; border-radius: 8px; padding: 6px;">
                </div>
                <div style="display: flex; gap: 12px; justify-content: center;">
                    <button type="button" id="btn-cancel-modal" class="btn btn-outline" style="padding: 8px 18px;">Cancel</button>
                    <button type="button" id="btn-confirm-cgpa" class="btn btn-primary" style="padding: 8px 24px;">Confirm &amp; Save CGPA</button>
                </div>
            </div>
        </div>

        <!-- Quick Campus Services -->
        <section style="margin-top: 35px;">
            <h3 style="color: var(--primary-color);">Campus Quick Links</h3>
            <ul class="quick-links-list">
                <li><a href="../public/pages/events.html">&rarr; Events Calendar</a></li>
                <li><a href="../public/pages/course-enrollment.php">&rarr; Course Enrollment</a></li>
                <li><a href="../public/pages/administrative.html">&rarr; Administrative Services</a></li>
                <li><a href="../public/pages/campuslife.html">&rarr; Campus Life &amp; Clubs</a></li>
                <li><a href="../public/pages/faq.html">&rarr; Frequently Asked Questions</a></li>
                <li><a href="../public/pages/contact.html">&rarr; Contact Administration</a></li>
                <li><a href="../public/pages/about.html">&rarr; About CSPIT, CHARUSAT</a></li>
                <li><a href="../public/process-logout.php" style="color: #DC2626;">&rarr; Sign Out</a></li>
            </ul>
        </section>
    </main>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        // Tab switching
        const tabBtns = document.querySelectorAll(".portal-tab-btn");
        const tabPanels = document.querySelectorAll(".portal-tab-panel");

        tabBtns.forEach(btn => {
            btn.addEventListener("click", () => {
                tabBtns.forEach(b => b.classList.remove("active"));
                tabPanels.forEach(p => p.classList.remove("active"));
                btn.classList.add("active");
                const target = document.getElementById(btn.getAttribute("data-tab"));
                if (target) target.classList.add("active");
            });
        });

        // Directory filtering by Batch
        const batchBtns = document.querySelectorAll(".batch-btn");
        const dirRows = document.querySelectorAll("#directory-tbody tr");
        const searchInput = document.getElementById("directory-search");

        function filterDirectory() {
            const activeBtn = document.querySelector(".batch-btn.active");
            const selectedBatch = activeBtn ? activeBtn.getAttribute("data-filter") : "All";
            const query = (searchInput ? searchInput.value : "").toLowerCase().trim();

            dirRows.forEach(row => {
                const batch = row.getAttribute("data-batch");
                const text = row.textContent.toLowerCase();
                const matchBatch = (selectedBatch === "All" || batch === selectedBatch);
                const matchSearch = (query === "" || text.includes(query));

                row.style.display = (matchBatch && matchSearch) ? "" : "none";
            });
        }

        batchBtns.forEach(btn => {
            btn.addEventListener("click", () => {
                batchBtns.forEach(b => b.classList.remove("active"));
                btn.classList.add("active");
                filterDirectory();
            });
        });

        if (searchInput) {
            searchInput.addEventListener("input", filterDirectory);
        }

        // Marksheet Upload Form & Modal
        const uploadForm = document.getElementById("marksheet-upload-form");
        const feedback = document.getElementById("upload-feedback");
        const modal = document.getElementById("cgpa-confirm-modal");
        const modalTitle = document.getElementById("modal-title");
        const modalDesc = document.getElementById("modal-desc");
        const modalInput = document.getElementById("modal-cgpa-input");
        const btnCancel = document.getElementById("btn-cancel-modal");
        const btnConfirm = document.getElementById("btn-confirm-cgpa");
        let currentMarksheetId = 0;

        if (uploadForm) {
            uploadForm.addEventListener("submit", function (e) {
                e.preventDefault();
                feedback.style.display = "block";
                feedback.style.background = "#EFF6FF";
                feedback.style.color = "#1E40AF";
                feedback.textContent = "Scanning document and extracting academic grades...";

                const formData = new FormData(uploadForm);
                fetch("../api/marksheets/upload.php", {
                    method: "POST",
                    body: formData
                })
                    .then(r => r.json().then(data => ({ ok: r.ok, status: r.status, data })))
                    .then(({ ok, status, data }) => {
                        if (data && data.success) {
                            currentMarksheetId = data.marksheet_id;
                            if (data.has_detected_cgpa && data.extracted_cgpa !== null) {
                                if (modalTitle) modalTitle.textContent = "Verify Extracted Grade";
                                if (modalDesc) modalDesc.textContent = "We scanned your uploaded document and detected a grade of " + parseFloat(data.extracted_cgpa).toFixed(2) + ". Please review and confirm.";
                                modalInput.value = parseFloat(data.extracted_cgpa).toFixed(2);
                            } else {
                                if (modalTitle) modalTitle.textContent = "Enter Verified Marksheet CGPA";
                                if (modalDesc) modalDesc.textContent = "Marksheet uploaded successfully. No numeric grade point could be automatically extracted from this document image. Please enter your official SGPA/CGPA:";
                                modalInput.value = "";
                                modalInput.placeholder = "e.g. 8.44";
                            }
                            modal.style.display = "flex";
                            feedback.style.background = "#DCFCE7";
                            feedback.style.color = "#166534";
                            feedback.textContent = data.message || "Document uploaded successfully. Please review and confirm your grade.";
                        } else {
                            feedback.style.background = "#FEE2E2";
                            feedback.style.color = "#991B1B";
                            feedback.textContent = (data && (data.error || data.message)) ? (data.error || data.message) : "Upload failed (Status: " + status + "). Please verify format and size.";
                        }
                    })
                    .catch((err) => {
                        feedback.style.background = "#FEE2E2";
                        feedback.style.color = "#991B1B";
                        feedback.textContent = "Network error while uploading marksheet. Please check server connectivity.";
                    });
            });
        }

        if (btnCancel) {
            btnCancel.addEventListener("click", () => {
                modal.style.display = "none";
            });
        }

        if (btnConfirm) {
            btnConfirm.addEventListener("click", () => {
                const confirmedVal = parseFloat(modalInput.value);
                if (isNaN(confirmedVal) || confirmedVal < 0 || confirmedVal > 10) {
                    alert("Please enter a valid SGPA/CGPA between 0.00 and 10.00");
                    return;
                }

                btnConfirm.disabled = true;
                btnConfirm.textContent = "Saving...";

                const fd = new FormData();
                fd.append("action", "confirm");
                fd.append("marksheet_id", currentMarksheetId);
                fd.append("confirmed_cgpa", confirmedVal);

                fetch("../api/marksheets/upload.php", {
                    method: "POST",
                    body: fd
                })
                    .then(r => r.json().then(data => ({ ok: r.ok, status: r.status, data })))
                    .then(({ ok, data }) => {
                        modal.style.display = "none";
                        btnConfirm.disabled = false;
                        btnConfirm.textContent = "Confirm & Save CGPA";
                        if (data && data.success) {
                            alert(data.message || "CGPA confirmed and saved successfully!");
                            window.location.reload();
                        } else {
                            alert((data && data.error) ? data.error : "Failed to confirm CGPA. Please try again.");
                        }
                    })
                    .catch(() => {
                        alert("Failed to confirm CGPA due to network error. Please try again.");
                        btnConfirm.disabled = false;
                        btnConfirm.textContent = "Confirm & Save CGPA";
                    });
            });
        }
    });
    </script>

<?php
require_once __DIR__ . '/../app/includes/footer.php';
?>
