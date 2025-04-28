<?php
class LanzouParser {
    private $url;
    private $pwd;
    private $type;

    public function __construct($url, $pwd = '', $type = '') {
        $this->url = $url;
        $this->pwd = $pwd;
        $this->type = $type;
    }

    public function parse() {
        $url = $this->formatUrl();
        $headers = $this->getHeaders();
        $content = $this->getPageContent($url, $headers);
        
        if ($this->isFileDeleted($content)) {
            response(false, '文件取消分享了');
        }

        $fileInfo = $this->extractFileInfo($content);
        if (!$fileInfo) {
            response(false, '解析失败');
        }

        if (strpos($content, 'function down_p(){') !== false) {
            if (empty($this->pwd)) {
                response(false, '请输入分享密码');
            }
            
            preg_match("~v3c = '(.*?)';~", $content, $sign);
            $sign = $sign[1] ?? '';
            if (strlen($sign) < 82) {
                preg_match_all("~sign\'\:\'(.*?)\'~", $content, $sign);
                $sign = $sign[1][1] ?? '';
            }
            
            preg_match("~ajaxm.php\?file=(\d+)~", $content, $ajaxm);
            $postData = [
                'action' => 'downprocess',
                'sign' => $sign,
                'p' => $this->pwd,
                'kd' => 1
            ];

            $headers[] = 'Referer: ' . $url;
            $apiUrl = "https://www.lanzoux.com/" . ($ajaxm[0] ?? '');
            $fileInfo['content'] = curlRequest($apiUrl, $postData, $headers);
        } else {
            preg_match("~<iframe.*?src=\"/(.*?)\"~", $content, $iframe);
            $iframeUrl = 'https://www.lanzoup.com/' . ($iframe[1] ?? '');
            $iframeContent = curlRequest($iframeUrl);

            preg_match("~wp_sign = '(.*?)'~", $iframeContent, $sign);
            preg_match_all("/ajaxm\.php\?file=(\d+)/", $iframeContent, $ajaxm);

            $postData = [
                'action' => 'downprocess',
                'signs' => '?ctdf',
                'sign' => $sign[1] ?? '',
                'kd' => 1
            ];

            $headers[] = 'Referer: ' . $url;
            $apiUrl = "https://www.lanzoux.com/" . ($ajaxm[0][1] ?? $ajaxm[0][0] ?? '');
            $fileInfo['content'] = curlRequest($apiUrl, $postData, $headers);
        }

        $downloadUrl = $this->getDownloadUrl($fileInfo);
        if (!$downloadUrl) {
            response(false, '获取下载链接失败');
        }

        response(true, '', [
            'name' => $fileInfo['name'],
            'size' => $fileInfo['size'],
            'time' => $fileInfo['time'],
            'url' => $downloadUrl
        ]);
    }

    private function formatUrl() {
        $parts = explode('.com/', $this->url);
        return 'https://www.lanzoup.com/' . ($parts[1] ?? '');
    }

    private function getHeaders() {
        return [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.77 Safari/537.36'
        ];
    }

    private function getPageContent($url, $headers) {
        return curlRequest($url, [], $headers);
    }

    private function isFileDeleted($content) {
        return strpos($content, '文件取消分享了') !== false;
    }

    private function extractFileInfo($content) {
        preg_match("~文件大小：(.*?)\"~", $content, $size);
        preg_match("~n_file_infos\"\>(.*?)\<~", $content, $time);
        
        if (empty($time[1])) {
            preg_match("~上传时间：</span>(.*?)\<~", $content, $time);
        }

        $name = $this->extractFileName($content);
        
        return [
            'name' => $name,
            'size' => $size[1] ?? '',
            'time' => $time[1] ?? ''
        ];
    }

    private function extractFileName($content) {
        preg_match("~<div class=\"n_box_3fn\".*?>(.*?)</div>~", $content, $name);
        if (empty($name[1])) {
            preg_match("~<title>(.*?) \-~", $content, $name);
        }
        return $name[1] ?? '';
    }

    private function getDownloadUrl($fileInfo) {
        $response = json_decode($fileInfo['content'], true);

        if ($response['url'] == '0') {
            response(false, $response['inf'] ?? '未知错误');
        }

        if (($response['zt'] ?? 0) != 1) {
            return false;
        }


        $downloadLink = $response['dom'] . '/file/' . $response['url'];
        $finalLink = getRedirectUrl($downloadLink, "https://developer.lanzoug.com", "down_ip=1; expires=Sat, 16-Nov-2019 11:42:54 GMT; path=/; domain=.baidupan.com");

        if (strpos($finalLink, 'http') === false) {
            return $downloadLink;
        }

        if (!empty($_GET['n'])) {
            preg_match("~(.*?)\?fn=(.*?)\.~", $finalLink, $rename);
            return ($rename[1] ?? $finalLink) . $_GET['n'];
        }

        return preg_replace('/pid=.*?&/', '', $finalLink);
    }
} 