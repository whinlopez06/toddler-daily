function partsAt(date: Date, timezone: string): string {
  const p = new Intl.DateTimeFormat('en-CA', { timeZone: timezone, year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit',hourCycle:'h23' }).formatToParts(date);
  const get = (key: string) => p.find(part => part.type === key)?.value ?? '00';
  return get('year')+'-'+get('month')+'-'+get('day')+'T'+get('hour')+':'+get('minute')+':'+get('second');
}
export function instantToLocal(iso: string, timezone: string): string { return partsAt(new Date(iso), timezone).slice(0,16); }
export function localToInstant(local: string, timezone: string, occurrence = 'reject'): string {
  if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(local)) throw new Error('Choose a valid date and time.');
  const target = Date.parse(local+':00Z');
  if (!Number.isFinite(target)) throw new Error('Choose a valid date and time.');
  const offsets = new Set<number>();
  for (const hours of [-36,-24,-12,0,12,24,36]) {
    const probe = new Date(target+hours*3600000);
    offsets.add(Date.parse(partsAt(probe,timezone)+'Z')-probe.getTime());
  }
  const candidates = [...offsets].map(offset => new Date(target-offset)).filter(date => partsAt(date,timezone).slice(0,16) === local).sort((a,b) => a.getTime()-b.getTime());
  if (!candidates.length) throw new Error('This local time does not exist in the selected timezone. Choose another time.');
  if (candidates.length > 1 && occurrence === 'reject') throw new Error('This local time occurs twice. Choose the earlier or later occurrence below.');
  return (occurrence === 'later' ? candidates[candidates.length-1] : candidates[0]).toISOString();
}
