import type { UpdateNotice } from '../types';

interface UpdateNoticeModalProps {
  notice: UpdateNotice;
  busy: boolean;
  onConfirm: () => void;
}

export function UpdateNoticeModal({ notice, busy, onConfirm }: UpdateNoticeModalProps) {
  return (
    <div className="modal-backdrop update-notice-backdrop" role="dialog" aria-modal="true" aria-labelledby="update-notice-title">
      <section className="card update-notice-modal">
        <p className="eyebrow">Game update {notice.version}</p>
        <h2 id="update-notice-title">{notice.title}</h2>
        <p>{notice.body}</p>
        <div className="update-notice-points">
          <span>Personal, crew, gang, district, and NPC heat</span>
          <span>Police investigations and spillover</span>
          <span>New heat reduction actions</span>
          <span>Dismissed high-heat crew revenge risk</span>
        </div>
        <button className="btn primary full-width" disabled={busy} onClick={onConfirm}>
          {busy ? 'Confirming…' : 'I understand, continue'}
        </button>
      </section>
    </div>
  );
}
