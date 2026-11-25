<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Bangkok');
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['httponly'=>true,'secure'=>true,'samesite'=>'Strict']);
session_start();
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
function h($s): string {return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function fail($message,$code=400){http_response_code($code);exit(h($message));}
$path=getenv('SWK_ATTENDANCE_CONFIG');
if(!$path || !is_file($path)) fail('ยังไม่ได้ตั้งค่าระบบ',503);
$c=require $path;
if(empty($c['operator']) || empty($c['password_hash']) || empty($c['student_query'])) fail('ตั้งค่าระบบไม่ครบ',503);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$csrf=$_SESSION['csrf'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($csrf,(string)($_POST['csrf']??''))) fail('Invalid token',403);
 if(isset($_POST['logout'])){session_destroy();header('Location: ./');exit;}
 if(!isset($_SESSION['auth'])){
  if(hash_equals($c['operator'],(string)($_POST['user']??'')) && password_verify((string)($_POST['password']??''),$c['password_hash'])){
   session_regenerate_id(true);$_SESSION['auth']=true;header('Location: ./');exit;
  }
  sleep(2);fail('เข้าสู่ระบบไม่สำเร็จ',403);
 }
}
function page($title){echo '<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($title).'</title><style>*{box-sizing:border-box}body{margin:0;background:#f1f5f9;color:#172554;font:16px system-ui,sans-serif}main{max-width:980px;margin:auto;padding:24px}section{background:white;padding:20px;border-radius:16px;margin:16px 0}input,select,button{font:inherit;padding:10px;border:1px solid #cbd5e1;border-radius:8px;max-width:100%}button{background:#1d4ed8;color:white;cursor:pointer}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:12px;border-bottom:1px solid #e2e8f0}.scroll{overflow:auto}label{display:inline-block;margin:5px}small{color:#475569}</style><main><h1>'.h($title).'</h1>';}
if(!isset($_SESSION['auth'])){
 page('เข้าสู่ระบบเช็คชื่อ SWK');echo '<section><form method="post"><input type="hidden" name="csrf" value="'.h($csrf).'"><p><input name="user" required placeholder="ชื่อผู้ใช้" autocomplete="username"></p><p><input type="password" name="password" required placeholder="รหัสผ่าน" autocomplete="current-password"></p><button>เข้าสู่ระบบ</button></form></section></main></html>';exit;
}
$date=(string)($_GET['date']??date('Y-m-d'));
$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
if(!$d || $d->format('Y-m-d')!==$date) fail('วันที่ไม่ถูกต้อง');
$room=(string)($_GET['room']??'');
$grade=(string)($_GET['grade']??'');
$states=['present'=>'มา','absent'=>'ขาด','leave'=>'ลา','late'=>'สาย'];
try{
 $db=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 $rows=$db->query($c['student_query'])->fetchAll(PDO::FETCH_ASSOC);
 $grades=[];$rooms=[];$students=[];
 foreach($rows as $r){
  foreach(['id','name','grade','room','number'] as $key) if(!isset($r[$key])) throw new RuntimeException('Mapping');
  $grades[(string)$r['grade']]=true;
  if((string)$r['grade']===$grade) $rooms[(string)$r['room']]=true;
  if($grade!=='' && $room!=='' && (string)$r['grade']===$grade && (string)$r['room']===$room){
   if(isset($students[(string)$r['id']])) throw new RuntimeException('Duplicate ID');
   $students[(string)$r['id']]=$r;
  }
 }
 uksort($grades,'strnatcmp');uksort($rooms,'strnatcmp');
 uasort($students,fn($a,$b)=>strnatcmp((string)$a['number'],(string)$b['number']));
 if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save'])){
  $values=$_POST['status']??[];
  if(!is_array($values)||!$students) fail('กรุณาเลือกห้องเรียน');
  foreach($values as $id=>$status) if(!isset($students[$id])||!is_string($status)||($status!==''&&!isset($states[$status]))) fail('ข้อมูลไม่ถูกต้อง');
  $db->beginTransaction();
  $q=$db->prepare('INSERT INTO swk_attendance_web(student_id,attendance_date,status) VALUES(?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status)');
  foreach($values as $id=>$status) if($status!=='') $q->execute([(string)$id,$date,$status]);
  $db->commit();
  header('Location: ?'.http_build_query(['date'=>$date,'grade'=>$grade,'room'=>$room,'saved'=>1]));exit;
 }
 $saved=[];
 if($students){
  $q=$db->prepare('SELECT student_id,status FROM swk_attendance_web WHERE attendance_date=? AND student_id IN ('.implode(',',array_fill(0,count($students),'?')).')');
  $q->execute(array_merge([$date],array_keys($students)));$saved=$q->fetchAll(PDO::FETCH_KEY_PAIR);
 }
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fail('ไม่สามารถอ่านหรือบันทึกข้อมูลได้ กรุณาตรวจสอบการตั้งค่าและฐานข้อมูล',503);}
page('ระบบเช็คชื่อนักเรียน SWK');
echo '<form method="post"><input type="hidden" name="csrf" value="'.h($csrf).'"><button name="logout">ออกจากระบบ</button></form><section><form method="get"><label>วันที่ <input type="date" name="date" value="'.h($date).'"></label><label>ชั้น <select name="grade" onchange="this.form.room.value=\'\';this.form.submit()"><option value="">เลือกชั้น</option>';
foreach($grades as $g=>$v)echo '<option value="'.h($g).'"'.((string)$g===$grade?' selected':'').'>'.h($g).'</option>';
echo '</select></label><label>ห้อง <select name="room"><option value="">เลือกห้อง</option>';
foreach($rooms as $r=>$v)echo '<option value="'.h($r).'"'.((string)$r===$room?' selected':'').'>'.h($r).'</option>';
echo '</select></label><button>แสดงรายชื่อ</button></form></section>';
if(isset($_GET['saved']))echo '<p>บันทึกข้อมูลแล้ว</p>';
if($students){
 $counts=array_fill_keys(array_keys($states),0);foreach($saved as $s)$counts[$s]++;
 echo '<section><p>ทั้งหมด '.count($students).' คน | ยังไม่เช็ค '.(count($students)-count($saved)).' คน</p><p>';
 foreach($states as $s=>$label)echo h($label).' '.$counts[$s].' คน &nbsp; ';
 echo '</p><form method="post"><input type="hidden" name="csrf" value="'.h($csrf).'"><div class="scroll"><table><thead><tr><th>เลขที่</th><th>ชื่อ-นามสกุล</th><th>สถานะ</th></tr></thead><tbody>';
 foreach($students as $id=>$r){
  echo '<tr><td>'.h($r['number']).'</td><td>'.h($r['name']).'</td><td><select name="status['.h($id).']"><option value="">ยังไม่เช็ค</option>';
  foreach($states as $s=>$label)echo '<option value="'.h($s).'"'.(($saved[$id]??'')===$s?' selected':'').'>'.h($label).'</option>';
  echo '</select></td></tr>';
 }
 echo '</tbody></table></div><p><button name="save">บันทึกการเช็คชื่อ</button></p></form></section>';
}else echo '<section>เลือกชั้นและห้องเรียนเพื่อแสดงรายชื่อ</section>';
echo '</main></html>';