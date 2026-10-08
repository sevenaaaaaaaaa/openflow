#!/usr/bin/env python3
# 部署 uploads-r2 Worker：/uploads/* 直出 R2（键映射 assets/uploads/<X>）
# 用法: CF_TOKEN=xxx python3 deploy/deploy-uploads-r2.py
import json
import os
import sys
import urllib.error
import urllib.request

CF_TOKEN = os.environ.get("CF_TOKEN", "")   # 从环境变量读取，勿硬编码提交到 git
ACCOUNT_ID = "00d02a54a3c0f7a3f6c3fc75068e29c5"
ZONE_ID = "8135597542c2723a06a91a7e14a6e747"
SCRIPT_NAME = "uploads-r2"
BUCKET = "nownexts-static"

HERE = os.path.dirname(os.path.abspath(__file__))
worker_src = open(os.path.join(HERE, "uploads-r2-worker.js"), encoding="utf-8").read()


def api(method, url, data=None, headers=None):
    req = urllib.request.Request(url, method=method)
    req.add_header("Authorization", f"Bearer {CF_TOKEN}")
    if data is not None:
        req.add_header("Content-Type", "application/json")
        req.data = json.dumps(data).encode()
    for k, v in (headers or {}).items():
        req.add_header(k, v)
    try:
        with urllib.request.urlopen(req) as r:
            return json.loads(r.read().decode())
    except urllib.error.HTTPError as e:
        return {"success": False, "errors": [{"code": e.code, "message": e.read().decode()[:300]}]}


# 1) 上传 Worker：multipart/form-data，含 metadata + worker.js
boundary = "----WebKitFormBoundary7MA4YWxkTrZu0gW"
buf = bytearray()

def add_field(name, value, filename=None, ctype=None):
    buf.extend(f"--{boundary}\r\n".encode())
    if filename:
        buf.extend(f'Content-Disposition: form-data; name="{name}"; filename="{filename}"\r\n'.encode())
        if ctype:
            buf.extend(f"Content-Type: {ctype}\r\n".encode())
    else:
        buf.extend(f'Content-Disposition: form-data; name="{name}"\r\n'.encode())
    buf.extend(b"\r\n")
    buf.extend(value if isinstance(value, bytes) else value.encode())
    buf.extend(b"\r\n")

metadata = {"main_module": "worker.js", "bindings": [{"name": "MEDIA", "type": "r2_bucket", "bucket_name": BUCKET}]}
add_field("metadata", json.dumps(metadata))
add_field("worker.js", worker_src, filename="worker.js", ctype="application/javascript+module")
buf.extend(f"--{boundary}--\r\n".encode())

print("==> 1/4 上传 Worker 脚本 (js module + R2 binding)")
req = urllib.request.Request(
    f"https://api.cloudflare.com/client/v4/accounts/{ACCOUNT_ID}/workers/scripts/{SCRIPT_NAME}",
    method="PUT",
)
req.add_header("Authorization", f"Bearer {CF_TOKEN}")
req.add_header("Content-Type", f"multipart/form-data; boundary={boundary}")
req.data = bytes(buf)
try:
    with urllib.request.urlopen(req) as r:
        d = json.loads(r.read().decode())
    print("   success=" + str(d.get("success")) + (f" errors={d.get('errors')}" if not d.get("success") else ""))
    if not d.get("success"):
        sys.exit(1)
except urllib.error.HTTPError as e:
    print("   HTTPError " + str(e.code) + ": " + e.read().decode()[:300])
    sys.exit(1)

print("==> 2/4 添加 Zone 路由 nownexts.com/uploads/*")
d = api("POST", f"https://api.cloudflare.com/client/v4/zones/{ZONE_ID}/workers/routes",
        {"pattern": "nownexts.com/uploads/*", "script": SCRIPT_NAME})
print("   success=" + str(d.get("success")) + (f" errors={d.get('errors')}" if not d.get("success") else ""))

print("==> 3/4 走通验证：一个已回填的键")
try:
    req2 = urllib.request.Request("https://nownexts.com/uploads/articles/screenshot-pi-web-pi-agent-d37649da.jpeg")
    with urllib.request.urlopen(req2) as r:
        print(f"   HTTP {r.status}  content-type={r.headers.get('content-type')}  size={r.headers.get('content-length')}")
except urllib.error.HTTPError as e:
    print(f"   HTTP {e.code}（若 404：检查回填键与 Worker 键映射是否一致）")

print("==> 4/4 完成。老内容的 /uploads/<X> 图片 URL 现在从 R2 直出。")
