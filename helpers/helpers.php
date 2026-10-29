<?php

/**
 * HTTP helpers for Lanzou_API.
 * Prefer PHP cURL; fall back to system curl when PHP cURL fails.
 */

function curlRequest($url, $postData = [], $headers = [], $options = [])
{
    // Windows PHP cURL often segfaults on HTTPS; prefer system curl there.
    if (shouldPreferShellCurl()) {
        $result = shellCurlRequest($url, $postData, $headers, $options);
        if (is_string($result) && strpos($result, 'Curl Error:') !== 0) {
            return $result;
        }
    }

    $result = phpCurlRequest($url, $postData, $headers, $options);
    if ($result !== false) {
        return $result;
    }

    return shellCurlRequest($url, $postData, $headers, $options);
}

function shouldPreferShellCurl()
{
    return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}

function phpCurlRequest($url, $postData = [], $headers = [], $options = [])
{
    if (!function_exists('curl_init')) {
        return false;
    }

    $ch = curl_init($url);
    $curlOpts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => empty($options['no_follow']),
        CURLOPT_TIMEOUT => isset($options['timeout']) ? (int)$options['timeout'] : 15,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => normalizeHeaders($headers),
    ];

    if (!empty($options['referer'])) {
        $curlOpts[CURLOPT_REFERER] = $options['referer'];
    }
    if (!empty($options['cookie'])) {
        $curlOpts[CURLOPT_COOKIE] = $options['cookie'];
    }
    if (!empty($options['cookie_jar'])) {
        $curlOpts[CURLOPT_COOKIEJAR] = $options['cookie_jar'];
        $curlOpts[CURLOPT_COOKIEFILE] = $options['cookie_jar'];
    }
    if (!empty($options['header_out'])) {
        $curlOpts[CURLOPT_HEADER] = true;
    }
    if (!empty($options['no_follow'])) {
        $curlOpts[CURLOPT_FOLLOWLOCATION] = false;
    }

    if (!empty($postData)) {
        $curlOpts[CURLOPT_POST] = true;
        $curlOpts[CURLOPT_POSTFIELDS] = is_array($postData) ? http_build_query($postData) : $postData;
    }

    curl_setopt_array($ch, $curlOpts);
    $result = @curl_exec($ch);
    $errno = curl_errno($ch);
    curl_close($ch);

    if ($result === false || $errno) {
        return false;
    }

    return $result;
}

function shellCurlRequest($url, $postData = [], $headers = [], $options = [])
{
    $curlBin = findCurlBinary();
    if ($curlBin === null) {
        return 'Curl Error: curl unavailable';
    }

    $cmd = [
        $curlBin,
        '-s',
        '--compressed',
        '-k',
        '--connect-timeout', '10',
        '--max-time', isset($options['timeout']) ? (string)(int)$options['timeout'] : '15',
    ];

    if (empty($options['no_follow'])) {
        $cmd[] = '-L';
    }

    if (!empty($options['header_out'])) {
        $cmd[] = '-i';
    }

    foreach (normalizeHeaders($headers) as $header) {
        $cmd[] = '-H';
        $cmd[] = $header;
    }

    if (!empty($options['referer'])) {
        $cmd[] = '-e';
        $cmd[] = $options['referer'];
    }

    if (!empty($options['cookie'])) {
        $cmd[] = '-b';
        $cmd[] = $options['cookie'];
    }

    if (!empty($options['cookie_jar'])) {
        $cmd[] = '-c';
        $cmd[] = $options['cookie_jar'];
        $cmd[] = '-b';
        $cmd[] = $options['cookie_jar'];
    }

    if (!empty($postData)) {
        $cmd[] = '-X';
        $cmd[] = 'POST';
        $cmd[] = '-d';
        $cmd[] = is_array($postData) ? http_build_query($postData) : $postData;
    }

    $cmd[] = $url;

    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open(buildCommandLine($cmd), $descriptor, $pipes);
    if (!is_resource($process)) {
        return 'Curl Error: failed to start curl';
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);

    if ($code !== 0 && $stdout === '') {
        return 'Curl Error: ' . trim($stderr);
    }

    return $stdout;
}

function createCookieJar()
{
    return tempnam(sys_get_temp_dir(), 'lzcookie_');
}

function appendCookieToJar($cookieJar, $host, $name, $value)
{
    if (!$cookieJar || !$host || !$name) {
        return;
    }

    $lines = [
        "# Netscape HTTP Cookie File",
        sprintf("%s\tFALSE\t/\tFALSE\t0\t%s\t%s", $host, $name, $value),
    ];

    $parts = explode('.', $host);
    if (count($parts) >= 2) {
        $domain = '.' . implode('.', array_slice($parts, -2));
        $lines[] = sprintf("%s\tTRUE\t/\tFALSE\t0\t%s\t%s", $domain, $name, $value);
    }

    file_put_contents($cookieJar, implode("\n", $lines) . "\n", FILE_APPEND);
}

/**
 * Aliyun / ESA acw_sc__v2 challenge solver.
 */
function solveAcwScV2($html)
{
    if (!preg_match("/arg1='([0-9A-Z]+)'/", $html, $m)) {
        return null;
    }

    $arg1 = $m[1];
    $box = [15, 35, 29, 24, 33, 16, 1, 38, 10, 9, 19, 31, 40, 27, 22, 23, 25, 13, 6, 11, 39, 18, 20, 8, 14, 21, 32, 26, 2, 30, 7, 4, 17, 5, 3, 28, 34, 37, 12, 36];
    $out = array_fill(0, count($box), '');
    $len = strlen($arg1);
    for ($i = 0; $i < $len; $i++) {
        for ($j = 0; $j < count($box); $j++) {
            if ($box[$j] === $i + 1) {
                $out[$j] = $arg1[$i];
            }
        }
    }
    $unsboxed = implode('', $out);
    $key = '3000176000856006061501533003690027800375';
    $res = '';
    $max = min(strlen($unsboxed), strlen($key));
    for ($i = 0; $i < $max; $i += 2) {
        $v = dechex(hexdec(substr($unsboxed, $i, 2)) ^ hexdec(substr($key, $i, 2)));
        $res .= strlen($v) === 1 ? '0' . $v : $v;
    }

    return $res;
}

function fetchHtml($url, $headers = [], $options = [])
{
    if (empty($options['cookie_jar'])) {
        $options['cookie_jar'] = createCookieJar();
    }

    $html = curlRequest($url, [], $headers, $options);
    if (!is_string($html) || strpos($html, 'Curl Error:') === 0) {
        return $html;
    }

    // Strip optional header block if present
    $body = $html;
    if (!empty($options['header_out']) && preg_match('/\r?\n\r?\n(.*)$/s', $html, $m)) {
        $body = $m[1];
    }

    if (strpos($body, 'arg1=') !== false && strpos($body, 'acw_sc__v2') !== false) {
        $cookie = solveAcwScV2($body);
        if ($cookie) {
            $host = parse_url($url, PHP_URL_HOST);
            appendCookieToJar($options['cookie_jar'], $host, 'acw_sc__v2', $cookie);
            $html = curlRequest($url, [], $headers, $options);
        }
    }

    return $html;
}

function getRedirectUrl($url, $referer = '', $cookieJar = '')
{
    if ($cookieJar === '') {
        $cookieJar = createCookieJar();
    }

    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ];

    $options = [
        'no_follow' => true,
        'header_out' => true,
        'referer' => $referer,
        'cookie_jar' => $cookieJar,
        'timeout' => 15,
    ];

    // Prefer shell curl for reliable header capture on broken PHP SSL builds.
    $raw = shellCurlRequest($url, [], $headers, $options);
    if (strpos($raw, 'Curl Error:') === 0) {
        $raw = phpCurlRequest($url, [], $headers, $options);
        if ($raw === false) {
            return '';
        }
    }

    for ($attempt = 0; $attempt < 2; $attempt++) {
        if (preg_match('/(?im)^Location:\s*(.+)$/', $raw, $loc)) {
            return trim($loc[1]);
        }

        $parts = preg_split("/\r?\n\r?\n/", $raw, 2);
        $body = isset($parts[1]) ? $parts[1] : $raw;

        if (strpos($body, 'arg1=') === false) {
            break;
        }

        $cookie = solveAcwScV2($body);
        if (!$cookie) {
            break;
        }

        $host = parse_url($url, PHP_URL_HOST);
        appendCookieToJar($cookieJar, $host, 'acw_sc__v2', $cookie);
        $raw = shellCurlRequest($url, [], $headers, $options);
        if (strpos($raw, 'Curl Error:') === 0) {
            $raw = phpCurlRequest($url, [], $headers, $options);
            if ($raw === false) {
                return '';
            }
        }
    }

    if (preg_match('/(?im)^Location:\s*(.+)$/', $raw, $loc)) {
        return trim($loc[1]);
    }

    return $url;
}

function normalizeHeaders($headers)
{
    $normalized = [];
    $hasUa = false;
    foreach ((array)$headers as $header) {
        if ($header === '' || $header === null) {
            continue;
        }
        $normalized[] = $header;
        if (stripos($header, 'User-Agent:') === 0) {
            $hasUa = true;
        }
    }
    if (!$hasUa) {
        array_unshift(
            $normalized,
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        );
    }
    return $normalized;
}

function findCurlBinary()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached ?: null;
    }

    $candidates = ['curl', 'curl.exe'];
    foreach ($candidates as $bin) {
        $out = [];
        $code = 1;
        @exec(escapeshellarg($bin) . ' --version 2>&1', $out, $code);
        if ($code === 0) {
            $cached = $bin;
            return $bin;
        }
    }

    $cached = '';
    return null;
}

function buildCommandLine(array $args)
{
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        return implode(' ', array_map('escapeshellarg', $args));
    }

    $parts = [];
    foreach ($args as $arg) {
        $arg = (string)$arg;
        if ($arg !== '' && preg_match('/^[A-Za-z0-9_.:\\/@%+=,-]+$/', $arg)) {
            $parts[] = $arg;
        } else {
            $parts[] = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $arg) . '"';
        }
    }
    return implode(' ', $parts);
}

function response($success, $msg = '', $data = [])
{
    $result = [
        'code' => $success ? 200 : 400,
        'msg' => $success ? ($msg ?: '解析成功') : ($msg ?: '解析失败'),
        'data' => $success ? $data : null,
    ];

    die(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
