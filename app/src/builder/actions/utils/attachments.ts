/**
 * attachments.ts
 *
 * Normalization for the attachment list shared by the actions that can deliver
 * files. Mirrors Registry::sanitize_attachments() on the PHP side, so what the
 * builder stores is already the shape the runtime resolver expects.
 *
 * @since 2.1.0
 */

export type AttachmentSource = 'media' | 'url' | 'order_downloads' | 'loop_item';

export interface AttachmentItem {
  source: AttachmentSource;
  name: string;
  url?: string;
  attachment_id?: number;
}

const SOURCES: AttachmentSource[] = ['media', 'url', 'order_downloads', 'loop_item'];

/**
 * Normalize the stored attachment list, dropping entries that point at nothing.
 *
 * An item is kept only when its source can actually yield a file: a media item
 * needs an id or a URL, a URL item needs a URL, and an order item needs neither
 * because it is resolved from the order at runtime.
 *
 * @since 2.1.0
 * @param {unknown} value Raw attachment list.
 * @returns {AttachmentItem[]} Normalized attachment list.
 */
export function normalizeAttachments(value: unknown): AttachmentItem[] {
  if (!Array.isArray(value)) {
    return [];
  }

  return value.reduce<AttachmentItem[]>((items, entry) => {
    if (!entry || typeof entry !== 'object') {
      return items;
    }

    const raw = entry as Record<string, unknown>;
    const source = String(raw.source || 'url') as AttachmentSource;

    if (!SOURCES.includes(source)) {
      return items;
    }

    const item: AttachmentItem = {
      source,
      name: String(raw.name || ''),
    };

    // order downloads and the current loop item are resolved from the payload at
    // runtime, so they carry no id or URL to validate here
    if (source === 'order_downloads' || source === 'loop_item') {
      items.push(item);

      return items;
    }

    item.url = String(raw.url || '').trim();

    if (source === 'media') {
      item.attachment_id = Number(raw.attachment_id) || 0;

      if (!item.attachment_id && !item.url) {
        return items;
      }
    }

    if (source === 'url' && !item.url) {
      return items;
    }

    items.push(item);

    return items;
  }, []);
}
