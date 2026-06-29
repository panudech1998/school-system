# School System Website

เว็บเพจโรงเรียนพร้อมระบบหน้าบ้านและหลังบ้าน ใช้ได้กับ PHP 8+, MySQL/MariaDB และ XAMPP

## ความสามารถหลัก

### หน้าบ้าน
- หน้าแรกโรงเรียน
- ข่าวประชาสัมพันธ์
- ประกาศโรงเรียน
- ข้อมูลโรงเรียน/วิสัยทัศน์/พันธกิจ
- บุคลากร
- แกลเลอรีภาพกิจกรรม
- ติดต่อโรงเรียน

### หลังบ้าน
- Dashboard สรุปจำนวนข่าว ประกาศ บุคลากร และอัลบั้มภาพ
- จัดการข่าวประชาสัมพันธ์ พร้อมรูปปก
- จัดการประกาศโรงเรียน
- จัดการบุคลากร พร้อมรูปประจำตัว
- จัดการอัลบั้มภาพและอัปโหลดรูปหลายรูป
- ตั้งค่าข้อมูลโรงเรียน เบอร์โทร อีเมล ที่อยู่ และลิงก์ Facebook
- จัดการผู้ใช้งานหลังบ้าน
- ระบบ Login ด้วย `password_hash()` และ `password_verify()`
- ป้องกัน SQL Injection ด้วย PDO Prepared Statement
- มี CSRF Token สำหรับฟอร์มหลังบ้าน

## วิธีติดตั้งบน XAMPP

1. คัดลอกโฟลเดอร์นี้ไปไว้ใน `C:\xampp\htdocs\school-system`
2. เปิด XAMPP แล้ว Start Apache และ MySQL
3. เปิด phpMyAdmin แล้วสร้างฐานข้อมูลชื่อ `school_system`
4. Import ไฟล์ `database/school_system.sql`
5. ตั้งค่าฐานข้อมูลที่ไฟล์ `config/app.php`
6. เปิดเว็บที่ `http://localhost/school-system/`
7. เข้าหลังบ้านที่ `http://localhost/school-system/login.php`

## บัญชีผู้ดูแลเริ่มต้น

- Email: `admin@school.local`
- Password: `12345678`

> ควรเปลี่ยนรหัสผ่านทันทีหลังติดตั้งใช้งานจริง

## การตั้งค่า BASE_URL

ถ้าวางโฟลเดอร์ไว้ที่ `htdocs/school-system` ให้ใช้ค่า:

```php
define('BASE_URL', '/school-system');
```

ถ้าวางไฟล์ไว้ที่ root ของเว็บโดยตรง ให้ใช้:

```php
define('BASE_URL', '');
```
