import { useState } from 'react';
import { requestProductConfiguration } from './ProductConfigurator';

type Props = {
    menu: any;
    restaurantName: string;
    restaurantAddress?: string | null;
    logoPath?: string | null;
    restaurantPhone?: string | null;
    restaurantWhatsappPhone?: string | null;
    searchActive?: boolean;
    preview?: boolean;
};

const money = (value: string | number) => `${Number(value).toLocaleString('ar-EG', { maximumFractionDigits: 2 })} ج.م`;
const imageUrl = (path?: string | null) => path ? (path.startsWith('http') || path.startsWith('/') || path.startsWith('blob:') || path.startsWith('data:') ? path : `/storage/${path}`) : null;
const isLightColor = (hex: string) => {
    const value = hex.replace('#', '');
    if (!/^[0-9a-fA-F]{6}$/.test(value)) return false;
    const channels = [0, 2, 4].map(index => parseInt(value.slice(index, index + 2), 16) / 255).map(channel => channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4);
    return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722 > 0.52;
};
export default function FastFoodTemplate({ menu, restaurantName, restaurantAddress, logoPath, restaurantPhone, restaurantWhatsappPhone, searchActive = false, preview = false }: Props) {
    const theme = menu.theme ?? { primary: '#c9953c', accent: '#e7b55d', background: '#090807', foreground: '#f8efe0', radius: 'xl' };
    const categories = menu.categories ?? [];
    const allProducts = categories.flatMap((category: any) => category.products ?? []);
    const [activeCategoryId, setActiveCategoryId] = useState<number | null>(null);
    const [logoFailed, setLogoFailed] = useState(false);
    const activeCategory = categories.find((category: any) => category.id === activeCategoryId) ?? categories[0];
    const heroProduct = allProducts.find((product: any) => product.is_featured && product.is_available)
        ?? allProducts.find((product: any) => product.is_available)
        ?? allProducts[0];
    const logo = imageUrl(logoPath);
    const phone = (restaurantPhone ?? '').trim();
    const whatsapp = (restaurantWhatsappPhone ?? '').replace(/\D/g, '');
    const radius = ({ sm: '0.6rem', md: '0.8rem', lg: '1rem', xl: '1.25rem', '2xl': '1.75rem' } as Record<string, string>)[theme.radius] ?? '1.25rem';
    const lightSurface = isLightColor(theme.background);
    const surface = lightSurface ? '#ffffff' : '#151211';
    const muted = lightSurface ? '#647067' : 'rgba(255,255,255,.55)';
    const subtle = lightSurface ? 'rgba(23,37,27,.12)' : 'rgba(255,255,255,.09)';
    const headerStyle = { backgroundColor: theme.background, color: theme.foreground, borderColor: subtle };

    return <div dir="rtl" className="min-h-screen overflow-x-hidden" style={{ backgroundColor: theme.background, color: theme.foreground, fontFamily: 'Cairo, Tajawal, Tahoma, sans-serif' }}>
        <style>{`.nx-fast-card{transition:transform .24s ease,border-color .24s ease,box-shadow .24s ease}.nx-fast-card:hover{transform:translateY(-3px);box-shadow:0 18px 36px rgba(0,0,0,.22)}.nx-category-scroll{scrollbar-width:none}.nx-category-scroll::-webkit-scrollbar{display:none}@media(prefers-reduced-motion:reduce){.nx-fast-card{transition:none}.nx-fast-card:hover{transform:none}}`}</style>
        <header className="sticky top-0 z-40 border-b shadow-[0_10px_35px_rgba(0,0,0,.12)] backdrop-blur-2xl" style={headerStyle}>
            <div className="mx-auto flex min-h-[76px] max-w-6xl items-center justify-between gap-3 px-4 sm:px-6">
                <div className="flex min-w-0 items-center gap-3">
                    {logo && !logoFailed ? <img src={logo} alt={`شعار ${restaurantName}`} onError={() => setLogoFailed(true)} className="h-12 w-12 shrink-0 rounded-2xl bg-white object-contain p-1.5 shadow-lg" /> : <div className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl border text-xl font-black shadow-lg" style={{ borderColor: `${theme.accent}80`, backgroundColor: `${theme.accent}12`, color: theme.accent }}>✦</div>}
                    <div className="min-w-0"><p className="mb-0.5 flex items-center gap-1.5 truncate text-[9px] font-black tracking-[.12em]" style={{ color: muted }}><span className="inline-block h-1.5 w-1.5 rounded-full" style={{ backgroundColor: theme.accent }} /> تجربة طعام رقمية</p><p className="truncate text-base font-black sm:text-lg">{restaurantName}</p>{preview && <p className="text-[9px]" style={{ color: muted }}>معاينة القالب — التغييرات غير منشورة</p>}</div>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    {phone && <a href={`tel:${phone}`} aria-label="اتصل بالمطعم" className="grid h-10 w-10 place-items-center rounded-2xl border border-white/10 bg-white/[.035] text-sm transition hover:bg-white/10" style={{ color: theme.accent }}>☎</a>}
                    {whatsapp && <a href={`https://wa.me/${whatsapp}`} target="_blank" rel="noreferrer" aria-label="واتساب المطعم" className="grid h-10 w-10 place-items-center rounded-2xl border border-white/10 bg-white/[.035] text-sm transition hover:bg-white/10" style={{ color: theme.accent }}>◉</a>}
                    <button type="button" aria-label="فتح السلة" onClick={() => window.dispatchEvent(new Event('nexora:open-cart'))} className="grid h-10 w-10 place-items-center rounded-2xl text-lg shadow-md transition hover:brightness-110" style={{ backgroundColor: theme.accent, color: '#17110a' }}>🛒</button>
                </div>
            </div>
        </header>

        <main className="mx-auto max-w-6xl px-3 pb-28 pt-3 sm:px-6 sm:pt-6">
            <section className="relative isolate overflow-hidden border border-white/[.12] shadow-2xl" style={{ borderRadius: radius, boxShadow: '0 32px 90px rgba(0,0,0,.42)' }}>
                <div className="relative h-[390px] sm:h-[500px]">
                    {heroProduct && imageUrl(heroProduct.image_path) ? <img src={imageUrl(heroProduct.image_path) as string} alt={heroProduct.name} fetchPriority="high" className="h-full w-full object-cover" /> : <div role="img" aria-label="صورة الوجبة غير متاحة" className="grid h-full w-full place-items-center bg-[radial-gradient(circle_at_50%_25%,rgba(231,181,93,.22),transparent_34%),linear-gradient(135deg,#201911,#080807)] text-7xl text-white/50">🍽️</div>}
                    <div className="absolute inset-0 bg-[linear-gradient(180deg,rgba(0,0,0,.05)_0%,rgba(0,0,0,.1)_25%,rgba(0,0,0,.82)_68%,rgba(0,0,0,.97)_100%)]" />
                    <div className="absolute right-5 top-5 flex items-center gap-2 rounded-full border border-white/20 bg-black/30 px-3 py-2 backdrop-blur-md sm:right-8 sm:top-8"><span className="grid h-6 w-6 place-items-center rounded-full text-[10px] font-black text-black" style={{ backgroundColor: theme.accent }}>N</span><span className="text-[9px] font-black tracking-[.2em] text-white/85">NEXORA <span className="text-white/45">EXPERIENCE</span></span></div>
                    <div className="absolute inset-x-0 bottom-0 p-5 sm:p-10">
                        <span className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-black/35 px-3 py-1.5 text-[10px] font-black text-white/90 backdrop-blur-md"><span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: theme.accent }} /> اكتشف قائمة الطعام</span>
                        <h1 className="mt-4 max-w-3xl text-4xl font-black leading-[1.2] tracking-tight text-white drop-shadow-lg sm:text-6xl">{restaurantName}</h1>
                        <p className="mt-3 max-w-xl text-sm leading-7 text-white/75 sm:text-base">كل اللي بتحبه في مكان واحد. اختار من المنيو، شوف التفاصيل، وابدأ طلبك في خطوات بسيطة.</p>
                        <div className="mt-5 flex flex-wrap items-center gap-3">
                            {heroProduct && <button type="button" onClick={() => requestProductConfiguration(heroProduct, theme)} className="rounded-xl px-5 py-3.5 text-sm font-black text-black shadow-[0_8px_28px_rgba(0,0,0,.3)] transition hover:-translate-y-0.5 hover:brightness-105" style={{ backgroundColor: theme.accent, borderRadius: radius }}>جرّب {heroProduct.name} <span className="mx-1 opacity-60">·</span> {money(heroProduct.price)} <span className="mr-1">←</span></button>}
                            <span className="rounded-xl border border-white/15 bg-black/20 px-3 py-3 text-xs font-bold text-white/75 backdrop-blur">{categories.length} أقسام <span className="mx-1 text-white/30">/</span> {allProducts.length} صنف</span>
                        </div>
                    </div>
                </div>
            </section>

            <section className="pt-7">
                <div className="mb-4 flex items-end justify-between gap-3"><div><p className="text-[9px] font-black tracking-[.16em]" style={{ color: theme.primary }}>THE MENU</p><h2 className="mt-1 text-xl font-black sm:text-2xl">اختار على مزاجك</h2><p className="mt-1 text-xs" style={{ color: muted }}>تصفح الأقسام واكتشف اختياراتك</p></div><span className="text-[10px]" style={{ color: muted }}>اسحب ←</span></div>
                <div className="nx-category-scroll -mx-3 flex snap-x gap-2 overflow-x-auto px-3 pb-2 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0">
                    <button type="button" onClick={() => setActiveCategoryId(null)} aria-pressed={activeCategoryId === null} className="shrink-0 snap-start rounded-full border px-4 py-2.5 text-xs font-black transition" style={{ borderRadius: '999px', borderColor: activeCategoryId === null ? theme.accent : subtle, backgroundColor: activeCategoryId === null ? theme.accent : (lightSurface ? '#ffffff' : 'rgba(255,255,255,.04)'), color: activeCategoryId === null ? '#17100a' : theme.foreground }}>كل الأصناف</button>
                    {categories.map((category: any) => {
                        const selected = activeCategoryId === category.id;
                        return <button key={category.id} type="button" onClick={() => setActiveCategoryId(category.id)} aria-pressed={selected} className="shrink-0 snap-start rounded-full border px-4 py-2.5 text-xs font-bold transition" style={{ borderRadius: '999px', borderColor: selected ? theme.accent : subtle, backgroundColor: selected ? `${theme.accent}22` : (lightSurface ? '#ffffff' : 'rgba(255,255,255,.035)'), color: selected ? theme.primary : theme.foreground }}>{category.name}<span className="mr-2 text-[10px] opacity-60">{category.products?.length ?? 0}</span></button>;
                    })}
                </div>
            </section>

            <section id="fast-food-menu" className="scroll-mt-24 pt-9">
                <div className="mb-5 flex items-end justify-between gap-3 border-b pb-4" style={{ borderColor: subtle }}><div><p className="text-[9px] font-black tracking-[.16em]" style={{ color: theme.primary }}>MADE FOR YOUR CRAVINGS</p><h2 className="mt-1 text-2xl font-black sm:text-3xl">{searchActive ? 'نتائج البحث' : activeCategoryId === null ? 'كل الأصناف' : activeCategory?.name ?? 'المنيو'}</h2></div><span className="rounded-full border px-3 py-1.5 text-[10px]" style={{ borderColor: subtle, color: muted }}>{(searchActive || activeCategoryId === null ? allProducts : activeCategory?.products ?? []).length} صنف</span></div>
                {categories.filter((category: any) => searchActive || activeCategoryId === null || category.id === activeCategoryId).map((category: any) => <div key={category.id} className="mb-7">
                    {(searchActive || activeCategoryId === null) && <h3 className="mb-3 border-r-2 pr-3 text-lg font-black" style={{ borderColor: theme.accent }}>{category.name}</h3>}
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {(category.products ?? []).map((product: any) => <article key={product.id} className="nx-fast-card group overflow-hidden border" style={{ borderRadius: radius, backgroundColor: surface, borderColor: subtle }}>
                            <button type="button" disabled={!product.is_available} onClick={() => requestProductConfiguration(product, theme)} className="block w-full text-right disabled:cursor-not-allowed disabled:opacity-50">
                                <div className="relative aspect-[1.35] overflow-hidden bg-black/5">{imageUrl(product.image_path) ? <img src={imageUrl(product.image_path) as string} alt={product.name} loading="lazy" className="h-full w-full object-cover transition duration-700 group-hover:scale-[1.045]" /> : <div role="img" aria-label={`صورة ${product.name} غير متاحة`} className="grid h-full w-full place-items-center text-5xl" style={{ color: muted, background: `linear-gradient(135deg, ${theme.primary}12, ${theme.accent}20)` }}>🍽️</div>}{product.is_featured && <span className="absolute right-3 top-3 rounded-full border border-white/20 px-3 py-1.5 text-[10px] font-black text-black shadow-lg" style={{ backgroundColor: theme.accent }}>اختيار مميز</span>}{!product.is_available && <span className="absolute inset-0 grid place-items-center bg-black/65 text-sm font-black text-white">غير متاح مؤقتًا</span>}<span className="absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-black/20 to-transparent" /></div>
                                <div className="p-4 sm:p-5"><div className="flex items-start justify-between gap-3"><h4 className="text-[15px] font-black leading-7">{product.name}</h4><strong className="shrink-0 rounded-xl px-3 py-2 text-sm" style={{ color: theme.primary, backgroundColor: `${theme.accent}24` }}>{money(product.price)}</strong></div>{product.description && <p className="mt-2 line-clamp-2 min-h-10 text-xs leading-6" style={{ color: muted }}>{product.description}</p>}<div className="mt-4 flex items-center justify-between gap-2 border-t pt-4" style={{ borderColor: subtle }}><span className="text-[10px]" style={{ color: muted }}>{(product.variants ?? []).filter((variant: any) => variant.is_active !== false).length ? 'أحجام واختيارات متاحة' : 'متاح للطلب'}</span><span className="rounded-xl px-3.5 py-2.5 text-xs font-black shadow-md transition group-hover:brightness-110" style={{ backgroundColor: theme.accent, color: lightSurface ? theme.foreground : '#17110a' }}>{product.is_available ? 'اطلب دلوقتي +' : 'غير متاح'}</span></div></div>
                            </button>
                        </article>)}
                    </div>
                    {!category.products?.length && <p className="rounded-xl border border-dashed border-white/15 p-6 text-center text-sm text-white/45">القسم ده لسه مفيهوش أصناف.</p>}
                </div>)}
                {!categories.length && <p className="rounded-xl border border-dashed border-white/15 p-10 text-center text-sm text-white/45">المنيو لسه مفيهاش أقسام أو أصناف متاحة.</p>}
            </section>
        </main>

        <footer className="border-t px-4 py-12 text-center" style={{ borderColor: subtle, backgroundColor: lightSurface ? `${theme.primary}08` : '#0b0908' }}>
            <div className="mx-auto mb-5 flex max-w-xs items-center justify-center gap-3"><span className="h-px flex-1" style={{ backgroundColor: subtle }}/><span className="text-[9px] font-black tracking-[.24em]" style={{ color: muted }}>NEXORA QR EXPERIENCE</span><span className="h-px flex-1" style={{ backgroundColor: subtle }}/></div>
            <p className="text-lg font-black">{restaurantName}</p><p className="mt-1 text-xs" style={{ color: muted }}>{menu.name}</p>
            {restaurantAddress && <p className="mx-auto mt-3 max-w-xl text-xs leading-6 text-white/65">📍 {restaurantAddress}</p>}
            <div className="mt-5 flex flex-wrap justify-center gap-2">{phone && <a href={`tel:${phone}`} className="rounded-xl border border-white/15 px-4 py-2.5 text-xs font-bold transition hover:bg-white/5">اتصال بالمطعم</a>}{whatsapp && <a href={`https://wa.me/${whatsapp}`} target="_blank" rel="noreferrer" className="rounded-xl border border-white/15 px-4 py-2.5 text-xs font-bold transition hover:bg-white/5">واتساب المطعم</a>}<button type="button" onClick={() => window.dispatchEvent(new Event('nexora:open-cart'))} className="rounded-xl px-4 py-2.5 text-xs font-black text-black" style={{ backgroundColor: theme.accent }}>عرض السلة</button></div>
            <p className="mt-8 text-[9px]" style={{ color: muted }}>Digital menu experience by NEXORA</p>
        </footer>
    </div>;
}
