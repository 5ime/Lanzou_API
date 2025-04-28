# LanZou_API

一个用于解析蓝奏云分享链接的 API 服务，支持获取文件信息和下载链接。

## 功能特性

- 支持解析蓝奏云分享链接
- 支持带密码的分享链接
- 支持获取文件详细信息（文件名、大小、上传时间）
- 支持直接获取下载链接

## 安装说明

1. 克隆项目到本地：
```bash
git clone https://github.com/5ime/Lanzou_API
```

2. 确保服务器环境满足以下要求：
- PHP 7.0 或更高版本
- 启用 cURL 扩展
- 支持 HTTPS 请求

## 使用方法

### 基本使用

通过 HTTP GET 请求访问 `public/index.php`，支持以下参数：

- `url`：蓝奏云分享链接（必填）
- `pwd`：提取码（如果有密码则必填）
- `type`：值为 "down" 时直接跳转下载，否则返回下载链接

### 示例请求

1. 获取文件信息：
```
GET /LanZou_API/public/index.php?url=https://www.lanzoui.com/xxxxxx
```

2. 带密码的分享链接：
```
GET /LanZou_API/public/index.php?url=https://www.lanzoui.com/xxxxxx&pwd=xxxx
```

3. 直接下载：
```
GET /LanZou_API/public/index.php?url=https://www.lanzoui.com/xxxxxx&pwd=xxxx&type=down
```

### 返回格式

成功响应：
```json
{
  "code": 200,
  "msg": "解析成功",
  "data": {
    "name": "文件名",
    "size": "文件大小",
    "time": "上传时间",
    "url": "下载链接"
  }
}
```

错误响应：
```json
{
  "code": 400,
  "msg": "错误信息",
  "data": null
}
```

## 注意事项

1. 请确保服务器能够正常访问蓝奏云网站
2. 建议在服务器端设置适当的请求频率限制
3. 本API仅供学习交流使用，请勿用于商业用途
4. 使用过程中如遇到问题，请检查：
   - 分享链接是否有效
   - 提取码是否正确
   - 文件是否被删除
   - 服务器网络连接是否正常