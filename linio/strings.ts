export function truncate(str: string, length: number): string {
  if(str.length <= length) return str;
  else return str.substring(0, length - 3) + "...";
}