import { Form, Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

type Restaurant = {
    id: number;
    name: string;
    slug: string;
    branches_count: number;
};

type Stats = {
    menus: number;
    published_menus: number;
    products: number;
};

type Menu = {
    id: number;
    name: string;
    slug: string;
    is_published: boolean;
    updated_at?: string | null;
    template?: { key: string; name: string } | null;
};

function Icon({ children }: { children: ReactNode }) {
    return (
        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
            {children}
        </span>
    );
}

function StatCard({
    label,
    value,
    hint,
    icon,
}: {
    label: string;
    value: number | string;
    hint: string;
    icon: ReactNode;
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between gap-4">
                <Icon>{icon}</Icon>
                <span className="text-xs font-semibold text-slate-400">{hint}</span>
            </div>
            <p className="mt-5 text-sm font-medium text-slate-500">{label}</p>
            <p className="mt-1 text-3xl font-black tracking-tight text-slate-950">{value}</p>
        </div>
    );
}

export default function Dashboard({
    restaurant,
    stats,
    menus,
}: {
    restaurant: Restaurant;
    stats: Stats;
    menus: Menu[];
}) {
    return (
        <main dir="rtl" className="min-h-screen bg-[#f6f7f9] text-slate-950">
            <Head title="لوحة التحكم" />

            <header className="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <div className="flex items-center gap-3">
                        <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-950 text-sm font-black text-white">
                            N
                        </div>
                        <div>
                            <p className="text-[10px] font-black tracking-[0.28em] text-slate-400">NEXORA QR</p>
                            <p className="mt-0.5 font-bold text-slate-950">{restaurant.name}</p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Link
                            href="/menus"
                            className="hidden rounded-xl px-4 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-slate-100 sm:block"
                        >
                            المنيوهات
                        </Link>
                        <Link href="/orders" className="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-slate-800">الطلبات</Link>
                        <Link href="/delivery-management" className="hidden rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 md:block">الدليفري</Link>
                        <Link href="/settings/orders" className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700">إعدادات الطلبات</Link>
                        <Form action="/logout" method="post">
                            <button className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                                خروج
                            </button>
                        </Form>
                    </div>
                </div>
            </header>

            <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <section className="overflow-hidden rounded-[2rem] bg-slate-950 p-6 text-white shadow-xl sm:p-8">
                    <div className="flex flex-col justify-between gap-8 lg:flex-row lg:items-end">
                        <div className="max-w-2xl">
                            <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-bold text-slate-300">
                                <span className="h-1.5 w-1.5 rounded-full bg-emerald-400" />
                                جاهز للإدارة
                            </div>
                            <h1 className="mt-5 text-3xl font-black tracking-tight sm:text-4xl">
                                أهلاً بيك في نكسورا.
                            </h1>
                            <p className="mt-3 max-w-xl text-sm leading-7 text-slate-300 sm:text-base">
                                أنشئ منيو احترافي، اختار الـTemplate، حدّث منتجاتك وانشر QR Menu لعملائك من مكان واحد.
                            </p>
                        </div>

                        <Link
                            href="/menus"
                            className="inline-flex items-center justify-center rounded-2xl bg-white px-5 py-3.5 text-sm font-black text-slate-950 transition hover:bg-slate-100"
                        >
                            إدارة المنيوهات
                            <span className="mr-2">←</span>
                        </Link>
                    </div>
                </section>

                <section className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="إجمالي المنيوهات"
                        value={stats.menus}
                        hint="Menus"
                        icon={<span className="text-lg">☰</span>}
                    />
                    <StatCard
                        label="منيو منشور"
                        value={stats.published_menus}
                        hint="Published"
                        icon={<span className="text-lg text-emerald-600">✓</span>}
                    />
                    <StatCard
                        label="المنتجات"
                        value={stats.products}
                        hint="Products"
                        icon={<span className="text-lg">◆</span>}
                    />
                    <StatCard
                        label="الفروع"
                        value={restaurant.branches_count}
                        hint="Branches"
                        icon={<span className="text-lg">⌂</span>}
                    />
                </section>

                <div className="mt-5 grid gap-5 lg:grid-cols-[1.5fr_1fr]">
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-xs font-black tracking-wide text-slate-400">YOUR MENUS</p>
                                <h2 className="mt-1 text-xl font-black">آخر المنيوهات</h2>
                            </div>
                            <Link href="/menus" className="text-sm font-bold text-slate-600 hover:text-slate-950">
                                عرض الكل
                            </Link>
                        </div>

                        <div className="mt-5 space-y-3">
                            {menus.length === 0 ? (
                                <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                                    <p className="font-bold">لسه مفيش منيوهات.</p>
                                    <p className="mt-1 text-sm text-slate-500">ابدأ بأول Menu من الـTemplates الجاهزة.</p>
                                    <Link href="/menus" className="mt-4 inline-flex rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white">
                                        إنشاء أول منيو
                                    </Link>
                                </div>
                            ) : (
                                menus.map((menu) => (
                                    <Link
                                        key={menu.id}
                                        href={`/menus/${menu.id}`}
                                        className="group flex items-center justify-between gap-4 rounded-2xl border border-slate-100 p-4 transition hover:border-slate-200 hover:bg-slate-50"
                                    >
                                        <div className="flex min-w-0 items-center gap-3">
                                            <Icon><span>☰</span></Icon>
                                            <div className="min-w-0">
                                                <p className="truncate font-bold">{menu.name}</p>
                                                <p className="mt-1 truncate text-xs text-slate-500">
                                                    {menu.template?.name ?? 'بدون Template'}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-3">
                                            <span className={`rounded-full px-2.5 py-1 text-[11px] font-bold ${menu.is_published ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>
                                                {menu.is_published ? 'منشور' : 'Draft'}
                                            </span>
                                            <span className="text-slate-300 transition group-hover:text-slate-700">←</span>
                                        </div>
                                    </Link>
                                ))
                            )}
                        </div>
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <p className="text-xs font-black tracking-wide text-slate-400">QUICK ACTIONS</p>
                        <h2 className="mt-1 text-xl font-black">اختصارات سريعة</h2>

                        <div className="mt-5 space-y-3">
                            <Link href="/menus" className="flex items-center gap-3 rounded-2xl bg-slate-950 p-4 text-white transition hover:bg-slate-800">
                                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 text-lg">+</span>
                                <div>
                                    <p className="font-bold">إنشاء Menu جديد</p>
                                    <p className="mt-0.5 text-xs text-slate-400">اختار Template وابدأ البناء</p>
                                </div>
                            </Link>
                            <Link href="/menus" className="flex items-center gap-3 rounded-2xl border border-slate-200 p-4 transition hover:bg-slate-50">
                                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-lg">◫</span>
                                <div>
                                    <p className="font-bold">Templates</p>
                                    <p className="mt-0.5 text-xs text-slate-500">Fast Food · Café · Fine Dining</p>
                                </div>
                            </Link>
                            <Link href="/orders" className="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 transition hover:bg-emerald-100">
                                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-lg">🛎️</span>
                                <div><p className="font-bold">طلبات العملاء</p><p className="mt-0.5 text-xs text-slate-600">عرض الطلبات الجديدة وتحديث حالتها</p></div>
                            </Link>
                            <Link href="/coupons" className="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 transition hover:bg-amber-100"><span className="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-lg">％</span><div><p className="font-bold">الكوبونات والعروض</p><p className="mt-0.5 text-xs text-slate-600">أكواد الخصم وشروط الاستخدام</p></div></Link>
                            <Link href="/delivery-management" className="flex items-center gap-3 rounded-2xl border border-blue-200 bg-blue-50 p-4 transition hover:bg-blue-100"><span className="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-lg">🛵</span><div><p className="font-bold">إدارة الدليفري</p><p className="mt-0.5 text-xs text-slate-600">ضم المندوبين، قبول الدعوات، وتوزيع الطلبات</p></div></Link>
                            <Link href="/reports/financial" className="flex items-center gap-3 rounded-2xl border border-violet-200 bg-violet-50 p-4 transition hover:bg-violet-100"><span className="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-lg">📊</span><div><p className="font-bold">التقرير المالي اليومي</p><p className="mt-0.5 text-xs text-slate-600">المبيعات وطرق الدفع وتحصلات المندوبين والفروق</p></div></Link>
                            <Link href="/expenses" className="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 transition hover:bg-rose-100"><span className="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-lg">🧾</span><div><p className="font-bold">مصروفات المطعم</p><p className="mt-0.5 text-xs text-slate-600">الإيجار والمرافق والرواتب والصيانة والمستلزمات</p></div></Link>
                            <Link href="/purchases" className="flex items-center gap-3 rounded-2xl border border-cyan-200 bg-cyan-50 p-4 transition hover:bg-cyan-100"><span className="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-lg">📦</span><div><p className="font-bold">الموردون والمشتريات</p><p className="mt-0.5 text-xs text-slate-600">فواتير التوريد والمدفوعات والمديونية المتبقية</p></div></Link>
                            <Link href="/settings/orders" className="flex items-center gap-3 rounded-2xl border border-slate-200 p-4 transition hover:bg-slate-50"><span className="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-lg">⚙</span><div><p className="font-bold">إعدادات الطلبات</p><p className="mt-0.5 text-xs text-slate-500">واتساب المطعم، مصاريف التوصيل وطرق الاستلام</p></div></Link>
                            <div className="rounded-2xl bg-slate-50 p-4">
                                <p className="text-xs font-bold text-slate-400">Restaurant ID</p>
                                <p className="mt-1 font-black">{restaurant.id}</p>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    );
}
