import React, { useMemo, useState } from 'react';

type Variant = { id: number; name: string; price?: string | null; price_delta?: string | null; is_active?: boolean };
type Modifier = { id: number; name: string; price_delta: string; is_required: boolean; is_active: boolean };
type Product = {
    id: number;
    name: string;
    description?: string | null;
    price: string;
    image_path?: string | null;
    is_available: boolean;
    is_featured: boolean;
    variants?: Variant[];
    modifiers?: Modifier[];
};
type Category = { id: number; name: string; description?: string | null; products: Product[] };
type Theme = { primary: string; accent: string; background: string; foreground: string; radius: 'sm' | 'md' | 'lg' | 'xl' | '2xl' };
type Menu = {
    name: string;
    categories: Category[];
    theme?: Theme | null;
    template?: { key: string; name: string } | null;
    templateVersion?: { version: number; schema?: Record<string, unknown> } | null;
};
type Props = { menu: Menu; restaurantName: string; preview?: boolean };

const money = (value: string | number) => Number(value).toFixed(0) + ' ج.م';
const imageUrl = (path?: string | null) =>
    path && (path.startsWith('http') || path.startsWith('/')) ? path : path ? '/storage/' + path : null;
const radiusMap = { sm: '0.65rem', md: '0.85rem', lg: '1rem', xl: '1.25rem', '2xl': '1.75rem' } as const;

function ImageOrFallback({ src, alt, className }: { src?: string | null; alt: string; className: string }) {
    return src ? (
        <img src={src} alt={alt} className={className} loading="lazy" />
    ) : (
        <div className={className + ' flex items-center justify-center bg-[radial-gradient(circle_at_30%_20%,#6d4a20,transparent_38%),linear-gradient(135deg,#191512,#050505)] text-4xl'}>
            🍔
        </div>
    );
}

export default function FastFoodTemplate({ menu, restaurantName, preview = false }: Props) {
    const theme: Theme = menu.theme ?? {
        primary: '#c9953c',
        accent: '#e7b55d',
        background: '#090807',
        foreground: '#f8efe0',
        radius: 'xl',
    };
    const radius = radiusMap[theme.radius];
    const categories = menu.categories;
    const [activeCategoryId, setActiveCategoryId] = useState<number | undefined>(categories[0]?.id);
    const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
    const [selectedVariantId, setSelectedVariantId] = useState<number | null>(null);
    const [quantity, setQuantity] = useState(1);

    const activeCategory = categories.find((category) => category.id === activeCategoryId) ?? categories[0];
    const allProducts = useMemo(() => categories.flatMap((category) => category.products), [categories]);
    const heroProduct =
        allProducts.find((product) => product.is_featured && product.is_available) ??
        allProducts.find((product) => product.is_available) ??
        allProducts[0];

    const openProduct = (product: Product) => {
        setSelectedProduct(product);
        setSelectedVariantId(product.variants?.find((variant) => variant.is_active !== false)?.id ?? null);
        setQuantity(1);
    };
    const closeProduct = () => {
        setSelectedProduct(null);
        setSelectedVariantId(null);
        setQuantity(1);
    };

    const selectedVariant = selectedProduct?.variants?.find((variant) => variant.id === selectedVariantId);
    const variantDelta = selectedVariant?.price
        ? Number(selectedVariant.price) - Number(selectedProduct?.price ?? 0)
        : Number(selectedVariant?.price_delta ?? 0);
    const selectedPrice = Number(selectedProduct?.price ?? 0) + variantDelta;

    const themeStyle = {
        '--ff-primary': theme.primary,
        '--ff-accent': theme.accent,
        '--ff-background': theme.background,
        '--ff-foreground': theme.foreground,
        '--ff-radius': radius,
    } as React.CSSProperties;

    return (
        <div dir="rtl" style={{ ...themeStyle, backgroundColor: theme.background, color: theme.foreground }} className="min-h-screen overflow-x-hidden bg-[#090807]">
            <header className="sticky top-0 z-40 border-b border-white/10 bg-[#090807]/92 backdrop-blur-xl">
                <div className="mx-auto flex h-16 max-w-3xl items-center justify-between px-4">
                    <button type="button" aria-label="فتح القائمة" className="grid h-10 w-10 place-items-center rounded-full border border-white/10 bg-white/[0.04] text-xl text-white">☰</button>
                    <div className="flex min-w-0 items-center gap-2">
                        <div className="grid h-9 w-9 shrink-0 place-items-center rounded-full border text-lg font-black" style={{ borderColor: theme.accent, color: theme.accent }}>✦</div>
                        <div className="min-w-0 text-center">
                            <p className="truncate font-serif text-lg font-bold tracking-wide text-[#f3d18d]">{restaurantName}</p>
                            {preview && <p className="text-[8px] uppercase tracking-[0.28em] text-white/35">NEXORA PREVIEW</p>}
                        </div>
                    </div>
                    <button type="button" aria-label="السلة" className="relative grid h-10 w-10 place-items-center rounded-full border border-white/10 bg-white/[0.04] text-xl text-[#f3d18d]">
                        🛒
                        <span className="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full px-1 text-[9px] font-black text-black" style={{ backgroundColor: theme.accent }}>0</span>
                    </button>
                </div>
            </header>

            <main className="mx-auto max-w-3xl pb-28">
                <section className="relative overflow-hidden px-3 pt-3">
                    <div className="relative overflow-hidden border border-white/10 shadow-2xl" style={{ borderRadius: '0 0 ' + radius + ' ' + radius }}>
                        <div className="aspect-[0.9] min-h-[390px]">
                            <ImageOrFallback src={imageUrl(heroProduct?.image_path)} alt={heroProduct?.name ?? menu.name} className="h-full w-full object-cover" />
                        </div>
                        <div className="absolute inset-0 bg-[linear-gradient(to_top,rgba(4,3,2,0.96)_0%,rgba(4,3,2,0.18)_52%,rgba(4,3,2,0.08)_100%)]" />
                        <div className="absolute inset-x-0 bottom-0 p-6 text-center">
                            <p className="text-[10px] font-bold uppercase tracking-[0.48em]" style={{ color: theme.accent }}>BOLD FLAVOR</p>
                            <h1 className="mt-3 font-serif text-4xl font-bold leading-tight text-white sm:text-5xl">{menu.name}</h1>
                            <p className="mx-auto mt-3 max-w-xs text-[11px] leading-6 text-white/60">Fresh ingredients. Big flavor. Made for your next craving.</p>
                        </div>
                    </div>
                </section>

                <section className="px-4 pt-7">
                    <div className="mb-4 flex items-end justify-between">
                        <div>
                            <p className="text-[9px] uppercase tracking-[0.32em] text-white/35">Explore</p>
                            <h2 className="mt-1 text-xl font-black">اختار اللي نفسك فيه</h2>
                        </div>
                        <span className="text-[10px] text-white/35">{categories.length} أقسام</span>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        {categories.slice(0, 4).map((category) => {
                            const tileProduct = category.products.find((product) => product.image_path) ?? category.products[0];
                            return (
                                <button key={category.id} type="button" onClick={() => {
                                    setActiveCategoryId(category.id);
                                    window.setTimeout(() => document.getElementById('fast-food-menu')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 0);
                                }} className="group relative min-h-36 overflow-hidden border border-white/10 text-right shadow-lg transition active:scale-[0.98]" style={{ borderRadius: radius }}>
                                    <ImageOrFallback src={imageUrl(tileProduct?.image_path)} alt={category.name} className="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105" />
                                    <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/25 to-transparent" />
                                    <div className="absolute inset-x-0 bottom-0 p-3">
                                        <p className="text-[9px] uppercase tracking-[0.24em]" style={{ color: theme.accent }}>{category.products.length} ITEMS</p>
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
                        <div>
                            <p className="text-[9px] uppercase tracking-[0.32em] text-white/35">Menu</p>
                            <h2 className="mt-1 text-2xl font-black">{activeCategory?.name ?? 'القائمة'}</h2>
                        </div>
                        <span className="text-[10px] text-white/35">{activeCategory?.products.length ?? 0} اختيار</span>
                    </div>

                    <div className="flex gap-2 overflow-x-auto pb-2 [scrollbar-width:none]">
                        {categories.map((category) => (
                            <button key={category.id} type="button" onClick={() => setActiveCategoryId(category.id)} className="shrink-0 border px-4 py-2 text-xs font-bold transition" style={{
                                borderRadius: 999,
                                borderColor: activeCategoryId === category.id ? theme.accent : 'rgba(255,255,255,0.1)',
                                backgroundColor: activeCategoryId === category.id ? theme.accent : 'rgba(255,255,255,0.04)',
                                color: activeCategoryId === category.id ? '#0a0806' : 'rgba(255,255,255,0.68)',
                            }}>{category.name}</button>
                        ))}
                    </div>

                    <div className="mt-5 space-y-2.5">
                        {(activeCategory?.products ?? []).map((product) => (
                            <button key={product.id} type="button" disabled={!product.is_available} onClick={() => openProduct(product)} className="group flex w-full items-center gap-3 border border-white/10 bg-white/[0.035] p-2 text-right shadow-lg transition active:scale-[0.99] disabled:opacity-45" style={{ borderRadius: radius }}>
                                <div className="h-20 w-20 shrink-0 overflow-hidden rounded-[0.85rem] bg-black/40">
                                    <ImageOrFallback src={imageUrl(product.image_path)} alt={product.name} className="h-full w-full object-cover transition duration-500 group-hover:scale-105" />
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
                                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full border text-lg font-light text-black transition group-hover:scale-105" style={{ backgroundColor: theme.accent, borderColor: theme.accent }}>+</span>
                            </button>
                        ))}
                        {(activeCategory?.products.length ?? 0) === 0 && <div className="rounded-2xl border border-dashed border-white/10 p-10 text-center text-sm text-white/35">القسم ده لسه مفيهوش منتجات.</div>}
                    </div>
                </section>

                <section className="px-4 pb-4 pt-12 text-center">
                    <div className="mx-auto h-px w-20" style={{ backgroundColor: theme.accent }} />
                    <p className="mt-4 font-serif text-sm tracking-[0.32em] text-white/30">MADE TO CRAVE</p>
                </section>
            </main>

            <div className="pointer-events-none fixed inset-x-0 bottom-0 z-30 px-4 pb-4">
                <div className="pointer-events-auto mx-auto flex max-w-3xl items-center gap-3 border border-white/10 bg-[#0d0b09]/94 p-2 shadow-2xl backdrop-blur-xl" style={{ borderRadius: radius }}>
                    <div className="flex min-w-0 flex-1 items-center gap-3 px-2">
                        <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl" style={{ backgroundColor: theme.accent + '18', color: theme.accent }}>🛒</div>
                        <div className="min-w-0">
                            <p className="text-[9px] uppercase tracking-widest text-white/35">Your order</p>
                            <p className="truncate text-xs font-bold text-white">السلة فارغة</p>
                        </div>
                    </div>
                    <button type="button" className="rounded-xl px-5 py-3 text-xs font-black text-black shadow-lg transition active:scale-95" style={{ backgroundColor: theme.accent }}>ابدأ الطلب</button>
                </div>
            </div>

            {selectedProduct && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/75 p-0 backdrop-blur-sm sm:items-center sm:p-4" onClick={closeProduct}>
                    <div dir="rtl" role="dialog" aria-modal="true" aria-label={selectedProduct.name} className="max-h-[92vh] w-full max-w-lg overflow-y-auto border border-white/10 bg-[#100d0b] shadow-2xl sm:max-h-[88vh]" style={{ borderRadius: radius + ' ' + radius + ' 0 0' }} onClick={(event) => event.stopPropagation()}>
                        <div className="relative">
                            <div className="aspect-[1.15]">
                                <ImageOrFallback src={imageUrl(selectedProduct.image_path)} alt={selectedProduct.name} className="h-full w-full object-cover" />
                            </div>
                            <div className="absolute inset-0 bg-gradient-to-t from-[#100d0b] via-transparent to-black/20" />
                            <button type="button" onClick={closeProduct} className="absolute right-4 top-4 grid h-9 w-9 place-items-center rounded-full bg-black/50 text-lg text-white backdrop-blur" aria-label="إغلاق">×</button>
                        </div>

                        <div className="px-5 pb-5">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <h2 className="text-2xl font-black text-white">{selectedProduct.name}</h2>
                                    {selectedProduct.description && <p className="mt-2 text-xs leading-6 text-white/45">{selectedProduct.description}</p>}
                                </div>
                                <strong className="shrink-0 text-lg" style={{ color: theme.accent }}>{money(selectedPrice)}</strong>
                            </div>

                            {(selectedProduct.variants?.filter((variant) => variant.is_active !== false).length ?? 0) > 0 && (
                                <div className="mt-6">
                                    <div className="mb-2 flex items-center justify-between">
                                        <h3 className="text-sm font-black">اختار الحجم / النوع</h3>
                                        <span className="text-[9px] text-white/35">اختياري</span>
                                    </div>
                                    <div className="grid gap-2">
                                        {selectedProduct.variants?.filter((variant) => variant.is_active !== false).map((variant) => {
                                            const active = selectedVariantId === variant.id;
                                            const variantPrice = variant.price
                                                ? money(variant.price)
                                                : (Number(variant.price_delta ?? 0) >= 0 ? '+' : '') + money(variant.price_delta ?? 0);
                                            return (
                                                <button key={variant.id} type="button" onClick={() => setSelectedVariantId(active ? null : variant.id)} className="flex items-center justify-between border p-3 text-right" style={{
                                                    borderRadius: radius,
                                                    borderColor: active ? theme.accent : 'rgba(255,255,255,0.08)',
                                                    backgroundColor: active ? theme.accent + '12' : 'rgba(255,255,255,0.03)',
                                                }}>
                                                    <span className="text-xs font-bold">{variant.name}</span>
                                                    <span className="text-xs" style={{ color: theme.accent }}>{variantPrice}</span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            {(selectedProduct.modifiers?.filter((modifier) => modifier.is_active).length ?? 0) > 0 && (
                                <div className="mt-6">
                                    <h3 className="mb-2 text-sm font-black">الإضافات</h3>
                                    <div className="space-y-2">
                                        {selectedProduct.modifiers?.filter((modifier) => modifier.is_active).map((modifier) => (
                                            <div key={modifier.id} className="flex items-center justify-between border border-white/8 bg-white/[0.03] p-3" style={{ borderRadius: radius }}>
                                                <div>
                                                    <p className="text-xs font-bold">{modifier.name}</p>
                                                    <p className="mt-0.5 text-[9px] text-white/35">{modifier.is_required ? 'مطلوب' : 'اختياري'}</p>
                                                </div>
                                                <span className="text-xs" style={{ color: theme.accent }}>{Number(modifier.price_delta) >= 0 ? '+' : ''}{money(modifier.price_delta)}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            <div className="mt-7 flex items-center gap-3">
                                <div className="flex items-center rounded-xl border border-white/10 bg-white/[0.04] p-1">
                                    <button type="button" onClick={() => setQuantity((value) => Math.max(1, value - 1))} className="grid h-9 w-9 place-items-center rounded-lg text-lg">−</button>
                                    <span className="w-8 text-center text-sm font-black">{quantity}</span>
                                    <button type="button" onClick={() => setQuantity((value) => value + 1)} className="grid h-9 w-9 place-items-center rounded-lg text-lg">+</button>
                                </div>
                                <button type="button" className="flex-1 px-4 py-3.5 text-sm font-black text-black shadow-lg" style={{ backgroundColor: theme.accent, borderRadius: radius }} onClick={closeProduct}>
                                    إضافة للطلب · {money(selectedPrice * quantity)}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
