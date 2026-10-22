import { useState } from 'react';
import { userAvatarUrl } from '../services/api';

export default function UserAvatar({ path, src, name = '', className = 'avatar-initials' }) {
  const url = src || userAvatarUrl(path);
  const [failedUrl, setFailedUrl] = useState(null);
  const parts = name.trim().split(/\s+/).filter(Boolean);
  const initials = parts.length ? (parts[0][0] + (parts.at(-1)[0] || '')).toUpperCase() : '?';

  return url && failedUrl !== url
    ? <img className={`${className} user-avatar-photo`} src={url} alt="Profile picture" onError={() => setFailedUrl(url)} />
    : <div className={className}>{initials}</div>;
}
