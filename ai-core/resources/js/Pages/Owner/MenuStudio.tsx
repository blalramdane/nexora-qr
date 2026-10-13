import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PwaInstallPrompt from '../../Components/PwaInstallPrompt';

type Menu = {
    id: number;
    restaurant_id: number;
    name: string;
    slug: string;
    is_published: boolean;
    updated_at?: string | null;
    template?: { key: string; name: string } | null;
    categories_count: number;
    products_count: number;
    public_url: string;
};

type Restaurant = {
    id: number;
    name: string;
    slug: string;
    phone?: string | null;
    is_active: boolean;
    role: string;
    settings_url: string;
    orders_url: string;
    tables_url: string;
    menus: Menu[];
};

type Template = { id: number; key: string; name: string; description?: string | null };
type FormErrors = Record<string, string>;

const moneyDate = (value?: string | null) => value
    ? new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : '—';

export default function OwnerMenuStudio({ restaurants, templates, platformOwner }: {
    restaurants: Restaurant[];
    templates: Template[];
    platformOwner: boolean;
}) {
    const flash = (usePage().props as unknown as { flash?: { success?: string; warning?: string; error?: string } }).flash;
    const [newMenuFor, setNewMenuFor] = useState<number | null>(null);
    const [menuName, setMenuName] = useState('');
    const [templateKey, setTemplateKey] = useState(templates[0]?.key ?? '');
    const [showRestaurantForm, setShowRestaurantForm] = useState(false);
    const [showTemplateForm, setShowTemplateForm] = useState(false);
    const [restaurantDraft, setRestaurantDraft] = useState({ name: '', phone: '', whatsapp_phone: '', address: '', owner_name: '', owner_email: '' });
    const [restaurantErrors, setRestaurantErrors] = useState<FormErrors>({});
    const [templateDraft, setTemplateDraft] = useState({ name: '', description: '', layout: 'fast-food', primary: '#111827', accent: '#D6A85F', background: '#FFFFFF', foreground: '#111827', radius: 'xl' });
    const [templateErrors, setTemplateErrors] = useState<FormErrors>({});
    const [menuSearch, setMenuSearch] = useState('');
    const [menuStatusFilter, setMenuStatusFilter] = useState<'all' | 'published' | 'draft'>('all');
    const totalMenus = restaurants.reduce((sum, restaurant) => sum + restaurant.menus.length, 0);
    const publishedMenus = restaurants.reduce((sum, restaurant) => sum + restaurant.menus.filter(menu => menu.is_published).length, 0);
    const totalProducts = restaurants.reduce((sum, restaurant) => sum + restaurant.menus.reduce((count, menu) => count + menu.products_count, 0), 0);
    const searchQuery = menuSearch.trim().toLocaleLowerCase('ar-EG');
    const filteredRestaurants = restaurants.map(restaurant => {
        const restaurantMatches = `${restaurant.name} ${restaurant.slug} ${restaurant.phone ?? ''}`.toLocaleLowerCase('ar-EG').includes(searchQuery);
        const matchingMenus = restaurant.menus.filter(menu => {
            const matchesStatus = menuStatusFilter === 'all' || (menuStatusFilter === 'published' ? menu.is_published : !menu.is_published);
            const matchesSearch = !searchQuery || restaurantMatches || `${menu.name} ${menu.slug} ${menu.template?.name ?? ''}`.toLocaleLowerCase('ar-EG').includes(searchQuery);
            return matchesStatus && matchesSearch;
        });
        return { ...restaurant, menus: matchingMenus, restaurantMatches };
    }).filter(restaurant => {
        const matchesSearch = !searchQuery || restaurant.restaurantMatches || restaurant.menus.length > 0;
        return matchesSearch && (menuStatusFilter === 'all' || restaurant.menus.length > 0);
    });
    const filteredMenuCount = filteredRestaurants.reduce((sum, restaurant) => sum + restaurant.menus.length, 0);

    const startCreate = (restaurant: Restaurant) => {
        setNewMenuFor(restaurant.id);
        setMenuName(`منيو ${restaurant.name}`);
        setTemplateKey(templates[0]?.key ?? '');
    };

    const createMenu = (restaurant: Restaurant) => {
        if (!menuName.trim() || !templateKey) return;
        router.post(`/owner/restaurants/${restaurant.id}/menus`, {
            name: menuName.trim(),
            template_key: templateKey,
        }, {
            onSuccess: () => {
                setNewMenuFor(null);
                setMenuName('');
            },
        });
    };

    const createRestaurant = () => {
        setRestaurantErrors({});
        router.post('/owner/restaurants', restaurantDraft, {
            onSuccess: () => {
                setRestaurantDraft({ name: '', phone: '', whatsapp_phone: '', address: '', owner_name: '', owner_email: '' });
                setShowRestaurantForm(false);
            },
            onError: errors => setRestaurantErrors(errors as FormErrors),
        });
    };

    const createTemplate = () => {
        setTemplateErrors({});
        router.post('/owner/templates', templateDraft, {
            onSuccess: () => {
                setTemplateDraft({ name: '', description: '', layout: 'fast-food', primary: '#111827', accent: '#D6A85F', background: '#FFFFFF', foreground: '#111827', radius: 'xl' });
                setShowTemplateForm(false);
            },
            onError: errors => setTemplateErrors(errors as FormErrors),
        });
    };

    return (
        <main dir="rtl" className="min-h-screen bg-slate-50 text-slate-950">
            <Head title="إدارة المنيوهات | NEXORA Owner Studio" />
            <header className="relative overflow-hidden bg-slate-950 text-white">
                <div aria-hidden="true" className="pointer-events-none absolute inset-0 opacity-70">
                    <div className="absolute -right-24 -top-32 h-80 w-80 rounded-full bg-emerald-400/10 blur-3xl" />
                    <div className="absolute -left-24 bottom-0 h-72 w-72 rounded-full bg-indigo-500/10 blur-3xl" />
                    <div className="absolute inset-0 nx-studio-grid opacity-30" />
                </div>
                <div className="mx-auto flex max-w-7xl flex-col justify-between gap-5 px-4 py-7 sm:flex-row sm:items-end sm:px-6 lg:px-8">
                    <div className="relative z-10">
                        <Link href="/dashboard" className="text-xs font-bold text-slate-400 transition hover:text-white">← العودة للوحة التحكم</Link>
                        <p className="mt-5 inline-flex items-center gap-2 rounded-full border border-emerald-300/20 bg-emerald-300/10 px-3 py-1.5 text-[10px] font-black tracking-[.22em] text-emerald-300"><span className="h-1.5 w-1.5 rounded-full bg-emerald-300" /> NEXORA · MENU CONTROL CENTER</p>
                        <h1 className="mt-3 text-3xl font-black tracking-tight sm:text-5xl">إدارة كل المنيوهات</h1>
                        <p className="mt-3 max-w-2xl text-sm leading-7 text-slate-300">مكان واحد لتعديل أسماء الأصناف، الأسعار، الصور، الاختيارات والأقسام، ثم معاينة المنيو ونشرها — من غير ما بيانات مطعم تدخل على مطعم تاني.</p>
                    </div>
                    <div className="relative z-10 flex flex-wrap gap-2">
                        <span className="rounded-xl border border-white/15 bg-white/5 px-4 py-3 text-sm font-bold">{platformOwner ? 'صلاحية مالك المنصة' : 'صلاحية المطاعم المرتبطة بالحساب'}</span>
                        {platformOwner && <button type="button" onClick={() => { setShowRestaurantForm(value => !value); setShowTemplateForm(false); }} className="rounded-xl bg-emerald-400 px-4 py-3 text-sm font-black text-slate-950 transition hover:bg-emerald-300">+ إضافة مطعم</button>}
                        {platformOwner && <button type="button" onClick={() => { setShowTemplateForm(value => !value); setShowRestaurantForm(false); }} className="rounded-xl border border-violet-300/50 px-4 py-3 text-sm font-black text-white transition hover:bg-violet-400/10">+ قالب تصميم</button>}
                        <Link href="/owner/account" className="rounded-xl border border-white/20 px-4 py-3 text-sm font-bold text-white transition hover:bg-white/10">حسابي وأمان الحساب</Link>
                    </div>
                </div>
                <div className="mx-auto grid max-w-7xl grid-cols-2 gap-3 px-4 pb-7 sm:grid-cols-4 sm:px-6 lg:px-8">
                    {[
                        { label: 'المطاعم المتاحة', value: restaurants.length, hint: 'RESTAURANTS', icon: '▤', tone: 'text-emerald-300' },
                        { label: 'كل المنيوهات', value: totalMenus, hint: 'MENUS', icon: '▦', tone: 'text-sky-300' },
                        { label: 'منيو منشور', value: publishedMenus, hint: 'PUBLISHED', icon: '↗', tone: 'text-amber-300' },
                        { label: 'إجمالي الأصناف', value: totalProducts, hint: 'PRODUCTS', icon: '◇', tone: 'text-violet-300' },
                    ].map(stat => <div key={stat.label} className="relative overflow-hidden rounded-2xl border border-white/10 bg-white/[.06] p-4 backdrop-blur sm:p-5">
                        <div className="flex items-center justify-between"><p className="text-[10px] font-black tracking-[.18em] text-slate-400">{stat.hint}</p><span className={`grid h-9 w-9 place-items-center rounded-xl bg-white/[.07] text-lg ${stat.tone}`}>{stat.icon}</span></div>
                        <p className="mt-3 text-3xl font-black tabular-nums">{stat.value.toLocaleString('ar-EG')}</p>
                        <p className="mt-1 text-sm text-slate-300">{stat.label}</p>
                    </div>)}
                </div>
            </header>

            <PwaInstallPrompt />
            <div className="mx-auto max-w-7xl px-4 py-7 sm:px-6 lg:px-8">
                {flash?.success && <p role="status" className="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold leading-6 text-emerald-900">{flash.success}</p>}
                {flash?.warning && <p role="alert" className="mb-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold leading-6 text-amber-900">{flash.warning}</p>}
                {flash?.error && <p role="alert" className="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold leading-6 text-rose-900">{flash.error}</p>}
                <section className="mb-6 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p className="text-[10px] font-black tracking-[.2em] text-emerald-700">WORKSPACE OVERVIEW</p>
                            <h2 className="mt-1 text-xl font-black">مساحة إدارة المطاعم</h2>
                            <p className="mt-1 text-xs leading-6 text-slate-500">ابحث بالاسم أو الرابط، واعرض المنشور أو المسودات فقط.</p>
                        </div>
                        <div className="flex min-w-0 flex-col gap-2 sm:flex-row lg:w-[min(100%,620px)]">
                            <label className="relative min-w-0 flex-1">
                                <span className="sr-only">البحث في المطاعم والمنيوهات</span>
                                <span aria-hidden="true" className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">⌕</span>
                                <input type="search" value={menuSearch} onChange={event => setMenuSearch(event.target.value)} placeholder="ابحث عن مطعم أو منيو أو قالب…" className="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 pr-10 pl-3 text-sm outline-none transition focus:border-slate-400 focus:bg-white focus:ring-4 focus:ring-slate-100" />
                            </label>
                            <div className="flex shrink-0 gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1" role="group" aria-label="فلترة حالة المنيو">
                                {([{ key: 'all', label: 'الكل' }, { key: 'published', label: 'منشور' }, { key: 'draft', label: 'مسودة' }] as const).map(filter => <button key={filter.key} type="button" onClick={() => setMenuStatusFilter(filter.key)} aria-pressed={menuStatusFilter === filter.key} className={`rounded-lg px-3 py-2 text-xs font-black transition ${menuStatusFilter === filter.key ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-500 hover:bg-white hover:text-slate-900'}`}>{filter.label}</button>)}
                            </div>
                        </div>
                    </div>
                    <div className="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-xs">
                        <span className="text-slate-500">نتائج المنيوهات</span>
                        <span className="rounded-full bg-slate-100 px-3 py-1 font-black text-slate-800">{filteredMenuCount.toLocaleString('ar-EG')} / {totalMenus.toLocaleString('ar-EG')}</span>
                    </div>
                </section>
                {platformOwner && showRestaurantForm && <section className="mb-6 rounded-3xl border border-emerald-200 bg-white p-5 shadow-sm sm:p-7">
                    <div className="mb-5"><p className="text-xs font-black tracking-widest text-emerald-700">RESTAURANT ONBOARDING</p><h2 className="mt-1 text-2xl font-black">إضافة مطعم وحساب صاحبه</h2><p className="mt-1 text-sm text-slate-500">هننشئ المطعم والفرع ونربط الحساب بصلاحية المالك. كلمة السر مش هتظهر هنا؛ صاحب المطعم هيعيّنها من رابط آمن على بريده.</p></div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="text-sm font-bold">اسم المطعم<input value={restaurantDraft.name} onChange={event => setRestaurantDraft(value => ({ ...value, name: event.target.value }))} maxLength={180} required className="mt-1.5 w-full rounded-xl border px-3 py-3 font-normal" placeholder="اسم المطعم أو الكافيه" />{restaurantErrors.name && <span className="mt-1 block text-xs text-rose-600">{restaurantErrors.name}</span>}</label>
                        <label className="text-sm font-bold">اسم صاحب المطعم<input value={restaurantDraft.owner_name} onChange={event => setRestaurantDraft(value => ({ ...value, owner_name: event.target.value }))} maxLength={120} required autoComplete="name" className="mt-1.5 w-full rounded-xl border px-3 py-3 font-normal" placeholder="الاسم المسجل للحساب" />{restaurantErrors.owner_name && <span className="mt-1 block text-xs text-rose-600">{restaurantErrors.owner_name}</span>}</label>
                        <label className="text-sm font-bold">بريد تسجيل الدخول<input type="email" value={restaurantDraft.owner_email} onChange={event => setRestaurantDraft(value => ({ ...value, owner_email: event.target.value }))} maxLength={255} required autoComplete="email" dir="ltr" className="mt-1.5 w-full rounded-xl border px-3 py-3 text-left font-normal" placeholder="owner@example.com" />{restaurantErrors.owner_email && <span className="mt-1 block text-xs text-rose-600">{restaurantErrors.owner_email}</span>}</label>
                        <label className="text-sm font-bold">رقم الهاتف<input value={restaurantDraft.phone} onChange={event => setRestaurantDraft(value => ({ ...value, phone: event.target.value }))} maxLength={32} className="mt-1.5 w-full rounded-xl border px-3 py-3 font-normal" placeholder="اختياري" />{restaurantErrors.phone && <span className="mt-1 block text-xs text-rose-600">{restaurantErrors.phone}</span>}</label>
                        <label className="text-sm font-bold">واتساب المطعم<input value={restaurantDraft.whatsapp_phone} onChange={event => setRestaurantDraft(value => ({ ...value, whatsapp_phone: event.target.value }))} maxLength={32} className="mt-1.5 w-full rounded-xl border px-3 py-3 font-normal" placeholder="رقم استقبال الطلبات" />{restaurantErrors.whatsapp_phone && <span className="mt-1 block text-xs text-rose-600">{restaurantErrors.whatsapp_phone}</span>}</label>
                        <label className="text-sm font-bold">عنوان الفرع الرئيسي<input value={restaurantDraft.address} onChange={event => setRestaurantDraft(value => ({ ...value, address: event.target.value }))} maxLength={500} className="mt-1.5 w-full rounded-xl border px-3 py-3 font-normal" placeholder="المدينة والشارع (اختياري)" />{restaurantErrors.address && <span className="mt-1 block text-xs text-rose-600">{restaurantErrors.address}</span>}</label>
                    </div>
                    <div className="mt-4 flex flex-wrap gap-2"><button type="button" onClick={createRestaurant} disabled={!restaurantDraft.name.trim() || !restaurantDraft.owner_name.trim() || !restaurantDraft.owner_email.trim()} className="rounded-xl bg-emerald-700 px-5 py-3 text-sm font-black text-white disabled:opacity-40">إنشاء المطعم وإرسال دعوة الدخول</button><button type="button" onClick={() => setShowRestaurantForm(false)} className="rounded-xl border px-5 py-3 text-sm font-bold">إلغاء</button></div>
                </section>}

                {platformOwner && showTemplateForm && <section className="mb-6 rounded-3xl border border-violet-200 bg-white p-5 shadow-sm sm:p-7">
                    <div className="mb-5"><p className="text-xs font-black tracking-widest text-violet-700">GENERIC TEMPLATE STUDIO</p><h2 className="mt-1 text-2xl font-black">إنشاء قالب تصميم قابل لإعادة الاستخدام</h2><p className="mt-1 text-sm text-slate-500">القالب بياخد ألوانه وتخطيطه من إعداداته، وبيستخدم بيانات كل مطعم عند العرض.</p></div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <label className="text-sm font-bold">اسم القالب<input value={templateDraft.name} onChange={event => setTemplateDraft(value => ({ ...value, name: event.target.value }))} maxLength={120} required className="mt-1.5 w-full rounded-xl border px-3 py-3 font-normal" placeholder="مثال: Modern Bistro" />{templateErrors.name && <span className="mt-1 block text-xs text-rose-600">{templateErrors.name}</span>}</label>
                        <label className="text-sm font-bold">نوع التصميم<select value={templateDraft.layout} onChange={event => setTemplateDraft(value => ({ ...value, layout: event.target.value }))} className="mt-1.5 w-full rounded-xl border bg-white px-3 py-3 font-normal"><option value="fast-food">وجبات سريعة</option><option value="cafe">كافيه ومشروبات</option><option value="fine-dining">مطعم راقٍ</option></select></label>
                        <label className="text-sm font-bold">استدارة العناصر<select value={templateDraft.radius} onChange={event => setTemplateDraft(value => ({ ...value, radius: event.target.value }))} className="mt-1.5 w-full rounded-xl border bg-white px-3 py-3 font-normal">{['sm', 'md', 'lg', 'xl', '2xl'].map(radius => <option key={radius} value={radius}>{radius}</option>)}</select></label>
                        {([{ key: 'primary', label: 'اللون الأساسي' }, { key: 'accent', label: 'لون التمييز' }, { key: 'background', label: 'الخلفية' }, { key: 'foreground', label: 'النص' }] as const).map(color => <label key={color.key} className="flex items-center justify-between gap-3 rounded-xl border p-3 text-sm font-bold">{color.label}<input type="color" value={templateDraft[color.key]} onChange={event => setTemplateDraft(value => ({ ...value, [color.key]: event.target.value.toUpperCase() }))} className="h-10 w-14 cursor-pointer rounded-lg border-0 bg-transparent" /></label>)}
                        <label className="text-sm font-bold sm:col-span-2 lg:col-span-3">وصف القالب<textarea value={templateDraft.description} onChange={event => setTemplateDraft(value => ({ ...value, description: event.target.value }))} maxLength={1000} rows={2} className="mt-1.5 w-full rounded-xl border px-3 py-3 font-normal" placeholder="وصف مختصر لشخصية التصميم" />{templateErrors.description && <span className="mt-1 block text-xs text-rose-600">{templateErrors.description}</span>}</label>
                    </div>
                    {Object.keys(templateErrors).some(key => !['name', 'description'].includes(key)) && <p className="mt-2 text-xs text-rose-600">{Object.entries(templateErrors).filter(([key]) => !['name', 'description'].includes(key)).map(([, message]) => message).join(' ')}</p>}
                    <div className="mt-4 flex flex-wrap gap-2"><button type="button" onClick={createTemplate} disabled={!templateDraft.name.trim()} className="rounded-xl bg-violet-700 px-5 py-3 text-sm font-black text-white disabled:opacity-40">حفظ القالب وتفعيله</button><button type="button" onClick={() => setShowTemplateForm(false)} className="rounded-xl border px-5 py-3 text-sm font-bold">إلغاء</button></div>
                </section>}

                {restaurants.length === 0 ? <section className="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center">
                    <span className="text-4xl">☰</span>
                    <h2 className="mt-4 text-xl font-black">مفيش مطاعم مرتبطة بصلاحيات حسابك لسه</h2>
                    <p className="mt-2 text-sm text-slate-500">أضف مطعمًا من Owner Studio أو اطلب من مالك المنصة ربط حسابك بالمطعم المطلوب.</p>
                </section> : filteredRestaurants.length === 0 ? <section className="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
                    <span className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-slate-100 text-2xl text-slate-500">⌕</span>
                    <h2 className="mt-4 text-xl font-black">مفيش نتائج مطابقة</h2>
                    <p className="mt-2 text-sm text-slate-500">جرّب كلمة بحث مختلفة أو غيّر فلتر حالة المنيو.</p>
                    <button type="button" onClick={() => { setMenuSearch(''); setMenuStatusFilter('all'); }} className="mt-4 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-black text-white">عرض كل المنيوهات</button>
                </section> : <div className="space-y-6">
                    {filteredRestaurants.map(restaurant => <section key={restaurant.id} className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
                        <div className="flex flex-col justify-between gap-4 border-b border-slate-100 bg-gradient-to-l from-slate-50 via-white to-white p-5 sm:flex-row sm:items-center sm:p-6">
                            <div className="flex min-w-0 items-start gap-3">
                                <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-slate-950 text-xl text-white shadow-lg shadow-slate-950/10">▤</div>
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="text-xl font-black">{restaurant.name}</h2>
                                        <span className={`rounded-full px-2.5 py-1 text-[11px] font-bold ${restaurant.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>{restaurant.is_active ? 'نشط' : 'متوقف'}</span>
                                        <span className="rounded-full bg-indigo-50 px-2.5 py-1 text-[11px] font-bold text-indigo-700">{restaurant.role === 'platform_owner' ? 'مالك المنصة' : restaurant.role === 'owner' ? 'مالك المطعم' : 'مدير'}</span>
                                    </div>
                                    <p className="mt-1 text-xs text-slate-500">/{restaurant.slug}{restaurant.phone ? ` · ${restaurant.phone}` : ''}</p>
                                    <p className="mt-2 text-xs font-bold text-slate-400">{restaurant.menus.length.toLocaleString('ar-EG')} منيو في النتائج <span className="mx-1 text-slate-200">•</span> {restaurant.menus.filter(menu => menu.is_published).length.toLocaleString('ar-EG')} منشور</p>
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Link href={restaurant.orders_url} className="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-black text-white transition hover:bg-indigo-700">صندوق الطلبات</Link>
                                <Link href={restaurant.tables_url} className="inline-flex items-center justify-center rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm font-black text-cyan-900 transition hover:bg-cyan-100">الترابيزات وQR</Link>
                                <Link href={restaurant.settings_url} className="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black transition hover:bg-slate-50">إعدادات الطلبات</Link>
                                <button type="button" onClick={() => newMenuFor === restaurant.id ? setNewMenuFor(null) : startCreate(restaurant)} className="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-3 text-sm font-black text-white transition hover:bg-slate-800">
                                    <span className="text-lg">＋</span> إنشاء منيو جديدة
                                </button>
                            </div>
                        </div>

                        {newMenuFor === restaurant.id && <form onSubmit={event => { event.preventDefault(); createMenu(restaurant); }} className="grid gap-3 border-b border-emerald-100 bg-emerald-50/60 p-5 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                            <label className="block text-sm font-bold">اسم المنيو<input value={menuName} onChange={event => setMenuName(event.target.value)} maxLength={120} required className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 font-normal outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" placeholder="مثال: منيو الحواوشي" /></label>
                            <label className="block text-sm font-bold">قالب التصميم<select value={templateKey} onChange={event => setTemplateKey(event.target.value)} required className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 font-normal outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"><option value="" disabled>اختار قالب</option>{templates.map(template => <option key={template.id} value={template.key}>{template.name}</option>)}</select></label>
                            <div className="flex gap-2"><button disabled={!menuName.trim() || !templateKey} className="flex-1 rounded-xl bg-emerald-700 px-5 py-3 text-sm font-black text-white disabled:opacity-50">إنشاء وفتح المحرر</button><button type="button" onClick={() => setNewMenuFor(null)} className="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold">إلغاء</button></div>
                        </form>}

                        <div className="p-5 sm:p-6">
                            {restaurant.menus.length === 0 ? <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-7 text-center">
                                <p className="font-bold">لسه مفيش منيو للمطعم ده</p>
                                <p className="mt-1 text-sm text-slate-500">أنشئ المنيو، وبعدها هتتحكم في الأقسام والأصناف والصور والأسعار والاختيارات.</p>
                            </div> : <div className="grid gap-3 xl:grid-cols-2">
                                {restaurant.menus.map(menu => <article key={menu.id} className="rounded-2xl border border-slate-200 p-4 transition hover:border-slate-300 hover:shadow-sm">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <h3 className="truncate text-lg font-black">{menu.name}</h3>
                                            <p className="mt-1 text-xs text-slate-500">{menu.template?.name ?? 'بدون قالب'} · آخر تحديث {moneyDate(menu.updated_at)}</p>
                                        </div>
                                        <span className={`shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold ${menu.is_published ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>{menu.is_published ? 'منشور' : 'مسودة'}</span>
                                    </div>
                                    <div className="mt-4 grid grid-cols-2 gap-2">
                                        <div className="rounded-xl bg-slate-50 px-3 py-2"><p className="text-[11px] text-slate-500">الأقسام</p><p className="mt-1 font-black">{menu.categories_count.toLocaleString('ar-EG')}</p></div>
                                        <div className="rounded-xl bg-slate-50 px-3 py-2"><p className="text-[11px] text-slate-500">الأصناف</p><p className="mt-1 font-black">{menu.products_count.toLocaleString('ar-EG')}</p></div>
                                    </div>
                                    <div className="mt-4 flex flex-wrap gap-2">
                                        <Link href={`/owner/menus/${menu.id}`} className="inline-flex flex-1 items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-black text-white transition hover:bg-slate-800">إدارة كل تفاصيل المنيو <span className="mr-2">←</span></Link>
                                        {menu.is_published && <a href={menu.public_url} target="_blank" rel="noreferrer" className="inline-flex items-center justify-center rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold">معاينة</a>}
                                    </div>
                                </article>)}
                            </div>}
                        </div>
                    </section>)}
                </div>}
            </div>
        </main>
    );
}
