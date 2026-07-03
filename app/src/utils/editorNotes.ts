/**
 * editorNotes.ts
 *
 * Factory and normalization helpers for canvas sticky notes (visual-only
 * documentation labels). Notes are stored in the workflow file's top-level
 * `editor_notes` array and never take part in execution.
 *
 * @since 2.0.0
 */
import { normalizeHex } from './color';
import { createWorkflowId } from './workflowIds';
import type { WorkflowEditorNote } from '../types/workflowBuilder';

/** Preset palette offered in the color popover (N8N-like soft tones). */
export const NOTE_COLORS: string[] = [
  '#fde68a', // amber
  '#bbf7d0', // green
  '#bfdbfe', // blue
  '#fbcfe8', // pink
  '#ddd6fe', // purple
  '#fed7aa', // orange
  '#e2e8f0', // slate
];

/** Default note color when none is provided. */
export const DEFAULT_NOTE_COLOR = NOTE_COLORS[0];

/** Default and minimum note dimensions, in canvas pixels. */
export const DEFAULT_NOTE_WIDTH = 260;
export const DEFAULT_NOTE_HEIGHT = 160;
export const MIN_NOTE_WIDTH = 160;
export const MIN_NOTE_HEIGHT = 80;

/**
 * Coerces a value to a finite number, falling back to a default.
 *
 * @since 2.0.0
 * @param {unknown} value The candidate value.
 * @param {number} fallback The fallback used when not finite.
 * @returns {number} The finite number.
 */
function toNumber(value: unknown, fallback: number): number {
  const parsed = typeof value === 'number' ? value : Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
}

/**
 * Creates a new sticky note with defaults, merging any provided partial.
 *
 * @since 2.0.0
 * @param {Partial<WorkflowEditorNote>} [partial] Initial values.
 * @returns {WorkflowEditorNote} The created note.
 */
export function createEditorNote(partial: Partial<WorkflowEditorNote> = {}): WorkflowEditorNote {
  return normalizeEditorNote({
    id: partial.id || createWorkflowId('note'),
    content: partial.content ?? '',
    color: partial.color ?? DEFAULT_NOTE_COLOR,
    position: partial.position ?? { x: 0, y: 0 },
    width: partial.width ?? DEFAULT_NOTE_WIDTH,
    height: partial.height ?? DEFAULT_NOTE_HEIGHT,
  });
}

/**
 * Normalizes a raw note object into a well-formed WorkflowEditorNote.
 *
 * @since 2.0.0
 * @param {unknown} raw The raw note value.
 * @returns {WorkflowEditorNote|null} The normalized note, or null when invalid.
 */
export function normalizeEditorNote(raw: unknown): WorkflowEditorNote | null {
  if (!raw || typeof raw !== 'object') {
    return null;
  }

  const source = raw as Record<string, unknown>;
  const position = (source.position && typeof source.position === 'object')
    ? source.position as Record<string, unknown>
    : {};

  return {
    id: typeof source.id === 'string' && source.id.trim() ? source.id : createWorkflowId('note'),
    content: typeof source.content === 'string' ? source.content : '',
    color: normalizeHex(source.color) || DEFAULT_NOTE_COLOR,
    position: {
      x: toNumber(position.x, 0),
      y: toNumber(position.y, 0),
    },
    width: Math.max(MIN_NOTE_WIDTH, toNumber(source.width, DEFAULT_NOTE_WIDTH)),
    height: Math.max(MIN_NOTE_HEIGHT, toNumber(source.height, DEFAULT_NOTE_HEIGHT)),
  };
}

/**
 * Normalizes an arbitrary value into an array of valid notes.
 *
 * @since 2.0.0
 * @param {unknown} value The raw list.
 * @returns {WorkflowEditorNote[]} The normalized notes.
 */
export function normalizeEditorNotes(value: unknown): WorkflowEditorNote[] {
  if (!Array.isArray(value)) {
    return [];
  }

  return value
    .map((item) => normalizeEditorNote(item))
    .filter(Boolean) as WorkflowEditorNote[];
}
