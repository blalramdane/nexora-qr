import React, { useEffect, useMemo, useState } from 'react';
import FastFoodTemplate from './FastFoodTemplate';
import CafeTemplate from './CafeTemplate';
import FineDiningTemplate from './FineDiningTemplate';
import ProductConfigurator, { requestProductConfiguration } from './ProductConfigurator';
import { useLocale } from '../../i18n/LocaleProvider';

type Product = {
    id: number; name: string; description?: string | null; price: string;
    image_path?: string | null; is_available: boolean; is_featured: boolean; dietary_tags?: string[] | null;
    variants?: { id: number; name: string; price?: string | null; price_delta?: string; is_active?: boolean }[];
    modifiers?: { id: number; name: string; price_delta: string; is_active: boolean; is_required: boolean; group_name?: string | null; group_min_choices?: number; group_max_choices?: number }[];
};
type Category = { id: number; name: string; slug: string; description?: string | null; products: Product[] };
type Theme = { primary: string; accent: string; background: string; foreground: string; radius: 'sm' | 'md' | 'lg' | 'xl' | '2xl' };
type TemplateSchema = { layout?: string; theme?: Theme; [key: string]: unknown };
type Menu = { name: string; categories: Category[]; theme?: Theme | null; template?: { key: string; name: string } | null; template_version?: { version: number; is_active: boolean; schema?: TemplateSchema } | null; templateVersion?: { version: number; is_active: boolean; schema?: TemplateSchema } | null };
type Props = { menu: Menu; restaurantName?: string; restaurantAddress?: string | null; preview?: boolean; previewCategoryId?: number; logoPath?: string | null; restaurantPhone?: string | null; restaurantWhatsappPhone?: string | null; dietaryFilter?: string[] };

const money = (value: string) => Number(value).toFixed(0) + ' ج.م';
const imageUrl = (path?: string | null) => path && (path.startsWith('http') || path.startsWith('/') || path.startsWith('blob:') || path.startsWith('data:')) ? path : path ? '/storage/' + path : null;

function ProductCard({ product, style, theme }: { product: Product; style: 'compact' | 'editorial' | 'featured'; theme: Theme }) {
    const { t } = useLocale();
    const hasOptions = (product.variants ?? []).some(variant => variant.is_active !== false) || (product.modifiers ?? []).some(modifier => modifier.is_active !== false);
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
            <button type="button" disabled={!product.is_available} onClick={() => requestProductConfiguration(product, theme)} style={{ backgroundColor: theme.primary, borderRadius: radius }} className="mt-4 w-full px-4 py-3 text-sm font-black text-white disabled:opacity-40">{product.is_available ? (hasOptions ? t('menu.chooseOptions') : t('menu.chooseQuantity')) : t('components.unavailable')}</button>
        </div>
    </article>;
}

export default function TemplateRenderer({ menu, restaurantName = 'NEXORA Restaurant', restaurantAddress, preview = false, previewCategoryId, logoPath, restaurantPhone, restaurantWhatsappPhone, dietaryFilter = [] }: Props) {
    const { t, locale, dir } = useLocale();
    const isDePapa = /de\s*papa|دي\s*بابا/i.test(`${restaurantName} ${menu.name}`);
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
    const [search, setSearch] = useState('');
    const visibleMenu = useMemo(() => {
        const query = search.trim().toLocaleLowerCase(locale === 'ar' ? 'ar-EG' : 'en-US');
        if (!query && dietaryFilter.length === 0) return menu;
        return {
            ...menu,
            categories: categories.map(category => ({
                ...category,
                products: category.products.filter(product => {
                    const matchesSearch = !query || `${product.name} ${product.description ?? ''} ${category.name}`.toLocaleLowerCase(locale === 'ar' ? 'ar-EG' : 'en-US').includes(query);
                    const productTags = product.dietary_tags ?? [];
                    const matchesDietary = dietaryFilter.every(tag => productTags.includes(tag));
                    return matchesSearch && matchesDietary;
                }),
            })).filter(category => category.products.length > 0),
        };
    }, [categories, menu, search, dietaryFilter, locale]);
    const storedKey = menu.template?.key || 'fast-food';
    const versionLayout = menu.template_version?.schema?.layout ?? menu.templateVersion?.schema?.layout;
    const layouts = ['fast-food', 'cafe', 'fine-dining'] as const;
    // Retired restaurant-specific keys remain compatible with existing menus,
    // but resolve to maintained reusable layouts instead of the minimal fallback.
    const legacyLayoutFallbacks: Record<string, 'fast-food' | 'cafe'> = {
        'al-saqa': 'fast-food',
        'al-agha': 'fast-food',
        'yum-box': 'fast-food',
        'panchy-fire': 'fast-food',
        'de-papa': 'cafe',
    };
    const legacyLayoutFallback = legacyLayoutFallbacks[storedKey];
    const key = (layouts as readonly string[]).includes(storedKey)
        ? storedKey
        : (layouts as readonly string[]).includes(String(versionLayout ?? ''))
            ? String(versionLayout)
            : legacyLayoutFallback ?? 'fast-food';
    // Laravel serializes Eloquent relationship names to snake_case in JSON.
    // Support camelCase too for the editor's in-memory preview objects.
    const version = Number(menu.template_version?.version ?? menu.templateVersion?.version ?? (preview ? 2 : 1));

    let renderedTemplate: React.ReactNode;
    if (key === 'fast-food' && (version >= 2 || versionLayout === 'hero-categories-menu-detail' || Boolean(legacyLayoutFallback))) {
        renderedTemplate = <FastFoodTemplate menu={visibleMenu} restaurantName={restaurantName} restaurantAddress={restaurantAddress} logoPath={logoPath} restaurantPhone={restaurantPhone} restaurantWhatsappPhone={restaurantWhatsappPhone} searchActive={Boolean(search.trim())} preview={preview} />;
    } else if (key === 'cafe' && (version >= 2 || versionLayout === key || Boolean(legacyLayoutFallback))) {
        renderedTemplate = <CafeTemplate menu={visibleMenu} restaurantName={restaurantName} logoPath={logoPath} restaurantPhone={restaurantPhone} restaurantWhatsappPhone={restaurantWhatsappPhone} searchActive={Boolean(search.trim())} preview={preview} />;
    } else if (key === 'fine-dining' && (version >= 2 || versionLayout === key)) {
        renderedTemplate = <FineDiningTemplate menu={visibleMenu} restaurantName={restaurantName} logoPath={logoPath} restaurantPhone={restaurantPhone} restaurantWhatsappPhone={restaurantWhatsappPhone} searchActive={Boolean(search.trim())} preview={preview} />;
    } else if (key === 'fine-dining') renderedTemplate = <div dir="rtl" style={{ ...themeStyle, backgroundColor: theme.background, color: theme.foreground }} className="min-h-screen">
        <header className="mx-auto max-w-5xl px-5 pb-10 pt-12 text-center">
            <p className="text-xs uppercase tracking-[0.35em] opacity-50">{preview ? 'NEXORA PREVIEW' : restaurantName}</p>
            <h1 className="mt-5 font-serif text-5xl">{menu.name}</h1>
            <p className="mx-auto mt-4 max-w-xl text-sm leading-7 opacity-60">قائمة مختارة بعناية، بتجربة هادئة تركز على المنتج.</p>
            <div className="mx-auto mt-8 flex gap-2 overflow-x-auto pb-2">{visibleMenu.categories.map(c => <button key={c.id} onClick={() => setActive(c.id)} className={'shrink-0 rounded-full border px-5 py-2 text-sm ' + (active === c.id ? 'border-[#e7c894] text-white' : 'border-white/15')}>{c.name}</button>)}</div>
        </header>
        <main className="mx-auto max-w-5xl px-5 pb-20"><section className="border-y border-white/10 py-10"><h2 className="font-serif text-3xl">{visibleMenu.categories.find(c => c.id === active)?.name ?? visibleMenu.categories[0]?.name ?? 'نتائج البحث'}</h2><div className="mt-8 grid gap-10 md:grid-cols-2">{(search.trim() ? visibleMenu.categories.flatMap(c => c.products) : visibleMenu.categories.find(c => c.id === active)?.products ?? visibleMenu.categories[0]?.products ?? []).map(p => <ProductCard key={p.id} product={p} style="editorial" theme={theme} />)}</div>{visibleMenu.categories.length === 0 && <p className="rounded-xl border border-dashed p-8 text-center text-sm">مفيش أصناف مطابقة للبحث.</p>}</section></main>
    </div>;
    else if (key === 'cafe') renderedTemplate = <div dir="rtl" style={{ ...themeStyle, backgroundColor: theme.background, color: theme.foreground }} className="min-h-screen">
        <header className="sticky top-0 z-20 border-b border-black/10 bg-[#f3ede4]/95 px-4 py-5 backdrop-blur">
            <div className="mx-auto flex max-w-6xl items-center justify-between"><div><p className="text-xs opacity-50">{restaurantName}</p><h1 className="text-2xl font-black">{menu.name}</h1></div><button type="button" onClick={() => window.dispatchEvent(new Event('nexora:open-cart'))} className="rounded-full bg-slate-950 px-4 py-2 text-sm font-bold text-white">عرض السلة</button></div>
            <div className="mx-auto mt-4 flex max-w-6xl gap-2 overflow-x-auto">{visibleMenu.categories.map(c => <button key={c.id} onClick={() => setActive(c.id)} style={{ backgroundColor: active === c.id ? theme.accent : undefined, color: active === c.id ? theme.foreground : undefined, borderRadius: radius }} className="shrink-0 px-4 py-2 text-sm font-bold bg-white/60">{c.name}</button>)}</div>
        </header>
        <main className="mx-auto max-w-6xl px-4 pb-24 pt-8"><h2 className="text-3xl font-black">{visibleMenu.categories.find(c => c.id === active)?.name ?? visibleMenu.categories[0]?.name ?? 'نتائج البحث'}</h2><div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{(search.trim() ? visibleMenu.categories.flatMap(c => c.products) : visibleMenu.categories.find(c => c.id === active)?.products ?? visibleMenu.categories[0]?.products ?? []).map(p => <ProductCard key={p.id} product={p} style="compact" theme={theme} />)}</div>{visibleMenu.categories.length === 0 && <p className="rounded-xl border border-dashed p-8 text-center text-sm">مفيش أصناف مطابقة للبحث.</p>}</main>
        <div style={{ backgroundColor: theme.primary, borderRadius: radius }} className="fixed inset-x-4 bottom-4 z-30 mx-auto max-w-md p-3 text-white shadow-2xl"><button type="button" onClick={() => window.dispatchEvent(new Event('nexora:open-cart'))} className="flex w-full justify-between px-3 py-2 text-sm font-bold"><span>ابدأ طلبك</span><span>عرض السلة</span></button></div>
    </div>;
    else {
    const featured = visibleMenu.categories.flatMap(c => c.products).filter(p => p.is_featured).slice(0, 4);
    renderedTemplate = <div dir="rtl" style={{ ...themeStyle, backgroundColor: theme.background, color: theme.foreground }} className="min-h-screen">
        <header className="text-white"><div className="mx-auto max-w-6xl px-4 pb-8 pt-6"><div className="flex items-center justify-between"><div><p className="text-xs font-bold text-amber-400">{restaurantName}</p><h1 className="mt-1 text-3xl font-black">{menu.name}</h1></div><span className="rounded-full bg-white/10 px-4 py-2 text-xs">QR MENU</span></div>{featured.length > 0 && <div className="mt-8 grid auto-cols-[82%] grid-flow-col gap-4 overflow-x-auto sm:auto-cols-[45%] lg:auto-cols-[24%]">{featured.map(p => <ProductCard key={p.id} product={p} style="featured" theme={theme} />)}</div>}</div></header>
        <main className="mx-auto max-w-6xl px-4 pb-24 pt-6"><div className="sticky top-0 z-10 -mx-4 flex gap-2 overflow-x-auto bg-[#faf7f2]/95 px-4 py-3 backdrop-blur">{visibleMenu.categories.map(c => <button key={c.id} onClick={() => setActive(c.id)} style={{ backgroundColor: active === c.id ? theme.primary : undefined, color: active === c.id ? '#fff' : undefined, borderRadius: radius }} className="shrink-0 px-4 py-2 text-sm font-black bg-white shadow-sm">{c.name}</button>)}</div><section className="pt-6"><h2 className="text-3xl font-black">{visibleMenu.categories.find(c => c.id === active)?.name ?? visibleMenu.categories[0]?.name ?? 'نتائج البحث'}</h2><div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{(search.trim() ? visibleMenu.categories.flatMap(c => c.products) : visibleMenu.categories.find(c => c.id === active)?.products ?? visibleMenu.categories[0]?.products ?? []).map(p => <ProductCard key={p.id} product={p} style="compact" theme={theme} />)}</div>{visibleMenu.categories.length === 0 && <p className="rounded-2xl border border-dashed p-8 text-center text-sm">مفيش أصناف مطابقة للبحث.</p>}</section></main>
    </div>;
    }

    return <>
        {!preview && <div dir={dir} className={`sticky top-0 z-[80] border-b px-4 py-2.5 backdrop-blur ${isDePapa ? 'border-[#d6a45b]/20 bg-[#100a07]/95 text-[#f7ecdc] shadow-[0_10px_28px_rgba(0,0,0,.3)]' : 'border-slate-200 bg-white/95 shadow-sm'}`} role="search">
            <div className={`mx-auto flex max-w-4xl items-center gap-2 rounded-xl border px-3 py-2 focus-within:border-slate-400 ${isDePapa ? 'border-[#d6a45b]/20 bg-white/[0.04]' : 'border-slate-200 bg-slate-50'}`}>
                <span aria-hidden="true" className={`text-lg ${isDePapa ? 'text-[#d6a45b]/70' : 'text-slate-400'}`}>⌕</span>
                <input type="search" value={search} onChange={event => setSearch(event.target.value)} placeholder={t('menu.search')} aria-label={t('menu.search')} className={`min-w-0 flex-1 bg-transparent text-sm outline-none ${isDePapa ? 'text-white placeholder:text-white/35' : ''}`} />
                {search && <button type="button" onClick={() => setSearch('')} className={`rounded-lg px-2 py-1 text-xs font-bold ${isDePapa ? 'text-[#f5e7cf]/70 hover:text-white' : 'text-slate-600'}`}>{t('common.clear')}</button>}
                <span className={`whitespace-nowrap text-[10px] ${isDePapa ? 'text-[#f5e7cf]/45' : 'text-slate-400'}`}>{visibleMenu.categories.reduce((sum, category) => sum + category.products.length, 0)} {t('menu.itemsCount')}</span>
            </div>
        </div>}
        {!preview && (search.trim() || dietaryFilter.length > 0) && visibleMenu.categories.length === 0 && <div dir={dir} role="status" className="sticky top-[62px] z-[75] mx-auto max-w-4xl border-b border-amber-200 bg-amber-50 px-4 py-4 text-slate-900 shadow-sm"><p className="font-black">{t('menu.noMatches')}</p><p className="mt-1 text-sm text-slate-600">{t('menu.tryAnotherSearch')}</p><button type="button" onClick={() => setSearch('')} className="mt-3 rounded-lg bg-slate-950 px-4 py-2 text-sm font-bold text-white">{t('menu.clearSearch')}</button></div>}
        {renderedTemplate}
        <ProductConfigurator />
    </>;
}
