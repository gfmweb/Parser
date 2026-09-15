export interface User {
  id: number;
  name: string;
  email: string;
}

export type ParseStatus = 'pending' | 'parsing' | 'done' | 'failed';

export interface ParseJob {
  status: string;
  total_reviews: number;
  parsed_reviews: number;
  error_message: string | null;
  started_at: string | null;
  finished_at: string | null;
}

export interface Organization {
  id: number;
  yandex_url: string;
  yandex_id: string | null;
  name: string | null;
  address: string | null;
  rating: number | null;
  rating_count: number;
  review_count: number;
  parse_status: ParseStatus;
  parse_error: string | null;
  last_parsed_at: string | null;
  latest_parse_job?: ParseJob | null;
}

export interface Review {
  id: number;
  author_name: string | null;
  rating: number | null;
  text: string | null;
  reviewed_at: string | null;
}

export interface ParseProgressPayload {
  organizationId: number;
  parseJobId: number;
  total: number;
  parsed: number;
  status: 'parsing' | 'done' | 'failed';
  error: string | null;
  name?: string | null;
  rating?: number | null;
  address?: string | null;
}

export interface RatingCounts {
  1: number;
  2: number;
  3: number;
  4: number;
  5: number;
}

export interface PaginatedMeta {
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
  rating_counts?: RatingCounts;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: PaginatedMeta;
}

export interface LoginResponse {
  user: User;
  token: string;
}

export interface NormalizedApiError {
  message: string;
  errors: Record<string, string[]>;
  status: number | null;
}
