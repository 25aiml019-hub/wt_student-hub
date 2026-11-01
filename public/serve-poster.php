<?php
/**
 * Secure Poster File Server
 * StudentHub - CSPIT, CHARUSAT
 * Course: AIUE203 - Web Development Fundamentals (Practical 12)
 * Author: Daksh (25AIML019)
 */

$filename = isset($_GET['file']) ? basename(trim($_GET['file'])) : '';

if (empty($filename)) {
    http_response_code(400);
    exit("Bad Request: File parameter missing.");
}

$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mimeMap = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'svg'  => 'image/svg+xml'
];

if (!isset($mimeMap[$ext])) {
    http_response_code(403);
    exit("Forbidden: Unsupported file type.");
}

// Search locations
$storageDir = realpath(__DIR__ . '/../storage/uploads/posters');
$publicImgDir = realpath(__DIR__ . '/assets/images');

$targetPath = null;
if ($storageDir && is_file($storageDir . DIRECTORY_SEPARATOR . $filename)) {
    $resolved = realpath($storageDir . DIRECTORY_SEPARATOR . $filename);
    if ($resolved && strpos($resolved, $storageDir) === 0) {
        $targetPath = $resolved;
    }
} elseif ($publicImgDir && is_file($publicImgDir . DIRECTORY_SEPARATOR . $filename)) {
    $resolved = realpath($publicImgDir . DIRECTORY_SEPARATOR . $filename);
    if ($resolved && strpos($resolved, $publicImgDir) === 0) {
        $targetPath = $resolved;
    }
}

if (!$targetPath || !is_readable($targetPath)) {
    // Output clean fallback placeholder SVG
    header("Content-Type: image/svg+xml");
    header("X-Content-Type-Options: nosniff");
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="250" viewBox="0 0 400 250" fill="#f1f5f9">' .
         '<rect width="400" height="250" fill="#e2e8f0"/>' .
         '<text x="50%" y="45%" text-anchor="middle" font-family="sans-serif" font-size="16" font-weight="bold" fill="#64748b">Event Poster</text>' .
         '<text x="50%" y="60%" text-anchor="middle" font-family="sans-serif" font-size="12" fill="#94a3b8">CHARUSAT StudentHub</text>' .
         '</svg>';
    exit;
}

header("Content-Type: " . $mimeMap[$ext]);
header("Content-Length: " . filesize($targetPath));
header("X-Content-Type-Options: nosniff");
header("Cache-Control: public, max-age=86400");
readfile($targetPath);
exit;
