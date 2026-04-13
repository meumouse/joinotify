export type WorkflowStatus = 'publish' | 'draft' | 'trash';

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

export interface WorkflowCounts {
  publish: number;
  draft: number;
  trash: number;
}

export interface WorkflowPagination {
  current_page: number;
  per_page: number;
  total_items: number;
  total_pages: number;
}

export interface WorkflowBulkActionOption {
  label: string;
  value: string;
  destructive?: boolean;
}
