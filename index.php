<?php
/**
 * @package Lanzou
 * @author iami233
 * @version 1.0.0
 * @link https://github.com/5ime/Lanzou_api
 */
header('Access-Control-Allow-Origin:*');
header('Content-type: application/json');
error_reporting(0);
$url = $_GET['url'];
$pwd = $_GET['pwd'];
if ($url != null) {
    if ($pwd == NULL) {
        $b = 'com/';
        $c = '/';
        $id = GetBetween($url, $b, $c);
        $d = 'https://www.lanzous.com/tp/' . $id;
        $lanzouo = curl($d);
        preg_match_all("/<div class=\"md\">(.*?)<span class=\"mtt\">/", $lanzouo, $name);
        preg_match_all('/时间:<\\/span>(.*?)<span class=\\"mt2\\">/', $lanzouo, $time);
        preg_match_all('/发布者:<\\/span>(.*?)<span class=\\"mt2\\">/', $lanzouo, $author);
        preg_match_all('/var cdomain = \'(.*?)\';/', $lanzouo, $down1);
        preg_match_all('/var sts = \'(.*?)\'/', $lanzouo, $down2);
        preg_match_all('/<div class=\\"md\\">(.*?)<span class=\\"mtt\\">\\((.*?)\\)<\\/span><\\/div>/', $lanzouo, $size);
        $Json = array(
            "code" => 200, 
            "data" => array(
                "name" => $name[1][0], 
                "author" => $author[1][0], 
                "time" => $time[1][0], 
                "size" => $size[2][0], 
                "url" => $down1[1][0] . $down2[1][0]
                )
        );
        $Json = json_encode($Json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        echo stripslashes($Json);
        return $Json;
    }
    $b = 'com/';
    $c = '/';
    $id = GetBetween($url, $b, $c);
    $d = 'https://www.lanzous.com/tp/' . $id;
    $lanzouo = curl($d);
    preg_match_all("/<div class=\"md\">(.*?)<span class=\"mtt\">/", $lanzouo, $name);
    preg_match_all('/时间:<\\/span>(.*?)<span class=\\"mt2\\">/', $lanzouo, $time);
    preg_match_all('/发布者:<\\/span>(.*?)<span class=\\"mt2\\">/', $lanzouo, $author);
    preg_match_all('/<div class=\\"md\\">(.*?)<span class=\\"mtt\\">\\((.*?)\\)<\\/span><\\/div>/', $lanzouo, $size);
    preg_match_all('/sign\':\'(.*?)\'/', $lanzouo, $sign);
    $post_data = array('action' => 'downprocess', 'sign' => $sign[1][0], 'p' => $pwd);
    $pwdurl = send_post('https://wwa.lanzous.com/ajaxm.php', $post_data);
    $obj = json_decode($pwdurl, true);
    $Json = array(
        "code" => 200, 
        "data" => array(
            "name" => $name[1][0], 
            "author" => $author[1][0], 
            "time" => $time[1][0], 
            "size" => $size[2][0], 
            "url" => $obj['dom'] . '/file/' . $obj['url']
        )
    );
    $Json = json_encode($Json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo stripslashes($Json);
    return $Json;
} else {
    echo '请输入蓝奏云分享的地址，如：https://www.lanzous.com/i8fclgh';
}
function send_post($url, $post_data)
{
    $postdata = http_build_query($post_data);
    $options = array('http' => array(
        'method' => 'POST',
        'header' => 'Referer: https://www.lanzous.com/\\r\\n' . 'Accept-Language:zh-CN,zh;q=0.9\\r\\n',
        'content' => $postdata,
        'timeout' => 15 * 60,
    ));
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    return $result;
}
function curl($url, $ua = 0)
{
    $ch = curl_init();
    $ip = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
    if ($ua) {
        curl_setopt($ch, CURLOPT_USERAGENT, $ua);
    } else {
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (iPhone; CPU iPhone OS 6_0 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Version/6.0 Mobile/10A5376e Safari/8536.25");
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_ENCODING, "gzip");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $ret = curl_exec($ch);
    curl_close($ch);
    return $ret;
}
function GetBetween($content, $start, $end)
{
    $r = explode($start, $content);
    if (isset($r[1])) {
        $r = explode($end, $r[1]);
        return $r[0];
    }
    return '';
}
