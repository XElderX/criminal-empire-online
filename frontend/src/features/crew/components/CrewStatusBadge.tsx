import { statusLabel } from '../utils/crewPresentation';

export function CrewStatusBadge({ status }: { status: string }) {
  return (
    <span className={`crew-status crew-status-${status}`}>
      <span aria-hidden="true" className="crew-status-dot" />
      {statusLabel(status)}
    </span>
  );
}
