import { useState } from 'react';
import { requestProductConfiguration } from './ProductConfigurator';

type Props = {
    menu: any;
    restaurantName: string;
    logoPath?: string | null;
    restaurantPhone?: string | null;
    restaurantWhatsappPhone?: string | null;
    searchActive?: boolean;
    preview?: boolean;
};

const money = (value: string | number) => `${Number(value).toLocaleString('ar-EG', { maximumFractionDigits: 2 })} ج.م`;
const imageUrl = (path?: string | null) => path ? (path.startsWith('http') || path.startsWith('/') || path.startsWith('blob:') || path.startsWith('data:') ? path : `/storage/${path}`) : null;
export default function FastFoodTemplate({ menu, restaurantName, logoPath, restaurantPhone, restaurantWhatsappPhone, searchActive = false, preview = false }: Props) {
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

    return <div dir="rtl" className="min-h-screen overflow-x-hidden" style={{ backgroundColor: theme.background, color: theme.foreground, fontFamily: 'Cairo, Tajawal, Tahoma, sans-serif' }}>
        <style>{`.nx-fast-card{transition:transform .24s ease,border-color .24s ease,box-shadow .24s ease}.nx-fast-card:hover{transform:translateY(-3px);box-shadow:0 18px 36px rgba(0,0,0,.22)}@media(prefers-reduced-motion:reduce){.nx-fast-card{transition:none}.nx-fast-card:hover{transform:none}}`}</style>
        <header className="sticky top-0 z-40 border-b border-white/10 bg-black/85 shadow-lg backdrop-blur-xl">
            <div className="mx-auto flex min-h-[72px] max-w-6xl items-center justify-between gap-3 px-4 sm:px-6">
                <div className="flex min-w-0 items-center gap-3">
                    {logo && !logoFailed ? <img src={logo} alt={`شعار ${restaurantName}`} onError={() => setLogoFailed(true)} className="h-11 w-11 shrink-0 rounded-xl bg-white object-contain p-1" /> : <div className="grid h-11 w-11 shrink-0 place-items-center rounded-xl border text-xl font-black" style={{ borderColor: theme.accent, color: theme.accent }}>✦</div>}
                    <div className="min-w-0"><p className="truncate text-[10px] font-black tracking-[.08em]" style={{ color: theme.accent }}>منيو إلكتروني · اطلب بسهولة</p><p className="truncate text-base font-black sm:text-lg">{restaurantName}</p>{preview && <p className="text-[9px] text-white/40">معاينة القالب — التغييرات غير منشورة</p>}</div>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    {phone && <a href={`tel:${phone}`} aria-label="اتصل بالمطعم" className="grid h-10 w-10 place-items-center rounded-full border border-white/15 text-sm" style={{ color: theme.accent }}>☎</a>}
                    {whatsapp && <a href={`https://wa.me/${whatsapp}`} target="_blank" rel="noreferrer" aria-label="واتساب المطعم" className="grid h-10 w-10 place-items-center rounded-full border border-white/15 text-sm" style={{ color: theme.accent }}>◉</a>}
                    <button type="button" aria-label="فتح السلة" onClick={() => window.dispatchEvent(new Event('nexora:open-cart'))} className="grid h-10 w-10 place-items-center rounded-full border border-white/15 text-xl" style={{ color: theme.accent }}>🛒</button>
                </div>
            </div>
        </header>

        <main className="mx-auto max-w-6xl px-3 pb-28 pt-4 sm:px-6 sm:pt-6">
            <section className="relative isolate overflow-hidden border border-white/10 shadow-2xl" style={{ borderRadius: `0 0 ${radius} ${radius}` }}>
                <div className="relative h-[320px] sm:h-[420px]">
                    {heroProduct && imageUrl(heroProduct.image_path) ? <img src={imageUrl(heroProduct.image_path) as string} alt={heroProduct.name} fetchPriority="high" className="h-full w-full object-cover" /> : <div role="img" aria-label="صورة الوجبة غير متاحة" className="grid h-full w-full place-items-center bg-[radial-gradient(circle_at_50%_25%,rgba(231,181,93,.22),transparent_34%),linear-gradient(135deg,#201911,#080807)] text-7xl text-white/50">🍽️</div>}
                    <div className="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-black/5" />
                    <div className="absolute inset-x-0 bottom-0 p-5 sm:p-8">
                        <span className="inline-flex rounded-full border border-white/20 bg-black/35 px-3 py-1.5 text-[10px] font-bold text-white/90">{heroProduct ? 'اختيار مميز من المنيو' : 'منيو المطعم'}</span>
                        <h1 className="mt-3 max-w-3xl text-3xl font-black leading-tight text-white sm:text-5xl">{menu.name}</h1>
                        <p className="mt-2 max-w-xl text-sm leading-7 text-white/70">اختار أكلك المفضل، خصّص الحجم والإضافات، وراجع إجمالي طلبك قبل التأكيد.</p>
                        {heroProduct && <button type="button" onClick={() => requestProductConfiguration(heroProduct, theme)} className="mt-4 rounded-xl px-5 py-3 text-sm font-black text-black shadow-xl" style={{ backgroundColor: theme.accent, borderRadius: radius }}>اطلب {heroProduct.name} · {money(heroProduct.price)}</button>}
                    </div>
                </div>
            </section>

            <section className="pt-8">
                <div className="mb-4 flex items-end justify-between gap-3"><div><p className="text-[10px] font-black tracking-[.08em]" style={{ color: theme.accent }}>استكشف المنيو</p><h2 className="mt-1 text-2xl font-black">اختار القسم</h2></div><span className="text-xs text-white/45">{categories.length} قسم · {allProducts.length} صنف</span></div>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    <button type="button" onClick={() => setActiveCategoryId(null)} aria-pressed={activeCategoryId === null} className="nx-fast-card relative flex min-h-24 items-end overflow-hidden border p-4 text-right" style={{ borderRadius: radius, borderColor: activeCategoryId === null ? theme.accent : 'rgba(255,255,255,.12)', backgroundColor: activeCategoryId === null ? `${theme.accent}20` : 'rgba(255,255,255,.04)' }}><span className="text-sm font-black">كل الأصناف</span><span className="mr-auto text-lg" style={{ color: theme.accent }}>←</span></button>
                    {categories.map((category: any) => {
                        const imageProduct = category.products?.find((product: any) => product.image_path) ?? category.products?.[0];
                        const selected = activeCategoryId === category.id;
                        return <button key={category.id} type="button" onClick={() => setActiveCategoryId(category.id)} aria-pressed={selected} className="nx-fast-card group relative min-h-24 overflow-hidden border text-right" style={{ borderRadius: radius, borderColor: selected ? theme.accent : 'rgba(255,255,255,.12)' }}>
                            {imageProduct && imageUrl(imageProduct.image_path) && <img src={imageUrl(imageProduct.image_path) as string} alt="" loading="lazy" className="absolute inset-0 h-full w-full object-cover opacity-55 transition duration-500 group-hover:scale-105" />}
                            <span className="absolute inset-0 bg-gradient-to-t from-black/90 to-black/25" />
                            <span className="relative flex h-full min-h-24 flex-col justify-end p-3"><span className="text-sm font-black text-white">{category.name}</span><span className="mt-1 text-[10px] text-white/60">{category.products?.length ?? 0} صنف</span></span>
                        </button>;
                    })}
                </div>
            </section>

            <section id="fast-food-menu" className="scroll-mt-24 pt-9">
                <div className="mb-4 flex items-end justify-between gap-3"><div><p className="text-[10px] font-black tracking-[.08em]" style={{ color: theme.accent }}>قائمة الطعام</p><h2 className="mt-1 text-2xl font-black">{searchActive ? 'نتائج البحث' : activeCategoryId === null ? 'كل الأصناف' : activeCategory?.name ?? 'المنيو'}</h2></div><span className="text-xs text-white/45">{(searchActive || activeCategoryId === null ? allProducts : activeCategory?.products ?? []).length} صنف</span></div>
                {categories.filter((category: any) => searchActive || activeCategoryId === null || category.id === activeCategoryId).map((category: any) => <div key={category.id} className="mb-7">
                    {(searchActive || activeCategoryId === null) && <h3 className="mb-3 border-r-2 pr-3 text-lg font-black" style={{ borderColor: theme.accent }}>{category.name}</h3>}
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {(category.products ?? []).map((product: any) => <article key={product.id} className="nx-fast-card overflow-hidden border border-white/10 bg-white/[.035]" style={{ borderRadius: radius }}>
                            <button type="button" disabled={!product.is_available} onClick={() => requestProductConfiguration(product, theme)} className="block w-full text-right disabled:cursor-not-allowed disabled:opacity-50">
                                <div className="relative aspect-[1.55] overflow-hidden bg-black/20">{imageUrl(product.image_path) ? <img src={imageUrl(product.image_path) as string} alt={product.name} loading="lazy" className="h-full w-full object-cover transition duration-500 hover:scale-105" /> : <div role="img" aria-label={`صورة ${product.name} غير متاحة`} className="grid h-full w-full place-items-center bg-[radial-gradient(circle_at_50%_25%,rgba(231,181,93,.15),transparent_34%),linear-gradient(135deg,#201911,#080807)] text-5xl text-white/40">🍽️</div>}{product.is_featured && <span className="absolute right-3 top-3 rounded-full px-3 py-1 text-[10px] font-black text-black" style={{ backgroundColor: theme.accent }}>مميز</span>}{!product.is_available && <span className="absolute inset-0 grid place-items-center bg-black/65 text-sm font-black text-white">غير متاح مؤقتًا</span>}</div>
                                <div className="p-4"><div className="flex items-start justify-between gap-3"><h4 className="font-black leading-6">{product.name}</h4><strong className="shrink-0 text-sm" style={{ color: theme.accent }}>{money(product.price)}</strong></div>{product.description && <p className="mt-2 line-clamp-2 text-xs leading-6 text-white/55">{product.description}</p>}<div className="mt-4 flex items-center justify-between gap-2 border-t border-white/10 pt-3"><span className="text-[10px] text-white/45">{(product.variants ?? []).filter((variant: any) => variant.is_active !== false).length ? 'أحجام واختيارات متاحة' : 'متاح للطلب'}</span><span className="rounded-lg px-3 py-2 text-xs font-black text-black" style={{ backgroundColor: theme.accent }}>{product.is_available ? 'اختار وأضف +' : 'غير متاح'}</span></div></div>
                            </button>
                        </article>)}
                    </div>
                    {!category.products?.length && <p className="rounded-xl border border-dashed border-white/15 p-6 text-center text-sm text-white/45">القسم ده لسه مفيهوش أصناف.</p>}
                </div>)}
                {!categories.length && <p className="rounded-xl border border-dashed border-white/15 p-10 text-center text-sm text-white/45">المنيو لسه مفيهاش أقسام أو أصناف متاحة.</p>}
            </section>
        </main>

        <footer className="border-t border-white/10 bg-black/35 px-4 py-8 text-center">
            <p className="font-black">{restaurantName}</p><p className="mt-1 text-xs text-white/45">{menu.name} · NEXORA QR</p>
            <div className="mt-4 flex flex-wrap justify-center gap-2">{phone && <a href={`tel:${phone}`} className="rounded-xl border border-white/15 px-4 py-2.5 text-xs font-bold">اتصال بالمطعم</a>}{whatsapp && <a href={`https://wa.me/${whatsapp}`} target="_blank" rel="noreferrer" className="rounded-xl border border-white/15 px-4 py-2.5 text-xs font-bold">واتساب المطعم</a>}<button type="button" onClick={() => window.dispatchEvent(new Event('nexora:open-cart'))} className="rounded-xl px-4 py-2.5 text-xs font-black text-black" style={{ backgroundColor: theme.accent }}>عرض السلة</button></div>
        </footer>
    </div>;
}
