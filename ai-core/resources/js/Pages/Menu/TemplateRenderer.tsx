import React, { useEffect, useState } from 'react';
import FastFoodTemplate from './FastFoodTemplate';
import CafeTemplate from './CafeTemplate';
import FineDiningTemplate from './FineDiningTemplate';

type Product = {
    id: number; name: string; description?: string | null; price: string;
    image_path?: string | null; is_available: boolean; is_featured: boolean;
    variants?: { id: number; name: string; price?: string | null; price_delta?: string }[];
    modifiers?: { id: number; name: string; price_delta: string; is_active: boolean; is_required: boolean; group_name?: string | null; group_min_choices?: number; group_max_choices?: number }[];
};
type Category = { id: number; name: string; description?: string | null; products: Product[] };
type Theme = { primary: string; accent: string; background: string; foreground: string; radius: 'sm' | 'md' | 'lg' | 'xl' | '2xl' };
type Menu = { name: string; categories: Category[]; theme?: Theme | null; template?: { key: string; name: string } | null; template_version?: { version: number; is_active: boolean } | null; templateVersion?: { version: number; is_active: boolean } | null };
type Props = { menu: Menu; restaurantName?: string; preview?: boolean; previewCategoryId?: number };

const money = (value: string) => Number(value).toFixed(0) + ' ج.م';
const imageUrl = (path?: string | null) => path && (path.startsWith('http') || path.startsWith('/')) ? path : path ? '/storage/' + path : null;

function ProductCard({ product, style, theme }: { product: Product; style: 'compact' | 'editorial' | 'featured'; theme: Theme }) {
    const [expanded, setExpanded] = useState(false);
    const [selectedVariant, setSelectedVariant] = useState<number | null>(null);
    const [selectedModifiers, setSelectedModifiers] = useState<number[]>([]);
    const [choiceError, setChoiceError] = useState('');
    const variants = product.variants ?? [];
    const modifiers = (product.modifiers ?? []).filter(modifier => modifier.is_active);
    const groups = Array.from(new Set(modifiers.map(modifier => modifier.group_name).filter((name): name is string => Boolean(name))));
    const selectedVariantRecord = variants.find(variant => variant.id === selectedVariant);
    const price = (selectedVariantRecord?.price != null ? Number(selectedVariantRecord.price) : Number(product.price) + Number(selectedVariantRecord?.price_delta ?? 0)) + modifiers.filter(modifier => selectedModifiers.includes(modifier.id)).reduce((sum, modifier) => sum + Number(modifier.price_delta), 0);
    const toggleModifier = (id: number, groupName: string | null | undefined, maxChoices: number) => {
        setChoiceError('');
        setSelectedModifiers(current => {
            if (current.includes(id)) return current.filter(existing => existing !== id);
            const inGroup = modifiers.filter(modifier => (modifier.group_name ?? null) === (groupName ?? null));
            const groupIds = new Set(inGroup.map(modifier => modifier.id));
            const currentGroup = current.filter(existing => groupIds.has(existing));
            if (currentGroup.length >= maxChoices) {
                if (groupName && maxChoices === 1) return [...current.filter(existing => !groupIds.has(existing)), id];
                setChoiceError(`يمكن اختيار ${maxChoices} من مجموعة ${groupName || 'الإضافات'}.`);
                return current;
            }
            return [...current, id];
        });
    };
    const addToCart = () => {
        for (const groupName of groups) {
            const options = modifiers.filter(modifier => modifier.group_name === groupName);
            const minimum = Math.max(...options.map(option => Number(option.group_min_choices ?? 0)));
            const maximum = Math.max(1, Math.min(...options.map(option => Number(option.group_max_choices ?? 1))));
            const chosen = options.filter(option => selectedModifiers.includes(option.id)).length;
            if (chosen < minimum || chosen > maximum) { setChoiceError(`اختار من ${minimum} إلى ${maximum} من مجموعة ${groupName}.`); return; }
        }
        const ungroupedRequiredMissing = modifiers.some(modifier => !modifier.group_name && modifier.is_required && !selectedModifiers.includes(modifier.id));
        if (ungroupedRequiredMissing) { setChoiceError('اختار كل الإضافات المطلوبة.'); return; }
        window.dispatchEvent(new CustomEvent('nexora:add-to-cart', { detail: { productId: product.id, name: product.name, price, variantId: selectedVariant, variantName: selectedVariantRecord?.name, modifiers: modifiers.filter(modifier => selectedModifiers.includes(modifier.id)).map(modifier => ({ id: modifier.id, name: modifier.name, price: Number(modifier.price_delta) })), quantity: 1 } }));
        setChoiceError('');
    };
    const shell = style === 'featured' ? 'border-white/10 bg-white/5 text-white' : style === 'editorial' ? 'border-black/10 bg-white' : 'border-black/10 bg-white';
    const radius = ({ sm: '0.5rem', md: '0.75rem', lg: '1rem', xl: '1.25rem', '2xl': '1.75rem' } as const)[theme.radius];
    return <article style={{ borderRadius: radius }} className={'group overflow-hidden border transition ' + shell + (product.is_available ? '' : ' opacity-50')}>
        <div className="aspect-[1.35] overflow-hidden bg-black/5">
            {imageUrl(product.image_path) ? <img src={imageUrl(product.image_path) as string} alt={product.name} className="h-full w-full object-cover transition duration-500 group-hover:scale-105" /> : <div className="flex h-full items-center justify-center bg-gradient-to-br from-black/5 to-black/10 text-4xl">🍽️</div>}
        </div>
        <div className="p-4">
            <div className="flex items-start justify-between gap-3">
                <div><h3 className="font-black">{product.name}</h3>{product.description && <p className="mt-1 text-sm opacity-60">{product.description}</p>}</div>
                <strong className="shrink-0">{money(product.price)}</strong>
            </div>
            {(variants.length > 0 || modifiers.length > 0) && <button onClick={() => setExpanded(!expanded)} className="mt-3 text-xs font-bold underline">{expanded ? 'إخفاء الاختيارات' : 'تخصيص الطلب'}</button>}
            {expanded && <div className="mt-3 space-y-3 border-t border-current/10 pt-3">
                {variants.length > 0 && <fieldset><legend className="mb-2 text-xs font-black">الحجم / الاختيار</legend>{variants.map(variant => <label key={variant.id} className="flex items-center justify-between gap-2 py-1.5 text-sm"><span className="flex items-center gap-2"><input type="radio" name={`variant-${product.id}`} checked={selectedVariant === variant.id} onChange={() => setSelectedVariant(variant.id)} />{variant.name}</span><span>{variant.price != null ? money(variant.price) : `${Number(product.price) + Number(variant.price_delta ?? 0)} ج.م`}</span></label>)}</fieldset>}
                {groups.map(groupName => { const options = modifiers.filter(modifier => modifier.group_name === groupName); const minimum = Math.max(...options.map(option => Number(option.group_min_choices ?? 0))); const maximum = Math.max(1, Math.min(...options.map(option => Number(option.group_max_choices ?? 1)))); return <fieldset key={groupName}><legend className="mb-1 text-xs font-black">{groupName} <span className="font-normal opacity-60">(اختار {minimum}–{maximum})</span></legend>{options.map(option => <label key={option.id} className="flex items-center justify-between gap-2 py-1.5 text-sm"><span className="flex items-center gap-2"><input type={maximum === 1 ? 'radio' : 'checkbox'} name={`modifier-${product.id}-${groupName}`} checked={selectedModifiers.includes(option.id)} onChange={() => toggleModifier(option.id, groupName, maximum)} />{option.name}</span><span>{Number(option.price_delta) > 0 ? `+${Number(option.price_delta).toFixed(2)} ج.م` : `${Number(option.price_delta).toFixed(2)} ج.م`}</span></label>)}</fieldset>; })}
                {modifiers.filter(modifier => !modifier.group_name).length > 0 && <fieldset><legend className="mb-1 text-xs font-black">إضافات</legend>{modifiers.filter(modifier => !modifier.group_name).map(option => <label key={option.id} className="flex items-center justify-between gap-2 py-1.5 text-sm"><span className="flex items-center gap-2"><input type="checkbox" checked={selectedModifiers.includes(option.id)} onChange={() => toggleModifier(option.id, null, 20)} />{option.name}{option.is_required && <span className="text-rose-600">*</span>}</span><span>{Number(option.price_delta) > 0 ? `+${Number(option.price_delta).toFixed(2)} ج.م` : `${Number(option.price_delta).toFixed(2)} ج.م`}</span></label>)}</fieldset>}
                {choiceError && <p role="alert" className="text-xs font-bold text-rose-600">{choiceError}</p>}
                <p className="text-sm font-black">السعر بعد الاختيارات: {price.toFixed(2)} ج.م</p>
            </div>}
            <button type="button" disabled={!product.is_available} onClick={addToCart} style={{ backgroundColor: theme.primary, borderRadius: radius }} className="mt-4 w-full px-4 py-3 text-sm font-black text-white disabled:opacity-40">{product.is_available ? 'إضافة للطلب' : 'غير متاح حالياً'}</button>
        </div>
    </article>;
}

export default function TemplateRenderer({ menu, restaurantName = 'NEXORA Restaurant', preview = false, previewCategoryId }: Props) {
    const theme: Theme = menu.theme ?? { primary: '#111827', accent: '#f59e0b', background: '#ffffff', foreground: '#111827', radius: 'xl' };
    const radius = ({ sm: '0.5rem', md: '0.75rem', lg: '1rem', xl: '1.25rem', '2xl': '1.75rem' } as const)[theme.radius];
    const themeStyle = { '--menu-primary': theme.primary, '--menu-accent': theme.accent, '--menu-background': theme.background, '--menu-foreground': theme.foreground, '--menu-radius': radius } as React.CSSProperties;
    const [active, setActive] = useState(previewCategoryId ?? menu.categories[0]?.id);
    const categories = menu.categories;

    useEffect(() => {
        if (previewCategoryId && menu.categories.some((category) => category.id === previewCategoryId)) {
            setActive(previewCategoryId);
        }
    }, [previewCategoryId, menu.categories]);
    const current = categories.find(c => c.id === active) || categories[0];
    const key = menu.template?.key || 'fast-food';
    // Laravel serializes Eloquent relationship names to snake_case in JSON.
    // Support camelCase too for the editor's in-memory preview objects.
    const version = Number(menu.template_version?.version ?? menu.templateVersion?.version ?? (preview ? 2 : 1));

    if (key === 'fast-food' && version >= 2) {
        return <FastFoodTemplate menu={menu} restaurantName={restaurantName} preview={preview} />;
    }

    if (key === 'cafe' && version >= 2) {
        return <CafeTemplate menu={menu} restaurantName={restaurantName} preview={preview} />;
    }

    if (key === 'fine-dining' && version >= 2) {
        return <FineDiningTemplate menu={menu} restaurantName={restaurantName} preview={preview} />;
    }

    if (key === 'fine-dining') return <div dir="rtl" style={{ ...themeStyle, backgroundColor: theme.background, color: theme.foreground }} className="min-h-screen">
        <header className="mx-auto max-w-5xl px-5 pb-10 pt-12 text-center">
            <p className="text-xs uppercase tracking-[0.35em] opacity-50">{preview ? 'NEXORA PREVIEW' : restaurantName}</p>
            <h1 className="mt-5 font-serif text-5xl">{menu.name}</h1>
            <p className="mx-auto mt-4 max-w-xl text-sm leading-7 opacity-60">قائمة مختارة بعناية، بتجربة هادئة تركز على المنتج.</p>
            <div className="mx-auto mt-8 flex gap-2 overflow-x-auto pb-2">{categories.map(c => <button key={c.id} onClick={() => setActive(c.id)} className={'shrink-0 rounded-full border px-5 py-2 text-sm ' + (active === c.id ? 'border-[#e7c894] text-white' : 'border-white/15')}>{c.name}</button>)}</div>
        </header>
        <main className="mx-auto max-w-5xl px-5 pb-20"><section className="border-y border-white/10 py-10"><h2 className="font-serif text-3xl">{current?.name}</h2><p className="mt-2 text-sm opacity-50">{current?.description}</p><div className="mt-8 grid gap-10 md:grid-cols-2">{current?.products.map(p => <ProductCard key={p.id} product={p} style="editorial" theme={theme} />)}</div></section></main>
    </div>;

    if (key === 'cafe') return <div dir="rtl" style={{ ...themeStyle, backgroundColor: theme.background, color: theme.foreground }} className="min-h-screen">
        <header className="sticky top-0 z-20 border-b border-black/10 bg-[#f3ede4]/95 px-4 py-5 backdrop-blur">
            <div className="mx-auto flex max-w-6xl items-center justify-between"><div><p className="text-xs opacity-50">{restaurantName}</p><h1 className="text-2xl font-black">{menu.name}</h1></div><button className="rounded-full text-white px-4 py-2 text-sm font-bold">السلة ٠</button></div>
            <div className="mx-auto mt-4 flex max-w-6xl gap-2 overflow-x-auto">{categories.map(c => <button key={c.id} onClick={() => setActive(c.id)} style={{ backgroundColor: active === c.id ? theme.accent : undefined, color: active === c.id ? theme.foreground : undefined, borderRadius: radius }} className="shrink-0 px-4 py-2 text-sm font-bold bg-white/60">{c.name}</button>)}</div>
        </header>
        <main className="mx-auto max-w-6xl px-4 pb-24 pt-8"><h2 className="text-3xl font-black">{current?.name}</h2><p className="mt-1 text-sm opacity-60">{current?.description}</p><div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{current?.products.map(p => <ProductCard key={p.id} product={p} style="compact" theme={theme} />)}</div></main>
        <div style={{ backgroundColor: theme.primary, borderRadius: radius }} className="fixed inset-x-4 bottom-4 z-30 mx-auto max-w-md p-3 text-white shadow-2xl"><button className="flex w-full justify-between px-3 py-2 text-sm font-bold"><span>ابدأ طلبك</span><span>السلة فارغة</span></button></div>
    </div>;

    const featured = categories.flatMap(c => c.products).filter(p => p.is_featured).slice(0, 4);
    return <div dir="rtl" style={{ ...themeStyle, backgroundColor: theme.background, color: theme.foreground }} className="min-h-screen">
        <header className="text-white"><div className="mx-auto max-w-6xl px-4 pb-8 pt-6"><div className="flex items-center justify-between"><div><p className="text-xs font-bold text-amber-400">{restaurantName}</p><h1 className="mt-1 text-3xl font-black">{menu.name}</h1></div><span className="rounded-full bg-white/10 px-4 py-2 text-xs">QR MENU</span></div>{featured.length > 0 && <div className="mt-8 grid auto-cols-[82%] grid-flow-col gap-4 overflow-x-auto sm:auto-cols-[45%] lg:auto-cols-[24%]">{featured.map(p => <ProductCard key={p.id} product={p} style="featured" theme={theme} />)}</div>}</div></header>
        <main className="mx-auto max-w-6xl px-4 pb-24 pt-6"><div className="sticky top-0 z-10 -mx-4 flex gap-2 overflow-x-auto bg-[#faf7f2]/95 px-4 py-3 backdrop-blur">{categories.map(c => <button key={c.id} onClick={() => setActive(c.id)} style={{ backgroundColor: active === c.id ? theme.primary : undefined, color: active === c.id ? '#fff' : undefined, borderRadius: radius }} className="shrink-0 px-4 py-2 text-sm font-black bg-white shadow-sm">{c.name}</button>)}</div><section className="pt-6"><h2 className="text-3xl font-black">{current?.name}</h2><p className="mt-1 text-sm opacity-60">{current?.description}</p><div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{current?.products.map(p => <ProductCard key={p.id} product={p} style="compact" theme={theme} />)}</div></section></main>
    </div>;
}
