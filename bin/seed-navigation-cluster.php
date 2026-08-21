<?php
/**
 * 补充种子：Cluster 选题 + Tags 目录工具 → 导航站
 * 运行：php bin/seed-navigation-cluster.php
 * 聚焦有官方 GitHub/官网 URL 的新工具，按内容创作子分类收录
 */
require_once __DIR__ . '/../admin/config.php';

$navFile = DATA_DIR . '/navigation.json';
$nav = json_read($navFile);
$exists = [];
foreach (($nav['sites'] ?? []) as $s) $exists[$s['url']] = true;

// name, name_en, url, desc, cat, sub, tags, featured
$U = [];

// ─── AI Agent / 工作流 ───
foreach ([
['MineContext','MineContext','https://github.com/volcengine/MineContext','火山引擎开源上下文 Agent','agent','Agent 应用',['AI','Agent','开源'],1],
['open-codesign','open-codesign','https://github.com/OpenCoworkAI/open-codesign','开源 AI 设计协作 Agent','agent','Agent 应用',['AI','Agent','开源']],
['Fay','Fay','https://github.com/xinxiyinhe/fay','开源数字人 Agent','agent','Agent 应用',['AI','数字人','开源']],
['Onlook','Onlook','https://github.com/onlook-dev/onlook','开源 AI 可视化编辑器','agent','Agent 应用',['AI','编辑器','开源']],
['OpenWork','OpenWork','https://github.com/different-ai/openwork','开源多 Agent 工作台','agent','Agent 应用',['AI','Agent','开源']],
['videocut-skills','videocut-skills','https://github.com/Ceeon/videocut-skills','Claude 自动化剪辑技能','agent','Agent 应用',['AI','视频','开源']],
['trendFinder','trendFinder','https://github.com/ericciarla/trendFinder','AI 趋势发现工具','agent','Agent 应用',['AI','趋势','开源']],
['TrendRadar','TrendRadar','https://github.com/sansan0/TrendRadar','开源 AI 趋势雷达','agent','Agent 应用',['AI','趋势','开源']],
['ai-trend-publish','ai-trend-publish','https://github.com/liyown/ai-trend-publish','AI 趋势内容发布','agent','Agent 应用',['AI','内容','开源']],
['LocalAI','LocalAI','https://github.com/mudler/LocalAI','开源本地 AI 推理','agent','本地 AI',['AI','本地','开源']],
['garden-skills','garden-skills','https://github.com/ConardLi/garden-skills','Claude Code 技能合集','agent','Agent 应用',['AI','技能','开源']],
['axton-obsidian-skills','axton obsidian','https://github.com/axtonliu/axton-obsidian-visual-skills','Claude+Obsidian 可视化技能','agent','Agent 应用',['AI','Obsidian','开源']],
['OpenClaw Skills','OpenClaw','https://github.com/VoltAgent/awesome-openclaw-skills','开源 OpenClaw 技能集','agent','Agent 应用',['AI','技能','开源']],
] as $t) $U[] = $t;

// ─── AI 视频 ───
foreach ([
['ACE-Step','ACE-Step','https://github.com/ace-step/ACE-Step-1.5','开源视频生成','content','AI视频',['AI','视频','开源']],
['VideoLingo','VideoLingo','https://github.com/Huanshere/VideoLingo','AI 视频翻译配音','content','字幕翻译',['AI','翻译','开源']],
['MoneyPrinterTurbo','MoneyPrinterTurbo','https://github.com/harry0703/MoneyPrinterTurbo','开源短视频自动生产','content','AI视频',['AI','视频','开源'],1],
['MoneyPrinterV2','MoneyPrinterV2','https://github.com/FujiwaraChoki/MoneyPrinterV2','短视频自动生成 V2','content','AI视频',['AI','视频','开源']],
['autocut','autocut','https://github.com/mli/autocut','开源 AI 剪辑','content','录屏剪辑',['AI','剪辑','开源']],
['auto-editor','auto-editor','https://github.com/WyattBlue/auto-editor','开源智能剪辑','content','录屏剪辑',['AI','剪辑','开源']],
['KrillinAI','KrillinAI','https://github.com/krillinai/KrillinAI','AI 视频翻译工具','content','字幕翻译',['AI','翻译','开源']],
['ComfyUI','ComfyUI','https://github.com/Comfy-Org/ComfyUI','开源节点式 AI 工作流','content','AI视频',['AI','工作流','开源'],1],
['火宝短剧','huobao-drama','https://github.com/chatfire-AI/huobao-drama','开源 AI 短剧工具','content','AI视频',['AI','短剧','开源']],
['ai-short-drama','ai-short-drama','https://github.com/EvoLinkAI/ai-short-drama','开源 AI 短剧生成','content','AI视频',['AI','短剧','开源']],
['Video Material GEN','Video Material GEN','https://github.com/Norsico/Video-Materials-AutoGEN-Workstation','开源视频素材自动生成','content','AI视频',['AI','素材','开源']],
] as $t) $U[] = $t;

// ─── AI 图像 ───
foreach ([
['Fooocus','Fooocus','https://github.com/lllyasviel/Fooocus','开源极简 AI 绘图','content','AI图像',['AI','绘图','开源']],
['InvokeAI','InvokeAI','https://github.com/invoke-ai/InvokeAI','开源专业 AI 绘图','content','AI图像',['AI','绘图','开源']],
['ControlNet','ControlNet','https://github.com/lllyasviel/ControlNet','开源可控生成','content','AI图像',['AI','生成','开源']],
['GFPGAN','GFPGAN','https://github.com/TencentARC/GFPGAN','腾讯开源人脸修复','content','AI图像',['AI','修复','开源']],
['IOPaint','IOPaint','https://github.com/Sanster/IOPaint','开源图像修复','content','AI图像',['AI','修复','开源']],
['Upscayl','Upscayl','https://github.com/upscayl/upscayl','开源 AI 放大','content','AI图像',['AI','放大','开源']],
['OmniSVG','OmniSVG','https://github.com/OmniSVG/OmniSVG','开源 SVG 生成','content','AI图像',['AI','矢量','开源']],
['HunyuanImage','HunyuanImage','https://github.com/Tencent-Hunyuan/HunyuanImage-3.0','腾讯开源图像模型','content','AI图像',['AI','图像','开源']],
['screenshot-to-code','screenshot to code','https://github.com/abi/screenshot-to-code','截图转代码','content','AI图像',['AI','开发','开源']],
['open-design','open-design','https://github.com/nexu-io/open-design','开源设计系统','content','AI图像',['AI','设计','开源']],
['html-anything','html-anything','https://github.com/nexu-io/html-anything','AI 生成 HTML','content','AI图像',['AI','前端','开源']],
['PosterCraft','PosterCraft','https://github.com/MeiGen-AI/PosterCraft','开源海报生成','content','AI图像',['AI','海报','开源']],
['DeepDiagram','DeepDiagram','https://github.com/LingyiChen-AI/DeepDiagram','开源图表生成','content','AI图像',['AI','图表','开源']],
['哩布哩布','Liblib','https://www.liblib.art','国内 AI 绘图社区','content','AI图像',['AI','绘图','社区']],
['星流','Xingliu','https://www.xingliu.art','AI 绘图工具','content','AI图像',['AI','绘图']],
['Recraft','Recraft','https://recraft.ai','AI 设计生成','content','AI图像',['AI','设计','商业']],
] as $t) $U[] = $t;

// ─── AI 音频 / 语音 / 数字人 ───
foreach ([
['faster-whisper','faster-whisper','https://github.com/SYSTRAN/faster-whisper','Whisper 加速版','content','AI音频',['AI','转录','开源']],
['CosyVoice','CosyVoice','https://github.com/FunAudioLLM/CosyVoice','阿里开源语音合成','content','AI音频',['AI','TTS','开源']],
['SenseVoice','SenseVoice','https://github.com/FunAudioLLM/SenseVoice','开源语音识别','content','AI音频',['AI','识别','开源']],
['FunASR','FunASR','https://github.com/modelscope/FunASR','开源语音识别框架','content','AI音频',['AI','识别','开源']],
['GPT-SoVITS','GPT-SoVITS','https://github.com/RVC-Boss/GPT-SoVITS','开源声音克隆','content','AI音频',['AI','克隆','开源']],
['RVC','RVC','https://github.com/RVC-Project/Retrieval-based-Voice-Conversion-WebUI','开源变声','content','AI音频',['AI','变声','开源']],
['UVR','UVR','https://github.com/Anjok07/ultimatevocalremovergui','开源人声分离','content','AI音频',['AI','分离','开源']],
['Bark','Bark','https://github.com/suno-ai/bark','Suno 开源 TTS','content','AI音频',['AI','TTS','开源']],
['ChatTTS','ChatTTS','https://github.com/2noise/ChatTTS','开源对话式 TTS','content','AI音频',['AI','TTS','开源']],
['edge-tts','edge-tts','https://github.com/rany2/edge-tts','Edge 免费 TTS','content','AI音频',['AI','TTS','开源']],
['index-tts','index-tts','https://github.com/index-tts/index-tts','开源情感 TTS','content','AI音频',['AI','TTS','开源']],
['MuseTalk','MuseTalk','https://github.com/TMElyralab/MuseTalk','开源数字人口型','content','AI音频',['AI','数字人','开源']],
['SadTalker','SadTalker','https://github.com/OpenTalker/SadTalker','开源数字人','content','AI音频',['AI','数字人','开源']],
['Linly-Talker','Linly-Talker','https://github.com/Kedreamix/Linly-Talker','开源数字人对话','agent','Agent 应用',['AI','数字人','开源']],
['Linly-Dubbing','Linly-Dubbing','https://github.com/Kedreamix/Linly-Dubbing','开源 AI 配音','content','AI音频',['AI','配音','开源']],
['lite-avatar','lite-avatar','https://github.com/HumanAIGC/lite-avatar','开源轻量数字人','content','AI音频',['AI','数字人','开源']],
['OpenAvatarChat','OpenAvatarChat','https://github.com/HumanAIGC/OpenAvatarChat','开源数字人聊天','agent','Agent 应用',['AI','数字人','开源']],
['duix.ai','duix.ai','https://github.com/GuijiAI/duix.ai','硅基智能开源数字人','content','AI音频',['AI','数字人','开源']],
['HeyGen','HeyGen','https://www.heygen.com','AI 视频数字人','content','AI音频',['AI','数字人','商业']],
] as $t) $U[] = $t;

// ─── OCR / 文档 / 效率 ───
foreach ([
['MinerU','MinerU','https://github.com/opendatalab/MinerU','开源文档解析','content','效率工具',['OCR','文档','开源']],
['markitdown','markitdown','https://github.com/microsoft/markitdown','微软开源文档转 Markdown','content','效率工具',['文档','开源']],
['Marker','Marker','https://github.com/datalab-to/marker','开源 PDF 转 Markdown','content','效率工具',['OCR','开源']],
['Surya','Surya','https://github.com/datalab-to/surya','开源 OCR','content','效率工具',['OCR','开源']],
['PaddleOCR','PaddleOCR','https://github.com/PaddlePaddle/PaddleOCR','百度开源 OCR','content','效率工具',['OCR','开源']],
['read-frog','read-frog','https://github.com/mengxi-ream/read-frog','开源文档阅读','content','效率工具',['文档','开源']],
['social-auto-upload','social-auto-upload','https://github.com/dreammis/social-auto-upload','开源多平台自动发布','flow','流程编排',['社媒','自动化','开源']],
['Postiz','Postiz','https://github.com/gitroomhq/postiz-app','开源社媒管理','flow','流程编排',['社媒','开源'],1],
['AI-ContentCraft','AI-ContentCraft','https://github.com/hotdancing/AI-ContentCraft','AI 内容生成','content','写作与PPT',['AI','内容','开源']],
['yt-dlp','yt-dlp','https://github.com/yt-dlp/yt-dlp','开源视频下载','content','效率工具',['下载','开源']],
['LM Studio','LM Studio','https://lmstudio.ai','本地跑大模型桌面端','agent','本地 AI',['AI','本地','商业']],
['Google AI Studio','AI Studio','https://aistudio.google.com','Google 免费 AI 平台','ai','对话模型',['AI','Google']],
['TikHub','TikHub','https://github.com/TikHub/TikHub-API-Python-SDK','社媒数据 API','data','竞品洞察',['数据','API','开源']],
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
        'region' => strpos($u[2], 'github.com') !== false || strpos($u[2], 'github.io') !== false ? 'intl' : 'cn',
        'logo' => '', 'reason' => '', 'weight' => 4,
        'status' => 'published', 'hits' => 0, 'created_at' => date('Y-m-d H:i:s'),
    ];
    $added++;
}
$nav['updated_at'] = date('Y-m-d H:i:s');
json_write($navFile, $nav);
echo "新增收录: {$added} · 总计 " . count($nav['sites']) . " 站\n";
