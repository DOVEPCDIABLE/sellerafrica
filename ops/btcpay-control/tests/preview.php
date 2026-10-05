<?php
declare(strict_types=1);
$mode = $argv[1] ?? 'login';
$_SESSION = ['csrf'=>'preview-only', 'authenticated'=>in_array($mode, ['dashboard','terminal'], true)];
$scriptNonce = 'preview-nonce';
if ($mode === 'terminal') $_SESSION['terminal'] = ['token'=>trim(stream_get_contents(STDIN)), 'expires'=>time()+300, 'new'=>true];
$owner = ['username'=>'owner'];
$error = $notice = '';
$health = ['docker_ready'=>true, 'summary'=>'11/11 running', 'disk'=>['free_gb'=>275.4], 'checked_at'=>'Preview', 'endpoint'=>'HTTPS reachable', 'job'=>['state'=>'inactive','result'=>'Not run yet'], 'checks'=>['All discovered containers are running.'], 'containers'=>array_map(fn($name)=>['name'=>$name,'state'=>'running','health'=>'not configured','restarts'=>0,'cpu'=>'0.5%','memory'=>'600 MiB'], ['btcpayserver','bitcoin','nbxplorer','postgres','nginx'])];
$console = new stdClass();
$console->db = new PDO('sqlite::memory:');
$console->db->exec('CREATE TABLE events(id INTEGER PRIMARY KEY, created INTEGER, action TEXT, detail TEXT)');
function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function token(): void { echo '<input type="hidden" name="csrf" value="preview-only">'; }
$source = file_get_contents(dirname(__DIR__) . '/public/index.php');
eval('?>' . substr($source, strpos($source, '<!doctype html>')));
