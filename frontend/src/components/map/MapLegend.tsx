import { getHotspotIcon } from '../../data/mapAssetManifest';

export function MapLegend({ entries }: { entries: Array<{ type: string; label: string }> }) {
  return (
    <div className="map-legend">
      <h3>Legend</h3>
      <div className="map-legend-grid">
        {entries.map((entry) => (
          <span key={entry.type}>
            <b>{getHotspotIcon(entry.type)}</b>
            {entry.label}
          </span>
        ))}
      </div>
    </div>
  );
}
