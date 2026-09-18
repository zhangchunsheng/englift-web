<?php
/**
 * EngLift 词典 API 代理
 * 转发 https://api.dictionaryapi.dev（浏览器直连有 CORS 限制，服务端转发解决）
 * 同源调用，无跨域问题；带 24 小时文件缓存，限制输入防止滥用。
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=86400');

$word = isset($_GET['word']) ? trim($_GET['word']) : '';

// 仅允许字母、连字符、撇号，最长 45 字符
if ($word === '' || !preg_match("/^[a-zA-Z'\\-]{1,45}$/", $word)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid word']);
    exit;
}
$word = strtolower($word);

// 文件缓存（24 小时）
$cacheDir = sys_get_temp_dir() . '/englift-dict-cache';
$cacheFile = $cacheDir . '/' . md5($word) . '.json';
if (is_file($cacheFile) && time() - filemtime($cacheFile) < 86400) {
    echo file_get_contents($cacheFile);
    exit;
}

$url = 'https://api.dictionaryapi.dev/api/v2/entries/en/' . urlencode($word);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_USERAGENT => 'EngLift/1.0 (https://englift.luomor.com)',
    CURLOPT_SSL_VERIFYPEER => true,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($body === false || $code !== 200) {
    http_response_code($code === 404 ? 404 : 502);
    echo json_encode(['error' => $code === 404 ? 'word not found' : 'upstream error']);
    exit;
}

if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
@file_put_contents($cacheFile, $body);
echo $body;
