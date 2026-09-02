<?php
/**
 * 共享页脚 —— 前台页面统一调用 of_footer()，样式来自 modules.css 的 .foot。
 *
 *   <?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
 *
 * 放在 <main> 内最后（与首页一致）。文案与首页页脚一致；改文案只改这里。
 */
if (!function_exists('of_footer')) {
    function of_footer(): void {
        $slogan = function_exists('site_config_get') ? site_config_get('site_slogan', '帮一人公司设计 Agent 能跑的增长系统') : '帮一人公司设计 Agent 能跑的增长系统';
        $copy   = function_exists('site_copyright') ? site_copyright() : '© 2026 芭乐派 · OpenFlow 增长操作系统';
        ?>
  <footer class="foot" data-od-id="site-footer">
    <div class="fb">
      <div class="brand"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span>芭乐派 · OpenFlow</div>
      <p class="f-about">芭乐派增长操作系统的开源底座。TIPS 框架（触达/洞察/个性化/销售）四力合一，自生长 AI Engine 主动驱动增长。</p>
      <p class="note">核心能力永久开源 · 鱼与渔相结合</p>
    </div>
    <div class="fb">
      <h4>站点导航</h4>
      <a href="/product">产品</a><a href="/capability">能力</a><a href="/courses">课程</a><a href="/academy">学院</a><a href="/community">门派社区</a><a href="/about">关于我们</a>
    </div>
    <div class="fb">
      <h4>资源</h4>
      <a href="/docs">文档中心</a><a href="/downloads">资料下载</a><a href="/podcasts">播客</a><a href="/marketplace">生态市场</a><a href="/navigation">增长导航</a>
    </div>
    <div class="fb">
      <h4>联系</h4>
      <a href="mailto:hello@openflow.dev">hello@openflow.dev</a><a href="/enterprise">商务合作</a><a href="/events">活动</a><a href="/community">门派社区</a>
    </div>
    <div class="f-bottom"><span><?=htmlspecialchars($copy)?></span><?php if (function_exists('i18n_enabled') && i18n_enabled()): ?><?=i18n_switcher()?><?php endif; ?><span><?=htmlspecialchars($slogan)?></span></div>
  </footer>
<?php
    }
}
