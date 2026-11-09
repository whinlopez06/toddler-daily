export function ageLabel(birthDate: string, today = new Date(), timezone = 'Asia/Manila'): string {
  const parts = new Intl.DateTimeFormat('en-CA', { timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(today);
  const year = Number(parts.find(p => p.type === 'year')?.value);
  const month = Number(parts.find(p => p.type === 'month')?.value);
  const day = Number(parts.find(p => p.type === 'day')?.value);
  const [by, bm, bd] = birthDate.split('-').map(Number);
  const months = (year - by) * 12 + month - bm - (day < bd ? 1 : 0);
  if (months < 1) return 'Under 1 month';
  if (months < 24) return months + (months === 1 ? ' month' : ' months');
  const years = Math.floor(months / 12); const remainder = months % 12;
  return years + (years === 1 ? ' year' : ' years') + (remainder ? ', ' + remainder + (remainder === 1 ? ' month' : ' months') : '');
}
export function dateLabel(value: string | undefined, timezone = 'Asia/Manila'): string {
  if (!value) return 'Not recorded';
  const date = /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(value + 'T12:00:00Z') : new Date(value);
  return new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeZone: /^\d{4}-\d{2}-\d{2}$/.test(value) ? 'UTC' : timezone }).format(date);
}
export function timeLabel(value: string | undefined, timezone: string): string {
  return value ? new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'short', timeZone: timezone }).format(new Date(value)) : 'Not recorded';
}
