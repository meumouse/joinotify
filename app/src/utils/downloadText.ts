/**
 * downloadText.ts
 *
 * Saves text (a CSV export, for instance) to the viewer's disk.
 *
 * @since 2.5.0
 */

/**
 * Downloads text as a file.
 *
 * Same care as downloadJson: the anchor has to be in the DOM for Firefox, and
 * the object URL has to outlive the click.
 *
 * @since 2.5.0
 * @param {string} content File content.
 * @param {string} filename Suggested file name.
 * @param {string} [type] MIME type.
 */
export function downloadText(content: string, filename: string, type = 'text/csv;charset=utf-8'): void {
  const blob = new Blob([content], { type });
  const url = window.URL.createObjectURL(blob);
  const anchor = document.createElement('a');

  anchor.href = url;
  anchor.download = filename || 'joinotify-export.txt';
  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);
  window.setTimeout(() => window.URL.revokeObjectURL(url), 0);
}
