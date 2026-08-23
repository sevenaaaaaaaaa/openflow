#!/usr/bin/env python3
"""
同步本地 assets/ 到 Cloudflare R2（仅上传变更文件）
用法：python3 sync-r2.py
"""
import boto3, os, sys, mimetypes

R2_ENDPOINT = "https://00d02a54a3c0f7a3f6c3fc75068e29c5.r2.cloudflarestorage.com"
R2_KEY = os.environ.get("R2_KEY", "bd8a5ecc09149dbbecc6390ab2d7f6f5")
R2_SECRET = os.environ.get("R2_SECRET", "5ab14a7f0fcfb99f0b5111616389ffc244933df4154413cf672ca5f21940b20b")
BUCKET = "nownexts-static"
ASSETS_DIR = os.path.dirname(os.path.abspath(__file__)) + "/assets"
EXCLUDE_EXTS = {'.map', '.ts', '.md', '.txt', '.sh'}

CACHE_STATIC = 'public, max-age=604800, immutable'
CACHE_IMAGE  = 'public, max-age=2592000, immutable'

def main():
    s3 = boto3.client('s3',
        endpoint_url=R2_ENDPOINT,
        aws_access_key_id=R2_KEY,
        aws_secret_access_key=R2_SECRET,
        region_name='auto'
    )

    # 获取 R2 现有文件的 ETag
    existing = {}
    ContinuationToken = None
    while True:
        kwargs = {'Bucket': BUCKET}
        if ContinuationToken: kwargs['ContinuationToken'] = ContinuationToken
        r = s3.list_objects_v2(**kwargs)
        for obj in r.get('Contents', []):
            existing[obj['Key']] = obj['ETag'].strip('"')
        if not r.get('IsTruncated'): break
        ContinuationToken = r.get('NextContinuationToken')

    uploaded = 0; skipped = 0; errors = 0; webp_count = 0
    for root, dirs, files in os.walk(ASSETS_DIR):
        for f in files:
            ext = os.path.splitext(f)[1].lower()
            if ext in EXCLUDE_EXTS: continue
            local_path = os.path.join(root, f)
            rel_path = os.path.relpath(local_path, os.path.dirname(ASSETS_DIR))
            key = rel_path.replace(os.sep, '/')

            # 比较本地 MD5 与 R2 ETag（跳过未变更文件）
            import hashlib
            with open(local_path, 'rb') as fp:
                local_md5 = hashlib.md5(fp.read()).hexdigest()
            if existing.get(key) == local_md5:
                skipped += 1
            else:
                content_type = mimetypes.guess_type(local_path)[0] or 'application/octet-stream'
                cache = CACHE_IMAGE if ext in ('.png','.jpg','.jpeg','.gif','.ico','.webp','.svg','.woff','.woff2') else CACHE_STATIC
                size = os.path.getsize(local_path)
                try:
                    with open(local_path, 'rb') as fp:
                        s3.put_object(Bucket=BUCKET, Key=key, Body=fp,
                                      ContentType=content_type, CacheControl=cache)
                    print(f"  ✅ {key} ({size:,} bytes)")
                    uploaded += 1
                except Exception as e:
                    print(f"  ❌ {key}: {e}")
                    errors += 1

            # 图片自动生成 WebP 版本（>15KB 的 png/jpg/jpeg）
            if ext in ('.png', '.jpg', '.jpeg') and os.path.getsize(local_path) > 15000:
                webp_key = key.rsplit('.', 1)[0] + '.webp'
                if existing.get(webp_key):
                    continue  # 已有 WebP
                try:
                    from PIL import Image
                    import io as _io
                    img = Image.open(local_path)
                    if img.mode not in ('RGB', 'RGBA'): img = img.convert('RGB')
                    buf = _io.BytesIO()
                    img.save(buf, format='WEBP', quality=80, method=4)
                    webp_data = buf.getvalue()
                    if len(webp_data) < os.path.getsize(local_path) * 0.6:
                        s3.put_object(Bucket=BUCKET, Key=webp_key, Body=webp_data,
                                      ContentType='image/webp', CacheControl=CACHE_IMAGE)
                        print(f"  🔄 {webp_key} ({len(webp_data):,} bytes)")
                        webp_count += 1
                except ImportError:
                    pass  # Pillow not installed, skip
                except Exception:
                    pass  # skip conversion errors

    print(f"\nR2 同步完成: 上传 {uploaded} / 跳过 {skipped} / WebP {webp_count} / 失败 {errors}")
    return 0 if errors == 0 else 1

if __name__ == '__main__':
    sys.exit(main())
