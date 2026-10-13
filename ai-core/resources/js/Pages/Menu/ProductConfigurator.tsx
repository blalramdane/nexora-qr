import { useEffect, useMemo, useState } from 'react';
import { modifierSelectionError, toggleModifierSelection } from './modifierChoices';

type Variant = { id: number; name: string; price?: string | number | null; price_delta?: string | number | null; is_active?: boolean };
type Modifier = { id: number; name: string; price_delta?: string | number | null; is_active?: boolean; is_required?: boolean; group_name?: string | null; group_min_choices?: number; group_max_choices?: number };
type Product = { id: number; name: string; description?: string | null; price: string | number; image_path?: string | null; is_available: boolean; variants?: Variant[]; modifiers?: Modifier[] };
type Theme = { primary?: string; accent?: string; background?: string; foreground?: string; radius?: string };
type ConfigurationRequest = { product: Product; theme?: Theme };

const money = (amount: number) => `${amount.toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ج.م`;
const activeVariants = (product: Product) => (product.variants ?? []).filter(variant => variant.is_active !== false);
const activeModifiers = (product: Product) => (product.modifiers ?? []).filter(modifier => modifier.is_active !== false);
const imageUrl = (path?: string | null) => path ? (path.startsWith('http') || path.startsWith('/') || path.startsWith('blob:') || path.startsWith('data:') ? path : `/storage/${path}`) : null;
const coffeeFallbackImage = 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?auto=format&fit=crop&w=1200&q=85';

export function requestProductConfiguration(product: Product, theme?: Theme) {
    if (!product.is_available) return;
    window.dispatchEvent(new CustomEvent<ConfigurationRequest>('nexora:configure-product', { detail: { product, theme } }));
}

export default function ProductConfigurator() {
    const [request, setRequest] = useState<ConfigurationRequest | null>(null);
    const [variantId, setVariantId] = useState<number | null>(null);
    const [modifierIds, setModifierIds] = useState<number[]>([]);
    const [quantity, setQuantity] = useState(1);
    const [error, setError] = useState('');
    const [imageFailed, setImageFailed] = useState(false);
    const [fallbackImageFailed, setFallbackImageFailed] = useState(false);

    useEffect(() => {
        const open = (event: Event) => {
            const detail = (event as CustomEvent<ConfigurationRequest>).detail;
            if (!detail?.product?.id || !detail.product.is_available) return;
            setRequest(detail);
            setVariantId(null);
            setModifierIds([]);
            setQuantity(1);
            setError('');
            setImageFailed(false);
            setFallbackImageFailed(false);
        };
        const imageUpdated = (event: Event) => {
            const detail = (event as CustomEvent<{ productId: number; imagePath: string }>).detail;
            if (!detail?.productId || !detail.imagePath) return;
            setImageFailed(false);
            setFallbackImageFailed(false);
            setRequest(current => {
                if (!current || current.product.id !== detail.productId) return current;
                return { ...current, product: { ...current.product, image_path: detail.imagePath } };
            });
        };
        window.addEventListener('nexora:configure-product', open);
        window.addEventListener('nexora:product-image-updated', imageUpdated);
        return () => {
            window.removeEventListener('nexora:configure-product', open);
            window.removeEventListener('nexora:product-image-updated', imageUpdated);
        };
    }, []);

    const product = request?.product;
    const theme = request?.theme ?? {};
    const productImage = imageUrl(product?.image_path);
    const coffeeProduct = /espresso|coffee|latte|cappuccino|إسبريسو|اسبريسو|قهوة|كابتشينو|لاتيه/i.test(product?.name ?? '');
    const variants = useMemo(() => product ? activeVariants(product) : [], [product]);
    const modifiers = useMemo(() => product ? activeModifiers(product) : [], [product]);
    const selectedVariant = variants.find(variant => variant.id === variantId);
    const variantPrice = selectedVariant
        ? selectedVariant.price !== null && selectedVariant.price !== undefined && selectedVariant.price !== ''
            ? Number(selectedVariant.price)
            : Number(product?.price ?? 0) + Number(selectedVariant.price_delta ?? 0)
        : Number(product?.price ?? 0);
    const selectedModifiers = modifiers.filter(modifier => modifierIds.includes(modifier.id));
    const unitPrice = variantPrice + selectedModifiers.reduce((total, modifier) => total + Number(modifier.price_delta ?? 0), 0);
    const lineTotal = unitPrice * quantity;
    const radius = ({ sm: '0.5rem', md: '0.75rem', lg: '1rem', xl: '1.25rem', '2xl': '1.75rem' } as Record<string, string>)[theme.radius ?? 'xl'] ?? '1.25rem';
    const primary = theme.primary ?? '#111827';
    const accent = theme.accent ?? '#d6a45b';

    const close = () => {
        setRequest(null);
        setVariantId(null);
        setModifierIds([]);
        setQuantity(1);
        setError('');
    };

    const addToCart = () => {
        if (!product) return;
        if (variants.length > 0 && !selectedVariant) {
            setError('اختار الحجم أو الاختيار الأول.');
            return;
        }
        const modifierError = modifierSelectionError(modifiers, modifierIds);
        if (modifierError) {
            setError(modifierError);
            return;
        }
        if (!Number.isFinite(unitPrice) || unitPrice < 0) {
            setError('السعر بعد الاختيارات غير صالح. راجع سعر الصنف والإضافات.');
            return;
        }
        window.dispatchEvent(new CustomEvent('nexora:add-to-cart', {
            detail: {
                productId: product.id,
                name: product.name,
                price: unitPrice,
                variantId: selectedVariant?.id ?? null,
                variantName: selectedVariant?.name,
                modifiers: selectedModifiers.map(modifier => ({ id: modifier.id, name: modifier.name, price: Number(modifier.price_delta ?? 0) })),
                quantity,
            },
        }));
        close();
        window.dispatchEvent(new Event('nexora:open-cart'));
    };

    if (!product) return null;

    return <div className="fixed inset-0 z-[90] flex items-end justify-center bg-black/75 p-0 backdrop-blur-sm sm:items-center sm:p-4" role="presentation" onMouseDown={event => { if (event.target === event.currentTarget) close(); }}>
        <section dir="rtl" role="dialog" aria-modal="true" aria-labelledby="nexora-configurator-title" className="max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-t-3xl bg-white text-slate-950 shadow-2xl sm:rounded-3xl" style={{ borderRadius: ` ${radius} ${radius} 0 0` }}>
            <div className="relative overflow-hidden bg-[#f1ece4]">
                {productImage && !imageFailed ? <img key={`${product.id}-${product.image_path}`} src={productImage} alt={product.name} loading="eager" decoding="async" onError={() => setImageFailed(true)} className="h-44 w-full object-cover sm:h-52" />
                    : coffeeProduct && !fallbackImageFailed ? <img key={`${product.id}-coffee-fallback`} src={coffeeFallbackImage} alt={`صورة قهوة بديلة — ${product.name}`} loading="eager" decoding="async" onError={() => setFallbackImageFailed(true)} className="h-44 w-full object-cover sm:h-52" />
                        : <div className="flex h-44 flex-col items-center justify-center bg-[radial-gradient(circle_at_50%_10%,#fffaf2_0%,#f1e7d7_58%,#e4d2b7_100%)] text-[#594837] sm:h-52" role="img" aria-label={productImage ? `صورة ${product.name} غير متاحة` : `لا توجد صورة لـ ${product.name}`}>
                    <span aria-hidden="true" className="grid h-16 w-16 place-items-center rounded-2xl border border-white/80 bg-white/70 text-4xl shadow-sm">{coffeeProduct ? '☕' : '🍽️'}</span>
                    <span className="mt-3 text-sm font-black">{productImage && imageFailed ? 'الصورة غير متاحة حاليًا' : 'لا توجد صورة لهذا المنتج'}</span>
                    <span className="mt-1 text-[11px] text-[#806f5d]">يمكن لصاحب المطعم رفع صورة بديلة من لوحة الإدارة</span>
                </div>}
                <button type="button" onClick={close} aria-label="إغلاق خيارات الصنف" className="absolute left-3 top-3 grid h-10 w-10 place-items-center rounded-full bg-black/70 text-xl text-white">×</button>
            </div>
            <div className="space-y-5 p-5 sm:p-6">
                <div className="flex items-start justify-between gap-4"><div className="min-w-0"><h2 id="nexora-configurator-title" className="text-2xl font-black">{product.name}</h2>{product.description && <p className="mt-2 text-sm leading-6 text-slate-500">{product.description}</p>}</div><strong className="shrink-0 text-lg" style={{ color: primary }}>{money(unitPrice)}</strong></div>

                {variants.length > 0 && <fieldset className="space-y-2"><legend className="mb-2 text-sm font-black">اختار الحجم أو النوع <span className="text-rose-600">*</span></legend>{variants.map(variant => {
                    const price = variant.price !== null && variant.price !== undefined && variant.price !== '' ? Number(variant.price) : Number(product.price) + Number(variant.price_delta ?? 0);
                    return <label key={variant.id} className="flex cursor-pointer items-center justify-between gap-3 rounded-xl border p-3" style={{ borderColor: variantId === variant.id ? primary : '#e2e8f0', backgroundColor: variantId === variant.id ? `${primary}0d` : '#fff', borderRadius: radius }}><span className="flex items-center gap-2"><input type="radio" name={`product-variant-${product.id}`} value={variant.id} checked={variantId === variant.id} onChange={() => { setVariantId(variant.id); setError(''); }} />{variant.name}</span><strong>{money(price)}</strong></label>;
                })}</fieldset>}

                {modifiers.length > 0 && <fieldset className="space-y-2"><legend className="mb-2 text-sm font-black">الإضافات والتخصيص</legend>{[...new Set(modifiers.map(modifier => modifier.group_name?.trim()).filter((name): name is string => Boolean(name)))].map(group => {
                    const options = modifiers.filter(modifier => modifier.group_name?.trim() === group);
                    const maximum = Math.max(1, Math.min(...options.map(option => Number(option.group_max_choices ?? 1))));
                    const minimum = Math.max(...options.map(option => Number(option.group_min_choices ?? 0)));
                    return <div key={group} className="rounded-xl border border-slate-200 p-3"><p className="mb-2 text-xs font-black">{group} <span className="font-normal text-slate-500">(اختار من {minimum} إلى {maximum})</span></p>{options.map(option => <label key={option.id} className="flex cursor-pointer items-center justify-between gap-3 py-2 text-sm"><span className="flex items-center gap-2"><input type={maximum === 1 ? 'radio' : 'checkbox'} name={`product-modifier-${product.id}-${group}`} checked={modifierIds.includes(option.id)} onChange={() => { setModifierIds(current => toggleModifierSelection(modifiers, current, option)); setError(''); }} />{option.name}</span><span className="whitespace-nowrap">{Number(option.price_delta ?? 0) > 0 ? '+' : ''}{money(Number(option.price_delta ?? 0))}</span></label>)}</div>;
                })}
                {modifiers.filter(modifier => !modifier.group_name?.trim()).map(modifier => <label key={modifier.id} className="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-slate-200 p-3 text-sm"><span className="flex items-center gap-2"><input type="checkbox" checked={modifierIds.includes(modifier.id)} onChange={() => { setModifierIds(current => toggleModifierSelection(modifiers, current, modifier)); setError(''); }} />{modifier.name}{modifier.is_required && <span className="text-rose-600">مطلوب</span>}</span><span className="whitespace-nowrap">{Number(modifier.price_delta ?? 0) > 0 ? '+' : ''}{money(Number(modifier.price_delta ?? 0))}</span></label>)}</fieldset>}

                <div className="flex items-center justify-between rounded-xl bg-slate-50 p-3"><span className="text-sm font-bold">الكمية</span><div className="flex items-center gap-4"><button type="button" aria-label="تقليل الكمية" disabled={quantity <= 1} onClick={() => setQuantity(current => Math.max(1, current - 1))} className="grid h-9 w-9 place-items-center rounded-lg border bg-white disabled:opacity-40">−</button><span className="min-w-5 text-center font-black">{quantity}</span><button type="button" aria-label="زيادة الكمية" disabled={quantity >= 30} onClick={() => setQuantity(current => Math.min(30, current + 1))} className="grid h-9 w-9 place-items-center rounded-lg border bg-white disabled:opacity-40">+</button></div></div>
                {error && <p role="alert" className="rounded-xl bg-rose-50 p-3 text-sm font-bold text-rose-700">{error}</p>}
                <button type="button" onClick={addToCart} className="w-full rounded-xl px-4 py-4 text-base font-black text-white shadow-lg" style={{ backgroundColor: primary, borderRadius: radius }}>إضافة للطلب · {money(lineTotal)}</button>
                <p className="text-center text-[11px] text-slate-500">السعر النهائي بيتراجع ويتحسب تاني على السيرفر وقت تأكيد الطلب.</p>
                <span className="hidden" aria-hidden="true" style={{ color: accent }} />
            </div>
        </section>
    </div>;
}
