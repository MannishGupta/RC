<?php
// audit.php — Version: 260916.14
declare(strict_types=1);
if (!defined('BASE_PATH')) exit;

// controller/audit.php — writeAuditLog() helper (no engine dependency)


// ── Audit Logger ─────────────────────────────────────────────────
function writeAuditLog(string $event, array $ctx=[]): void {
    $dir = defined('DATA_PATH') ? DATA_PATH.'/logs' : sys_get_temp_dir();
    if (!is_dir($dir) && !@mkdir($dir,0755,true)) return;
    // Use rightmost IP from X-Forwarded-For to resist spoofing; fallback to REMOTE_ADDR
    $xFwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    $ip   = !empty($xFwd)
        ? trim(end(array_map('trim', explode(',', $xFwd))))
        : trim($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    $ua  = $_SERVER['HTTP_USER_AGENT']??'';
    $dev = preg_match('/Mobile|Android|iPhone|iPad/i',$ua)?'mobile':(preg_match('/Tablet/i',$ua)?'tablet':'desktop');
    $br  = 'other';
    if (preg_match('/Edg\/(\d+)/i',$ua,$m))      $br='Edge/'.$m[1];
    elseif (preg_match('/Chrome\/(\d+)/i',$ua,$m)) $br='Chrome/'.$m[1];
    elseif (preg_match('/Firefox\/(\d+)/i',$ua,$m))$br='Firefox/'.$m[1];
    elseif (preg_match('/Safari\/(\d+)/i',$ua,$m)) $br='Safari/'.$m[1];
    $os='other';
    if (preg_match('/Windows NT ([\d.]+)/i',$ua,$m))    $os='Windows/'.$m[1];
    elseif (preg_match('/Android ([\d.]+)/i',$ua,$m))   $os='Android/'.$m[1];
    elseif (preg_match('/Mac OS X ([\d_]+)/i',$ua,$m))  $os='macOS/'.str_replace('_','.',$m[1]);
    elseif (preg_match('/Linux/i',$ua))                  $os='Linux';
    $entry=json_encode(['ts'=>date('c'),'event'=>$event,'ip'=>$ip,'device'=>$dev,'browser'=>$br,
        'os'=>$os,'ua'=>mb_substr($ua,0,200),'ctx'=>$ctx],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
    if ($fh=@fopen($dir.'/numero_'.date('Y-m').'.log','a')) { flock($fh,LOCK_EX); fwrite($fh,$entry); flock($fh,LOCK_UN); fclose($fh); }
}
