export function formatDate(d: Date): string {
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
}

export function normalizeDate(str: string | undefined): string | undefined {
  if(str) return formatDate(new Date(str));
}

export function daysSince(d: Date | string) {
  return Math.floor((Date.now() - new Date(d).getTime()) / (24 * 3600 * 1000));
}
