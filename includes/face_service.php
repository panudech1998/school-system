<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
function face_health(): bool { $c=curl_init(rtrim(FACE_SERVICE_URL,'/').'/health');curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>4]);$b=curl_exec($c);$s=(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);return $b!==false&&$s===200; }
function face_index_photo(int $eventId,int $photoId,string $path): array { $payload=json_encode(['event_id'=>$eventId,'photo_id'=>$photoId,'path'=>$path],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$c=curl_init(rtrim(FACE_SERVICE_URL,'/').'/index');curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-Service-Token: '.FACE_SERVICE_TOKEN],CURLOPT_TIMEOUT=>300]);$b=curl_exec($c);$s=(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE);$e=curl_error($c);curl_close($c);if($b===false||$s>=400)throw new RuntimeException($e?:((string)$b?:'Face Service ไม่พร้อม'));return json_decode((string)$b,true,512,JSON_THROW_ON_ERROR); }
