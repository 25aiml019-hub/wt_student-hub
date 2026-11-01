<?php
/**
 * Events Resource API Endpoint
 * Resource: /api/events/
 * StudentHub - CSPIT, CHARUSAT
 * Course: AIUE203 - Web Development Fundamentals (Practical 12)
 * Author: Daksh (25AIML019)
 */

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/security.php';
require_once __DIR__ . '/../../app/includes/auth_check.php';
require_once __DIR__ . '/../../app/services/event_service.php';

initSession();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // 1. Single Event by ID
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $event = EventService::getById((int)$_GET['id']);
            if (!$event) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Event not found.']);
                exit;
            }
            echo json_encode(['success' => true, 'data' => $event], JSON_PRETTY_PRINT);
            exit;
        }

        // 2. Categories Metadata Request
        if (isset($_GET['categories']) && $_GET['categories'] === '1') {
            echo json_encode([
                'success'    => true,
                'categories' => EventService::getCategories()
            ], JSON_PRETTY_PRINT);
            exit;
        }

        // 3. Paginated / Filtered List (Default)
        try {
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 10;
            $filters = [
                'search'   => $_GET['search'] ?? '',
                'category' => $_GET['category'] ?? '',
                'status'   => $_GET['status'] ?? 'all'
            ];

            // Backward compatibility for unpaginated status queries (Practical 6/8)
            if (isset($_GET['unpaginated']) || (isset($_GET['status']) && !isset($_GET['page']) && !isset($_GET['search']) && !isset($_GET['category']))) {
                $statusParam = ($filters['status'] === 'all') ? '' : $filters['status'];
                $events = EventService::getAll($statusParam);
                echo json_encode([
                    'success' => true,
                    'count'   => count($events),
                    'data'    => $events
                ], JSON_PRETTY_PRINT);
                exit;
            }

            $result = EventService::getPaginated($filters, $page, $limit);

            echo json_encode([
                'success'    => true,
                'count'      => count($result['items']),
                'total'      => $result['total'],
                'page'       => $result['page'],
                'limit'      => $result['limit'],
                'totalPages' => $result['totalPages'],
                'data'       => $result['items'],
                'categories' => EventService::getCategories()
            ], JSON_PRETTY_PRINT);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Unable to retrieve events: ' . $e->getMessage()
            ]);
        }
        break;

    case 'POST':
        // Require admin role for modifying events
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
            // Action A: Delete Event
            if ($action === 'delete' || $action === 'DELETE') {
                $id = isset($payload['id']) ? (int)$payload['id'] : 0;
                if ($id <= 0) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'message' => 'Valid event ID is required for deletion.']);
                    exit;
                }
                EventService::delete($id, $adminId);
                echo json_encode([
                    'success' => true,
                    'message' => 'Event and associated assets removed successfully.'
                ]);
                exit;
            }

            // Handle Poster Upload if file is attached in multipart form
            if (isset($_FILES['poster']) && is_array($_FILES['poster']) && $_FILES['poster']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadedPoster = EventService::handlePosterUpload($_FILES['poster']);
                $payload['poster_image'] = $uploadedPoster;
            }

            // Action B: Update Event
            if ($action === 'update' || $action === 'PUT') {
                $id = isset($payload['id']) ? (int)$payload['id'] : 0;
                if ($id <= 0) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'message' => 'Valid event ID is required for update.']);
                    exit;
                }
                EventService::update($id, $payload, $adminId);
                echo json_encode([
                    'success' => true,
                    'message' => 'Event updated successfully.'
                ]);
                exit;
            }

            // Action C: Create Event (Default)
            if (empty($payload['title']) || empty($payload['event_date'])) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Event title and event date are required.']);
                exit;
            }

            $newId = EventService::create($payload, $adminId);
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Event created successfully.',
                'id'      => $newId
            ]);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to process event request: ' . $e->getMessage()]);
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
