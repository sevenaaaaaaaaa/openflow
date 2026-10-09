#!/usr/bin/env python3
"""由 segments.json（简体原文）生成 content.zh-TW.json（繁體，台灣用語，zhconv）。
译文源改了简体原文后重跑即可；人工校订可直接改 content.zh-TW.json 中对应条目（重跑不会覆盖已存在的条目，加 --force 才全量重生）。"""
import json, sys, os
from zhconv import convert
D = os.path.dirname(os.path.abspath(__file__))
seg = json.load(open(f'{D}/segments.json', encoding='utf8'))
out_path = f'{D}/content.zh-TW.json'
cur = json.load(open(out_path, encoding='utf8')) if os.path.exists(out_path) and '--force' not in sys.argv else {}
# zhconv 只做字形+少量词汇转换，这里补台湾常用技术词（长词在前，避免被短词截断）
TW_FIX = [
    ('服務端', '伺服器端'), ('客戶端', '用戶端'), ('視頻', '影片'), ('視訊', '影片'), ('鑰匙串', '鑰匙圈'), ('存儲', '儲存'),
    ('默認', '預設'), ('軟件', '軟體'), ('硬件', '硬體'), ('網絡', '網路'), ('信息流', '資訊流'), ('信息', '資訊'),
    ('設置', '設定'), ('登錄', '登入'), ('用戶', '使用者'), ('數據', '資料'), ('程序', '程式'), ('屏幕', '螢幕'),
    ('鼠標', '滑鼠'), ('文檔', '文件'), ('質量', '品質'), ('搜索', '搜尋'), ('調用', '呼叫'), ('接口', '介面'),
    ('兼容', '相容'), ('激活', '啟用'), ('內存', '記憶體'), ('緩存', '快取'), ('插件', '外掛'), ('擴展', '擴充'),
    ('服務器', '伺服器'), ('代碼', '程式碼'), ('支持', '支援'), ('打開', '開啟'), ('文件夾', '資料夾'), ('剪貼板', '剪貼簿'),
    ('輸入法', '輸入法'), ('郵箱', '信箱'), ('郵件', '郵件'), ('導出', '匯出'), ('導入', '匯入'), ('下載', '下載'),
    ('點擊', '點選'), ('點選即', '點選即'), ('彈窗', '彈出視窗'), ('隱私政策', '隱私權政策'),
    ('操作系統', '作業系統'), ('增長', '成長'), ('復現', '重現'), ('創建', '建立'), ('建庫', '建庫'), ('配置', '設定'), ('部署', '部署'), ('脫兔', '脫兔'),
]
def tw(s):
    t = convert(s, 'zh-tw')
    for a, b in TW_FIX: t = t.replace(a, b)
    return t
for s in seg:
    if s not in cur:
        cur[s] = tw(s)
json.dump(dict(sorted(cur.items())), open(out_path, 'w', encoding='utf8'), ensure_ascii=False, indent=4)
print('zh-TW:', len(cur), '段')
