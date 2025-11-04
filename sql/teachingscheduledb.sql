CREATE TABLE academic_years (
  academic_year_id INT PRIMARY KEY AUTO_INCREMENT,
  academic_year INT,
  semester INT,
  start_date DATE,
  end_date DATE,
  is_active TINYINT,
  is_current TINYINT
);

CREATE TABLE classrooms (
  classroom_id INT PRIMARY KEY AUTO_INCREMENT,
  room_number VARCHAR(255),
  building VARCHAR(255),
  capacity INT
);

CREATE TABLE class_sessions (
  session_id INT PRIMARY KEY AUTO_INCREMENT,
  schedule_id INT,
  session_date DATE,
  actual_classroom_id INT,
  actual_start_time_slot_id INT,
  actual_end_time_slot_id INT,
  attendance_count INT,
  notes TEXT,
  created_at DATETIME,
  updated_at DATETIME,
  user_id INT,
  google_event_id VARCHAR(255),
  google_event_url TEXT,
  google_sync_status VARCHAR(255),
  google_sync_at DATETIME,
  google_sync_error TEXT,
  FOREIGN KEY (schedule_id) REFERENCES teaching_schedules(schedule_id),
  FOREIGN KEY (actual_classroom_id) REFERENCES classrooms(classroom_id),
  FOREIGN KEY (actual_start_time_slot_id) REFERENCES time_slots(time_slot_id),
  FOREIGN KEY (actual_end_time_slot_id) REFERENCES time_slots(time_slot_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE compensation_logs (
  cancellation_id INT PRIMARY KEY AUTO_INCREMENT,
  schedule_id INT,
  cancellation_date DATE,
  cancellation_type VARCHAR(255),
  reason VARCHAR(255),
  is_makeup_required TINYINT,
  makeup_date DATE,
  makeup_classroom_id INT,
  makeup_start_time_slot_id INT,
  makeup_end_time_slot_id INT,
  proposed_makeup_date DATE,
  proposed_makeup_classroom_id INT,
  proposed_makeup_start_time_slot_id INT,
  proposed_makeup_end_time_slot_id INT,
  change_reason TEXT,
  approval_notes TEXT,
  approved_by INT,
  approved_at DATETIME,
  rejected_reason TEXT,
  status VARCHAR(255),
  created_at DATETIME,
  updated_at DATETIME,
  user_id INT,
  google_event_id VARCHAR(255),
  google_event_url TEXT,
  google_sync_status VARCHAR(255),
  google_sync_at DATETIME,
  google_sync_error TEXT,
  FOREIGN KEY (schedule_id) REFERENCES teaching_schedules(schedule_id),
  FOREIGN KEY (makeup_classroom_id) REFERENCES classrooms(classroom_id),
  FOREIGN KEY (makeup_start_time_slot_id) REFERENCES time_slots(time_slot_id),
  FOREIGN KEY (makeup_end_time_slot_id) REFERENCES time_slots(time_slot_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  FOREIGN KEY (approved_by) REFERENCES users(user_id),
  FOREIGN KEY (proposed_makeup_classroom_id) REFERENCES classrooms(classroom_id),
  FOREIGN KEY (proposed_makeup_start_time_slot_id) REFERENCES time_slots(time_slot_id),
  FOREIGN KEY (proposed_makeup_end_time_slot_id) REFERENCES time_slots(time_slot_id)
);

CREATE TABLE compensation_status_history (
  history_id INT PRIMARY KEY AUTO_INCREMENT,
  cancellation_id INT,
  old_status VARCHAR(255),
  new_status VARCHAR(255),
  action_by INT,
  action_reason TEXT,
  created_at DATETIME,
  FOREIGN KEY (cancellation_id) REFERENCES compensation_logs(cancellation_id),
  FOREIGN KEY (action_by) REFERENCES users(user_id)
);

CREATE TABLE google_auth (
  google_auth_id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  google_access_token TEXT,
  google_refresh_token TEXT,
  google_id_token TEXT,
  token_expiry DATETIME,
  google_email VARCHAR(255),
  google_name VARCHAR(255),
  calendar_id VARCHAR(255),
  is_active TINYINT,
  last_checked DATETIME,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE modules (
  module_id INT PRIMARY KEY AUTO_INCREMENT,
  module_name VARCHAR(255),
  description TEXT
);

CREATE TABLE module_groups (
  group_id INT PRIMARY KEY AUTO_INCREMENT,
  module_id INT,
  group_name VARCHAR(255),
  FOREIGN KEY (module_id) REFERENCES modules(module_id)
);

CREATE TABLE module_group_year_levels (
  id INT PRIMARY KEY AUTO_INCREMENT,
  group_id INT,
  year_level_id INT,
  FOREIGN KEY (group_id) REFERENCES module_groups(group_id),
  FOREIGN KEY (year_level_id) REFERENCES year_levels(year_level_id)
);

CREATE TABLE public_holidays (
  holiday_id INT PRIMARY KEY AUTO_INCREMENT,
  academic_year INT,
  holiday_date DATE,
  holiday_name VARCHAR(255),
  holiday_type VARCHAR(255),
  is_active TINYINT,
  api_source VARCHAR(255),
  api_response_data LONGTEXT,
  created_at DATETIME,
  updated_at DATETIME,
  created_by INT,
  FOREIGN KEY (created_by) REFERENCES users(user_id)
);

CREATE TABLE subjects (
  subject_id INT PRIMARY KEY AUTO_INCREMENT,
  subject_code VARCHAR(255),
  subject_name VARCHAR(255),
  credits INT,
  subject_type VARCHAR(255)
);

CREATE TABLE teaching_schedules (
  schedule_id INT PRIMARY KEY AUTO_INCREMENT,
  academic_year_id INT,
  user_id INT,
  subject_id INT,
  year_level_id INT,
  classroom_id INT,
  day_of_week VARCHAR(255),
  start_time_slot_id INT,
  end_time_slot_id INT,
  is_external_subject TINYINT,
  is_active TINYINT,
  created_at DATETIME,
  updated_at DATETIME,
  created_by INT,
  co_user_id INT,
  co_user_id_2 INT,
  max_teachers INT,
  current_teachers INT,
  is_module_subject TINYINT,
  group_id INT,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(academic_year_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  FOREIGN KEY (subject_id) REFERENCES subjects(subject_id),
  FOREIGN KEY (year_level_id) REFERENCES year_levels(year_level_id),
  FOREIGN KEY (classroom_id) REFERENCES classrooms(classroom_id),
  FOREIGN KEY (start_time_slot_id) REFERENCES time_slots(time_slot_id),
  FOREIGN KEY (end_time_slot_id) REFERENCES time_slots(time_slot_id),
  FOREIGN KEY (created_by) REFERENCES users(user_id),
  FOREIGN KEY (co_user_id) REFERENCES users(user_id),
  FOREIGN KEY (co_user_id_2) REFERENCES users(user_id),
  FOREIGN KEY (group_id) REFERENCES module_groups(group_id)
);

CREATE TABLE time_slots (
  time_slot_id INT PRIMARY KEY AUTO_INCREMENT,
  slot_number INT,
  start_time TIME,
  end_time TIME
);

CREATE TABLE users (
  user_id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(255),
  password VARCHAR(255),
  title VARCHAR(255),
  name VARCHAR(255),
  lastname VARCHAR(255),
  cid VARCHAR(255),
  email VARCHAR(255),
  elogin_token TEXT,
  faccode VARCHAR(255),
  facname VARCHAR(255),
  depcode VARCHAR(255),
  depname VARCHAR(255),
  seccode VARCHAR(255),
  secname VARCHAR(255),
  user_type VARCHAR(255),
  is_active TINYINT,
  created_at DATETIME,
  last_login DATETIME
);

CREATE TABLE user_action_logs (
  log_id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  action VARCHAR(255),
  details TEXT,
  target_user_id INT,
  ip_address VARCHAR(255),
  user_agent TEXT,
  created_at TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  FOREIGN KEY (target_user_id) REFERENCES users(user_id)
);

CREATE TABLE year_levels (
  year_level_id INT PRIMARY KEY AUTO_INCREMENT,
  department VARCHAR(255),
  class_year VARCHAR(255),
  curriculum VARCHAR(255)
);