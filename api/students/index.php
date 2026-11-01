<?php
/**
 * Students Resource API Endpoint
 * Resource: /api/students/
 * StudentHub - CSPIT, CHARUSAT
 * Course: AIUE203 - Web Development Fundamentals (Practical 11)
 * Author: Daksh (25AIML019)
 */

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/security.php';
require_once __DIR__ . '/../../app/includes/auth_check.php';
require_once __DIR__ . '/../../app/services/student_service.php';

initSession();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // 1. CSV Export Request
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            enforceApiAuth('admin');
            try {
                $csv = StudentService::exportCsv($_GET);
                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment; filename="students_export_' . date('Ymd_His') . '.csv"');
                header('Pragma: no-cache');
                header('Expires: 0');
                echo $csv;
                exit;
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'CSV Export failed: ' . $e->getMessage()]);
                exit;
            }
        }

        // 2. Single Student by ID
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $student = StudentService::getById((int)$_GET['id']);
            if (!$student) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Student not found.']);
                exit;
            }
            echo json_encode(['success' => true, 'data' => $student], JSON_PRETTY_PRINT);
            exit;
        }

        // 3. Filter metadata request
        if (isset($_GET['metadata']) && $_GET['metadata'] === '1') {
            echo json_encode([
                'success' => true,
                'departments' => StudentService::getDepartments(),
                'academic_years' => StudentService::getAcademicYears()
            ], JSON_PRETTY_PRINT);
            exit;
        }

        // 4. Paginated List (Default)
        try {
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 10;
            $filters = [
                'search'        => $_GET['search'] ?? '',
                'department'    => $_GET['department'] ?? '',
                'course'        => $_GET['course'] ?? '',
                'academic_year' => $_GET['academic_year'] ?? '',
                'status'        => $_GET['status'] ?? 'all'
            ];

            // If caller requested unpaginated all records
            if (isset($_GET['all']) && $_GET['all'] === '1') {
                $allStudents = StudentService::getAll($filters['search'], $filters['department']);
                echo json_encode([
                    'success' => true,
                    'count'   => count($allStudents),
                    'data'    => $allStudents
                ], JSON_PRETTY_PRINT);
                exit;
            }

            $result = StudentService::getPaginated($filters, $page, $limit);

            echo json_encode([
                'success'        => true,
                'count'          => count($result['items']),
                'total'          => $result['total'],
                'page'           => $result['page'],
                'limit'          => $result['limit'],
                'totalPages'     => $result['totalPages'],
                'data'           => $result['items'],
                'departments'    => StudentService::getDepartments(),
                'academic_years' => StudentService::getAcademicYears()
            ], JSON_PRETTY_PRINT);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Unable to retrieve students: ' . $e->getMessage()
            ]);
        }
        break;

    case 'POST':
        // Admin authorization required for state-changing operations
        enforceApiAuth('admin');

        $raw = file_get_contents('php://input');
        $jsonPayload = json_decode($raw, true);
        $payload = is_array($jsonPayload) ? $jsonPayload : $_POST;

        // CSRF Token Check
        $csrfToken = $payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!validateCsrfToken($csrfToken)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid or missing CSRF token. Request rejected.'
            ]);
            exit;
        }

        $user = currentUser();
        $adminId = $user ? $user['id'] : null;
        $action = $payload['action'] ?? ($payload['_method'] ?? 'create');

        try {
            // Action A: Delete / Status Toggle
            if ($action === 'delete' || $action === 'DELETE') {
                $id = isset($payload['id']) ? (int)$payload['id'] : 0;
                if ($id <= 0) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'message' => 'A valid student ID is required for deletion.']);
                    exit;
                }
                $softDelete = !isset($payload['permanent']) || !$payload['permanent'];
                StudentService::delete($id, $softDelete, $adminId);
                echo json_encode([
                    'success' => true,
                    'message' => $softDelete ? 'Student account status updated successfully.' : 'Student permanently removed.'
                ]);
                exit;
            }

            // Action B: Update
            if ($action === 'update' || $action === 'PUT') {
                $id = isset($payload['id']) ? (int)$payload['id'] : 0;
                if ($id <= 0) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'message' => 'A valid student ID is required for update.']);
                    exit;
                }
                StudentService::update($id, $payload, $adminId);
                echo json_encode([
                    'success' => true,
                    'message' => 'Student record updated successfully.'
                ]);
                exit;
            }

            // Action C: Create (Default)
            if (empty($payload['name']) || empty($payload['email'])) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Name and email are required.']);
                exit;
            }

            $newId = StudentService::create($payload, $adminId);
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Student record created successfully.',
                'id'      => $newId
            ]);
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $code = (strpos($msg, 'already exists') !== false || strpos($msg, 'already in use') !== false) ? 409 : 422;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $msg]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to process student request: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method Not Allowed'
        ]);
        break;
}
