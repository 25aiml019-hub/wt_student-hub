<?php
/**
 * StudentHub - Marksheet Upload & CGPA Extraction Service
 * Course: AIUE203 - Web Development Fundamentals
 * Author: Daksh (25AIML019), CSPIT, CHARUSAT
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/audit_service.php';

class MarksheetService {

    /**
     * Get all marksheets for a student
     */
    public static function getStudentMarksheets(int $studentId): array {
        $mysqli = getMySQLi();
        $stmt = $mysqli->prepare(
            "SELECT id, student_id, semester, academic_year, original_filename, stored_filename, file_size, " .
            "mime_type, extracted_cgpa, confirmed_cgpa, status, uploaded_at " .
            "FROM marksheets WHERE student_id = ? ORDER BY uploaded_at DESC"
        );
        if (!$stmt) return [];
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $res = $stmt->get_result();
        $list = [];
        while ($row = $res->fetch_assoc()) {
            $list[] = $row;
        }
        $stmt->close();
        return $list;
    }

    /**
     * Get marksheet by ID
     */
    public static function getById(int $id): ?array {
        $mysqli = getMySQLi();
        $stmt = $mysqli->prepare(
            "SELECT id, student_id, semester, academic_year, original_filename, stored_filename, file_size, " .
            "mime_type, extracted_cgpa, confirmed_cgpa, raw_extracted_text, status, uploaded_at " .
            "FROM marksheets WHERE id = ? LIMIT 1"
        );
        if (!$stmt) return null;
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Parse CGPA/SGPA strictly from extracted text without fabrication
     */
    public static function extractCgpaFromText(string $text): ?float {
        if (empty(trim($text))) {
            return null;
        }

        // Pattern 1: Direct labels with colon/equal: CGPA: 8.44, SGPA = 9.10, SPI: 8.75, CPI: 8.40
        $labelPattern = '/(?:CGPA|SGPA|SPI|CPI|GPA|Grade\s*Point\s*Average|Cumulative\s*Grade\s*Point\s*Average|Semester\s*Grade\s*Point\s*Average|C\.G\.P\.A\.|S\.G\.P\.A\.|S\.P\.I\.|C\.P\.I\.)\s*[:=\-]?\s*([0-9]{1,2}(?:\.[0-9]{1,2})?)/i';
        if (preg_match($labelPattern, $text, $matches)) {
            $val = (float)$matches[1];
            if ($val >= 0.0 && $val <= 10.0) {
                return round($val, 2);
            }
        }

        // Pattern 2: Label followed closely by floating point value (within 30 characters)
        $proximityPattern = '/(?:CGPA|SGPA|SPI|CPI)\b[\s\S]{1,30}?\b([0-9]\.[0-9]{1,2})\b/i';
        if (preg_match($proximityPattern, $text, $matches)) {
            $val = (float)$matches[1];
            if ($val >= 0.0 && $val <= 10.0) {
                return round($val, 2);
            }
        }

        // Pattern 3: Tabular grade summary line containing "Total Credits" or "Result" followed by a grade point
        if (preg_match('/(?:Result|Pass|Promoted|Qualified)\b[\s\S]{0,40}?\b([0-9]\.[0-9]{1,2})\b/i', $text, $matches)) {
            $val = (float)$matches[1];
            if ($val >= 4.0 && $val <= 10.0) {
                return round($val, 2);
            }
        }

        // Strictly return null if no valid academic score pattern matched (never fabricate!)
        return null;
    }

    /**
     * Extract readable text from PDF or text file
     */
    public static function extractTextFromFile(string $filePath, string $mime): string {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return '';
        }

        if ($mime === 'text/plain') {
            return (string)file_get_contents($filePath);
        }

        if ($mime === 'application/pdf') {
            $text = '';

            // Approach A: Try Python pypdf helper if available
            try {
                $pyScript = "import sys, pypdf; reader = pypdf.PdfReader(sys.argv[1]); print(' '.join(p.extract_text() or '' for p in reader.pages))";
                $cmd = 'python -c ' . escapeshellarg($pyScript) . ' ' . escapeshellarg($filePath);
                $pyOutput = @shell_exec($cmd);
                if (!empty($pyOutput) && trim($pyOutput) !== '') {
                    $text = trim($pyOutput);
                }
            } catch (Throwable $e) {
                // Fall back to pure PHP extraction
            }

            // Approach B: Pure PHP stream parsing & FlateDecode decompression
            if (empty($text)) {
                $content = (string)file_get_contents($filePath);

                // Check for deflated streams
                if (preg_match_all('#stream[\r\n]+(.*?)[\r\n]+endstream#s', $content, $streamMatches)) {
                    foreach ($streamMatches[1] as $rawStream) {
                        $decompressed = @gzuncompress($rawStream);
                        if ($decompressed === false) {
                            $decompressed = @gzinflate($rawStream);
                        }
                        if ($decompressed !== false) {
                            // Extract text within BT ... ET blocks
                            if (preg_match_all('/BT[\s\S]*?ET/s', $decompressed, $btMatches)) {
                                foreach ($btMatches[0] as $block) {
                                    if (preg_match_all('/\((.*?)\)\s*T[jJ]/s', $block, $tMatches)) {
                                        $text .= ' ' . implode(' ', $tMatches[1]);
                                    }
                                }
                            } else {
                                $text .= ' ' . $decompressed;
                            }
                        }
                    }
                }

                // If text still empty, check raw uncompressed blocks
                if (empty(trim($text))) {
                    if (preg_match_all('/BT[\s\S]*?ET/s', $content, $btMatches)) {
                        foreach ($btMatches[0] as $block) {
                            if (preg_match_all('/\((.*?)\)\s*T[jJ]/s', $block, $tMatches)) {
                                $text .= ' ' . implode(' ', $tMatches[1]);
                            }
                        }
                    }
                }

                // Check for plain ASCII keywords if structured text failed
                if (empty(trim($text)) && preg_match_all('/(?:CGPA|SGPA|SPI|CPI|Grade|Score)[^\r\n]{0,60}/i', $content, $kwMatches)) {
                    $text = implode(' ', $kwMatches[0]);
                }
            }

            return $text;
        }

        // For image files (PNG, JPG, WEBP), return empty text if no OCR engine installed
        return '';
    }

    /**
     * Create marksheet record in database using a secure transaction
     */
    public static function createMarksheet(
        int $studentId,
        int $semester,
        string $academicYear,
        string $origName,
        string $storedName,
        int $fileSize,
        string $mime,
        ?float $extractedCgpa,
        string $rawText
    ): int {
        $mysqli = getMySQLi();
        $mysqli->begin_transaction();

        try {
            $stmt = $mysqli->prepare(
                "INSERT INTO marksheets (student_id, semester, academic_year, original_filename, " .
                "stored_filename, file_size, mime_type, extracted_cgpa, raw_extracted_text, status, uploaded_at) " .
                "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'extracted', NOW())"
            );
            if (!$stmt) {
                throw new RuntimeException("Prepare failed: " . $mysqli->error);
            }

            $stmt->bind_param("iisssisds", 
                $studentId, 
                $semester, 
                $academicYear, 
                $origName, 
                $storedName, 
                $fileSize, 
                $mime, 
                $extractedCgpa, 
                $rawText
            );

            if (!$stmt->execute()) {
                throw new RuntimeException("Execute failed: " . $stmt->error);
            }

            $newId = (int)$stmt->insert_id;
            $stmt->close();

            if (class_exists('AuditService')) {
                $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
                AuditService::log($uid, 'MARKSHEET_UPLOADED', json_encode([
                    'student_id' => $studentId,
                    'semester' => $semester,
                    'original_filename' => $origName,
                    'extracted_cgpa' => $extractedCgpa
                ]));
            }

            $mysqli->commit();
            return $newId;
        } catch (Throwable $e) {
            $mysqli->rollback();
            error_log("createMarksheet transaction failed: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Confirm CGPA for a marksheet after student review
     * Requirement 11: Save the academic result only after confirmation!
     */
    public static function confirmCgpa(int $marksheetId, int $studentId, float $confirmedCgpa): bool {
        if ($confirmedCgpa < 0.0 || $confirmedCgpa > 10.0) {
            return false;
        }

        $mysqli = getMySQLi();
        $mysqli->begin_transaction();

        try {
            // 1. Verify marksheet belongs to this student and update status to confirmed
            $stmt = $mysqli->prepare(
                "UPDATE marksheets SET confirmed_cgpa = ?, status = 'confirmed' WHERE id = ? AND student_id = ?"
            );
            if (!$stmt) {
                throw new RuntimeException("Prepare marksheets update failed: " . $mysqli->error);
            }
            $stmt->bind_param("dii", $confirmedCgpa, $marksheetId, $studentId);
            if (!$stmt->execute()) {
                throw new RuntimeException("Execute marksheets update failed: " . $stmt->error);
            }
            $affected = $stmt->affected_rows;
            $stmt->close();

            // 2. Update students table CGPA
            $stuStmt = $mysqli->prepare("UPDATE students SET cgpa = ? WHERE id = ?");
            if ($stuStmt) {
                $stuStmt->bind_param("di", $confirmedCgpa, $studentId);
                $stuStmt->execute();
                $stuStmt->close();
            }

            // 3. Update semester_results table if record exists for this semester
            $ms = self::getById($marksheetId);
            if ($ms && !empty($ms['semester'])) {
                $sem = (int)$ms['semester'];
                $srStmt = $mysqli->prepare(
                    "UPDATE semester_results SET sgpa = ?, is_missing = 0, remarks = 'Verified via Marksheet Upload' " .
                    "WHERE student_id = ? AND semester = ?"
                );
                if ($srStmt) {
                    $srStmt->bind_param("dii", $confirmedCgpa, $studentId, $sem);
                    $srStmt->execute();
                    $srStmt->close();
                }
            }

            // 4. Audit Log
            if (class_exists('AuditService')) {
                $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
                AuditService::log($uid, 'MARKSHEET_CONFIRMED', json_encode([
                    'marksheet_id' => $marksheetId,
                    'student_id' => $studentId,
                    'confirmed_cgpa' => $confirmedCgpa
                ]));
            }

            $mysqli->commit();
            return true;
        } catch (Throwable $e) {
            $mysqli->rollback();
            error_log("confirmCgpa failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete marksheet securely ensuring ownership check and disk file cleanup
     */
    public static function deleteMarksheet(int $marksheetId, int $studentId): bool {
        $ms = self::getById($marksheetId);
        if (!$ms || (int)$ms['student_id'] !== $studentId) {
            return false;
        }

        $storedFile = $ms['stored_filename'];
        $filePath = __DIR__ . '/../../storage/uploads/marksheets/' . $storedFile;

        $mysqli = getMySQLi();
        $stmt = $mysqli->prepare("DELETE FROM marksheets WHERE id = ? AND student_id = ?");
        if (!$stmt) return false;
        $stmt->bind_param("ii", $marksheetId, $studentId);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok && file_exists($filePath)) {
            @unlink($filePath);
        }

        return $ok;
    }
}
