<?php
/**
 * T1-5 验收：同意管理 + 数据保留（ConsentSystem）
 *   php tests/consent_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-consent-' . getmypid());
@mkdir(DATA_DIR . '/db', 0777, true);
function json_read(string $f): array { if(!is_file($f)) return []; $d=json_decode((string)file_get_contents($f),true); return is_array($d)?$d:[]; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f),0777,true); return (bool)file_put_contents($f,json_encode($d,JSON_UNESCAPED_UNICODE)); }
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/ConsentSystem.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

echo "\n── 1. 默认 off：一律放行（行为不变）──\n";
check('无 cookie 放行', consent_granted([]) === true);
check('off 模式下不看 cookie(denied 也放行)', consent_granted(['of_consent'=>'denied']) === true);
check('默认模式=off', consent_settings()['mode'] === 'off');

echo "\n── 2. implied：默认同意，明确拒绝才拦 ──\n";
consent_save(['mode'=>'implied']);
check('未表态→采集', consent_granted([]) === true);
check('granted→采集', consent_granted(['of_consent'=>'granted']) === true);
check('denied→不采', consent_granted(['of_consent'=>'denied']) === false);

echo "\n── 3. explicit：必须明确同意 ──\n";
consent_save(['mode'=>'explicit']);
check('未表态→不采', consent_granted([]) === false);
check('denied→不采', consent_granted(['of_consent'=>'denied']) === false);
check('granted→采集', consent_granted(['of_consent'=>'granted']) === true);
check('allow_profile 同步', consent_allow_profile([]) === false);

echo "\n── 4. 非法模式回落 off ──\n";
check('非法 mode→off', consent_save(['mode'=>'bogus'])['mode'] === 'off');

echo "\n── 5. 保留期：0 跳过 ──\n";
consent_save(['mode'=>'off','retention_days'=>0]);
$r = consent_purge_expired();
check('retention=0 跳过', ($r['skipped'] ?? false) === true);

echo "\n── 6. 保留期清理真的删旧数据 ──\n";
Database::execute("CREATE TABLE IF NOT EXISTS events (id INTEGER PRIMARY KEY AUTOINCREMENT, event TEXT, created_at TEXT)");
Database::execute("DELETE FROM events");
Database::execute("INSERT INTO events (event, created_at) VALUES (?,?)", ['old', date('Y-m-d H:i:s', time()-40*86400)]);
Database::execute("INSERT INTO events (event, created_at) VALUES (?,?)", ['new', date('Y-m-d H:i:s')]);
consent_save(['mode'=>'off','retention_days'=>30]);
$r2 = consent_purge_expired();
check('删除 1 条过期事件', ($r2['events'] ?? 0) === 1, json_encode($r2));
$left = Database::query("SELECT event FROM events");
check('新事件保留', count($left) === 1 && $left[0]['event'] === 'new');

echo "\n── 7. 横幅配置 ──\n";
consent_save(['mode'=>'explicit','banner_text'=>'请允许我们采集']);
$b = consent_banner_config();
check('need=true', $b['need'] === true);
check('文案透出', $b['text'] === '请允许我们采集');
consent_save(['mode'=>'off']);
check('off 时 need=false', consent_banner_config()['need'] === false);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@unlink(DATA_DIR.'/db/openflow.db'); @array_map('unlink', glob(DATA_DIR.'/db/*')); @unlink(DATA_DIR.'/settings.json');
@rmdir(DATA_DIR.'/db'); @rmdir(DATA_DIR);
exit($fail===0?0:1);
