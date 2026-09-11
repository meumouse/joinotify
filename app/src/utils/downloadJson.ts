/**
 * downloadJson.ts
 *
 * Saves a JSON payload to the viewer's disk, used by the table exports of the
 * workflows, message history and processing queue screens.
 *
 * @since 2.4.2
 */

/**
 * Downloads a payload as a pretty-printed JSON file.
 *
 * The anchor must be in the DOM for the click to start a download in Firefox,
 * and the object URL must outlive the click: revoking it synchronously can
 * abort the download before the browser has read the blob.
 *
 * @since 2.4.2
 * @param {unknown} payload The data to write.
 * @param {string} filename Suggested file name.
 */
export function downloadJson(payload: unknown, filename: string): void {
  const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json;charset=utf-8' });
  const url = window.URL.createObjectURL(blob);
  const anchor = document.createElement('a');

  anchor.href = url;
  anchor.download = filename || 'joinotify-export.json';
  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);
  window.setTimeout(() => window.URL.revokeObjectURL(url), 0);
}
