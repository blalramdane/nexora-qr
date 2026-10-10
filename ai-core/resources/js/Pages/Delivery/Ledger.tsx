import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

type Rider = { id: number; name: string };
type Entry = { id: number; type: string; amount: number; reference: string | null; description: string; occurred_at: string | null; created_by: string | null };
type Summary = { opening_balance: number; advances: number; expenses: number; repayments: number; collections: number; handovers: number; balance_due: number };
const money = (amount: number) => `${amount.toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ج.م`;
const labels: Record<string, string> = { opening_balance: 'رصيد افتتاحي', advance: 'عهدة للمندوب', expense: 'مصروف تشغيل', repayment: 'رد عهدة / سداد', adjustment_debit: 'زيادة مستحقة على المندوب', adjustment_credit: 'تخفيض مستحقات المندوب', collection: 'تحصيل من العملاء', handover: 'تسليم للمطعم' };
const types = [
    { value: 'opening_balance', label: 'رصيد افتتاحي (مبلغ على المندوب)' },
    { value: 'advance', label: 'عهدة نقدية سلّمها المطعم للمندوب' },
    { value: 'expense', label: 'مصروف تشغيل دفعه المندوب نيابة عن المطعم' },
    { value: 'repayment', label: 'رد عهدة أو سداد من المندوب' },
    { value: 'adjustment_debit', label: 'تسوية: زيادة المبلغ المستحق على المندوب' },
    { value: 'adjustment_credit', label: 'تسوية: تخفيض المبلغ المستحق على المندوب' },
];

export default function DeliveryLedger({ riders, selectedRiderId, entries, summary }: { riders: Rider[]; selectedRiderId: number | null; entries: Entry[]; summary: Summary }) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const [riderId, setRiderId] = useState(String(selectedRiderId ?? ''));
    const [type, setType] = useState('expense');
    const [amount, setAmount] = useState('');
    const [description, setDescription] = useState('');
    const [reference, setReference] = useState('');
    const [occurredAt, setOccurredAt] = useState(new Date().toISOString().slice(0, 16));
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);
    const submit = () => {
        if (!riderId || !amount || Number(amount) <= 0 || description.trim().length < 3) { setError('اختار المندوب، واكتب مبلغًا صحيحًا ووصفًا للحركة.'); return; }
        setSaving(true); setError('');
        router.post('/delivery-ledger', { delivery_rider_id: Number(riderId), type, amount: Number(amount), description, reference: reference || null, occurred_at: occurredAt || null }, { preserveScroll: true, onError: errors => setError(Object.values(errors).flat().join(' ')), onSuccess: () => { setAmount(''); setDescription(''); setReference(''); }, onFinish: () => setSaving(false) });
    };
    const stats = [
        { label: 'الرصيد المستحق على المندوب', value: summary.balance_due, strong: true },
        { label: 'إجمالي التحصيل المسجل', value: summary.collections },
        { label: 'إجمالي التسليم للمطعم', value: summary.handovers },
        { label: 'العهد المصروفة', value: summary.advances },
        { label: 'مصروفات التشغيل', value: summary.expenses },
        { label: 'رد العهد / السداد', value: summary.repayments },
    ];
    return <main dir="rtl" className="min-h-screen bg-slate-50 px-4 py-6 text-slate-950 sm:px-8"><Head title="كشف حساب المندوب"/><div className="mx-auto max-w-7xl">
        <header className="flex flex-wrap items-center justify-between gap-4 rounded-3xl bg-slate-950 p-6 text-white sm:p-8"><div><p className="text-xs font-black tracking-[.22em] text-emerald-300">NEXORA / RIDER LEDGER</p><h1 className="mt-2 text-3xl font-black">كشف حساب المندوب</h1><p className="mt-2 text-sm text-slate-300">كل حركة مالية في سجل واحد مع فصل التحصيل والتسليم والعهد والمصروفات.</p></div><div className="flex flex-wrap gap-2"><Link href="/delivery-shift-closings" className="rounded-xl border border-white/20 px-4 py-3 text-sm font-bold">إقفال الوردية</Link><Link href="/delivery-settlements" className="rounded-xl border border-white/20 px-4 py-3 text-sm font-bold">تسويات التحصيل</Link><Link href="/delivery-management" className="rounded-xl bg-white px-4 py-3 text-sm font-bold text-slate-950">إدارة الدليفري</Link></div></header>
        {flash?.success && <p role="status" className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-900">{flash.success}</p>}
        <section className="mt-5 rounded-2xl border border-slate-200 bg-white p-5"><label className="mb-2 block text-sm font-bold">كشف حساب المندوب</label><select value={riderId} onChange={event => { setRiderId(event.target.value); router.get('/delivery-ledger', event.target.value ? { rider_id: Number(event.target.value) } : {}, { preserveState: true, preserveScroll: true }); }} className="w-full max-w-xl rounded-xl border border-slate-200 bg-white px-3 py-3"><option value="">اختار المندوب</option>{riders.map(r => <option key={r.id} value={r.id}>{r.name}</option>)}</select>
            <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">{stats.map(stat => <article key={stat.label} className={`rounded-xl border p-4 ${stat.strong ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-slate-50'}`}><p className="text-sm font-bold text-slate-500">{stat.label}</p><p className="mt-2 text-xl font-black">{money(stat.value)}</p></article>)}</div><p className="mt-3 text-xs leading-6 text-slate-500">الرصيد الموجب يعني مبلغًا مستحقًا على المندوب للمطعم. العهد والمصروفات تخصم من هذا الرصيد، والتحصيل يزيده، والتسليم للمطعم يقلله. راجع المستندات قبل تسجيل أي حركة.</p>
        </section>
        <section className="mt-5 grid gap-5 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]"><div className="rounded-2xl border border-slate-200 bg-white p-5"><h2 className="text-xl font-black">تسجيل حركة مالية</h2><p className="mt-1 text-sm text-slate-500">الرصيد الافتتاحي يتسجل مرة واحدة فقط لكل مندوب.</p><div className="mt-4 space-y-4"><div><label className="mb-2 block text-sm font-bold">نوع الحركة</label><select value={type} onChange={event => setType(event.target.value)} className="w-full rounded-xl border border-slate-200 px-3 py-3">{types.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}</select></div><div><label className="mb-2 block text-sm font-bold">المبلغ (ج.م)</label><input type="number" min="0.01" step="0.01" value={amount} onChange={event => setAmount(event.target.value)} className="w-full rounded-xl border border-slate-200 px-3 py-3" placeholder="0.00"/></div><div><label className="mb-2 block text-sm font-bold">وصف الحركة / سبب المصروف</label><textarea value={description} onChange={event => setDescription(event.target.value)} maxLength={2000} rows={3} className="w-full rounded-xl border border-slate-200 px-3 py-3" placeholder="مثال: بنزين توصيلات يوم الخميس"/></div><div><label className="mb-2 block text-sm font-bold">رقم إيصال أو مرجع (اختياري)</label><input value={reference} onChange={event => setReference(event.target.value)} maxLength={100} className="w-full rounded-xl border border-slate-200 px-3 py-3" placeholder="رقم الإيصال"/></div><div><label className="mb-2 block text-sm font-bold">تاريخ الحركة</label><input type="datetime-local" value={occurredAt} onChange={event => setOccurredAt(event.target.value)} className="w-full rounded-xl border border-slate-200 px-3 py-3"/></div>{error && <p role="alert" className="rounded-xl bg-rose-50 p-3 text-sm font-bold text-rose-800">{error}</p>}<button onClick={submit} disabled={saving || !riderId} className="w-full rounded-xl bg-emerald-600 px-4 py-3 font-black text-white disabled:opacity-50">{saving ? 'جارٍ الحفظ…' : 'حفظ الحركة'}</button></div></div>
            <div className="rounded-2xl border border-slate-200 bg-white p-5"><div className="flex items-center justify-between gap-3"><div><h2 className="text-xl font-black">سجل الحركات</h2><p className="mt-1 text-sm text-slate-500">أحدث 150 حركة للمندوب المختار.</p></div><span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{entries.length} حركة</span></div><div className="mt-4 space-y-3">{entries.map(entry => <article key={entry.id} className="rounded-xl border border-slate-200 p-4"><div className="flex flex-wrap items-start justify-between gap-3"><div><span className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-black">{labels[entry.type] ?? entry.type}</span><p className="mt-2 font-bold">{entry.description}</p><p className="mt-1 text-xs text-slate-500">{entry.occurred_at ? new Date(entry.occurred_at).toLocaleString('ar-EG') : 'بدون تاريخ'}{entry.created_by ? ` · سجّلها ${entry.created_by}` : ''}{entry.reference ? ` · مرجع ${entry.reference}` : ''}</p></div><strong className="whitespace-nowrap">{money(entry.amount)}</strong></div></article>)}{!entries.length && <p className="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-500">لا توجد حركات لهذا المندوب حتى الآن.</p>}</div></div></section>
    </div></main>;
}
