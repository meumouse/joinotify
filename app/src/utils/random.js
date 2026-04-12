/**
 * Generate a hexadecimal token with the requested length.
 *
 * @since 1.4.7
 * @version 1.4.7
 * @param {number} [length=32] - Desired token length in characters.
 * @return {string} Random hexadecimal token.
 */
export function generateHexToken(length = 32) {
  const bytes = Math.ceil(length / 2);
  const buffer = new Uint8Array(bytes);

  if (globalThis.crypto?.getRandomValues) {
    globalThis.crypto.getRandomValues(buffer);
  } else {
    for (let index = 0; index < bytes; index += 1) {
      buffer[index] = Math.floor(Math.random() * 256);
    }
  }

  return Array.from(buffer, (value) => value.toString(16).padStart(2, '0')).join('').slice(0, length);
}
