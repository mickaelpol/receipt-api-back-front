<?php

/**
 * Main application entry point - contains only runtime logic
 * All function declarations are in app.php
 */

declare(strict_types=1);

// Include bootstrap (autoload + app declarations)
require __DIR__ . '/bootstrap.php';

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
    $allow = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
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

/* ---- GET /api/health ---- */
if ($path === '/api/health' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    // Basic health check - just verify the process is alive
    sendJsonResponse([
        'ok' => true,
        'status' => 'alive',
        'timestamp' => date('c')
    ]);
}

/* ---- GET /api/ready ---- */
if ($path === '/api/ready' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
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
    // Cache court pour éviter les requêtes répétées
    header('Cache-Control: public, max-age=300'); // 5 minutes
    sendJsonResponse([
        'ok'              => true,
        'client_id'       => $CLIENT_ID,
        'default_sheet'   => $DEFAULT_SHEET,
        'receipt_api_url' => (isset($_SERVER['HTTPS']) ? 'https' : 'http') .
            '://' . $_SERVER['HTTP_HOST'] . '/api/scan',
        'who_options'     => array_keys($WHO_COLUMNS),
        'max_batch'       => $MAX_BATCH_UPLOADS,
    ]);
}

/* ---- GET /api/auth/me ---- */
if ($path === '/api/auth/me') {
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
    $lockFp = null;
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
        if (!$sheetName || !$who || !$supplier || !$dateISO) {
            throw new RuntimeException('Champs requis');
        }

        $colsArr = $WHO_COLUMNS[$who] ?? null;
        if (!$colsArr) {
            throw new RuntimeException('who inconnu');
        }
        $cols = ['label' => $colsArr[0], 'date' => $colsArr[1], 'total' => $colsArr[2]];
        $startRow = 11;

        // ----- VERROU (évite collisions de ligne en écriture concurrente)
        $lockKey  = preg_replace('/[^a-z0-9_\-]/i', '_', $SPREADSHEET_ID . '_' . $sheetName . '_' . $who);
        $lockPath = sys_get_temp_dir() . "/gsheets_lock_{$lockKey}.lock";
        $lockFp   = fopen($lockPath, 'c');
        if ($lockFp) {
            flock($lockFp, LOCK_EX);
        }

        $token = saToken(['https://www.googleapis.com/auth/spreadsheets']);

        // 1) trouver la prochaine ligne vide (dans la colonne "label")
        $scanRange = sprintf('%s!%s%d:%s', $sheetName, $cols['label'], $startRow, $cols['label']);
        $getUrl = 'https://sheets.googleapis.com/v4/spreadsheets/'
            . rawurlencode($SPREADSHEET_ID)
            . '/values/' . rawurlencode($scanRange);
        $qr = http_json('GET', $getUrl, ['Authorization' => "Bearer $token", 'Accept' => 'application/json']);
        if ($qr['status'] < 200 || $qr['status'] >= 300) {
            throw new RuntimeException($qr['text'] ?: 'Lecture feuille échouée');
        }
        $values = $qr['json']['values'] ?? [];
        $row = $startRow;
        for ($i = 0; $i < count($values); $i++) {
            $v = isset($values[$i][0]) ? trim((string)$values[$i][0]) : '';
            if ($v === '') {
                $row = $startRow + $i;
                break;
            }
            $row = $startRow + $i + 1;
        }

        // 2) préparer la date numérique (serial)
        $ymd = parse_date_ymd($dateISO);
        if ($ymd) {
            [$yy,$mm,$dd] = $ymd;
            $dateValue = sheets_date_serial($yy, $mm, $dd);
        } else {
            $dateValue = $dateISO;
        } // fallback

        // 3) écriture des 3 valeurs (RAW)
        $putRange = sprintf('%s!%s%d:%s%d', $sheetName, $cols['label'], $row, $cols['total'], $row);
        $updUrl = 'https://sheets.googleapis.com/v4/spreadsheets/'
            . rawurlencode($SPREADSHEET_ID)
            . '/values/' . rawurlencode($putRange)
            . '?valueInputOption=RAW';

        $wr = http_json(
            'PUT',
            $updUrl,
            [
                'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            ['values' => [[ $supplier, $dateValue, $total ]]]
        );
        if ($wr['status'] < 200 || $wr['status'] >= 300) {
            throw new RuntimeException($wr['text'] ?: 'Écriture échouée');
        }

        // 4) forcer le format Date sur la cellule de date (dd/mm/yyyy)
        $sheetId = get_sheet_id_by_title($SPREADSHEET_ID, $sheetName, $token);
        $dateColIndex = col_letter_to_index($cols['date']);
        $range = [
            'sheetId' => $sheetId,
            'startRowIndex' => $row - 1,
            'endRowIndex'   => $row,
            'startColumnIndex' => $dateColIndex,
            'endColumnIndex'   => $dateColIndex + 1,
        ];
        $batchBody = [
            'requests' => [[
                'repeatCell' => [
                    'range' => $range,
                    'cell' => [
                        'userEnteredFormat' => [
                            'numberFormat' => [
                                'type' => 'DATE',
                                'pattern' => 'dd/mm/yyyy'
                            ]
                        ]
                    ],
                    'fields' => 'userEnteredFormat.numberFormat'
                ]
            ]]
        ];
        $fmt = http_json(
            'POST',
            'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode($SPREADSHEET_ID) . ':batchUpdate',
            [
                'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            $batchBody
        );
        if ($fmt['status'] < 200 || $fmt['status'] >= 300) {
            throw new RuntimeException('Format date échoué: ' . $fmt['text']);
        }

        echo json_encode(['ok' => true,'written' => ['sheet' => $sheetName,'row' => $row,'range' => $putRange]]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false,'error' => $e->getMessage()]);
    } finally {
        if ($lockFp) {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
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

        $doc  = docai_process_bytes($bytes, $mime, $PROJECT_ID, $LOCATION, $PROCESSOR_ID);

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

                $doc   = docai_process_bytes($bytes, $mime, $PROJECT_ID, $LOCATION, $PROCESSOR_ID);
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
