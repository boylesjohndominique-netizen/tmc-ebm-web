import { useCallback, useEffect, useState } from 'react';
import {
  ResponsiveContainer, AreaChart, Area, XAxis, YAxis,
  CartesianGrid, Tooltip, Legend, PieChart, Pie, Cell,
  BarChart, Bar, LabelList,
} from 'recharts';
import { TrendingUp, PieChart as PieIcon, Trophy, Boxes, Users, AlertCircle } from 'lucide-react';
import api from '../services/api';
import { fmtMonth } from '../utils/datetime';
import { useTheme } from '../context/ThemeContext';
import useRealtimeResource from '../hooks/useRealtimeResource';

/* Chart palettes — mirrors the CSS design system (light + dark) */
const LIGHT = {
  blue: '#0b4ea2', blueSoft: '#7fa3cf', yellow: '#f6c800', yellowLine: '#d9ac00',
  green: '#12805c', red: '#b42318', gray: '#94a3b8',
  grid: '#edf2f8', tick: '#64748b', tickSoft: '#94a3b8', label: '#16233a',
  cursor: '#c3d2e5', cursorFill: 'rgba(11, 78, 162, .05)', donutStroke: '#ffffff',
  tooltip: {
    background: '#fff', border: '1px solid #e2e9f2', color: '#16233a',
    boxShadow: '0 10px 28px -6px rgba(7, 59, 120, .18)',
  },
};

const DARK = {
  blue: '#5b9bff', blueSoft: '#9dc2ff', yellow: '#ffd83d', yellowLine: '#ffd83d',
  green: '#4ade9d', red: '#ff8a7d', gray: '#66758a',
  grid: '#2a3a52', tick: '#8b98ab', tickSoft: '#66758a', label: '#e3e9f2',
  cursor: '#3d5273', cursorFill: 'rgba(91, 155, 255, .08)', donutStroke: '#131c2e',
  tooltip: {
    background: '#1a2538', border: '1px solid #2a3a52', color: '#e3e9f2',
    boxShadow: '0 10px 28px -6px rgba(0, 0, 0, .55)',
  },
};

function ChartCard({ icon: Icon, title, subtitle, children, className = '' }) {
  return (
    <div className={`analytics-card ${className}`}>
      <div className="analytics-card-head">
        <div className="analytics-card-icon"><Icon size={17} /></div>
        <div>
          <h3>{title}</h3>
          {subtitle && <p>{subtitle}</p>}
        </div>
      </div>
      <div className="analytics-card-body">{children}</div>
    </div>
  );
}

function EmptyChart({ label = 'No data yet' }) {
  return <div className="analytics-empty"><AlertCircle size={18} /> {label}</div>;
}

export default function AnalyticsSection() {
  const { theme } = useTheme();
  const C = theme === 'dark' ? DARK : LIGHT;
  const STATUS_COLORS = {
    Pending: C.yellow,
    Approved: C.blue,
    Confirmed: C.blueSoft,
    Completed: C.green,
    Rejected: C.red,
    Cancelled: C.gray,
  };
  const ROLE_COLORS = [C.blue, C.yellow, C.green, C.red, C.gray, C.blueSoft];
  const tooltipStyle = {
    background: C.tooltip.background,
    border: C.tooltip.border,
    borderRadius: 10,
    boxShadow: C.tooltip.boxShadow,
    fontSize: 12.5,
    fontFamily: 'Inter, sans-serif',
    color: C.tooltip.color,
  };

  const [a, setA] = useState(null);

  const load = useCallback(() => {
    return api.get('/admin/analytics')
      .then(r => setA(r.data))
      .catch(() => setA({ failed: true }));
  }, []);

  useEffect(() => { load(); }, [load]);

  // Charts track borrowing activity, returns and stock — keep them live.
  useRealtimeResource(['equipment', 'borrowings', 'returns', 'users'], load);

  if (!a) {
    return (
      <div className="panel analytics-panel" aria-hidden="true">
        <h2 className="analytics-title">Analytics</h2>
        <div className="analytics-grid">
          {[0, 1].map(i => (
            <div className="analytics-card" key={i}>
              <div className="skeleton" style={{ height: 18, width: '40%', marginBottom: 18 }} />
              <div className="skeleton" style={{ height: 240, borderRadius: 10 }} />
            </div>
          ))}
        </div>
      </div>
    );
  }

  if (a.failed) return null; // charts are a bonus — never block the dashboard

  const m = a.metrics || {};
  const statusTotal = (a.status_mix || []).reduce((s, x) => s + x.value, 0);

  // Month axis labels in the shared date style ("Apr 2026"), plus full
  // dates in each point's tooltip.
  const monthly = (a.monthly_activity || []).map(x => ({
    ...x,
    label: fmtMonth(x.month),
  }));

  return (
    <div className="panel analytics-panel">
      <div className="panel-header analytics-head">
        <div>
          <h2>Analytics</h2>
          <p className="muted" style={{ fontSize: 12.5, margin: 0 }}>
            Last 6 months of borrowing activity.
          </p>
        </div>
        <div className="analytics-metric-pills">
          <div className="metric-pill">
            <span className="metric-pill-value">{m.approval_rate ?? 0}%</span>
            <span className="metric-pill-label">Approval rate</span>
          </div>
          <div className="metric-pill">
            <span className="metric-pill-value">{m.completion_rate ?? 0}%</span>
            <span className="metric-pill-label">Completion rate</span>
          </div>
          <div className="metric-pill warn">
            <span className="metric-pill-value">{m.pending_returns ?? 0}</span>
            <span className="metric-pill-label">Pending returns</span>
          </div>
        </div>
      </div>

      <div className="analytics-grid">
        {/* Monthly activity — area chart */}
        <ChartCard
          className="span-2"
          icon={TrendingUp}
          title="Borrowing & Returns Trend"
          subtitle="Requests created vs. returns processed, per month"
        >
          {monthly.some(x => x.requests || x.returns) ? (
            <ResponsiveContainer width="100%" height={260}>
              <AreaChart data={monthly} margin={{ top: 6, right: 12, left: -14, bottom: 0 }}>
                <defs>
                  <linearGradient id="gRequests" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor={C.blue} stopOpacity={0.32} />
                    <stop offset="100%" stopColor={C.blue} stopOpacity={0.02} />
                  </linearGradient>
                  <linearGradient id="gReturns" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor={C.yellow} stopOpacity={0.38} />
                    <stop offset="100%" stopColor={C.yellow} stopOpacity={0.03} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke={C.grid} vertical={false} />
                <XAxis dataKey="label" tick={{ fontSize: 11.5, fill: C.tick }} axisLine={false} tickLine={false} />
                <YAxis allowDecimals={false} tick={{ fontSize: 11.5, fill: C.tickSoft }} axisLine={false} tickLine={false} width={40} />
                <Tooltip
                  contentStyle={tooltipStyle}
                  cursor={{ stroke: C.cursor, strokeDasharray: '4 4' }}
                  labelFormatter={(label, payload) => fmtMonth(payload?.[0]?.payload?.month || label)}
                />
                <Legend iconType="circle" iconSize={8} wrapperStyle={{ fontSize: 12, paddingTop: 6 }} />
                <Area type="monotone" dataKey="requests" name="Borrowing requests" stroke={C.blue} strokeWidth={2.4} fill="url(#gRequests)" dot={{ r: 3, fill: C.blue, strokeWidth: 0 }} activeDot={{ r: 5 }} />
                <Area type="monotone" dataKey="returns" name="Returns" stroke={C.yellowLine} strokeWidth={2.4} fill="url(#gReturns)" dot={{ r: 3, fill: C.yellowLine, strokeWidth: 0 }} activeDot={{ r: 5 }} />
              </AreaChart>
            </ResponsiveContainer>
          ) : <EmptyChart label="No borrowing activity in the last 6 months" />}
        </ChartCard>

        {/* Status mix — donut */}
        <ChartCard
          icon={PieIcon}
          title="Request Status"
          subtitle={statusTotal ? `${statusTotal} requests in total` : undefined}
        >
          {statusTotal ? (
            <div className="donut-wrap">
              <ResponsiveContainer width="100%" height={210}>
                <PieChart>
                  <Pie
                    data={a.status_mix}
                    dataKey="value"
                    nameKey="name"
                    innerRadius={62}
                    outerRadius={92}
                    paddingAngle={3}
                    strokeWidth={2}
                    stroke={C.donutStroke}
                  >
                    {a.status_mix.map(entry => (
                      <Cell key={entry.name} fill={STATUS_COLORS[entry.name] || C.blueSoft} />
                    ))}
                  </Pie>
                  <Tooltip contentStyle={tooltipStyle} />
                </PieChart>
              </ResponsiveContainer>
              <div className="donut-center">
                <b>{statusTotal}</b>
                <span>requests</span>
              </div>
              <ul className="analytics-legend">
                {a.status_mix.map(entry => (
                  <li key={entry.name}>
                    <i style={{ background: STATUS_COLORS[entry.name] || C.blueSoft }} />
                    <span>{entry.name}</span>
                    <b>{entry.value}</b>
                  </li>
                ))}
              </ul>
            </div>
          ) : <EmptyChart label="No requests yet" />}
        </ChartCard>

        {/* Top equipment — horizontal bars */}
        <ChartCard
          icon={Trophy}
          title="Most Borrowed Equipment"
          subtitle="Top 5 by total units requested"
        >
          {(a.top_equipment || []).length ? (
            <ResponsiveContainer width="100%" height={240}>
              <BarChart data={a.top_equipment} layout="vertical" margin={{ top: 0, right: 42, left: 0, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke={C.grid} horizontal={false} />
                <XAxis type="number" allowDecimals={false} hide />
                <YAxis
                  type="category" dataKey="name" width={150}
                  tick={{ fontSize: 11.5, fill: C.tickSoft }}
                  axisLine={false} tickLine={false}
                />
                <Tooltip contentStyle={tooltipStyle} cursor={{ fill: C.cursorFill }} />
                <Bar dataKey="value" name="Units requested" fill={C.blue} radius={[0, 7, 7, 0]} barSize={18}>
                  <LabelList dataKey="value" position="right" style={{ fontSize: 11.5, fontWeight: 700, fill: C.label }} />
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          ) : <EmptyChart label="No borrowing history yet" />}
        </ChartCard>

        {/* Stock availability — donut */}
        <ChartCard
          icon={Boxes}
          title="Stock Availability"
          subtitle="Units available vs. currently on loan"
        >
          {(a.stock || []).some(x => x.value) ? (
            <div className="donut-wrap">
              <ResponsiveContainer width="100%" height={210}>
                <PieChart>
                  <Pie
                    data={a.stock}
                    dataKey="value"
                    nameKey="name"
                    innerRadius={62}
                    outerRadius={92}
                    paddingAngle={3}
                    strokeWidth={2}
                    stroke={C.donutStroke}
                    startAngle={90}
                    endAngle={-270}
                  >
                    <Cell fill={C.green} />
                    <Cell fill={C.yellow} />
                  </Pie>
                  <Tooltip contentStyle={tooltipStyle} />
                </PieChart>
              </ResponsiveContainer>
              <div className="donut-center">
                <b>{a.stock[0]?.value ?? 0}</b>
                <span>available</span>
              </div>
              <ul className="analytics-legend">
                {a.stock.map(entry => (
                  <li key={entry.name}>
                    <i style={{ background: entry.name === 'Available' ? C.green : C.yellow }} />
                    <span>{entry.name}</span>
                    <b>{entry.value}</b>
                  </li>
                ))}
              </ul>
            </div>
          ) : <EmptyChart label="No equipment registered" />}
        </ChartCard>

        {/* Users by role — column chart */}
        <ChartCard
          icon={Users}
          title="Users by Role"
          subtitle="Registered accounts per role"
        >
          {(a.users_by_role || []).length ? (
            <ResponsiveContainer width="100%" height={240}>
              <BarChart data={a.users_by_role} margin={{ top: 22, right: 8, left: -18, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke={C.grid} vertical={false} />
                <XAxis dataKey="name" tick={{ fontSize: 11, fill: C.tick }} axisLine={false} tickLine={false} interval={0} />
                <YAxis allowDecimals={false} tick={{ fontSize: 11.5, fill: C.tickSoft }} axisLine={false} tickLine={false} width={40} />
                <Tooltip contentStyle={tooltipStyle} cursor={{ fill: C.cursorFill }} />
                <Bar dataKey="value" name="Users" radius={[7, 7, 0, 0]} barSize={38}>
                  {a.users_by_role.map((entry, i) => (
                    <Cell key={entry.name} fill={ROLE_COLORS[i % ROLE_COLORS.length]} />
                  ))}
                  <LabelList dataKey="value" position="top" style={{ fontSize: 11.5, fontWeight: 700, fill: C.label }} />
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          ) : <EmptyChart label="No users yet" />}
        </ChartCard>
      </div>
    </div>
  );
}
