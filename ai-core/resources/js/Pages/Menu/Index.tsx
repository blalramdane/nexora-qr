import { Head, Link, router } from '@inertiajs/react';

type Template = { id: number; key: string; name: string; description?: string | null; is_premium: boolean };
type Menu = { id: number; name: string; slug: string; is_published: boolean; template?: Template | null };

export default function MenuIndex({ menus, templates }: { menus: Menu[]; templates: Template[] }) {
    const create = (templateKey: string) => {
        const name = window.prompt('اسم المنيو');
        if (!name) return;
        router.post('/menus', { name, template_key: templateKey });
    };
    return <div dir="rtl" className="min-h-screen bg-slate-50 text-slate-900"><Head title="Menus" /><div className="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-sm font-bold text-amber-600">NEXORA QR</p><h1 className="mt-1 text-3xl font-black">منيوهاتك</h1><p className="mt-2 text-sm text-slate-500">اختار الـTemplate وبعدها نبني المحتوى عليه.</p></div><Link href="/dashboard" className="text-sm font-bold underline">Dashboard</Link></div>
        <div className="mt-8 grid gap-4 md:grid-cols-3">{templates.map(t => <button key={t.id} onClick={() => create(t.key)} className="group rounded-3xl border border-slate-200 bg-white p-5 text-right shadow-sm transition hover:-translate-y-1 hover:shadow-xl"><div className="aspect-[16/9] rounded-2xl bg-gradient-to-br from-slate-900 via-slate-700 to-amber-500 p-4 text-white"><div className="flex h-full flex-col justify-between"><span className="text-xs opacity-60">TEMPLATE</span><strong className="text-2xl">{t.name}</strong></div></div><h2 className="mt-4 font-extrabold">{t.name}{t.is_premium ? ' · Premium' : ''}</h2><p className="mt-1 text-sm text-slate-500">{t.description}</p></button>)}</div>
        <section className="mt-10"><h2 className="text-xl font-black">المنيوهات الحالية</h2><div className="mt-4 grid gap-3">{menus.map(m => <Link key={m.id} href={'/menus/' + m.id} className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 hover:border-slate-400"><div><strong>{m.name}</strong><p className="mt-1 text-xs text-slate-500">{m.template?.name || 'بدون Template'} · {m.is_published ? 'Published' : 'Draft'}</p></div><span className="text-sm font-bold">فتح الـBuilder →</span></Link>)}</div></section>
    </div></div>;
}
