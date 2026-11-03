USE smartjob_db;

ALTER TABLE tbl_student_educational_attainment
  ADD COLUMN IF NOT EXISTS elementary_honors TEXT NULL AFTER elementary_year_graduated,
  ADD COLUMN IF NOT EXISTS junior_high_honors TEXT NULL AFTER junior_high_year_completed,
  ADD COLUMN IF NOT EXISTS senior_high_honors TEXT NULL AFTER senior_high_year_graduated,
  ADD COLUMN IF NOT EXISTS senior_high_not_applicable TINYINT(1) NOT NULL DEFAULT 0 AFTER senior_high_honors;

ALTER TABLE tbl_student_education
  ADD COLUMN IF NOT EXISTS education_level ENUM('primary','junior_high','senior_high','tertiary') NOT NULL DEFAULT 'tertiary' AFTER user_id,
  ADD COLUMN IF NOT EXISTS location VARCHAR(255) NULL AFTER school_name,
  ADD COLUMN IF NOT EXISTS major VARCHAR(160) NULL AFTER department,
  ADD COLUMN IF NOT EXISTS expected_graduation_year YEAR NULL AFTER end_date,
  ADD COLUMN IF NOT EXISTS academic_status ENUM('currently_enrolled','graduated','undergraduate','transferred','on_leave') NOT NULL DEFAULT 'undergraduate' AFTER expected_graduation_year,
  ADD COLUMN IF NOT EXISTS is_current TINYINT(1) NOT NULL DEFAULT 0 AFTER academic_status;

UPDATE tbl_student_education e
JOIN tbl_student_educational_attainment a ON a.user_id=e.user_id
SET e.education_level='tertiary',
    e.location=COALESCE(e.location, a.tertiary_location),
    e.major=COALESCE(e.major, a.tertiary_course),
    e.year_level=COALESCE(e.year_level, a.tertiary_year_level),
    e.expected_graduation_year=CASE
      WHEN e.end_date IS NULL AND e.expected_graduation_year IS NULL THEN a.tertiary_year_graduated
      ELSE e.expected_graduation_year
    END,
    e.is_current=CASE
      WHEN LOWER(TRIM(e.school_name))=LOWER(TRIM(a.tertiary_school))
       AND LOWER(TRIM(e.program))=LOWER(TRIM(COALESCE(a.tertiary_course, e.program)))
       AND e.id=(SELECT MAX(e2.id) FROM tbl_student_education e2
                 WHERE e2.user_id=e.user_id AND e2.education_level='tertiary'
                   AND LOWER(TRIM(e2.school_name))=LOWER(TRIM(a.tertiary_school))
                   AND LOWER(TRIM(e2.program))=LOWER(TRIM(COALESCE(a.tertiary_course, e2.program))))
       AND e.end_date IS NULL AND a.tertiary_year_level IS NOT NULL THEN 1
      ELSE e.is_current
    END,
    e.academic_status=CASE
      WHEN LOWER(TRIM(e.school_name))=LOWER(TRIM(a.tertiary_school))
       AND LOWER(TRIM(e.program))=LOWER(TRIM(COALESCE(a.tertiary_course, e.program)))
       AND e.end_date IS NULL AND a.tertiary_year_level IS NOT NULL THEN 'currently_enrolled'
      ELSE e.academic_status
    END
WHERE e.education_level='tertiary'
  AND LOWER(TRIM(e.school_name))=LOWER(TRIM(a.tertiary_school))
  AND LOWER(TRIM(e.program))=LOWER(TRIM(COALESCE(a.tertiary_course, e.program)));

INSERT INTO tbl_student_education
  (user_id, education_level, school_name, location, program, department, major, year_level, achievements, start_date, end_date, expected_graduation_year, academic_status, is_current)
SELECT a.user_id, 'tertiary', a.tertiary_school, a.tertiary_location, COALESCE(NULLIF(a.tertiary_course, ''), 'Not specified'),
       NULL, NULL, a.tertiary_year_level, NULL,
       CASE WHEN a.tertiary_year_started IS NULL THEN NULL ELSE STR_TO_DATE(CONCAT(a.tertiary_year_started, '-01-01'), '%Y-%m-%d') END,
       CASE WHEN a.tertiary_year_level IS NULL AND a.tertiary_year_graduated IS NOT NULL
            THEN STR_TO_DATE(CONCAT(a.tertiary_year_graduated, '-01-01'), '%Y-%m-%d') ELSE NULL END,
       CASE WHEN a.tertiary_year_level IS NOT NULL THEN a.tertiary_year_graduated ELSE NULL END,
       CASE WHEN a.tertiary_year_level IS NOT NULL THEN 'currently_enrolled' ELSE 'graduated' END,
       CASE WHEN a.tertiary_year_level IS NOT NULL THEN 1 ELSE 0 END
FROM tbl_student_educational_attainment a
WHERE a.tertiary_school IS NOT NULL AND a.tertiary_school <> ''
  AND NOT EXISTS (
    SELECT 1 FROM tbl_student_education e
    WHERE e.user_id=a.user_id
      AND e.education_level='tertiary'
      AND LOWER(TRIM(e.school_name))=LOWER(TRIM(a.tertiary_school))
      AND LOWER(TRIM(e.program))=LOWER(TRIM(COALESCE(a.tertiary_course, e.program)))
  );

UPDATE tbl_student_profiles sp
JOIN tbl_student_education e ON e.user_id=sp.user_id AND e.education_level='tertiary' AND e.is_current=1
SET sp.program=e.program, sp.year_level=e.year_level;
