<?php

declare(strict_types=1);

use Google\Auth\ApplicationDefaultCredentials;

/**
 * Application declarations - contains only function/class definitions
 * No side effects or runtime logic
 */

/**
 * Structured logging function
 * @param string $level Log level (info, warn, error)
 * @param string $message Log message
 * @param array $context Additional context data
 */
function logMessage($level, $message, $context = [])
{
    global $DEBUG;

    $logEntry = [
        'timestamp' => date('c'),
        'level' => $level,
        'message' => $message,
        'context' => $context,
        'endpoint' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];

    // In production, only log errors and warnings
    if (!$DEBUG && !in_array($level, ['error', 'warn'])) {
        return;
    }

    // Output as JSON for structured logging
    error_log(json_encode($logEntry));
}

/**
 * Log API request
 * @param string $endpoint API endpoint
 * @param int $status HTTP status code
 * @param array $context Additional context
 */
function logApiRequest($endpoint, $status, $context = [])
{
    $level = $status >= 400 ? 'error' : 'info';
    $message = "API request: {$endpoint} -> {$status}";
    
    $context['status_code'] = $status;
    $context['endpoint'] = $endpoint;
    
    logMessage($level, $message, $context);
}

/**
 * Mask sensitive data in strings
 * @param string $data Input data
 * @return string Masked data
 */
function maskSensitiveData($data)
{
    if (!is_string($data)) {
        return $data;
    }

    // Mask tokens, IDs, and other sensitive patterns
    $patterns = [
        '/Bearer\s+[A-Za-z0-9\-_]+/' => 'Bearer ***',
        '/token["\']?\s*[:=]\s*["\']?[A-Za-z0-9\-_]+/' => 'token="***"',
        '/client_id["\']?\s*[:=]\s*["\']?[A-Za-z0-9\-_]+/' => 'client_id="***"',
        '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/' => '***@***.***'
    ];

    foreach ($patterns as $pattern => $replacement) {
        $data = preg_replace($pattern, $replacement, $data);
    }

    return $data;
}

/**
 * Validate WHO_COLUMNS format
 * @param string $whoColumns JSON string
 * @return bool True if valid
 */
function validate_who_columns(string $whoColumns): bool {
    $decoded = json_decode($whoColumns, true);
    
    if (!is_array($decoded)) {
        return false;
    }
    
    foreach ($decoded as $name => $columns) {
        // Name must be non-empty string
        if (!is_string($name) || trim($name) === '') {
            return false;
        }
        
        // Columns must be array with exactly 3 elements
        if (!is_array($columns) || count($columns) !== 3) {
            return false;
        }
        
        // Each column must be a single letter
        foreach ($columns as $col) {
            if (!is_string($col) || strlen($col) !== 1 || !ctype_alpha($col)) {
                return false;
            }
        }
    }
    
    return !empty($decoded);
}

/**
 * Parse WHO_COLUMNS configuration with validation
 * @return array Parsed columns configuration
 */
function parse_who_columns(): array
{
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
            if ($norm) {
                return $norm;
            }
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
        if ($norm) {
            return $norm;
        }
    }

    // 3) Format legacy "Sabrina:K,L,M;Mickael:O,P,Q"
    $raw = getenv('WHO_COLUMNS') ?: '';
    $norm = [];
    foreach (array_filter(array_map('trim', explode(';', $raw))) as $chunk) {
        if (!str_contains($chunk, ':')) {
            continue;
        }
        [$who, $colsStr] = array_map('trim', explode(':', $chunk, 2));
        $cols = array_map(fn($c)=>strtoupper(trim($c)), explode(',', $colsStr));
        if ($who !== '' && count($cols) === 3) {
            $norm[$who] = [$cols[0], $cols[1], $cols[2]];
        }
    }
    return $norm;
}

/**
 * Parse date in YYYY-MM-DD format
 * @param string $s Date string
 * @return array|null [year, month, day] or null
 */
function parse_date_ymd(string $s): ?array
{
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

/**
 * Excel/Sheets serial date (base 1899-12-30)
 * @param int $y Year
 * @param int $m Month
 * @param int $d Day
 * @return float Serial date
 */
function sheets_date_serial(int $y, int $m, int $d): float
{
    $ts = gmmktime(0, 0, 0, $m, $d, $y); // UTC midnight
    return $ts / 86400 + 25569;
}

/**
 * Make HTTP request and return JSON response
 * @param string $method HTTP method
 * @param string $url URL
 * @param array $headers Headers
 * @param mixed $payload Request payload
 * @return array Response data
 */
function http_json(string $method, string $url, array $headers = [], $payload = null): array
{
    $ch = curl_init($url);
    $hdrs = [];
    foreach ($headers as $k => $v) {
        $hdrs[] = "$k: $v";
    }
    $opts = [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_HTTPHEADER     => $hdrs,
        CURLOPT_TIMEOUT        => 90,
    ];
    if ($method !== 'GET' && $payload !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    if ($resp === false) {
        $e = curl_error($ch);
        curl_close($ch);
        return ['status' => 0,'text' => null,'json' => null,'error' => $e];
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdr    = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $text = substr($resp, $hdr);
    $json = json_decode($text, true);
    return ['status' => $status, 'text' => $text, 'json' => $json];
}

/**
 * Make authenticated HTTP request to Google APIs
 * @param string $url URL
 * @param string $bearer Bearer token
 * @return array Response data
 */
function http_get_json_google(string $url, string $bearer): array
{
    $r = http_json('GET', $url, ['Authorization' => "Bearer $bearer", 'Accept' => 'application/json']);
    if ($r['status'] < 200 || $r['status'] >= 300 || !is_array($r['json'])) {
        throw new RuntimeException("HTTP $url failed: " . $r['text']);
    }
    return $r['json'];
}

/**
 * Extract Bearer token from request headers
 * @return string|null Bearer token or null
 */
function bearer(): ?string
{
    if (function_exists('getallheaders')) {
        $hdrs = array_change_key_case(getallheaders(), CASE_LOWER);
        if (!empty($hdrs['authorization'])) {
            $h = trim($hdrs['authorization']);
            if (stripos($h, 'bearer ') === 0) {
                return substr($h, 7);
            }
        }
    }
    foreach (['HTTP_AUTHORIZATION', 'Authorization', 'REDIRECT_HTTP_AUTHORIZATION'] as $k) {
        if (!empty($_SERVER[$k])) {
            $h = trim($_SERVER[$k]);
            if (stripos($h, 'Bearer ') === 0) {
                return substr($h, 7);
            }
        }
    }
    if (!empty($_GET['access_token'])) {
        return $_GET['access_token'];
    }
    return null;
}

/**
 * Require Google user authentication and authorization
 * @param array $allowed Allowed email addresses
 * @param string $clientId OAuth client ID
 * @return array User information
 */
function requireGoogleUserAllowed(array $allowed, string $clientId): array
{
    $tok = bearer();
    if (!$tok) {
        http_response_code(401);
        echo json_encode(['ok' => false,'error' => 'Token Google manquant']);
        exit;
    }

    $ti  = http_get_json_google('https://oauth2.googleapis.com/tokeninfo?access_token=' . urlencode($tok), $tok);
    $aud = $ti['aud'] ?? $ti['audience'] ?? null;
    if ($clientId && $aud && !hash_equals($clientId, $aud)) {
        http_response_code(401);
        echo json_encode(['ok' => false,'error' => 'Token aud invalide']);
        exit;
    }
    if (!empty($ti['expires_in']) && (int)$ti['expires_in'] <= 0) {
        http_response_code(401);
        echo json_encode(['ok' => false,'error' => 'Token expiré']);
        exit;
    }

    $ui    = http_get_json_google('https://openidconnect.googleapis.com/v1/userinfo', $tok);
    $email = strtolower(trim((string)($ui['email'] ?? '')));
    $ver   = (bool)($ui['email_verified'] ?? false);
    if (!$email || !$ver) {
        http_response_code(401);
        echo json_encode(['ok' => false,'error' => 'Email non vérifié']);
        exit;
    }
    if ($allowed && !in_array($email, $allowed, true)) {
        http_response_code(403);
        echo json_encode(['ok' => false,'error' => 'Email non autorisé']);
        exit;
    }
    return ['email' => $email, 'userinfo' => $ui, 'tokeninfo' => $ti];
}

/**
 * Validate Google Application Credentials
 * @return array Validation result with status and details
 */
function validateGoogleCredentials(): array
{
    // On Cloud Run, utiliser les credentials du Service Account par défaut
    $isCloudRun = getenv('K_SERVICE') !== false; // K_SERVICE est défini sur Cloud Run
    
    if ($isCloudRun) {
        return [
            'valid' => true,
            'message' => 'Using Cloud Run default credentials',
            'code' => 'CLOUD_RUN_DEFAULT'
        ];
    }
    
    // En local, vérifier GOOGLE_APPLICATION_CREDENTIALS
    $credentialsPath = getenv('GOOGLE_APPLICATION_CREDENTIALS');
    
    if (!$credentialsPath) {
        return [
            'valid' => false,
            'error' => 'GOOGLE_APPLICATION_CREDENTIALS not set',
            'code' => 'MISSING_ENV'
        ];
    }
    
    if (!file_exists($credentialsPath)) {
        return [
            'valid' => false,
            'error' => "Credentials file does not exist: $credentialsPath",
            'code' => 'FILE_NOT_FOUND'
        ];
    }
    
    if (!is_readable($credentialsPath)) {
        return [
            'valid' => false,
            'error' => "Credentials file not readable: $credentialsPath",
            'code' => 'FILE_NOT_READABLE'
        ];
    }
    
    // Try to parse JSON
    $content = file_get_contents($credentialsPath);
    if ($content === false) {
        return [
            'valid' => false,
            'error' => "Cannot read credentials file: $credentialsPath",
            'code' => 'FILE_READ_ERROR'
        ];
    }
    
    $json = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'valid' => false,
            'error' => 'Invalid JSON in credentials file: ' . json_last_error_msg(),
            'code' => 'INVALID_JSON'
        ];
    }
    
    // Validate required fields
    $requiredFields = ['type', 'project_id', 'private_key', 'client_email'];
    foreach ($requiredFields as $field) {
        if (!isset($json[$field]) || empty($json[$field])) {
            return [
                'valid' => false,
                'error' => "Missing required field in credentials: $field",
                'code' => 'MISSING_FIELD'
            ];
        }
    }
    
    if ($json['type'] !== 'service_account') {
        return [
            'valid' => false,
            'error' => 'Invalid credentials type, expected service_account',
            'code' => 'INVALID_TYPE'
        ];
    }
    
    return [
        'valid' => true,
        'project_id' => $json['project_id'],
        'client_email' => $json['client_email'],
        'path' => $credentialsPath
    ];
}

/**
 * Get service account token with credential validation
 * @param array $scopes Required scopes
 * @return string Access token
 * @throws RuntimeException If credentials are invalid or token cannot be obtained
 */
function saToken(array $scopes): string
{
    // Validate credentials first
    $validation = validateGoogleCredentials();
    if (!$validation['valid']) {
        throw new RuntimeException("Invalid credentials: {$validation['error']}");
    }
    
    try {
        $c = ApplicationDefaultCredentials::getCredentials($scopes);
        $t = $c->fetchAuthToken();
        $a = $t['access_token'] ?? '';
        if (!$a) {
            throw new RuntimeException('Impossible d\'obtenir un access_token SA');
        }
        return $a;
    } catch (Exception $e) {
        logMessage('error', 'Failed to get service account token', [
            'error' => $e->getMessage(),
            'scopes' => $scopes
        ]);
        throw new RuntimeException('Erreur d\'authentification service account: ' . $e->getMessage());
    }
}

/**
 * Save base64 data to temporary file
 * @param string $b64 Base64 encoded data
 * @param string $ext File extension
 * @return string Temporary file path
 */
function save_base64_to_tmp(string $b64, string $ext = '.jpg'): string
{
    $raw = preg_replace('#^data:[^;]+;base64,#', '', $b64);
    $bin = base64_decode($raw, true);
    if ($bin === false) {
        throw new RuntimeException('Base64 invalide');
    }
    $p = sys_get_temp_dir() . '/docai_' . bin2hex(random_bytes(6)) . $ext;
    $result = file_put_contents($p, $bin);
    if ($result === false) {
        throw new RuntimeException('Écriture fichier tmp échouée: ' . $p);
    }
    return $p;
}

/**
 * Process document with Document AI
 * @param string $bytes Document bytes
 * @param string $mime MIME type
 * @param string $projectId GCP project ID
 * @param string $location GCP location
 * @param string $processorId Document AI processor ID
 * @return array Document AI response
 */
function docai_process_bytes(
    string $bytes,
    string $mime,
    string $projectId,
    string $location,
    string $processorId
): array {
    $token = saToken(['https://www.googleapis.com/auth/cloud-platform']);
    $processUrl = sprintf(
        'https://%s-documentai.googleapis.com/v1/projects/%s/locations/%s/processors/%s:process',
        $location,
        $projectId,
        $location,
        $processorId
    );
    $payload = ['rawDocument' => ['mimeType' => $mime,'content' => base64_encode($bytes)]];
    $r = http_json(
        'POST',
        $processUrl,
        [
            'Authorization' => "Bearer $token",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ],
        $payload
    );
    if ($r['status'] < 200 || $r['status'] >= 300) {
        throw new RuntimeException($r['text'] ?: 'Document AI error');
    }
    return $r['json']; // JSON brut DocAI
}

/**
 * Extract supplier, date, and total from Document AI response
 * @param array $doc Document AI response
 * @return array Extracted data
 */
function docai_extract_triplet(array $doc): array
{
    $entities = $doc['document']['entities'] ?? [];

    $supplier_name = null;
    $receipt_date  = null;
    $total_amount  = null;
    $currency      = null;

    $prefTotal = null;
    $prefConf = -1.0;
    $bestTotal = null;
    $bestConf = -1.0;
    $subtotal  = null;
    $subtotalConf = -1.0;
    $taxSum    = 0.0;
    $hasTax = false;

    $moneyFrom = function (array $e) {
        $nv   = $e['normalizedValue'] ?? null;
        $conf = (float)($e['confidence'] ?? 0.0);
        $val  = null;
        $cur = null;

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
                if (is_numeric($num)) {
                    $val = (float)$num;
                }
            }
        }
        return [$val, $cur, $conf];
    };

    $walk = function (array $e) use (
        &$supplier_name,
        &$receipt_date,
        &$currency,
        &$prefTotal,
        &$prefConf,
        &$bestTotal,
        &$bestConf,
        &$subtotal,
        &$subtotalConf,
        &$taxSum,
        &$hasTax,
        &$walk,
        $moneyFrom
    ) {

        $type = strtolower((string)($e['type'] ?? ''));
        $text = (string)($e['mentionText'] ?? '');

        if (
            $supplier_name === null &&
            preg_match('/supplier_name|supplier|merchant|merchant_name|vendor|retailer|store/', $type)
        ) {
            $supplier_name = $text ?: (($e['normalizedValue']['text'] ?? null) ?: $supplier_name);
        }

        if (
            $receipt_date === null &&
            preg_match('/receipt_?date|transaction_?date|start_?date|end_?date|date/', $type)
        ) {
            $nv = $e['normalizedValue'] ?? null;
            if (is_array($nv) && isset($nv['dateValue'])) {
                $d = $nv['dateValue'];
                $y = $d['year'] ?? null;
                $m = $d['month'] ?? null;
                $dd = $d['day'] ?? null;
                if ($y && $m && $dd) {
                    $receipt_date = sprintf('%04d-%02d-%02d', $y, $m, $dd);
                }
            }
            if ($receipt_date === null && $text) {
                if (preg_match('~(\d{4})[-/](\d{2})[-/](\d{2})~', $text, $mm)) {
                    $receipt_date = sprintf('%04d-%02d-%02d', $mm[1], $mm[2], $mm[3]);
                } elseif (preg_match('~(\d{2})/(\d{2})/(\d{4})~', $text, $mm)) {
                    $receipt_date = sprintf('%04d-%02d-%02d', $mm[3], $mm[2], $mm[1]);
                }
            }
        }

        if (preg_match('/total_?amount/', $type)) {
            [$v,$cur,$conf] = $moneyFrom($e);
            if ($v !== null && $conf >= $prefConf) {
                $prefTotal = $v;
                $prefConf = $conf;
                if ($cur) {
                    $currency = $cur;
                }
            }
        } elseif (preg_match('/(grand_?total|amount_?total|^total$|_total$)/', $type)) {
            [$v,$cur,$conf] = $moneyFrom($e);
            if ($v !== null && $conf > $bestConf) {
                $bestTotal = $v;
                $bestConf = $conf;
                if ($cur) {
                    $currency = $cur;
                }
            }
        } elseif (preg_match('/(subtotal|net_?amount)/', $type)) {
            [$v,$cur,$conf] = $moneyFrom($e);
            if ($v !== null && $conf > $subtotalConf) {
                $subtotal = $v;
                $subtotalConf = $conf;
                if ($cur) {
                    $currency = $cur;
                }
            }
        } elseif (preg_match('/(total_?tax_?amount|tax_?amount|vat_?amount)/', $type)) {
            [$v,$cur,$conf] = $moneyFrom($e);
            if ($v !== null) {
                $taxSum += $v;
                $hasTax = true;
                if ($cur) {
                    $currency = $cur;
                }
            }
        }

        foreach (($e['properties'] ?? []) as $c) {
            $walk($c);
        }
    };
    foreach ($entities as $e) {
        $walk($e);
    }

    if ($prefTotal !== null) {
        $total_amount = $prefTotal;
    } elseif ($bestTotal !== null) {
        $total_amount = $bestTotal;
    } elseif ($subtotal !== null && $hasTax) {
        $total_amount = $subtotal + $taxSum;
    } else {
        $total_amount = null;
    }

    return [
        'supplier_name' => $supplier_name,
        'receipt_date'  => $receipt_date,
        'total_amount'  => $total_amount,
    ];
}

/**
 * Convert column letter to index
 * @param string $col Column letter
 * @return int Column index (0-based)
 */
function col_letter_to_index(string $col): int
{
    $col = strtoupper(trim($col));
    $n = 0;
    for ($i = 0; $i < strlen($col); $i++) {
        $n = $n * 26 + (ord($col[$i]) - 64);
    }
    return max(0, $n - 1);
}

/**
 * Get sheet ID by title
 * @param string $spreadsheetId Spreadsheet ID
 * @param string $title Sheet title
 * @param string $token Access token
 * @return int Sheet ID
 */
function get_sheet_id_by_title(string $spreadsheetId, string $title, string $token): int
{
    $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode($spreadsheetId)
        . '?fields=sheets(properties(sheetId,title))';
    $r = http_json('GET', $url, ['Authorization' => "Bearer $token", 'Accept' => 'application/json']);
    if ($r['status'] < 200 || $r['status'] >= 300) {
        throw new RuntimeException('Impossible de lire la liste des feuilles: ' . ($r['text'] ?? ''));
    }
    foreach (($r['json']['sheets'] ?? []) as $s) {
        $p = $s['properties'] ?? [];
        if (($p['title'] ?? '') === $title) {
            return (int)($p['sheetId'] ?? -1);
        }
    }
    throw new RuntimeException("Feuille introuvable: $title");
}

/**
 * Send JSON response with proper headers
 * @param mixed $data Response data
 * @param int $status HTTP status code
 */
function sendJsonResponse($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

/**
 * Send error response
 * @param string $message Error message
 * @param int $status HTTP status code
 */
function sendErrorResponse($message, $status = 500)
{
    sendJsonResponse(['ok' => false, 'error' => $message], $status);
}
