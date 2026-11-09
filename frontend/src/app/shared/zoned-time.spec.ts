import { describe, expect, it } from 'vitest';
import { instantToLocal, localToInstant } from './zoned-time';
import { ageLabel, dateLabel } from './date-format';

describe('calendar dates and timezone boundaries', () => {
  it('keeps birth dates as dates across timezones', () => {
    expect(dateLabel('2024-03-15', 'America/Los_Angeles')).toContain('15');
    expect(ageLabel('2024-03-15',new Date('2026-03-14T15:00:00Z'),'Asia/Manila')).toBe('23 months');
  });
  it('converts a Manila appointment without using the device timezone', () => {
    expect(localToInstant('2026-11-01T09:00','Asia/Manila')).toBe('2026-11-01T01:00:00.000Z');
    expect(instantToLocal('2026-11-01T01:00:00Z','Asia/Manila')).toBe('2026-11-01T09:00');
  });
  it('rejects nonexistent daylight-saving times', () => {
    expect(() => localToInstant('2026-03-08T02:30','America/New_York')).toThrow('does not exist');
  });
  it('requires a choice for repeated daylight-saving times', () => {
    expect(() => localToInstant('2026-11-01T01:30','America/New_York')).toThrow('occurs twice');
    expect(localToInstant('2026-11-01T01:30','America/New_York','earlier')).toBe('2026-11-01T05:30:00.000Z');
    expect(localToInstant('2026-11-01T01:30','America/New_York','later')).toBe('2026-11-01T06:30:00.000Z');
  });
});
