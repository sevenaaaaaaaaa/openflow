<?php
/**
 * 共享页脚 —— 前台页面统一调用 of_footer()，样式来自 modules.css 的 .foot。
 *
 *   <?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
 *
 * 放在 <main> 内最后（与首页一致）。文案与首页页脚一致；改文案只改这里。
 * logo 与站头一致：流环（开放的 O 缺口溢出成箭，Flow / 增长飞轮）。
 */
if (!function_exists('of_footer')) {
    function of_footer(): void {
        $slogan = function_exists('site_config_get') ? site_config_get('site_slogan', '帮一人公司设计 Agent 能跑的增长系统') : '帮一人公司设计 Agent 能跑的增长系统';
        $copy   = function_exists('site_copyright') ? site_copyright() : '© 2026 芭乐派 · OpenFlow 增长操作系统';
        // 插件前台插槽：页脚上方（横幅/订阅框等）
        if (class_exists('PluginSystem')) PluginSystem::render_front_slot('footer_before');

        // 社交图标只渲染后台配置了链接的（系统设置 → 品牌设置 → 社交账号）
        $socMap = [
          'github' => ['GitHub 开源仓库', '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-3.16 19.49c.5.09.68-.22.68-.48v-1.7c-2.78.6-3.37-1.34-3.37-1.34-.45-1.16-1.11-1.47-1.11-1.47-.9-.62.07-.6.07-.6 1 .07 1.53 1.03 1.53 1.03.9 1.52 2.34 1.08 2.91.83.09-.65.35-1.09.63-1.34-2.22-.25-4.56-1.11-4.56-4.94 0-1.1.39-1.99 1.03-2.69-.1-.25-.45-1.27.1-2.64 0 0 .84-.27 2.75 1.02a9.58 9.58 0 0 1 5 0c1.91-1.3 2.75-1.02 2.75-1.02.55 1.37.2 2.39.1 2.64.64.7 1.03 1.6 1.03 2.69 0 3.84-2.34 4.68-4.57 4.93.36.31.68.92.68 1.85V21c0 .27.18.58.69.48A10 10 0 0 0 12 2Z"/></svg>'],
          'x' => ['X 官方账号', '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.8 3h3.1l-6.8 7.8L22 21h-6.3l-4.9-6.4L5.2 21H2.1l7.3-8.3L2 3h6.4l4.4 5.9L17.8 3Zm-1.1 16.1h1.7L7.6 4.8H5.8l10.9 14.3Z"/></svg>'],
          'youtube' => ['YouTube 频道', '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8ZM10 15.5v-7l6 3.5-6 3.5-6 3.5Z"/></svg>'],
          'wechat' => ['微信公众号', '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9.6 4.5C5.9 4.5 3 6.9 3 9.9c0 1.7 1 3.2 2.5 4.2l-.6 2.2 2.4-1.2c.7.2 1.5.3 2.3.3h.5a5.8 5.8 0 0 1-.4-2c0-3 2.9-5.4 6.5-5.4h.4C15.9 6.2 13 4.5 9.6 4.5Zm-2.4 3.4a.9.9 0 1 1 0 1.8.9.9 0 0 1 0-1.8Zm4.8 0a.9.9 0 1 1 0 1.8.9.9 0 0 1 0-1.8Z"/><path d="M21 14.5c0-2.5-2.4-4.5-5.4-4.5s-5.4 2-5.4 4.5 2.4 4.5 5.4 4.5c.5 0 1-.1 1.4-.2l1.9 1-.5-1.7c1.5-.8 2.6-2.2 2.6-3.6Z"/></svg>'],
          'bilibili' => ['B 站账号', '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="3.2" y="7" width="17.6" height="12.5" rx="2.8"/><path d="M9.2 4 7.8 6.4M14.8 4l1.4 2.4M7.8 11v3.4M12 11v3.4M16.2 11v3.4"/></svg>'],
          'zhihu' => ['知乎机构号', '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5.5 4.5h13l-7.5 12.5h4.5v2.5H5.8l.8-2.4h2.6L12.6 9H5.5v-4.5Z"/></svg>'],
        ];
        $socDefault = ['github' => 'https://github.com/sevenaaaaaaaaa/openflow'];
        ?>
  <footer class="foot" data-od-id="site-footer">
    <div class="fb">
      <div class="brand"><span class="ic"><svg viewBox="0 0 64 64" fill="none" aria-hidden="true"><defs><linearGradient id="ofg-foot" x1="10" y1="54" x2="56" y2="10" gradientUnits="userSpaceOnUse"><stop stop-color="var(--accent)"/><stop offset="1" stop-color="oklch(58% .16 285)"/></linearGradient></defs><path d="M13.8 45.8 A19.5 19.5 0 1 1 51.5 17.5" stroke="url(#ofg-foot)" stroke-width="7" stroke-linecap="round"/><path d="M47.5 12.8 L56.8 10.2 L54.2 19.5" stroke="url(#ofg-foot)" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>芭乐派 · OpenFlow</div>
      <p class="f-about">芭乐派增长操作系统的开源底座。TIPS 框架（触达/洞察/个性化/销售）四力合一，自生长 AI Engine 主动驱动增长。</p>
      <p class="note">核心能力永久开源 · 鱼与渔相结合</p>
      <div class="f-social" aria-label="社交媒体">
        <?php
        $rendered = 0;
        foreach ([['github','x','youtube'], ['wechat','bilibili','zhihu']] as $group):
          $items = array_values(array_filter($group, fn($k) => site_config_get('social_' . $k, $socDefault[$k] ?? '') !== ''));
          if (!$items) continue;
          if ($rendered++) echo '<span class="soc-div" aria-hidden="true"></span>'; ?>
        <div class="soc-group">
          <?php foreach ($items as $k): [$label, $svg] = $socMap[$k]; $url = site_config_get('social_' . $k, $socDefault[$k] ?? ''); ?>
          <a class="soc" href="<?=htmlspecialchars($url)?>" target="_blank" rel="noopener" data-od-id="soc-<?=$k?>" aria-label="<?=$label?>"><?=$svg?></a>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="fb">
      <h4>站点导航</h4>
      <a href="/product">产品</a><a href="/capability">能力</a><a href="/courses">课程</a><a href="/academy">学院</a><a href="/community">增长社区</a><a href="/about">关于我们</a>
    </div>
    <div class="fb">
      <h4>资源</h4>
      <a href="/docs">文档中心</a><a href="/downloads">资料下载</a><a href="/podcasts">播客</a><a href="/marketplace">生态市场</a><a href="/navigation">增长导航</a>
      <a href="/gtm-onepager.html">一页纸介绍</a><a href="/gtm-deck.html">产品介绍幻灯片</a><a href="/lp/solo-growth.html">一人公司增长手册（H5）</a>
    </div>
    <div class="fb">
      <h4>联系</h4>
      <a href="mailto:hello@openflow.dev">hello@openflow.dev</a><a href="/enterprise">商务合作</a><a href="/events">活动</a><a href="/community">增长社区</a>
    </div>
    <div class="f-bottom"><span><?=htmlspecialchars($copy)?></span><?php if (function_exists('i18n_enabled') && i18n_enabled()): ?><?=i18n_switcher()?><?php endif; ?><span><?=htmlspecialchars($slogan)?></span></div>
  </footer>
<?php
        // 插件前台插槽：body 末尾（脚本/浮层）+ 插件 JS 资产
        if (class_exists('PluginSystem')) {
            PluginSystem::render_front_slot('body_end');
            PluginSystem::render_front_assets('js');
        }
    }
}
