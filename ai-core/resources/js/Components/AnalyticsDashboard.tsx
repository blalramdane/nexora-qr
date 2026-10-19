import { useEffect, useState } from 'react';
import { useLocale } from '../i18n/LocaleProvider';

type TrendPoint = { date: string; orders: number; revenue: number };
type TopItem = { name: string; quantity: number; sales: number };
type StatusCount = { status: string; count: number };
type Metrics = {
    range: { days: number; from: string; to: string };
    orders: number;
    completed_orders: number;
    revenue: number;
    aov: number;
    completion_rate: number;
    trend: TrendPoint[];
    top_items: TopItem[];
    status_breakdown: StatusCount[];
};

const STATUS_COLORS: Record<string, string> = {
    new: 'bg-sky-500', confirmed: 'bg-indigo-500', preparing: 'bg-amber-500',
    ready: 'bg-violet-500', completed: 'bg-emerald-500', cancelled: 'bg-rose-500',
};

export default function AnalyticsDashboard({ restaurantId, endpoint, compact = false }: { restaurantId?: number; endpoint?: string; compact?: boolean }) {
    const { locale, dir, t } = useLocale();
    const [days, setDays] = useState<7 | 30>(7);
    const [metrics, setMetrics] = useState<Metrics | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);
    const [attempt, setAttempt] = useState(0);
    const url = endpoint ?? (restaurantId ? `/owner/restaurants/${restaurantId}/analytics` : '/analytics');
    const money = (value: number) => new Intl.NumberFormat(locale === 'ar' ? 'ar-EG' : 'en-EG', { style: 'currency', currency: 'EGP', maximumFractionDigits: 0 }).format(value);
    const number = (value: number) => new Intl.NumberFormat(locale === 'ar' ? 'ar-EG' : 'en-US').format(value);

    useEffect(() => {
        const abort = new AbortController();
        setLoading(true);
        setError(false);
        fetch(`${url}?days=${days}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', signal: abort.signal })
            .then(response => {
                if (!response.ok) throw new Error(`Analytics request failed (${response.status})`);
                return response.json() as Promise<Metrics>;
            })
            .then(data => { if (!abort.signal.aborted) setMetrics(data); })
            .catch(() => { if (!abort.signal.aborted) { setMetrics(null); setError(true); } })
            .finally(() => { if (!abort.signal.aborted) setLoading(false); });
        return () => abort.abort();
    }, [url, days, attempt]);

    const cardData = metrics ? [
        { label: t('analytics.orders'), value: number(metrics.orders), detail: `${number(metrics.completed_orders)} ${t('analytics.completedOrders').toLocaleLowerCase(locale === 'ar' ? 'ar-EG' : 'en-US')}`, tone: 'text-slate-950' },
        { label: t('analytics.revenue'), value: money(metrics.revenue), detail: t('analytics.completedOrders'), tone: 'text-emerald-700' },
        { label: t('analytics.averageOrder'), value: money(metrics.aov), detail: `${number(metrics.completion_rate)}% ${t('analytics.completionRate').toLocaleLowerCase(locale === 'ar' ? 'ar-EG' : 'en-US')}`, tone: 'text-indigo-700' },
    ] : [];
    const maxOrders = Math.max(1, ...(metrics?.trend.map(point => point.orders) ?? []));

    return <section dir={dir} className={`rounded-2xl border border-slate-200 bg-white shadow-sm ${compact ? 'p-4 sm:p-5' : 'p-5 sm:p-6'}`}>
        <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
            <div>
                <p className="text-xs font-black tracking-widest text-indigo-600">NEXORA ANALYTICS</p>
                <h2 className="mt-1 text-xl font-black text-slate-950">{t('analytics.title')}</h2>
                {!compact && <p className="mt-1 text-sm leading-6 text-slate-500">{t('analytics.subtitle')}</p>}
            </div>
            <div className="inline-flex w-fit rounded-xl bg-slate-100 p-1" role="group" aria-label={t('analytics.title')}>
                <button type="button" aria-pressed={days === 7} onClick={() => setDays(7)} className={`rounded-lg px-3 py-2 text-xs font-bold transition ${days === 7 ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-900'}`}>{t('analytics.last7Days')}</button>
                <button type="button" aria-pressed={days === 30} onClick={() => setDays(30)} className={`rounded-lg px-3 py-2 text-xs font-bold transition ${days === 30 ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-900'}`}>{t('analytics.last30Days')}</button>
            </div>
        </div>

        {loading ? <div className="mt-5 grid gap-3 sm:grid-cols-3" role="status" aria-live="polite"><span className="sr-only">{t('analytics.loading')}</span>{[1, 2, 3].map(item => <div key={item} className="h-24 animate-pulse rounded-xl bg-slate-100" />)}</div>
            : error ? <div className="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert"><p>{t('analytics.loadError')}</p><button type="button" onClick={() => setAttempt(value => value + 1)} className="mt-3 rounded-lg border border-rose-200 bg-white px-3 py-2 font-bold">{t('analytics.retry')}</button></div>
                : metrics && <>
                    <div className="mt-5 grid gap-3 sm:grid-cols-3">{cardData.map(card => <article key={card.label} className="rounded-xl border border-slate-100 bg-slate-50/70 p-4"><p className="text-xs font-bold text-slate-500">{card.label}</p><p className={`mt-2 break-words text-2xl font-black tabular-nums ${card.tone}`}>{card.value}</p><p className="mt-1 text-[11px] text-slate-400">{card.detail}</p></article>)}</div>
                    {metrics.orders === 0 ? <p className="mt-4 rounded-xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500">{t('analytics.noData')}</p> : <>
                        <div className="mt-5 rounded-xl border border-slate-100 p-4"><div className="flex items-center justify-between gap-3"><h3 className="font-black">{t('analytics.dailyTrend')}</h3><span className="text-[11px] text-slate-400">{number(metrics.range.days)} {locale === 'ar' ? 'يوم' : 'days'}</span></div>
                            <div className="mt-4 flex min-h-40 items-end gap-1.5 overflow-x-auto pb-1" role="img" aria-label={t('analytics.dailyTrend')}>
                                {metrics.trend.map(point => <div key={point.date} title={`${point.date}: ${number(point.orders)} ${t('analytics.orders')}, ${money(point.revenue)}`} className="flex min-w-[24px] flex-1 flex-col items-center justify-end gap-1 self-stretch">
                                    <span className="text-[9px] font-bold text-slate-500">{point.orders ? number(point.orders) : ''}</span>
                                    <div className="flex h-28 w-full items-end rounded-t-md bg-slate-100"><div className="w-full rounded-t-md bg-indigo-500 transition-[height]" style={{ height: `${point.orders ? Math.max(6, (point.orders / maxOrders) * 100) : 2}%` }} /></div>
                                    <span className="text-[9px] text-slate-500">{new Intl.DateTimeFormat(locale === 'ar' ? 'ar-EG' : 'en-GB', { day: 'numeric', month: days === 7 ? 'short' : undefined }).format(new Date(`${point.date}T12:00:00`))}</span>
                                </div>)}
                            </div>
                        </div>
                        <div className="mt-4 grid gap-4 lg:grid-cols-2">
                            <div className="rounded-xl border border-slate-100 p-4"><h3 className="font-black">{t('analytics.topItems')}</h3>{metrics.top_items.length === 0 ? <p className="mt-3 text-sm text-slate-500">{t('analytics.noData')}</p> : <div className="mt-3 space-y-3">{metrics.top_items.map((item, index) => <div key={`${item.name}-${index}`} className="flex items-center gap-3"><span className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-slate-100 text-xs font-black text-slate-600">{index + 1}</span><div className="min-w-0 flex-1"><p className="truncate text-sm font-bold">{item.name}</p><p className="mt-0.5 text-[11px] text-slate-500">{number(item.quantity)} {t('analytics.quantity')}</p></div><span className="shrink-0 text-xs font-black text-emerald-700">{money(item.sales)}</span></div>)}</div>}</div>
                            <div className="rounded-xl border border-slate-100 p-4"><h3 className="font-black">{t('analytics.statusBreakdown')}</h3><div className="mt-3 space-y-3">{metrics.status_breakdown.map(row => <div key={row.status}><div className="flex items-center justify-between gap-3 text-xs"><span className="font-bold text-slate-600">{t(`order.${row.status}`) === `order.${row.status}` ? row.status : t(`order.${row.status}`)}</span><span className="font-black tabular-nums">{number(row.count)}</span></div><div className="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100"><div className={`h-full rounded-full ${STATUS_COLORS[row.status] ?? 'bg-slate-500'}`} style={{ width: `${Math.max(3, (row.count / Math.max(1, metrics.orders)) * 100)}%` }} /></div></div>)}</div></div>
                        </div>
                    </>}
                </>}
    </section>;
}
