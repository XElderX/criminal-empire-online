export interface PaginationMeta { page: number; per_page: number; total: number; total_pages: number; has_next: boolean; has_previous: boolean }
export function PaginationControls({ pagination, onPage }: { pagination?: PaginationMeta; onPage: (page: number) => void }) {
  if (!pagination) return null;
  return (
    <div className="pagination-controls">
      <button className="btn" disabled={!pagination.has_previous} onClick={() => onPage(pagination.page - 1)}>Previous</button>
      <span>Page {pagination.page} / {pagination.total_pages} · {pagination.total} records</span>
      <button className="btn" disabled={!pagination.has_next} onClick={() => onPage(pagination.page + 1)}>Next</button>
    </div>
  );
}
