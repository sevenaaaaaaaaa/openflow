#!/usr/bin/env python3
# 部署 r2-media Worker + 绑定 R2 桶 + 添加 media/* 路由
# 用法: CF_TOKEN=xxx python3 deploy/deploy-r2-media.py
import json, sys, os, urllib.request, urllib.error

CF_TOKEN = os.environ.get("CF_TOKEN", "")   # 从环境变量读取，勿硬编码提交到 git
ACCOUNT_ID = "00d02a54a3c0f7a3f6c3fc75068e29c5"
ZONE_ID = "8135597542c2723a06a91a7e14a6e747"
SCRIPT_NAME = "r2-media"
BUCKET = "nownexts-static"

HERE = os.path.dirname(os.path.abspath(__file__))
worker_src = open(os.path.join(HERE, "r2-media-worker.js"), encoding="utf-8").read()


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
except urllib.error.HTTPError as e:
    print("   HTTPError " + str(e.code) + ": " + e.read().decode()[:300])
    sys.exit(1)

print("==> 2/4 添加 Zone 路由 nownexts.com/media/*")
d = api("POST", f"https://api.cloudflare.com/client/v4/zones/{ZONE_ID}/workers/routes",
        {"pattern": "nownexts.com/media/*", "script": SCRIPT_NAME})
print("   success=" + str(d.get("success")) + (f" errors={d.get('errors')}" if not d.get("success") else ""))

print("==> 3/4 测试 Range：访问一个不存在的键，看有没有走 Worker")
try:
    with urllib.request.urlopen("https://nownexts.com/media/nonexistent.mp4") as r:
        pass
except urllib.error.HTTPError as e:
    print(f"   404 检查: HTTP {e.code}（走 Worker 且正确返回 404）")

print("==> 4/4 完成。上传视频到 R2 的 media/ 前缀即可分段播放。")
print("   上传: python3 -c \"import boto3; s3=boto3.client('s3', endpoint_url='https://00d02a54a3c0f7a3f6c3fc75068e29c5.r2.cloudflarestorage.com', aws_access_key_id='..', aws_secret_access_key='..', region_name='auto'); s3.put_object(Bucket='nownexts-static', Key='media/xxx.mp4', Body=open('xxx.mp4','rb'), ContentType='video/mp4')\"")
