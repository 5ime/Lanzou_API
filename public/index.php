<?php

/**
 * @package Lanzou_API
 * @author iami233
 * @version 2.0.0
 * @link http://github.com/5ime/Lanzou_api
 */

require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../src/LanzouParser.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$url = trim($_REQUEST['url'] ?? '');
$pwd = trim($_REQUEST['pwd'] ?? '');
$type = trim($_REQUEST['type'] ?? '');

if (empty($url)) {
    response(false, '请输入需要解析的蓝奏链接');
}

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    response(false, '请输入有效的蓝奏链接');
}

if (!in_array($type, ['', 'down'])) {
    response(false, '无效的请求类型');
}

try {
    $parser = new LanzouParser($url, $pwd, $type);
    $result = $parser->parse();

    if ($type === 'down') {
        if (empty($result['data']['url'])) {
            response(false, '获取下载链接失败');
        }
        header("Location: {$result['data']['url']}");
        exit;
    }

    if (!$result['success']) {
        response(false, $result['msg'] ?? '解析失败，请稍后再试');
    }

    response(true, '解析成功', $result['data']);
} catch (Exception $e) {
    response(false, '解析过程中发生错误：' . $e->getMessage());
} 