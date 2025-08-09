export function formatDate(d: Date): string {
  return d.toISOString().slice(0, 10).replace(/-(0)/g, '-');
}
