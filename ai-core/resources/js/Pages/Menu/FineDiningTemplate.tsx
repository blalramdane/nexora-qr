import { useState } from 'react';

type Props = { menu: any; restaurantName: string; preview?: boolean };

const money = (value: string | number) => Number(value).toFixed(0) + ' ج.م';
const imageUrl = (path?: string | null) =>
    path && (path.startsWith('http') || path.startsWith('/')) ? path : path ? '/storage/' + path : null;
const radiusMap = { sm: '0.65rem', md: '0.85rem', lg: '1rem', xl: '1.25rem', '2xl': '1.75rem' } as const;

const motionCss = "@keyframes nxAurora{0%,100%{transform:translate3d(0,0,0) scale(1);opacity:.22}50%{transform:translate3d(4%,-3%,0) scale(1.08);opacity:.4}}.nx-fine{background-image:radial-gradient(circle at 50% -8%,rgba(240,223,174,.08),transparent 30%),linear-gradient(180deg,rgba(255,255,255,.012),transparent 32%)}.nx-fine-hero{isolation:isolate;box-shadow:0 35px 100px rgba(0,0,0,.48)}.nx-fine-hero:before{content:'';position:absolute;inset:-15%;z-index:-1;background:radial-gradient(ellipse at 30% 20%,rgba(240,223,174,.13),transparent 38%),radial-gradient(ellipse at 70% 60%,rgba(180,145,70,.08),transparent 40%);filter:blur(28px);animation:nxAurora 9s ease-in-out infinite}.nx-editorial{transition:transform .45s cubic-bezier(.2,.8,.2,1),opacity .45s}.nx-editorial:hover{transform:translateY(-5px)}.nx-editorial img{transition:transform .8s cubic-bezier(.2,.8,.2,1),filter .8s}.nx-editorial:hover img{transform:scale(1.045);filter:saturate(1.08) contrast(1.04)}@media(prefers-reduced-motion:reduce){.nx-fine-hero:before{animation:none}.nx-editorial,.nx-editorial img{transition:none}}";

function Image({ src, alt, className }: { src?: string | null; alt: string; className: string }) {
    return src ? <img src={src} alt={alt} className={className} loading="lazy" /> :
        <div className={className + ' flex items-center justify-center bg-[radial-gradient(circle_at_50%_20%,#c8a96b,transparent_32%),linear-gradient(135deg,#181714,#050505)] text-4xl'}>🍽️</div>;
}

export default function FineDiningTemplate({ menu, restaurantName, preview = false }: Props) {
    const theme = menu.theme ?? { primary: '#d8c18a', accent: '#f0dfae', background: '#080807', foreground: '#f5efe2', radius: 'lg' };
    const borderRadius = radiusMap[theme.radius as keyof typeof radiusMap] ?? radiusMap.lg;
    const categories = menu.categories ?? [];
    const products = categories.flatMap((c: any) => c.products ?? []);
    const [activeId, setActiveId] = useState<number | undefined>(categories[0]?.id);
    const [selected, setSelected] = useState<any>(null);
    const [variant, setVariant] = useState<number | null>(null);
    const [quantity, setQuantity] = useState(1);

    const current = categories.find((c: any) => c.id === activeId) ?? categories[0];
    const hero = products.find((p: any) => p.is_featured && p.is_available) ?? products.find((p: any) => p.is_available) ?? products[0];

    const open = (product: any) => {
        setSelected(product);
        setVariant(product.variants?.find((v: any) => v.is_active !== false)?.id ?? null);
        setQuantity(1);
    };
    const close = () => { setSelected(null); setVariant(null); setQuantity(1); };
    const selectedVariant = selected?.variants?.find((v: any) => v.id === variant);
    const price = selected ? Number(selected.price) + (selectedVariant?.price ? Number(selectedVariant.price) - Number(selected.price) : Number(selectedVariant?.price_delta ?? 0)) : 0;

    return (
        <>
            <style>{motionCss}</style>\n        <div dir="rtl" style={{ backgroundColor: theme.background, color: theme.foreground }} className="nx-fine min-h-screen overflow-x-hidden bg-[#080807]">
            <header className="sticky top-0 z-40 border-b border-white/10 bg-[#080807]/90 backdrop-blur-xl">
                <div className="mx-auto flex h-[72px] max-w-5xl items-center justify-between px-5">
                    <button type="button" aria-label="القائمة" className="grid h-10 w-10 place-items-center border border-white/10 text-lg text-white/70" style={{ borderRadius: '999px' }}>☰</button>
                    <div className="text-center">
                        <p className="font-serif text-[17px] tracking-[0.16em]" style={{ color: theme.accent }}>{restaurantName}</p>
                        {preview && <p className="mt-0.5 text-[8px] uppercase tracking-[0.3em] text-white/30">NEXORA PREVIEW</p>}
                    </div>
                    <button type="button" aria-label="السلة" className="relative grid h-10 w-10 place-items-center border border-white/10 text-lg" style={{ borderRadius: '999px', color: theme.accent }}>🛒<span className="absolute -right-1 -top-1 grid h-4 min-w-4 place-items-center rounded-full px-1 text-[8px] font-black text-black" style={{ backgroundColor: theme.accent }}>0</span></button>
                </div>
            </header>

            <main className="mx-auto max-w-5xl pb-28">
                <section className="px-4 pt-4 sm:px-6">
                    <div className="nx-fine-hero relative overflow-hidden border border-white/10 shadow-2xl" style={{ borderRadius }}>
                        <div className="aspect-[1.05] min-h-[360px] sm:min-h-[500px]">
                            <Image src={imageUrl(hero?.image_path)} alt={hero?.name ?? menu.name} className="h-full w-full object-cover" />
                        </div>
                        <div className="absolute inset-0 bg-[linear-gradient(to_top,rgba(4,4,3,.98),rgba(4,4,3,.2)_58%,rgba(4,4,3,.08))]" />
                        <div className="absolute inset-x-0 bottom-0 p-7 text-center sm:p-10">
                            <div className="mx-auto mb-4 h-px w-14" style={{ backgroundColor: theme.accent }} />
                            <p className="text-[9px] uppercase tracking-[0.5em]" style={{ color: theme.accent }}>A CURATED EXPERIENCE</p>
                            <h1 className="mt-3 font-serif text-4xl font-medium leading-tight sm:text-6xl">{menu.name}</h1>
                            <p className="mx-auto mt-4 max-w-lg text-xs leading-7 text-white/50">A refined selection of dishes, crafted with intention and served with detail.</p>
                        </div>
                    </div>
                </section>

                <section className="px-4 pt-10 sm:px-6">
                    <div className="grid grid-cols-3 border-y border-white/10 py-5 text-center">
                        <div><p className="font-serif text-lg" style={{ color: theme.accent }}>01</p><p className="mt-1 text-[8px] uppercase tracking-[0.25em] text-white/35">Curated</p></div>
                        <div className="border-x border-white/10"><p className="font-serif text-lg" style={{ color: theme.accent }}>02</p><p className="mt-1 text-[8px] uppercase tracking-[0.25em] text-white/35">Seasonal</p></div>
                        <div><p className="font-serif text-lg" style={{ color: theme.accent }}>03</p><p className="mt-1 text-[8px] uppercase tracking-[0.25em] text-white/35">Signature</p></div>
                    </div>
                </section>

                <section className="px-4 pt-10 sm:px-6">
                    <div className="mb-5 flex items-end justify-between">
                        <div><p className="text-[9px] uppercase tracking-[0.38em] text-white/30">The Menu</p><h2 className="mt-2 font-serif text-3xl">{current?.name ?? 'القائمة'}</h2></div>
                        <span className="text-[9px] uppercase tracking-widest text-white/25">{current?.products?.length ?? 0} selections</span>
                    </div>
                    <div className="flex gap-2 overflow-x-auto border-b border-white/10 pb-4">
                        {categories.map((c: any) => <button key={c.id} type="button" onClick={() => setActiveId(c.id)} className="shrink-0 px-5 py-2 text-xs font-medium tracking-wide transition" style={{ borderBottom: activeId === c.id ? '1px solid ' + theme.accent : '1px solid transparent', color: activeId === c.id ? theme.accent : 'rgba(255,255,255,.45)' }}>{c.name}</button>)}
                    </div>

                    <div className="mt-7 divide-y divide-white/10">
                        {(current?.products ?? []).map((product: any) => <button key={product.id} type="button" disabled={!product.is_available} onClick={() => open(product)} className="nx-editorial group flex w-full gap-4 py-5 text-right transition disabled:opacity-35 sm:gap-6">
                            <div className="order-2 h-24 w-24 shrink-0 overflow-hidden sm:h-32 sm:w-32" style={{ borderRadius }}>
                                <Image src={imageUrl(product.image_path)} alt={product.name} className="h-full w-full object-cover transition duration-700 group-hover:scale-105" />
                            </div>
                            <div className="order-1 min-w-0 flex-1 py-1">
                                <div className="flex items-start justify-between gap-4">
                                    <div><h3 className="font-serif text-xl font-medium">{product.name}</h3>{product.description && <p className="mt-2 max-w-xl text-[11px] leading-6 text-white/40">{product.description}</p>}</div>
                                    <span className="shrink-0 text-sm" style={{ color: theme.accent }}>{money(product.price)}</span>
                                </div>
                                <div className="mt-4 flex items-center gap-3 text-[8px] uppercase tracking-[0.25em] text-white/25">
                                    <span>{product.is_featured ? 'Signature' : 'Chef selection'}</span>
                                    {product.variants?.length ? <><span>•</span><span>{product.variants.length} options</span></> : null}
                                </div>
                            </div>
                        </button>)}
                    </div>
                </section>

                <section className="px-4 pb-8 pt-16 text-center sm:px-6">
                    <div className="mx-auto flex items-center justify-center gap-4"><span className="h-px w-16 bg-white/10" /><span className="text-lg" style={{ color: theme.accent }}>✦</span><span className="h-px w-16 bg-white/10" /></div>
                    <p className="mt-5 font-serif text-sm tracking-[0.38em] text-white/30">AN EVENING TO REMEMBER</p>
                </section>
            </main>

            <div className="pointer-events-none fixed inset-x-0 bottom-0 z-30 px-4 pb-4">
                <div className="pointer-events-auto mx-auto flex max-w-3xl items-center gap-3 border border-white/10 bg-[#0d0c0a]/95 p-2 shadow-2xl backdrop-blur-xl" style={{ borderRadius }}>
                    <div className="flex min-w-0 flex-1 items-center gap-3 px-2"><span className="grid h-10 w-10 shrink-0 place-items-center border border-white/10" style={{ borderRadius, color: theme.accent }}>🛒</span><div><p className="text-[8px] uppercase tracking-[0.25em] text-white/25">Your selection</p><p className="text-xs font-medium">السلة فارغة</p></div></div>
                    <button type="button" className="px-5 py-3 text-xs font-bold" style={{ backgroundColor: theme.accent, color: '#0b0907', borderRadius }}>عرض الطلب</button>
                </div>
            </div>

            {selected && <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/80 backdrop-blur-md sm:items-center sm:p-5" onClick={close}>
                <div role="dialog" aria-modal="true" className="max-h-[92vh] w-full max-w-2xl overflow-y-auto border border-white/10 bg-[#0d0c0a] shadow-2xl sm:max-h-[88vh]" style={{ borderRadius: borderRadius + ' ' + borderRadius + ' 0 0' }} onClick={e => e.stopPropagation()}>
                    <div className="relative"><div className="aspect-[1.4]"><Image src={imageUrl(selected.image_path)} alt={selected.name} className="h-full w-full object-cover" /></div><div className="absolute inset-0 bg-gradient-to-t from-[#0d0c0a] via-transparent to-black/10" /><button type="button" onClick={close} className="absolute right-4 top-4 grid h-9 w-9 place-items-center rounded-full bg-black/50 text-white">×</button></div>
                    <div className="p-6 sm:p-8">
                        <div className="flex items-start justify-between gap-6"><div><p className="text-[8px] uppercase tracking-[0.35em]" style={{ color: theme.accent }}>Chef selection</p><h2 className="mt-2 font-serif text-3xl">{selected.name}</h2><p className="mt-3 text-xs leading-7 text-white/45">{selected.description}</p></div><strong className="shrink-0 font-serif text-lg" style={{ color: theme.accent }}>{money(price)}</strong></div>
                        {selected.variants?.filter((v: any) => v.is_active !== false).length > 0 && <div className="mt-7"><h3 className="mb-3 text-xs font-bold uppercase tracking-widest text-white/50">Choose</h3><div className="grid gap-2 sm:grid-cols-3">{selected.variants.filter((v: any) => v.is_active !== false).map((v: any) => <button key={v.id} type="button" onClick={() => setVariant(variant === v.id ? null : v.id)} className="border p-3 text-right" style={{ borderRadius, borderColor: variant === v.id ? theme.accent : 'rgba(255,255,255,.08)', backgroundColor: variant === v.id ? theme.accent + '10' : 'rgba(255,255,255,.02)' }}><span className="block text-xs">{v.name}</span><span className="mt-1 block text-[10px]" style={{ color: theme.accent }}>{v.price ? money(v.price) : (Number(v.price_delta ?? 0) >= 0 ? '+' : '') + money(v.price_delta ?? 0)}</span></button>)}</div></div>}
                        {selected.modifiers?.filter((m: any) => m.is_active).length > 0 && <div className="mt-7"><h3 className="mb-3 text-xs font-bold uppercase tracking-widest text-white/50">Enhancements</h3><div className="space-y-2">{selected.modifiers.filter((m: any) => m.is_active).map((m: any) => <div key={m.id} className="flex items-center justify-between border border-white/10 p-3" style={{ borderRadius }}><span className="text-xs">{m.name}</span><span className="text-xs" style={{ color: theme.accent }}>+{money(m.price_delta)}</span></div>)}</div></div>}
                        <div className="mt-8 flex gap-3"><div className="flex items-center border border-white/10 p-1" style={{ borderRadius }}><button type="button" onClick={() => setQuantity(Math.max(1, quantity - 1))} className="h-10 w-10">−</button><span className="w-8 text-center text-sm">{quantity}</span><button type="button" onClick={() => setQuantity(quantity + 1)} className="h-10 w-10">+</button></div><button type="button" onClick={close} className="flex-1 py-3.5 text-xs font-bold" style={{ backgroundColor: theme.accent, color: '#0b0907', borderRadius }}>إضافة للطلب · {money(price * quantity)}</button></div>
                    </div>
                </div>
            </div>}
        </div>
        </>
    );
}
