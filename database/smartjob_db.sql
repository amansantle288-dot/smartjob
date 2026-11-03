CREATE DATABASE IF NOT EXISTS smartjob_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE smartjob_db;

CREATE TABLE IF NOT EXISTS tbl_system_settings (
  setting_key VARCHAR(100) NOT NULL,
  setting_value VARCHAR(255) NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student','employer','coordinator','admin') NOT NULL,
  status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  email_verified_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_institutions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  code VARCHAR(40) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_institutions_code (code),
  KEY idx_institutions_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_academic_programs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  institution_id BIGINT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (id),
  KEY idx_program_institution (institution_id),
  CONSTRAINT fk_program_institution FOREIGN KEY (institution_id) REFERENCES tbl_institutions(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_student_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  institution_id BIGINT UNSIGNED NULL,
  program_id BIGINT UNSIGNED NULL,
  program VARCHAR(160) NULL,
  year_level VARCHAR(40) NULL,
  bio TEXT NULL,
  preferred_location VARCHAR(180) NULL,
  preferred_work_mode ENUM('onsite','hybrid','remote','any') NOT NULL DEFAULT 'any',
  availability_date DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_profile_user (user_id),
  KEY idx_student_institution (institution_id),
  CONSTRAINT fk_student_profile_user FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_student_profile_institution FOREIGN KEY (institution_id) REFERENCES tbl_institutions(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_student_profile_program FOREIGN KEY (program_id) REFERENCES tbl_academic_programs(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_ojt_coordinators (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  institution_id BIGINT UNSIGNED NOT NULL,
  department VARCHAR(160) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (id),
  UNIQUE KEY uq_coordinator_user (user_id),
  KEY idx_coordinator_institution (institution_id),
  CONSTRAINT fk_coordinator_user FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_coordinator_institution FOREIGN KEY (institution_id) REFERENCES tbl_institutions(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_companies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(180) NOT NULL,
  company_type VARCHAR(40) NOT NULL DEFAULT 'other',
  description TEXT NULL,
  mission_statement TEXT NULL,
  vision_statement TEXT NULL,
  industry VARCHAR(120) NULL,
  website VARCHAR(255) NULL,
  address VARCHAR(255) NULL,
  verification_status ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  verified_by BIGINT UNSIGNED NULL,
  verified_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_companies_verification (verification_status, created_at),
  CONSTRAINT fk_company_verified_by FOREIGN KEY (verified_by) REFERENCES tbl_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_company_members (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  member_role ENUM('owner','manager','reviewer') NOT NULL DEFAULT 'owner',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_company_member (company_id, user_id),
  KEY idx_company_member_user (user_id, status),
  CONSTRAINT fk_company_member_company FOREIGN KEY (company_id) REFERENCES tbl_companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_company_member_user FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_company_verification_requirements (
  company_type VARCHAR(40) NOT NULL,
  document_type VARCHAR(80) NOT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  applicability ENUM('required','optional','not_applicable') NOT NULL DEFAULT 'optional',
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (company_type, document_type),
  KEY idx_company_requirement_type (company_type, is_required),
  CONSTRAINT fk_company_requirement_admin FOREIGN KEY (updated_by) REFERENCES tbl_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_company_verifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','approved','rejected','revision_requested') NOT NULL DEFAULT 'pending',
  reviewer_id BIGINT UNSIGNED NULL,
  reviewer_notes TEXT NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_company_verification (company_id),
  KEY idx_verification_status (status, created_at),
  CONSTRAINT fk_verification_company FOREIGN KEY (company_id) REFERENCES tbl_companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_verification_reviewer FOREIGN KEY (reviewer_id) REFERENCES tbl_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_company_document_batches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  batch_key CHAR(32) NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  uploaded_by BIGINT UNSIGNED NOT NULL,
  document_count INT UNSIGNED NOT NULL,
  total_bytes BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_company_document_batch_key (batch_key),
  KEY idx_company_document_batches (company_id, created_at),
  CONSTRAINT fk_company_document_batch_company FOREIGN KEY (company_id) REFERENCES tbl_companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_company_document_batch_user FOREIGN KEY (uploaded_by) REFERENCES tbl_users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_company_documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id BIGINT UNSIGNED NOT NULL,
  uploaded_by BIGINT UNSIGNED NOT NULL,
  document_type VARCHAR(80) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  storage_key CHAR(64) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  review_status ENUM('pending','under_review','approved','revision_requested') NOT NULL DEFAULT 'pending',
  reviewer_id BIGINT UNSIGNED NULL,
  reviewer_notes VARCHAR(1000) NULL,
  reviewed_at DATETIME NULL,
  previous_document_id BIGINT UNSIGNED NULL,
  batch_id BIGINT UNSIGNED NULL,
  version_number INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_company_document_key (storage_key),
  KEY idx_company_documents (company_id, review_status),
  KEY idx_company_documents_batch (batch_id),
  CONSTRAINT fk_company_document_company FOREIGN KEY (company_id) REFERENCES tbl_companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_company_document_user FOREIGN KEY (uploaded_by) REFERENCES tbl_users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_company_document_reviewer FOREIGN KEY (reviewer_id) REFERENCES tbl_users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_company_document_previous FOREIGN KEY (previous_document_id) REFERENCES tbl_company_documents(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_company_document_batch FOREIGN KEY (batch_id) REFERENCES tbl_company_document_batches(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_company_document_review_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id BIGINT UNSIGNED NOT NULL,
  reviewer_id BIGINT UNSIGNED NULL,
  action ENUM('uploaded','under_review','approved','revision_requested') NOT NULL,
  notes VARCHAR(1000) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_company_document_history (document_id, created_at),
  CONSTRAINT fk_company_document_history_document FOREIGN KEY (document_id) REFERENCES tbl_company_documents(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_company_document_history_reviewer FOREIGN KEY (reviewer_id) REFERENCES tbl_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_verification_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id BIGINT UNSIGNED NOT NULL,
  reviewer_id BIGINT UNSIGNED NOT NULL,
  decision ENUM('approved','rejected','revision_requested') NOT NULL,
  notes VARCHAR(1000) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_verification_log_company (company_id, created_at),
  CONSTRAINT fk_verification_log_company FOREIGN KEY (company_id) REFERENCES tbl_companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_verification_log_reviewer FOREIGN KEY (reviewer_id) REFERENCES tbl_users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_ojt_partner_approvals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id BIGINT UNSIGNED NOT NULL,
  institution_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  notes TEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ojt_partner_institution (company_id, institution_id),
  CONSTRAINT fk_partner_company FOREIGN KEY (company_id) REFERENCES tbl_companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_partner_institution FOREIGN KEY (institution_id) REFERENCES tbl_institutions(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_partner_reviewer FOREIGN KEY (reviewed_by) REFERENCES tbl_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_skill_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_skill_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_skills (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id BIGINT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_skill_name (name),
  CONSTRAINT fk_skill_category FOREIGN KEY (category_id) REFERENCES tbl_skill_categories(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_student_skills (
  student_id BIGINT UNSIGNED NOT NULL,
  skill_id BIGINT UNSIGNED NOT NULL,
  proficiency ENUM('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
  description TEXT NULL,
  years_experience DECIMAL(4,1) NULL,
  PRIMARY KEY (student_id, skill_id),
  CONSTRAINT fk_student_skill_user FOREIGN KEY (student_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_student_skill_skill FOREIGN KEY (skill_id) REFERENCES tbl_skills(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_internship_opportunities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  location VARCHAR(180) NULL,
  work_mode ENUM('onsite','hybrid','remote') NOT NULL DEFAULT 'onsite',
  slots SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  application_deadline DATE NOT NULL,
  published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  hiring_duration_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  status ENUM('draft','published','paused','closed') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_internship_listing (status, application_deadline, created_at),
  KEY idx_internship_published (status, published_at, id),
  KEY idx_internship_company (company_id, status),
  CONSTRAINT fk_internship_company FOREIGN KEY (company_id) REFERENCES tbl_companies(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_internship_creator FOREIGN KEY (created_by) REFERENCES tbl_users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_internship_skills (
  internship_id BIGINT UNSIGNED NOT NULL,
  skill_id BIGINT UNSIGNED NOT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (internship_id, skill_id),
  CONSTRAINT fk_internship_skill_opportunity FOREIGN KEY (internship_id) REFERENCES tbl_internship_opportunities(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_internship_skill_skill FOREIGN KEY (skill_id) REFERENCES tbl_skills(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_saved_internships (
  student_id BIGINT UNSIGNED NOT NULL,
  internship_id BIGINT UNSIGNED NOT NULL,
  saved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (student_id, internship_id),
  KEY idx_saved_internship (internship_id),
  CONSTRAINT fk_saved_internship_student FOREIGN KEY (student_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_saved_internship_opportunity FOREIGN KEY (internship_id) REFERENCES tbl_internship_opportunities(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_saved_companies (
  student_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  saved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (student_id, company_id),
  KEY idx_saved_company (company_id),
  CONSTRAINT fk_saved_company_student FOREIGN KEY (student_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_saved_company_profile FOREIGN KEY (company_id) REFERENCES tbl_companies(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_applications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  internship_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  cover_letter TEXT NULL,
  status ENUM('submitted','reviewing','shortlisted','interview_scheduled','accepted','rejected','withdrawn') NOT NULL DEFAULT 'submitted',
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  interview_at DATETIME NULL,
  interview_location VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_application_student_opportunity (internship_id, student_id),
  KEY idx_application_student (student_id, status, created_at),
  KEY idx_application_internship (internship_id, status),
  CONSTRAINT fk_application_internship FOREIGN KEY (internship_id) REFERENCES tbl_internship_opportunities(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_application_student FOREIGN KEY (student_id) REFERENCES tbl_users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_application_reviewer FOREIGN KEY (reviewed_by) REFERENCES tbl_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_application_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id BIGINT UNSIGNED NOT NULL,
  changed_by BIGINT UNSIGNED NOT NULL,
  status ENUM('submitted','reviewing','shortlisted','interview_scheduled','accepted','rejected','withdrawn') NOT NULL,
  note VARCHAR(1000) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_application_history (application_id, created_at),
  CONSTRAINT fk_application_history_application FOREIGN KEY (application_id) REFERENCES tbl_applications(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_application_history_user FOREIGN KEY (changed_by) REFERENCES tbl_users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_application_qualification_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id BIGINT UNSIGNED NOT NULL,
  reviewer_id BIGINT UNSIGNED NOT NULL,
  assessment ENUM('qualified','needs_improvement','not_qualified') NOT NULL,
  notes VARCHAR(1000) NOT NULL,
  missing_qualifications TEXT NULL,
  reviewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_qualification_review_application (application_id, id),
  CONSTRAINT fk_qualification_review_application FOREIGN KEY (application_id) REFERENCES tbl_applications(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_qualification_review_reviewer FOREIGN KEY (reviewer_id) REFERENCES tbl_users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_match_results (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id BIGINT UNSIGNED NOT NULL,
  internship_id BIGINT UNSIGNED NOT NULL,
  score DECIMAL(5,2) NOT NULL,
  explanation JSON NOT NULL,
  calculated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_match_student_internship (student_id, internship_id),
  KEY idx_match_score (student_id, score),
  CONSTRAINT fk_match_student FOREIGN KEY (student_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_match_internship FOREIGN KEY (internship_id) REFERENCES tbl_internship_opportunities(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_recommendations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id BIGINT UNSIGNED NOT NULL,
  internship_id BIGINT UNSIGNED NOT NULL,
  match_result_id BIGINT UNSIGNED NOT NULL,
  status ENUM('new','viewed','dismissed','applied') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_recommendation_student_internship (student_id, internship_id),
  CONSTRAINT fk_recommendation_student FOREIGN KEY (student_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_recommendation_internship FOREIGN KEY (internship_id) REFERENCES tbl_internship_opportunities(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_recommendation_match FOREIGN KEY (match_result_id) REFERENCES tbl_match_results(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_placements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id BIGINT UNSIGNED NOT NULL,
  coordinator_id BIGINT UNSIGNED NULL,
  status ENUM('pending','active','completed','terminated') NOT NULL DEFAULT 'pending',
  start_date DATE NULL,
  end_date DATE NULL,
  required_hours SMALLINT UNSIGNED NULL,
  completed_hours SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_placement_application (application_id),
  CONSTRAINT fk_placement_application FOREIGN KEY (application_id) REFERENCES tbl_applications(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_placement_coordinator FOREIGN KEY (coordinator_id) REFERENCES tbl_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_internship_progress (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  placement_id BIGINT UNSIGNED NOT NULL,
  submitted_by BIGINT UNSIGNED NOT NULL,
  progress_date DATE NOT NULL,
  hours_completed DECIMAL(5,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_progress_placement (placement_id, progress_date),
  CONSTRAINT fk_progress_placement FOREIGN KEY (placement_id) REFERENCES tbl_placements(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_progress_user FOREIGN KEY (submitted_by) REFERENCES tbl_users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  related_type VARCHAR(40) NULL,
  related_id BIGINT UNSIGNED NULL,
  read_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_user (user_id, read_at, created_at),
  KEY idx_notifications_related (related_type, related_id),
  CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_reports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  requested_by BIGINT UNSIGNED NOT NULL,
  report_type VARCHAR(80) NOT NULL,
  filters JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_report_user FOREIGN KEY (requested_by) REFERENCES tbl_users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  actor_role VARCHAR(30) NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id BIGINT UNSIGNED NULL,
  result ENUM('success','failure') NOT NULL DEFAULT 'success',
  description VARCHAR(500) NOT NULL DEFAULT 'Recorded event',
  ip_hash CHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_user_time (user_id, created_at),
  KEY idx_audit_entity (entity_type, entity_id, created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_admin_email_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id BIGINT UNSIGNED NULL,
  company_name VARCHAR(180) NOT NULL,
  recipient_email VARCHAR(190) NOT NULL,
  template_key VARCHAR(60) NOT NULL,
  subject VARCHAR(180) NOT NULL,
  message_body TEXT NOT NULL,
  delivery_status ENUM('sent','failed') NOT NULL,
  provider_name VARCHAR(40) NOT NULL,
  http_status_code SMALLINT UNSIGNED NULL,
  error_code VARCHAR(80) NULL,
  sent_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_email_company (company_id, created_at),
  KEY idx_admin_email_status (delivery_status, created_at),
  CONSTRAINT fk_admin_email_company FOREIGN KEY (company_id) REFERENCES tbl_companies(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_admin_email_sender FOREIGN KEY (sent_by) REFERENCES tbl_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_ai_writing_usage (
  user_id BIGINT UNSIGNED NOT NULL,
  usage_day DATE NOT NULL,
  request_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, usage_day),
  CONSTRAINT fk_ai_writing_usage_user FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_matching_configurations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  config_key VARCHAR(80) NOT NULL,
  config_value DECIMAL(7,4) NOT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_matching_config_key (config_key),
  CONSTRAINT fk_matching_config_user FOREIGN KEY (updated_by) REFERENCES tbl_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  ip_hash CHAR(64) NOT NULL,
  succeeded TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_attempt_limit (email, ip_hash, succeeded, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO tbl_skill_categories (name) VALUES
  ('Technical'), ('Communication'), ('Business'), ('Design'), ('Data');

INSERT IGNORE INTO tbl_system_settings (setting_key, setting_value) VALUES
  ('initial_admin_created', '0');

INSERT IGNORE INTO tbl_matching_configurations (config_key, config_value) VALUES
  ('skills_weight', 0.6000),
  ('location_weight', 0.2000),
  ('work_mode_weight', 0.1000),
  ('academic_weight', 0.1000);
