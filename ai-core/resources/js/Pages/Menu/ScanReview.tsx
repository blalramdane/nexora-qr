import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type Confidence = 'high' | 'medium' | 'low';
type MenuVariant = { name: string; price: number | string | null };
type ScannedProduct = {
    name: string;
    description: string | null;
    price: number | string | null;
    confidence: Confidence;
    variants: MenuVariant[];
};
type ScannedCategory = { name: string; products: ScannedProduct[] };
type ScanDraft = { categories: ScannedCategory[] };
type MenuSummary = { id: number; name: string };

const blankProduct = (): ScannedProduct => ({ name: '', description: '', price: '', confidence: 'low', variants: [] });
const moneyValueIsValid = (value: number | string | null) => value !== null && String(value).trim() !== '' && Number.isFinite(Number(value)) && Number(value) >= 0;
const confidenceLabel: Record<Confidence, string> = { high: 'قراءة واضحة', medium: 'راجع البيانات', low: 'محتاج مراجعة دقيقة' };

export default function ScanReview({ menu, draft: initialDraft, managementBase, menusIndexUrl }: {
    menu: MenuSummary;
    draft: ScanDraft;
    managementBase: string;
    menusIndexUrl: string;
}) {
    const [draft, setDraft] = useState<ScanDraft>(initialDraft);
    const [error, setError] = useState('');
    const [processing, setProcessing] = useState(false);
    const productsCount = useMemo(() => draft.categories.reduce((sum, category) => sum + category.products.length, 0), [draft]);
    const missingPrices = useMemo(() => draft.categories.some(category => category.products.some(product =>
        !product.name.trim() || !moneyValueIsValid(product.price) || product.variants.some(variant => !variant.name.trim() || !moneyValueIsValid(variant.price)))), [draft]);

    const updateCategory = (categoryIndex: number, changes: Partial<ScannedCategory>) => setDraft(current => ({
        ...current,
        categories: current.categories.map((category, index) => index === categoryIndex ? { ...category, ...changes } : category),
    }));

    const updateProduct = (categoryIndex: number, productIndex: number, changes: Partial<ScannedProduct>) => setDraft(current => ({
        ...current,
        categories: current.categories.map((category, index) => index !== categoryIndex ? category : {
            ...category,
            products: category.products.map((product, itemIndex) => itemIndex === productIndex ? { ...product, ...changes } : product),
        }),
    }));

    const updateVariant = (categoryIndex: number, productIndex: number, variantIndex: number, changes: Partial<MenuVariant>) => setDraft(current => ({
        ...current,
        categories: current.categories.map((category, index) => index !== categoryIndex ? category : {
            ...category,
            products: category.products.map((product, itemIndex) => itemIndex !== productIndex ? product : {
                ...product,
                variants: product.variants.map((variant, optionIndex) => optionIndex === variantIndex ? { ...variant, ...changes } : variant),
            }),
        }),
    }));

    const applyDraft = () => {
        setError('');
        setProcessing(true);
        router.post(`${managementBase}/scan/apply`, { categories: draft.categories }, {
            preserveScroll: true,
            onError: errors => setError(Object.values(errors).filter(Boolean).join(' ')),
            onFinish: () => setProcessing(false),
        });
    };

    return <main dir="rtl" className="min-h-screen bg-slate-50 px-4 py-7 text-slate-950 sm:px-8">
        <Head title={`مراجعة منيو · ${menu.name}`} />
        <div className="mx-auto max-w-6xl">
            <header className="mb-6 rounded-3xl bg-slate-950 p-5 text-white shadow-xl sm:p-7">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-xs font-black tracking-widest text-emerald-300">NEXORA MENU SCANNER</p>
                        <h1 className="mt-2 text-2xl font-black sm:text-3xl">مراجعة الأصناف المستخرجة</h1>
                        <p className="mt-2 text-sm leading-6 text-slate-300">{menu.name} · راجع كل سعر، وصحح أي قراءة غير دقيقة قبل الاعتماد.</p>
                    </div>
                    <Link href={`${managementBase}/import`} className="rounded-xl border border-white/20 px-4 py-2.5 text-sm font-bold transition hover:bg-white/10">إلغاء والعودة</Link>
                </div>
                <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div className="rounded-2xl border border-white/10 bg-white/5 p-4"><p className="text-xs text-slate-400">الأقسام</p><p className="mt-1 text-2xl font-black">{draft.categories.length}</p></div>
                    <div className="rounded-2xl border border-white/10 bg-white/5 p-4"><p className="text-xs text-slate-400">الأصناف</p><p className="mt-1 text-2xl font-black">{productsCount}</p></div>
                    <div className="col-span-2 rounded-2xl border border-amber-300/20 bg-amber-300/10 p-4 sm:col-span-1"><p className="text-xs text-amber-100">شرط قبل الاعتماد</p><p className="mt-1 text-sm font-bold">كل اسم وسعر لازم يكون صحيحًا</p></div>
                </div>
            </header>

            <div role="note" className="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                دي مسودة وليست بيانات مؤكدة. لو السعر مش مقروء، اكتبه يدويًا أو احذف الصنف/الحجم غير المطلوب. المكونات غير الموجودة في الصور، مثل صور المنتجات، لا يتم اختراعها.
            </div>
            {error && <p role="alert" className="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-800">{error}</p>}

            <div className="space-y-6">
                {draft.categories.map((category, categoryIndex) => <section key={`${categoryIndex}-${category.name}`} className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 p-4 sm:p-5">
                        <label className="min-w-[220px] flex-1 text-xs font-bold text-slate-500">اسم القسم
                            <input value={category.name} onChange={event => updateCategory(categoryIndex, { name: event.target.value })} maxLength={120} className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-base font-black text-slate-950 outline-none focus:border-emerald-600" />
                        </label>
                        <button type="button" onClick={() => setDraft(current => ({ ...current, categories: current.categories.filter((_, index) => index !== categoryIndex) }))} className="rounded-xl border border-rose-200 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50">حذف القسم</button>
                    </div>

                    <div className="space-y-4 p-4 sm:p-5">
                        {category.products.map((product, productIndex) => <article key={`${categoryIndex}-${productIndex}`} className="rounded-2xl border border-slate-200 p-4">
                            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <span className={`rounded-full px-3 py-1 text-[11px] font-black ${product.confidence === 'high' ? 'bg-emerald-50 text-emerald-700' : product.confidence === 'medium' ? 'bg-amber-50 text-amber-800' : 'bg-rose-50 text-rose-700'}`}>{confidenceLabel[product.confidence]}</span>
                                <button type="button" onClick={() => updateCategory(categoryIndex, { products: category.products.filter((_, index) => index !== productIndex) })} className="rounded-lg px-2 py-1 text-xs font-bold text-rose-700 hover:bg-rose-50">حذف الصنف</button>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <label className="text-xs font-bold text-slate-500 sm:col-span-2">اسم الصنف
                                    <input value={product.name} onChange={event => updateProduct(categoryIndex, productIndex, { name: event.target.value })} maxLength={180} className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-950 outline-none focus:border-emerald-600" />
                                </label>
                                <label className="text-xs font-bold text-slate-500 sm:col-span-2 lg:col-span-1">السعر الأساسي (جنيه)
                                    <input type="number" min="0" step="0.01" value={product.price ?? ''} onChange={event => updateProduct(categoryIndex, productIndex, { price: event.target.value })} className={`mt-1.5 w-full rounded-xl border px-3 py-2.5 text-sm font-black text-slate-950 outline-none focus:border-emerald-600 ${moneyValueIsValid(product.price) ? 'border-slate-200' : 'border-rose-400 bg-rose-50'}`} />
                                </label>
                                <label className="text-xs font-bold text-slate-500 sm:col-span-2 lg:col-span-1">الوصف (اختياري)
                                    <input value={product.description ?? ''} onChange={event => updateProduct(categoryIndex, productIndex, { description: event.target.value })} maxLength={1000} className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal text-slate-950 outline-none focus:border-emerald-600" placeholder="اكتب وصفًا عند الحاجة" />
                                </label>
                            </div>

                            <div className="mt-4 rounded-xl bg-slate-50 p-3">
                                <div className="mb-3 flex flex-wrap items-center justify-between gap-2"><h3 className="text-sm font-black">الأحجام والاختيارات ذات السعر المستقل</h3><button type="button" onClick={() => updateProduct(categoryIndex, productIndex, { variants: [...product.variants, { name: '', price: '' }] })} className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold">+ إضافة حجم</button></div>
                                {product.variants.length === 0 ? <p className="text-xs text-slate-500">لا توجد أحجام مستخرجة لهذا الصنف.</p> : <div className="space-y-2">
                                    {product.variants.map((variant, variantIndex) => <div key={`${variantIndex}-${variant.name}`} className="grid gap-2 sm:grid-cols-[1fr_180px_auto]">
                                        <input aria-label="اسم الحجم" value={variant.name} onChange={event => updateVariant(categoryIndex, productIndex, variantIndex, { name: event.target.value })} maxLength={100} placeholder="مثال: كبير" className="rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-emerald-600" />
                                        <input aria-label="سعر الحجم" type="number" min="0" step="0.01" value={variant.price ?? ''} onChange={event => updateVariant(categoryIndex, productIndex, variantIndex, { price: event.target.value })} placeholder="السعر بالجنيه" className={`rounded-lg border px-3 py-2 text-sm outline-none focus:border-emerald-600 ${moneyValueIsValid(variant.price) ? 'border-slate-200' : 'border-rose-400 bg-rose-50'}`} />
                                        <button type="button" onClick={() => updateProduct(categoryIndex, productIndex, { variants: product.variants.filter((_, index) => index !== variantIndex) })} className="rounded-lg px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50">حذف</button>
                                    </div>)}
                                </div>}
                            </div>
                        </article>)}
                        <button type="button" onClick={() => updateCategory(categoryIndex, { products: [...category.products, blankProduct()] })} className="w-full rounded-xl border border-dashed border-slate-300 px-4 py-3 text-sm font-bold text-slate-700 transition hover:border-emerald-500 hover:bg-emerald-50">+ إضافة صنف يدويًا للقسم</button>
                    </div>
                </section>)}

                <button type="button" onClick={() => setDraft(current => ({ ...current, categories: [...current.categories, { name: '', products: [] }] }))} className="w-full rounded-2xl border border-dashed border-slate-300 bg-white p-4 text-sm font-black text-slate-700 transition hover:border-emerald-500 hover:bg-emerald-50">+ إضافة قسم جديد</button>
            </div>

            <footer className="sticky bottom-0 z-20 mt-7 rounded-t-2xl border border-slate-200 bg-white/95 p-4 shadow-2xl backdrop-blur sm:rounded-2xl">
                {missingPrices && <p className="mb-3 text-xs font-bold text-rose-700">راجع أسماء وأسعار المنتجات والأحجام. أي سعر فارغ أو غير صحيح يمنع الاعتماد.</p>}
                {productsCount < 1 && <p className="mb-3 text-xs font-bold text-rose-700">أضف صنفًا واحدًا على الأقل قبل الاعتماد.</p>}
                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <Link href={menusIndexUrl} className="rounded-xl border border-slate-200 px-5 py-3 text-center text-sm font-bold text-slate-700">الرجوع للمنيوهات</Link>
                    <button type="button" disabled={processing || missingPrices || productsCount < 1 || draft.categories.length === 0} onClick={applyDraft} className="rounded-xl bg-emerald-800 px-6 py-3 text-sm font-black text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40">{processing ? 'جارٍ حفظ المراجعة…' : `اعتماد وإضافة ${productsCount} صنف للمنيو`}</button>
                </div>
            </footer>
        </div>
    </main>;
}
