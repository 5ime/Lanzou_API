<?php
function curlRequest($url, $postData = [], $headers = [])
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    if (!empty($postData)) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }

    $result = curl_exec($ch);
    if ($result === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return 'Curl Error: ' . $error;
    }
    curl_close($ch);
    return $result;
}

function getRedirectUrl($url, $referer, $cookie)
{
    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Encoding: gzip, deflate',
        'Accept-Language: zh-CN,zh;q=0.9',
        'Cache-Control: no-cache',
        'Connection: keep-alive',
        'Pragma: no-cache',
        'Upgrade-Insecure-Requests: 1',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.77 Safari/537.36',
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_REFERER => $referer,
        CURLOPT_COOKIE => $cookie,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 10,
    ]);

    curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    return $info['redirect_url'] ?? '';
}

function response($success, $msg = '', $data = [])
{
    $result = [
        'code' => $success ? 200 : 400,
        'msg' => $success ? ($msg ?: '解析成功') : ($msg ?: '解析失败'),
        'data' => $success ? $data : null
    ];

    die(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
} 