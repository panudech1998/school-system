CREATE TABLE IF NOT EXISTS swk_attendance_web (
 student_id VARCHAR(100) COLLATE utf8mb4_bin NOT NULL,
 attendance_date DATE NOT NULL,
 status ENUM('present','absent','leave','late') NOT NULL,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(student_id,attendance_date),
 INDEX(attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;