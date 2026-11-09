export interface Envelope<T> { data: T; }
export interface Page<T> { data: T[]; meta?: { current_page: number; last_page: number; total: number }; current_page?: number; last_page?: number; total?: number; }
export interface Family { id: string; name: string; timezone: string; version: number; deletion_pending: boolean; }
export interface User { id: string; name: string; email: string; email_verified: boolean; email_reminders_enabled: boolean; is_demo?: boolean; demo_expires_at?: string | null; family: Family | null; }
export interface Child { first_name?: string | null; middle_name?: string | null; last_name?: string | null; suffix?: string | null; gender?: string | null; id: string; display_name: string; birth_date: string; archived_at: string | null; version: number; }
export interface FamilyRecord {
  id: string; child_id: string; child_name?: string; version: number; created_at: string;
  kind?: string; category?: string; title?: string; description?: string; entry_date?: string;
  measured_on?: string; weight_value?: string; weight_unit?: string; height_value?: string; height_unit?: string; notes?: string;
  name?: string; instructed_amount?: string; dose_unit?: string; instructions?: string; start_date?: string; end_date?: string; archived_at?: string;
  vaccine_name?: string; administered_on?: string; dose_label?: string; provider?: string;
  starts_at?: string; timezone?: string; clinic?: string; reason?: string; visit_summary?: string; status?: string; reminders_enabled?: boolean;
  administered_at?: string; actual_amount?: string; state?: string; correction_of_id?: string; correction_reason?: string; replacement_id?: string | null;
  snapshot?: { name: string; instructions?: string };
}
export interface Dashboard { children: Child[]; appointments: FamilyRecord[]; medications?: FamilyRecord[]; recent_entries: FamilyRecord[]; timezone: string; reminder_notice: string; }
export interface Notification { id: string; read_at: string | null; created_at: string; data: { message: string; child_id: string; appointment_id: string }; }
