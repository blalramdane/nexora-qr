import { useState } from 'react';

type Props = { menu: any; restaurantName: string; preview?: boolean };

const money = (value: string | number) => Number(value).toFixed(0) + ' ج.م';
const imageUrl = (path?: string | null) =>
    path && (path.startsWith('http') || path.startsWith('/')) ? path : path ? '/storage/' + path : null;
const radiusMap = { sm: '0.65rem', md: '0.85rem', lg: '1rem', xl: '1.25rem', '2xl': '1.75rem' } as const;

const motionCss = "@keyframes nxSteam{0%{opacity:0;transform:translateY(12px) scaleX(.8)}45%{opacity:.35}100%{opacity:0;transform:translateY(-26px) scaleX(1.15)}}@keyframes nxGlow{0%,100%{opacity:.25}50%{opacity:.55}}.nx-cafe{background-image:radial-gradient(circle at 20% 0%,rgba(214,164,91,.13),transparent 25%),linear-gradient(180deg,rgba(255,255,255,.015),transparent 28%)}.nx-cafe-hero{isolation:isolate;box-shadow:0 28px 80px rgba(0,0,0,.34)}.nx-cafe-hero:after{content:'';position:absolute;width:180px;height:180px;left:-70px;top:20px;border-radius:999px;background:rgba(214,164,91,.16);filter:blur(45px);animation:nxGlow 6s ease-in-out infinite;pointer-events:none}.nx-steam{position:absolute;width:2px;height:34px;border-radius:99px;background:linear-gradient(transparent,rgba(255,255,255,.25),transparent);filter:blur(1px);animation:nxSteam 3.8s ease-in-out infinite}.nx-cafe-card{transition:transform .3s cubic-bezier(.2,.8,.2,1),box-shadow .3s,border-color .3s}.nx-cafe-card:hover{transform:translateY(-4px);box-shadow:0 20px 44px rgba(0,0,0,.28);border-color:rgba(214,164,91,.3)!important}@media(prefers-reduced-motion:reduce){.nx-cafe-hero:after,.nx-steam{animation:none}.nx-cafe-card{transition:none}}";

function Image({ src, alt, className }: { src?: string | null; alt: string; className: string }) {
    return src ? <img src={src} alt={alt} className={className} loading="lazy" /> :
        <div className={className + ' flex items-center justify-center bg-[radial-gradient(circle_at_50%_20%,#8a5b32,transparent_45%),linear-gradient(135deg,#24170f,#090706)] text-4xl'}>☕</div>;
}

export default function CafeTemplate({ menu, restaurantName, preview = false }: Props) {
    const theme = menu.theme ?? { primary: '#2b1d14', accent: '#d6a45b', background: '#0d0907', foreground: '#f7ecdc', radius: 'xl' };
    const radius = radiusMap[theme.radius as keyof typeof radiusMap] ?? radiusMap.xl;
    const categories = menu.categories ?? [];
    const [activeId, setActiveId] = useState<number | undefined>(categories[0]?.id);
    const [selected, setSelected] = useState<any>(null);
    const [variant, setVariant] = useState<number | null>(null);
    const [quantity, setQuantity] = useState(1);

    const current = categories.find((c: any) => c.id === activeId) ?? categories[0];
    const products = categories.flatMap((c: any) => c.products ?? []);
    const hero = products.find((p: any) => p.is_featured && p.is_available) ?? products.find((p: any) => p.is_available) ?? products[0];
    const open = (product: any) => {
        setSelected(product);
        setVariant(product.variants?.find((v: any) => v.is_active !== false)?.id ?? null);
        setQuantity(1);
    };
    const close = () => { setSelected(null); setVariant(null); setQuantity(1); };
    const selectedVariant = selected?.variants?.find((v: any) => v.id === variant);
    const price = selected ? Number(selected.price) + (selectedVariant?.price ? Number(selectedVariant.price) - Number(selected.price) : Number(selectedVariant?.price_delta ?? 0)) : 0;

    return (\n        <>\n        <style>{motionCss}</style>\n        <div dir="rtl" style={{ backgroundColor: theme.background, color: theme.foreground }} className="nx-cafe min-h-screen overflow-x-hidden bg-[#0d0907]">
            <header className="sticky top-0 z-40 border-b border-white/10 bg-[#0d0907]/90 backdrop-blur-xl">
                <div className="mx-auto flex h-[68px] max-w-3xl items-center justify-between px-4">
                    <button type="button" className="grid h-10 w-10 place-items-center rounded-full border border-white/10 bg-white/[0.04] text-xl">☰</button>
                    <div className="text-center">
                        <div className="flex items-center justify-center gap-2">
                            <span className="text-xl" style={{ color: theme.accent }}>☕</span>
                            <strong className="font-serif text-lg text-[#f3d18d]">{restaurantName}</strong>
                        </div>
                        {preview && <p className="text-[8px] tracking-[0.28em] text-white/30">NEXORA PREVIEW</p>}
                    </div>
                    <button type="button" className="relative grid h-10 w-10 place-items-center rounded-full border border-white/10 text-xl" style={{ color: theme.accent }}>
                        🛒<span className="absolute -right-1 -top-1 grid h-4 min-w-4 place-items-center rounded-full px-1 text-[9px] font-black text-black" style={{ backgroundColor: theme.accent }}>2</span>
                    </button>
                </div>
            </header>

            <main className="mx-auto max-w-3xl pb-28">
                <section className="relative px-3 pt-3">
                    <div className="nx-cafe-hero relative overflow-hidden border border-white/10 shadow-2xl" style={{ borderRadius: '0 0 ' + radius + ' ' + radius }}>
                        <div className="aspect-[0.9] min-h-[390px]">
                            <Image src={imageUrl(hero?.image_path)} alt={hero?.name ?? menu.name} className="h-full w-full object-cover" />
                        </div>
                        <div className="absolute inset-0 bg-[linear-gradient(to_top,rgba(5,3,2,0.97),rgba(5,3,2,0.18)_58%,rgba(5,3,2,0.05))]" />
                        <div className="absolute inset-x-0 bottom-0 p-6">
                            <p className="text-[10px] font-bold uppercase tracking-[0.42em]" style={{ color: theme.accent }}>COFFEE & MORE</p>
                            <h1 className="mt-3 font-serif text-4xl font-bold leading-tight text-white">{menu.name}</h1>
                            <p className="mt-2 max-w-xs text-[11px] leading-6 text-white/55">Good coffee. Better mood. Made fresh for your day.</p>
                        </div>
                    </div>
                </section>

                <section className="px-4 pt-7">
                    <div className="mb-4">
                        <p className="text-[9px] uppercase tracking-[0.32em] text-white/30">Explore</p>
                        <h2 className="mt-1 text-xl font-black">اختار مشروبك</h2>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        {categories.slice(0, 4).map((category: any) => {
                            const photo = category.products?.find((p: any) => p.image_path) ?? category.products?.[0];
                            return <button key={category.id} type="button" onClick={() => {
                                setActiveId(category.id);
                                window.setTimeout(() => document.getElementById('cafe-menu')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 0);
                            }} className="group relative min-h-36 overflow-hidden border border-white/10 text-right shadow-lg" style={{ borderRadius: radius }}>
                                <Image src={imageUrl(photo?.image_path)} alt={category.name} className="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105" />
                                <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/25 to-transparent" />
                                <div className="absolute inset-x-0 bottom-0 p-3">
                                    <p className="text-[9px] uppercase tracking-[0.2em]" style={{ color: theme.accent }}>{category.products?.length ?? 0} ITEMS</p>
                                    <h3 className="mt-1 text-base font-black text-white">{category.name}</h3>
                                    <span className="mt-2 inline-flex h-7 w-7 items-center justify-center rounded-full text-black" style={{ backgroundColor: theme.accent }}>←</span>
                                </div>
                            </button>;
                        })}
                    </div>
                </section>

                <section id="cafe-menu" className="scroll-mt-20 px-4 pt-9">
                    <div className="mb-4 flex items-end justify-between">
                        <div><p className="text-[9px] uppercase tracking-[0.3em] text-white/30">Menu</p><h2 className="mt-1 text-2xl font-black">{current?.name ?? 'القائمة'}</h2></div>
                        <span className="text-[10px] text-white/30">{current?.products?.length ?? 0} اختيار</span>
                    </div>
                    <div className="flex gap-2 overflow-x-auto pb-2">
                        {categories.map((c: any) => <button key={c.id} type="button" onClick={() => setActiveId(c.id)} className="shrink-0 border px-4 py-2 text-xs font-bold" style={{ borderRadius: 999, borderColor: activeId === c.id ? theme.accent : 'rgba(255,255,255,.1)', backgroundColor: activeId === c.id ? theme.accent : 'rgba(255,255,255,.04)', color: activeId === c.id ? '#1a1009' : 'rgba(255,255,255,.68)' }}>{c.name}</button>)}
                    </div>
                    <div className="mt-5 space-y-2.5">
                        {(current?.products ?? []).map((product: any) => <button key={product.id} type="button" disabled={!product.is_available} onClick={() => open(product)} className="group flex w-full items-center gap-3 border border-white/10 bg-white/[0.035] p-2 text-right transition active:scale-[.99] disabled:opacity-40" style={{ borderRadius: radius }}>
                            <div className="h-20 w-20 shrink-0 overflow-hidden rounded-xl"><Image src={imageUrl(product.image_path)} alt={product.name} className="h-full w-full object-cover transition duration-500 group-hover:scale-105" /></div>
                            <div className="min-w-0 flex-1 py-1">
                                <div className="flex items-start justify-between gap-2"><h3 className="truncate text-sm font-black text-white">{product.name}</h3>{product.is_featured && <span className="text-[8px] uppercase tracking-wider" style={{ color: theme.accent }}>Special</span>}</div>
                                {product.description && <p className="mt-1 line-clamp-2 text-[10px] leading-5 text-white/40">{product.description}</p>}
                                <div className="mt-2 flex items-center gap-2"><span className="text-sm font-black" style={{ color: theme.accent }}>{money(product.price)}</span>{product.variants?.length ? <span className="text-[9px] text-white/30">+ {product.variants.length} اختيارات</span> : null}</div>
                            </div>
                            <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full border text-lg text-black" style={{ backgroundColor: theme.accent, borderColor: theme.accent }}>+</span>
                        </button>)}
                    </div>
                </section>
            </main>

            <div className="pointer-events-none fixed inset-x-0 bottom-0 z-30 px-4 pb-4">
                <div className="pointer-events-auto mx-auto flex max-w-3xl items-center gap-3 border border-white/10 bg-[#0d0b09]/95 p-2 shadow-2xl backdrop-blur-xl" style={{ borderRadius: radius }}>
                    <div className="flex min-w-0 flex-1 items-center gap-3 px-2"><span className="grid h-10 w-10 place-items-center rounded-xl" style={{ backgroundColor: theme.accent + '18', color: theme.accent }}>🛒</span><div><p className="text-[9px] uppercase tracking-widest text-white/30">Your order</p><p className="text-xs font-bold">2 items · 130 ج.م</p></div></div>
                    <button type="button" className="rounded-xl px-5 py-3 text-xs font-black text-black" style={{ backgroundColor: theme.accent }}>عرض الطلب</button>
                </div>
            </div>

            {selected && <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/75 backdrop-blur-sm sm:items-center sm:p-4" onClick={close}>
                <div role="dialog" aria-modal="true" className="max-h-[92vh] w-full max-w-lg overflow-y-auto border border-white/10 bg-[#100d0b] shadow-2xl" style={{ borderRadius: radius + ' ' + radius + ' 0 0' }} onClick={e => e.stopPropagation()}>
                    <div className="relative"><div className="aspect-[1.15]"><Image src={imageUrl(selected.image_path)} alt={selected.name} className="h-full w-full object-cover" /></div><div className="absolute inset-0 bg-gradient-to-t from-[#100d0b] via-transparent to-black/10" /><button type="button" onClick={close} className="absolute right-4 top-4 grid h-9 w-9 place-items-center rounded-full bg-black/50 text-white">×</button></div>
                    <div className="p-5">
                        <div className="flex items-start justify-between gap-4"><div><h2 className="text-2xl font-black">{selected.name}</h2><p className="mt-2 text-xs leading-6 text-white/45">{selected.description}</p></div><strong style={{ color: theme.accent }}>{money(price)}</strong></div>
                        {selected.variants?.length > 0 && <div className="mt-6"><h3 className="mb-2 text-sm font-black">الحجم</h3><div className="grid grid-cols-3 gap-2">{selected.variants.filter((v: any) => v.is_active !== false).map((v: any) => <button key={v.id} type="button" onClick={() => setVariant(variant === v.id ? null : v.id)} className="border p-3 text-center" style={{ borderRadius: radius, borderColor: variant === v.id ? theme.accent : 'rgba(255,255,255,.08)', backgroundColor: variant === v.id ? theme.accent + '12' : 'rgba(255,255,255,.03)' }}><span className="block text-xs font-bold">{v.name}</span><span className="mt-1 block text-[10px]" style={{ color: theme.accent }}>{v.price ? money(v.price) : (Number(v.price_delta ?? 0) >= 0 ? '+' : '') + money(v.price_delta ?? 0)}</span></button>)}</div></div>}
                        {selected.modifiers?.filter((m: any) => m.is_active).length > 0 && <div className="mt-6"><h3 className="mb-2 text-sm font-black">إضافات</h3><div className="space-y-2">{selected.modifiers.filter((m: any) => m.is_active).map((m: any) => <div key={m.id} className="flex items-center justify-between border border-white/10 bg-white/[.03] p-3" style={{ borderRadius: radius }}><span className="text-xs font-bold">{m.name}</span><span className="text-xs" style={{ color: theme.accent }}>+{money(m.price_delta)}</span></div>)}</div></div>}
                        <div className="mt-7 flex gap-3"><div className="flex items-center rounded-xl border border-white/10 p-1"><button type="button" onClick={() => setQuantity(Math.max(1, quantity - 1))} className="h-10 w-10">−</button><span className="w-8 text-center font-bold">{quantity}</span><button type="button" onClick={() => setQuantity(quantity + 1)} className="h-10 w-10">+</button></div><button type="button" onClick={close} className="flex-1 py-3.5 text-sm font-black text-black" style={{ backgroundColor: theme.accent, borderRadius: radius }}>إضافة للطلب · {money(price * quantity)}</button></div>
                    </div>
                </div>
            </div>}
        </div>
    );
}
