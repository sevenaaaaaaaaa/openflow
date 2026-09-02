/* OpenFlow 首页角色化内容
 * 4 角色：小白(beginner) / 开发者(dev) / 达人(power) / 企业(enterprise)
 * 只替换文字内容，不改变视觉结构
 */
window.OF_ROLES = {
  beginner: {
    key: 'beginner',
    label: '我是新手',
    emoji: '🌱',
    desc: '刚开始做一人公司/内容，想快速上手',
    hero: {
      kicker: '芭乐派 · 帮一人公司设计 Agent 能跑的增长系统',
      title1: '不操作你的系统，',
      title2: '设计你的系统',
      lead: '你不缺「怎么做」的工具，你缺「该做什么」的系统。OpenFlow 自动爬取行业信号、生成内容草稿、主动触达转化——让 Agent 跑流程，你只做判断。',
      cta1: '免费开始',
      cta2: '看看怎么用',
      cta3: '免费诊断',
      trust: '一人公司首选 · 核心能力永久开源 · 装完即用'
    },
    qs: [
      {href:'/tools', t:'增长工具箱', d:'SEO检查、文案生成等免费工具', icon:'bolt'},
      {href:'/courses', t:'新手课程', d:'New-1 开始学增长系统', icon:'book'},
      {href:'/docs', t:'使用指南', d:'一步步带你上手', icon:'doc'},
      {href:'/academy', t:'入门文章', d:'看得懂的增长知识', icon:'doc'},
      {href:'/community', t:'门派社区', d:'有人帮你答疑', icon:'users'},
      {href:'#contact', t:'免费诊断', d:'顾问帮你看看增长瓶颈', icon:'info'}
    ],
    steps: [
      {h:'连接增长信号', p:'接入舆情、搜索热点、CDP 事件——OpenFlow 替你盯住市场变化。'},
      {h:'设计你的系统', p:'把增长漏斗拆成 Agent 可执行的任务图，四引擎协同推进。'},
      {h:'主动驱动增长', p:'自生长引擎按你设的周期推一轮，从 Marketing 到 Sales 全闭环。'}
    ],
    band: {
      kicker: '新手友好',
      title: '不用从零学起，跟着 New-1 做就行',
      p: '免费课稿一步步带你搭，照着做就能让增长系统开始转起来。',
      btn1: '开始学习',
      btn2: '看看教程'
    }
  },
  dev: {
    key: 'dev',
    label: '资深开发者',
    emoji: '💻',
    desc: '懂技术，要 API、可扩展、可部署',
    hero: {
      kicker: '芭乐派 · OpenFlow 开放平台',
      title1: '为开发者打造的',
      title2: 'Agent 增长基础设施',
      lead: '开放 API、Webhook、Task Graph 编排、数据连接器——把增长系统嵌进你的技术栈。自托管、私有化、完全可控，核心能力永久开源。',
      cta1: '查看 API 文档',
      cta2: '开发者中心',
      cta3: '部署文档',
      trust: '开放 API · 永久开源 · 自托管 · 鱼与渔结合'
    },
    qs: [
      {href:'/docs#api', t:'API 文档', d:'REST API · Webhook · 鉴权', icon:'doc'},
      {href:'/marketplace', t:'插件市场', d:'Skill/插件/主题 生态', icon:'box'},
      {href:'/docs', t:'开发者文档', d:'架构、扩展点、部署', icon:'book'},
      {href:'/docs', t:'路线图', d:'平台演进规划', icon:'bolt'},
      {href:'/community', t:'开发者社区', d:'提问、贡献、讨论', icon:'users'},
      {href:'https://github.com/sevenaaaaaaaaa/openflow', t:'GitHub', d:'开源仓库 · Star', icon:'info'}
    ],
    steps: [
      {h:'接入数据', p:'前端埋点、服务端事件、Webhook 回调，多通道接入你的数据。'},
      {h:'调用能力', p:'内容、CDP、自动化、SEO 全能力开放为 REST API。'},
      {h:'扩展与部署', p:'插件系统扩展能力，自托管或容器化部署完全可控。'}
    ],
    band: {
      kicker: '开发者优先',
      title: 'API 优先，一切皆可编程',
      p: '从数据接入到能力调用再到部署，全链路开放，任你组合。',
      btn1: '查看文档',
      btn2: '获取 API Key'
    }
  },
  power: {
    key: 'power',
    label: '增长/运营达人',
    emoji: '🚀',
    desc: '要效率、要自动化、要数据驱动',
    hero: {
      kicker: '芭乐派 · 帮一人公司设计 Agent 能跑的增长系统',
      title1: '让增长动作',
      title2: '主动发生',
      lead: '自生长 AI Engine 按你设的周期推一轮：爬取信号 → AI 洞察 → 生成草稿 → 主动触达。从 Marketing 到 Sales 全闭环，把增长交给系统，你专注策略判断。',
      cta1: '免费开始',
      cta2: '查看平台演示',
      cta3: '预约诊断',
      trust: '主动驱动 · 数据洞察 · 自生长引擎'
    },
    qs: [
      {href:'/tools', t:'增长工具箱', d:'SEO/文案/LTV 等专业工具', icon:'bolt'},
      {href:'/marketplace', t:'Skill 市场', d:'开箱即用的增长技能', icon:'box'},
      {href:'/courses', t:'R.B.E 训练营', d:'New-1~4 + 八周系统设计', icon:'book'},
      {href:'/academy', t:'实践文章', d:'增长案例与技巧', icon:'doc'},
      {href:'/community', t:'门派社区', d:'分享与交流', icon:'users'},
      {href:'#contact', t:'增长诊断', d:'深度评估你的增长体系', icon:'info'}
    ],
    steps: [
      {h:'连接增长信号', p:'内容、SEO、CDP、自动化、微信生态，一个平台全打通。'},
      {h:'设计你的系统', p:'把增长漏斗拆成 Agent 可执行的任务图，四引擎协同。'},
      {h:'主动驱动增长', p:'AI 洞察发现机会，A/B 测试验证，持续迭代。'}
    ],
    band: {
      kicker: '为增长而生',
      title: '从内容到转化，全链路 Agent 化',
      p: '你的增长动作不再是孤立的点，而是一条自动运转的流水线。',
      btn1: '开始使用',
      btn2: '查看能力'
    }
  },
  enterprise: {
    key: 'enterprise',
    label: '企业用户',
    emoji: '🏢',
    desc: '要方案、要安全、要落地服务',
    hero: {
      kicker: '芭乐派 · 企业级增长系统',
      title1: '企业的增长',
      title2: '由引擎驱动',
      lead: '从内容到客户数据再到转化变现，一套打通的 Agent 原生增长系统。私有化部署、数据自主可控、专业顾问陪跑落地。',
      cta1: '预约企业演示',
      cta2: '解决方案',
      cta3: '联系顾问',
      trust: '私有化部署 · 数据自主 · 企业级服务'
    },
    qs: [
      {href:'#contact', t:'预约演示', d:'30分钟了解企业方案', icon:'info'},
      {href:'/about', t:'关于我们', d:'团队与使命', icon:'info'},
      {href:'/docs', t:'能力清单', d:'全部功能模块', icon:'doc'},
      {href:'/community', t:'客户社区', d:'案例与讨论', icon:'users'},
      {href:'/courses', t:'团队培训', d:'员工成长体系', icon:'book'},
      {href:'#contact', t:'咨询落地', d:'诊断→方案→实施', icon:'bolt'}
    ],
    steps: [
      {h:'诊断现状', p:'专业顾问评估你网站的内容、获客、转化现状与机会。'},
      {h:'定制方案', p:'基于你的业务场景，规划内容体系、数据中台与自动化流程。'},
      {h:'落地陪跑', p:'从部署到运营，顾问全程陪跑，确保方案真正生效。'}
    ],
    band: {
      kicker: '企业级',
      title: '从诊断到落地，我们陪你把增长做起来',
      p: '私有化部署、数据安全、专业服务团队，为企业增长保驾护航。',
      btn1: '预约演示',
      btn2: '联系顾问'
    }
  }
};

/* 默认角色列表顺序（用于选择器） */
window.OF_ROLE_ORDER = ['beginner', 'power', 'dev', 'enterprise'];
