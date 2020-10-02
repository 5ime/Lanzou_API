# LanZou_API
蓝奏云获取直链/蓝奏云直链解析

### 使用方法
- url:蓝奏云外链链接
- pwd:外链密码
- type:直接下载
<!--more-->

### 支持链接
- \*.lanzous.com
- \*.lanzoui.com
- \*.lanzoux.com

### 请求示例
无密码 https://tenapi.cn/lanzou/?url=https://www.lanzous.com/i8fclgh

有密码 https://tenapi.cn/lanzou/?url=https://www.lanzous.com/itahfehy1bc&pwd=d17u

直接下载  https://tenapi.cn/lanzou/?url=https://www.lanzous.com/itahfehy1bc&pwd=d17u&type=down

### 返回数据
~~~ json
{
  "code": 200,
  "data": {
    "name": "智云影音V3.7.3.3清爽特别版.apk ",
    "author": "智云** ",
    "time": "2020-01-03 ",
    "size": " 7.9 M ",
    "url": "https://developer78.baidupan.com/082015bb/2020/01/03/9bb45993f98d785a0775754236a8a451.apk?st=CGGAhiMMwQ2Yqzzww1YO7Q&e=1597909079&b=VOAKkwmzVbRYtwPEVuNT6gPnDLkGmAC1VVBfYVx1VmMEeFppV3kCNAKxB_blQhQfkUYwB4FawUN8C6A3lAo1W_a1ThCoMJgVV_bWGwDJVZt&fi=15880697&pid=13-70-23-99&up="
  }
}
~~~

|code| 返回值|
| ------ | ------ |
| 200 | 解析成功 |
| 201 | 链接失效 |
| 202 | 密码错误/请输入密码 |
