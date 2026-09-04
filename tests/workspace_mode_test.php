<?php
define('DATA_DIR',sys_get_temp_dir().'/of-workspace-mode-'.getmypid());
@mkdir(DATA_DIR,0777,true);
function json_read(string $file):array{if(!is_file($file))return[];$v=json_decode((string)file_get_contents($file),true);return is_array($v)?$v:[];}
function json_write(string $file,array $data):void{file_put_contents($file,json_encode($data));}
require_once __DIR__.'/../lib/WorkspaceMode.php';
$p=0;$f=0;function wm($n,$ok){global$p,$f;if($ok){$p++;echo"  ✓ $n\n";}else{$f++;echo"  ✗ $n\n";}}
echo "\n── user workspace mode ──\n";
wm('default remains Flow',workspace_mode_current('alice')==='flow');
wm('Loop preference saves',workspace_mode_set('loop','alice')['ok']);
wm('preference persists per user',workspace_mode_current('alice')==='loop');
wm('other user remains Flow',workspace_mode_current('bob')==='flow');
wm('switching back works',workspace_mode_set('flow','alice')['ok'] && workspace_mode_current('alice')==='flow');
wm('invalid mode rejected',!workspace_mode_set('agent','alice')['ok']);
wm('missing user rejected',!workspace_mode_set('loop','')['ok']);
$raw=(string)file_get_contents(workspace_mode_file());
wm('username is not stored in plaintext',!str_contains($raw,'alice'));
echo "\n".($f===0?"✅ 全部通过（{$p}）\n":"❌ 失败 {$f} / 通过 {$p}\n");exit($f===0?0:1);
