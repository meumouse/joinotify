/**
 * workflow.ts
 *
 * Shared TypeScript types for the workflows listing screen, including workflow
 * items, status counts, pagination, and bulk-action option shapes.
 *
 * @since 2.0.0
 */

/** Publication status of a workflow. */
export type WorkflowStatus = 'publish' | 'draft' | 'trash';

/** A single workflow row as shown in the listing. */
export interface WorkflowItem {
  id: number | string;
  name: string;
  created_at: string;
  status: WorkflowStatus;
  edit_url: string;
  delete_url: string;
  restore_url?: string;
  delete_permanently_url?: string;
  previous_status?: WorkflowStatus | null;
}

/** Counts of workflows per status. */
export interface WorkflowCounts {
  publish: number;
  draft: number;
  trash: number;
}

/** Pagination state for the workflows listing. */
export interface WorkflowPagination {
  current_page: number;
  per_page: number;
  total_items: number;
  total_pages: number;
}

/** An option in the bulk-action dropdown. */
export interface WorkflowBulkActionOption {
  label: string;
  value: string;
  destructive?: boolean;
}
