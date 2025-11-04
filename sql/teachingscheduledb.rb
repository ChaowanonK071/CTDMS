Table academic_years {
  academic_year_id int [pk, increment]
  academic_year int
  semester int
  start_date date
  end_date date
  is_active tinyint
  is_current tinyint
}

Table classrooms {
  classroom_id int [pk, increment]
  room_number varchar
  building varchar
  capacity int
}

Table class_sessions {
  session_id int [pk, increment]
  schedule_id int
  session_date date
  actual_classroom_id int
  actual_start_time_slot_id int
  actual_end_time_slot_id int
  attendance_count int
  notes text
  created_at datetime
  updated_at datetime
  user_id int
  google_event_id varchar
  google_event_url text
  google_sync_status varchar
  google_sync_at datetime
  google_sync_error text
}

Table compensation_logs {
  cancellation_id int [pk, increment]
  schedule_id int
  cancellation_date date
  cancellation_type varchar
  reason varchar
  is_makeup_required tinyint
  makeup_date date
  makeup_classroom_id int
  makeup_start_time_slot_id int
  makeup_end_time_slot_id int
  proposed_makeup_date date
  proposed_makeup_classroom_id int
  proposed_makeup_start_time_slot_id int
  proposed_makeup_end_time_slot_id int
  change_reason text
  approval_notes text
  approved_by int
  approved_at datetime
  rejected_reason text
  status varchar
  created_at datetime
  updated_at datetime
  user_id int
  google_event_id varchar
  google_event_url text
  google_sync_status varchar
  google_sync_at datetime
  google_sync_error text
}

Table compensation_status_history {
  history_id int [pk, increment]
  cancellation_id int
  old_status varchar
  new_status varchar
  action_by int
  action_reason text
  created_at datetime
}

Table google_auth {
  google_auth_id int [pk, increment]
  user_id int
  google_access_token text
  google_refresh_token text
  google_id_token text
  token_expiry datetime
  google_email varchar
  google_name varchar
  calendar_id varchar
  is_active tinyint
  last_checked datetime
  created_at timestamp
  updated_at timestamp
}

Table modules {
  module_id int [pk, increment]
  module_name varchar
  description text
}

Table module_groups {
  group_id int [pk, increment]
  module_id int
  group_name varchar
}

Table module_group_year_levels {
  id int [pk, increment]
  group_id int
  year_level_id int
}

Table public_holidays {
  holiday_id int [pk, increment]
  academic_year int
  holiday_date date
  holiday_name varchar
  holiday_type varchar
  is_active tinyint
  api_source varchar
  api_response_data longtext
  created_at datetime
  updated_at datetime
  created_by int
}

Table subjects {
  subject_id int [pk, increment]
  subject_code varchar
  subject_name varchar
  credits int
  subject_type varchar
}

Table teaching_schedules {
  schedule_id int [pk, increment]
  academic_year_id int
  user_id int
  subject_id int
  year_level_id int
  classroom_id int
  day_of_week varchar
  start_time_slot_id int
  end_time_slot_id int
  is_external_subject tinyint
  is_active tinyint
  created_at datetime
  updated_at datetime
  created_by int
  co_user_id int
  co_user_id_2 int
  max_teachers int
  current_teachers int
  is_module_subject tinyint
  group_id int
}

Table time_slots {
  time_slot_id int [pk, increment]
  slot_number int
  start_time time
  end_time time
}

Table users {
  user_id int [pk, increment]
  username varchar
  password varchar
  title varchar
  name varchar
  lastname varchar
  cid varchar
  email varchar
  elogin_token text
  faccode varchar
  facname varchar
  depcode varchar
  depname varchar
  seccode varchar
  secname varchar
  user_type varchar
  is_active tinyint
  created_at datetime
  last_login datetime
}

Table user_action_logs {
  log_id int [pk, increment]
  user_id int
  action varchar
  details text
  target_user_id int
  ip_address varchar
  user_agent text
  created_at timestamp
}

Table year_levels {
  year_level_id int [pk, increment]
  department varchar
  class_year varchar
  curriculum varchar
}

/* Foreign keys / relationships */
Ref: class_sessions.schedule_id > teaching_schedules.schedule_id
Ref: class_sessions.actual_classroom_id > classrooms.classroom_id
Ref: class_sessions.actual_start_time_slot_id > time_slots.time_slot_id
Ref: class_sessions.actual_end_time_slot_id > time_slots.time_slot_id
Ref: class_sessions.user_id > users.user_id

Ref: compensation_logs.schedule_id > teaching_schedules.schedule_id
Ref: compensation_logs.makeup_classroom_id > classrooms.classroom_id
Ref: compensation_logs.makeup_start_time_slot_id > time_slots.time_slot_id
Ref: compensation_logs.makeup_end_time_slot_id > time_slots.time_slot_id
Ref: compensation_logs.user_id > users.user_id
Ref: compensation_logs.approved_by > users.user_id
Ref: compensation_logs.proposed_makeup_classroom_id > classrooms.classroom_id
Ref: compensation_logs.proposed_makeup_start_time_slot_id > time_slots.time_slot_id
Ref: compensation_logs.proposed_makeup_end_time_slot_id > time_slots.time_slot_id

Ref: compensation_status_history.cancellation_id > compensation_logs.cancellation_id
Ref: compensation_status_history.action_by > users.user_id

Ref: google_auth.user_id > users.user_id

Ref: module_groups.module_id > modules.module_id

Ref: module_group_year_levels.group_id > module_groups.group_id
Ref: module_group_year_levels.year_level_id > year_levels.year_level_id

Ref: public_holidays.created_by > users.user_id

Ref: teaching_schedules.co_user_id > users.user_id
Ref: teaching_schedules.co_user_id_2 > users.user_id
Ref: teaching_schedules.group_id > module_groups.group_id
Ref: teaching_schedules.academic_year_id > academic_years.academic_year_id
Ref: teaching_schedules.user_id > users.user_id
Ref: teaching_schedules.subject_id > subjects.subject_id
Ref: teaching_schedules.classroom_id > classrooms.classroom_id
Ref: teaching_schedules.year_level_id > year_levels.year_level_id
Ref: teaching_schedules.start_time_slot_id > time_slots.time_slot_id
Ref: teaching_schedules.end_time_slot_id > time_slots.time_slot_id
Ref: teaching_schedules.created_by > users.user_id

Ref: user_action_logs.user_id > users.user_id
