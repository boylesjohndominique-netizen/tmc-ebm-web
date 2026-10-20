import { useState } from 'react';
import { Package } from 'lucide-react';
import { equipmentImageUrl } from '../services/api';
import StatusBadge from './StatusBadge';

export default function EquipmentCard({ item, onBorrow }) {
  const [imgError, setImgError] = useState(false);
  const total = Math.max(item.quantity ?? 0, 1);
  const available = item.available_quantity ?? 0;
  const pct = Math.min(100, Math.round((available / total) * 100));
  const meterClass = available === 0 ? 'out' : pct <= 25 ? 'low' : '';
  const mainSrc = !imgError ? equipmentImageUrl(item.image) : null;

  return (
    <div className="equipment-card">
      <div className="equipment-image">
        {mainSrc ? (
          <img
            src={mainSrc}
            alt={item.name}
            className="equipment-card-photo"
            onError={() => setImgError(true)}
          />
        ) : (
          <Package size={46} />
        )}
      </div>
      <div className="equipment-content">
        <div className="row-between">
          <h3>{item.name}</h3>
          <StatusBadge status={item.status} />
        </div>
        <p>{item.description || 'No description available.'}</p>

        <div className={`stock-meter ${meterClass}`}>
          <i style={{ width: `${pct}%` }} />
        </div>

        <div className="equipment-meta">
          <span>Category: <b>{item.category?.name || '—'}</b></span>
          <span>Available: <b>{available} / {item.quantity}</b></span>
        </div>

        <button className="btn btn-primary full" disabled={!available} onClick={() => onBorrow(item)}>
          {available ? 'Borrow Equipment' : 'Unavailable'}
        </button>
      </div>
    </div>
  );
}
