import { useState } from 'react';
import { modifierSelectionError, toggleModifierSelection } from './modifierChoices';

type Props = {
    menu: any;
    restaurantName: string;
    preview?: boolean;
};

const money = (value: string | number) => Number(value).toFixed(0) + ' ج.م';
const imageUrl = (path?: string | null) =>
    path && (path.startsWith('http') || path.startsWith('/')) ? path : path ? '/storage/' + path : null;
const stockFoodImages = [
    'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=1200&q=85',
    'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?auto=format&fit=crop&w=900&q=85',
    'https://images.unsplash.com/photo-1550547660-d9450f859349?auto=format&fit=crop&w=900&q=85',
    'https://images.unsplash.com/photo-1513104890138-7c749659a591?auto=format&fit=crop&w=900&q=85',
    'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=900&q=85',
];
const fallbackFoodImage = (index = 0) => stockFoodImages[Math.abs(index) % stockFoodImages.length];
const radiusMap = { sm: '0.65rem', md: '0.85rem', lg: '1rem', xl: '1.25rem', '2xl': '1.75rem' } as const;

const motionCss = "@keyframes nxFloat{0%,100%{transform:translateY(0) rotate(-1deg)}50%{transform:translateY(-9px) rotate(1deg)}}@keyframes nxGlow{0%,100%{opacity:.25;transform:scale(.96)}50%{opacity:.6;transform:scale(1.05)}}@keyframes nxShine{from{transform:translateX(-130%) skewX(-18deg)}to{transform:translateX(160%) skewX(-18deg)}}@keyframes nxOrbit{from{transform:rotate(0deg) translateX(8px) rotate(0deg)}to{transform:rotate(360deg) translateX(8px) rotate(-360deg)}}.nx-fast{background-image:radial-gradient(circle at 85% 8%,rgba(231,181,93,.1),transparent 24%),radial-gradient(circle at 10% 42%,rgba(201,149,60,.07),transparent 26%)}.nx-hero{isolation:isolate;box-shadow:0 30px 80px rgba(0,0,0,.38),inset 0 1px 0 rgba(255,255,255,.12);transform-style:preserve-3d}.nx-hero:before{content:'';position:absolute;inset:-20%;z-index:-1;background:radial-gradient(circle,rgba(231,181,93,.18),transparent 55%);filter:blur(28px);animation:nxGlow 6s ease-in-out infinite}.nx-float{animation:nxFloat 5s ease-in-out infinite}.nx-shine{position:relative;overflow:hidden;transform:translateZ(0)}.nx-shine:after{content:'';position:absolute;inset:0 auto 0 -45%;width:32%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.16),transparent);transform:skewX(-18deg);pointer-events:none}.nx-shine:hover:after{animation:nxShine .85s ease}.nx-product{transition:transform .28s cubic-bezier(.2,.8,.2,1),border-color .28s,box-shadow .28s;box-shadow:0 8px 20px rgba(0,0,0,.12),inset 0 1px 0 rgba(255,255,255,.04)}.nx-product:hover{transform:translateY(-4px) perspective(800px) rotateX(1deg);border-color:rgba(231,181,93,.32)!important;box-shadow:0 22px 45px rgba(0,0,0,.3),0 0 0 1px rgba(231,181,93,.05)}.nx-glass{background:linear-gradient(135deg,rgba(255,255,255,.12),rgba(255,255,255,.025));backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,.16);box-shadow:inset 0 1px 0 rgba(255,255,255,.16),0 16px 38px rgba(0,0,0,.18)}.nx-orbit{animation:nxOrbit 14s linear infinite}@media(prefers-reduced-motion:reduce){.nx-float,.nx-hero:before,.nx-orbit{animation:none}.nx-product{transition:none}}";

function ImageOrFallback({ src, alt, className, priority = false }: { src?: string | null; alt: string; className: string; priority?: boolean }) {
    return src ? (
        <img src={src} alt={alt} className={className} loading={priority ? 'eager' : 'lazy'} fetchPriority={priority ? 'high' : 'auto'} decoding="async" />
    ) : (
        <div className={className + ' flex items-center justify-center bg-[radial-gradient(circle_at_30%_20%,#6d4a20,transparent_38%),linear-gradient(135deg,#191512,#050505)] text-4xl'}>🍔</div>
    );
}

export default function FastFoodTemplate({ menu, restaurantName, preview = false }: Props) {
    const theme = menu.theme ?? {
        primary: '#c9953c',
        accent: '#e7b55d',
        background: '#090807',
        foreground: '#f8efe0',
        radius: 'xl',
    };
    const radius = radiusMap[theme.radius as keyof typeof radiusMap] ?? radiusMap.xl;
    const categories = menu.categories ?? [];
    const [activeCategoryId, setActiveCategoryId] = useState<number | undefined>(categories[0]?.id);
    const [selectedProduct, setSelectedProduct] = useState<any>(null);
    const [selectedVariantId, setSelectedVariantId] = useState<number | null>(null);
    const [selectedModifierIds, setSelectedModifierIds] = useState<number[]>([]);
    const [modifierError, setModifierError] = useState('');
    const [quantity, setQuantity] = useState(1);
    const [heroTilt, setHeroTilt] = useState({ x: 0, y: 0 });

    const activeCategory = categories.find((category: any) => category.id === activeCategoryId) ?? categories[0];
    const allProducts = categories.flatMap((category: any) => category.products ?? []);
    const heroProduct =
        allProducts.find((product: any) => product.is_featured && product.is_available) ??
        allProducts.find((product: any) => product.is_available) ??
        allProducts[0];

    const openProduct = (product: any) => {
        setSelectedProduct(product);
        setSelectedVariantId(product.variants?.find((variant: any) => variant.is_active !== false)?.id ?? null);
        setSelectedModifierIds([]);
        setModifierError('');
        setQuantity(1);
    };
    const closeProduct = () => {
        setSelectedProduct(null);
        setSelectedVariantId(null);
        setSelectedModifierIds([]);
        setQuantity(1);
    };

    const selectedVariant = selectedProduct?.variants?.find((variant: any) => variant.id === selectedVariantId);
    const selectedPrice = selectedProduct
        ? Number(selectedProduct.price) + (
            selectedVariant?.price
                ? Number(selectedVariant.price) - Number(selectedProduct.price)
                : Number(selectedVariant?.price_delta ?? 0)
        )
        : 0;
    const addSelectedToOrder = () => {
        if (!selectedProduct) return;
        const validationError = modifierSelectionError(selectedProduct.modifiers ?? [], selectedModifierIds);
        if (validationError) { setModifierError(validationError); return; }
        const modifiers = (selectedProduct.modifiers ?? []).filter((modifier: any) => selectedModifierIds.includes(modifier.id)).map((modifier: any) => ({ id: modifier.id, name: modifier.name, price: Number(modifier.price_delta ?? 0) }));
        window.dispatchEvent(new CustomEvent('nexora:add-to-cart', { detail: { productId: selectedProduct.id, name: selectedProduct.name, price: selectedPrice + modifiers.reduce((sum: number, modifier: any) => sum + modifier.price, 0), variantId: selectedVariantId, variantName: selectedVariant?.name, modifiers, quantity } }));
        closeProduct();
        window.dispatchEvent(new Event('nexora:open-cart'));
    };

    return (
        <>
            <style>{motionCss + "@keyframes nxReveal{from{opacity:0;transform:translateY(14px) scale(.985)}to{opacity:1;transform:translateY(0) scale(1)}"}</style><div dir="rtl" style={{ backgroundColor: theme.background, color: theme.foreground }} className="nx-fast min-h-screen overflow-x-hidden bg-[#090807]">
            <header className="sticky top-0 z-40 border-b border-white/10 bg-[#090807]/92 backdrop-blur-xl">
                <div className="mx-auto flex h-16 max-w-3xl items-center justify-between px-4">
                    <button type="button" aria-label="عرض الأقسام" onClick={() => document.getElementById('fast-food-menu')?.scrollIntoView({ behavior: 'smooth', block: 'start' })} className="grid h-10 w-10 place-items-center rounded-full border border-white/10 bg-white/[0.04] text-xl text-white">☰</button>
                    <div className="flex min-w-0 items-center gap-2">
                        <div className="grid h-9 w-9 shrink-0 place-items-center rounded-full border text-lg font-black" style={{ borderColor: theme.accent, color: theme.accent }}>✦</div>
                        <div className="min-w-0 text-center">
                            <p className="truncate font-serif text-lg font-bold tracking-wide text-[#f3d18d]">{restaurantName}</p>
                            {preview && <p className="text-[8px] uppercase tracking-[0.28em] text-white/35">NEXORA PREVIEW</p>}
                        </div>
                    </div>
                    <button type="button" aria-label="فتح السلة" onClick={() => window.dispatchEvent(new Event('nexora:open-cart'))} className="relative grid h-10 w-10 place-items-center rounded-full border border-white/10 bg-white/[0.04] text-xl text-[#f3d18d]">
                        🛒
                    </button>
                </div>
            </header>

            <main className="mx-auto max-w-3xl pb-28">
                <section className="px-3 pt-3">
                    <div className="nx-hero relative overflow-hidden border border-white/10 shadow-2xl animate-[nxReveal_.7s_ease-out_both]" style={{ borderRadius: '0 0 ' + radius + ' ' + radius }} onPointerMove={(event) => { if (event.pointerType === 'touch') return; const rect = event.currentTarget.getBoundingClientRect(); setHeroTilt({ x: ((event.clientY - rect.top) / rect.height - 0.5) * -7, y: ((event.clientX - rect.left) / rect.width - 0.5) * 9 }); }} onPointerLeave={() => setHeroTilt({ x: 0, y: 0 })}>
                        <div className="aspect-[0.9] min-h-[390px] overflow-hidden transition-transform duration-200 ease-out" style={{ transform: `perspective(1100px) rotateX(${heroTilt.x * 0.12}deg) rotateY(${heroTilt.y * 0.12}deg) scale(1.025)` }}>
                            <ImageOrFallback priority src={imageUrl(heroProduct?.image_path) ?? fallbackFoodImage(0)} alt={heroProduct?.name ?? menu.name} className="h-full w-full object-cover transition-transform duration-200 ease-out will-change-transform" />
                        </div>
                        <div aria-hidden="true" className="pointer-events-none absolute inset-0 z-[1] transition-opacity duration-200" style={{ background: `radial-gradient(circle at ${50 + heroTilt.y * 2}% ${50 + heroTilt.x * -2}%, rgba(255,255,255,.16), transparent 38%)`, opacity: Math.abs(heroTilt.x) + Math.abs(heroTilt.y) > 0 ? 1 : 0 }} />
                        <div className="absolute inset-0 bg-[linear-gradient(to_top,rgba(4,3,2,0.96)_0%,rgba(4,3,2,0.18)_52%,rgba(4,3,2,0.08)_100%)]" />
                        {heroProduct && <div className="nx-glass absolute left-4 top-5 z-10 max-w-[68%] rounded-2xl p-3 text-left shadow-2xl sm:left-6 sm:top-7 sm:p-4" style={{ transform: 'perspective(700px) rotateY(-8deg) rotateX(3deg)' }}><p className="text-[8px] uppercase tracking-[0.24em] text-white/55">SIGNATURE PICK</p><p className="mt-1 truncate text-xs font-black text-white sm:text-sm">{heroProduct.name}</p><p className="mt-1 text-sm font-black" style={{ color: theme.accent }}>{money(heroProduct.price)}</p><div className="mt-2 h-1 overflow-hidden rounded-full bg-white/10"><div className="h-full w-2/3 rounded-full" style={{ backgroundColor: theme.accent }} /></div></div>}
                        <div className="absolute inset-x-0 bottom-0 p-6 text-center [transform:translateZ(35px)]">
                            <div className="nx-glass mx-auto mb-4 inline-flex items-center gap-2 rounded-full px-4 py-2 text-[9px] font-bold tracking-[0.18em] text-white/85"><span className="h-1.5 w-1.5 rounded-full bg-emerald-300 shadow-[0_0_12px_rgba(110,231,183,.9)]" /> FRESH · MADE TO ORDER <span className="nx-orbit text-sm" style={{ color: theme.accent }}>✦</span></div>
                            <p className="text-[10px] font-bold uppercase tracking-[0.48em]" style={{ color: theme.accent }}>BOLD FLAVOR</p>
                            <h1 className="mt-3 font-serif text-4xl font-bold leading-tight text-white sm:text-5xl">{menu.name}</h1>
                            <p className="mx-auto mt-3 max-w-xs text-[11px] leading-6 text-white/60">Fresh ingredients. Big flavor. Made for your next craving.</p>
                        </div>
                    </div>
                </section>

                <section className="px-4 pt-7">
                    <div className="mb-4 flex items-end justify-between">
                        <div><p className="text-[9px] uppercase tracking-[0.32em] text-white/35">Explore</p><h2 className="mt-1 text-xl font-black">اختار اللي نفسك فيه</h2></div>
                        <span className="text-[10px] text-white/35">{categories.length} أقسام</span>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        {categories.slice(0, 4).map((category: any) => {
                            const tileProduct = category.products?.find((product: any) => product.image_path) ?? category.products?.[0];
                            return (
                                <button key={category.id} type="button" onClick={() => {
                                    setActiveCategoryId(category.id);
                                    window.setTimeout(() => document.getElementById('fast-food-menu')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 0);
                                }} className="nx-shine group relative min-h-36 overflow-hidden border border-white/10 text-right shadow-lg transition active:scale-[0.98]" style={{ borderRadius: radius }}>
                                    <ImageOrFallback src={imageUrl(tileProduct?.image_path) ?? fallbackFoodImage(category.id)} alt={category.name} className="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105" />
                                    <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/25 to-transparent" />
                                    <div className="absolute inset-x-0 bottom-0 p-3">
                                        <p className="text-[9px] uppercase tracking-[0.24em]" style={{ color: theme.accent }}>{category.products?.length ?? 0} ITEMS</p>
                                        <h3 className="mt-1 text-base font-black text-white">{category.name}</h3>
                                        <span className="mt-2 inline-flex h-7 w-7 items-center justify-center rounded-full text-black" style={{ backgroundColor: theme.accent }}>←</span>
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                </section>

                <section id="fast-food-menu" className="scroll-mt-20 px-4 pt-9">
                        <div className="mb-4 flex items-end justify-between">
                        <div><p className="text-[9px] uppercase tracking-[0.32em] text-white/35">Menu</p><h2 className="mt-1 text-2xl font-black">{activeCategory?.name ?? 'القائمة'}</h2></div>
                        <span className="text-[10px] text-white/35">{activeCategory?.products?.length ?? 0} اختيارات</span>
                    </div>
                    <div className="flex gap-2 overflow-x-auto pb-2 [scrollbar-width:none]">
                        {categories.map((category: any) => (
                            <button key={category.id} type="button" onClick={() => setActiveCategoryId(category.id)} className="shrink-0 border px-4 py-2 text-xs font-bold transition" style={{
                                borderRadius: 999,
                                borderColor: activeCategoryId === category.id ? theme.accent : 'rgba(255,255,255,0.1)',
                                backgroundColor: activeCategoryId === category.id ? theme.accent : 'rgba(255,255,255,0.04)',
                                color: activeCategoryId === category.id ? '#0a0806' : 'rgba(255,255,255,0.68)',
                            }}>{category.name}</button>
                        ))}
                    </div>

                    <div className="mt-5 space-y-2.5">
                        {(activeCategory?.products ?? []).map((product: any) => (
                            <button key={product.id} type="button" disabled={!product.is_available} onClick={() => openProduct(product)} className="nx-product nx-shine group flex w-full items-center gap-3 border border-white/10 bg-white/[0.035] p-2 text-right shadow-lg transition active:scale-[0.99] disabled:opacity-45 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-[#090807]" style={{ borderRadius: radius }}>
                                <div className="relative h-20 w-20 shrink-0 overflow-hidden rounded-[0.85rem] bg-black/40 ring-1 ring-white/10 shadow-[0_8px_24px_rgba(0,0,0,.35)]">
                                    <ImageOrFallback src={imageUrl(product.image_path) ?? fallbackFoodImage(product.id)} alt={product.name} className="h-full w-full object-cover transition duration-500 group-hover:scale-105" />
                                </div>
                                <div className="min-w-0 flex-1 py-1">
                                    <div className="flex items-start justify-between gap-2">
                                        <h3 className="truncate text-sm font-black text-white">{product.name}</h3>
                                        {product.is_featured && <span className="shrink-0 text-[8px] font-bold uppercase tracking-wider" style={{ color: theme.accent }}>Featured</span>}
                                    </div>
                                    {product.description && <p className="mt-1 line-clamp-2 text-[10px] leading-5 text-white/40">{product.description}</p>}
                                    <div className="mt-2 flex items-center gap-2">
                                        <span className="text-sm font-black" style={{ color: theme.accent }}>{money(product.price)}</span>
                                        {product.variants?.length ? <span className="text-[9px] text-white/30">+ {product.variants.length} اختيارات</span> : null}
                                    </div>
                                </div>
                                <span className="nx-float nx-glass grid h-9 w-9 shrink-0 place-items-center rounded-full border text-lg font-light text-white transition group-hover:scale-105" style={{ borderColor: theme.accent, color: theme.accent }}>+</span>
                            </button>
                        ))}
                        {(activeCategory?.products?.length ?? 0) === 0 && <div className="rounded-2xl border border-dashed border-white/10 p-10 text-center text-sm text-white/35">القسم ده لسه مفيهوش منتجات.</div>}
                    </div>
                </section>

                <section className="px-4 pb-4 pt-12 text-center"><div className="mx-auto h-px w-20" style={{ backgroundColor: theme.accent }} /><p className="mt-4 font-serif text-sm tracking-[0.32em] text-white/30">MADE TO CRAVE</p></section>
            </main>

            <div className="hidden">
                <div className="pointer-events-auto mx-auto flex max-w-3xl items-center gap-3 border border-white/10 bg-[#0d0b09]/94 p-2 shadow-2xl backdrop-blur-xl" style={{ borderRadius: radius }}>
                    <div className="flex min-w-0 flex-1 items-center gap-3 px-2"><div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl" style={{ backgroundColor: theme.accent + '18', color: theme.accent }}>🛒</div><div className="min-w-0"><p className="text-[9px] uppercase tracking-widest text-white/35">Your order</p><p className="truncate text-xs font-bold text-white">السلة فارغة</p></div></div>
                    <button type="button" onClick={() => window.dispatchEvent(new Event('nexora:open-cart'))} className="rounded-xl px-5 py-3 text-xs font-black text-black shadow-lg transition active:scale-95" style={{ backgroundColor: theme.accent }}>ابدأ الطلب</button>
                </div>
            </div>

            {selectedProduct && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/75 p-0 backdrop-blur-sm sm:items-center sm:p-4" onClick={closeProduct}>
                    <div dir="rtl" role="dialog" aria-modal="true" aria-label={selectedProduct.name} className="max-h-[92vh] w-full max-w-lg overflow-y-auto border border-white/10 bg-[#100d0b] shadow-2xl sm:max-h-[88vh]" style={{ borderRadius: radius + ' ' + radius + ' 0 0' }} onClick={(event) => event.stopPropagation()}>
                        <div className="relative"><div className="aspect-[1.15]"><ImageOrFallback src={imageUrl(selectedProduct.image_path) ?? fallbackFoodImage(selectedProduct.id)} alt={selectedProduct.name} className="h-full w-full object-cover" /></div><div className="absolute inset-0 bg-gradient-to-t from-[#100d0b] via-transparent to-black/20" /><button type="button" onClick={closeProduct} className="absolute right-4 top-4 grid h-9 w-9 place-items-center rounded-full bg-black/50 text-lg text-white backdrop-blur" aria-label="إغلاق">×</button></div>
                        <div className="px-5 pb-5">
                            <div className="flex items-start justify-between gap-4"><div><h2 className="text-2xl font-black text-white">{selectedProduct.name}</h2>{selectedProduct.description && <p className="mt-2 text-xs leading-6 text-white/45">{selectedProduct.description}</p>}</div><strong className="shrink-0 text-lg" style={{ color: theme.accent }}>{money(selectedPrice)}</strong></div>
                            {(selectedProduct.variants?.filter((variant: any) => variant.is_active !== false).length ?? 0) > 0 && (
                                <div className="mt-6"><h3 className="mb-2 text-sm font-black">اختار الحجم / النوع</h3><div className="grid gap-2">{selectedProduct.variants?.filter((variant: any) => variant.is_active !== false).map((variant: any) => {
                                    const active = selectedVariantId === variant.id;
                                    const variantPrice = variant.price ? money(variant.price) : (Number(variant.price_delta ?? 0) >= 0 ? '+' : '') + money(variant.price_delta ?? 0);
                                    return <button key={variant.id} type="button" onClick={() => setSelectedVariantId(active ? null : variant.id)} className="flex items-center justify-between border p-3 text-right" style={{ borderRadius: radius, borderColor: active ? theme.accent : 'rgba(255,255,255,0.08)', backgroundColor: active ? theme.accent + '12' : 'rgba(255,255,255,0.03)' }}><span className="text-xs font-bold">{variant.name}</span><span className="text-xs" style={{ color: theme.accent }}>{variantPrice}</span></button>;
                                })}</div></div>
                            )}
                            {(selectedProduct.modifiers?.filter((modifier: any) => modifier.is_active).length ?? 0) > 0 && (
                                <div className="mt-6"><h3 className="mb-2 text-sm font-black">اختار الإضافات</h3><div className="space-y-2">{selectedProduct.modifiers?.filter((modifier: any) => modifier.is_active).map((modifier: any) => { const checked = selectedModifierIds.includes(modifier.id); return <label key={modifier.id} className="flex cursor-pointer items-center justify-between gap-3 border border-white/8 bg-white/[0.03] p-3" style={{ borderRadius: radius, borderColor: checked ? theme.accent : 'rgba(255,255,255,0.08)' }}><span className="flex items-center gap-3"><input type={modifier.group_name && Number(modifier.group_max_choices ?? 1) === 1 ? 'radio' : 'checkbox'} name={`fast-modifier-${selectedProduct.id}-${modifier.group_name ?? 'ungrouped'}`} checked={checked} onChange={() => { setSelectedModifierIds(ids => toggleModifierSelection(selectedProduct.modifiers ?? [], ids, modifier)); setModifierError(''); }} className="h-4 w-4 accent-amber-400" /><span><span className="block text-xs font-bold">{modifier.name}</span><span className="mt-0.5 block text-[9px] text-white/35">{modifier.group_name ? `${modifier.group_name} · اختار ${modifier.group_min_choices ?? 0}–${modifier.group_max_choices ?? 1}` : modifier.is_required ? 'مطلوب' : 'اختياري'}</span></span></span><span className="shrink-0 text-xs" style={{ color: theme.accent }}>{Number(modifier.price_delta) >= 0 ? '+' : ''}{money(modifier.price_delta)}</span></label>; })}</div>{modifierError && <p role="alert" className="mt-2 text-xs font-bold text-rose-400">{modifierError}</p>}</div>
                            )}
                            <div className="mt-7 flex items-center gap-3">
                                <div className="flex items-center rounded-xl border border-white/10 bg-white/[0.04] p-1"><button type="button" aria-label="تقليل الكمية" onClick={() => setQuantity((value) => Math.max(1, value - 1))} className="grid h-9 w-9 place-items-center rounded-lg text-lg">−</button><span className="w-8 text-center text-sm font-black">{quantity}</span><button type="button" disabled={quantity >= 30} aria-label="زيادة الكمية" onClick={() => setQuantity((value) => Math.min(30, value + 1))} className="grid h-9 w-9 place-items-center rounded-lg text-lg disabled:opacity-30">+</button></div>
                                <button type="button" className="flex-1 px-4 py-3.5 text-sm font-black text-black shadow-lg" style={{ backgroundColor: theme.accent, borderRadius: radius }} onClick={addSelectedToOrder}>إضافة للطلب · {money((selectedPrice + (selectedProduct.modifiers ?? []).filter((modifier: any) => selectedModifierIds.includes(modifier.id)).reduce((sum: number, modifier: any) => sum + Number(modifier.price_delta ?? 0), 0)) * quantity)}</button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
        </>
    );
}
