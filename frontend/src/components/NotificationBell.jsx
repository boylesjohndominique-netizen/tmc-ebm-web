import { Bell } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useNotifications } from '../context/NotificationContext';

export default function NotificationBell() {
  const { unread } = useNotifications();
  return <Link to="/admin/notifications" className="icon-button" title="Notifications"><Bell size={21}/>{unread>0 && <span className="notification-count">{unread}</span>}</Link>;
}

