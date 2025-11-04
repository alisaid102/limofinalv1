<?php
/**
 * Limo Anywhere API proxy endpoint.
 * - Includes a secure API key stored outside the public_html directory.
 * - Forwards requests to the Limo Anywhere API using the hidden key.
 * - Does not expose the API key in any response.
 * - Returns JSON responses and appropriate HTTP status codes.
 */

// Never display PHP errors publicly to avoid leaking sensitive information
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');

// Always respond with JSON
header('Content-Type: application/json; charset=utf-8');

// Extra security headers for this JSON endpoint
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");

// Define a strict allowlist for permitted Limo Anywhere endpoints used by the frontend
// Exact matches
$allowedExact = [
    '/v1/fleet',
    '/v1/fleet/available',
    '/v1/fleet/pricing',
    '/v1/bookings',
    '/v1/test-connection',
];
// Patterns with path parameters
$allowedRegex = [
    '#^/v1/bookings/[A-Za-z0-9_-]+$#',
    '#^/v1/bookings/[A-Za-z0-9_-]+/cancel$#',
];

// Resolve path to the secure config file outside public_html
$secureConfigPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'secure_config' . DIRECTORY_SEPARATOR . 'limo_anywhere_key.php';

if (!file_exists($secureConfigPath)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server configuration error: missing API key file.'
    ]);
    exit;
}

// Include the API key. The included file must only define $apiKey and output nothing.
require $secureConfigPath; // sets $apiKey

if (!isset($apiKey) || !is_string($apiKey) || $apiKey === '') {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server configuration error: API key not set.'
    ]);
    exit;
}

// Configure the upstream Limo Anywhere API base URL.
// Adjust if your integration specifies a different hostname or version path.
$baseUrl = 'https://api.limoanywhere.com';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Parse JSON body if present
$rawBody = file_get_contents('php://input');
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$bodyData = null;
if ($rawBody !== '' && stripos($contentType, 'application/json') !== false) {
    $bodyData = json_decode($rawBody, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON in request body.']);
        exit;
    }
}

// Extract target API path
$path = $_GET['path'] ?? ($bodyData['path'] ?? null);
if (!is_string($path) || trim($path) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameter: path']);
    exit;
}
$path = trim($path);

// Security: disallow absolute URLs or protocol injection
if (preg_match('#://#', $path) || strpos($path, '//') === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid path value.']);
    exit;
}

// Allowlist enforcement
$allowed = in_array($path, $allowedExact, true);
if (!$allowed) {
    foreach ($allowedRegex as $re) {
        if (preg_match($re, $path)) { $allowed = true; break; }
    }
}
if (!$allowed) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

// Construct upstream URL
$url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');

// Prepare cURL
$ch = curl_init($url);
if ($ch === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to initialize request.']);
    exit;
}

// Build headers. Replace header name below if your integration requires a different scheme (e.g., Authorization: Bearer ...)
$headers = [
    'Accept: application/json',
    'X-Api-Key: ' . $apiKey,
];

// Forward query params for GET requests (excluding our internal control param `path`)
if ($method === 'GET') {
    $query = $_GET;
    unset($query['path']);
    if (!empty($query)) {
        $qs = http_build_query($query);
        $url .= (strpos($url, '?') === false ? '?' : '&') . $qs;
        curl_setopt($ch, CURLOPT_URL, $url);
    }
}

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_FAILONERROR, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

switch ($method) {
    case 'GET':
        // default
        break;
    case 'POST':
    case 'PUT':
    case 'PATCH':
    case 'DELETE':
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($rawBody !== '' && stripos($contentType, 'application/json') !== false) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        } else {
            // Fallback to form-encoded if not JSON
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $formData = $_POST ?? [];
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($formData));
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        curl_close($ch);
        exit;
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$responseBody = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$respContentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/json; charset=utf-8';
curl_close($ch);

if ($responseBody === false) {
    http_response_code(502);
    echo json_encode([
        'error' => 'Upstream request failed',
        'details' => $curlErr ?: 'Unknown error'
    ]);
    exit;
}

// Mirror upstream status code and content type
if ($httpCode > 0) {
    http_response_code($httpCode);
}
header('Content-Type: ' . $respContentType);

echo $responseBody;
exit;
