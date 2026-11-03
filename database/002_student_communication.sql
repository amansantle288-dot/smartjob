USE smartjob_db;

CREATE TABLE IF NOT EXISTS tbl_student_biodata (
  user_id BIGINT UNSIGNED NOT NULL,
  date_of_birth DATE NULL,
  phone VARCHAR(40) NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(120) NULL,
  province VARCHAR(120) NULL,
  summary TEXT NULL,
  template ENUM('standard','modern') NOT NULL DEFAULT 'standard',
  visibility JSON NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_biodata_user FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_student_education (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  school_name VARCHAR(190) NOT NULL,
  program VARCHAR(160) NOT NULL,
  department VARCHAR(160) NULL,
  year_level VARCHAR(40) NULL,
  achievements TEXT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_education_student (user_id, start_date),
  CONSTRAINT fk_education_user FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_student_experiences (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  organization VARCHAR(180) NULL,
  description TEXT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_experience_student (user_id, start_date),
  CONSTRAINT fk_experience_user FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_student_projects (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  role VARCHAR(120) NULL,
  description TEXT NULL,
  technologies VARCHAR(500) NULL,
  project_url VARCHAR(500) NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_project_student (user_id, start_date),
  CONSTRAINT fk_project_user FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_biodata_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  template_key VARCHAR(40) NOT NULL,
  name VARCHAR(100) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_biodata_template_key (template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_student_credentials (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  category ENUM('resume','biodata','enrollment','endorsement','training_certificate','seminar_certificate','academic_record','portfolio','internship_requirement','other') NOT NULL,
  description VARCHAR(500) NULL,
  original_name VARCHAR(255) NOT NULL,
  storage_key CHAR(64) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  issued_at DATE NULL,
  expires_at DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_credential_storage_key (storage_key),
  KEY idx_credential_owner_category (user_id, category, created_at),
  CONSTRAINT fk_credential_user FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_application_documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id BIGINT UNSIGNED NOT NULL,
  credential_id BIGINT UNSIGNED NOT NULL,
  shared_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_application_credential (application_id, credential_id),
  CONSTRAINT fk_application_document_application FOREIGN KEY (application_id) REFERENCES tbl_applications(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_application_document_credential FOREIGN KEY (credential_id) REFERENCES tbl_student_credentials(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_application_biodata (
  application_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  shared_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (application_id),
  KEY idx_shared_biodata_student (student_id, shared_at),
  CONSTRAINT fk_shared_biodata_application FOREIGN KEY (application_id) REFERENCES tbl_applications(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_shared_biodata_student FOREIGN KEY (student_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_application_document_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id BIGINT UNSIGNED NOT NULL,
  credential_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  storage_key CHAR(64) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_application_document_snapshot (application_id, credential_id),
  UNIQUE KEY uq_application_snapshot_storage_key (storage_key),
  KEY idx_application_snapshot_application (application_id, created_at),
  CONSTRAINT fk_application_snapshot_application FOREIGN KEY (application_id) REFERENCES tbl_applications(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_application_snapshot_credential FOREIGN KEY (credential_id) REFERENCES tbl_student_credentials(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_student_trainings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  credential_id BIGINT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  category VARCHAR(100) NULL,
  provider VARCHAR(180) NULL,
  description TEXT NULL,
  start_date DATE NULL,
  completion_date DATE NULL,
  duration_hours DECIMAL(7,2) NULL,
  acquired_skills JSON NULL,
  certificate_reference VARCHAR(120) NULL,
  verification_status ENUM('self_reported','pending','verified','rejected') NOT NULL DEFAULT 'self_reported',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_training_student_status (user_id, verification_status),
  CONSTRAINT fk_training_user FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_training_credential FOREIGN KEY (credential_id) REFERENCES tbl_student_credentials(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_student_certifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  credential_id BIGINT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  issuer VARCHAR(180) NULL,
  issued_at DATE NULL,
  expires_at DATE NULL,
  credential_number VARCHAR(120) NULL,
  verification_status ENUM('self_reported','pending','verified','rejected') NOT NULL DEFAULT 'self_reported',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_certification_student (user_id, issued_at),
  CONSTRAINT fk_certification_user FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_certification_credential FOREIGN KEY (credential_id) REFERENCES tbl_student_credentials(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_training_verifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  training_id BIGINT UNSIGNED NOT NULL,
  reviewer_id BIGINT UNSIGNED NOT NULL,
  status ENUM('verified','rejected') NOT NULL,
  notes VARCHAR(1000) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_training_verification (training_id, created_at),
  CONSTRAINT fk_training_verification_training FOREIGN KEY (training_id) REFERENCES tbl_student_trainings(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_training_verification_reviewer FOREIGN KEY (reviewer_id) REFERENCES tbl_users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_conversations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id BIGINT UNSIGNED NULL,
  subject VARCHAR(180) NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_conversation_application (application_id, updated_at),
  CONSTRAINT fk_conversation_application FOREIGN KEY (application_id) REFERENCES tbl_applications(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_conversation_creator FOREIGN KEY (created_by) REFERENCES tbl_users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_conversation_participants (
  conversation_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  last_read_at DATETIME NULL,
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (conversation_id, user_id),
  KEY idx_participant_user (user_id, conversation_id),
  CONSTRAINT fk_participant_conversation FOREIGN KEY (conversation_id) REFERENCES tbl_conversations(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_participant_user FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id BIGINT UNSIGNED NOT NULL,
  sender_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_message_thread (conversation_id, created_at),
  CONSTRAINT fk_message_conversation FOREIGN KEY (conversation_id) REFERENCES tbl_conversations(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_message_sender FOREIGN KEY (sender_id) REFERENCES tbl_users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_message_attachments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  message_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  storage_key CHAR(64) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_message_attachment_key (storage_key),
  CONSTRAINT fk_message_attachment_message FOREIGN KEY (message_id) REFERENCES tbl_messages(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_message_reports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  message_id BIGINT UNSIGNED NOT NULL,
  reporter_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(1000) NOT NULL,
  status ENUM('open','reviewed','closed') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_message_report_status (status, created_at),
  CONSTRAINT fk_message_report_message FOREIGN KEY (message_id) REFERENCES tbl_messages(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_message_report_user FOREIGN KEY (reporter_id) REFERENCES tbl_users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_email_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sender_id BIGINT UNSIGNED NOT NULL,
  recipient_email VARCHAR(190) NOT NULL,
  subject VARCHAR(250) NOT NULL,
  body TEXT NOT NULL,
  provider VARCHAR(40) NULL,
  provider_message_id VARCHAR(255) NULL,
  status ENUM('draft','queued','sent','delivered','failed') NOT NULL DEFAULT 'draft',
  error_message VARCHAR(1000) NULL,
  application_id BIGINT UNSIGNED NULL,
  submitted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_email_sender_status (sender_id, status, created_at),
  CONSTRAINT fk_email_sender FOREIGN KEY (sender_id) REFERENCES tbl_users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_email_application FOREIGN KEY (application_id) REFERENCES tbl_applications(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_email_attachments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email_id BIGINT UNSIGNED NOT NULL,
  credential_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email_credential (email_id, credential_id),
  CONSTRAINT fk_email_attachment_email FOREIGN KEY (email_id) REFERENCES tbl_email_messages(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_email_attachment_credential FOREIGN KEY (credential_id) REFERENCES tbl_student_credentials(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_email_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  template_key VARCHAR(80) NOT NULL,
  name VARCHAR(120) NOT NULL,
  subject VARCHAR(250) NOT NULL,
  body TEXT NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email_template_key (template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_email_delivery_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email_id BIGINT UNSIGNED NOT NULL,
  status ENUM('queued','sent','delivered','failed') NOT NULL,
  provider_message_id VARCHAR(255) NULL,
  details VARCHAR(1000) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_email_delivery_log (email_id, created_at),
  CONSTRAINT fk_email_delivery_message FOREIGN KEY (email_id) REFERENCES tbl_email_messages(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_integration_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  integration_key VARCHAR(80) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  public_config JSON NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_integration_key (integration_key),
  CONSTRAINT fk_integration_setting_user FOREIGN KEY (updated_by) REFERENCES tbl_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_notification_preferences (
  user_id BIGINT UNSIGNED NOT NULL,
  email_enabled TINYINT(1) NOT NULL DEFAULT 1,
  internal_enabled TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_notification_preference_user FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO tbl_biodata_templates (template_key, name) VALUES
  ('standard', 'Standard Internship Biodata'),
  ('modern', 'Modern Professional Biodata');

INSERT IGNORE INTO tbl_email_templates (template_key, name, subject, body) VALUES
  ('internship_inquiry', 'Internship inquiry', 'Internship inquiry', 'Hello, I am interested in learning more about your internship opportunity.'),
  ('application_confirmation', 'Application confirmation', 'Application received', 'Thank you. We have received your application and will review it.'),
  ('interview_invitation', 'Interview invitation', 'Interview invitation', 'We would like to invite you to discuss your internship application.'),
  ('application_acceptance', 'Application acceptance', 'Application update', 'We are pleased to share an update about your application.'),
  ('application_rejection', 'Application decision', 'Application update', 'Thank you for your application. We are unable to proceed at this time.'),
  ('missing_document', 'Missing document request', 'Additional documents requested', 'Please provide the following document(s) for your application.'),
  ('company_verification', 'Company verification result', 'Company verification update', 'There is an update regarding your company verification.'),
  ('placement_approval', 'OJT placement approval', 'Placement approval update', 'There is an update regarding your OJT placement.'),
  ('internship_completion', 'Internship completion', 'Internship completion update', 'Congratulations on completing your internship.');
