<?php

class LanzouParser
{
    private $url;
    private $pwd;
    private $type;
    private $cookieJar;
    private $headers;

    public function __construct($url, $pwd = '', $type = '')
    {
        $this->url = $url;
        $this->pwd = $pwd;
        $this->type = $type;
        $this->cookieJar = createCookieJar();
        $this->headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
        ];
    }

    public function parse()
    {
        $url = $this->formatUrl();
        $content = $this->getPage($url);

        if (!is_string($content) || strpos($content, 'Curl Error:') === 0) {
            return $this->fail('网络请求失败');
        }

        if ($this->isFileDeleted($content)) {
            return $this->fail('文件取消分享了');
        }

        if ($this->isFolder($url, $content)) {
            return $this->parseFolder($url, $content);
        }

        if ($this->needsPassword($content)) {
            return $this->parsePasswordFile($url, $content);
        }

        return $this->parseNormalFile($url, $content);
    }

    private function formatUrl()
    {
        $url = trim($this->url);
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }
        return $url;
    }

    private function getPage($url)
    {
        return fetchHtml($url, $this->headers, [
            'cookie_jar' => $this->cookieJar,
            'timeout' => 15,
        ]);
    }

    private function postAjax($apiUrl, $postData, $referer)
    {
        $headers = $this->headers;
        $headers[] = 'Referer: ' . $referer;
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';

        return curlRequest($apiUrl, $postData, $headers, [
            'cookie_jar' => $this->cookieJar,
            'referer' => $referer,
            'timeout' => 15,
        ]);
    }

    private function isFileDeleted($content)
    {
        return strpos($content, '文件取消分享了') !== false
            || strpos($content, '文件不存在') !== false;
    }

    private function isFolder($url, $content)
    {
        if (strpos($content, 'filemoreajax.php') !== false) {
            return true;
        }
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        return (bool)preg_match('~/b[0-9a-zA-Z]+/?$~', $path);
    }

    private function needsPassword($content)
    {
        return strpos($content, 'function down_p()') !== false
            || strpos($content, 'id="pwd"') !== false
            || strpos($content, 'passwddiv') !== false;
    }

    private function parsePasswordFile($url, $content)
    {
        if ($this->pwd === '') {
            return $this->fail('请输入分享密码');
        }

        $sign = $this->extractSign($content, ['isngis', 'skdklds', 'v3c']);
        if ($sign === '') {
            preg_match_all("~'sign'\s*:\s*'([^']+)'~", $content, $signs);
            foreach ($signs[1] ?? [] as $candidate) {
                if (strlen($candidate) > 10) {
                    $sign = $candidate;
                }
            }
        }

        $fileId = $this->extractFileId($content);
        if ($sign === '' || $fileId === '') {
            return $this->fail('解析失败：无法提取签名参数');
        }

        $apiUrl = $this->buildAjaxUrl($content, $fileId);
        $response = $this->postAjax($apiUrl, [
            'action' => 'downprocess',
            'sign' => $sign,
            'p' => $this->pwd,
            'kd' => 1,
        ], $url);

        return $this->buildFileResult($url, $content, $response);
    }

    private function parseNormalFile($url, $content)
    {
        preg_match('~<iframe[^>]+src="(/[^"]+)"~i', $content, $iframe);
        if (empty($iframe[1])) {
            return $this->fail('解析失败：未找到下载页面');
        }

        $iframeUrl = $this->joinUrl($url, $iframe[1]);
        $iframeContent = $this->getPage($iframeUrl);
        if (!is_string($iframeContent) || strpos($iframeContent, 'Curl Error:') === 0) {
            return $this->fail('获取下载页面失败');
        }

        preg_match("~var wp_sign = '([^']*)'~", $iframeContent, $wpSign);
        preg_match("~var ajaxdata = '([^']*)'~", $iframeContent, $ajaxdata);
        preg_match("~var aihidcms = '([^']*)'~", $iframeContent, $websign);

        $fileId = $this->extractFileId($iframeContent);
        if (empty($wpSign[1]) || $fileId === '') {
            return $this->fail('解析失败：无法提取下载参数');
        }

        $apiUrl = $this->buildAjaxUrl($iframeContent, $fileId);
        $signs = $ajaxdata[1] ?? '?ctdf';
        $response = $this->postAjax($apiUrl, [
            'action' => 'downprocess',
            'websignkey' => $signs,
            'signs' => $signs,
            'sign' => $wpSign[1],
            'websign' => $websign[1] ?? '',
            'kd' => 1,
            'ves' => 1,
        ], $url);

        return $this->buildFileResult($url, $content, $response);
    }

    private function parseFolder($url, $content)
    {
        if (!$this->extractFolderParams($content, $params)) {
            return $this->fail('解析失败：无法提取文件夹参数');
        }

        if (!empty($params['need_pwd']) && $this->pwd === '') {
            return $this->fail('请输入分享密码');
        }

        $apiUrl = $this->joinUrl($url, '/filemoreajax.php?file=' . $params['fid']);
        $post = [
            'lx' => $params['lx'],
            'fid' => $params['fid'],
            'uid' => $params['uid'],
            'pg' => 1,
            'rep' => '0',
            't' => $params['t'],
            'k' => $params['k'],
            'up' => 1,
            'ls' => 1,
            'pwd' => $this->pwd,
        ];
        if ($params['puid'] !== '') {
            $post['puid'] = $params['puid'];
        }

        $raw = $this->postAjax($apiUrl, $post, $url);
        $json = json_decode($raw, true);
        if (!is_array($json) || ($json['zt'] ?? 0) != 1) {
            return $this->fail($json['info'] ?? $json['msg'] ?? '获取文件夹列表失败');
        }

        $list = [];
        foreach ((array)($json['text'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = $item['id'] ?? $item['id_'] ?? '';
            $list[] = [
                'id' => $id,
                'name' => $item['name_all'] ?? $item['name'] ?? '',
                'size' => $item['size'] ?? '',
                'time' => $item['time'] ?? '',
                'url' => $id !== '' ? $this->joinUrl($url, '/' . ltrim($id, '/')) : '',
            ];
        }

        preg_match('~<title>(.*?)</title>~s', $content, $title);
        $name = trim(html_entity_decode($title[1] ?? '文件夹'));
        $name = preg_replace('~\s*-\s*蓝奏云.*$~u', '', $name);

        return [
            'success' => true,
            'msg' => '解析成功',
            'data' => [
                'type' => 'folder',
                'name' => $name,
                'list' => $list,
            ],
        ];
    }

    private function extractFolderParams($content, &$params)
    {
        $params = [
            'fid' => '',
            'uid' => '',
            'lx' => '2',
            't' => '',
            'k' => '',
            'puid' => '',
            'need_pwd' => false,
        ];

        if (!preg_match('~filemoreajax\.php\?file=(\d+)~', $content, $fid)) {
            return false;
        }
        $params['fid'] = $fid[1];

        if (preg_match("~'uid'\s*:\s*'?(\d+)'?~", $content, $uid)) {
            $params['uid'] = $uid[1];
        }
        if (preg_match("~'lx'\s*:\s*(\d+)~", $content, $lx)) {
            $params['lx'] = $lx[1];
        }
        if (preg_match("~'puid'\s*:\s*'([^']*)'~", $content, $puid)) {
            $params['puid'] = $puid[1];
        }

        $tVar = preg_match("~'t'\s*:\s*(\w+)~", $content, $tm) ? $tm[1] : '';
        $kVar = preg_match("~'k'\s*:\s*(\w+)~", $content, $km) ? $km[1] : '';
        if ($tVar && preg_match("~var\s+{$tVar}\s*=\s*'([^']*)'~", $content, $tv)) {
            $params['t'] = $tv[1];
        }
        if ($kVar && preg_match("~var\s+{$kVar}\s*=\s*'([^']*)'~", $content, $kv)) {
            $params['k'] = $kv[1];
        }

        $params['need_pwd'] = (strpos($content, 'passwddiv') !== false && strpos($content, "'pwd':") !== false)
            || (bool)preg_match("~'pwd'\s*:\s*pwd~", $content);

        return $params['fid'] !== '' && $params['t'] !== '' && $params['k'] !== '';
    }

    private function buildFileResult($shareUrl, $pageContent, $ajaxRaw)
    {
        $response = json_decode($ajaxRaw, true);
        if (!is_array($response)) {
            return $this->fail('获取下载链接失败');
        }

        if (($response['url'] ?? '') === '0' || ($response['zt'] ?? 0) != 1) {
            return $this->fail($response['inf'] ?? $response['info'] ?? '未知错误');
        }

        $downloadLink = rtrim($response['dom'], '/') . '/file/' . $response['url'];
        $finalLink = getRedirectUrl($downloadLink, $shareUrl, $this->cookieJar);
        if ($finalLink === '' || strpos($finalLink, 'http') !== 0) {
            $finalLink = $downloadLink;
        }

        if (!empty($_GET['n'])) {
            $finalLink = preg_replace('~\?fn=[^&]*~', '?fn=' . rawurlencode($_GET['n']), $finalLink);
        } else {
            $finalLink = preg_replace('/pid=.*?&/', '', $finalLink);
        }

        $info = $this->extractFileInfo($pageContent);
        $name = $response['inf'] ?? '';
        if (!is_string($name) || $name === '' || $name === '文件' || $name === '0') {
            $name = $info['name'];
        }
        if ($name === '' || $name === '文件') {
            if (preg_match('~[?&]fn=([^&]+)~', $finalLink, $fn)) {
                $name = rawurldecode($fn[1]);
            }
        }

        return [
            'success' => true,
            'msg' => '解析成功',
            'data' => [
                'name' => $name,
                'size' => $info['size'],
                'time' => $info['time'],
                'url' => $finalLink,
            ],
        ];
    }

    private function extractFileInfo($content)
    {
        $size = '';
        if (preg_match('~文件大小：([^"<]+)~', $content, $m)) {
            $size = trim($m[1]);
        } elseif (preg_match('~n_filesize">大小：([^<]+)~', $content, $m)) {
            $size = trim($m[1]);
        } elseif (preg_match('~meta name="description" content="文件大小：([^"]+)"~', $content, $m)) {
            $size = trim($m[1]);
        }

        $time = '';
        if (preg_match('~n_file_infos">(.*?)<~', $content, $m)) {
            $time = trim($m[1]);
        } elseif (preg_match('~上传时间：</span>(.*?)<~', $content, $m)) {
            $time = trim($m[1]);
        }

        return [
            'name' => $this->extractFileName($content),
            'size' => $size,
            'time' => $time,
        ];
    }

    private function extractFileName($content)
    {
        if (preg_match('~<div class="n_box_3fn"[^>]*>(.*?)</div>~', $content, $m)) {
            $name = trim(strip_tags($m[1]));
            if ($name !== '' && $name !== '文件') {
                return $name;
            }
        }
        if (preg_match('~<title>(.*?) \-~', $content, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    private function extractSign($content, array $vars)
    {
        $sign = '';
        foreach ($vars as $var) {
            if (preg_match_all("~var\s+{$var}\s*=\s*'([^']*)'~", $content, $ms)) {
                foreach ($ms[1] as $candidate) {
                    if (strlen($candidate) > 10) {
                        $sign = $candidate;
                    }
                }
            }
        }
        return $sign;
    }

    private function extractFileId($content)
    {
        if (preg_match('~ajax(?:file|m)\.php\?file=(\d+)~', $content, $m)) {
            return $m[1];
        }
        return '';
    }

    private function buildAjaxUrl($content, $fileId)
    {
        if (preg_match("~https://apifile\.[a-z0-9.]+/ajaxfile\.php\?file={$fileId}~", $content, $m)) {
            return $m[0];
        }
        return 'https://apifile.woozooo.com/ajaxfile.php?file=' . $fileId;
    }

    private function joinUrl($base, $path)
    {
        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }
        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        return $scheme . '://' . $host . '/' . ltrim($path, '/');
    }

    private function fail($msg)
    {
        return [
            'success' => false,
            'msg' => $msg,
            'data' => null,
        ];
    }
}
