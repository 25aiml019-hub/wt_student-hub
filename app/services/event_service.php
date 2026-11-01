<?php
/**
 * Event Service (Data Access Layer - PDO & MySQLi)
 * StudentHub - CSPIT, CHARUSAT
 * Course: AIUE203 - Web Development Fundamentals (Practical 8 & 12)
 * Author: Daksh (25AIML019)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/audit_service.php';

class EventService {
    /**
     * Retrieve all events using PDO (Preserving backward compatibility)
     */
    public static function getAll(string $status = 'upcoming'): array {
        $db = getDB();
        $sql = "SELECT * FROM events";
        $params = [];
        if (!empty($status) && $status !== 'all') {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY event_date ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Retrieve all events using MySQLi prepared statement (Preserving backward compatibility)
     */
    public static function getAllMySQLi(string $status = 'upcoming'): array {
        $mysqli = getMySQLi();
        $sql = "SELECT id, title, description, category, event_date, event_time, location, poster_image, status, created_at, updated_at FROM events";
        if (!empty($status) && $status !== 'all') {
            $sql .= " WHERE status = ?";
        }
        $sql .= " ORDER BY event_date ASC";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException("Prepare failed: " . $mysqli->error);
        }

        if (!empty($status) && $status !== 'all') {
            $stmt->bind_param("s", $status);
        }

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
     * Get event by ID (MySQLi prepared statement)
     */
    public static function getById(int $id): ?array {
        $mysqli = getMySQLi();
        $stmt = $mysqli->prepare("SELECT id, title, description, category, event_date, event_time, location, poster_image, status, created_at, updated_at FROM events WHERE id = ? LIMIT 1");
        if (!$stmt) {
            throw new RuntimeException("Prepare failed: " . $mysqli->error);
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $event = $res->fetch_assoc();
        $stmt->close();
        return $event ?: null;
    }

    /**
     * Server-side paginated, searchable, and filtered event retrieval (Practical 12)
     */
    public static function getPaginated(array $filters = [], int $page = 1, int $limit = 10): array {
        $mysqli = getMySQLi();

        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        $where = ["1=1"];
        $types = "";
        $params = [];

        // Search: title, description, location
        if (!empty($filters['search'])) {
            $term = "%" . trim($filters['search']) . "%";
            $where[] = "(title LIKE ? OR description LIKE ? OR location LIKE ?)";
            $types .= "sss";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        // Category filter
        if (!empty($filters['category']) && $filters['category'] !== 'all') {
            $where[] = "category = ?";
            $types .= "s";
            $params[] = trim($filters['category']);
        }

        // Status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all' && in_array($filters['status'], ['upcoming', 'completed', 'cancelled'])) {
            $where[] = "status = ?";
            $types .= "s";
            $params[] = trim($filters['status']);
        }

        $whereClause = implode(" AND ", $where);

        // 1. Total Count Query
        $countSql = "SELECT COUNT(*) AS total FROM events WHERE {$whereClause}";
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

        // 2. Data Query
        $dataSql = "SELECT id, title, description, category, event_date, event_time, location, poster_image, status, created_at, updated_at " .
                   "FROM events WHERE {$whereClause} ORDER BY event_date ASC, id DESC LIMIT ? OFFSET ?";
        $stmtData = $mysqli->prepare($dataSql);
        if (!$stmtData) {
            throw new RuntimeException("Data query failed: " . $mysqli->error);
        }

        $dataTypes = $types . "ii";
        $dataParams = array_merge($params, [$limit, $offset]);
        $stmtData->bind_param($dataTypes, ...$dataParams);

        $stmtData->execute();
        $res = $stmtData->get_result();
        $events = [];
        while ($row = $res->fetch_assoc()) {
            $events[] = $row;
        }
        $stmtData->close();

        return [
            'items'       => $events,
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'totalPages'  => $totalPages,
            'offset'      => $offset
        ];
    }

    /**
     * Create event using MySQLi prepared statement (Practical 12 Create)
     */
    public static function create(array $data, ?int $adminUserId = null): int {
        $mysqli = getMySQLi();

        // 1. Validation
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $category = trim($data['category'] ?? 'General');
        $eventDate = trim($data['event_date'] ?? '');
        $eventTime = trim($data['event_time'] ?? '10:00 AM');
        $location = trim($data['location'] ?? ($data['venue'] ?? 'CHARUSAT Campus'));
        $posterImage = !empty($data['poster_image']) ? basename(trim($data['poster_image'])) : null;
        $status = in_array($data['status'] ?? 'upcoming', ['upcoming', 'completed', 'cancelled']) ? $data['status'] : 'upcoming';

        if (empty($title) || strlen($title) < 3) {
            throw new InvalidArgumentException("Event title is required (minimum 3 characters).");
        }
        if (empty($eventDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
            throw new InvalidArgumentException("A valid event date is required in YYYY-MM-DD format.");
        }
        if (empty($location)) {
            throw new InvalidArgumentException("Event location / venue is required.");
        }

        // 2. MySQLi prepared insert
        $stmt = $mysqli->prepare(
            "INSERT INTO events (title, description, category, event_date, event_time, location, poster_image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            throw new RuntimeException("Prepare failed: " . $mysqli->error);
        }

        $stmt->bind_param(
            "ssssssss",
            $title,
            $description,
            $category,
            $eventDate,
            $eventTime,
            $location,
            $posterImage,
            $status
        );

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException("Event insertion failed: " . $err);
        }

        $insertId = (int)$stmt->insert_id;
        $stmt->close();

        // 3. Audit logging
        $auditUser = $adminUserId ?? ($_SESSION['user']['id'] ?? null);
        AuditService::log($auditUser, 'EVENT_CREATE', "Created event ID #{$insertId}: '{$title}' on {$eventDate}");

        return $insertId;
    }

    /**
     * Update existing event using MySQLi prepared statement (Practical 12 Update)
     */
    public static function update(int $id, array $data, ?int $adminUserId = null): bool {
        $mysqli = getMySQLi();

        $existing = self::getById($id);
        if (!$existing) {
            throw new InvalidArgumentException("Event with ID #{$id} does not exist.");
        }

        $title = trim($data['title'] ?? $existing['title']);
        $description = isset($data['description']) ? trim($data['description']) : $existing['description'];
        $category = trim($data['category'] ?? $existing['category']);
        $eventDate = trim($data['event_date'] ?? $existing['event_date']);
        $eventTime = trim($data['event_time'] ?? ($existing['event_time'] ?? '10:00 AM'));
        $location = trim($data['location'] ?? ($data['venue'] ?? $existing['location']));
        $status = in_array($data['status'] ?? $existing['status'], ['upcoming', 'completed', 'cancelled']) ? ($data['status'] ?? $existing['status']) : 'upcoming';

        // Check poster update
        $posterImage = $existing['poster_image'];
        $oldPosterToRemove = null;

        if (isset($data['remove_poster']) && $data['remove_poster']) {
            $oldPosterToRemove = $existing['poster_image'];
            $posterImage = null;
        } elseif (!empty($data['poster_image'])) {
            $newPoster = basename(trim($data['poster_image']));
            if ($newPoster !== $existing['poster_image']) {
                $oldPosterToRemove = $existing['poster_image'];
                $posterImage = $newPoster;
            }
        }

        if (empty($title) || strlen($title) < 3) {
            throw new InvalidArgumentException("Event title is required (minimum 3 characters).");
        }
        if (empty($eventDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
            throw new InvalidArgumentException("A valid event date is required in YYYY-MM-DD format.");
        }

        $stmt = $mysqli->prepare(
            "UPDATE events SET title = ?, description = ?, category = ?, event_date = ?, event_time = ?, location = ?, poster_image = ?, status = ? WHERE id = ?"
        );
        if (!$stmt) {
            throw new RuntimeException("Prepare failed: " . $mysqli->error);
        }

        $stmt->bind_param(
            "ssssssssi",
            $title,
            $description,
            $category,
            $eventDate,
            $eventTime,
            $location,
            $posterImage,
            $status,
            $id
        );

        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            // Delete old poster only after database update succeeds
            if (!empty($oldPosterToRemove)) {
                self::removePosterFile($oldPosterToRemove);
            }
            $auditUser = $adminUserId ?? ($_SESSION['user']['id'] ?? null);
            AuditService::log($auditUser, 'EVENT_UPDATE', "Updated event ID #{$id}: '{$title}'");
        }

        return $success;
    }

    /**
     * Delete an event (Practical 12 Delete)
     */
    public static function delete(int $id, ?int $adminUserId = null): bool {
        $mysqli = getMySQLi();
        $existing = self::getById($id);
        if (!$existing) {
            throw new InvalidArgumentException("Event with ID #{$id} not found.");
        }

        $stmt = $mysqli->prepare("DELETE FROM events WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            // Safely clean up poster file after database deletion
            if (!empty($existing['poster_image'])) {
                self::removePosterFile($existing['poster_image']);
            }
            $auditUser = $adminUserId ?? ($_SESSION['user']['id'] ?? null);
            AuditService::log($auditUser, 'EVENT_DELETE', "Deleted event ID #{$id}: '{$existing['title']}'");
        }

        return $success;
    }

    /**
     * Secure Poster Upload Handler (Practical 12 Security Requirements)
     */
    public static function handlePosterUpload(array $file, ?string $oldPosterFilename = null): string {
        // 1. Basic error check
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new InvalidArgumentException("Invalid upload parameters.");
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    throw new InvalidArgumentException("Uploaded file exceeds the maximum allowed size (2 MB).");
                case UPLOAD_ERR_PARTIAL:
                    throw new InvalidArgumentException("The uploaded file was only partially uploaded.");
                case UPLOAD_ERR_NO_FILE:
                    throw new InvalidArgumentException("No file was uploaded.");
                default:
                    throw new InvalidArgumentException("File upload failed with error code: " . $file['error']);
            }
        }

        // 2. File size check (2 MB limit)
        $maxBytes = 2 * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            throw new InvalidArgumentException("Uploaded file exceeds the maximum allowed size of 2 MB.");
        }

        // 3. File extension validation
        $originalFilename = $file['name'];
        $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowedExtensions, true)) {
            throw new InvalidArgumentException("Unsupported file extension '.{$ext}'. Only JPG, JPEG, PNG, and WebP are allowed.");
        }

        // 4. MIME Type Validation (finfo)
        $tmpPath = $file['tmp_name'];
        if (!is_file($tmpPath) || !is_readable($tmpPath)) {
            throw new InvalidArgumentException("Temporary upload file is missing or unreadable.");
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        $allowedMimes = [
            'image/jpeg',
            'image/pjpeg',
            'image/png',
            'image/webp'
        ];

        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new InvalidArgumentException("Invalid file MIME type '{$mimeType}'. Only authentic image files are permitted.");
        }

        // 5. Image dimension integrity check
        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
            throw new InvalidArgumentException("Uploaded file is corrupted or not a valid image.");
        }

        // 6. Security scan: prevent polyglot / script execution inside image
        $fileHeader = @file_get_contents($tmpPath, false, null, 0, 4096);
        if ($fileHeader !== false) {
            $dangerousPatterns = ['<?php', '<?=', '<script', '<%'];
            foreach ($dangerousPatterns as $pattern) {
                if (stripos($fileHeader, $pattern) !== false) {
                    throw new InvalidArgumentException("File rejected: Embedded script content detected.");
                }
            }
        }

        // 7. Generate unique random filename
        $uniqueFilename = 'poster_' . bin2hex(random_bytes(16)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);

        // 8. Secure destination directory
        $targetDir = realpath(__DIR__ . '/../../storage/uploads/posters');
        if (!$targetDir) {
            $targetDir = __DIR__ . '/../../storage/uploads/posters';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
        }

        $destination = $targetDir . DIRECTORY_SEPARATOR . $uniqueFilename;

        // 9. Move uploaded file
        if (!move_uploaded_file($tmpPath, $destination)) {
            // Fallback for CLI / test environments where move_uploaded_file may fail
            if (!copy($tmpPath, $destination)) {
                throw new RuntimeException("Failed to move uploaded file to target storage directory.");
            }
        }

        // 10. Restrict permissions (non-executable)
        @chmod($destination, 0644);

        return $uniqueFilename;
    }

    /**
     * Safely remove poster file from storage
     */
    public static function removePosterFile(string $filename): bool {
        $safeName = basename($filename);
        if (empty($safeName)) {
            return false;
        }

        // Prevent deletion of sample seed images
        $seedImages = ['hackathon.jpg', 'sports.jpg', 'techfest.jpg', 'web_dev.jpg', 'cultural.jpg'];
        if (in_array($safeName, $seedImages, true)) {
            return false;
        }

        $targetDir = realpath(__DIR__ . '/../../storage/uploads/posters');
        if (!$targetDir) {
            return false;
        }

        $filePath = $targetDir . DIRECTORY_SEPARATOR . $safeName;
        // Verify file is within poster upload directory (prevent directory traversal)
        if (is_file($filePath) && strpos(realpath($filePath), $targetDir) === 0) {
            return @unlink($filePath);
        }

        return false;
    }

    /**
     * Get distinct categories
     */
    public static function getCategories(): array {
        $mysqli = getMySQLi();
        $res = $mysqli->query("SELECT DISTINCT category FROM events WHERE category != '' ORDER BY category ASC");
        $categories = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $categories[] = $row['category'];
            }
        }
        return $categories;
    }

    /**
     * Count total events
     */
    public static function count(): int {
        $mysqli = getMySQLi();
        $res = $mysqli->query("SELECT COUNT(*) AS total FROM events");
        if ($res && $row = $res->fetch_assoc()) {
            return (int)$row['total'];
        }
        return 0;
    }
}
