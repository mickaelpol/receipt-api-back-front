<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Google\Auth\ApplicationDefaultCredentials;

/**
 * Endpoints:
 *  GET  /config
 *  GET  /auth/me
 *  GET  /sheets
 *  POST /sheets/write
 *  POST /scan
 *  POST /scan/batch
 */

$DEBUG = (getenv('DEBUG') && getenv('DEBUG') !== '0');
if ($DEBUG) { ini_set('display_errors', '1'); error_reporting(E_ALL); }
else        { ini_set('display_errors', '0'); error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED); }

/* ---------- CORS ---------- */
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = getenv('ALLOWED_ORIGINS') ?: '*';
$allow   = '*';
if ($allowed !== '*') {
    $list = array_map('trim', explode(',', $allowed));
    if ($origin && in_array($origin, $list, true)) $allow = $origin;
    else $allow = $list[0] ?? '*';
}
header('Access-Control-Allow-Origin: '.$allow);
header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

header('Content-Type: application/json; charset=utf-8');

/* ---------- ENV ---------- */
$CLIENT_ID      = getenv('GOOGLE_OAUTH_CLIENT_ID') ?: '';
$ALLOWED_EMAILS = array_values(array_filter(array_map('strtolower',
    array_map('trim', explode(',', getenv('ALLOWED_EMAILS') ?: '')))));

$SPREADSHEET_ID = getenv('SPREADSHEET_ID') ?: '';
$DEFAULT_SHEET  = getenv('DEFAULT_SHEET') ?: '';

$PROJECT_ID     = getenv('GCP_PROJECT_ID') ?: '';
$LOCATION       = getenv('GCP_LOCATION') ?: 'eu';
$PROCESSOR_ID   = getenv('GCP_PROCESSOR_ID') ?: '';

$MAX_BATCH_UPLOADS = (int) (getenv('MAX_BATCH_UPLOADS') ?: 10);

/**
 * WHO_COLUMNS dans .env :
 *   WHO_COLUMNS="Sabrina=K,L,M;Mickael=O,P,Q"
 */
function parse_who_columns(): array {
    // 1) WHO_COLUMNS_JSON (JSON explicite)
    $json = getenv('WHO_COLUMNS_JSON') ?: '';
    if ($json) {
        $arr = json_decode($json, true);
        if (is_array($arr)) {
            $norm = [];
            foreach ($arr as $who => $cols) {
                if (is_array($cols) && count($cols) === 3) {
                    $norm[trim((string)$who)] = [
                        strtoupper($cols[0]), strtoupper($cols[1]), strtoupper($cols[2])
                    ];
                }
            }
            if ($norm) return $norm;
        }
    }

    // 2) WHO_COLUMNS en JSON (ton cas)
    $rawJson = getenv('WHO_COLUMNS') ?: '';
    if ($rawJson && ($arr = json_decode($rawJson, true)) && is_array($arr)) {
        $norm = [];
        foreach ($arr as $who => $cols) {
            if (is_array($cols) && count($cols) === 3) {
                $norm[trim((string)$who)] = [
                    strtoupper($cols[0]), strtoupper($cols[1]), strtoupper($cols[2])
                ];
            }
        }
        if ($norm) return $norm;
    }

    // 3) Format legacy "Sabrina:K,L,M;Mickael:O,P,Q"
    $raw = getenv('WHO_COLUMNS') ?: '';
    $norm = [];
    foreach (array_filter(array_map('trim', explode(';', $raw))) as $chunk) {
        if (!str_contains($chunk, ':')) continue;
        [$who, $colsStr] = array_map('trim', explode(':', $chunk, 2));
        $cols = array_map(fn($c)=>strtoupper(trim($c)), explode(',', $colsStr));
        if ($who !== '' && count($cols) === 3) $norm[$who] = [$cols[0], $cols[1], $cols[2]];
    }
    return $norm;
}

$WHO_COLUMNS = parse_who_columns();

if (!$WHO_COLUMNS) {
    // Fallback si WHO_COLUMNS non défini
    $WHO_COLUMNS = [
        'Sabrina' => ['label'=>'K','date'=>'L','total'=>'M'],
        'Mickael' => ['label'=>'O','date'=>'P','total'=>'Q'],
    ];
}

/* ---------- helpers dates ---------- */
function parse_date_ymd(string $s): ?array {
    $s = trim($s);
    if (preg_match('~^(\d{4})[-/\.](\d{1,2})[-/\.](\d{1,2})$~', $s, $m)) {
        return [(int)$m[1], (int)$m[2], (int)$m[3]];
    }
    if (preg_match('~^(\d{1,2})[-/\.](\d{1,2})[-/\.](\d{4})$~', $s, $m)) {
        return [(int)$m[3], (int)$m[2], (int)$m[1]];
    }
    if (preg_match('~^(\d{4})-(\d{2})-(\d{2})[T\s]~', $s, $m)) {
        return [(int)$m[1], (int)$m[2], (int)$m[3]];
    }
    return null;
}
/** Excel/Sheets serial date (base 1899-12-30) */
function sheets_date_serial(int $y, int $m, int $d): float {
    $ts = gmmktime(0, 0, 0, $m, $d, $y); // UTC midnight
    return $ts / 86400 + 25569;
}

/* ---------- helpers HTTP ---------- */
function http_json(string $method, string $url, array $headers = [], $payload = null): array {
    $ch = curl_init($url);
    $hdrs = [];
    foreach ($headers as $k => $v) $hdrs[] = "$k: $v";
    $opts = [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_HTTPHEADER     => $hdrs,
        CURLOPT_TIMEOUT        => 90,
    ];
    if ($method !== 'GET' && $payload !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    if ($resp === false) { $e = curl_error($ch); curl_close($ch); return ['status'=>0,'text'=>null,'json'=>null,'error'=>$e]; }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdr    = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $text = substr($resp, $hdr);
    $json = json_decode($text, true);
    return ['status'=>$status, 'text'=>$text, 'json'=>$json];
}
function http_get_json_google(string $url, string $bearer): array {
    $r = http_json('GET', $url, ['Authorization'=>"Bearer $bearer", 'Accept'=>'application/json']);
    if ($r['status'] < 200 || $r['status'] >= 300 || !is_array($r['json'])) {
        throw new RuntimeException("HTTP $url failed: ".$r['text']);
    }
    return $r['json'];
}

/* ---------- Auth côté back ---------- */
function bearer(): ?string {
    if (function_exists('getallheaders')) {
        $hdrs = array_change_key_case(getallheaders(), CASE_LOWER);
        if (!empty($hdrs['authorization'])) {
            $h = trim($hdrs['authorization']);
            if (stripos($h, 'bearer ') === 0) return substr($h, 7);
        }
    }
    foreach (['HTTP_AUTHORIZATION', 'Authorization', 'REDIRECT_HTTP_AUTHORIZATION'] as $k) {
        if (!empty($_SERVER[$k])) {
            $h = trim($_SERVER[$k]);
            if (stripos($h, 'Bearer ') === 0) return substr($h, 7);
        }
    }
    if (!empty($_GET['access_token'])) return $_GET['access_token'];
    return null;
}
function requireGoogleUserAllowed(array $allowed, string $clientId): array {
    $tok = bearer();
    if (!$tok) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Token Google manquant']); exit; }

    $ti  = http_get_json_google('https://oauth2.googleapis.com/tokeninfo?access_token='.urlencode($tok), $tok);
    $aud = $ti['aud'] ?? $ti['audience'] ?? null;
    if ($clientId && $aud && !hash_equals($clientId, $aud)) {
        http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Token aud invalide']); exit;
    }
    if (!empty($ti['expires_in']) && (int)$ti['expires_in'] <= 0) {
        http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Token expiré']); exit;
    }

    $ui    = http_get_json_google('https://openidconnect.googleapis.com/v1/userinfo', $tok);
    $email = strtolower(trim((string)($ui['email'] ?? '')));
    $ver   = (bool)($ui['email_verified'] ?? false);
    if (!$email || !$ver) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Email non vérifié']); exit; }
    if ($allowed && !in_array($email, $allowed, true)) {
        http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Email non autorisé']); exit;
    }
    return ['email'=>$email, 'userinfo'=>$ui, 'tokeninfo'=>$ti];
}

/* ---------- SA token ---------- */
function saToken(array $scopes): string {
    $c = ApplicationDefaultCredentials::getCredentials($scopes);
    $t = $c->fetchAuthToken();
    $a = $t['access_token'] ?? '';
    if (!$a) throw new RuntimeException('Impossible d’obtenir un access_token SA');
    return $a;
}

/* ---------- utils fichiers ---------- */
function save_base64_to_tmp(string $b64, string $ext = '.jpg'): string {
    $raw = preg_replace('#^data:[^;]+;base64,#', '', $b64);
    $bin = base64_decode($raw, true);
    if ($bin === false) throw new RuntimeException('Base64 invalide');
    $p = sys_get_temp_dir().'/docai_'.bin2hex(random_bytes(6)).$ext;
    if (@file_put_contents($p, $bin) === false) throw new RuntimeException('Écriture fichier tmp échouée');
    return $p;
}

/* ---------- DocAI ---------- */
function docai_process_bytes(string $bytes, string $mime, string $projectId, string $location, string $processorId): array {
    $token = saToken(['https://www.googleapis.com/auth/cloud-platform']);
    $processUrl = sprintf(
        'https://%s-documentai.googleapis.com/v1/projects/%s/locations/%s/processors/%s:process',
        $location, $projectId, $location, $processorId
    );
    $payload = ['rawDocument'=>['mimeType'=>$mime,'content'=>base64_encode($bytes)]];
    $r = http_json('POST', $processUrl,
        ['Authorization'=>"Bearer $token",'Content-Type'=>'application/json','Accept'=>'application/json'],
        $payload);
    if ($r['status'] < 200 || $r['status'] >= 300) {
        throw new RuntimeException($r['text'] ?: 'Document AI error');
    }
    return $r['json']; // JSON brut DocAI
}
function docai_extract_triplet(array $doc): array {
    $entities = $doc['document']['entities'] ?? [];

    $supplier_name = null;
    $receipt_date  = null;
    $total_amount  = null;
    $currency      = null;

    $prefTotal = null; $prefConf = -1.0;
    $bestTotal = null; $bestConf = -1.0;
    $subtotal  = null; $subtotalConf = -1.0;
    $taxSum    = 0.0;  $hasTax = false;

    $moneyFrom = function(array $e) {
        $nv   = $e['normalizedValue'] ?? null;
        $conf = (float)($e['confidence'] ?? 0.0);
        $val  = null; $cur = null;

        if (is_array($nv) && isset($nv['moneyValue'])) {
            $m     = $nv['moneyValue'];
            $units = (float)($m['units'] ?? 0);
            $nanos = (int)($m['nanos'] ?? 0);
            $val   = $units + ($nanos / 1e9);
            $cur   = $m['currencyCode'] ?? null;
        } else {
            $text = (string)($e['mentionText'] ?? '');
            if ($text !== '') {
                $num = str_replace(',', '.', preg_replace('/[^\d,.\-]/', '', $text));
                if (is_numeric($num)) $val = (float)$num;
            }
        }
        return [$val, $cur, $conf];
    };

    $walk = function(array $e) use (&$supplier_name,&$receipt_date,&$currency,
        &$prefTotal,&$prefConf,&$bestTotal,&$bestConf,&$subtotal,&$subtotalConf,&$taxSum,&$hasTax,&$walk,$moneyFrom) {

        $type = strtolower((string)($e['type'] ?? ''));
        $text = (string)($e['mentionText'] ?? '');

        if ($supplier_name === null && preg_match('/supplier_name|supplier|merchant|merchant_name|vendor|retailer|store/', $type)) {
            $supplier_name = $text ?: (($e['normalizedValue']['text'] ?? null) ?: $supplier_name);
        }

        if ($receipt_date === null && preg_match('/receipt_?date|transaction_?date|start_?date|end_?date|date/', $type)) {
            $nv = $e['normalizedValue'] ?? null;
            if (is_array($nv) && isset($nv['dateValue'])) {
                $d=$nv['dateValue']; $y=$d['year']??null; $m=$d['month']??null; $dd=$d['day']??null;
                if ($y&&$m&&$dd) $receipt_date=sprintf('%04d-%02d-%02d',$y,$m,$dd);
            }
            if ($receipt_date===null && $text) {
                if (preg_match('~(\d{4})[-/](\d{2})[-/](\d{2})~',$text,$mm))
                    $receipt_date=sprintf('%04d-%02d-%02d',$mm[1],$mm[2],$mm[3]);
                elseif (preg_match('~(\d{2})/(\d{2})/(\d{4})~',$text,$mm))
                    $receipt_date=sprintf('%04d-%02d-%02d',$mm[3],$mm[2],$mm[1]);
            }
        }

        if (preg_match('/total_?amount/', $type)) {
            [$v,$cur,$conf] = $moneyFrom($e);
            if ($v !== null && $conf >= $prefConf) { $prefTotal = $v; $prefConf = $conf; if ($cur) $currency=$cur; }
        } elseif (preg_match('/(grand_?total|amount_?total|^total$|_total$)/', $type)) {
            [$v,$cur,$conf] = $moneyFrom($e);
            if ($v !== null && $conf > $bestConf)  { $bestTotal = $v; $bestConf = $conf; if ($cur) $currency=$cur; }
        } elseif (preg_match('/(subtotal|net_?amount)/', $type)) {
            [$v,$cur,$conf] = $moneyFrom($e);
            if ($v !== null && $conf > $subtotalConf) { $subtotal = $v; $subtotalConf = $conf; if ($cur) $currency=$cur; }
        } elseif (preg_match('/(total_?tax_?amount|tax_?amount|vat_?amount)/', $type)) {
            [$v,$cur,$conf] = $moneyFrom($e);
            if ($v !== null) { $taxSum += $v; $hasTax = true; if ($cur) $currency=$cur; }
        }

        foreach (($e['properties'] ?? []) as $c) $walk($c);
    };
    foreach ($entities as $e) $walk($e);

    if     ($prefTotal !== null)                  $total_amount = $prefTotal;
    elseif ($bestTotal !== null)                  $total_amount = $bestTotal;
    elseif ($subtotal !== null && $hasTax)        $total_amount = $subtotal + $taxSum;
    else                                          $total_amount = null;

    return [
        'supplier_name' => $supplier_name,
        'receipt_date'  => $receipt_date,
        'total_amount'  => $total_amount,
    ];
}

/* ---------- helpers Sheets ---------- */
/** Lettre(s) colonne -> index 0-based */
function col_letter_to_index(string $col): int {
    $col = strtoupper(trim($col));
    $n = 0;
    for ($i = 0; $i < strlen($col); $i++) {
        $n = $n * 26 + (ord($col[$i]) - 64);
    }
    return max(0, $n - 1);
}
/** Récupère sheetId par titre */
function get_sheet_id_by_title(string $spreadsheetId, string $title, string $token): int {
    $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode($spreadsheetId)
        . '?fields=sheets(properties(sheetId,title))';
    $r = http_json('GET', $url, ['Authorization'=>"Bearer $token", 'Accept'=>'application/json']);
    if ($r['status'] < 200 || $r['status'] >= 300) {
        throw new RuntimeException('Impossible de lire la liste des feuilles: ' . ($r['text'] ?? ''));
    }
    foreach (($r['json']['sheets'] ?? []) as $s) {
        $p = $s['properties'] ?? [];
        if (($p['title'] ?? '') === $title) return (int)($p['sheetId'] ?? -1);
    }
    throw new RuntimeException("Feuille introuvable: $title");
}

/* ---------- Router ---------- */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

/* ---- GET /debug/headers ---- */
if ($path === '/debug/headers') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "getallheaders():\n";
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $k=>$v) echo "$k: $v\n";
    } else {
        echo "(getallheaders indisponible)\n";
    }
    echo "\n_SERVER:\n";
    foreach (['HTTP_AUTHORIZATION','Authorization','REDIRECT_HTTP_AUTHORIZATION'] as $k) {
        if (isset($_SERVER[$k])) echo "$k: ".$_SERVER[$k]."\n";
    }
    exit;
}

/* ---- GET /config ---- */
if ($path === '/config' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    echo json_encode([
        'ok'              => true,
        'client_id'       => $CLIENT_ID,
        'default_sheet'   => $DEFAULT_SHEET,
        'receipt_api_url' => (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/scan',
        'who_options'     => array_keys($WHO_COLUMNS),
        'max_batch'       => $MAX_BATCH_UPLOADS,   // ← NEW
    ]);
    exit;
}

/* ---- GET /auth/me ---- */
if ($path === '/auth/me') {
    try {
        $auth = requireGoogleUserAllowed($ALLOWED_EMAILS, $CLIENT_ID);
        echo json_encode(['ok'=>true,'email'=>$auth['email']]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* ---- GET /sheets ---- */
if ($path === '/sheets' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    try {
        requireGoogleUserAllowed($ALLOWED_EMAILS, $CLIENT_ID);
        if (!$SPREADSHEET_ID) throw new RuntimeException('SPREADSHEET_ID manquant');

        $token = saToken(['https://www.googleapis.com/auth/spreadsheets.readonly']);
        $url   = 'https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($SPREADSHEET_ID)
            .'?fields=sheets(properties(sheetId,title,index))';
        $r = http_json('GET', $url, ['Authorization'=>"Bearer $token", 'Accept'=>'application/json']);
        if ($r['status'] < 200 || $r['status'] >= 300) throw new RuntimeException($r['text']);

        $props = array_map(fn($s)=>$s['properties'], $r['json']['sheets'] ?? []);
        usort($props, fn($a,$b)=>($a['index']??0)<=>($b['index']??0));
        echo json_encode(['ok'=>true, 'sheets'=>$props, 'default_sheet'=>$DEFAULT_SHEET]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* ---- POST /sheets/write ---- */
if ($path === '/sheets/write' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $lockFp = null;
    try {
        requireGoogleUserAllowed($ALLOWED_EMAILS, $CLIENT_ID);
        if (!$SPREADSHEET_ID) throw new RuntimeException('SPREADSHEET_ID manquant');

        $body      = json_decode(file_get_contents('php://input') ?: '', true);
        $sheetName = (string)($body['sheetName'] ?? '');
        $who       = (string)($body['who'] ?? '');
        $supplier  = trim((string)($body['supplier'] ?? ''));
        $dateISO   = (string)($body['dateISO'] ?? '');
        $total     = (float)($body['total'] ?? 0);
        if (!$sheetName || !$who || !$supplier || !$dateISO) throw new RuntimeException('Champs requis');

        $colsArr = $WHO_COLUMNS[$who] ?? null;
        if (!$colsArr) throw new RuntimeException('who inconnu');
        $cols = ['label'=>$colsArr[0], 'date'=>$colsArr[1], 'total'=>$colsArr[2]];
        $startRow = 11;

        // ----- VERROU (évite collisions de ligne en écriture concurrente)
        $lockKey  = preg_replace('/[^a-z0-9_\-]/i','_', $SPREADSHEET_ID.'_'.$sheetName.'_'.$who);
        $lockPath = sys_get_temp_dir()."/gsheets_lock_{$lockKey}.lock";
        $lockFp   = fopen($lockPath, 'c');
        if ($lockFp) { flock($lockFp, LOCK_EX); }

        $token = saToken(['https://www.googleapis.com/auth/spreadsheets']);

        // 1) trouver la prochaine ligne vide (dans la colonne "label")
        $scanRange = sprintf('%s!%s%d:%s', $sheetName, $cols['label'], $startRow, $cols['label']);
        $getUrl = 'https://sheets.googleapis.com/v4/spreadsheets/'
            . rawurlencode($SPREADSHEET_ID)
            . '/values/' . rawurlencode($scanRange);
        $qr = http_json('GET', $getUrl, ['Authorization'=>"Bearer $token", 'Accept'=>'application/json']);
        if ($qr['status'] < 200 || $qr['status'] >= 300) {
            throw new RuntimeException($qr['text'] ?: 'Lecture feuille échouée');
        }
        $values = $qr['json']['values'] ?? [];
        $row = $startRow;
        for ($i = 0; $i < count($values); $i++) {
            $v = isset($values[$i][0]) ? trim((string)$values[$i][0]) : '';
            if ($v === '') { $row = $startRow + $i; break; }
            $row = $startRow + $i + 1;
        }

        // 2) préparer la date numérique (serial)
        $ymd = parse_date_ymd($dateISO);
        if ($ymd) { [$yy,$mm,$dd] = $ymd; $dateValue = sheets_date_serial($yy,$mm,$dd); }
        else { $dateValue = $dateISO; } // fallback

        // 3) écriture des 3 valeurs (RAW)
        $putRange = sprintf('%s!%s%d:%s%d', $sheetName, $cols['label'], $row, $cols['total'], $row);
        $updUrl = 'https://sheets.googleapis.com/v4/spreadsheets/'
            . rawurlencode($SPREADSHEET_ID)
            . '/values/' . rawurlencode($putRange)
            . '?valueInputOption=RAW';

        $wr = http_json('PUT', $updUrl,
            ['Authorization'=>"Bearer $token",'Content-Type'=>'application/json','Accept'=>'application/json'],
            ['values'=>[[ $supplier, $dateValue, $total ]]]
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
            'https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($SPREADSHEET_ID).':batchUpdate',
            ['Authorization'=>"Bearer $token",'Content-Type'=>'application/json','Accept'=>'application/json'],
            $batchBody
        );
        if ($fmt['status'] < 200 || $fmt['status'] >= 300) {
            throw new RuntimeException('Format date échoué: '.$fmt['text']);
        }

        echo json_encode(['ok'=>true,'written'=>['sheet'=>$sheetName,'row'=>$row,'range'=>$putRange]]);

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    } finally {
        if ($lockFp) { flock($lockFp, LOCK_UN); fclose($lockFp); }
    }
    exit;
}

/* ---- POST /scan ---- */
if ($path === '/scan' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        requireGoogleUserAllowed($ALLOWED_EMAILS, $CLIENT_ID);
        if (!$PROJECT_ID || !$PROCESSOR_ID) throw new RuntimeException('Config DocAI manquante');

        $bytes = null; $mime = 'image/jpeg'; $j = null;
        $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') !== false) {
            $j   = json_decode(file_get_contents('php://input') ?: '', true);
            $b64 = $j['imageBase64'] ?? '';
            if (!$b64) throw new RuntimeException('imageBase64 manquant');
            $raw = preg_replace('#^data:[^;]+;base64,#', '', $b64);
            $bytes = base64_decode($raw, true);
            if ($bytes === false) throw new RuntimeException('Base64 invalide');
            $f = finfo_open(FILEINFO_MIME_TYPE); $mime = finfo_buffer($f, $bytes) ?: $mime; finfo_close($f);
        } elseif (!empty($_FILES['document']) && $_FILES['document']['error']===UPLOAD_ERR_OK) {
            $bytes = file_get_contents($_FILES['document']['tmp_name']);
            $f = finfo_open(FILEINFO_MIME_TYPE); $mime = finfo_buffer($f, $bytes) ?: $mime; finfo_close($f);
        } else {
            throw new RuntimeException('Aucun document transmis');
        }

        $doc  = docai_process_bytes($bytes, $mime, $PROJECT_ID, $LOCATION, $PROCESSOR_ID);

        // Option: renvoyer le JSON DocAI brut
        $wantRaw = (isset($_GET['raw']) && $_GET['raw'] == '1') || (is_array($j) && !empty($j['include_raw_only']));
        if ($wantRaw) { echo json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION); exit; }

        $data = docai_extract_triplet($doc);
        echo json_encode(['ok'=>true] + $data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* ---- POST /scan/batch ---- */
if ($path === '/scan/batch' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        requireGoogleUserAllowed($ALLOWED_EMAILS, $CLIENT_ID);
        if (!$PROJECT_ID || !$PROCESSOR_ID) throw new RuntimeException('Config DocAI manquante');

        $j = json_decode(file_get_contents('php://input') ?: '', true);
        $arr = $j['imagesBase64'] ?? null;
        if (!is_array($arr) || !count($arr)) throw new RuntimeException('imagesBase64[] manquant');

        if (count($arr) > $MAX_BATCH_UPLOADS) {
            http_response_code(400);
            echo json_encode([
                'ok'    => false,
                'error' => "Trop d’images: max $MAX_BATCH_UPLOADS"
            ]);
            exit;
        }

        $items = [];
        foreach ($arr as $idx => $b64) {
            try {
                $raw   = preg_replace('#^data:[^;]+;base64,#', '', (string)$b64);
                $bytes = base64_decode($raw, true);
                if ($bytes === false) throw new RuntimeException('Base64 invalide');

                $f = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_buffer($f, $bytes) ?: 'image/jpeg';
                finfo_close($f);

                $doc   = docai_process_bytes($bytes, $mime, $PROJECT_ID, $LOCATION, $PROCESSOR_ID);
                $data  = docai_extract_triplet($doc);

                $items[] = ['ok'=>true] + $data;
            } catch (Throwable $eItem) {
                $items[] = ['ok'=>false,'error'=>$eItem->getMessage()];
            }
        }

        echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* ---- fallback ---- */
echo json_encode(['ok'=>true,'endpoints'=>['/config','/auth/me','/sheets','/sheets/write','/scan','/scan/batch']]);
