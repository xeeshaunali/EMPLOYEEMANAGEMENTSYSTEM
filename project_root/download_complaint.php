<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Access denied. Please login first.';
    exit;
}

$file = $_GET['file'] ?? '';
if (empty($file)) {
    header('HTTP/1.0 400 Bad Request');
    echo 'No file specified';
    exit;
}

// Security: Prevent directory traversal attacks
$file = basename($file);
$filePath = __DIR__ . '/uploads/complaints/' . $file;

// Check if file exists
if (!file_exists($filePath)) {
    header('HTTP/1.0 404 Not Found');
    echo 'File not found: ' . htmlspecialchars($file);
    exit;
}

// Get file mime type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
finfo_close($finfo);

// Set headers for download/inline view
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $file . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: public, must-revalidate');
header('Pragma: public');
header('Expires: 0');

// Clear output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Output file
readfile($filePath);
exit;