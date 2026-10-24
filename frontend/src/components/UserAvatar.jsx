import { memo, useState } from 'react';
import { userAvatarUrl } from '../services/api';

function UserAvatar({ path, src, name = '', className = 'avatar-initials' }) {
  const url = src || userAvatarUrl(path);
  const [failedUrl, setFailedUrl] = useState(null);
  const parts = name.trim().split(/\s+/).filter(Boolean);
  const initials = parts.length ? (parts[0][0] + (parts.at(-1)[0] || '')).toUpperCase() : '?';
  const altText = name.trim() ? `${name.trim()}'s avatar` : 'User avatar';

  return url && failedUrl !== url
    ? <img className={`${className} user-avatar-photo`} src={url} alt={altText} onError={() => setFailedUrl(url)} />
    : <div className={className} aria-label={altText} role="img">{initials}</div>;
}

export default memo(UserAvatar);
