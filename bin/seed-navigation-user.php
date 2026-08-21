<?php
/**
 * 补充种子：用户本地工具库（Drafts/Daily 收集）→ 导航站
 * 运行：php bin/seed-navigation-user.php
 * 按内容创作/AI/Agent 等子分类收录，去重（按 URL）
 */
require_once __DIR__ . '/../admin/config.php';

$navFile = DATA_DIR . '/navigation.json';
$nav = json_read($navFile);
$exists = [];
foreach (($nav['sites'] ?? []) as $s) $exists[$s['url']] = true;

// 工具：name, name_en, url, desc, cat, sub, tags, featured, weight
$U = [];

// ─── 内容创作 · AI 写作与 PPT ───
foreach ([
['AI 写作助手','AI Writing','https://www.lovart.ai','你收集的 AI 写作选品笔记（生态入口 Lovart）','content','写作与PPT',['AI','写作','小众']],
['Presenton','Presenton','https://github.com/presenton/presenton','开源 AI 演示文稿生成','content','写作与PPT',['AI','PPT','开源']],
['GEOFlow','GEOFlow','https://github.com/yaojingang/GEOFlow','GEO 内容流程工具','content','写作与PPT',['GEO','内容','开源']],
['Compare2Word','Compare2Word','https://compare2word.com','文档对比工具','content','写作与PPT',['效率','工具']],
['Design Prompts','Design Prompts','https://designprompts.dev','设计提示词库','content','写作与PPT',['AI','设计']],
['Codex PPT Skill','Codex PPT Skill','https://github.com/ningzimu/codex-ppt-skill','Codex 生成 PPT 技能','content','写作与PPT',['AI','PPT','开源']],
['AnyToCopy','AnyToCopy','https://anytocopy.com','任意文本复制工具','content','写作与PPT',['效率','工具']],
['Portfolio Audit','Portfolio Audit','https://github.com/hey-stefan/portfolio-audit','作品集审查工具','content','写作与PPT',['效率','开源']],
['Docmd','Docmd','https://github.com/mgks/docmd','文档转 Markdown','content','写作与PPT',['文档','开源']],
] as $t) $U[] = $t;

// ─── 内容创作 · AI 视频生成 ───
foreach ([
['CogVideo','CogVideo','https://github.com/zai-org/CogVideo','智谱开源视频生成模型','content','AI视频',['AI','视频','开源']],
['Seedance','Seedance','https://dreamina.jianying.com','字节即梦 AI 视频模型','content','AI视频',['AI','视频','商业']],
['LibLib AI','LibLib AI','https://liblib.tv','你收集的视频工具生态入口（LibTV）','content','AI视频',['AI','视频','创作'],1,60],
['Craft-Agent','Craft-Agent','https://github.com/ipvoov/Craft-Agent','AI 创意生成 Agent','content','AI视频',['AI','Agent','开源']],
['Text2Video','Text2Video','https://github.com/bravekingzhang/text2video','文本生成视频工具','content','AI视频',['AI','视频','开源']],
['AI Short Video Factory','Short Video Factory','https://github.com/YILS-LIN/short-video-factory','AI 短视频工厂','content','AI视频',['AI','视频','开源']],
['Toonflow','Toonflow','https://github.com/HBAI-Ltd/Toonflow-app','AI 卡通风格视频工具','content','AI视频',['AI','视频','开源']],
['ComeCut','ComeCut','https://github.com/juntaosun/ComeCut','AI 视频剪辑','content','AI视频',['AI','视频','开源']],
['FreeCut','FreeCut','https://github.com/walterlow/freecut','AI 智能剪辑','content','AI视频',['AI','视频','开源']],
['KVideo','KVideo','https://github.com/KuekHaoYang/KVideo','AI 视频处理','content','AI视频',['AI','视频','开源']],
] as $t) $U[] = $t;

// ─── 内容创作 · AI 音频与语音 ───
foreach ([
['Whisper','Whisper','https://github.com/openai/whisper','OpenAI 开源语音识别','content','AI音频',['AI','语音','开源'],1,70],
['VoxCPM','VoxCPM','https://github.com/OpenBMB/VoxCPM','开源语音模型','content','AI音频',['AI','语音','开源']],
['OpenVoice','OpenVoice','https://github.com/myshell-ai/OpenVoice','开源声音克隆','content','AI音频',['AI','语音','开源']],
['Voice-Pro','Voice-Pro','https://github.com/abus-aikorea/voice-pro','AI 语音处理套件','content','AI音频',['AI','语音','开源']],
['Suno AI','Suno','https://suno.com','AI 音乐生成','content','AI音频',['AI','音乐','商业']],
['Podcastfy','Podcastfy','https://github.com/souzatharsis/podcastfy','内容转播客','content','AI音频',['AI','播客','开源']],
['Buzz','Buzz','https://github.com/chidiwilliams/buzz','离线语音转文字','content','AI音频',['AI','转录','开源']],
['TTS Online','TTS Online','https://www.ttson.cn','在线语音合成','content','AI音频',['AI','TTS']],
['SpokenType','SpokenType','https://spokentype.com','语音输入打字','content','AI音频',['AI','效率']],
['AudioVisual','AudioVisual','https://github.com/RemotePinee/AudioVisual','音视频可视化','content','AI音频',['AI','开源']],
['FunClip','FunClip','https://github.com/alibaba-damo-academy/FunClip','阿里开源视频裁剪配音','content','AI音频',['AI','视频','开源']],
['Call Center AI','Call Center AI','https://github.com/microsoft/call-center-ai','微软客服 AI','content','AI音频',['AI','客服','开源']],
] as $t) $U[] = $t;

// ─── 内容创作 · 字幕翻译 ───
foreach ([
['xiaohu-video-translate','xiaohu video translate','https://github.com/xiaohuailabs/xiaohu-video-translate','视频翻译与字幕','content','字幕翻译',['AI','字幕','开源']],
['MioSub','MioSub','https://github.com/corvo007/MioSub','字幕工具','content','字幕翻译',['AI','字幕','开源']],
['AirTranslate','AirTranslate','https://github.com/himomohi/AirTranslate','AI 实时翻译','content','字幕翻译',['AI','翻译','开源']],
['Auto-Subs','Auto-Subs','https://github.com/tmoroney/auto-subs','自动字幕生成','content','字幕翻译',['AI','字幕','开源']],
['pyVideoTrans','pyVideoTrans','https://github.com/jianchang512/pyvideotrans','视频翻译配音','content','字幕翻译',['AI','翻译','开源']],
['Voice to Text Tools','Voice to Text','https://github.com/aiyoubucuoyou/voice-to-text-tools','纯前端音视频转文字','content','字幕翻译',['AI','转录','开源']],
['DeepLX','DeepLX','https://github.com/OwO-Network/DeepLX','DeepL 免费 API 替代','content','字幕翻译',['翻译','开源']],
['ReadPo','ReadPo','https://readpo.com','AI 阅读与翻译','content','字幕翻译',['AI','阅读','小众']],
['TurboSeek','TurboSeek','https://github.com/Nutlope/turboseek','AI 搜索','content','字幕翻译',['AI','搜索','开源']],
['Morphic','Morphic','https://github.com/miurla/morphic','开源 AI 搜索','content','字幕翻译',['AI','搜索','开源']],
] as $t) $U[] = $t;

// ─── 内容创作 · AI 图像设计 ───
foreach ([
['Pixnarr','Pixnarr','https://github.com/vyixor/pixnarr','AI 图像工具','content','AI图像',['AI','图像','开源']],
['Rembg','Rembg','https://github.com/danielgatis/rembg','开源 AI 抠图','content','AI图像',['AI','抠图','开源']],
['Real-ESRGAN','Real-ESRGAN','https://github.com/xinntao/Real-ESRGAN','开源图像超分','content','AI图像',['AI','放大','开源']],
['Logo Creator','Logo Creator','https://github.com/Nutlope/logocreator','AI Logo 生成','content','AI图像',['AI','设计','开源']],
['Penpot','Penpot','https://github.com/penpot/penpot','开源设计协作（Figma 替代）','content','AI图像',['设计','开源']],
['Excalidraw','Excalidraw','https://github.com/excalidraw/excalidraw','开源手绘白板','content','AI图像',['设计','开源']],
['Brat Generator','Brat Generator','https://brat-generator.app','Brat 风格图片生成','content','AI图像',['AI','图像','小众']],
['Caesium Compressor','Caesium','https://github.com/Lymphatus/caesium-image-compressor','开源图片压缩','content','AI图像',['效率','开源']],
['StockCake','StockCake','https://stockcake.com','免费 AI 图库','content','AI图像',['素材','AI']],
['CattoPic','CattoPic','https://image-flow-next-js.vercel.app','AI 图片生成','content','AI图像',['AI','图像','小众']],
['花快图','HuaKuaitu','https://hua.kuaitu.cc','AI 图片处理','content','AI图像',['AI','图像','小众']],
['Next AI Draw.io','Next AI Draw.io','https://github.com/DayuanJiang/next-ai-draw-io','AI 绘图工具','content','AI图像',['AI','绘图','开源']],
] as $t) $U[] = $t;

// ─── 内容创作 · 录屏剪辑 ───
foreach ([
['Cap','Cap','https://github.com/CapSoftware/cap','开源录屏分享','content','录屏剪辑',['录屏','开源']],
['QuickRecorder','QuickRecorder','https://github.com/lihaoyun6/QuickRecorder','macOS 轻量录屏','content','录屏剪辑',['录屏','开源']],
['Screenity','Screenity','https://github.com/alyssaxuu/screenity','开源录屏+标注','content','录屏剪辑',['录屏','开源']],
['LosslessCut','LosslessCut','https://github.com/mifi/lossless-cut','开源无损剪辑','content','录屏剪辑',['视频','开源']],
['OpenCut','OpenCut','https://github.com/OpenCut-app/OpenCut','AI 视频剪辑','content','录屏剪辑',['AI','视频','开源']],
['Video Candy','Video Candy','https://videocandy.com','在线视频处理','content','录屏剪辑',['视频','在线']],
['Recorder Online','Recorder Online','https://recorder-online.com','在线录屏','content','录屏剪辑',['录屏','在线']],
] as $t) $U[] = $t;

// ─── 内容创作 · 效率工具 ───
foreach ([
['Maccy','Maccy','https://github.com/p0deje/Maccy','macOS 剪贴板历史','content','效率工具',['效率','开源']],
['UniClipboard','UniClipboard','https://github.com/UniClipboard/UniClipboard','开源跨端剪贴板','content','效率工具',['效率','开源']],
['EasySpider','EasySpider','https://github.com/NaiboWang/EasySpider','可视化爬虫','content','效率工具',['爬虫','开源']],
['LLM Wiki','LLM Wiki','https://github.com/nashsu/llm_wiki','LLM 工具合集','content','效率工具',['AI','资源']],
['LiteParse','LiteParse','https://github.com/run-llama/liteparse','轻量解析工具','content','效率工具',['效率','开源']],
['Inkeys','Inkeys','https://github.com/Alan-CRL/Inkeys','键盘工具','content','效率工具',['效率','开源']],
['Bob','Bob','https://github.com/ripperhe/Bob','macOS 翻译工具','content','效率工具',['翻译','效率']],
['Aye','Aye','https://okaapps.com','效率工具','content','效率工具',['效率']],
['Flowershow','Flowershow','https://github.com/datopian/flowershow','笔记转网站','content','效率工具',['建站','开源']],
['Qwerty Learner','Qwerty Learner','https://github.com/RealKai42/qwerty-learner','打字练习（词库）','content','效率工具',['效率','开源']],
['DataVis 看板','DataVis','https://github.com/sinyu1012/Double-Color-Ball-AI','数据可视化看板','content','效率工具',['数据','可视化']],
['Chinese Days','Chinese Days','https://github.com/vsme/chinese-days','节假日小工具','content','效率工具',['效率','小众']],
] as $t) $U[] = $t;

// ─── AI Agent ───
foreach ([
['UI-TARS Desktop','UI-TARS Desktop','https://github.com/bytedance/UI-TARS-desktop','字节开源 GUI Agent','agent','Agent 应用',['AI','Agent','开源'],1,80],
['bolt.diy','bolt.diy','https://github.com/stackblitz-labs/bolt.diy','开源 AI 应用搭建','agent','Agent 应用',['AI','建站','开源']],
['AnythingLLM','AnythingLLM','https://github.com/Mintplex-Labs/anything-llm','开源本地知识库助手','agent','本地 AI',['AI','RAG','开源']],
['Lobe Chat','Lobe Chat','https://github.com/lobehub/lobe-chat','开源 AI 聊天界面','agent','本地 AI',['AI','对话','开源']],
['Refly','Refly','https://github.com/refly-ai/refly','开源 AI 知识助手','agent','Agent 应用',['AI','Agent','开源']],
['OpenCyvis','OpenCyvis','https://github.com/opencyvis/opencyvis-phone','AI 视觉助手','agent','Agent 应用',['AI','视觉','开源']],
['CyberVerse','CyberVerse','https://github.com/dsd2077/CyberVerse','开源 AI 助手','agent','Agent 应用',['AI','助手','开源']],
['pi-web','pi-web','https://github.com/Epsilondelta-ai/pi-web','开源 AI 对话','agent','Agent 应用',['AI','对话','开源']],
['GeekAI','GeekAI','https://github.com/yangjian102621/geekai','国产 AI 助手套件','agent','Agent 应用',['AI','助手','开源']],
['DeepSeek GUI','DeepSeek GUI','https://github.com/XingYu-Zhong/DeepSeek-GUI','DeepSeek 桌面客户端','agent','Agent 应用',['AI','客户端','开源']],
['GPT4All','GPT4All','https://github.com/nomic-ai/gpt4all','本地运行大模型','agent','本地 AI',['AI','本地','开源']],
['CChatbot','CChatbot','https://github.com/ChatBot-All/chatbot-app','多模型聊天客户端','agent','Agent 应用',['AI','对话','开源']],
['Xget','Xget','https://github.com/xixu-me/Xget','AI 工具','agent','Agent 应用',['AI','开源']],
['Inbox Zero','Inbox Zero','https://github.com/elie222/inbox-zero','开源 AI 邮件助手','agent','Agent 应用',['AI','邮件','开源']],
] as $t) $U[] = $t;

// ─── 开源生态 ───
foreach ([
['Duix-Avatar','Duix-Avatar','https://github.com/duixcom/Duix-Avatar','开源 AI 数字人','open','开源 AI 应用',['AI','数字人','开源']],
['shimmy','shimmy','https://github.com/Michael-A-Kuykendall/shimmy','AI 工具','open','开源 AI 应用',['AI','开源']],
['Jellyfish','Jellyfish','https://github.com/Forget-C/Jellyfish','开源 AI 项目','open','开源 AI 应用',['AI','开源']],
['quant-agent','QuantAgent','https://github.com/Y-Research-SBU/QuantAgent','AI 量化 Agent','open','开源 AI 应用',['AI','量化','开源']],
['Reasonix','Reasonix','https://github.com/esengine/reasonix','开源推理工具','open','开源 AI 应用',['AI','开源']],
['3DCellForge','3DCellForge','https://github.com/huangserva/3DCellForge','3D 生成工具','open','开源 AI 应用',['AI','3D','开源']],
['GBrain','GBrain','https://github.com/garrytan/gbrain','开源 AI 项目','open','开源 AI 应用',['AI','开源']],
] as $t) $U[] = $t;

// 组装 + 去重
$added = 0;
foreach ($U as $u) {
    if (isset($exists[$u[2]])) continue;
    $exists[$u[2]] = true;
    $nav['sites'][] = [
        'id' => 'site_' . substr(md5($u[2]), 0, 8),
        'name' => $u[0], 'name_en' => $u[1], 'url' => $u[2],
        'description' => $u[3], 'category' => $u[4], 'sub' => $u[5],
        'tags' => $u[6], 'featured' => !empty($u[7]),
        'region' => strpos($u[2], 'github.com') !== false ? 'intl' : (preg_match('/\.(cn|com\.cn)$/', parse_url($u[2], PHP_URL_HOST) ?? '') ? 'cn' : 'cn'),
        'logo' => '', 'reason' => '', 'weight' => $u[8] ?? 5,
        'status' => 'published', 'hits' => 0, 'created_at' => date('Y-m-d H:i:s'),
    ];
    $added++;
}
$nav['updated_at'] = date('Y-m-d H:i:s');
json_write($navFile, $nav);
echo "补充新增：{$added} 站点 / 总计 " . count($nav['sites']) . " 站\n";
