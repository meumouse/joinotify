/**
 * csv.ts
 *
 * A small CSV reader for the contact import: quoted cells, doubled quotes,
 * line breaks inside quotes, CRLF, a UTF-8 BOM, and the delimiter spreadsheets
 * pick by locale (comma, semicolon or tab), guessed from the header line.
 *
 * @since 2.5.0
 */

/**
 * Guesses the delimiter from the first line: the candidate that appears most
 * outside quotes.
 *
 * @since 2.5.0
 * @param {string} text File content.
 * @returns {string} The delimiter.
 */
export function detectDelimiter(text: string): string {
  const firstLine = text.split(/\r?\n/, 1)[0] || '';
  let best = ',';
  let bestCount = 0;

  for (const candidate of [',', ';', '\t']) {
    let count = 0;
    let quoted = false;

    for (const char of firstLine) {
      if (char === '"') {
        quoted = !quoted;
      } else if (char === candidate && !quoted) {
        count++;
      }
    }

    if (count > bestCount) {
      best = candidate;
      bestCount = count;
    }
  }

  return best;
}

/**
 * Parses CSV text into rows of cells. Empty trailing lines are dropped.
 *
 * @since 2.5.0
 * @param {string} input File content.
 * @param {string} [delimiter] Delimiter; guessed when omitted.
 * @returns {string[][]} The rows.
 */
export function parseCsv(input: string, delimiter = ''): string[][] {
  const text = input.replace(/^﻿/, '');
  const separator = delimiter || detectDelimiter(text);
  const rows: string[][] = [];
  let row: string[] = [];
  let cell = '';
  let quoted = false;

  for (let index = 0; index < text.length; index++) {
    const char = text[index];

    if (quoted) {
      if (char === '"' && text[index + 1] === '"') {
        cell += '"';
        index++;
      } else if (char === '"') {
        quoted = false;
      } else {
        cell += char;
      }

      continue;
    }

    if (char === '"' && cell === '') {
      quoted = true;
    } else if (char === separator) {
      row.push(cell);
      cell = '';
    } else if (char === '\n' || char === '\r') {
      if (char === '\r' && text[index + 1] === '\n') {
        index++;
      }

      row.push(cell);
      rows.push(row);
      row = [];
      cell = '';
    } else {
      cell += char;
    }
  }

  if (cell !== '' || row.length) {
    row.push(cell);
    rows.push(row);
  }

  return rows.filter((cells) => cells.some((value) => value.trim() !== ''));
}
