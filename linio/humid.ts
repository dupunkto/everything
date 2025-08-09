export function generateHumID(): string {
  const LENGTH = 5;
  const BASE = 36;

  const buffer = new Uint8Array(4);
  crypto.getRandomValues(buffer);

  let n = 0;
  for (let i = 0; i < buffer.length; i++)
    n = (n << 8) | buffer[i];

  return n.toString(BASE).toUpperCase().padStart(LENGTH, '0').slice(-LENGTH);
}