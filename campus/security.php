<?php
/**
 * Campus Hub — Security Firewall
 * Include this at the very top of every PHP file (after session_start + database)
 *
 * Features:
 *  1. SQLi / XSS / path-traversal pattern blocking
 *  2. Rate limiting (login attempts, general requests)
 *  3. CSRF token system
 *  4. AbuseIPDB reputation check (optional — set API key below)
 *  5. IP blocklist (DB-backed, auto + manual)
 *  6. Security headers
 */

// ══════════════════════════════════════════════════════════════
//  CONFIG — edit these
// ══════════════════════════════════════════════════════════════
define('SEC_ABUSEIPDB_KEY',    '');        // Paste your free AbuseIPDB key here (https://www.abuseipdb.com/register)
define('SEC_ABUSEIPDB_ENABLE', false);     // Set true once key is set
define('SEC_RATE_LIMIT',       2000);      // Max requests per minute per IP (increased for NAT/Campus networks)
define('SEC_LOGIN_LIMIT',      20);        // Max login failures before temp-block (per 10 min)
define('SEC_BLOCK_MINUTES',    30);        // How long to auto-block after brute-force
define('SEC_ABUSE_THRESHOLD',  50);        // AbuseIPDB confidence score to block (0-100)
define('SEC_LOG_ENABLED',      false);     // Disable general request logging to improve performance and prevent DB bloat
define('SEC_WHITELIST_IPS',    ['127.0.0.1', '::1']); // Never block these IPs

// ══════════════════════════════════════════════════════════════
//  HELPERS
// ══════════════════════════════════════════════════════════════
function sec_get_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR'] as $h) {
        if (!empty($_SERVER[$h])) {
            $candidate = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) { $ip = $candidate; break; }
        }
    }
    return $ip;
}

function sec_log(string $event, string $detail = ''): void {
    if (!SEC_LOG_ENABLED) return;
    global $conn;
    if (!$conn) return;
    $ip  = mysqli_real_escape_string($conn, sec_get_ip());
    $ua  = mysqli_real_escape_string($conn, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300));
    $uri = mysqli_real_escape_string($conn, substr($_SERVER['REQUEST_URI'] ?? '', 0, 500));
    $ev  = mysqli_real_escape_string($conn, $event);
    $det = mysqli_real_escape_string($conn, substr($detail, 0, 1000));
    @mysqli_query($conn, "INSERT INTO security_log (ip, event, user_agent, uri, detail) VALUES ('$ip','$ev','$ua','$uri','$det')");
}

function sec_block_response(string $reason = 'Access denied'): void {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    die('<!DOCTYPE html><html><head><title>403 Forbidden</title>
<style>body{font-family:sans-serif;background:#0f172a;color:#f1f5f9;display:flex;align-items:center;
justify-content:center;min-height:100vh;margin:0;}
.box{text-align:center;padding:40px;background:#1e293b;border-radius:16px;max-width:400px;}
h1{color:#ef4444;font-size:48px;margin:0 0 10px}h2{margin:0 0 10px}p{color:#94a3b8;}</style>
</head><body><div class="box"><h1>🛡</h1><h2>Access Blocked</h2>
<p>' . htmlspecialchars($reason) . '</p>
<p style="font-size:12px;margin-top:20px;">Campus Hub Security · ' . date('d M Y H:i') . '</p>
</div></body></html>');
}

// ══════════════════════════════════════════════════════════════
//  1. SECURITY HEADERS
// ══════════════════════════════════════════════════════════════
function sec_send_headers(): void {
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self';");
        header("Access-Control-Allow-Origin: *");
        // Only add HSTS if on HTTPS
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}

// ══════════════════════════════════════════════════════════════
//  2. DB BLOCKLIST CHECK
// ══════════════════════════════════════════════════════════════
function sec_check_blocklist(string $ip): void {
    global $conn;
    if (!$conn || in_array($ip, SEC_WHITELIST_IPS)) return;
    $esc = mysqli_real_escape_string($conn, $ip);
    $r = @mysqli_fetch_assoc(@mysqli_query($conn,
        "SELECT reason, expires_at FROM blocked_ips WHERE ip='$esc'
         AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1"));
    if ($r) {
        sec_log('blocked_ip', 'Blocked IP tried to access: ' . $r['reason']);
        sec_block_response('Your IP has been blocked. Reason: ' . htmlspecialchars($r['reason']));
    }
}

// ══════════════════════════════════════════════════════════════
//  3. ABUSEIPDB REPUTATION CHECK (optional)
// ══════════════════════════════════════════════════════════════
function sec_check_abuseipdb(string $ip): void {
    if (!SEC_ABUSEIPDB_ENABLE || empty(SEC_ABUSEIPDB_KEY)) return;
    if (in_array($ip, SEC_WHITELIST_IPS)) return;
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) return; // skip private IPs

    // Cache result in session to avoid checking on every page load
    if (!isset($_SESSION)) @session_start();
    $cache_key = 'abuseipdb_' . md5($ip);
    if (isset($_SESSION[$cache_key])) return; // already checked this session

    $url = 'https://api.abuseipdb.com/api/v2/check?' . http_build_query([
        'ipAddress'    => $ip,
        'maxAgeInDays' => 30,
    ]);
    $ctx = stream_context_create(['http' => [
        'timeout' => 3,
        'header'  => "Key: " . SEC_ABUSEIPDB_KEY . "\r\nAccept: application/json\r\n",
    ]]);
    $resp = @file_get_contents($url, false, $ctx);
    if (!$resp) { $_SESSION[$cache_key] = true; return; } // API down, allow through

    $data = json_decode($resp, true);
    $score = $data['data']['abuseConfidenceScore'] ?? 0;
    $_SESSION[$cache_key] = true; // mark as checked

    if ($score >= SEC_ABUSE_THRESHOLD) {
        global $conn;
        // Auto-add to block list for 24 hours
        if ($conn) {
            $esc = mysqli_real_escape_string($conn, $ip);
            @mysqli_query($conn,
                "INSERT IGNORE INTO blocked_ips (ip, reason, blocked_by, expires_at)
                 VALUES ('$esc', 'AbuseIPDB score: $score', 'auto', DATE_ADD(NOW(), INTERVAL 24 HOUR))");
        }
        sec_log('abuseipdb_block', "Score: $score");
        sec_block_response("Suspicious IP detected (score: $score). Access denied.");
    }
}

// ══════════════════════════════════════════════════════════════
//  4. RATE LIMITING
// ══════════════════════════════════════════════════════════════
function sec_rate_limit(string $ip): void {
    global $conn;
    if (!$conn || in_array($ip, SEC_WHITELIST_IPS)) return;
    $esc  = mysqli_real_escape_string($conn, $ip);
    $cnt  = (int)(@mysqli_fetch_assoc(@mysqli_query($conn,
        "SELECT COUNT(*) as c FROM security_log WHERE ip='$esc' AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)"))['c'] ?? 0);
    if ($cnt > SEC_RATE_LIMIT) {
        // Auto-block temporarily
        @mysqli_query($conn,
            "INSERT IGNORE INTO blocked_ips (ip, reason, blocked_by, expires_at)
             VALUES ('$esc', 'Rate limit exceeded ($cnt req/min)', 'auto', DATE_ADD(NOW(), INTERVAL " . SEC_BLOCK_MINUTES . " MINUTE))");
        sec_log('rate_limit', "Requests: $cnt/min");
        sec_block_response('Too many requests. Try again in ' . SEC_BLOCK_MINUTES . ' minutes.');
    }
}

// ══════════════════════════════════════════════════════════════
//  5. WAF — ATTACK PATTERN DETECTION
// ══════════════════════════════════════════════════════════════
function sec_waf(): void {
    $sqli_patterns = [
        '/(\bUNION\b.+\bSELECT\b)/i',
        '/(\bSELECT\b.+\bFROM\b.+\bWHERE\b)/i',
        '/(\bINSERT\b.+\bINTO\b)/i',
        '/(\bDROP\b.+\b(TABLE|DATABASE)\b)/i',
        '/(\bDELETE\b.+\bFROM\b)/i',
        '/(\bUPDATE\b.+\bSET\b)/i',
        "/('|(\\%27))(\\s)*(or|OR|Or)(\\s)*('|(\\%27)|(\\%22)|\"|1)/",
        '/(-{2}|\#|\*\/)/',
        '/(SLEEP\s*\(|BENCHMARK\s*\(|WAITFOR\s+DELAY)/i',
    ];
    $xss_patterns = [
        '/<script[\s>]/i',
        '/javascript\s*:/i',
        '/on\w+\s*=\s*["\']?[^"\']*["\']?/i',
        '/<\s*iframe/i',
        '/<\s*object/i',
        '/document\s*\.\s*(cookie|write|location)/i',
        '/eval\s*\(/i',
        '/base64_decode\s*\(/i',
    ];
    $path_patterns = [
        '/\.\.(\/|\\\\)/',
        '/\.(htaccess|htpasswd|env|config\.php|database\.php)/i',
        '/\/etc\/(passwd|shadow|hosts)/i',
    ];

    $inputs = array_merge(
        array_values($_GET ?? []),
        array_values($_POST ?? []),
        array_values(array_map(fn($c) => $c['value'] ?? '', $_COOKIE ?? []))
    );

    foreach ($inputs as $val) {
        if (!is_string($val)) continue;
        $decoded = html_entity_decode(urldecode($val));

        foreach ($sqli_patterns as $p) {
            if (preg_match($p, $decoded)) {
                sec_log('sqli_attempt', "Pattern: $p | Value: " . substr($val, 0, 200));
                sec_block_response('Request blocked: Invalid input detected.');
            }
        }
        foreach ($xss_patterns as $p) {
            if (preg_match($p, $decoded)) {
                sec_log('xss_attempt', "Pattern: $p | Value: " . substr($val, 0, 200));
                sec_block_response('Request blocked: Potentially harmful input detected.');
            }
        }
        foreach ($path_patterns as $p) {
            if (preg_match($p, $decoded)) {
                sec_log('path_traversal', "Pattern: $p | Value: " . substr($val, 0, 200));
                sec_block_response('Request blocked: Access denied.');
            }
        }
    }
}

// ══════════════════════════════════════════════════════════════
//  6. CSRF TOKEN SYSTEM
// ══════════════════════════════════════════════════════════════
function sec_csrf_token(): string {
    if (!isset($_SESSION)) @session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function sec_csrf_input(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(sec_csrf_token()) . '">';
}

function sec_csrf_verify(): bool {
    if (!isset($_SESSION)) @session_start();
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ══════════════════════════════════════════════════════════════
//  7. LOGIN BRUTE-FORCE PROTECTION  (call from index.php on login fail)
// ══════════════════════════════════════════════════════════════
function sec_record_login_fail(string $ip): void {
    global $conn;
    if (!$conn) return;
    sec_log('login_fail', '');
    $esc = mysqli_real_escape_string($conn, $ip);
    $fails = (int)(@mysqli_fetch_assoc(@mysqli_query($conn,
        "SELECT COUNT(*) as c FROM security_log
         WHERE ip='$esc' AND event='login_fail' AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)"))['c'] ?? 0);
    if ($fails >= SEC_LOGIN_LIMIT) {
        @mysqli_query($conn,
            "INSERT IGNORE INTO blocked_ips (ip, reason, blocked_by, expires_at)
             VALUES ('$esc', 'Brute-force: $fails failed logins in 10min', 'auto',
             DATE_ADD(NOW(), INTERVAL " . SEC_BLOCK_MINUTES . " MINUTE))");
        sec_log('brute_force_block', "Fails: $fails");
    }
}

// ══════════════════════════════════════════════════════════════
//  RUN ALL CHECKS (called once on include)
// ══════════════════════════════════════════════════════════════
sec_send_headers();
$_sec_ip = sec_get_ip();
sec_check_blocklist($_sec_ip);
sec_waf();
sec_rate_limit($_sec_ip);
sec_check_abuseipdb($_sec_ip);
// Log this request (for rate limiting tracking - lightweight)
if (SEC_LOG_ENABLED && isset($conn)) {
    $__sip = mysqli_real_escape_string($conn, $_sec_ip);
    $__su  = mysqli_real_escape_string($conn, substr($_SERVER['REQUEST_URI'] ?? '', 0, 500));
    @mysqli_query($conn, "INSERT INTO security_log (ip, event, uri) VALUES ('$__sip','request','$__su')");
}
unset($_sec_ip, $__sip, $__su);