interface NoticeProps {
  message: string;
  kind?: 'info' | 'success' | 'error';
}

export function Notice({ message, kind = 'info' }: NoticeProps) {
  if (!message) {
    return null;
  }

  return <div className={`notice notice-${kind}`}>{message}</div>;
}
