#!/usr/bin/env python3
# 上传视频/大文件到 Cloudflare R2 的 media/ 前缀，供 < 视频 > 播放
# 用法: python3 deploy/upload-r2-video.py 本地.mp4 [course-id/slug.mp4]
# 上传后在后台课程课时填 https://nownexts.com/media/<key>
import os, sys, boto3, mimetypes

R2_ENDPOINT = "https://00d02a54a3c0f7a3f6c3fc75068e29c5.r2.cloudflarestorage.com"
R2_KEY = os.environ.get("R2_KEY", "")       # 从环境变量读取，勿硬编码提交到 git
R2_SECRET = os.environ.get("R2_SECRET", "")  # 从环境变量读取，勿硬编码提交到 git
BUCKET = "nownexts-static"
MIME = {".mp4": "video/mp4", ".m3u8": "application/vnd.apple.mpegurl", ".ts": "video/mp2t",
        ".webm": "video/webm", ".mov": "video/quicktime", ".mkv": "video/x-matroska",
        ".mp3": "audio/mpeg", ".wav": "audio/wav", ".pdf": "application/pdf"}

def main():
    if len(sys.argv) < 2:
        print("用法: python3 deploy/upload-r2-video.py [file] [key]"); sys.exit(1)
    local = sys.argv[1]
    if not os.path.isfile(local):
        print("文件不存在:", local); sys.exit(1)
    ext = os.path.splitext(local)[1].lower()
    key = sys.argv[2] if len(sys.argv) > 2 else "media/" + os.path.basename(local)
    if not key.startswith("media/"):
        print("提示: 上传到 media/ 前缀, key=" + key); key = "media/" + key

    ctype = MIME.get(ext, mimetypes.guess_type(local)[0] or "application/octet-stream")
    size = os.path.getsize(local)
    s3 = boto3.client("s3", endpoint_url=R2_ENDPOINT, aws_access_key_id=R2_KEY,
                      aws_secret_access_key=R2_SECRET, region_name="auto")
    with open(local, "rb") as fp:
        s3.put_object(Bucket=BUCKET, Key=key, Body=fp, ContentType=ctype,
                      CacheControl="public, max-age=86400")
    url = "https://nownexts.com/" + key
    print(f"✅ 上传成功 {key} ({size:,} bytes) [{ctype}]")
    print(f"   播放地址: {url}")
    print("   在后台课程课时的「视频地址」填入上述 URL 即可")

if __name__ == "__main__":
    main()
