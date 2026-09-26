/**
 * Shimmer placeholder blocks — the polished alternative to spinners.
 * Render one of the presets while data loads so the page layout
 * appears instantly and content "fills in" when ready.
 */
export default function Skeleton({ variant = 'table', rows = 5, cards = 6 }) {
  if (variant === 'cards') {
    return (
      <div className="skeleton-cards" aria-hidden="true">
        {Array.from({ length: cards }, (_, i) => (
          <div className="skeleton-card" key={i}>
            <div className="skeleton sk-media" />
            <div className="sk-body">
              <div className="skeleton w60" />
              <div className="skeleton" />
              <div className="skeleton w40" />
            </div>
          </div>
        ))}
      </div>
    );
  }

  if (variant === 'stats') {
    return (
      <div className="dashboard-grid" aria-hidden="true">
        {Array.from({ length: 4 }, (_, i) => (
          <div className="skeleton-stat" key={i}>
            <div className="skeleton" style={{ width: 48, height: 48, borderRadius: 13, flex: '0 0 auto' }} />
            <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 8 }}>
              <div className="skeleton" style={{ width: '55%' }} />
              <div className="skeleton" style={{ width: '35%' }} />
            </div>
          </div>
        ))}
      </div>
    );
  }

  // Default: table rows
  return (
    <div className="skeleton-table" aria-hidden="true">
      {Array.from({ length: rows }, (_, i) => (
        <div className="skeleton-row" key={i}>
          <div className="skeleton" style={{ width: 44 }} />
          <div className="skeleton" />
          <div className="skeleton" />
          <div className="skeleton" />
          <div className="skeleton w40" style={{ justifySelf: 'end', width: 80 }} />
        </div>
      ))}
    </div>
  );
}
