import { useMemo, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import LocaleSwitcher from '../../Components/LocaleSwitcher';
import { useLocale } from '../../i18n/LocaleProvider';

type MenuOption = { id: number; name: string };
type MenuItemDraft = { name: string; price: number | null; description?: string | null; uncertain?: boolean };
type MenuCategoryDraft = { name: string; items: MenuItemDraft[] };
type Job = {
    id: number;
    menu_id?: number | null;
    status: string;
    image_paths: string[];
    extracted_data?: { categories?: MenuCategoryDraft[]; note?: string } | null;
    reviewed_data?: { categories?: MenuCategoryDraft[] } | null;
    import_summary?: { categories: number; products: number; failed?: Array<{ category: string; item?: string | null; reason: string }> } | null;
    error_message?: string | null;
    created_at?: string | null;
};

type ScanResponse = Job & { job?: Job; summary?: Job['import_summary']; message?: string; error?: string };

export default function ScanMenu({ restaurantId, menus, recentJobs = [] }: { restaurantId: number; menus: MenuOption[]; recentJobs?: Job[] }) {
    const { t, dir, locale } = useLocale();
    const [selectedMenuId, setSelectedMenuId] = useState<number>(menus[0]?.id ?? 0);
    const [files, setFiles] = useState<File[]>([]);
    const [job, setJob] = useState<Job | null>(null);
    const [reviewed, setReviewed] = useState<MenuCategoryDraft[]>([]);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [message, setMessage] = useState<string | null>(null);

    const endpoint = `/owner/restaurants/${restaurantId}/menu-scans`;
    const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    const canApprove = reviewed.length > 0 && reviewed.every(category => category.name.trim() && category.items.length > 0 && category.items.every(item => item.name.trim() && item.price !== null && Number.isFinite(item.price) && item.price >= 0));
    const statusLabel = (status: string) => ({ uploaded: locale === 'ar' ? 'مرفوع — جاهز للتحليل' : 'Uploaded — ready to process', processing: locale === 'ar' ? 'قيد التحليل' : 'Processing', extracted: locale === 'ar' ? 'تم الاستخراج — يحتاج مراجعة' : 'Extracted — needs review', reviewed: locale === 'ar' ? 'تمت المراجعة' : 'Reviewed', imported: locale === 'ar' ? 'تم الاستيراد' : 'Imported', failed: locale === 'ar' ? 'فشل التحليل' : 'Failed' }[status] ?? status);

    async function requestJson(url: string, init: RequestInit = {}): Promise<ScanResponse> {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...init,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrf() ? { 'X-CSRF-TOKEN': csrf()! } : {}),
                ...(init.headers ?? {}),
            },
        });
        const result = await response.json().catch(() => ({})) as ScanResponse;
        if (!response.ok) {
            const errors = Object.values((result as any).errors ?? {}).flat().join(' ');
            throw new Error(result.message || result.error || errors || `HTTP ${response.status}`);
        }
        return result;
    }

    function applyJob(next: Job) {
        setJob(next);
        const source = next.reviewed_data?.categories ?? next.extracted_data?.categories ?? [];
        setReviewed(source.map(category => ({
            name: category.name ?? '',
            items: (category.items ?? []).map(item => ({
                name: item.name ?? '',
                price: item.price == null ? null : Number(item.price),
                description: item.description ?? '',
                uncertain: Boolean(item.uncertain),
            })),
        })));
    }

    async function upload() {
        if (busy || !files.length || !selectedMenuId) return;
        setBusy(true); setError(null); setMessage(null);
        try {
            const form = new FormData();
            form.append('menu_id', String(selectedMenuId));
            files.forEach(file => form.append('images[]', file));
            const result = await requestJson(endpoint, { method: 'POST', body: form });
            applyJob(result);
            setMessage(locale === 'ar' ? 'تم رفع صور المنيو. ابدأ التحليل ثم راجع الأصناف والأسعار قبل الاستيراد.' : 'Menu images uploaded. Process them, then review every item and price before importing.');
        } catch (cause) {
            setError(cause instanceof Error ? cause.message : t('common.error'));
        } finally { setBusy(false); }
    }

    async function process() {
        if (!job || busy || !['uploaded', 'failed'].includes(job.status)) return;
        setBusy(true); setError(null); setMessage(null);
        try {
            const result = await requestJson(`${endpoint}/${job.id}/process`, { method: 'POST' });
            applyJob(result);
            setMessage(result.error_message || (locale === 'ar' ? 'تم التحليل. راجع الأسماء والأسعار المصنفة كغير مؤكدة.' : 'Processing finished. Review names and any uncertain prices.'));
        } catch (cause) {
            setError(cause instanceof Error ? cause.message : t('common.error'));
        } finally { setBusy(false); }
    }

    async function approve() {
        if (!job || busy || !canApprove || !['extracted', 'reviewed'].includes(job.status)) return;
        setBusy(true); setError(null); setMessage(null);
        try {
            let approvedJob = job;
            if (job.status === 'extracted') {
                const reviewedResponse = await requestJson(`${endpoint}/${job.id}/review`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ reviewed_data: { categories: reviewed } }),
                });
                approvedJob = reviewedResponse;
                setJob(reviewedResponse);
            }
            const result = await requestJson(`${endpoint}/${approvedJob.id}/approve`, { method: 'POST' });
            setJob(result.job ?? { ...approvedJob, status: 'imported', import_summary: result.summary });
            setMessage(`${locale === 'ar' ? 'تم استيراد' : 'Imported'} ${result.summary?.products ?? 0} ${locale === 'ar' ? 'صنف في' : 'items in'} ${result.summary?.categories ?? 0} ${locale === 'ar' ? 'تصنيف.' : 'categories.'}`);
        } catch (cause) {
            setError(cause instanceof Error ? cause.message : t('common.error'));
        } finally { setBusy(false); }
    }

    const itemCount = useMemo(() => reviewed.reduce((sum, category) => sum + category.items.length, 0), [reviewed]);

    return <main dir={dir} className="min-h-screen bg-slate-50 text-slate-950">
        <Head title={t('dashboard.scanMenu')} />
        <header className="border-b border-slate-200 bg-white"><div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-4"><div><Link href="/owner/menus" className="text-xs font-bold text-slate-500 hover:text-slate-950">{locale === 'ar' ? 'العودة لاستوديو المنيوهات' : 'Back to Owner Studio'}</Link><h1 className="mt-2 text-2xl font-black">{t('dashboard.scanMenu')}</h1><p className="mt-1 text-sm text-slate-500">{t('dashboard.scanMenuDescription')}</p></div><LocaleSwitcher /></div></header>
        <div className="mx-auto max-w-5xl space-y-5 px-4 py-6">
            {error && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{error}</div>}
            {message && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{message}</div>}

            {!job || job.status === 'imported' ? <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 className="text-lg font-black">{locale === 'ar' ? 'ابدأ من صور المنيو' : 'Start from menu photos'}</h2>
                {menus.length === 0 ? <div className="mt-4 rounded-xl border border-dashed p-6 text-center"><p className="font-bold">{locale === 'ar' ? 'لا توجد منيوهات لهذا المطعم' : 'This restaurant has no menus yet'}</p><Link href="/owner/menus" className="mt-3 inline-flex rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white">{t('dashboard.createMenu')}</Link></div> : <div className="mt-4 space-y-4">
                    <label className="block text-sm font-bold">{locale === 'ar' ? 'المنيو المستهدف' : 'Target menu'}<select value={selectedMenuId} onChange={event => setSelectedMenuId(Number(event.target.value))} className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 font-normal">{menus.map(menu => <option key={menu.id} value={menu.id}>{menu.name}</option>)}</select></label>
                    <label className="block rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 p-6 text-center"><span className="block text-3xl">📄</span><span className="mt-2 block font-bold">{locale === 'ar' ? 'اختر صور صفحات المنيو' : 'Choose menu page images'}</span><span className="mt-1 block text-xs text-slate-500">JPG, PNG, WEBP · 10 images max · 10MB each</span><input type="file" accept="image/jpeg,image/png,image/webp" multiple onChange={event => setFiles(Array.from(event.target.files ?? []))} className="mt-4 block w-full text-sm" /></label>
                    {files.length > 0 && <p className="text-xs text-slate-500">{files.length} {locale === 'ar' ? 'ملف محدد' : 'file(s) selected'}</p>}
                    <button type="button" disabled={busy || !files.length || !selectedMenuId} onClick={upload} className="w-full rounded-xl bg-emerald-700 px-4 py-3 font-black text-white disabled:opacity-40">{busy ? t('common.loading') : locale === 'ar' ? 'رفع الصور وبدء المهمة' : 'Upload images and create scan job'}</button>
                </div>}
            </section> : <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-xs font-black tracking-widest text-indigo-700">SCAN #{job.id}</p><h2 className="mt-1 text-xl font-black">{menus.find(menu => menu.id === job.menu_id)?.name ?? (locale === 'ar' ? 'منيو مستهدف' : 'Target menu')}</h2></div><span className="rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-800">{statusLabel(job.status)}</span></div>
                <div className="mt-4 flex flex-wrap gap-2">{job.image_paths?.map((path, index) => <img key={`${path}-${index}`} src={`/storage/${path}`} alt="" className="h-20 w-20 rounded-lg border border-slate-200 object-cover" />)}</div>
                {job.error_message && <p className="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-800">{job.error_message}</p>}
                {job.status === 'uploaded' || job.status === 'failed' ? <button type="button" disabled={busy} onClick={process} className="mt-4 rounded-xl bg-indigo-700 px-5 py-3 text-sm font-black text-white disabled:opacity-40">{busy ? t('common.loading') : locale === 'ar' ? 'بدء التحليل بالـAI' : 'Process with AI'}</button> : null}
                {job.status === 'extracted' && reviewed.length === 0 && <p className="mt-4 rounded-xl border border-dashed p-5 text-sm text-slate-500">{locale === 'ar' ? 'لم يتم استخراج أصناف واضحة. جرّب صورًا أوضح أو أدخل الأصناف يدويًا.' : 'No clear items were extracted. Try clearer photos or enter the items manually.'}</p>}
                {['extracted', 'reviewed'].includes(job.status) && reviewed.length > 0 && <div className="mt-5 space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-2"><div><h3 className="font-black">{locale === 'ar' ? 'مراجعة الأصناف والأسعار' : 'Review item names and prices'}</h3><p className="mt-1 text-xs text-slate-500">{locale === 'ar' ? 'الأسعار غير المؤكدة لا تُقبل. التعديلات لن تُستورد إلا بعد الضغط على الاعتماد.' : 'Uncertain or missing prices must be corrected. Changes are not imported until you approve.'}</p></div><span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{itemCount} {t('analytics.item')}</span></div>
                    {reviewed.map((category, categoryIndex) => <div key={`category-${categoryIndex}`} className="rounded-xl border border-slate-200 p-4"><label className="block text-sm font-bold">{locale === 'ar' ? 'اسم القسم' : 'Category name'}<input value={category.name} onChange={event => setReviewed(current => current.map((entry, index) => index === categoryIndex ? { ...entry, name: event.target.value } : entry))} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 font-normal" /></label>
                        <div className="mt-3 space-y-2">{category.items.map((item, itemIndex) => <div key={`item-${categoryIndex}-${itemIndex}`} className="grid gap-2 rounded-lg bg-slate-50 p-3 sm:grid-cols-[1fr_150px]"><label className="text-xs font-bold">{locale === 'ar' ? 'اسم الصنف' : 'Item name'}<input value={item.name} onChange={event => setReviewed(current => current.map((entry, ci) => ci === categoryIndex ? { ...entry, items: entry.items.map((row, ii) => ii === itemIndex ? { ...row, name: event.target.value } : row) } : entry))} className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-normal" />{item.uncertain && <span className="mt-1 block text-amber-700">{locale === 'ar' ? 'راجع الاسم' : 'Verify this name'}</span>}</label><label className="text-xs font-bold">{locale === 'ar' ? 'السعر' : 'Price'}<input type="number" min="0" step="0.01" value={item.price ?? ''} onChange={event => setReviewed(current => current.map((entry, ci) => ci === categoryIndex ? { ...entry, items: entry.items.map((row, ii) => ii === itemIndex ? { ...row, price: event.target.value === '' ? null : Number(event.target.value), uncertain: false } : row) } : entry))} className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-normal" placeholder={locale === 'ar' ? 'أدخل السعر المؤكد' : 'Enter confirmed price'} /></label></div>)}</div>
                    </div>)}
                    <button type="button" disabled={busy || !canApprove || job.status === 'reviewed'} onClick={approve} className="w-full rounded-xl bg-emerald-700 px-4 py-3 font-black text-white disabled:opacity-40">{busy ? t('common.loading') : job.status === 'reviewed' ? (locale === 'ar' ? 'جارٍ الاعتماد' : 'Approved') : locale === 'ar' ? 'اعتماد المراجعة واستيراد الأصناف' : 'Approve review and import items'}</button>
                </div>}
                {job.status === 'imported' && <button type="button" onClick={() => { setJob(null); setReviewed([]); setFiles([]); }} className="mt-4 rounded-xl bg-slate-950 px-4 py-3 text-sm font-bold text-white">{locale === 'ar' ? 'بدء مسح جديد' : 'Start another scan'}</button>}
            </section>}

            {recentJobs.length > 0 && <section className="rounded-2xl border border-slate-200 bg-white p-5"><h2 className="font-black">{locale === 'ar' ? 'المهام الأخيرة' : 'Recent scan jobs'}</h2><div className="mt-3 space-y-2">{recentJobs.map(recent => <button key={recent.id} type="button" onClick={() => applyJob(recent)} className="flex w-full items-center justify-between gap-3 rounded-xl border border-slate-100 p-3 text-start hover:bg-slate-50"><span><span className="block text-sm font-bold">#{recent.id} · {menus.find(menu => menu.id === recent.menu_id)?.name ?? (locale === 'ar' ? 'منيو محذوفة/غير معروفة' : 'Menu unavailable')}</span><span className="mt-1 block text-xs text-slate-500">{statusLabel(recent.status)}</span></span><span className="text-xs text-indigo-700">{t('common.open')}</span></button>)}</div></section>}
        </div>
    </main>;
}
