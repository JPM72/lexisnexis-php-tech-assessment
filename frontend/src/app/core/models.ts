export interface Document {
  id: number;
  title: string;
  filename: string;
  file_size: number;
  mime_type: string;
  created_at: string;
  updated_at?: string;
}

export interface SearchResult extends Document {
  relevance_score: number;
  snippet?: string;
  title_highlighted?: string;
  file_size_formatted?: string;
  created_at_formatted?: string;
}

export interface PaginationInfo {
  current_page: number;
  per_page: number;
  total: number;
  total_pages: number;
  has_next: boolean;
  has_prev: boolean;
  next_page: number | null;
  prev_page: number | null;
}

export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
  timestamp: string;
}

export interface DocumentListResponse {
  data: Document[];
  pagination: PaginationInfo;
}

export interface SearchResponse {
  data: SearchResult[];
  pagination: PaginationInfo;
  metadata: {
    query: string;
    execution_time_ms: number;
    page: number;
    limit: number;
    sort_by: string;
    sort_order: string;
    search_mode: string;
  };
}

export interface UploadResponse {
  id: number;
  title: string;
  filename: string;
  file_size: number;
  mime_type: string;
}