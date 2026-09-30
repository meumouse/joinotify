/**
 * flowNodeIcons.ts
 *
 * Resolves the Boxicons names used by flow nodes (e.g. "bx-time", "bxl-whatsapp",
 * "sparkles") to @boxicons/vue components. The app never loads the Boxicons
 * webfont, so a name without an entry here has no glyph and the node falls back
 * to its initial.
 *
 * @since 2.4.3
 */
import type { Component } from 'vue';
import {
  Bolt,
  CalendarEvent,
  Clock,
  GitBranch,
  Sparkles,
  StopCircle,
  Whatsapp,
} from '@boxicons/vue';

const FLOW_NODE_ICONS: Record<string, Component> = {
  zap: Bolt,
  bolt: Bolt,
  time: Clock,
  clock: Clock,
  'calendar-event': CalendarEvent,
  'git-branch': GitBranch,
  'stop-circle': StopCircle,
  whatsapp: Whatsapp,
  sparkles: Sparkles,
};

/**
 * Finds the component for a Boxicons name, accepting a bare name ("time"), a
 * prefixed class ("bx-time", "bxl-whatsapp") or a class list ("bx bx-time").
 *
 * @since 2.4.3
 * @param {string} value The icon name or class list.
 * @returns {Component|null} The matching component, or null when none is mapped.
 */
export function resolveFlowNodeIcon(value: string): Component | null {
  const tokens = String(value || '').trim().toLowerCase().split(/\s+/).filter(Boolean);

  for (const token of tokens) {
    const icon = FLOW_NODE_ICONS[token.replace(/^bx[lrs]?-/, '')];

    if (icon) {
      return icon;
    }
  }

  return null;
}
