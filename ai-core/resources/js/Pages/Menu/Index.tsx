import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

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

const templateMeta: Record<string, {
    label: string;
    tone: string;
    accent: string;
    icon: string;
    eyebrow: string;
    description: string;
}> = {
    'fast-food': {
        label: 'Fast Food',
        tone: 'from-[#070707] via-[#19130b] to-[#9a651e]',
        accent: '#e7b55d',
        icon: '🍔',
        eyebrow: 'BOLD / SOCIAL / HIGH-CONVERSION',
        description: 'صور كبيرة، حركة خفيفة، وCTA واضح للأماكن اللي عايزة الناس تطلب بسرعة.',
    },
    cafe: {
        label: 'Café',
        tone: 'from-[#24180f] via-[#76522f] to-[#d8b17a]',
        accent: '#f0c58d',
        icon: '☕',
        eyebrow: 'WARM / EDITORIAL / COZY',
        description: 'ستايل دافي ومرن للكافيهات، المخابز، والحلويات مع تركيز على الصور والـdiscovery.',
    },
    'fine-dining': {
        label: 'Fine Dining',
        tone: 'from-[#050505] via-[#151515] to-[#5f5031]',
        accent: '#e8c989',
        icon: '✦',
        eyebrow: 'EDITORIAL / LUXURY / MINIMAL',
        description: 'تجربة هادئة وفخمة تخلي المنتج هو البطل بدون زحمة بصرية.',
    },
};

function TemplatePreview({ template }: { template: Template }) {
    const meta = templateMeta[template.key] ?? {
        label: template.name,
        tone: 'from-slate-950 to-slate-700',
        accent: '#f59e0b',
        icon: '◫',
        eyebrow: 'NEXORA TEMPLATE',
        description: template.description ?? 'قالب جاهز للتخصيص.',
    };

    return (
        <div className={`nx-shimmer relative aspect-[1.35] overflow-hidden bg-gradient-to-br ${meta.tone} p-4 text-white sm:p-5`}>
            <div className="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-white/10 blur-3xl nx-glow" />
            <div className="absolute -left-16 bottom-0 h-40 w-40 rounded-full blur-3xl" style={{ backgroundColor: meta.accent + '20' }} />

            <div className="relative z-10 flex h-full flex-col justify-between">
                <div className="flex items-center justify-between">
                    <span className="rounded-full border border-white/15 bg-black/15 px-2.5 py-1 text-[8px] font-bold tracking-[.18em] text-white/70">
                        {template.is_premium ? 'PREMIUM' : 'READY'}
                    </span>
                    <span className="grid h-9 w-9 place-items-center rounded-full border border-white/15 bg-white/10 text-lg backdrop-blur">
                        {meta.icon}
                    </span>
                </div>

                <div className="mx-auto w-[78%] rounded-[1.2rem] border border-white/10 bg-black/25 p-3 shadow-2xl backdrop-blur-md">
                    <div className="flex items-center gap-2 border-b border-white/10 pb-2">
                        <span className="h-5 w-5 rounded-full" style={{ backgroundColor: meta.accent }} />
                        <span className="h-1.5 w-16 rounded-full bg-white/30" />
                        <span className="mr-auto h-1.5 w-7 rounded-full bg-white/15" />
                    </div>
                    <div className="mt-3 grid grid-cols-3 gap-2">
                        <div className="col-span-2 h-20 rounded-lg bg-white/10" />
                        <div className="space-y-2">
                            <div className="h-9 rounded-lg bg-white/10" />
                            <div className="h-9 rounded-lg bg-white/10" />
                        </div>
                    </div>
                    <div className="mt-2 flex gap-1.5">
                        <span className="h-1.5 w-10 rounded-full" style={{ backgroundColor: meta.accent + '99' }} />
                        <span className="h-1.5 w-16 rounded-full bg-white/15" />
                    </div>
                </div>

                <div>
                    <p className="text-[8px] font-bold tracking-[.22em] text-white/45">{meta.eyebrow}</p>
                    <p className="mt-1 text-lg font-black sm:text-xl">{template.name}</p>
                </div>
            </div>
        </div>
    );
}

export default function MenuIndex({ menus, templates }: { menus: Menu[]; templates: Template[] }) {
    const [search, setSearch] = useState('');

    const filteredMenus = useMemo(
        () => menus.filter((menu) => menu.name.toLocaleLowerCase().includes(search.toLocaleLowerCase())),
        [menus, search],
    );

    const create = (templateKey: string) => {
        const name = window.prompt('اسم المنيو');
        if (!name?.trim()) return;
        router.post('/menus', { name: name.trim(), template_key: templateKey });
    };

    return (
        <main dir="rtl" className="min-h-screen overflow-x-hidden text-slate-950">
            <Head title="المنيوهات" />

            <header className="sticky top-0 z-40 border-b border-slate-200/70 bg-white/80 backdrop-blur-2xl">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
                    <div className="flex items-center gap-3">
                        <Link
                            href="/dashboard"
                            aria-label="العودة للوحة التحكم"
                            className="grid h-11 w-11 place-items-center rounded-2xl bg-slate-950 text-sm font-black text-white shadow-lg shadow-slate-950/10 transition hover:-translate-y-0.5 hover:shadow-xl"
                        >
                            N
                        </Link>
                        <div>
                            <p className="text-[9px] font-black tracking-[.3em] text-slate-400">NEXORA QR</p>
                            <h1 className="text-sm font-black sm:text-base">Menu Studio</h1>
                        </div>
                    </div>

                    <Link
                        href="/dashboard"
                        className="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-black text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        لوحة التحكم
                    </Link>
                </div>
            </header>

            <div className="mx-auto max-w-7xl px-4 py-5 sm:px-6 sm:py-8 lg:px-8 lg:py-10">
                <section className="nx-studio-grid relative overflow-hidden rounded-[2rem] bg-slate-950 p-6 text-white shadow-2xl sm:p-9">
                    <div className="absolute -right-24 -top-28 h-72 w-72 rounded-full bg-amber-400/10 blur-3xl nx-glow" />
                    <div className="absolute -left-24 bottom-[-10rem] h-80 w-80 rounded-full bg-indigo-400/10 blur-3xl nx-float" />

                    <div className="relative grid gap-8 lg:grid-cols-[1fr_auto] lg:items-end">
                        <div className="max-w-3xl">
                            <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[.05] px-3 py-1.5 text-[10px] font-black tracking-[.18em] text-white/65">
                                <span className="h-1.5 w-1.5 rounded-full bg-emerald-400 shadow-[0_0_12px_rgba(52,211,153,.9)]" />
                                LIVE MENU EXPERIENCE
                            </div>
                            <h2 className="mt-5 max-w-2xl text-3xl font-black leading-[1.12] tracking-tight sm:text-5xl">
                                المنيو مش PDF.
                                <br />
                                <span className="text-white/45">دي أول تجربة للبراند.</span>
                            </h2>
                            <p className="mt-5 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base">
                                اختار قالب يليق بالنشاط، وبعدها الـBuilder يحول المنتجات والصور والأسعار إلى تجربة موبايل جاهزة للـQR.
                            </p>
                        </div>

                        <div className="grid grid-cols-2 gap-2 sm:flex sm:items-stretch">
                            <div className="rounded-2xl border border-white/10 bg-white/[.06] px-5 py-4 backdrop-blur">
                                <p className="text-[10px] font-bold text-white/40">MENUS</p>
                                <p className="mt-1 text-3xl font-black">{menus.length}</p>
                            </div>
                            <div className="rounded-2xl border border-white/10 bg-white/[.06] px-5 py-4 backdrop-blur">
                                <p className="text-[10px] font-bold text-white/40">TEMPLATES</p>
                                <p className="mt-1 text-3xl font-black">{templates.length}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="mt-8">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-[10px] font-black tracking-[.22em] text-slate-400">DESIGN SYSTEM</p>
                            <h2 className="mt-1 text-2xl font-black tracking-tight sm:text-3xl">اختار الشخصية قبل ما تبدأ.</h2>
                        </div>
                        <p className="max-w-md text-xs leading-6 text-slate-500">
                            كل Template له composition وحركة وhierarchy مختلفة — مش مجرد تغيير ألوان.
                        </p>
                    </div>

                    <div className="mt-5 grid gap-5 lg:grid-cols-3">
                        {templates.map((template) => {
                            const meta = templateMeta[template.key] ?? {
                                label: template.name,
                                tone: 'from-slate-950 to-slate-700',
                                accent: '#f59e0b',
                                icon: '◫',
                                eyebrow: 'NEXORA TEMPLATE',
                                description: template.description ?? 'قالب جاهز للتخصيص.',
                            };

                            return (
                                <button
                                    key={template.id}
                                    type="button"
                                    onClick={() => create(template.key)}
                                    className="group overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white text-right shadow-sm transition duration-300 hover:-translate-y-1.5 hover:border-slate-300 hover:shadow-2xl focus:outline-none focus:ring-4 focus:ring-slate-950/10"
                                >
                                    <TemplatePreview template={template} />

                                    <div className="p-5 sm:p-6">
                                        <div className="flex items-start justify-between gap-4">
                                            <div>
                                                <p className="text-[9px] font-black tracking-[.2em] text-slate-400">{meta.label}</p>
                                                <h3 className="mt-1 text-xl font-black">{template.name}</h3>
                                            </div>
                                            <span
                                                className="grid h-10 w-10 shrink-0 place-items-center rounded-full border text-sm font-black transition duration-300 group-hover:translate-x-[-3px]"
                                                style={{ borderColor: meta.accent + '45', color: meta.accent }}
                                            >
                                                ←
                                            </span>
                                        </div>
                                        <p className="mt-3 text-xs leading-6 text-slate-500">{template.description ?? meta.description}</p>
                                        <div className="mt-5 flex items-center gap-2 text-[10px] font-black text-slate-400">
                                            <span className="rounded-full bg-slate-100 px-2.5 py-1">Mobile-first</span>
                                            <span className="rounded-full bg-slate-100 px-2.5 py-1">QR ready</span>
                                            {template.is_premium && <span className="rounded-full bg-amber-50 px-2.5 py-1 text-amber-700">Premium</span>}
                                        </div>
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                </section>

                <section className="mt-12">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-[10px] font-black tracking-[.22em] text-slate-400">YOUR MENUS</p>
                            <h2 className="mt-1 text-2xl font-black tracking-tight">منيوهاتك</h2>
                        </div>

                        <div className="relative w-full sm:w-80">
                            <span className="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-slate-400">⌕</span>
                            <input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="ابحث عن منيو..."
                                aria-label="البحث في المنيوهات"
                                className="w-full rounded-2xl border border-slate-200 bg-white py-3.5 pe-10 ps-4 text-sm font-semibold outline-none shadow-sm transition placeholder:text-slate-400 focus:border-slate-400 focus:ring-4 focus:ring-slate-950/5"
                            />
                        </div>
                    </div>

                    <div className="mt-5 space-y-3">
                        {filteredMenus.length === 0 ? (
                            <div className="rounded-[1.75rem] border border-dashed border-slate-300 bg-white p-12 text-center shadow-sm">
                                <div className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-slate-100 text-xl">☰</div>
                                <p className="mt-4 font-black">{menus.length ? 'مفيش نتائج مطابقة.' : 'لسه مفيش منيوهات.'}</p>
                                <p className="mt-1 text-xs leading-6 text-slate-500">اختار Template من فوق وابدأ أول Menu.</p>
                            </div>
                        ) : (
                            filteredMenus.map((menu) => {
                                const meta = templateMeta[menu.template?.key ?? ''] ?? {
                                    label: 'Menu',
                                    tone: 'from-slate-950 to-slate-700',
                                    accent: '#f59e0b',
                                    icon: '☰',
                                    eyebrow: 'MENU',
                                    description: '',
                                };

                                return (
                                    <Link
                                        key={menu.id}
                                        href={`/menus/${menu.id}`}
                                        className="group flex flex-col gap-4 rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-lg sm:flex-row sm:items-center sm:justify-between sm:p-5"
                                    >
                                        <div className="flex min-w-0 items-center gap-4">
                                            <div className={`relative grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-2xl bg-gradient-to-br ${meta.tone} text-xl text-white shadow-inner`}>
                                                <span className="relative z-10">{meta.icon}</span>
                                                <span className="absolute inset-0 bg-white/5" />
                                            </div>
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h3 className="truncate font-black">{menu.name}</h3>
                                                    <span className={`rounded-full px-2 py-0.5 text-[9px] font-black ${menu.is_published ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>
                                                        {menu.is_published ? 'منشور' : 'Draft'}
                                                    </span>
                                                </div>
                                                <p className="mt-1 truncate text-[11px] font-medium text-slate-500">
                                                    {menu.template?.name ?? 'بدون Template'} · /{menu.slug}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="flex items-center justify-between gap-5 sm:justify-end">
                                            <span className="text-[10px] font-black tracking-[.15em] text-slate-300 transition group-hover:text-slate-500">OPEN BUILDER</span>
                                            <span className="grid h-10 w-10 place-items-center rounded-full border border-slate-200 text-sm font-black text-slate-300 transition group-hover:border-slate-950 group-hover:bg-slate-950 group-hover:text-white">
                                                ←
                                            </span>
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
