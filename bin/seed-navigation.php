<?php
/**
 * 导航站种子数据 — 两级分类 + 140 站点（AI/Agent 加权 + 开源小众优先 + 增长导向）
 * 运行：php bin/seed-navigation.php
 */
require_once __DIR__ . '/../admin/config.php';

$nav = [
    'categories' => [
        ['id'=>'ai','name'=>'AI 对话与助手','name_en'=>'AI Chat & Assistants','icon'=>'🤖','sort'=>1],
        ['id'=>'agent','name'=>'AI Agent','name_en'=>'AI Agents','icon'=>'🧩','sort'=>2],
        ['id'=>'open','name'=>'开源生态','name_en'=>'Open Source','icon'=>'📖','sort'=>3],
        ['id'=>'flow','name'=>'自动化与工作流','name_en'=>'Automation','icon'=>'🔀','sort'=>4],
        ['id'=>'growth','name'=>'增长与 SEO','name_en'=>'Growth & SEO','icon'=>'📈','sort'=>5],
        ['id'=>'data','name'=>'数据与洞察','name_en'=>'Data & Insight','icon'=>'📊','sort'=>6],
        ['id'=>'crm','name'=>'客户与私域','name_en'=>'CRM & Private Domain','icon'=>'🗣','sort'=>7],
        ['id'=>'content','name'=>'内容与知识','name_en'=>'Content & Knowledge','icon'=>'💬','sort'=>8],
        ['id'=>'site','name'=>'建站与变现','name_en'=>'Build & Monetize','icon'=>'🌐','sort'=>9],
    ],
    'sites' => [],
];

// 站点数组：id, name, name_en, url, desc, cat, sub, sub_en, region, featured, tags, reason, weight
$S = [];

// ═══ ① AI 对话与助手 ═══
$S[] = ['name'=>'ChatGPT','name_en'=>'ChatGPT','url'=>'https://chat.openai.com','desc'=>'OpenAI 对话助手，AI 时代的入口','cat'=>'ai','sub'=>'对话模型','region'=>'intl','tags'=>['AI','对话','商业'],'featured'=>1,'weight'=>100];
$S[] = ['name'=>'Claude','name_en'=>'Claude','url'=>'https://claude.ai','desc'=>'Anthropic 安全可靠的 AI 助手','cat'=>'ai','sub'=>'对话模型','region'=>'intl','tags'=>['AI','对话','商业'],'featured'=>1,'weight'=>95];
$S[] = ['name'=>'DeepSeek','name_en'=>'DeepSeek','url'=>'https://chat.deepseek.com','desc'=>'国产开源大模型，性价比之选','cat'=>'ai','sub'=>'对话模型','region'=>'cn','tags'=>['AI','对话','开源'],'featured'=>1,'weight'=>90];
$S[] = ['name'=>'Kimi','name_en'=>'Kimi','url'=>'https://kimi.moonshot.cn','desc'=>'月之暗面，长文本处理强','cat'=>'ai','sub'=>'对话模型','region'=>'cn','tags'=>['AI','对话','商业']];
$S[] = ['name'=>'豆包','name_en'=>'Doubao','url'=>'https://www.doubao.com','desc'=>'字节系 AI 助手，免费易用','cat'=>'ai','sub'=>'对话模型','region'=>'cn','tags'=>['AI','对话','商业']];
$S[] = ['name'=>'通义千问','name_en'=>'Qwen Chat','url'=>'https://tongyi.aliyun.com','desc'=>'阿里大模型，开源权重','cat'=>'ai','sub'=>'对话模型','region'=>'cn','tags'=>['AI','对话','开源']];
$S[] = ['name'=>'文心一言','name_en'=>'Ernie Bot','url'=>'https://yiyan.baidu.com','desc'=>'百度 AI，中文知识丰富','cat'=>'ai','sub'=>'对话模型','region'=>'cn','tags'=>['AI','对话','商业']];
$S[] = ['name'=>'Gemini','name_en'=>'Gemini','url'=>'https://gemini.google.com','desc'=>'Google 多模态 AI','cat'=>'ai','sub'=>'对话模型','region'=>'intl','tags'=>['AI','对话','商业']];
$S[] = ['name'=>'Perplexity','name_en'=>'Perplexity','url'=>'https://www.perplexity.ai','desc'=>'AI 搜索，带引用的答案','cat'=>'ai','sub'=>'搜索问答','region'=>'intl','tags'=>['AI','搜索','商业'],'featured'=>1,'weight'=>85];
$S[] = ['name'=>'秘塔 AI','name_en'=>'Metaso','url'=>'https://metaso.cn','desc'=>'国内 AI 搜索，学术强','cat'=>'ai','sub'=>'搜索问答','region'=>'cn','tags'=>['AI','搜索','商业']];
$S[] = ['name'=>'Felo','name_en'=>'Felo','url'=>'https://felo.ai','desc'=>'多语言 AI 搜索助手','cat'=>'ai','sub'=>'搜索问答','region'=>'cn','tags'=>['AI','搜索','小众']];
$S[] = ['name'=>'OpenRouter','name_en'=>'OpenRouter','url'=>'https://openrouter.ai','desc'=>'聚合 300+ 大模型 API','cat'=>'ai','sub'=>'API 聚合','region'=>'intl','tags'=>['AI','API','小众'],'featured'=>1,'weight'=>80];
$S[] = ['name'=>'LiteLLM','name_en'=>'LiteLLM','url'=>'https://github.com/BerriAI/litellm','desc'=>'开源 LLM 网关，统一 API','cat'=>'ai','sub'=>'API 聚合','region'=>'intl','tags'=>['AI','API','开源']];
$S[] = ['name'=>'Poe','name_en'=>'Poe','url'=>'https://poe.com','desc'=>'Quora 聚合多模型','cat'=>'ai','sub'=>'API 聚合','region'=>'intl','tags'=>['AI','对话','商业']];

// ═══ ② AI Agent（比重最大） ═══
$S[] = ['name'=>'browser-use','name_en'=>'browser-use','url'=>'https://github.com/browser-use/browser-use','desc'=>'⭐109k 让 AI Agent 操控浏览器执行任务','cat'=>'agent','sub'=>'浏览器 Agent','region'=>'intl','tags'=>['AI','Agent','开源'],'featured'=>1,'weight'=>98];
$S[] = ['name'=>'nanobrowser','name_en'=>'nanobrowser','url'=>'https://github.com/nanobrowser/nanobrowser','desc'=>'开源 AI 浏览器扩展，自动化网页','cat'=>'agent','sub'=>'浏览器 Agent','region'=>'intl','tags'=>['AI','Agent','开源']];
$S[] = ['name'=>'deep-research','name_en'=>'deep-research','url'=>'https://github.com/dzhng/deep-research','desc'=>'AI 深度研究助手，迭代式搜索','cat'=>'agent','sub'=>'浏览器 Agent','region'=>'intl','tags'=>['AI','Agent','开源']];
$S[] = ['name'=>'Skyvern','name_en'=>'Skyvern','url'=>'https://github.com/Skyvern-AI/skyvern','desc'=>'开源网页自动化 Agent','cat'=>'agent','sub'=>'浏览器 Agent','region'=>'intl','tags'=>['AI','Agent','开源']];
$S[] = ['name'=>'Composio','name_en'=>'Composio','url'=>'https://github.com/ComposioHQ/composio','desc'=>'Agent 工具接入平台','cat'=>'agent','sub'=>'浏览器 Agent','region'=>'intl','tags'=>['AI','Agent','开源']];
$S[] = ['name'=>'Crawlee','name_en'=>'Crawlee','url'=>'https://github.com/apify/crawlee','desc'=>'⭐25k AI 网页抓取框架','cat'=>'agent','sub'=>'浏览器 Agent','region'=>'intl','tags'=>['AI','抓取','开源']];
$S[] = ['name'=>'maxun','name_en'=>'maxun','url'=>'https://github.com/getmaxun/maxun','desc'=>'⭐17k 无代码 AI 爬虫','cat'=>'agent','sub'=>'浏览器 Agent','region'=>'intl','tags'=>['AI','抓取','开源']];
$S[] = ['name'=>'MetaGPT','name_en'=>'MetaGPT','url'=>'https://github.com/FoundationAgents/MetaGPT','desc'=>'⭐70k 多 Agent 框架','cat'=>'agent','sub'=>'Agent 框架','region'=>'intl','tags'=>['AI','Agent','开源']];
$S[] = ['name'=>'CrewAI','name_en'=>'CrewAI','url'=>'https://github.com/crewAIInc/crewAI','desc'=>'⭐57k 角色扮演 Agent 编排','cat'=>'agent','sub'=>'Agent 框架','region'=>'intl','tags'=>['AI','Agent','开源'],'featured'=>1,'weight'=>90];
$S[] = ['name'=>'AutoGen','name_en'=>'AutoGen','url'=>'https://github.com/microsoft/autogen','desc'=>'⭐60k 微软 Agent 框架','cat'=>'agent','sub'=>'Agent 框架','region'=>'intl','tags'=>['AI','Agent','开源']];
$S[] = ['name'=>'Mastra','name_en'=>'Mastra','url'=>'https://github.com/mastra-ai/mastra','desc'=>'⭐27k TS 原生 AI 框架','cat'=>'agent','sub'=>'Agent 框架','region'=>'intl','tags'=>['AI','Agent','开源']];
$S[] = ['name'=>'LangGraph','name_en'=>'LangGraph','url'=>'https://github.com/langchain-ai/langgraph','desc'=>'LangChain 图编排','cat'=>'agent','sub'=>'Agent 框架','region'=>'intl','tags'=>['AI','Agent','开源']];
$S[] = ['name'=>'Open WebUI','name_en'=>'Open WebUI','url'=>'https://github.com/open-webui/open-webui','desc'=>'⭐149k 本地 AI 界面，自托管','cat'=>'agent','sub'=>'本地 AI','region'=>'intl','tags'=>['AI','本地','开源'],'featured'=>1,'weight'=>92];
$S[] = ['name'=>'Ollama','name_en'=>'Ollama','url'=>'https://ollama.com','desc'=>'本地跑大模型','cat'=>'agent','sub'=>'本地 AI','region'=>'intl','tags'=>['AI','本地','开源']];
$S[] = ['name'=>'Lobe Chat','name_en'=>'Lobe Chat','url'=>'https://github.com/lobehub/lobe-chat','desc'=>'开源聊天界面，多模型','cat'=>'agent','sub'=>'本地 AI','region'=>'cn','tags'=>['AI','对话','开源']];
$S[] = ['name'=>'Manus','name_en'=>'Manus','url'=>'https://manus.im','desc'=>'通用 Agent，自主完成复杂任务','cat'=>'agent','sub'=>'Agent 应用','region'=>'cn','tags'=>['AI','Agent','商业'],'featured'=>1,'weight'=>88];
$S[] = ['name'=>'Coze 扣子','name_en'=>'Coze','url'=>'https://www.coze.cn','desc'=>'字节 Agent 平台，工作流+插件','cat'=>'agent','sub'=>'Agent 平台','region'=>'cn','tags'=>['AI','Agent','商业'],'featured'=>1,'weight'=>85];
$S[] = ['name'=>'Dify','name_en'=>'Dify','url'=>'https://github.com/langgenius/dify','desc'=>'⭐153k 开源 LLM 应用平台','cat'=>'agent','sub'=>'Agent 平台','region'=>'intl','tags'=>['AI','Agent','开源'],'featured'=>1,'weight'=>93];
$S[] = ['name'=>'Cursor','name_en'=>'Cursor','url'=>'https://cursor.com','desc'=>'AI 代码编辑器，Agent 编码','cat'=>'agent','sub'=>'Agent 应用','region'=>'intl','tags'=>['AI','Agent','商业']];
$S[] = ['name'=>'nanobot','name_en'=>'nanobot','url'=>'https://github.com/HKUDS/nanobot','desc'=>'⭐47k 轻量自部署个人 Agent','cat'=>'agent','sub'=>'Agent 应用','region'=>'intl','tags'=>['AI','Agent','开源']];

// ═══ ③ 开源生态 ═══
$S[] = ['name'=>'RAGFlow','name_en'=>'RAGFlow','url'=>'https://github.com/infiniflow/ragflow','desc'=>'⭐88k 开源 RAG 知识库','cat'=>'open','sub'=>'RAG 知识库','region'=>'intl','tags'=>['开源','RAG','AI'],'featured'=>1,'weight'=>88];
$S[] = ['name'=>'Trilium','name_en'=>'Trilium','url'=>'https://github.com/TriliumNext/Trilium','desc'=>'⭐37k 开源个人知识库','cat'=>'open','sub'=>'RAG 知识库','region'=>'intl','tags'=>['开源','知识库','小众']];
$S[] = ['name'=>'pathway','name_en'=>'pathway','url'=>'https://github.com/pathwaycom/pathway','desc'=>'⭐62k 实时数据分析引擎','cat'=>'open','sub'=>'RAG 知识库','region'=>'intl','tags'=>['开源','数据','AI']];
$S[] = ['name'=>'Llama','name_en'=>'Llama','url'=>'https://github.com/meta-llama/llama-models','desc'=>'Meta 开源大模型','cat'=>'open','sub'=>'开源大模型','region'=>'intl','tags'=>['开源','大模型']];
$S[] = ['name'=>'Qwen','name_en'=>'Qwen','url'=>'https://github.com/QwenLM','desc'=>'阿里开源大模型系列','cat'=>'open','sub'=>'开源大模型','region'=>'cn','tags'=>['开源','大模型'],'featured'=>1,'weight'=>80];
$S[] = ['name'=>'Mistral','name_en'=>'Mistral','url'=>'https://mistral.ai','desc'=>'欧洲开源大模型','cat'=>'open','sub'=>'开源大模型','region'=>'intl','tags'=>['开源','大模型']];
$S[] = ['name'=>'GLM 智谱','name_en'=>'GLM','url'=>'https://github.com/THUDM/GLM-4','desc'=>'清华系开源大模型','cat'=>'open','sub'=>'开源大模型','region'=>'cn','tags'=>['开源','大模型']];
$S[] = ['name'=>'Stable Diffusion','name_en'=>'Stable Diffusion WebUI','url'=>'https://github.com/AUTOMATIC1111/stable-diffusion-webui','desc'=>'开源 AI 绘图','cat'=>'open','sub'=>'开源 AI 应用','region'=>'intl','tags'=>['开源','AI绘图']];
$S[] = ['name'=>'AFFiNE','name_en'=>'AFFiNE','url'=>'https://github.com/toeverything/AFFiNE','desc'=>'开源 Notion 替代','cat'=>'open','sub'=>'开源 AI 应用','region'=>'cn','tags'=>['开源','知识库']];
$S[] = ['name'=>'vLLM','name_en'=>'vLLM','url'=>'https://github.com/vllm-project/vllm','desc'=>'LLM 推理加速','cat'=>'open','sub'=>'开源部署','region'=>'intl','tags'=>['开源','推理']];
$S[] = ['name'=>'Hugging Face','name_en'=>'Hugging Face','url'=>'https://huggingface.co','desc'=>'开源 AI 模型社区','cat'=>'open','sub'=>'开源社区','region'=>'intl','tags'=>['开源','社区'],'featured'=>1,'weight'=>82];
$S[] = ['name'=>'ModelScope','name_en'=>'ModelScope','url'=>'https://modelscope.cn','desc'=>'阿里魔搭开源社区','cat'=>'open','sub'=>'开源社区','region'=>'cn','tags'=>['开源','社区']];
$S[] = ['name'=>'GitHub','name_en'=>'GitHub','url'=>'https://github.com','desc'=>'全球开源代码托管','cat'=>'open','sub'=>'开源社区','region'=>'intl','tags'=>['开源','社区','开发']];
$S[] = ['name'=>'Gitee','name_en'=>'Gitee','url'=>'https://gitee.com','desc'=>'国内代码托管','cat'=>'open','sub'=>'开源社区','region'=>'cn','tags'=>['开源','社区']];

// ═══ ④ 自动化与工作流 ═══
$S[] = ['name'=>'n8n','name_en'=>'n8n','url'=>'https://n8n.io','desc'=>'⭐201k 开源工作流自动化','cat'=>'flow','sub'=>'流程编排','region'=>'intl','tags'=>['自动化','开源'],'featured'=>1,'weight'=>95];
$S[] = ['name'=>'Activepieces','name_en'=>'Activepieces','url'=>'https://github.com/activepieces/activepieces','desc'=>'⭐24k 开源 Zapier 替代','cat'=>'flow','sub'=>'流程编排','region'=>'intl','tags'=>['自动化','开源']];
$S[] = ['name'=>'automatisch','name_en'=>'automatisch','url'=>'https://github.com/automatisch/automatisch','desc'=>'⭐14k 开源自动化','cat'=>'flow','sub'=>'流程编排','region'=>'intl','tags'=>['自动化','开源']];
$S[] = ['name'=>'Node-RED','name_en'=>'Node-RED','url'=>'https://nodered.org','desc'=>'⭐23k 低代码事件流','cat'=>'flow','sub'=>'流程编排','region'=>'intl','tags'=>['自动化','开源','低代码']];
$S[] = ['name'=>'Windmill','name_en'=>'Windmill','url'=>'https://github.com/windmill-labs/windmill','desc'=>'开源开发者平台+自动化','cat'=>'flow','sub'=>'流程编排','region'=>'intl','tags'=>['自动化','开源','小众']];
$S[] = ['name'=>'Kestra','name_en'=>'Kestra','url'=>'https://github.com/kestra-io/kestra','desc'=>'开源数据编排','cat'=>'flow','sub'=>'流程编排','region'=>'intl','tags'=>['自动化','开源','数据']];
$S[] = ['name'=>'Huginn','name_en'=>'Huginn','url'=>'https://github.com/huginn/huginn','desc'=>'开源代理系统，自建监控','cat'=>'flow','sub'=>'流程编排','region'=>'intl','tags'=>['自动化','开源','小众']];
$S[] = ['name'=>'Pipedream','name_en'=>'Pipedream','url'=>'https://pipedream.com','desc'=>'代码级工作流平台','cat'=>'flow','sub'=>'无代码集成','region'=>'intl','tags'=>['自动化','开发']];
$S[] = ['name'=>'Zapier','name_en'=>'Zapier','url'=>'https://zapier.com','desc'=>'无代码自动化标杆','cat'=>'flow','sub'=>'无代码集成','region'=>'intl','tags'=>['自动化','商业']];
$S[] = ['name'=>'Make','name_en'=>'Make','url'=>'https://www.make.com','desc'=>'可视化自动化，多步场景','cat'=>'flow','sub'=>'无代码集成','region'=>'intl','tags'=>['自动化','商业']];
$S[] = ['name'=>'IFTTT','name_en'=>'IFTTT','url'=>'https://ifttt.com','desc'=>'轻量触发式自动化','cat'=>'flow','sub'=>'无代码集成','region'=>'intl','tags'=>['自动化','商业']];
$S[] = ['name'=>'Wechaty','name_en'=>'Wechaty','url'=>'https://github.com/wechaty/wechaty','desc'=>'开源微信机器人框架','cat'=>'flow','sub'=>'私域自动化','region'=>'cn','tags'=>['自动化','微信','开源']];
$S[] = ['name'=>'BillionMail','name_en'=>'BillionMail','url'=>'https://github.com/Billionmail','desc'=>'⭐15k 开源邮件/Newsletter 服务','cat'=>'flow','sub'=>'邮件自动化','region'=>'intl','tags'=>['邮件','开源'],'featured'=>1,'weight'=>75];

// ═══ ⑤ 增长与 SEO ═══
$S[] = ['name'=>'marketing-skills','name_en'=>'marketing-skills','url'=>'https://github.com/coreyhaines31/marketingskills','desc'=>'⭐45k Claude 营销技能包（CRO/文案）','cat'=>'growth','sub'=>'AI 营销','region'=>'intl','tags'=>['AI','营销','开源'],'featured'=>1,'weight'=>94];
$S[] = ['name'=>'claude-seo','name_en'=>'claude-seo','url'=>'https://github.com/AgriciDaniel/claude-seo','desc'=>'⭐14k SEO 技能，25 子技能','cat'=>'growth','sub'=>'AI 营销','region'=>'intl','tags'=>['AI','SEO','开源']];
$S[] = ['name'=>'open-seo','name_en'=>'open-seo','url'=>'https://github.com/every-app/open-seo','desc'=>'⭐13k 开源 Semrush 替代','cat'=>'growth','sub'=>'AI 营销','region'=>'intl','tags'=>['SEO','开源']];
$S[] = ['name'=>'geo-seo-claude','name_en'=>'geo-seo-claude','url'=>'https://github.com/zubair-trabzada/geo-seo-claude','desc'=>'⭐9k GEO 优化技能','cat'=>'growth','sub'=>'AI 营销','region'=>'intl','tags'=>['GEO','AI','开源']];
$S[] = ['name'=>'seomachine','name_en'=>'seomachine','url'=>'https://github.com/TheCraigHewitt/seomachine','desc'=>'⭐7k SEO 长文工作台','cat'=>'growth','sub'=>'AI 营销','region'=>'intl','tags'=>['SEO','AI','开源']];
$S[] = ['name'=>'Ahrefs','name_en'=>'Ahrefs','url'=>'https://ahrefs.com','desc'=>'SEO 分析标杆','cat'=>'growth','sub'=>'SEO 工具','region'=>'intl','tags'=>['SEO','商业']];
$S[] = ['name'=>'Semrush','name_en'=>'Semrush','url'=>'https://www.semrush.com','desc'=>'关键词与竞品研究','cat'=>'growth','sub'=>'SEO 工具','region'=>'intl','tags'=>['SEO','商业']];
$S[] = ['name'=>'5118','name_en'=>'5118','url'=>'https://www.5118.com','desc'=>'中文关键词数据','cat'=>'growth','sub'=>'SEO 工具','region'=>'cn','tags'=>['SEO','中文']];
$S[] = ['name'=>'爱站','name_en'=>'Aizhan','url'=>'https://www.aizhan.com','desc'=>'国内 SEO 查询','cat'=>'growth','sub'=>'SEO 工具','region'=>'cn','tags'=>['SEO','中文']];
$S[] = ['name'=>'Search Console','name_en'=>'Search Console','url'=>'https://search.google.com/search-console','desc'=>'Google 官方收录工具','cat'=>'growth','sub'=>'SEO 工具','region'=>'intl','tags'=>['SEO','Google']];
$S[] = ['name'=>'GrowthBook','name_en'=>'GrowthBook','url'=>'https://github.com/growthbook/growthbook','desc'=>'⭐8k 开源 AB 实验+Feature Flag','cat'=>'growth','sub'=>'AB 实验','region'=>'intl','tags'=>['AB','开源'],'featured'=>1,'weight'=>78];
$S[] = ['name'=>'VWO','name_en'=>'VWO','url'=>'https://vwo.com','desc'=>'AB 测试+转化优化','cat'=>'growth','sub'=>'AB 实验','region'=>'intl','tags'=>['AB','CRO','商业']];
$S[] = ['name'=>'Microsoft Clarity','name_en'=>'Clarity','url'=>'https://clarity.microsoft.com','desc'=>'免费热力图+录屏','cat'=>'growth','sub'=>'行为分析','region'=>'intl','tags'=>['行为','免费']];
$S[] = ['name'=>'Hotjar','name_en'=>'Hotjar','url'=>'https://www.hotjar.com','desc'=>'热力图+问卷','cat'=>'growth','sub'=>'行为分析','region'=>'intl','tags'=>['行为','商业']];
$S[] = ['name'=>'Product Hunt','name_en'=>'Product Hunt','url'=>'https://www.producthunt.com','desc'=>'新品发布社区','cat'=>'growth','sub'=>'增长社区','region'=>'intl','tags'=>['社区','发布']];
$S[] = ['name'=>'Indie Hackers','name_en'=>'Indie Hackers','url'=>'https://www.indiehackers.com','desc'=>'独立开发者社区','cat'=>'growth','sub'=>'增长社区','region'=>'intl','tags'=>['社区','独立开发']];

// ═══ ⑥ 数据与洞察 ═══
$S[] = ['name'=>'PostHog','name_en'=>'PostHog','url'=>'https://github.com/PostHog/posthog','desc'=>'开源产品分析+实验+录屏','cat'=>'data','sub'=>'产品分析','region'=>'intl','tags'=>['分析','开源'],'featured'=>1,'weight'=>86];
$S[] = ['name'=>'Umami','name_en'=>'Umami','url'=>'https://github.com/umami-software/umami','desc'=>'⭐38k 隐私优先分析','cat'=>'data','sub'=>'网站分析','region'=>'intl','tags'=>['分析','开源','隐私']];
$S[] = ['name'=>'Plausible','name_en'=>'Plausible','url'=>'https://plausible.io','desc'=>'⭐28k 轻量隐私分析','cat'=>'data','sub'=>'网站分析','region'=>'intl','tags'=>['分析','开源','隐私']];
$S[] = ['name'=>'OpenReplay','name_en'=>'OpenReplay','url'=>'https://github.com/openreplay/openreplay','desc'=>'⭐12k 开源会话回放','cat'=>'data','sub'=>'产品分析','region'=>'intl','tags'=>['行为','开源']];
$S[] = ['name'=>'OpenPanel','name_en'=>'OpenPanel','url'=>'https://github.com/Openpanel-dev/openpanel','desc'=>'⭐6k 开源产品分析','cat'=>'data','sub'=>'产品分析','region'=>'intl','tags'=>['分析','开源','小众']];
$S[] = ['name'=>'Google Analytics','name_en'=>'GA4','url'=>'https://analytics.google.com','desc'=>'Google 免费分析','cat'=>'data','sub'=>'网站分析','region'=>'intl','tags'=>['分析','Google']];
$S[] = ['name'=>'Amplitude','name_en'=>'Amplitude','url'=>'https://amplitude.com','desc'=>'产品分析标杆','cat'=>'data','sub'=>'产品分析','region'=>'intl','tags'=>['分析','商业']];
$S[] = ['name'=>'神策','name_en'=>'Sensors','url'=>'https://www.sensorsdata.cn','desc'=>'国内数据洞察','cat'=>'data','sub'=>'产品分析','region'=>'cn','tags'=>['分析','国内']];
$S[] = ['name'=>'GrowingIO','name_en'=>'GrowingIO','url'=>'https://www.growingio.com','desc'=>'国内增长分析','cat'=>'data','sub'=>'产品分析','region'=>'cn','tags'=>['分析','国内']];
$S[] = ['name'=>'Metabase','name_en'=>'Metabase','url'=>'https://github.com/metabase/metabase','desc'=>'开源 BI 工具','cat'=>'data','sub'=>'BI 可视化','region'=>'intl','tags'=>['BI','开源'],'featured'=>1,'weight'=>76];
$S[] = ['name'=>'Superset','name_en'=>'Superset','url'=>'https://github.com/apache/superset','desc'=>'Apache 开源 BI','cat'=>'data','sub'=>'BI 可视化','region'=>'intl','tags'=>['BI','开源']];
$S[] = ['name'=>'Tableau','name_en'=>'Tableau','url'=>'https://www.tableau.com','desc'=>'企业级可视化','cat'=>'data','sub'=>'BI 可视化','region'=>'intl','tags'=>['BI','商业']];
$S[] = ['name'=>'Segment','name_en'=>'Segment','url'=>'https://segment.com','desc'=>'CDP 客户数据','cat'=>'data','sub'=>'CDP','region'=>'intl','tags'=>['CDP','数据']];
$S[] = ['name'=>'易观方舟','name_en'=>'Analysys','url'=>'https://www.analysysdata.com','desc'=>'国内 CDP/分析','cat'=>'data','sub'=>'CDP','region'=>'cn','tags'=>['CDP','国内']];
$S[] = ['name'=>'SimilarWeb','name_en'=>'SimilarWeb','url'=>'https://www.similarweb.com','desc'=>'竞品流量研究','cat'=>'data','sub'=>'竞品洞察','region'=>'intl','tags'=>['竞品','研究']];
$S[] = ['name'=>'Google Trends','name_en'=>'Trends','url'=>'https://trends.google.com','desc'=>'搜索趋势洞察','cat'=>'data','sub'=>'竞品洞察','region'=>'intl','tags'=>['趋势','Google']];

// ═══ ⑦ 客户与私域 ═══
$S[] = ['name'=>'Salesforce','name_en'=>'Salesforce','url'=>'https://www.salesforce.com','desc'=>'CRM 标杆','cat'=>'crm','sub'=>'CRM','region'=>'intl','tags'=>['CRM','商业']];
$S[] = ['name'=>'HubSpot','name_en'=>'HubSpot','url'=>'https://www.hubspot.com','desc'=>'入站营销+CRM','cat'=>'crm','sub'=>'CRM','region'=>'intl','tags'=>['CRM','营销']];
$S[] = ['name'=>'纷享销客','name_en'=>'Fenxiang','url'=>'https://www.fxiaoke.com','desc'=>'国内销售 CRM','cat'=>'crm','sub'=>'CRM','region'=>'cn','tags'=>['CRM','国内']];
$S[] = ['name'=>'销售易','name_en'=>'Xiaoshouyi','url'=>'https://www.xiaoshouyi.com','desc'=>'国内云 CRM','cat'=>'crm','sub'=>'CRM','region'=>'cn','tags'=>['CRM','国内']];
$S[] = ['name'=>'Intercom','name_en'=>'Intercom','url'=>'https://www.intercom.com','desc'=>'产品内客服+消息','cat'=>'crm','sub'=>'客服工单','region'=>'intl','tags'=>['客服','商业']];
$S[] = ['name'=>'Zendesk','name_en'=>'Zendesk','url'=>'https://www.zendesk.com','desc'=>'工单客服标杆','cat'=>'crm','sub'=>'客服工单','region'=>'intl','tags'=>['客服','商业']];
$S[] = ['name'=>'智齿客服','name_en'=>'Sobot','url'=>'https://www.sobot.com','desc'=>'国内智能客服','cat'=>'crm','sub'=>'客服工单','region'=>'cn','tags'=>['客服','国内']];
$S[] = ['name'=>'Mailchimp','name_en'=>'Mailchimp','url'=>'https://mailchimp.com','desc'=>'邮件营销标杆','cat'=>'crm','sub'=>'邮件营销','region'=>'intl','tags'=>['邮件','商业']];
$S[] = ['name'=>'Brevo','name_en'=>'Brevo','url'=>'https://www.brevo.com','desc'=>'邮件+自动化','cat'=>'crm','sub'=>'邮件营销','region'=>'intl','tags'=>['邮件','自动化']];
$S[] = ['name'=>'listmonk','name_en'=>'listmonk','url'=>'https://github.com/knadh/listmonk','desc'=>'开源 Newsletter','cat'=>'crm','sub'=>'邮件营销','region'=>'intl','tags'=>['邮件','开源']];
$S[] = ['name'=>'企业微信','name_en'=>'WeCom','url'=>'https://work.weixin.qq.com','desc'=>'企微私域','cat'=>'crm','sub'=>'微信私域','region'=>'cn','tags'=>['私域','微信']];
$S[] = ['name'=>'微伴助手','name_en'=>'WeBan','url'=>'https://weban.work','desc'=>'企微客户运营','cat'=>'crm','sub'=>'微信私域','region'=>'cn','tags'=>['私域','企微']];
$S[] = ['name'=>'小鹅通','name_en'=>'Xiaoe','url'=>'https://www.xiaoe-tech.com','desc'=>'知识付费+私域','cat'=>'crm','sub'=>'微信私域','region'=>'cn','tags'=>['私域','知识付费']];

// ═══ ⑧ 内容与知识 ═══
$S[] = ['name'=>'Notion','name_en'=>'Notion','url'=>'https://www.notion.so','desc'=>'All-in-one 工作台','cat'=>'content','sub'=>'文档协作','region'=>'intl','tags'=>['文档','生产力']];
$S[] = ['name'=>'语雀','name_en'=>'Yuque','url'=>'https://www.yuque.com','desc'=>'阿里知识库','cat'=>'content','sub'=>'文档协作','region'=>'cn','tags'=>['文档','知识库']];
$S[] = ['name'=>'飞书文档','name_en'=>'Feishu','url'=>'https://www.feishu.cn','desc'=>'协同办公+文档','cat'=>'content','sub'=>'文档协作','region'=>'cn','tags'=>['文档','协同']];
$S[] = ['name'=>'思源笔记','name_en'=>'SiYuan','url'=>'https://github.com/siyuan-note/siyuan','desc'=>'开源本地优先笔记','cat'=>'content','sub'=>'文档协作','region'=>'cn','tags'=>['笔记','开源']];
$S[] = ['name'=>'Astro','name_en'=>'Astro','url'=>'https://astro.build','desc'=>'⭐50k 内容优先建站框架','cat'=>'content','sub'=>'博客发布','region'=>'intl','tags'=>['建站','开源'],'featured'=>1,'weight'=>72];
$S[] = ['name'=>'Ghost','name_en'=>'Ghost','url'=>'https://ghost.org','desc'=>'订阅式博客平台','cat'=>'content','sub'=>'博客发布','region'=>'intl','tags'=>['博客','开源']];
$S[] = ['name'=>'WordPress','name_en'=>'WordPress','url'=>'https://wordpress.org','desc'=>'全球 CMS','cat'=>'content','sub'=>'博客发布','region'=>'intl','tags'=>['CMS','开源']];
$S[] = ['name'=>'Canva','name_en'=>'Canva','url'=>'https://www.canva.com','desc'=>'设计小白神器','cat'=>'content','sub'=>'设计工具','region'=>'intl','tags'=>['设计','商业']];
$S[] = ['name'=>'稿定设计','name_en'=>'Gaoding','url'=>'https://www.gaoding.com','desc'=>'国内在线设计','cat'=>'content','sub'=>'设计工具','region'=>'cn','tags'=>['设计','国内']];

// ═══ ⑨ 建站与变现 ═══
$S[] = ['name'=>'Webflow','name_en'=>'Webflow','url'=>'https://webflow.com','desc'=>'可视化建站标杆','cat'=>'site','sub'=>'建站平台','region'=>'intl','tags'=>['建站','无代码'],'featured'=>1,'weight'=>70];
$S[] = ['name'=>'Wix','name_en'=>'Wix','url'=>'https://www.wix.com','desc'=>'大众建站','cat'=>'site','sub'=>'建站平台','region'=>'intl','tags'=>['建站','无代码']];
$S[] = ['name'=>'Strikingly','name_en'=>'Strikingly','url'=>'https://www.strikingly.com','desc'=>'单页建站','cat'=>'site','sub'=>'建站平台','region'=>'intl','tags'=>['建站','单页']];
$S[] = ['name'=>'有赞','name_en'=>'Youzan','url'=>'https://www.youzan.com','desc'=>'国内电商私域','cat'=>'site','sub'=>'建站平台','region'=>'cn','tags'=>['电商','私域']];
$S[] = ['name'=>'Strapi','name_en'=>'Strapi','url'=>'https://strapi.io','desc'=>'开源无头 CMS','cat'=>'site','sub'=>'无头 CMS','region'=>'intl','tags'=>['CMS','开源']];
$S[] = ['name'=>'Contentful','name_en'=>'Contentful','url'=>'https://www.contentful.com','desc'=>'企业无头 CMS','cat'=>'site','sub'=>'无头 CMS','region'=>'intl','tags'=>['CMS','商业']];
$S[] = ['name'=>'Directus','name_en'=>'Directus','url'=>'https://github.com/directus/directus','desc'=>'开源无头数据 CMS','cat'=>'site','sub'=>'无头 CMS','region'=>'intl','tags'=>['CMS','开源']];
$S[] = ['name'=>'Vercel','name_en'=>'Vercel','url'=>'https://vercel.com','desc'=>'前端部署平台','cat'=>'site','sub'=>'托管部署','region'=>'intl','tags'=>['托管','部署']];
$S[] = ['name'=>'Netlify','name_en'=>'Netlify','url'=>'https://www.netlify.com','desc'=>'静态托管','cat'=>'site','sub'=>'托管部署','region'=>'intl','tags'=>['托管','部署']];
$S[] = ['name'=>'Cloudflare','name_en'=>'Cloudflare','url'=>'https://www.cloudflare.com','desc'=>'CDN+安全','cat'=>'site','sub'=>'托管部署','region'=>'intl','tags'=>['CDN','安全']];
$S[] = ['name'=>'宝塔','name_en'=>'BT Panel','url'=>'https://www.bt.cn','desc'=>'国内服务器面板','cat'=>'site','sub'=>'托管部署','region'=>'cn','tags'=>['服务器','运维']];
$S[] = ['name'=>'Stripe','name_en'=>'Stripe','url'=>'https://stripe.com','desc'=>'国际支付标杆','cat'=>'site','sub'=>'支付变现','region'=>'intl','tags'=>['支付','商业'],'featured'=>1,'weight'=>74];
$S[] = ['name'=>'支付宝','name_en'=>'Alipay','url'=>'https://open.alipay.com','desc'=>'国内支付','cat'=>'site','sub'=>'支付变现','region'=>'cn','tags'=>['支付','国内']];
$S[] = ['name'=>'虎皮椒','name_en'=>'XunhuPay','url'=>'https://www.xunhupay.com','desc'=>'个人聚合支付','cat'=>'site','sub'=>'支付变现','region'=>'cn','tags'=>['支付','聚合']];
$S[] = ['name'=>'Paddle','name_en'=>'Paddle','url'=>'https://www.paddle.com','desc'=>'订阅+税务处理','cat'=>'site','sub'=>'支付变现','region'=>'intl','tags'=>['支付','订阅']];
$S[] = ['name'=>'知识星球','name_en'=>'ZhiShiXingQiu','url'=>'https://www.zsxq.com','desc'=>'知识付费社群','cat'=>'site','sub'=>'支付变现','region'=>'cn','tags'=>['知识付费','社群']];
$S[] = ['name'=>'小报童','name_en'=>'Xiaobaotong','url'=>'https://xiaobot.com','desc'=>'内容订阅付费','cat'=>'site','sub'=>'支付变现','region'=>'cn','tags'=>['知识付费','订阅']];
$S[] = ['name'=>'爱发电','name_en'=>'Afadian','url'=>'https://afdian.com','desc'=>'创作者打赏/订阅','cat'=>'site','sub'=>'支付变现','region'=>'cn','tags'=>['创作者','订阅']];
$S[] = ['name'=>'Shopify','name_en'=>'Shopify','url'=>'https://www.shopify.com','desc'=>'电商建站标杆','cat'=>'site','sub'=>'支付变现','region'=>'intl','tags'=>['电商','商业']];

// 组装 sites（按完整 URL 去重）
$seen = [];
foreach ($S as $s) {
    if (isset($seen[$s['url']])) continue;
    $seen[$s['url']] = true;
    $nav['sites'][] = array_merge([
        'id' => 'site_' . substr(md5($s['url']), 0, 8),
        'category' => $s['cat'],
        'sub' => $s['sub'],
        'sub_en' => $s['sub_en'] ?? '',
        'description' => $s['desc'],
        'featured' => !empty($s['featured']),
        'region' => $s['region'] ?? 'cn',
        'logo' => '',
        'reason' => $s['reason'] ?? '',
        'weight' => $s['weight'] ?? 0,
        'status' => 'published',
        'hits' => 0,
        'created_at' => date('Y-m-d H:i:s'),
    ], $s);
}
$nav['hot_searches'] = ['AI Agent', '开源大模型', '浏览器自动化', 'RAG 知识库', 'SEO 技能', '增长黑客'];
$nav['banner'] = ['title' => 'OpenFlow 导航', 'subtitle' => 'AI 时代 · 开源 · 自动化 · 增长工具集', 'site_id' => $nav['sites'][0]['id'] ?? ''];
$nav['updated_at'] = date('Y-m-d H:i:s');

json_write(DATA_DIR . '/navigation.json', $nav);
echo "已生成：" . count($nav['categories']) . " 分类 / " . count($nav['sites']) . " 站点\n";
