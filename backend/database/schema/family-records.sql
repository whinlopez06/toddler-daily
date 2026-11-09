CREATE TABLE pilot_invitations (
 id char(26) PRIMARY KEY, email varchar(254) NOT NULL, token_hash char(64) NOT NULL UNIQUE,
 expires_at timestamptz NOT NULL, accepted_at timestamptz, created_at timestamptz, updated_at timestamptz
);
CREATE TABLE families (
 id char(26) PRIMARY KEY, owner_user_id char(26) NOT NULL UNIQUE REFERENCES users(id),
 name varchar(100) NOT NULL, timezone varchar(64) NOT NULL DEFAULT 'Asia/Manila',
 deletion_requested_at timestamptz, version integer NOT NULL DEFAULT 1 CHECK(version > 0),
 created_at timestamptz, updated_at timestamptz
);
CREATE TABLE children (
 id char(26) PRIMARY KEY, family_id char(26) NOT NULL REFERENCES families(id),
 display_name varchar(100) NOT NULL, birth_date date NOT NULL, archived_at timestamptz,
 version integer NOT NULL DEFAULT 1 CHECK(version > 0), created_at timestamptz, updated_at timestamptz
);
CREATE INDEX children_family_active ON children(family_id, archived_at);
CREATE TABLE timeline_entries (
 id char(26) PRIMARY KEY, child_id char(26) NOT NULL REFERENCES children(id),
 created_by char(26) NOT NULL REFERENCES users(id), version integer NOT NULL DEFAULT 1 CHECK(version > 0),
 kind varchar(20) NOT NULL CHECK(kind IN ('memory','milestone','health_note')), category varchar(50),
 title varchar(160) NOT NULL, description text, entry_date date NOT NULL, deleted_at timestamptz,
 created_at timestamptz, updated_at timestamptz
);
CREATE INDEX timeline_child_date ON timeline_entries(child_id, entry_date, id);
CREATE TABLE growth_measurements (
 id char(26) PRIMARY KEY, child_id char(26) NOT NULL REFERENCES children(id),
 created_by char(26) NOT NULL REFERENCES users(id), version integer NOT NULL DEFAULT 1 CHECK(version > 0),
 measured_on date NOT NULL, weight_value numeric(8,3), weight_unit varchar(4),
 height_value numeric(8,2), height_unit varchar(4), notes text, deleted_at timestamptz,
 created_at timestamptz, updated_at timestamptz,
 CHECK(weight_value IS NOT NULL OR height_value IS NOT NULL),
 CHECK((weight_value IS NULL AND weight_unit IS NULL) OR (weight_value IS NOT NULL AND weight_value > 0 AND weight_unit IS NOT NULL AND weight_unit IN ('kg','lb'))),
 CHECK((height_value IS NULL AND height_unit IS NULL) OR (height_value IS NOT NULL AND height_value > 0 AND height_unit IS NOT NULL AND height_unit IN ('cm','in')))
);
CREATE INDEX growth_child_date ON growth_measurements(child_id, measured_on, id);
CREATE TABLE medications (
 id char(26) PRIMARY KEY, child_id char(26) NOT NULL REFERENCES children(id),
 created_by char(26) NOT NULL REFERENCES users(id), version integer NOT NULL DEFAULT 1 CHECK(version > 0),
 name varchar(150) NOT NULL, instructed_amount numeric(12,3), dose_unit varchar(32), instructions text,
 start_date date, end_date date, archived_at timestamptz, created_at timestamptz, updated_at timestamptz,
 UNIQUE(id, child_id), CHECK(end_date IS NULL OR start_date IS NULL OR end_date >= start_date),
 CHECK((instructed_amount IS NULL AND dose_unit IS NULL) OR (instructed_amount IS NOT NULL AND instructed_amount > 0 AND dose_unit IS NOT NULL))
);
CREATE TABLE medication_intakes (
 id char(26) PRIMARY KEY, child_id char(26) NOT NULL REFERENCES children(id), medication_id char(26) NOT NULL,
 administered_at timestamptz NOT NULL, timezone varchar(64) NOT NULL, actual_amount numeric(12,3), dose_unit varchar(32),
 snapshot text NOT NULL, notes text, state varchar(12) NOT NULL DEFAULT 'recorded' CHECK(state IN ('recorded','voided')),
 correction_of_id char(26) UNIQUE, correction_reason varchar(500), client_request_id uuid NOT NULL,
 request_hash char(64) NOT NULL, created_by char(26) NOT NULL REFERENCES users(id), created_at timestamptz NOT NULL DEFAULT now(),
 UNIQUE(child_id, client_request_id), UNIQUE(id, child_id, medication_id),
 FOREIGN KEY(medication_id, child_id) REFERENCES medications(id, child_id),
 FOREIGN KEY(correction_of_id, child_id, medication_id) REFERENCES medication_intakes(id, child_id, medication_id) DEFERRABLE INITIALLY DEFERRED,
 CHECK((actual_amount IS NULL AND dose_unit IS NULL) OR (actual_amount IS NOT NULL AND actual_amount > 0 AND dose_unit IS NOT NULL)),
 CHECK(correction_of_id IS NULL OR correction_reason IS NOT NULL), CHECK(state <> 'voided' OR correction_of_id IS NOT NULL)
);
CREATE INDEX intake_medication_date ON medication_intakes(medication_id, administered_at, id);
CREATE OR REPLACE FUNCTION app.freeze_medication_intake() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF TG_OP = 'DELETE' AND current_setting('app.privacy_purge', true) = 'on' THEN RETURN OLD; END IF;
 RAISE EXCEPTION 'Medication intake history is immutable; append a correction';
END; $$;
CREATE TRIGGER immutable_intakes BEFORE UPDATE OR DELETE ON medication_intakes FOR EACH ROW EXECUTE FUNCTION app.freeze_medication_intake();
CREATE TABLE vaccination_records (
 id char(26) PRIMARY KEY, child_id char(26) NOT NULL REFERENCES children(id),
 created_by char(26) NOT NULL REFERENCES users(id), version integer NOT NULL DEFAULT 1 CHECK(version > 0),
 vaccine_name varchar(150) NOT NULL, administered_on date NOT NULL, dose_label varchar(50), provider varchar(160),
 notes text, deleted_at timestamptz, created_at timestamptz, updated_at timestamptz
);
CREATE INDEX vaccinations_child_date ON vaccination_records(child_id, administered_on, id);
CREATE TABLE appointments (
 id char(26) PRIMARY KEY, child_id char(26) NOT NULL REFERENCES children(id),
 created_by char(26) NOT NULL REFERENCES users(id), version integer NOT NULL DEFAULT 1 CHECK(version > 0),
 title varchar(160) NOT NULL, kind varchar(20) NOT NULL DEFAULT 'general' CHECK(kind IN ('general','vaccination')),
 starts_at timestamptz NOT NULL, timezone varchar(64) NOT NULL, provider varchar(160), clinic varchar(160),
 reason text, visit_summary text, status varchar(20) NOT NULL DEFAULT 'scheduled' CHECK(status IN ('scheduled','completed','cancelled')),
 reminders_enabled boolean NOT NULL DEFAULT true, schedule_version integer NOT NULL DEFAULT 1 CHECK(schedule_version > 0),
 created_at timestamptz, updated_at timestamptz
);
CREATE INDEX appointments_child_status ON appointments(child_id, status, starts_at);
CREATE TABLE reminder_deliveries (
 id char(26) PRIMARY KEY, appointment_id char(26) NOT NULL REFERENCES appointments(id), user_id char(26) NOT NULL REFERENCES users(id),
 schedule_version integer NOT NULL, lead_minutes smallint NOT NULL CHECK(lead_minutes IN (60,1440)),
 channel varchar(10) NOT NULL CHECK(channel IN ('in_app','email')), due_at timestamptz NOT NULL, expires_at timestamptz NOT NULL,
 status varchar(20) NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','processing','accepted','failed','cancelled','expired')),
 attempts smallint NOT NULL DEFAULT 0 CHECK(attempts >= 0), next_attempt_at timestamptz, locked_until timestamptz,
 provider_message_id varchar(255), last_error_code varchar(80), created_at timestamptz, updated_at timestamptz,
 UNIQUE(appointment_id, schedule_version, user_id, lead_minutes, channel)
);
CREATE INDEX delivery_due ON reminder_deliveries(status, next_attempt_at, due_at);
CREATE TABLE notifications (
 id char(26) PRIMARY KEY, user_id char(26) NOT NULL REFERENCES users(id),
 reminder_delivery_id char(26) NOT NULL UNIQUE REFERENCES reminder_deliveries(id),
 type varchar(40) NOT NULL, data jsonb NOT NULL, read_at timestamptz, created_at timestamptz, updated_at timestamptz
);
CREATE INDEX notifications_user_read ON notifications(user_id, read_at, created_at);
CREATE TABLE audit_logs (
 id char(26) PRIMARY KEY, family_id char(26) NOT NULL REFERENCES families(id), actor_user_id char(26) NOT NULL REFERENCES users(id),
 entity_type varchar(50) NOT NULL, entity_id char(26) NOT NULL, action varchar(30) NOT NULL, changes_ciphertext text,
 created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX audit_family_date ON audit_logs(family_id, created_at);
CREATE TABLE deletion_requests (
 id char(26) PRIMARY KEY, family_id char(26) NOT NULL UNIQUE, owner_user_id char(26) NOT NULL,
 status varchar(20) NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','completed')),
 requested_at timestamptz NOT NULL, completed_at timestamptz, retain_until timestamptz NOT NULL,
 last_error_code varchar(80), ledger_confirmed boolean NOT NULL DEFAULT false
);
