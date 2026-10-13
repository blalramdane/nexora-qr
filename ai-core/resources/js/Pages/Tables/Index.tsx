import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useEffect, useMemo, useState } from 'react';
import QRCode from 'qrcode';

type RestaurantTable = { id: number; name: string; is_active: boolean; token: string };
type Menu = { id: number; name: string; url: string };

export default function TablesIndex({ tables, menus, managementBase = '/tables' }: { tables: RestaurantTable[]; menus: Menu[]; managementBase?: string }) {
    const [name, setName] = useState('');
    const [selectedMenuId, setSelectedMenuId] = useState(menus[0] ? String(menus[0].id) : '');
    const [qrImages, setQrImages] = useState<Record<number, string>>({});
    const [editing, setEditing] = useState<Record<number, string>>({});
    const [busyId, setBusyId] = useState<number | null>(null);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const selectedMenu = useMemo(() => menus.find(menu => String(menu.id) === selectedMenuId), [menus, selectedMenuId]);

    useEffect(() => {
        let cancelled = false;
        if (!selectedMenu) { setQrImages({}); return () => { cancelled = true; }; }
        Promise.all(tables.map(async table => {
            const url = new URL(selectedMenu.url, window.location.origin);
            url.searchParams.set('table', table.token);
            const dataUrl = await QRCode.toDataURL(url.toString(), { width: 720, margin: 3, errorCorrectionLevel: 'H' });
            return [table.id, dataUrl] as const;
        })).then(entries => { if (!cancelled) setQrImages(Object.fromEntries(entries)); })
            .catch(() => { if (!cancelled) setQrImages({}); });
        return () => { cancelled = true; };
    }, [selectedMenu, tables]);

    const createTable = (event: FormEvent) => {
        event.preventDefault(); setError(''); setNotice('');
        router.post(managementBase, { name: name.trim() }, {
            preserveScroll: true,
            onError: errors => setError(Object.values(errors).flat().join(' ')),
            onSuccess: () => setName(''),
        });
    };

    const updateTableValues = (table: RestaurantTable, nextName: string, isActive: boolean) => {
        setBusyId(table.id); setError(''); setNotice('');
        router.put(`${managementBase.replace(/\/$/, '')}/${table.id}`, { name: nextName.trim(), is_active: isActive }, {
            preserveScroll: true,
            onError: errors => setError(Object.values(errors).flat().join(' ')),
            onSuccess: () => { setEditing(current => { const copy = { ...current }; delete copy[table.id]; return copy; }); setNotice('تم حفظ تغييرات الترابيزة.'); },
            onFinish: () => setBusyId(null),
        });
    };

    return <main dir="rtl" className="min-h-screen bg-slate-50 px-4 py-8 text-slate-950 sm:px-8">
        <Head title="ترابيزات وQR" />
        <div className="mx-auto max-w-6xl">
            <header className="mb-7 flex flex-wrap items-center justify-between gap-4 rounded-3xl bg-slate-950 p-6 text-white shadow-xl sm:p-8">
                <div><p className="text-xs font-black tracking-widest text-amber-300">NEXORA QR · TABLES</p><h1 className="mt-2 text-3xl font-black">ترابيزات المطعم وQR</h1><p className="mt-2 text-sm text-slate-300">كل ترابيزة لها رمز مستقل، والطلب بيتربط بها من السيرفر مش من اسم يكتبه العميل.</p></div>
                <div className="flex gap-2"><Link href="/orders" className="rounded-xl border border-white/20 px-4 py-3 text-sm font-bold">الطلبات</Link><Link href="/dashboard" className="rounded-xl border border-white/20 px-4 py-3 text-sm font-bold">لوحة التحكم</Link></div>
            </header>
            {notice && <p role="status" className="mb-4 rounded-xl bg-emerald-50 p-3 text-sm font-bold text-emerald-800">{notice}</p>}
            {error && <p role="alert" className="mb-4 rounded-xl bg-rose-50 p-3 text-sm font-bold text-rose-800">{error}</p>}
            <section className="mb-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(280px,.8fr)]">
                <form onSubmit={createTable} className="rounded-2xl border bg-white p-5 shadow-sm">
                    <h2 className="text-lg font-black">إضافة ترابيزة</h2>
                    <p className="mt-1 text-sm text-slate-500">استخدم اسمًا أو رقمًا فريدًا داخل المطعم، زي ترابيزة 01 أو الصالة الخارجية.</p>
                    <label className="mt-4 block text-sm font-bold">اسم الترابيزة<input value={name} onChange={event => setName(event.target.value)} maxLength={80} required placeholder="مثال: ترابيزة 01" className="mt-2 w-full rounded-xl border px-3 py-3 font-normal" /></label>
                    <button type="submit" disabled={!name.trim()} className="mt-4 w-full rounded-xl bg-slate-950 px-4 py-3 font-black text-white disabled:opacity-40">إنشاء الترابيزة والـQR</button>
                </form>
                <div className="rounded-2xl border bg-white p-5 shadow-sm">
                    <h2 className="text-lg font-black">المنيو المستخدمة في QR</h2>
                    {menus.length ? <><p className="mt-1 text-sm text-slate-500">اختار منيو منشورة، وبعدها حمّل QR لكل ترابيزة. تغيير المنيو لا يغيّر رمز الترابيزة.</p><label className="mt-4 block text-sm font-bold">المنيو<select value={selectedMenuId} onChange={event => setSelectedMenuId(event.target.value)} className="mt-2 w-full rounded-xl border bg-white px-3 py-3 font-normal">{menus.map(menu => <option key={menu.id} value={menu.id}>{menu.name}</option>)}</select></label></> : <p className="mt-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">لا توجد منيو منشورة بعد. انشر منيو أولًا علشان تقدر تولّد QR للترابيزات.</p>}
                </div>
            </section>
            <section className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                {tables.map(table => {
                    const url = selectedMenu && typeof window !== 'undefined' ? (() => { const value = new URL(selectedMenu.url, window.location.origin); value.searchParams.set('table', table.token); return value.toString(); })() : '';
                    return <article key={table.id} className="rounded-2xl border bg-white p-5 shadow-sm">
                        <div className="flex items-start justify-between gap-3"><div className="min-w-0 flex-1"><p className="text-[10px] font-black tracking-widest text-slate-400">TABLE {String(table.id).padStart(3, '0')}</p><input aria-label={`اسم الترابيزة ${table.name}`} value={editing[table.id] ?? table.name} onChange={event => setEditing(current => ({ ...current, [table.id]: event.target.value }))} maxLength={80} className="mt-2 w-full rounded-lg border border-transparent px-2 py-1 text-lg font-black focus:border-slate-300"/><span className={`mt-2 inline-flex rounded-full px-3 py-1 text-xs font-bold ${table.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>{table.is_active ? 'نشطة' : 'متوقفة'}</span></div>
                            {qrImages[table.id] && <img src={qrImages[table.id]} alt={`QR ${table.name}`} className={`w-24 rounded-xl border p-1 ${table.is_active ? '' : 'opacity-40'}`} />}
                        </div>
                        <div className="mt-4 flex flex-wrap gap-2"><button type="button" disabled={busyId === table.id || !(editing[table.id] ?? table.name).trim()} onClick={() => updateTableValues(table, editing[table.id] ?? table.name, table.is_active)} className="rounded-lg border px-3 py-2 text-xs font-bold disabled:opacity-40">حفظ الاسم</button><button type="button" disabled={busyId === table.id} onClick={() => updateTableValues(table, editing[table.id] ?? table.name, !table.is_active)} className={`rounded-lg px-3 py-2 text-xs font-bold text-white disabled:opacity-40 ${table.is_active ? 'bg-rose-700' : 'bg-emerald-700'}`}>{table.is_active ? 'إيقاف QR' : 'تفعيل QR'}</button>{qrImages[table.id] && <a href={qrImages[table.id]} download={`table-${table.id}-qr.png`} className="rounded-lg bg-slate-950 px-3 py-2 text-xs font-bold text-white">تحميل QR للطباعة</a>}</div>
                        {url && <button type="button" onClick={() => navigator.clipboard.writeText(url)} className="mt-3 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600">نسخ رابط QR</button>}
                        <p className="mt-3 break-all text-[10px] leading-5 text-slate-400">لا تشارك الرابط خارج الترابيزة؛ هو اللي بيحدد سياق طلب الـDine-in.</p>
                    </article>;
                })}
                {!tables.length && <div className="rounded-2xl border border-dashed bg-white p-10 text-center text-sm text-slate-500 md:col-span-2 xl:col-span-3">لسه مفيش ترابيزات. أضف أول ترابيزة لتوليد رمزها.</div>}
            </section>
        </div>
    </main>;
}
