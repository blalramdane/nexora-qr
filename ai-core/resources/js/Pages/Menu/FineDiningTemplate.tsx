import { useState } from 'react';
import { modifierSelectionError, toggleModifierSelection } from './modifierChoices';

type Props = { menu: any; restaurantName: string; preview?: boolean };

const money = (value: string | number) => Number(value).toFixed(0) + ' ج.م';
const imageUrl = (path?: string | null) =>
    path && (path.startsWith('http') || path.startsWith('/')) ? path : path ? '/storage/' + path : null;
const fineDiningImages = [
    'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=1400&q=85',
    'https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=900&q=85',
    'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=900&q=85',
    'https://images.unsplash.com/photo-1559339352-11d035aa65de?auto=format&fit=crop&w=900&q=85',
    'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=900&q=85',
];
const fallbackFineImage = (index = 0) => fineDiningImages[Math.abs(index) % fineDiningImages.length];
const radiusMap = { sm: '0.65rem', md: '0.85rem', lg: '1rem', xl: '1.25rem', '2xl': '1.75rem' } as const;

const motionCss = "@keyframes nxAurora{0%,100%{transform:translate3d(0,0,0) scale(1);opacity:.22}50%{transform:translate3d(4%,-3%,0) scale(1.08);opacity:.4}}@keyframes nxGoldPulse{0%,100%{box-shadow:0 0 0 rgba(240,223,174,0)}50%{box-shadow:0 0 32px rgba(240,223,174,.12)}}.nx-fine{background-image:radial-gradient(circle at 50% -8%,rgba(240,223,174,.08),transparent 30%),linear-gradient(180deg,rgba(255,255,255,.012),transparent 32%)}.nx-fine-hero{isolation:isolate;box-shadow:0 35px 100px rgba(0,0,0,.48),inset 0 1px 0 rgba(255,255,255,.12);transform-style:preserve-3d}.nx-fine-hero:before{content:'';position:absolute;inset:-15%;z-index:-1;background:radial-gradient(ellipse at 30% 20%,rgba(240,223,174,.13),transparent 38%),radial-gradient(ellipse at 70% 60%,rgba(180,145,70,.08),transparent 40%);filter:blur(28px);animation:nxAurora 9s ease-in-out infinite}.nx-editorial{transition:transform .45s cubic-bezier(.2,.8,.2,1),opacity .45s;transform-style:preserve-3d}.nx-editorial:hover{transform:translateY(-5px) perspective(1000px) rotateX(1deg)}.nx-editorial img{transition:transform .8s cubic-bezier(.2,.8,.2,1),filter .8s}.nx-editorial:hover img{transform:scale(1.045);filter:saturate(1.08) contrast(1.04)}.nx-fine-glass{background:linear-gradient(135deg,rgba(255,255,255,.13),rgba(255,255,255,.025));backdrop-filter:blur(16px);border:1px solid rgba(240,223,174,.25);box-shadow:inset 0 1px 0 rgba(255,255,255,.15),0 14px 34px rgba(0,0,0,.24);animation:nxGoldPulse 7s ease-in-out infinite}@media(prefers-reduced-motion:reduce){.nx-fine-hero:before,.nx-fine-glass{animation:none}.nx-editorial,.nx-editorial img{transition:none}}";

function Image({ src, alt, className, priority = false }: { src?: string | null; alt: string; className: string; priority?: boolean }) {
    return src ? <img src={src} alt={alt} className={className} loading={priority ? 'eager' : 'lazy'} fetchPriority={priority ? 'high' : 'auto'} decoding="async" /> :
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
    const [modifierIds, setModifierIds] = useState<number[]>([]);
    const [modifierError, setModifierError] = useState('');
    const [quantity, setQuantity] = useState(1);
    const [heroTilt, setHeroTilt] = useState({ x: 0, y: 0 });

    const current = categories.find((c: any) => c.id === activeId) ?? categories[0];
    const hero = products.find((p: any) => p.is_featured && p.is_available) ?? products.find((p: any) => p.is_available) ?? products[0];

    const open = (product: any) => {
        setSelected(product);
        setVariant(product.variants?.find((v: any) => v.is_active !== false)?.id ?? null);
        setModifierIds([]);
        setQuantity(1);
    };
    const close = () => { setSelected(null); setVariant(null); setModifierIds([]); setQuantity(1); };
    const selectedVariant = selected?.variants?.find((v: any) => v.id === variant);
    const price = selected ? Number(selected.price) + (selectedVariant?.price ? Number(selectedVariant.price) - Number(selected.price) : Number(selectedVariant?.price_delta ?? 0)) : 0;
    const addSelectedToOrder = () => {
        if (!selected) return;
        const validationError = modifierSelectionError(selected.modifiers ?? [], modifierIds);
        if (validationError) { setModifierError(validationError); return; }
        const modifiers = (selected.modifiers ?? []).filter((m: any) => m.is_active && modifierIds.includes(m.id)).map((m: any) => ({ id: m.id, name: m.name, price: Number(m.price_delta ?? 0) }));
        window.dispatchEvent(new CustomEvent('nexora:add-to-cart', { detail: { productId: selected.id, name: selected.name, price: price + modifiers.reduce((sum: number, m: any) => sum + m.price, 0), variantId: variant, variantName: selectedVariant?.name, modifiers, quantity } }));
        close();
        window.dispatchEvent(new Event('nexora:open-cart'));
    };

    return (
        <>
            <style>{motionCss + "@keyframes nxReveal{from{opacity:0;transform:translateY(14px) scale(.985)}to{opacity:1;transform:translateY(0) scale(1)}"}</style><div dir="rtl" style={{ backgroundColor: theme.background, color: theme.foreground }} className="nx-fine min-h-screen overflow-x-hidden bg-[#080807]">
            <header className="sticky top-0 z-40 border-b border-white/10 bg-[#080807]/90 backdrop-blur-xl">
                <div className="mx-auto flex h-[72px] max-w-5xl items-center justify-between px-5">
                    <button type="button" aria-label="عرض القائمة" onClick={() => document.getElementById('fine-dining-menu')?.scrollIntoView({ behavior: 'smooth', block: 'start' })} className="grid h-10 w-10 place-items-center border border-white/10 text-lg text-white/70" style={{ borderRadius: '999px' }}>☰</button>
                    <div className="text-center">
                        <p className="font-serif text-[17px] tracking-[0.16em]" style={{ color: theme.accent }}>{restaurantName}</p>
                        {preview && <p className="mt-0.5 text-[8px] uppercase tracking-[0.3em] text-white/30">NEXORA PREVIEW</p>}
                    </div>
                    <button type="button" aria-label="فتح السلة" onClick={() => window.dispatchEvent(new Event('nexora:open-cart'))} className="relative grid h-10 w-10 place-items-center border border-white/10 text-lg" style={{ borderRadius: '999px', color: theme.accent }}>🛒</button>
                </div>
            </header>

            <main className="mx-auto max-w-5xl pb-28">
                <section className="px-4 pt-4 sm:px-6">
                    <div className="nx-fine-hero relative overflow-hidden border animate-[nxReveal_.9s_ease-out_both] border-white/10 shadow-2xl" style={{ borderRadius }} onPointerMove={(event) => { if (event.pointerType === 'touch') return; const rect = event.currentTarget.getBoundingClientRect(); setHeroTilt({ x: ((event.clientY - rect.top) / rect.height - 0.5) * -6, y: ((event.clientX - rect.left) / rect.width - 0.5) * 8 }); }} onPointerLeave={() => setHeroTilt({ x: 0, y: 0 })}>
                        <div className="aspect-[1.05] min-h-[360px] transition-transform duration-200 ease-out sm:min-h-[500px]" style={{ transform: `perspective(1100px) rotateX(${heroTilt.x * 0.12}deg) rotateY(${heroTilt.y * 0.12}deg) scale(1.025)` }}>
                            <Image priority src={imageUrl(hero?.image_path) ?? fallbackFineImage(0)} alt={hero?.name ?? menu.name} className="h-full w-full object-cover" />
                        </div>
                        <div className="absolute inset-0 bg-[linear-gradient(to_top,rgba(4,4,3,.98),rgba(4,4,3,.2)_58%,rgba(4,4,3,.08))]" />
                        {hero && <div className="nx-fine-glass absolute left-4 top-5 z-10 max-w-[68%] p-4 sm:left-7 sm:top-7" style={{ borderRadius, transform: 'perspective(800px) rotateY(-8deg) rotateX(2deg)' }}><p className="text-[8px] uppercase tracking-[0.3em] text-white/45">THE CHEF'S SIGNATURE</p><p className="mt-2 truncate font-serif text-sm text-white sm:text-base">{hero.name}</p><div className="mt-3 flex items-center justify-between gap-5"><span className="text-[8px] uppercase tracking-[0.2em] text-white/35">A curated dish</span><strong className="text-sm" style={{ color: theme.accent }}>{money(hero.price)}</strong></div><div className="mt-3 h-px w-full" style={{ backgroundColor: theme.accent, opacity: .55 }} /></div>}
                        <div className="absolute inset-x-0 bottom-0 p-7 text-center [transform:translateZ(32px)] sm:p-10">
                            <div className="nx-fine-glass mx-auto mb-5 inline-flex items-center gap-3 rounded-full px-5 py-2 text-[9px] uppercase tracking-[0.22em] text-white/80"><span style={{ color: theme.accent }}>✦</span> A SIGNATURE DINING MOMENT <span style={{ color: theme.accent }}>✦</span></div>
                            <div className="mx-auto mb-4 h-px w-14" style={{ backgroundColor: theme.accent }} />
                            <p className="text-[9px] uppercase tracking-[0.5em]" style={{ color: theme.accent }}>A CURATED EXPERIENCE</p>
                            <h1 className="mt-3 font-serif text-4xl font-medium leading-tight sm:text-6xl">{menu.name}</h1>
                            <p className="mx-auto mt-4 max-w-lg text-xs leading-7 text-white/50">A refined selection of dishes, crafted with intention and served with detail.</p>
                        </div>
                    </div>
                </section>

                <section id="fine-dining-menu" className="scroll-mt-24 px-4 pt-10 sm:px-6">
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
                        {(current?.products ?? []).map((product: any) => <button key={product.id} type="button" disabled={!product.is_available} onClick={() => open(product)} className="nx-editorial group flex w-full items-center gap-4 py-5 text-right transition disabled:opacity-35 sm:gap-6 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#f0dfae]/70">
                            <div className="order-2 h-24 w-24 shrink-0 overflow-hidden ring-1 ring-white/10 shadow-[0_12px_34px_rgba(0,0,0,.35)] sm:h-32 sm:w-32" style={{ borderRadius }}>
                                <Image src={imageUrl(product.image_path) ?? fallbackFineImage(product.id)} alt={product.name} className="h-full w-full object-cover transition duration-700 group-hover:scale-105" />
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

            <div className="hidden">
                <div className="pointer-events-auto mx-auto flex max-w-3xl items-center gap-3 border border-white/10 bg-[#0d0c0a]/95 p-2 shadow-2xl backdrop-blur-xl" style={{ borderRadius }}>
                    <div className="flex min-w-0 flex-1 items-center gap-3 px-2"><span className="grid h-10 w-10 shrink-0 place-items-center border border-white/10" style={{ borderRadius, color: theme.accent }}>🛒</span><div><p className="text-[8px] uppercase tracking-[0.25em] text-white/25">Your selection</p><p className="text-xs font-medium">السلة جاهزة للطلب</p></div></div>
                    <button type="button" onClick={() => window.dispatchEvent(new Event('nexora:open-cart'))} className="px-5 py-3 text-xs font-bold" style={{ backgroundColor: theme.accent, color: '#0b0907', borderRadius }}>عرض الطلب</button>
                </div>
            </div>

            {selected && <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/80 backdrop-blur-md sm:items-center sm:p-5" onClick={close}>
                <div role="dialog" aria-modal="true" className="max-h-[92vh] w-full max-w-2xl overflow-y-auto border border-white/10 bg-[#0d0c0a] shadow-2xl sm:max-h-[88vh]" style={{ borderRadius: borderRadius + ' ' + borderRadius + ' 0 0' }} onClick={e => e.stopPropagation()}>
                    <div className="relative"><div className="aspect-[1.4]"><Image src={imageUrl(selected.image_path) ?? fallbackFineImage(selected.id)} alt={selected.name} className="h-full w-full object-cover" /></div><div className="absolute inset-0 bg-gradient-to-t from-[#0d0c0a] via-transparent to-black/10" /><button type="button" onClick={close} aria-label="إغلاق" className="absolute right-4 top-4 grid h-9 w-9 place-items-center rounded-full bg-black/50 text-white">×</button></div>
                    <div className="p-6 sm:p-8">
                        <div className="flex items-start justify-between gap-6"><div><p className="text-[8px] uppercase tracking-[0.35em]" style={{ color: theme.accent }}>Chef selection</p><h2 className="mt-2 font-serif text-3xl">{selected.name}</h2><p className="mt-3 text-xs leading-7 text-white/45">{selected.description}</p></div><strong className="shrink-0 font-serif text-lg" style={{ color: theme.accent }}>{money(price)}</strong></div>
                        {selected.variants?.filter((v: any) => v.is_active !== false).length > 0 && <div className="mt-7"><h3 className="mb-3 text-xs font-bold uppercase tracking-widest text-white/50">Choose</h3><div className="grid gap-2 sm:grid-cols-3">{selected.variants.filter((v: any) => v.is_active !== false).map((v: any) => <button key={v.id} type="button" onClick={() => setVariant(variant === v.id ? null : v.id)} className="border p-3 text-right" style={{ borderRadius, borderColor: variant === v.id ? theme.accent : 'rgba(255,255,255,.08)', backgroundColor: variant === v.id ? theme.accent + '10' : 'rgba(255,255,255,.02)' }}><span className="block text-xs">{v.name}</span><span className="mt-1 block text-[10px]" style={{ color: theme.accent }}>{v.price ? money(v.price) : (Number(v.price_delta ?? 0) >= 0 ? '+' : '') + money(v.price_delta ?? 0)}</span></button>)}</div></div>}
                        {selected.modifiers?.filter((m: any) => m.is_active).length > 0 && <div className="mt-7"><h3 className="mb-3 text-xs font-bold uppercase tracking-widest text-white/50">لمستك الخاصة</h3><div className="space-y-2">{selected.modifiers.filter((m: any) => m.is_active).map((m: any) => { const checked = modifierIds.includes(m.id); return <button type="button" key={m.id} aria-pressed={checked} onClick={() => { setModifierIds(ids => toggleModifierSelection(selected.modifiers ?? [], ids, m)); setModifierError(''); }} className="flex w-full items-center justify-between border p-3 text-right transition" style={{ borderRadius, borderColor: checked ? theme.accent : 'rgba(255,255,255,.1)', backgroundColor: checked ? theme.accent + '12' : 'transparent' }}><span className="flex items-center gap-3 text-xs"><span className="grid h-5 w-5 place-items-center border" style={{ borderColor: checked ? theme.accent : 'rgba(255,255,255,.25)', backgroundColor: checked ? theme.accent : 'transparent', color: '#0b0907' }}>{checked ? '✓' : ''}</span><span>{m.name}{m.group_name && <span className="block text-[9px] opacity-50">{m.group_name} · {m.group_min_choices ?? 0}–{m.group_max_choices ?? 1}</span>}</span></span><span className="text-xs" style={{ color: theme.accent }}>+{money(m.price_delta)}</span></button>; })}</div>{modifierError && <p role="alert" className="mt-2 text-xs font-bold text-rose-400">{modifierError}</p>}</div>}
                        <div className="mt-8 flex gap-3"><div className="flex items-center border border-white/10 p-1" style={{ borderRadius }}><button type="button" onClick={() => setQuantity(Math.max(1, quantity - 1))} className="h-10 w-10">−</button><span className="w-8 text-center text-sm">{quantity}</span><button type="button" disabled={quantity >= 30} onClick={() => setQuantity(Math.min(30, quantity + 1))} className="h-10 w-10 disabled:opacity-30">+</button></div><button type="button" onClick={addSelectedToOrder} className="flex-1 py-3.5 text-xs font-bold" style={{ backgroundColor: theme.accent, color: '#0b0907', borderRadius }}>إضافة للطلب · {money((price + (selected.modifiers ?? []).filter((m: any) => modifierIds.includes(m.id)).reduce((sum: number, m: any) => sum + Number(m.price_delta ?? 0), 0)) * quantity)}</button></div>
                    </div>
                </div>
            </div>}
        </div>
        </>
    );
}
