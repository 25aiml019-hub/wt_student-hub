<?php
/**
 * Student Service (Data Access Layer - PDO & MySQLi)
 * StudentHub - CSPIT, CHARUSAT
 * Course: AIUE203 - Web Development Fundamentals (Practical 8 & 11)
 * Author: Daksh (25AIML019)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/audit_service.php';

class StudentService {
    /**
     * Retrieve all students with optional search filter using PDO
     */
    public static function getAll(string $search = '', string $department = ''): array {
        $db = getDB();
        $sql = "SELECT * FROM students WHERE 1=1";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (name LIKE ? OR email LIKE ? OR course LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($department)) {
            $sql .= " AND department = ?";
            $params[] = $department;
        }

        $sql .= " ORDER BY id DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Retrieve all students with optional search filter using MySQLi prepared statements
     */
    public static function getAllMySQLi(string $search = '', string $department = ''): array {
        $mysqli = getMySQLi();
        $sql = "SELECT id, user_id, name, email, mobile, course, department, academic_year, gender, hobbies, address, status, created_at, updated_at FROM students WHERE 1=1";
        $types = "";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (name LIKE ? OR email LIKE ? OR course LIKE ?)";
            $term = "%{$search}%";
            $types .= "sss";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($department)) {
            $sql .= " AND department = ?";
            $types .= "s";
            $params[] = $department;
        }

        $sql .= " ORDER BY id DESC";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException("Prepare failed: " . $mysqli->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $students = [];
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();

        return $students;
    }

    /**
     * Get student by ID (PDO / MySQLi fallback)
     */
    public static function getById(int $id): ?array {
        $mysqli = getMySQLi();
        $stmt = $mysqli->prepare(
            "SELECT id, user_id, name, email, mobile, course, department, academic_year, gender, hobbies, address, status, created_at, updated_at " .
            "FROM students WHERE id = ? LIMIT 1"
        );
        if (!$stmt) {
            throw new RuntimeException("Prepare failed: " . $mysqli->error);
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $student = $res->fetch_assoc();
        $stmt->close();
        return $student ?: null;
    }

    /**
     * Alias for getById
     */
    public static function findById(int $id): ?array {
        return self::getById($id);
    }

    /**
     * Get student by email using MySQLi prepared statement
     */
    public static function findByEmail(string $email): ?array {
        $mysqli = getMySQLi();
        $stmt = $mysqli->prepare("SELECT id, user_id, name, email, mobile, course, department, academic_year, gender, hobbies, address, status, created_at, updated_at FROM students WHERE email = ? LIMIT 1");
        if (!$stmt) {
            throw new RuntimeException("Prepare failed: " . $mysqli->error);
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();

        return $student ?: null;
    }

    /**
     * Get student by linked user ID using MySQLi prepared statement
     */
    public static function findByUserId(int $userId): ?array {
        $mysqli = getMySQLi();
        $stmt = $mysqli->prepare(
            "SELECT id, user_id, name, email, mobile, course, department, academic_year, gender, hobbies, address, status, created_at, updated_at " .
            "FROM students WHERE user_id = ? LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $student = $res->fetch_assoc();
        $stmt->close();
        return $student ?: null;
    }

    /**
     * Get campus events registered by a student
     */
    public static function getRegisteredEvents(int $studentId): array {
        $mysqli = getMySQLi();
        $stmt = $mysqli->prepare(
            "SELECT r.id AS registration_id, r.registered_at, " .
            "e.id AS event_id, e.title, e.description, e.category, e.event_date, e.event_time, e.location, e.status, e.poster_image " .
            "FROM registrations r " .
            "JOIN events e ON r.event_id = e.id " .
            "WHERE r.student_id = ? " .
            "ORDER BY e.event_date ASC"
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $res = $stmt->get_result();
        $events = [];
        while ($row = $res->fetch_assoc()) {
            $events[] = $row;
        }
        $stmt->close();
        return $events;
    }

    /**
     * Check if a student is registered for a specific event
     */
    public static function isRegisteredForEvent(int $studentId, int $eventId): bool {
        $mysqli = getMySQLi();
        $stmt = $mysqli->prepare("SELECT id FROM registrations WHERE student_id = ? AND event_id = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("ii", $studentId, $eventId);
        $stmt->execute();
        $stmt->store_result();
        $isReg = ($stmt->num_rows > 0);
        $stmt->close();
        return $isReg;
    }

    /**
     * Register a student for an event enforcing business rules, event status, and deadlines
     */
    public static function registerForEvent(int $studentId, int $eventId): array {
        $mysqli = getMySQLi();

        // 1. Verify student exists and is active
        $student = self::findById($studentId);
        if (!$student) {
            return [
                'success' => false,
                'status_code' => 403,
                'message' => 'No active student record found.'
            ];
        }

        if (isset($student['status']) && strtolower($student['status']) !== 'active') {
            return [
                'success' => false,
                'status_code' => 403,
                'message' => 'Registration rejected: Your student profile status is inactive. Please contact administration.'
            ];
        }

        // 2. Fetch event details from database
        $stmt = $mysqli->prepare("SELECT id, title, category, event_date, registration_deadline, event_time, location, max_participants, status FROM events WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return [
                'success' => false,
                'status_code' => 500,
                'message' => 'Database error while retrieving event details.'
            ];
        }
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $res = $stmt->get_result();
        $event = $res->fetch_assoc();
        $stmt->close();

        if (!$event) {
            return [
                'success' => false,
                'status_code' => 404,
                'message' => 'Event not found.'
            ];
        }

        $eventTitle = $event['title'];
        $eventStatus = strtolower($event['status'] ?? 'upcoming');

        // 3. Rule: Completed events must not accept registrations
        if ($eventStatus === 'completed') {
            return [
                'success' => false,
                'status_code' => 400,
                'event_status' => 'completed',
                'message' => "Registration closed. '{$eventTitle}' has already concluded."
            ];
        }

        // 4. Rule: Cancelled or closed events: registration disabled
        if ($eventStatus === 'cancelled' || $eventStatus === 'closed') {
            return [
                'success' => false,
                'status_code' => 400,
                'event_status' => $eventStatus,
                'message' => "Registration closed. '{$eventTitle}' is {$eventStatus}."
            ];
        }

        // 5. Rule: Past registration deadlines / event date in past: registration disabled
        // Timezone: Asia/Kolkata (CSPIT, CHARUSAT campus)
        date_default_timezone_set('Asia/Kolkata');
        $currentDate = date('Y-m-d');
        $eventDate = $event['event_date'];
        $deadline = !empty($event['registration_deadline']) ? $event['registration_deadline'] : $eventDate;

        if ($eventDate < $currentDate) {
            return [
                'success' => false,
                'status_code' => 400,
                'event_status' => 'completed',
                'message' => "Registration closed. '{$eventTitle}' took place on " . date('d M Y', strtotime($eventDate)) . " and is no longer accepting registrations."
            ];
        }

        if ($deadline < $currentDate) {
            return [
                'success' => false,
                'status_code' => 400,
                'event_status' => 'closed',
                'message' => "Registration deadline for '{$eventTitle}' passed on " . date('d M Y', strtotime($deadline)) . "."
            ];
        }

        // 6. Rule: Duplicate registration prevention
        if (self::isRegisteredForEvent($studentId, $eventId)) {
            return [
                'success' => false,
                'status_code' => 409,
                'already_registered' => true,
                'message' => "You are already registered for '{$eventTitle}'."
            ];
        }

        // 7. Rule: Capacity check (if max_participants set)
        $maxParticipants = (int)($event['max_participants'] ?? 0);
        if ($maxParticipants > 0) {
            $cStmt = $mysqli->prepare("SELECT COUNT(*) AS reg_count FROM registrations WHERE event_id = ?");
            if ($cStmt) {
                $cStmt->bind_param("i", $eventId);
                $cStmt->execute();
                $cRes = $cStmt->get_result();
                $cRow = $cRes->fetch_assoc();
                $cStmt->close();
                $currentRegs = (int)($cRow['reg_count'] ?? 0);
                if ($currentRegs >= $maxParticipants) {
                    return [
                        'success' => false,
                        'status_code' => 400,
                        'message' => "Registration closed. '{$eventTitle}' has reached its maximum capacity of {$maxParticipants} participants."
                    ];
                }
            }
        }

        // 8. Atomic Registration Persistence using transaction
        $mysqli->begin_transaction();
        try {
            $stmt = $mysqli->prepare("INSERT INTO registrations (event_id, student_id, registered_at) VALUES (?, ?, NOW())");
            if (!$stmt) {
                throw new RuntimeException("Prepare registration query failed: " . $mysqli->error);
            }
            $stmt->bind_param("ii", $eventId, $studentId);
            if (!$stmt->execute()) {
                throw new RuntimeException("Execution of registration query failed: " . $stmt->error);
            }
            $newId = (int)$stmt->insert_id;
            $stmt->close();

            if (class_exists('AuditService')) {
                $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
                AuditService::log($uid, 'EVENT_REGISTERED', json_encode([
                    'registration_id' => $newId,
                    'student_id' => $studentId,
                    'event_id' => $eventId,
                    'event_title' => $eventTitle
                ]));
            }

            $mysqli->commit();

            return [
                'success' => true,
                'status_code' => 201,
                'registration_id' => $newId,
                'event_id' => $eventId,
                'event_title' => $eventTitle,
                'registered_at' => date('Y-m-d H:i:s'),
                'message' => "Successfully registered for '{$eventTitle}'! The event is now visible in your Student Portal."
            ];
        } catch (Throwable $e) {
            $mysqli->rollback();
            error_log("registerForEvent transaction failed: " . $e->getMessage());
            return [
                'success' => false,
                'status_code' => 500,
                'message' => 'Registration could not be completed due to a database error. Please try again.'
            ];
        }
    }

    /**
     * Check if another student with this email exists (excluding given student ID)
     */
    public static function emailExistsExcluding(string $email, int $excludeId): bool {
        $mysqli = getMySQLi();
        $stmt = $mysqli->prepare("SELECT id FROM students WHERE email = ? AND id != ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("si", $email, $excludeId);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    /**
     * Create student using MySQLi prepared statement (Practical 8 & 11)
     */
    public static function create(array $data, ?int $adminUserId = null): int {
        $mysqli = getMySQLi();

        // 1. Server-side field validation
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $mobile = trim($data['mobile'] ?? '');
        $course = trim($data['course'] ?? '');
        $department = trim($data['department'] ?? '');
        $academicYear = trim($data['academic_year'] ?? '2025-2026');
        $gender = trim($data['gender'] ?? 'Other');
        $status = in_array($data['status'] ?? 'active', ['active', 'inactive']) ? $data['status'] : 'active';
        $address = trim($data['address'] ?? '');
        $hobbies = is_array($data['hobbies'] ?? '') ? implode(', ', $data['hobbies']) : trim($data['hobbies'] ?? '');

        if (empty($name) || strlen($name) < 2) {
            throw new InvalidArgumentException("Student full name is required (minimum 2 characters).");
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("A valid email address is required.");
        }
        if (!preg_match('/^[0-9]{10}$/', $mobile)) {
            throw new InvalidArgumentException("Mobile number must be exactly 10 digits.");
        }
        if (empty($course)) {
            throw new InvalidArgumentException("Course is required.");
        }
        if (empty($department)) {
            throw new InvalidArgumentException("Department is required.");
        }
        if (!in_array($gender, ['Male', 'Female', 'Other'])) {
            $gender = 'Other';
        }

        // 2. Uniqueness check
        if (self::findByEmail($email) !== null) {
            throw new InvalidArgumentException("A student record with email '{$email}' already exists.");
        }

        // 3. MySQLi prepared insert statement
        $stmt = $mysqli->prepare(
            "INSERT INTO students (name, email, mobile, course, department, academic_year, gender, hobbies, address, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            throw new RuntimeException("Prepare failed: " . $mysqli->error);
        }

        $stmt->bind_param(
            "ssssssssss",
            $name,
            $email,
            $mobile,
            $course,
            $department,
            $academicYear,
            $gender,
            $hobbies,
            $address,
            $status
        );

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException("Student insertion failed: " . $err);
        }

        $insertId = (int)$stmt->insert_id;
        $stmt->close();

        // 4. Audit logging
        $auditUser = $adminUserId ?? ($_SESSION['user']['id'] ?? null);
        AuditService::log($auditUser, 'STUDENT_CREATE', "Created student record ID #{$insertId}: {$name} ({$email})");

        return $insertId;
    }

    /**
     * Update existing student using MySQLi prepared statement (Practical 11 Update)
     */
    public static function update(int $id, array $data, ?int $adminUserId = null): bool {
        $mysqli = getMySQLi();

        // Check existence
        $existing = self::getById($id);
        if (!$existing) {
            throw new InvalidArgumentException("Student with ID #{$id} does not exist.");
        }

        // Field validation
        $name = trim($data['name'] ?? $existing['name']);
        $email = trim($data['email'] ?? $existing['email']);
        $mobile = trim($data['mobile'] ?? $existing['mobile']);
        $course = trim($data['course'] ?? $existing['course']);
        $department = trim($data['department'] ?? $existing['department']);
        $academicYear = trim($data['academic_year'] ?? $existing['academic_year']);
        $gender = trim($data['gender'] ?? $existing['gender']);
        $status = in_array($data['status'] ?? $existing['status'], ['active', 'inactive']) ? ($data['status'] ?? $existing['status']) : 'active';
        $address = trim($data['address'] ?? ($existing['address'] ?? ''));
        $hobbies = isset($data['hobbies']) ? (is_array($data['hobbies']) ? implode(', ', $data['hobbies']) : trim($data['hobbies'])) : ($existing['hobbies'] ?? '');

        if (empty($name) || strlen($name) < 2) {
            throw new InvalidArgumentException("Student full name is required (minimum 2 characters).");
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("A valid email address is required.");
        }
        if (!preg_match('/^[0-9]{10}$/', $mobile)) {
            throw new InvalidArgumentException("Mobile number must be exactly 10 digits.");
        }
        if (empty($course) || empty($department)) {
            throw new InvalidArgumentException("Course and department are required.");
        }
        if (!in_array($gender, ['Male', 'Female', 'Other'])) {
            $gender = $existing['gender'];
        }

        // Duplicate check on email change
        if (self::emailExistsExcluding($email, $id)) {
            throw new InvalidArgumentException("Email '{$email}' is already in use by another student.");
        }

        $stmt = $mysqli->prepare(
            "UPDATE students SET name = ?, email = ?, mobile = ?, course = ?, department = ?, academic_year = ?, gender = ?, hobbies = ?, address = ?, status = ? WHERE id = ?"
        );
        if (!$stmt) {
            throw new RuntimeException("Prepare failed: " . $mysqli->error);
        }

        $stmt->bind_param(
            "ssssssssssi",
            $name,
            $email,
            $mobile,
            $course,
            $department,
            $academicYear,
            $gender,
            $hobbies,
            $address,
            $status,
            $id
        );

        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            $auditUser = $adminUserId ?? ($_SESSION['user']['id'] ?? null);
            AuditService::log($auditUser, 'STUDENT_UPDATE', "Updated student record ID #{$id}: {$name} ({$email})");
        }

        return $success;
    }

    /**
     * Delete or deactive student (Practical 11 Delete)
     */
    public static function delete(int $id, bool $softDelete = true, ?int $adminUserId = null): bool {
        $mysqli = getMySQLi();
        $existing = self::getById($id);
        if (!$existing) {
            throw new InvalidArgumentException("Student with ID #{$id} not found.");
        }

        $auditUser = $adminUserId ?? ($_SESSION['user']['id'] ?? null);

        if ($softDelete) {
            $newStatus = ($existing['status'] === 'active') ? 'inactive' : 'active';
            $stmt = $mysqli->prepare("UPDATE students SET status = ? WHERE id = ?");
            if (!$stmt) return false;
            $stmt->bind_param("si", $newStatus, $id);
            $success = $stmt->execute();
            $stmt->close();
            if ($success) {
                AuditService::log($auditUser, 'STUDENT_STATUS_TOGGLE', "Changed status of student ID #{$id} ({$existing['name']}) to '{$newStatus}'");
            }
            return $success;
        } else {
            $stmt = $mysqli->prepare("DELETE FROM students WHERE id = ?");
            if (!$stmt) return false;
            $stmt->bind_param("i", $id);
            $success = $stmt->execute();
            $stmt->close();
            if ($success) {
                AuditService::log($auditUser, 'STUDENT_DELETE', "Permanently deleted student ID #{$id}: {$existing['name']} ({$existing['email']})");
            }
            return $success;
        }
    }

    /**
     * Server-side paginated, searchable, and filtered student retrieval (Practical 11)
     */
    public static function getPaginated(array $filters = [], int $page = 1, int $limit = 10): array {
        $mysqli = getMySQLi();

        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        $where = ["1=1"];
        $types = "";
        $params = [];

        // Search: name or email
        if (!empty($filters['search'])) {
            $term = "%" . trim($filters['search']) . "%";
            $where[] = "(name LIKE ? OR email LIKE ? OR course LIKE ?)";
            $types .= "sss";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        // Filter: department
        if (!empty($filters['department']) && $filters['department'] !== 'all') {
            $where[] = "department = ?";
            $types .= "s";
            $params[] = trim($filters['department']);
        }

        // Filter: course
        if (!empty($filters['course']) && $filters['course'] !== 'all') {
            $where[] = "course = ?";
            $types .= "s";
            $params[] = trim($filters['course']);
        }

        // Filter: academic_year
        if (!empty($filters['academic_year']) && $filters['academic_year'] !== 'all') {
            $where[] = "academic_year = ?";
            $types .= "s";
            $params[] = trim($filters['academic_year']);
        }

        // Filter: status
        if (!empty($filters['status']) && in_array($filters['status'], ['active', 'inactive'])) {
            $where[] = "status = ?";
            $types .= "s";
            $params[] = trim($filters['status']);
        }

        $whereClause = implode(" AND ", $where);

        // 1. Total Count Query
        $countSql = "SELECT COUNT(*) AS total FROM students WHERE {$whereClause}";
        $stmtCount = $mysqli->prepare($countSql);
        if (!$stmtCount) {
            throw new RuntimeException("Count query failed: " . $mysqli->error);
        }
        if (!empty($params)) {
            $stmtCount->bind_param($types, ...$params);
        }
        $stmtCount->execute();
        $countRes = $stmtCount->get_result();
        $totalRow = $countRes->fetch_assoc();
        $total = (int)($totalRow['total'] ?? 0);
        $stmtCount->close();

        $totalPages = max(1, (int)ceil($total / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $limit;

        // 2. Paginated Data Query
        $dataSql = "SELECT id, user_id, name, email, mobile, course, department, academic_year, gender, hobbies, address, status, created_at " .
                   "FROM students WHERE {$whereClause} ORDER BY id DESC LIMIT ? OFFSET ?";
        $stmtData = $mysqli->prepare($dataSql);
        if (!$stmtData) {
            throw new RuntimeException("Data query failed: " . $mysqli->error);
        }

        $dataTypes = $types . "ii";
        $dataParams = array_merge($params, [$limit, $offset]);
        $stmtData->bind_param($dataTypes, ...$dataParams);

        $stmtData->execute();
        $res = $stmtData->get_result();
        $students = [];
        while ($row = $res->fetch_assoc()) {
            $students[] = $row;
        }
        $stmtData->close();

        return [
            'items'       => $students,
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'totalPages'  => $totalPages,
            'offset'      => $offset
        ];
    }

    /**
     * Get distinct departments for filter dropdown
     */
    public static function getDepartments(): array {
        $mysqli = getMySQLi();
        $res = $mysqli->query("SELECT DISTINCT department FROM students WHERE department != '' ORDER BY department ASC");
        $departments = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $departments[] = $row['department'];
            }
        }
        return $departments;
    }

    /**
     * Get distinct academic years for filter dropdown
     */
    public static function getAcademicYears(): array {
        $mysqli = getMySQLi();
        $res = $mysqli->query("SELECT DISTINCT academic_year FROM students WHERE academic_year != '' ORDER BY academic_year DESC");
        $years = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $years[] = $row['academic_year'];
            }
        }
        return $years;
    }

    /**
     * Export students to CSV with formula injection defense (Practical 11)
     */
    public static function exportCsv(array $filters = []): string {
        $mysqli = getMySQLi();

        $where = ["1=1"];
        $types = "";
        $params = [];

        if (!empty($filters['search'])) {
            $term = "%" . trim($filters['search']) . "%";
            $where[] = "(name LIKE ? OR email LIKE ? OR course LIKE ?)";
            $types .= "sss";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        if (!empty($filters['department']) && $filters['department'] !== 'all') {
            $where[] = "department = ?";
            $types .= "s";
            $params[] = trim($filters['department']);
        }
        if (!empty($filters['academic_year']) && $filters['academic_year'] !== 'all') {
            $where[] = "academic_year = ?";
            $types .= "s";
            $params[] = trim($filters['academic_year']);
        }
        if (!empty($filters['status']) && in_array($filters['status'], ['active', 'inactive'])) {
            $where[] = "status = ?";
            $types .= "s";
            $params[] = trim($filters['status']);
        }

        $sql = "SELECT id, name, email, mobile, course, department, academic_year, gender, hobbies, address, status, created_at " .
               "FROM students WHERE " . implode(" AND ", $where) . " ORDER BY id ASC";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException("Export prepare failed: " . $mysqli->error);
        }
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();

        $output = fopen('php://temp', 'r+');

        // CSV Header
        fputcsv($output, ['ID', 'Name', 'Email', 'Mobile', 'Course', 'Department', 'Academic Year', 'Gender', 'Hobbies', 'Address', 'Status', 'Registered At']);

        // Formula injection defense helper
        $sanitizeCell = function($val) {
            if ($val === null) return '';
            $str = (string)$val;
            // If cell starts with formula characters (=, +, -, @, tab, CR), prepend single quote
            if (strlen($str) > 0 && in_array($str[0], ['=', '+', '-', '@', "\t", "\r", '%'])) {
                return "'" . $str;
            }
            return $str;
        };

        while ($row = $res->fetch_assoc()) {
            $sanitizedRow = array_map($sanitizeCell, [
                $row['id'],
                $row['name'],
                $row['email'],
                $row['mobile'],
                $row['course'],
                $row['department'],
                $row['academic_year'],
                $row['gender'],
                $row['hobbies'],
                $row['address'],
                $row['status'],
                $row['created_at']
            ]);
            fputcsv($output, $sanitizedRow);
        }
        $stmt->close();

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }

    /**
     * Get total count of registered students
     */
    public static function count(): int {
        $mysqli = getMySQLi();
        $res = $mysqli->query("SELECT COUNT(*) AS total FROM students");
        if ($res && $row = $res->fetch_assoc()) {
            return (int)$row['total'];
        }
        return 0;
    }
}
