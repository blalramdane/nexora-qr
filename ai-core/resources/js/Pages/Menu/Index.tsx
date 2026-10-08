import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

type Template = {
    id: number;
    key: string;
    name: string;
    description?: string | null;
    is_premium: boolean;
};

type Menu = {
    id: number;
    name: string;
    slug: string;
    is_published: boolean;
    template?: Template | null;
};

const templateMeta: Record<string, { label: string; tone: string; icon: string }> = {
    'fast-food': { label: 'Fast Food', tone: 'from-slate-950 via-slate-900 to-amber-500', icon: '🍔' },
    cafe: { label: 'Café', tone: 'from-stone-950 via-amber-950 to-amber-500', icon: '☕' },
    'fine-dining': { label: 'Fine Dining', tone: 'from-zinc-950 via-slate-900 to-yellow-700', icon: '✦' },
};

export default function MenuIndex({ menus, templates }: { menus: Menu[]; templates: Template[] }) {
    const [search, setSearch] = useState('');

    const create = (templateKey: string) => {
        const name = window.prompt('اسم المنيو');
        if (!name?.trim()) return;
        router.post('/menus', { name: name.trim(), template_key: templateKey });
    };

    const filteredMenus = menus.filter((menu) =>
        menu.name.toLowerCase().includes(search.toLowerCase()),
    );

    return (
        <main dir="rtl" className="min-h-screen bg-[#f6f7f9] text-slate-950">
            <Head title="المنيوهات" />

            <header className="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <div className="flex items-center gap-3">
                        <Link href="/dashboard" className="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-950 text-sm font-black text-white">
                            N
                        </Link>
                        <div>
                            <p className="text-[10px] font-black tracking-[0.28em] text-slate-400">NEXORA QR</p>
                            <h1 className="font-black">إدارة المنيوهات</h1>
                        </div>
                    </div>
                    <Link href="/dashboard" className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">
                        لوحة التحكم
                    </Link>
                </div>
            </header>

            <div className="mx-auto max-w-7xl px-4 py-7 sm:px-6 lg:px-8 lg:py-9">
                <section className="overflow-hidden rounded-[2rem] bg-slate-950 p-6 text-white shadow-xl sm:p-8">
                    <div className="flex flex-col justify-between gap-7 lg:flex-row lg:items-end">
                        <div className="max-w-2xl">
                            <span className="inline-flex rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-bold text-slate-300">
                                MENU STUDIO
                            </span>
                            <h2 className="mt-5 text-3xl font-black tracking-tight sm:text-4xl">ابني تجربة المنيو بتاعتك.</h2>
                            <p className="mt-3 text-sm leading-7 text-slate-300 sm:text-base">
                                اختار ستايل مناسب لنشاطك، وبعدها ادخل الـBuilder وأضف الأقسام والمنتجات والصور والـVariants والـModifiers.
                            </p>
                        </div>
                        <div className="rounded-2xl border border-white/10 bg-white/5 px-5 py-4">
                            <p className="text-xs text-slate-400">إجمالي المنيوهات</p>
                            <p className="mt-1 text-3xl font-black">{menus.length}</p>
                        </div>
                    </div>
                </section>

                <section className="mt-7">
                    <div className="flex items-end justify-between gap-4">
                        <div>
                            <p className="text-xs font-black tracking-[0.18em] text-slate-400">START FROM TEMPLATE</p>
                            <h2 className="mt-1 text-2xl font-black">اختار الـTemplate</h2>
                        </div>
                    </div>

                    <div className="mt-5 grid gap-4 md:grid-cols-3">
                        {templates.map((template) => {
                            const meta = templateMeta[template.key] ?? {
                                label: template.name,
                                tone: 'from-slate-950 to-slate-700',
                                icon: '◫',
                            };

                            return (
                                <button
                                    key={template.id}
                                    onClick={() => create(template.key)}
                                    className="group overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white text-right shadow-sm transition duration-200 hover:-translate-y-1 hover:border-slate-300 hover:shadow-xl"
                                >
                                    <div className={`relative aspect-[16/9] overflow-hidden bg-gradient-to-br ${meta.tone} p-5 text-white`}>
                                        <div className="absolute -left-8 -top-8 h-28 w-28 rounded-full bg-white/10 blur-2xl" />
                                        <div className="relative flex h-full flex-col justify-between">
                                            <div className="flex items-center justify-between">
                                                <span className="rounded-full border border-white/15 bg-white/10 px-2.5 py-1 text-[10px] font-bold tracking-wider">
                                                    {template.is_premium ? 'PREMIUM' : 'READY'}
                                                </span>
                                                <span className="text-2xl">{meta.icon}</span>
                                            </div>
                                            <div>
                                                <p className="text-xs font-bold text-white/60">{meta.label}</p>
                                                <p className="mt-1 text-2xl font-black">{template.name}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="p-5">
                                        <div className="flex items-center justify-between gap-4">
                                            <h3 className="font-black">{template.name}</h3>
                                            <span className="text-sm font-black text-slate-400 transition group-hover:text-slate-950">ابدأ ←</span>
                                        </div>
                                        <p className="mt-2 text-sm leading-6 text-slate-500">{template.description}</p>
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                </section>

                <section className="mt-10">
                    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                        <div>
                            <p className="text-xs font-black tracking-[0.18em] text-slate-400">YOUR MENUS</p>
                            <h2 className="mt-1 text-2xl font-black">منيوهاتك</h2>
                        </div>
                        <div className="w-full sm:w-72">
                            <input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="ابحث عن منيو..."
                                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium outline-none transition placeholder:text-slate-400 focus:border-slate-400 focus:ring-4 focus:ring-slate-950/5"
                            />
                        </div>
                    </div>

                    <div className="mt-5 grid gap-3">
                        {filteredMenus.length === 0 ? (
                            <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-xl">☰</div>
                                <p className="mt-4 font-black">{menus.length ? 'مفيش نتائج مطابقة.' : 'لسه مفيش منيوهات.'}</p>
                                <p className="mt-1 text-sm text-slate-500">اختار Template من فوق وابدأ أول Menu.</p>
                            </div>
                        ) : (
                            filteredMenus.map((menu) => {
                                const meta = templateMeta[menu.template?.key ?? ''] ?? { label: 'Menu', tone: 'from-slate-950 to-slate-700', icon: '☰' };
                                return (
                                    <Link
                                        key={menu.id}
                                        href={`/menus/${menu.id}`}
                                        className="group flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow-md sm:flex-row sm:items-center sm:justify-between sm:p-5"
                                    >
                                        <div className="flex min-w-0 items-center gap-4">
                                            <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br ${meta.tone} text-lg text-white`}>
                                                {meta.icon}
                                            </div>
                                            <div className="min-w-0">
                                                <h3 className="truncate font-black">{menu.name}</h3>
                                                <p className="mt-1 truncate text-xs font-medium text-slate-500">
                                                    {menu.template?.name ?? 'بدون Template'} · /{menu.slug}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center justify-between gap-4 sm:justify-end">
                                            <span className={`rounded-full px-3 py-1.5 text-[11px] font-black ${menu.is_published ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>
                                                {menu.is_published ? 'منشور' : 'Draft'}
                                            </span>
                                            <span className="font-black text-slate-300 transition group-hover:text-slate-950">←</span>
                                        </div>
                                    </Link>
                                );
                            })
                        )}
                    </div>
                </section>
            </div>
        </main>
    );
}
