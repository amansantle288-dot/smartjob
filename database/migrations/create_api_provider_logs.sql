USE smartjob_db;

CREATE TABLE IF NOT EXISTS tbl_api_provider_logs (
  api_log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider_name VARCHAR(40) NOT NULL,
  service_type ENUM('email','sms') NOT NULL,
  request_reference CHAR(36) NOT NULL,
  response_status ENUM('success','failure') NOT NULL,
  http_status_code SMALLINT UNSIGNED NULL,
  error_code VARCHAR(80) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (api_log_id),
  UNIQUE KEY uq_api_provider_request_reference (request_reference),
  KEY idx_api_provider_service_status (service_type, response_status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
