import { PaginationControls, type PaginationMeta } from './PaginationControls';

export function PaginatedLogTable({ logs, pagination, onPage }: { logs: Array<Record<string, unknown>>; pagination?: PaginationMeta; onPage: (page: number) => void }) {
  return (
    <div>
      <div className="responsive-table-wrap">
        <table className="compact-data-table">
          <thead><tr><th>ID</th><th>Type</th><th>Message / Action</th><th>Created</th></tr></thead>
          <tbody>{logs.map((log) => <tr key={String(log.id)}><td>#{String(log.id ?? '—')}</td><td>{String(log.category ?? log.action_type ?? log.action ?? log.type ?? 'log')}</td><td>{String(log.description ?? log.action ?? log.message ?? JSON.stringify(log).slice(0, 120))}</td><td>{String(log.created_at ?? '—')}</td></tr>)}</tbody>
        </table>
      </div>
      {logs.length === 0 && <p className="muted">No records on this page.</p>}
      <PaginationControls pagination={pagination} onPage={onPage} />
    </div>
  );
}
