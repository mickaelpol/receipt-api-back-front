<?php

/**
 * Main application entry point - contains only runtime logic
 * All function declarations are in app.php
 */

declare(strict_types=1);

// Include bootstrap (autoload + app declarations)
require __DIR__ . '/bootstrap.php';

/* ---------- DEBUG & TRACEABILITY (APP_ENV=local only) ---------- */
$APP_ENV = getenv('APP_ENV') ?: 'prod';
$DEBUG = (getenv('DEBUG') && getenv('DEBUG') !== '0');

// Fonction pour capturer var_dump en mode debug
function debugDump($data, $label = 'DEBUG')
{
    global $APP_ENV, $DEBUG;
    if ($APP_ENV === 'local' && $DEBUG) {
        $GLOBALS['_DEBUG_OUTPUT'][] = [
            'label' => $label,
            'data' => $data,
            'file' => debug_backtrace()[1]['file'] ?? 'unknown',
            'line' => debug_backtrace()[1]['line'] ?? 'unknown'
        ];
    }
}

// Fonction pour ajouter des headers de debug
function addDebugHeaders($handler, $route)
{
    global $APP_ENV;
    if ($APP_ENV === 'local') {
        header("X-Handler: $handler");
        header("X-Route: $route");
        header("X-Timestamp: " . date('c'));
    }
}

// Fonction pour afficher les debug dumps
function outputDebugDumps()
{
    global $APP_ENV, $DEBUG, $_DEBUG_OUTPUT;

    if ($APP_ENV !== 'local' || !$DEBUG || empty($_DEBUG_OUTPUT)) {
        return;
    }

    $contentType = $_SERVER['HTTP_ACCEPT'] ?? '';

    // Si c'est une requête JSON, on ajoute les dumps en commentaire
    if (strpos($contentType, 'application/json') !== false) {
        echo "\n/* DEBUG DUMPS:\n";
        foreach ($_DEBUG_OUTPUT as $dump) {
            echo "=== {$dump['label']} ({$dump['file']}:{$dump['line']}) ===\n";
            ob_start();
            var_dump($dump['data']);
            echo ob_get_clean();
            echo "\n";
        }
        echo "*/\n";
    } else {
        // Pour les requêtes HTML, on affiche directement
        echo "\n<div style='background:#f0f0f0;border:1px solid #ccc;padding:10px;margin:10px;font-family:monospace;'>";
        echo "<h3>DEBUG OUTPUT (APP_ENV=local)</h3>";
        foreach ($_DEBUG_OUTPUT as $dump) {
            echo "<h4>{$dump['label']} ({$dump['file']}:{$dump['line']})</h4>";
            echo "<pre>";
            var_dump($dump['data']);
            echo "</pre>";
        }
        echo "</div>";
    }
}

/* ---------- CORS ---------- */
// Same-origin policy pour la production, CORS permissif pour le dev local
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$isLocalDev = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ||
               strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false);

if ($isLocalDev) {
    // En dev local, autoriser localhost
    $allowed = getenv('ALLOWED_ORIGINS') ?: 'http://localhost:8080';
    $list = array_map('trim', explode(',', $allowed));
    $allow = $origin && in_array($origin, $list, true) ? $origin : $list[0] ?? '*';
} else {
    // En production, same-origin seulement
    $allow = detectProtocol() . '://' . ($_SERVER['HTTP_HOST'] ?? '');
}

header('Access-Control-Allow-Origin: ' . $allow);
header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

/* ---------- ENV ---------- */
$CLIENT_ID      = getenv('GOOGLE_OAUTH_CLIENT_ID') ?: '';
$ALLOWED_EMAILS = array_values(array_filter(array_map(
    'strtolower',
    array_map('trim', explode(',', getenv('ALLOWED_EMAILS') ?: ''))
)));

$SPREADSHEET_ID = getenv('SPREADSHEET_ID') ?: '';
$DEFAULT_SHEET  = getenv('DEFAULT_SHEET') ?: '';

$PROJECT_ID     = getenv('GCP_PROJECT_ID') ?: '';
$LOCATION       = getenv('GCP_LOCATION') ?: 'eu';
$PROCESSOR_ID   = getenv('GCP_PROCESSOR_ID') ?: '';

$MAX_BATCH_UPLOADS = (int) (getenv('MAX_BATCH_UPLOADS') ?: 10);

$WHO_COLUMNS = parse_who_columns();

if (!$WHO_COLUMNS) {
    // Fallback si WHO_COLUMNS non défini
    $WHO_COLUMNS = [
        'Sabrina' => ['label' => 'K','date' => 'L','total' => 'M'],
        'Mickael' => ['label' => 'O','date' => 'P','total' => 'Q'],
    ];
}

/* ---------- Router ---------- */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

/* ---------- Rate Limiting ---------- */
require_once __DIR__ . '/rate_limit_middleware.php';

// Apply rate limiting to API endpoints
if (str_starts_with($path, '/api/')) {
    $rateLimitId = getRateLimitIdentifier();
    applyRateLimit($rateLimitId, $path);
}

/* ---------- Cache Cleanup (once per day max) ---------- */
// Cleanup old Document AI cache files (24h+ old) - run max once per day
$cleanupFlagFile = sys_get_temp_dir() . '/docai_cache_cleanup_last_run.txt';
$cleanupInterval = 86400; // 24 hours
if (!file_exists($cleanupFlagFile) || (time() - filemtime($cleanupFlagFile)) > $cleanupInterval) {
    cleanupDocAiCache(86400); // Clean files older than 24h
    touch($cleanupFlagFile);
}

/* ---- GET /api/debug/headers ---- */
if ($path === '/api/debug/headers') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "getallheaders():\n";
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            echo "$k: $v\n";
        }
    } else {
        echo "(getallheaders indisponible)\n";
    }
    echo "\n_SERVER:\n";
    foreach (['HTTP_AUTHORIZATION','Authorization','REDIRECT_HTTP_AUTHORIZATION'] as $k) {
        if (isset($_SERVER[$k])) {
            echo "$k: " . $_SERVER[$k] . "\n";
        }
    }
    exit;
}

/* ---- GET /debug/routes (DEV ONLY) ---- */
if ($path === '/debug/routes' && $APP_ENV === 'local') {
    addDebugHeaders('api.php::debug/routes', '/debug/routes');

    $routes = [
        'GET /api/config' => 'api.php::config - Configuration de l\'application',
        'GET /api/auth/me' => 'api.php::auth/me - Authentification utilisateur',
        'GET /api/sheets' => 'api.php::sheets - Liste des feuilles Google Sheets',
        'POST /api/scan' => 'api.php::scan - Scanner un ticket',
        'POST /api/scan-batch' => 'api.php::scan-batch - Scanner plusieurs tickets',
        'GET /api/health' => 'api.php::health - Santé de l\'application',
        'GET /api/ready' => 'api.php::ready - Prêt à recevoir des requêtes',
        'GET /api/debug/headers' => 'api.php::debug/headers - Debug des headers HTTP',
        'GET /debug/routes' => 'api.php::debug/routes - Cette page (dev seulement)',
    ];

    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Routes Debug - Receipt API</title>";
    echo "<style>";
    echo "body{font-family:monospace;margin:20px;} ";
    echo ".route{margin:10px 0;padding:10px;background:#f5f5f5;border-left:4px solid #007cba;} ";
    echo ".method{color:#007cba;font-weight:bold;} .path{color:#333;} .handler{color:#666;}";
    echo "</style>";
    echo "</head><body>";
    echo "<h1>Routes Debug - Receipt API</h1>";
    echo "<p><strong>APP_ENV:</strong> $APP_ENV</p>";
    echo "<p><strong>DEBUG:</strong> " . ($DEBUG ? 'ON' : 'OFF') . "</p>";
    echo "<p><strong>Timestamp:</strong> " . date('c') . "</p>";
    echo "<h2>Available Routes:</h2>";

    foreach ($routes as $route => $description) {
        $parts = explode(' ', $route, 2);
        $method = $parts[0];
        $path = $parts[1];
        echo "<div class='route'>";
        echo "<span class='method'>$method</span> <span class='path'>$path</span><br>";
        echo "<span class='handler'>$description</span>";
        echo "</div>";
    }

    echo "<h2>Request Info:</h2>";
    echo "<p><strong>Current URL:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "</p>";
    echo "<p><strong>Method:</strong> " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . "</p>";
    echo "<p><strong>Host:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "</p>";

    outputDebugDumps();
    echo "</body></html>";
    exit;
}

/* ---- GET /api/health & /health ---- */
if (($path === '/api/health' || $path === '/health') && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    addDebugHeaders('api.php::health', $path);

    // Basic health check - just verify the process is alive
    sendJsonResponse([
        'ok' => true,
        'status' => 'alive',
        'timestamp' => date('c')
    ]);
}

/* ---- GET /api/ready & /ready ---- */
if (($path === '/api/ready' || $path === '/ready') && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    // Readiness check - verify credentials and critical dependencies
    $validation = validateGoogleCredentials();

    if (!$validation['valid']) {
        http_response_code(503);
        sendJsonResponse([
            'ok' => false,
            'status' => 'not_ready',
            'error' => $validation['error'],
            'code' => $validation['code'],
            'timestamp' => date('c')
        ]);
    }

    // Check other critical environment variables
    $criticalEnvVars = [
        'GOOGLE_OAUTH_CLIENT_ID' => $CLIENT_ID,
        'SPREADSHEET_ID' => $SPREADSHEET_ID,
        'GCP_PROJECT_ID' => $PROJECT_ID,
        'GCP_PROCESSOR_ID' => $PROCESSOR_ID
    ];

    $missingVars = [];
    foreach ($criticalEnvVars as $var => $value) {
        if (empty($value)) {
            $missingVars[] = $var;
        }
    }

    if (!empty($missingVars)) {
        http_response_code(503);
        sendJsonResponse([
            'ok' => false,
            'status' => 'not_ready',
            'error' => 'Missing required environment variables: ' . implode(', ', $missingVars),
            'code' => 'MISSING_ENV_VARS',
            'timestamp' => date('c')
        ]);
    }

    sendJsonResponse([
        'ok' => true,
        'status' => 'ready',
        'credentials' => [
            'valid' => true,
            'project_id' => $validation['project_id'] ?? null,
            'client_email' => $validation['client_email'] ?? null
        ],
        'timestamp' => date('c')
    ]);
}

/* ---- GET /api/config ---- */
if ($path === '/api/config' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    addDebugHeaders('api.php::config', '/api/config');

    // Debug: Afficher les variables d'environnement en mode dev
    debugDump([
        'ALLOWED_EMAILS' => $ALLOWED_EMAILS,
        'DEFAULT_SHEET' => $DEFAULT_SHEET,
        'WHO_COLUMNS' => $WHO_COLUMNS,
        'APP_ENV' => $APP_ENV
    ], 'Config Variables');

    // Cache court pour éviter les requêtes répétées
    header('Cache-Control: public, max-age=300'); // 5 minutes

    $response = [
        'ok'              => true,
        'client_id'       => $CLIENT_ID,
        'default_sheet'   => $DEFAULT_SHEET,
        'receipt_api_url' => detectProtocol() . '://' . $_SERVER['HTTP_HOST'] . '/api/scan',
        'who_options'     => array_keys($WHO_COLUMNS),
        'max_batch'       => $MAX_BATCH_UPLOADS,
    ];

    sendJsonResponse($response);

    // Afficher les debug dumps après la réponse JSON
    outputDebugDumps();
    exit;
}

/* ---- GET /api/auth/me ---- */
if ($path === '/api/auth/me') {
    addDebugHeaders('api.php::auth/me', '/api/auth/me');

    debugDump($ALLOWED_EMAILS, 'ALLOWED_EMAILS');

    try {
        $auth = requireGoogleUserAllowed($ALLOWED_EMAILS, $CLIENT_ID);
        sendJsonResponse(['ok' => true, 'email' => $auth['email']]);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        if (strpos($message, 'Token') !== false || strpos($message, 'manquant') !== false) {
            sendErrorResponse('Connexion non autorisée', 401);
        } elseif (strpos($message, 'non autorisé') !== false) {
            sendErrorResponse('Email non autorisé', 403);
        } else {
            sendErrorResponse('Erreur serveur', 500);
        }
    }
}

/* ---- GET /api/sheets ---- */
if ($path === '/api/sheets' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    try {
        requireGoogleUserAllowed($ALLOWED_EMAILS, $CLIENT_ID);
        if (!$SPREADSHEET_ID) {
            throw new RuntimeException('SPREADSHEET_ID manquant');
        }

        $token = saToken(['https://www.googleapis.com/auth/spreadsheets.readonly']);
        $url   = 'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode($SPREADSHEET_ID)
            . '?fields=sheets(properties(sheetId,title,index))';
        $r = http_json('GET', $url, ['Authorization' => "Bearer $token", 'Accept' => 'application/json']);
        if ($r['status'] < 200 || $r['status'] >= 300) {
            throw new RuntimeException($r['text']);
        }

        $props = array_map(fn($s)=>$s['properties'], $r['json']['sheets'] ?? []);
        usort($props, fn($a, $b)=>($a['index'] ?? 0) <=> ($b['index'] ?? 0));
        echo json_encode(['ok' => true, 'sheets' => $props, 'default_sheet' => $DEFAULT_SHEET]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false,'error' => $e->getMessage()]);
    }
    exit;
}

/* ---- POST /api/sheets/write ---- */
if ($path === '/api/sheets/write' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $requestId = bin2hex(random_bytes(4));

    try {
        requireGoogleUserAllowed($ALLOWED_EMAILS, $CLIENT_ID);
        if (!$SPREADSHEET_ID) {
            throw new RuntimeException('SPREADSHEET_ID manquant');
        }

        $body      = json_decode(file_get_contents('php://input') ?: '', true);
        $sheetName = (string)($body['sheetName'] ?? '');
        $who       = (string)($body['who'] ?? '');
        $supplier  = trim((string)($body['supplier'] ?? ''));
        $dateISO   = (string)($body['dateISO'] ?? '');
        $total     = (float)($body['total'] ?? 0);

        logMessage('info', "📝 Write request received", [
            'request_id' => $requestId,
            'sheet' => $sheetName,
            'who' => $who,
            'supplier' => $supplier,
            'date' => $dateISO,
            'total' => $total
        ]);

        if (!$sheetName || !$who || !$supplier || !$dateISO) {
            throw new RuntimeException('Champs requis');
        }

        $colsArr = $WHO_COLUMNS[$who] ?? null;
        if (!$colsArr) {
            throw new RuntimeException('who inconnu');
        }
        $cols = ['label' => $colsArr[0], 'date' => $colsArr[1], 'total' => $colsArr[2]];
        $startRow = 11;

        $token = saToken(['https://www.googleapis.com/auth/spreadsheets']);

        // Use aggregation with label detection and incremental formulas
        $result = writeToSheetWithAggregation(
            $SPREADSHEET_ID,
            $sheetName,
            $cols,
            $startRow,
            $supplier,
            $dateISO,
            $total,
            $token,
            5 // max 5 retries
        );

        logMessage('info', "🎉 Write request completed successfully", [
            'request_id' => $requestId,
            'sheet' => $sheetName,
            'row' => $result['written']['row'] ?? 'unknown',
            'attempts' => $result['written']['attempts'] ?? 1,
            'duration_ms' => (microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? 0)) * 1000
        ]);

        echo json_encode($result);
    } catch (Throwable $e) {
        logMessage('error', "❌ Write request failed", [
            'request_id' => $requestId ?? 'unknown',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        http_response_code(500);
        echo json_encode(['ok' => false,'error' => $e->getMessage()]);
    }
    exit;
}

/* ---- POST /api/scan ---- */
if ($path === '/api/scan' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        requireGoogleUserAllowed($ALLOWED_EMAILS, $CLIENT_ID);
        if (!$PROJECT_ID || !$PROCESSOR_ID) {
            throw new RuntimeException('Config DocAI manquante');
        }

        $bytes = null;
        $mime = 'image/jpeg';
        $j = null;
        $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') !== false) {
            $j   = json_decode(file_get_contents('php://input') ?: '', true);
            $b64 = $j['imageBase64'] ?? '';
            if (!$b64) {
                throw new RuntimeException('imageBase64 manquant');
            }
            $raw = preg_replace('#^data:[^;]+;base64,#', '', $b64);
            $bytes = base64_decode($raw, true);
            if ($bytes === false) {
                throw new RuntimeException('Base64 invalide');
            }
            $f = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_buffer($f, $bytes) ?: $mime;
            finfo_close($f);
        } elseif (!empty($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $bytes = file_get_contents($_FILES['document']['tmp_name']);
            $f = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_buffer($f, $bytes) ?: $mime;
            finfo_close($f);
        } else {
            throw new RuntimeException('Aucun document transmis');
        }

        $doc  = docai_process_bytes_cached($bytes, $mime, $PROJECT_ID, $LOCATION, $PROCESSOR_ID);

        // Option: renvoyer le JSON DocAI brut
        $wantRaw = (isset($_GET['raw']) && $_GET['raw'] == '1') || (is_array($j) && !empty($j['include_raw_only']));
        if ($wantRaw) {
            echo json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
            exit;
        }

        $data = docai_extract_triplet($doc);
        echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false,'error' => $e->getMessage()]);
    }
    exit;
}

/* ---- POST /api/scan/batch ---- */
if ($path === '/api/scan/batch' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        requireGoogleUserAllowed($ALLOWED_EMAILS, $CLIENT_ID);
        if (!$PROJECT_ID || !$PROCESSOR_ID) {
            throw new RuntimeException('Config DocAI manquante');
        }

        $j = json_decode(file_get_contents('php://input') ?: '', true);
        $arr = $j['imagesBase64'] ?? null;
        if (!is_array($arr) || !count($arr)) {
            throw new RuntimeException('imagesBase64[] manquant');
        }

        if (count($arr) > $MAX_BATCH_UPLOADS) {
            http_response_code(400);
            echo json_encode([
                'ok'    => false,
                'error' => "Trop d'images: max $MAX_BATCH_UPLOADS"
            ]);
            exit;
        }

        $items = [];
        foreach ($arr as $idx => $b64) {
            try {
                $raw   = preg_replace('#^data:[^;]+;base64,#', '', (string)$b64);
                $bytes = base64_decode($raw, true);
                if ($bytes === false) {
                    throw new RuntimeException('Base64 invalide');
                }

                $f = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_buffer($f, $bytes) ?: 'image/jpeg';
                finfo_close($f);

                $doc   = docai_process_bytes_cached($bytes, $mime, $PROJECT_ID, $LOCATION, $PROCESSOR_ID);
                $data  = docai_extract_triplet($doc);

                $items[] = ['ok' => true] + $data;
            } catch (Throwable $eItem) {
                $items[] = ['ok' => false,'error' => $eItem->getMessage()];
            }
        }

        echo json_encode(['ok' => true,'items' => $items], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false,'error' => $e->getMessage()]);
    }
    exit;
}

/* ---- GET /api/health ---- */
if ($path === '/api/health') {
    sendJsonResponse(['ok' => true, 'status' => 'alive', 'timestamp' => date('c')]);
}

/* ---- GET /api/ready ---- */
if ($path === '/api/ready') {
    try {
        // Vérifier que PHP fonctionne
        if (!function_exists('json_encode')) {
            throw new RuntimeException('PHP JSON extension missing');
        }

        // Vérifier DocAI (si configuré)
        if ($PROJECT_ID && $PROCESSOR_ID) {
            $token = saToken(['https://www.googleapis.com/auth/cloud-platform']);
            // Test simple de connectivité DocAI
            $testUrl = sprintf(
                'https://%s-documentai.googleapis.com/v1/projects/%s/locations/%s/processors/%s',
                $LOCATION,
                $PROJECT_ID,
                $LOCATION,
                $PROCESSOR_ID
            );
            $r = http_json('GET', $testUrl, ['Authorization' => "Bearer $token", 'Accept' => 'application/json']);
            if ($r['status'] < 200 || $r['status'] >= 300) {
                throw new RuntimeException('DocAI service unavailable');
            }
        }

        sendJsonResponse(['ok' => true, 'status' => 'ready', 'timestamp' => date('c')]);
    } catch (Throwable $e) {
        sendErrorResponse('Service not ready: ' . $e->getMessage(), 503);
    }
}

/* ---- fallback ---- */
sendJsonResponse([
    'ok' => true,
    'endpoints' => [
        '/api/config',
        '/api/auth/me',
        '/api/sheets',
        '/api/sheets/write',
        '/api/scan',
        '/api/scan/batch',
        '/api/health',
        '/api/ready'
    ]
]);
