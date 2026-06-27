interface Pagination {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
  has_next: boolean;
  has_previous: boolean;
}

interface PaginatedLogTableProps {
  logs: Array<Record<string, unknown>>;
  pagination?: Pagination;
  onPage?: (page: number) => void;
}

const PREFERRED_COLUMNS = ['id', 'created_at', 'type', 'action', 'category', 'message', 'description', 'amount', 'heat_delta', 'user_id'];

export function PaginatedLogTable({ logs, pagination, onPage }: PaginatedLogTableProps) {
  const columns = visibleColumns(logs);

  return (
    <div className="paginated-log-shell">
      {logs.length === 0 ? (
        <div className="empty-state compact-empty-state">
          <strong>No records found.</strong>
          <p>Try another log type or page.</p>
        </div>
      ) : (
        <div className="table-scroll">
          <table className="data-table compact-log-table">
            <thead>
              <tr>
                {columns.map((column) => <th key={column}>{label(column)}</th>)}
              </tr>
            </thead>
            <tbody>
              {logs.map((row, index) => (
                <tr key={String(row.id ?? `${index}-${row.created_at ?? ''}`)}>
                  {columns.map((column) => <td key={column}>{formatCell(row[column])}</td>)}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {pagination && (
        <div className="pagination-controls">
          <button
            type="button"
            className="btn small"
            disabled={!pagination.has_previous}
            onClick={() => onPage?.(Math.max(1, pagination.page - 1))}
          >
            Previous
          </button>
          <span>Page {pagination.page} / {pagination.total_pages || 1} · {pagination.total} records</span>
          <button
            type="button"
            className="btn small"
            disabled={!pagination.has_next}
            onClick={() => onPage?.(pagination.page + 1)}
          >
            Next
          </button>
        </div>
      )}
    </div>
  );
}

function visibleColumns(logs: Array<Record<string, unknown>>): string[] {
  const keys = new Set<string>();
  logs.forEach((row) => Object.keys(row).forEach((key) => keys.add(key)));
  const preferred = PREFERRED_COLUMNS.filter((key) => keys.has(key));
  const extra = Array.from(keys).filter((key) => !preferred.includes(key)).slice(0, 4);
  return [...preferred, ...extra].slice(0, 8);
}

function label(value: string): string {
  return value.replace(/_/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase());
}

function formatCell(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—';
  if (typeof value === 'object') return JSON.stringify(value);
  return String(value);
}
