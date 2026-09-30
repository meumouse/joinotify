/**
 * useReviewPrompt.ts
 *
 * Shared state for the WordPress.org review request shown by the settings
 * screen and the workflow builder. The server decides whether the prompt is
 * due (`review_prompt.show` in the page bootstrap); this composable only waits
 * a moment after the page mounts, keeps the dialog closed while the page
 * reports it is busy, and stores the user's answer.
 *
 * @since 2.5.0
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

export type ReviewPromptStatus = 'rated' | 'later' | 'dismissed';

export interface ReviewPromptPayload {
  show?: boolean;
  review_url?: string;
}

interface ReviewPromptOptions {
  /** Reads the `review_prompt` entry of the page bootstrap. */
  payload: () => ReviewPromptPayload | null | undefined;
  /** REST client of the page. */
  api: { post: (path: string, body?: unknown) => Promise<unknown> };
  /** True while another dialog or panel owns the user's attention. */
  blocked?: () => boolean;
  /** Called after the user chose to leave a review. */
  onRated?: () => void;
  /** Optional debug logger of the page. */
  log?: (event: string, context?: Record<string, unknown>) => void;
  /** Wait after mount before the prompt may open, in milliseconds. */
  delay?: number;
}

/**
 * Wires the review prompt into a page.
 *
 * @since 2.5.0
 * @param options Page-specific hooks.
 * @returns The review URL, whether the dialog is open and the answer handler.
 */
export function useReviewPrompt(options: ReviewPromptOptions) {
  const ready = ref(false);
  let timer: number | null = null;

  const reviewUrl = computed(() => String(options.payload()?.review_url || ''));
  const open = computed(() => ready.value && !(options.blocked?.() ?? false));

  onMounted(() => {
    const payload = options.payload();

    if (!payload?.show || !reviewUrl.value) {
      return;
    }

    timer = window.setTimeout(() => {
      ready.value = true;
      options.log?.('review-prompt:shown');
    }, options.delay ?? 2000);
  });

  onBeforeUnmount(() => {
    if (timer) {
      window.clearTimeout(timer);
    }
  });

  async function answer(status: ReviewPromptStatus) {
    ready.value = false;
    options.log?.('review-prompt:answered', { status });

    if (status === 'rated') {
      options.onRated?.();
    }

    try {
      await options.api.post('/admin/user/review-prompt', { status });
    } catch (error) {
      options.log?.('review-prompt:save-failed', {
        status,
        error: error instanceof Error ? error.message : String(error),
      });
    }
  }

  return { reviewUrl, open, answer };
}
