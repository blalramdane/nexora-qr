import { Head } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Props = {
    business: { name: string; city: string; area?: string | null; category: string };
    status: 'invited' | 'requested';
    expiresAt: string;
    endpoint: string;
};

const choices = [
    { value: 'customer_preview', title: 'تجربة العميل', description: 'اتصفح المنيو وشوف السلة وتأكيد الطلب التجريبي.' },
    { value: 'owner_dashboard', title: 'لوحة صاحب المطعم', description: 'راجع إدارة الأصناف والأسعار والطلبات في Sandbox منفصل.' },
    { value: 'custom_preview', title: 'معاينة على طلبك', description: 'حدد شكل وتجهيزات معينة تناسب نشاطك.' },
] as const;

const features = [
    ['digital_menu', 'منيو رقمي'], ['qr_code', 'QR للمكان'], ['order_dashboard', 'إدارة الطلبات'],
    ['whatsapp', 'إشعارات واتساب'], ['delivery', 'التوصيل'], ['product_editing', 'تعديل الأصناف والأسعار'], ['photos', 'صور وهوية بصرية'], ['other', 'متطلبات أخرى'],
] as const;

export default function Request({ business, status, expiresAt, endpoint }: Props) {
    const [mode, setMode] = useState<string>('customer_preview');
    const [style, setStyle] = useState('modern');
    const [note, setNote] = useState('');
    const [selectedFeatures, setSelectedFeatures] = useState<string[]>(['digital_menu', 'qr_code', 'order_dashboard']);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        setBusy(true); setError(''); setSuccess('');
        try {
            const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
            const response = await fetch(endpoint, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) },
                body: JSON.stringify({ mode, style, note, features: selectedFeatures }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || Object.values(result.errors ?? {}).flat().join(' ') || 'تعذر تسجيل الطلب.');
            setSuccess(result.message || 'تم تسجيل الطلب.');
        } catch (cause) {
            setError(cause instanceof Error ? cause.message : 'حصل خطأ أثناء تسجيل الطلب.');
        } finally { setBusy(false); }
    };

    const submitted = status === 'requested' || Boolean(success);

    return <main dir="rtl" className="min-h-screen bg-[#f4f2ec] text-slate-950">
        <Head title={`اختيار تجربة NEXORA — ${business.name}`}>
            <meta name="robots" content="noindex,nofollow" />
            <meta name="description" content="اختار تجربة NEXORA QR المناسبة لنشاطك واطلب معاينة مخصصة." />
        </Head>
        <header className="relative overflow-hidden bg-[#111814] text-white">
            <div className="pointer-events-none absolute -left-20 -top-20 h-80 w-80 rounded-full bg-emerald-300/15 blur-3xl" />
            <div className="relative mx-auto max-w-5xl px-4 py-7 sm:px-7">
                <div className="flex items-center gap-3"><div className="grid h-12 w-12 place-items-center rounded-2xl border border-emerald-200/30 bg-emerald-200/10 text-xl font-black text-emerald-100">N</div><div><p className="text-xs font-black tracking-[.24em] text-emerald-200">NEXORA QR</p><p className="mt-1 text-xs text-white/60">اختيار تجربة خاصة بنشاطك</p></div></div>
                <div className="grid gap-6 py-12 md:grid-cols-[1.2fr_.8fr] md:items-end"><div><p className="text-sm font-bold text-emerald-200">{[business.city, business.area, business.category].filter(Boolean).join(' · ')}</p><h1 className="mt-3 text-4xl font-black leading-tight sm:text-6xl">خلّينا نوريك اللي يناسب <span className="text-emerald-200">{business.name}</span></h1><p className="mt-5 max-w-2xl text-base leading-8 text-white/65">اختار بنفسك التجربة اللي تحب تشوفها، أو اطلب معاينة بتفاصيلك. الطلب هيوصل لفريق NEXORA للمراجعة، ومش هنبعت أي رسالة أو ننشر حاجة من غير موافقتك.</p></div><div className="rounded-3xl border border-white/10 bg-white/[.06] p-5"><p className="text-xs font-black tracking-widest text-white/50">PRIVATE DEMO REQUEST</p><p className="mt-3 text-lg font-black">طلبك مش التزام بالتعاقد</p><p className="mt-2 text-sm leading-7 text-white/60">أي أصناف أو أسعار تجريبية هتفضل منفصلة عن الطلبات الفعلية والحسابات.</p><p className="mt-4 border-t border-white/10 pt-4 text-xs text-white/45">صلاحية الرابط حتى {new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium' }).format(new Date(expiresAt))}</p></div></div>
            </div>
        </header>

        <div className="mx-auto max-w-5xl px-4 py-8 sm:px-7 sm:py-10">
            {(error || success) && <p role={error ? 'alert' : 'status'} className={`mb-5 rounded-2xl border p-4 text-sm leading-7 ${error ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-900'}`}>{error || success}</p>}
            {submitted ? <section className="rounded-3xl border border-emerald-200 bg-white p-7 sm:p-10"><div className="grid h-14 w-14 place-items-center rounded-2xl bg-emerald-100 text-2xl text-emerald-900">✓</div><h2 className="mt-5 text-2xl font-black">طلبك وصل لفريق NEXORA</h2><p className="mt-3 max-w-2xl text-sm leading-8 text-slate-600">تم ربط اختيارك بسجل المطعم علشان الفريق يراجع التفاصيل ويجهز معاينة مناسبة. إرسال الطلب لا يرسل رسائل نيابة عنك ولا ينشر منيو أو يفعّل طلبات حقيقية.</p></section> : <form onSubmit={submit} className="space-y-6">
                <section className="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7"><div><p className="text-xs font-black tracking-widest text-emerald-800">01 / EXPERIENCE</p><h2 className="mt-2 text-2xl font-black">تحب تشوف إيه؟</h2><p className="mt-2 text-sm text-slate-500">اختار تجربة واحدة، ونجهز الخطوة التالية على أساسها.</p></div><div className="mt-5 grid gap-3 md:grid-cols-3">{choices.map(choice => <label key={choice.value} className={`cursor-pointer rounded-2xl border p-4 transition ${mode === choice.value ? 'border-emerald-700 bg-emerald-50 ring-1 ring-emerald-700' : 'border-slate-200 hover:border-slate-400'}`}><input className="sr-only" type="radio" name="mode" value={choice.value} checked={mode === choice.value} onChange={() => setMode(choice.value)} /><span className="flex items-start justify-between gap-2"><span className="font-black">{choice.title}</span><span className={`grid h-5 w-5 shrink-0 place-items-center rounded-full border ${mode === choice.value ? 'border-emerald-800 bg-emerald-800 text-xs text-white' : 'border-slate-300'}`}>{mode === choice.value ? '✓' : ''}</span></span><span className="mt-3 block text-sm leading-6 text-slate-600">{choice.description}</span></label>)}</div></section>

                <section className="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7"><p className="text-xs font-black tracking-widest text-emerald-800">02 / STYLE</p><h2 className="mt-2 text-xl font-black">الشكل العام</h2><div className="mt-4 grid gap-3 sm:grid-cols-4">{[['modern','Modern'],['playful','مبهج'],['luxury','Premium'],['classic','كلاسيك']].map(([value, label]) => <button key={value} type="button" onClick={() => setStyle(value)} className={`rounded-xl border px-4 py-3 text-sm font-bold ${style === value ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white'}`}>{label}</button>)}</div></section>

                <section className="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7"><p className="text-xs font-black tracking-widest text-emerald-800">03 / NEEDS</p><h2 className="mt-2 text-xl font-black">إيه اللي يهمك في التجربة؟</h2><div className="mt-4 grid gap-3 sm:grid-cols-2 md:grid-cols-4">{features.map(([value, label]) => <label key={value} className="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm font-bold"><input type="checkbox" checked={selectedFeatures.includes(value)} onChange={event => setSelectedFeatures(current => event.target.checked ? [...current, value] : current.filter(item => item !== value))} className="h-4 w-4 accent-emerald-800" />{label}</label>)}</div><label className="mt-5 block text-sm font-bold">ملاحظات أو تفاصيل خاصة<textarea value={note} onChange={event => setNote(event.target.value)} rows={4} maxLength={1500} placeholder="مثلاً: ألوان الهوية، أصناف مهمة، طريقة استقبال الطلبات..." className="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 font-normal leading-7 outline-none focus:border-emerald-700" /></label></section>

                <div className="flex flex-col gap-3 rounded-3xl bg-[#17221b] p-5 text-white sm:flex-row sm:items-center sm:justify-between sm:p-7"><div><h2 className="text-lg font-black">جاهز تطلب المعاينة؟</h2><p className="mt-2 max-w-xl text-sm leading-6 text-white/60">هنسجل اختياراتك فقط. فريق NEXORA هيراجع الطلب قبل إنشاء رابط Demo خاص.</p></div><button type="submit" disabled={busy} className="rounded-xl bg-emerald-200 px-6 py-4 font-black text-emerald-950 disabled:opacity-40">{busy ? 'جاري تسجيل الطلب...' : 'إرسال طلب المعاينة'}</button></div>
            </form>}
        </div>
        <footer className="pb-8 text-center text-xs text-slate-500">NEXORA QR · لا يتم نشر المنيو أو إنشاء طلبات فعلية من هذه الصفحة.</footer>
    </main>;
}
