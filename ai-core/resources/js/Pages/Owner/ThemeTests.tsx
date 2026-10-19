import { FormEvent, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import LocaleSwitcher from '../../Components/LocaleSwitcher';
import ABThemeTest from '../../Components/ABThemeTest';
import { useLocale } from '../../i18n/LocaleProvider';

type Menu = { id: number; name: string; slug: string; is_published: boolean };
type Test = {
    id: number;
    menu_id: number | null;
    menu_name: string | null;
    variant_a: string;
    variant_b: string;
    status: 'running' | 'paused' | 'completed';
    traffic_split: number;
    impressions_a: number;
    impressions_b: number;
    conversions_a: number;
    conversions_b: number;
    conversion_rate_a: number;
    conversion_rate_b: number;
    started_at: string | null;
    ended_at: string | null;
};

const styleKeys = ['modern', 'luxury', 'playful', 'classic'] as const;
type Style = typeof styleKeys[number];

export default function ThemeTests({ restaurant, menus, tests }: { restaurant: { id: number; name: string }; menus: Menu[]; tests: Test[] }) {
    const { t, dir, locale } = useLocale();
    const page = usePage().props as { errors?: Record<string, string>; flash?: { success?: string } };
    const [menuId, setMenuId] = useState(menus[0]?.id ?? 0);
    const [variantA, setVariantA] = useState<Style>('modern');
    const [variantB, setVariantB] = useState<Style>('luxury');
    const [trafficSplit, setTrafficSplit] = useState(50);
    const endpoint = `/owner/restaurants/${restaurant.id}/theme-tests`;
    const number = (value: number) => new Intl.NumberFormat(locale === 'ar' ? 'ar-EG' : 'en-US', { maximumFractionDigits: 2 }).format(value);
    const date = (value: string | null) => value ? new Intl.DateTimeFormat(locale === 'ar' ? 'ar-EG' : 'en-GB', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';
    const styleLabel = (style: string) => t(`components.${style}`);
    const statusLabel = (status: Test['status']) => t(`themeTests.status.${status}`);

    function createTest(event: FormEvent) {
        event.preventDefault();
        if (!menuId || variantA === variantB) return;
        router.post(endpoint, { menu_id: menuId, variant_a: variantA, variant_b: variantB, traffic_split: trafficSplit });
    }

    function updateTest(test: Test, data: { status?: Test['status']; traffic_split?: number }) {
        router.patch(`${endpoint}/${test.id}`, data, { preserveScroll: true });
    }

    return <main dir={dir} className="min-h-screen bg-slate-50 text-slate-950">
        <Head title={t('themeTests.title')} />
        <header className="border-b border-slate-200 bg-white">
            <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                <div>
                    <Link href="/owner/menus" className="text-xs font-bold text-slate-500 hover:text-slate-950">{t('themeTests.backToStudio')}</Link>
                    <p className="mt-2 text-[10px] font-black tracking-[.2em] text-fuchsia-700">NEXORA · {t('themeTests.title')}</p>
                    <h1 className="mt-1 text-2xl font-black">{t('themeTests.title')}</h1>
                    <p className="mt-1 text-sm text-slate-500">{restaurant.name} · {t('themeTests.subtitle')}</p>
                </div>
                <LocaleSwitcher />
            </div>
        </header>

        <div className="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6">
            {page.flash?.success && <p role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{page.flash.success}</p>}
            {Object.keys(page.errors ?? {}).length > 0 && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{Object.values(page.errors ?? {}).join(' ')}</div>}

            <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div>
                    <p className="text-xs font-black tracking-widest text-fuchsia-700">{t('themeTests.newExperiment')}</p>
                    <h2 className="mt-1 text-xl font-black">{t('themeTests.createTitle')}</h2>
                    <p className="mt-1 text-sm leading-6 text-slate-500">{t('themeTests.createDescription')}</p>
                </div>
                {menus.length === 0 ? <div className="mt-4 rounded-xl border border-dashed border-slate-300 p-6 text-center"><p className="font-bold">{t('themeTests.noPublishedMenus')}</p><Link href="/owner/menus" className="mt-3 inline-flex rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white">{t('themeTests.openStudio')}</Link></div> : <form onSubmit={createTest} className="mt-5 space-y-4">
                    <label className="block text-sm font-bold">{t('themeTests.targetMenu')}<select value={menuId} onChange={event => setMenuId(Number(event.target.value))} className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 font-normal">{menus.map(menu => <option key={menu.id} value={menu.id}>{menu.name}</option>)}</select></label>
                    <ABThemeTest variantA={styleLabel(variantA)} variantB={styleLabel(variantB)} split={trafficSplit} onChange={setTrafficSplit} />
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="block text-sm font-bold">{t('themeTests.variantA')}<select value={variantA} onChange={event => setVariantA(event.target.value as Style)} className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 font-normal">{styleKeys.map(style => <option key={style} value={style}>{styleLabel(style)}</option>)}</select></label>
                        <label className="block text-sm font-bold">{t('themeTests.variantB')}<select value={variantB} onChange={event => setVariantB(event.target.value as Style)} className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 font-normal">{styleKeys.map(style => <option key={style} value={style}>{styleLabel(style)}</option>)}</select></label>
                    </div>
                    {variantA === variantB && <p role="alert" className="text-sm font-bold text-rose-700">{t('themeTests.variantsMustDiffer')}</p>}
                    <div className="rounded-xl bg-slate-50 p-3 text-xs leading-6 text-slate-600">{t('themeTests.metricsNote')}</div>
                    <button type="submit" disabled={!menuId || variantA === variantB} className="w-full rounded-xl bg-fuchsia-700 px-4 py-3 font-black text-white transition hover:bg-fuchsia-800 disabled:cursor-not-allowed disabled:opacity-40">{t('themeTests.startTest')}</button>
                </form>}
            </section>

            <section className="space-y-4">
                <div className="flex items-end justify-between gap-3"><div><p className="text-xs font-black tracking-widest text-slate-400">{t('themeTests.results')}</p><h2 className="mt-1 text-xl font-black">{t('themeTests.history')}</h2></div><span className="rounded-full bg-slate-200 px-3 py-1 text-xs font-black">{number(tests.length)}</span></div>
                {tests.length === 0 ? <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">{t('themeTests.noTests')}</div> : tests.map(test => <article key={test.id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div><p className="text-[10px] font-black tracking-widest text-fuchsia-700">{t('themeTests.testNumber')} #{test.id}</p><h3 className="mt-1 text-lg font-black">{test.menu_name ?? t('themeTests.menuUnavailable')}</h3><p className="mt-1 text-xs text-slate-500">{t('themeTests.started')}: {date(test.started_at)}</p></div>
                        <span className={`rounded-full px-3 py-1.5 text-xs font-black ${test.status === 'running' ? 'bg-emerald-50 text-emerald-700' : test.status === 'paused' ? 'bg-amber-50 text-amber-800' : 'bg-slate-100 text-slate-600'}`}>{statusLabel(test.status)}</span>
                    </div>
                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                        {([{ key: 'a' as const, style: test.variant_a, impressions: test.impressions_a, conversions: test.conversions_a, rate: test.conversion_rate_a }, { key: 'b' as const, style: test.variant_b, impressions: test.impressions_b, conversions: test.conversions_b, rate: test.conversion_rate_b }]).map(variant => <div key={variant.key} className="rounded-xl border border-slate-100 bg-slate-50 p-4"><div className="flex items-center justify-between gap-2"><p className="font-black">{variant.key === 'a' ? t('themeTests.variantA') : t('themeTests.variantB')} · {styleLabel(variant.style)}</p><span className="text-xs font-black text-fuchsia-700">{number(variant.rate)}%</span></div><div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-200"><div className="h-full rounded-full bg-fuchsia-600" style={{ width: `${Math.min(100, Math.max(0, variant.rate))}%` }} /></div><div className="mt-3 grid grid-cols-2 gap-2 text-xs"><div><p className="text-slate-500">{t('themeTests.impressions')}</p><p className="mt-1 text-lg font-black tabular-nums">{number(variant.impressions)}</p></div><div><p className="text-slate-500">{t('themeTests.conversions')}</p><p className="mt-1 text-lg font-black tabular-nums">{number(variant.conversions)}</p></div></div></div>)}
                    </div>
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                        <div className="text-xs text-slate-500">{t('themeTests.trafficSplit')}: {number(100 - test.traffic_split)}% / {number(test.traffic_split)}%{test.ended_at ? ` · ${t('themeTests.ended')}: ${date(test.ended_at)}` : ''}</div>
                        {test.status !== 'completed' && <div className="flex flex-wrap gap-2">{test.status === 'running' ? <button type="button" onClick={() => updateTest(test, { status: 'paused' })} className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-black text-amber-800">{t('themeTests.pause')}</button> : <button type="button" onClick={() => updateTest(test, { status: 'running' })} className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-black text-emerald-800">{t('themeTests.resume')}</button>}<button type="button" onClick={() => updateTest(test, { status: 'completed' })} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-black text-slate-700">{t('themeTests.complete')}</button><label className="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold"><span>{t('themeTests.trafficSplitLabel')}</span><select value={test.traffic_split} onChange={event => updateTest(test, { traffic_split: Number(event.target.value) })} className="bg-transparent font-black"><option value={0}>0%</option><option value={25}>25%</option><option value={50}>50%</option><option value={75}>75%</option><option value={100}>100%</option></select></label></div>}
                    </div>
                </article>)}
            </section>
        </div>
    </main>;
}
