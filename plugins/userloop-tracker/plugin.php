<?php
/**
 * UserLoop 旅程埋点 —— 前台 body_end 插槽注入 tracker 脚本。
 * 数据流：page_view/element_click → nownexts.com/userloop /api/v1/track
 *        → UserLoop 事件总线 → CDP 建档 → 旅程阶段 → 断点 Loop。
 */
if (!class_exists('PluginSystem')) {
    return;
}
PluginSystem::register_front_slot('body_end', function () {
    ?>
    <script src="https://nownexts.com/userloop/track.js" defer></script>
    <?php
});
