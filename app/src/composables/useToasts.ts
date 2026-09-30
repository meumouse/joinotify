import { onBeforeUnmount, ref } from 'vue';
import { __, textDomain } from '../utils/i18n';

export type ToastTone = 'success' | 'warning' | 'error' | 'info';

export interface ToastItem {
  id: string;
  title: string;
  message: string;
  tone: ToastTone;
  closing: boolean;
}

/**
 * Normalizes the tone names used across the admin screens ("danger" is an
 * alias of "error").
 *
 * @since 2.5.0
 * @param {string} tone Requested tone.
 * @returns {ToastTone} A tone ToastStack knows.
 */
function normalizeTone(tone: string): ToastTone {
  if (tone === 'danger') {
    return 'error';
  }

  if (tone === 'success' || tone === 'warning' || tone === 'error' || tone === 'info') {
    return tone;
  }

  return 'info';
}

/**
 * Toast queue for the ToastStack component: each toast fades out after three
 * seconds, or when dismissed.
 *
 * @since 2.5.0
 * @returns {Object} `toasts`, `toast(message, tone, title)` and `dismissToast(id)`.
 */
export function useToasts() {
  const toasts = ref<ToastItem[]>([]);
  const timers = new Map<string, number[]>();

  function clearTimers(id: string) {
    (timers.get(id) || []).forEach((timer) => window.clearTimeout(timer));
    timers.delete(id);
  }

  function remove(id: string) {
    clearTimers(id);
    toasts.value = toasts.value.filter((item) => item.id !== id);
  }

  function setClosing(id: string) {
    toasts.value = toasts.value.map((item) => (item.id === id ? { ...item, closing: true } : item));
  }

  function toast(message: string, tone = 'info', title = __('Joinotify', textDomain)) {
    const id = `${Date.now()}-${Math.random().toString(16).slice(2)}`;

    toasts.value.push({ id, title, message, tone: normalizeTone(tone), closing: false });
    timers.set(id, [
      window.setTimeout(() => setClosing(id), 3000),
      window.setTimeout(() => remove(id), 3500),
    ]);
  }

  function dismissToast(id: string) {
    clearTimers(id);
    setClosing(id);
    timers.set(id, [window.setTimeout(() => remove(id), 180)]);
  }

  onBeforeUnmount(() => {
    timers.forEach((list) => list.forEach((timer) => window.clearTimeout(timer)));
    timers.clear();
  });

  return { toasts, toast, dismissToast };
}
