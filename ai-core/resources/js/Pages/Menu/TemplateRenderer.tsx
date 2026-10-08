import { useState } from 'react';

type Product = {
    id: number; name: string; description?: string | null; price: string;
    image_path?: string | null; is_available: boolean; is_featured: boolean;
    variants?: { id: number; name: string; price?: string | null; price_delta?: string }[];
};
type Category = { id: number; name: string; description?: string | null; products: Product[] };
type Menu = { name: string; categories: Category[]; template?: { key: string; name: string } | null };
type Props = { menu: Menu; restaurantName?: string; preview?: boolean };

const money = (value: string) => Number(value).toFixed(0) + ' ج.م';

function ProductCard({ product, style }: { product: Product; style: 'compact' | 'editorial' | 'featured' }) {
    const [expanded, setExpanded] = useState(false);
    const shell = style === 'featured' ? 'rounded-3xl border-white/10 bg-white/5 text-white' : style === 'editorial' ? 'rounded-[2rem] border-black/10 bg-white' : 'rounded-2xl border-black/10 bg-white';
    return <article className={'group overflow-hidden border transition ' + shell + (product.is_available ? '' : ' opacity-50')}>
        <div className="aspect-[1.35] overflow-hidden bg-black/5">
            {product.image_path ? <img src={product.image_path} alt={product.name} className="h-full w-full object-cover transition duration-500 group-hover:scale-105" /> : <div className="flex h-full items-center justify-center bg-gradient-to-br from-black/5 to-black/10 text-4xl">🍽️</div>}
        </div>
        <div className="p-4">
            <div className="flex items-start justify-between gap-3">
                <div><h3 className="font-black">{product.name}</h3>{product.description && <p className="mt-1 text-sm opacity-60">{product.description}</p>}</div>
                <strong className="shrink-0">{money(product.price)}</strong>
            </div>
            {product.variants && product.variants.length > 0 && <button onClick={() => setExpanded(!expanded)} className="mt-3 text-xs font-bold underline">{expanded ? 'إخفاء الاختيارات' : 'عرض الاختيارات'}</button>}
            {expanded && <div className="mt-3 space-y-2 border-t border-current/10 pt-3">{product.variants?.map(v => <div key={v.id} className="flex justify-between text-sm"><span>{v.name}</span><span>{v.price ? money(v.price) : v.price_delta ? '+' + money(v.price_delta) : 'بدون تغيير'}</span></div>)}</div>}
            <button disabled={!product.is_available} className="mt-4 w-full rounded-xl bg-black px-4 py-3 text-sm font-black text-white disabled:opacity-40">{product.is_available ? 'إضافة للطلب' : 'غير متاح حالياً'}</button>
        </div>
    </article>;
}

export default function TemplateRenderer({ menu, restaurantName = 'NEXORA Restaurant', preview = false }: Props) {
    const [active, setActive] = useState(menu.categories[0]?.id);
    const categories = menu.categories;
    const current = categories.find(c => c.id === active) || categories[0];
    const key = menu.template?.key || 'fast-food';

    if (key === 'fine-dining') return <div dir="rtl" className="min-h-screen bg-[#17130f] text-[#f8efe2]">
        <header className="mx-auto max-w-5xl px-5 pb-10 pt-12 text-center">
            <p className="text-xs uppercase tracking-[0.35em] opacity-50">{preview ? 'NEXORA PREVIEW' : restaurantName}</p>
            <h1 className="mt-5 font-serif text-5xl">{menu.name}</h1>
            <p className="mx-auto mt-4 max-w-xl text-sm leading-7 opacity-60">قائمة مختارة بعناية، بتجربة هادئة تركز على المنتج.</p>
            <div className="mx-auto mt-8 flex gap-2 overflow-x-auto pb-2">{categories.map(c => <button key={c.id} onClick={() => setActive(c.id)} className={'shrink-0 rounded-full border px-5 py-2 text-sm ' + (active === c.id ? 'border-[#e7c894] bg-[#e7c894] text-[#17130f]' : 'border-white/15')}>{c.name}</button>)}</div>
        </header>
        <main className="mx-auto max-w-5xl px-5 pb-20"><section className="border-y border-white/10 py-10"><h2 className="font-serif text-3xl">{current?.name}</h2><p className="mt-2 text-sm opacity-50">{current?.description}</p><div className="mt-8 grid gap-10 md:grid-cols-2">{current?.products.map(p => <ProductCard key={p.id} product={p} style="editorial" />)}</div></section></main>
    </div>;

    if (key === 'cafe') return <div dir="rtl" className="min-h-screen bg-[#f3ede4] text-[#2d241e]">
        <header className="sticky top-0 z-20 border-b border-black/10 bg-[#f3ede4]/95 px-4 py-5 backdrop-blur">
            <div className="mx-auto flex max-w-6xl items-center justify-between"><div><p className="text-xs opacity-50">{restaurantName}</p><h1 className="text-2xl font-black">{menu.name}</h1></div><button className="rounded-full bg-[#2d241e] px-4 py-2 text-sm font-bold text-white">السلة ٠</button></div>
            <div className="mx-auto mt-4 flex max-w-6xl gap-2 overflow-x-auto">{categories.map(c => <button key={c.id} onClick={() => setActive(c.id)} className={'shrink-0 rounded-full px-4 py-2 text-sm font-bold ' + (active === c.id ? 'bg-[#d59a61] text-white' : 'bg-white/60')}>{c.name}</button>)}</div>
        </header>
        <main className="mx-auto max-w-6xl px-4 pb-24 pt-8"><h2 className="text-3xl font-black">{current?.name}</h2><p className="mt-1 text-sm opacity-60">{current?.description}</p><div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{current?.products.map(p => <ProductCard key={p.id} product={p} style="compact" />)}</div></main>
        <div className="fixed inset-x-4 bottom-4 z-30 mx-auto max-w-md rounded-2xl bg-[#2d241e] p-3 text-white shadow-2xl"><button className="flex w-full justify-between px-3 py-2 text-sm font-bold"><span>ابدأ طلبك</span><span>السلة فارغة</span></button></div>
    </div>;

    const featured = categories.flatMap(c => c.products).filter(p => p.is_featured).slice(0, 4);
    return <div dir="rtl" className="min-h-screen bg-[#faf7f2] text-[#171717]">
        <header className="bg-[#151515] text-white"><div className="mx-auto max-w-6xl px-4 pb-8 pt-6"><div className="flex items-center justify-between"><div><p className="text-xs font-bold text-amber-400">{restaurantName}</p><h1 className="mt-1 text-3xl font-black">{menu.name}</h1></div><span className="rounded-full bg-white/10 px-4 py-2 text-xs">QR MENU</span></div>{featured.length > 0 && <div className="mt-8 grid auto-cols-[82%] grid-flow-col gap-4 overflow-x-auto sm:auto-cols-[45%] lg:auto-cols-[24%]">{featured.map(p => <ProductCard key={p.id} product={p} style="featured" />)}</div>}</div></header>
        <main className="mx-auto max-w-6xl px-4 pb-24 pt-6"><div className="sticky top-0 z-10 -mx-4 flex gap-2 overflow-x-auto bg-[#faf7f2]/95 px-4 py-3 backdrop-blur">{categories.map(c => <button key={c.id} onClick={() => setActive(c.id)} className={'shrink-0 rounded-xl px-4 py-2 text-sm font-black ' + (active === c.id ? 'bg-[#151515] text-white' : 'bg-white shadow-sm')}>{c.name}</button>)}</div><section className="pt-6"><h2 className="text-3xl font-black">{current?.name}</h2><p className="mt-1 text-sm opacity-60">{current?.description}</p><div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{current?.products.map(p => <ProductCard key={p.id} product={p} style="compact" />)}</div></section></main>
    </div>;
}
