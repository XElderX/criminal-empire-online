import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { buildOutcomeFromApiResponse, normalizeOutcome } from './outcomeAdapter';
import type { ActionOutcome, NotificationEntry, NotificationPriority, NotificationType } from './types';

interface NotificationContextValue {
  notifications: NotificationEntry[];
  unreadCount: number;
  drawerOpen: boolean;
  activeOutcome: ActionOutcome | null;
  notify: (notification: Omit<NotificationEntry, 'id' | 'createdAt' | 'read'>) => void;
  showOutcome: (outcome: ActionOutcome) => void;
  markRead: (id: string) => void;
  markAllRead: () => void;
  clearRead: () => void;
  setDrawerOpen: (open: boolean) => void;
  dismissOutcome: () => void;
}

const NotificationContext = createContext<NotificationContextValue | null>(null);

export function NotificationProvider({ children }: { children: ReactNode }) {
  const [notifications, setNotifications] = useState<NotificationEntry[]>([]);
  const [activeOutcome, setActiveOutcome] = useState<ActionOutcome | null>(null);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const previousFocus = useRef<HTMLElement | null>(null);

  const notify = useCallback((notification: Omit<NotificationEntry, 'id' | 'createdAt' | 'read'>) => {
    setNotifications((current) => [{
      ...notification,
      id: crypto.randomUUID(),
      createdAt: new Date().toISOString(),
      read: false,
    }, ...current].slice(0, 80));
  }, []);

  const showOutcome = useCallback((outcome: ActionOutcome) => {
    const normalized = normalizeOutcome(outcome);
    previousFocus.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    setActiveOutcome(normalized);
    notify({
      type: normalized.type,
      priority: normalized.priority,
      title: normalized.title,
      message: normalized.message,
      source: normalized.source,
      payload: normalized,
    });
  }, [notify]);

  const dismissOutcome = useCallback(() => {
    setActiveOutcome(null);
    window.setTimeout(() => previousFocus.current?.focus(), 0);
  }, []);

  const markRead = useCallback((id: string) => {
    setNotifications((current) => current.map((notification) => (
      notification.id === id ? { ...notification, read: true } : notification
    )));
  }, []);

  const markAllRead = useCallback(() => {
    setNotifications((current) => current.map((notification) => ({ ...notification, read: true })));
  }, []);

  const clearRead = useCallback(() => {
    setNotifications((current) => current.filter((notification) => !notification.read));
  }, []);

  useEffect(() => {
    function handleApiFeedback(event: Event): void {
      const detail = (event as CustomEvent).detail as unknown;
      const outcome = buildOutcomeFromApiResponse(detail);
      if (!outcome) return;

      if (outcome.priority === 'high' || outcome.priority === 'critical') {
        showOutcome(outcome);
      } else {
        const normalized = normalizeOutcome(outcome);
        notify({
          type: normalized.type,
          priority: normalized.priority,
          title: normalized.title,
          message: normalized.message,
          source: normalized.source,
          payload: normalized,
        });
      }
    }

    window.addEventListener('ceo:api-feedback', handleApiFeedback);
    return () => window.removeEventListener('ceo:api-feedback', handleApiFeedback);
  }, [notify, showOutcome]);

  const unreadCount = notifications.filter((notification) => !notification.read).length;
  const value = useMemo<NotificationContextValue>(() => ({
    notifications,
    unreadCount,
    drawerOpen,
    activeOutcome,
    notify,
    showOutcome,
    markRead,
    markAllRead,
    clearRead,
    setDrawerOpen,
    dismissOutcome,
  }), [notifications, unreadCount, drawerOpen, activeOutcome, notify, showOutcome, markRead, markAllRead, clearRead, dismissOutcome]);

  return (
    <NotificationContext.Provider value={value}>
      {children}
      <NotificationLiveRegion notifications={notifications} />
      <ToastStack notifications={notifications.filter((notification) => !notification.read).slice(0, 4)} onDismiss={markRead} />
      <NotificationBell />
      <NotificationDrawer />
      {activeOutcome && <OutcomeFocusOverlay outcome={activeOutcome} onDismiss={dismissOutcome} />}
    </NotificationContext.Provider>
  );
}

export function useNotifications(): NotificationContextValue {
  const context = useContext(NotificationContext);
  if (!context) {
    throw new Error('useNotifications must be used inside NotificationProvider.');
  }
  return context;
}

function NotificationLiveRegion({ notifications }: { notifications: NotificationEntry[] }) {
  const latest = notifications[0];
  return (
    <div className="sr-only" aria-live="polite" aria-atomic="true">
      {latest ? `${latest.title}. ${latest.message}` : ''}
    </div>
  );
}

function ToastStack({ notifications, onDismiss }: { notifications: NotificationEntry[]; onDismiss: (id: string) => void }) {
  if (notifications.length === 0) return null;

  return (
    <div className="toast-stack" aria-label="Recent notifications">
      {notifications.map((notification) => (
        <ToastNotification key={notification.id} notification={notification} onDismiss={() => onDismiss(notification.id)} />
      ))}
    </div>
  );
}

function ToastNotification({ notification, onDismiss }: { notification: NotificationEntry; onDismiss: () => void }) {
  return (
    <article className={`toast-notification toast-${notification.type} priority-${notification.priority}`}>
      <div>
        <p className="eyebrow">{notification.type.replace(/_/g, ' ')}</p>
        <strong>{notification.title}</strong>
        <p>{notification.message}</p>
      </div>
      <button className="icon-btn" type="button" aria-label={`Dismiss ${notification.title}`} onClick={onDismiss}>×</button>
    </article>
  );
}

function NotificationBell() {
  const { unreadCount, drawerOpen, setDrawerOpen } = useNotifications();
  return (
    <button
      className="notification-bell"
      type="button"
      aria-label={unreadCount > 0 ? `Open notifications, ${unreadCount} unread` : 'Open notifications'}
      aria-expanded={drawerOpen}
      onClick={() => setDrawerOpen(!drawerOpen)}
    >
      <span>!</span>
      {unreadCount > 0 && <strong>{unreadCount}</strong>}
    </button>
  );
}

function NotificationDrawer() {
  const { notifications, drawerOpen, setDrawerOpen, markRead, markAllRead, clearRead } = useNotifications();
  if (!drawerOpen) return null;

  return (
    <aside className="notification-drawer" aria-label="Notification center">
      <div className="drawer-heading">
        <div>
          <p className="eyebrow">Notification Center</p>
          <h2>Recent events</h2>
        </div>
        <button className="icon-btn" type="button" aria-label="Close notification center" onClick={() => setDrawerOpen(false)}>×</button>
      </div>
      <div className="drawer-actions">
        <button className="btn small" type="button" onClick={markAllRead}>Mark all read</button>
        <button className="btn small ghost" type="button" onClick={clearRead}>Clear read</button>
      </div>
      {notifications.length === 0 && (
        <div className="notification-empty">
          <strong>No notifications yet.</strong>
          <p>Important outcomes, heat warnings, purchases, travel discoveries, and loadout actions will appear here.</p>
        </div>
      )}
      <div className="notification-list">
        {notifications.map((notification) => (
          <button
            key={notification.id}
            className={`notification-row ${notification.read ? 'read' : 'unread'} priority-${notification.priority}`}
            type="button"
            onClick={() => markRead(notification.id)}
          >
            <span className={`outcome-dot outcome-${notification.type}`} />
            <span>
              <strong>{notification.title}</strong>
              <small>{notification.message}</small>
              <em>{new Date(notification.createdAt).toLocaleString()}</em>
            </span>
          </button>
        ))}
      </div>
    </aside>
  );
}

function OutcomeFocusOverlay({ outcome, onDismiss }: { outcome: ActionOutcome; onDismiss: () => void }) {
  const normalizedOutcome = normalizeOutcome(outcome);
  const badges = normalizedOutcome.badges || [];
  const sections = normalizedOutcome.sections || [];
  const nextActions = normalizedOutcome.nextActions || [];
  const dialogRef = useRef<HTMLDivElement | null>(null);
  const dismissible = normalizedOutcome.dismissible !== false && normalizedOutcome.priority !== 'critical';

  useEffect(() => {
    dialogRef.current?.focus();
    function handleKeyDown(event: KeyboardEvent): void {
      if (event.key === 'Escape' && dismissible) onDismiss();
    }
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [dismissible, onDismiss]);

  return (
    <div
      className={`outcome-backdrop priority-${normalizedOutcome.priority}`}
      onMouseDown={(event) => {
        if (dismissible && event.target === event.currentTarget) onDismiss();
      }}
    >
      <section
        className={`outcome-focus-panel outcome-${normalizedOutcome.type}`}
        role="dialog"
        aria-modal="true"
        aria-labelledby="outcome-focus-title"
        tabIndex={-1}
        ref={dialogRef}
      >
        <div className="outcome-panel-header">
          <div>
            <p className="eyebrow">{normalizedOutcome.source || 'Action report'}</p>
            <h2 id="outcome-focus-title">{normalizedOutcome.title}</h2>
            <p>{normalizedOutcome.message}</p>
          </div>
          <OutcomeBadge type={normalizedOutcome.type} priority={normalizedOutcome.priority} />
        </div>

        {badges.length > 0 && (
          <div className="outcome-badge-row">
            {badges.map((badge) => <span className={`outcome-chip ${badge.kind || 'info'}`} key={`${badge.label}-${badge.value}`}>{badge.label}{badge.value ? ` ${badge.value}` : ''}</span>)}
          </div>
        )}

        <div className="outcome-section-grid">
          {sections.map((section) => (
            <article className="outcome-section" key={section.title}>
              <h3>{section.title}</h3>
              <ul>
                {section.lines.map((line) => <li key={line}>{line}</li>)}
              </ul>
            </article>
          ))}
        </div>

        {nextActions.length > 0 && (
          <div className="next-action-grid">
            {nextActions.map((action) => (
              <div className="next-action-card" key={action.label}>
                <strong>{action.label}</strong>
                {action.description && <p>{action.description}</p>}
              </div>
            ))}
          </div>
        )}

        <div className="outcome-footer">
          <button className="btn primary" type="button" onClick={onDismiss}>{normalizedOutcome.priority === 'critical' ? 'Continue' : 'Close report'}</button>
          {dismissible && <small>Press Escape or click outside to dismiss.</small>}
        </div>
      </section>
    </div>
  );
}

function OutcomeBadge({ type, priority }: { type: NotificationType; priority: NotificationPriority }) {
  return <span className={`outcome-badge outcome-${type} priority-${priority}`}>{priority} · {type.replace(/_/g, ' ')}</span>;
}
