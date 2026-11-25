<?php
// Copy outside web root and set SWK_ATTENDANCE_CONFIG to its absolute path.
return [
 'dsn'=>'mysql:host=127.0.0.1;dbname=ischool_swk;charset=utf8mb4',
 'db_user'=>'', 'db_password'=>'',
 'operator'=>'', 'password_hash'=>'',
 // REQUIRED: supply a SELECT using verified existing schema.
 // Return aliases id, name, grade, room, number; exclude inactive students.
 'student_query'=>'',
];