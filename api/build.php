<?php
/**
 * Arcadia short-link API.
 *
 *   POST /api/build.php    body: {"p":"c.<payload>"}   -> {"id":"x7k2p"}
 *   GET  /api/build.php?id=x7k2p                       -> {"p":"c.<payload>"}
 *
 * Stores only the encoded build string. No accounts, no personal data; IP
 * addresses are salted-hashed for rate limiting and never stored raw.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
// Same-origin only; the page and API are served from the same host.
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_HOST'] ?? ''));

function fail(int $code, string $msg): never {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    fail(500, 'Server not configured');
}
$cfg = require $configPath;

try {
    $pdo = new PDO(
        "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset=utf8mb4",
        $cfg['db_user'],
        $cfg['db_pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (Throwable $e) {
    fail(500, 'Database unavailable');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ----------------------------- read ----------------------------- */
if ($method === 'GET') {
    $id = (string) ($_GET['id'] ?? '');
    if (!preg_match('/^[A-Za-z0-9]{4,12}$/', $id)) {
        fail(400, 'Bad id');
    }

    $stmt = $pdo->prepare('SELECT payload FROM builds WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        fail(404, 'Not found');
    }

    // Best-effort popularity counter; a failure here must not break the read.
    try {
        $pdo->prepare('UPDATE builds SET hits = hits + 1 WHERE id = ?')->execute([$id]);
    } catch (Throwable $e) {
    }

    header('Cache-Control: public, max-age=300');
    echo json_encode(['p' => $row['payload']]);
    exit;
}

/* ----------------------------- create ----------------------------- */
if ($method !== 'POST') {
    fail(405, 'Method not allowed');
}

$raw = file_get_contents('php://input') ?: '';
if (strlen($raw) > $cfg['max_payload_bytes'] + 200) {
    fail(413, 'Too large');
}

$body    = json_decode($raw, true);
$payload = is_array($body) ? (string) ($body['p'] ?? '') : '';

// Must look like something encodeBuild() produced: optional "c." marker
// followed by base64url. Anything else is not ours.
if (!preg_match('/^(c\.)?[A-Za-z0-9_-]+$/', $payload)) {
    fail(400, 'Bad payload');
}
if (strlen($payload) < 8 || strlen($payload) > $cfg['max_payload_bytes']) {
    fail(400, 'Bad payload size');
}

$ip     = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ipHash = hash('sha256', $cfg['ip_salt'] . $ip);

// Rate limit, and opportunistically drop expired rows.
try {
    $pdo->exec('DELETE FROM rate_limit WHERE window_start < (NOW() - INTERVAL 1 HOUR)');
    $stmt = $pdo->prepare('SELECT COUNT(*) AS n FROM rate_limit WHERE ip_hash = ?');
    $stmt->execute([$ipHash]);
    if ((int) ($stmt->fetch()['n'] ?? 0) >= (int) $cfg['rate_limit_per_hour']) {
        fail(429, 'Slow down a moment');
    }
} catch (PDOException $e) {
    // Rate-limit table problems shouldn't take the feature down.
}

// Identical builds reuse their existing id, so re-sharing doesn't fill the table.
$hash = hash('sha256', $payload);
$stmt = $pdo->prepare('SELECT id FROM builds WHERE payload_hash = ? LIMIT 1');
$stmt->execute([$hash]);
if ($existing = $stmt->fetch()) {
    echo json_encode(['id' => $existing['id'], 'reused' => true]);
    exit;
}

/** Unambiguous alphabet: no 0/O/1/l/I. */
function makeId(int $len): string {
    $alphabet = '23456789abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    $out      = '';
    $max      = strlen($alphabet) - 1;
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

$insert = $pdo->prepare(
    'INSERT INTO builds (id, payload, payload_hash, created_at) VALUES (?, ?, ?, NOW())'
);

// Widen the id if we somehow keep colliding.
for ($attempt = 0; $attempt < 12; $attempt++) {
    $id = makeId(5 + intdiv($attempt, 4));
    try {
        $insert->execute([$id, $payload, $hash]);
        try {
            $pdo->prepare('INSERT INTO rate_limit (ip_hash, window_start) VALUES (?, NOW())')
                ->execute([$ipHash]);
        } catch (Throwable $e) {
        }
        echo json_encode(['id' => $id]);
        exit;
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') {   // not a duplicate-key clash
            fail(500, 'Could not save');
        }
    }
}

fail(500, 'Could not allocate an id');
