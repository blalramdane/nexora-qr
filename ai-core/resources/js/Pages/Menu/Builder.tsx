import { Head, Link, router } from '@inertiajs/react';
import React, { FormEvent, useState } from 'react';
import TemplateRenderer from './TemplateRenderer';

type Template = { id: number; key: string; name: string };
type Category = {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    sort_order: number;
    is_active: boolean;
    products: Array<{ id: number; name: string; description?: string | null; price: string; image_path?: string | null; is_available: boolean; is_featured: boolean; sort_order: number; variants?: Array<{ id: number; name: string; price?: string | null; price_delta?: string; sort_order: number; is_active: boolean }>;
        modifiers?: Array<{ id: number; name: string; price_delta: string; sort_order: number; is_required: boolean; is_active: boolean }>;
    }>;
};
type RenderableMenu = Parameters<typeof TemplateRenderer>[0]['menu'];
type Menu = Omit<RenderableMenu, 'categories'> & {
    id: number;
    slug: string;
    is_published: boolean;
    template_id: number | null;
    theme: { primary: string; accent: string; background: string; foreground: string; radius: 'sm' | 'md' | 'lg' | 'xl' | '2xl' };
    categories: Category[];
};

export default function MenuBuilder({ menu, template, templates }: { menu: Menu; template: Template | null; templates: Template[] }) {
    const [name, setName] = useState(menu.name);
    const [templateKey, setTemplateKey] = useState(template?.key ?? '');
    const initialTheme = menu.theme ?? { primary: '#111827', accent: '#f59e0b', background: '#ffffff', foreground: '#111827', radius: 'xl' as const };
    const [themePrimary, setThemePrimary] = useState(initialTheme.primary);
    const [themeAccent, setThemeAccent] = useState(initialTheme.accent);
    const [themeBackground, setThemeBackground] = useState(initialTheme.background);
    const [themeForeground, setThemeForeground] = useState(initialTheme.foreground);
    const [themeRadius, setThemeRadius] = useState(initialTheme.radius);
    const [categoryName, setCategoryName] = useState('');
    const [categoryDescription, setCategoryDescription] = useState('');
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editingName, setEditingName] = useState('');
    const [editingDescription, setEditingDescription] = useState('');
    const [productCategoryId, setProductCategoryId] = useState<number | null>(null);
    const [productName, setProductName] = useState('');
    const [productDescription, setProductDescription] = useState('');
    const [productPrice, setProductPrice] = useState('');
    const [productImage, setProductImage] = useState('');
    const [productFeatured, setProductFeatured] = useState(false);
    const [editingProductId, setEditingProductId] = useState<number | null>(null);
    const [editingProductName, setEditingProductName] = useState('');
    const [editingProductPrice, setEditingProductPrice] = useState('');
    const [variantProductId, setVariantProductId] = useState<number | null>(null);
    const [variantName, setVariantName] = useState('');
    const [variantPricingMode, setVariantPricingMode] = useState<'fixed' | 'delta'>('fixed');
    const [variantPrice, setVariantPrice] = useState('');
    const [variantDelta, setVariantDelta] = useState('');
    const [editingVariantId, setEditingVariantId] = useState<number | null>(null);
    const [editingVariantName, setEditingVariantName] = useState('');
    const [editingVariantPricingMode, setEditingVariantPricingMode] = useState<'fixed' | 'delta'>('fixed');
    const [editingVariantPrice, setEditingVariantPrice] = useState('');
    const [editingVariantDelta, setEditingVariantDelta] = useState('');
    const [modifierProductId, setModifierProductId] = useState<number | null>(null);
    const [modifierName, setModifierName] = useState('');
    const [modifierDelta, setModifierDelta] = useState('');
    const [modifierRequired, setModifierRequired] = useState(false);
    const [editingModifierId, setEditingModifierId] = useState<number | null>(null);
    const [editingModifierName, setEditingModifierName] = useState('');
    const [editingModifierDelta, setEditingModifierDelta] = useState('');
    const [editingModifierRequired, setEditingModifierRequired] = useState(false);
    const [dragging, setDragging] = useState<{ type: 'category' | 'product' | 'variant' | 'modifier'; id: number; categoryId?: number; productId?: number } | null>(null);
    const [dragOver, setDragOver] = useState<{ type: 'category' | 'product' | 'variant' | 'modifier'; id: number } | null>(null);

    const save = (event: FormEvent) => {
        event.preventDefault();
        router.put('/menus/' + menu.id, { name, template_key: templateKey });
    };

    const saveTheme = (event: FormEvent) => {
        event.preventDefault();
        router.put('/menus/' + menu.id + '/theme', {
            primary: themePrimary,
            accent: themeAccent,
            background: themeBackground,
            foreground: themeForeground,
            radius: themeRadius,
        });
    };

    const remove = () => {
        if (window.confirm('حذف المنيو نهائيًا؟')) {
            router.delete('/menus/' + menu.id);
        }
    };

    const addCategory = (event: FormEvent) => {
        event.preventDefault();
        if (!categoryName.trim()) return;
        router.post('/menus/' + menu.id + '/categories', {
            name: categoryName,
            description: categoryDescription || null,
        }, {
            onSuccess: () => {
                setCategoryName('');
                setCategoryDescription('');
            },
        });
    };

    const startEditing = (category: Category) => {
        setEditingId(category.id);
        setEditingName(category.name);
        setEditingDescription(category.description ?? '');
    };

    const updateCategory = (event: FormEvent) => {
        event.preventDefault();
        if (!editingId || !editingName.trim()) return;
        router.put('/menus/' + menu.id + '/categories/' + editingId, {
            name: editingName,
            description: editingDescription || null,
            is_active: menu.categories.find(c => c.id === editingId)?.is_active ?? true,
        }, {
            onSuccess: () => setEditingId(null),
        });
    };

    const toggleCategory = (category: Category) => {
        router.put('/menus/' + menu.id + '/categories/' + category.id, {
            name: category.name,
            description: category.description ?? null,
            sort_order: category.sort_order,
            is_active: !category.is_active,
        });
    };

    const deleteCategory = (category: Category) => {
        if (window.confirm('حذف القسم؟ المنتجات المرتبطة به سيتم حذفها أيضًا.')) {
            router.delete('/menus/' + menu.id + '/categories/' + category.id);
        }
    };

    const addProduct = (event: FormEvent, categoryId: number) => {
        event.preventDefault();
        if (!productName.trim() || !productPrice) return;
        router.post('/menus/' + menu.id + '/categories/' + categoryId + '/products', {
            name: productName,
            description: productDescription || null,
            price: productPrice,
            image_path: productImage || null,
            is_featured: productFeatured,
        }, {
            onSuccess: () => {
                setProductName('');
                setProductDescription('');
                setProductPrice('');
                setProductImage('');
                setProductFeatured(false);
                setProductCategoryId(null);
            },
        });
    };

    const uploadProductImage = (event: React.ChangeEvent<HTMLInputElement>, categoryId: number, productId: number) => {
        const file = event.target.files?.[0];
        if (!file) return;
        router.post('/menus/' + menu.id + '/categories/' + categoryId + '/products/' + productId + '/image', { image: file }, {
            forceFormData: true,
        });
        event.target.value = '';
    };

    const removeProductImage = (categoryId: number, productId: number) => {
        if (window.confirm('حذف صورة المنتج؟')) {
            router.delete('/menus/' + menu.id + '/categories/' + categoryId + '/products/' + productId + '/image');
        }
    };

    const startEditingProduct = (product: Category['products'][number]) => {
        setEditingProductId(product.id);
        setEditingProductName(product.name);
        setEditingProductPrice(product.price);
    };

    const saveProduct = (event: FormEvent, categoryId: number, productId: number) => {
        event.preventDefault();
        if (!editingProductName.trim() || !editingProductPrice) return;
        const product = menu.categories.find(c => c.id === categoryId)?.products.find(p => p.id === productId);
        if (!product) return;
        router.put('/menus/' + menu.id + '/categories/' + categoryId + '/products/' + productId, {
            name: editingProductName,
            description: product.description ?? null,
            price: editingProductPrice,
            image_path: product.image_path ?? null,
            is_available: product.is_available,
            is_featured: product.is_featured,
            sort_order: product.sort_order,
        }, { onSuccess: () => setEditingProductId(null) });
    };

    const toggleProduct = (category: Category, product: Category['products'][number]) => {
        router.put('/menus/' + menu.id + '/categories/' + category.id + '/products/' + product.id, {
            name: product.name,
            description: product.description ?? null,
            price: product.price,
            image_path: product.image_path ?? null,
            is_available: !product.is_available,
            is_featured: product.is_featured,
            sort_order: product.sort_order,
        });
    };

    const deleteProduct = (category: Category, product: Category['products'][number]) => {
        if (window.confirm('حذف المنتج؟')) {
            router.delete('/menus/' + menu.id + '/categories/' + category.id + '/products/' + product.id);
        }
    };

    const moveProduct = (category: Category, index: number, direction: -1 | 1) => {
        const nextIndex = index + direction;
        if (nextIndex < 0 || nextIndex >= category.products.length) return;
        const ids = category.products.map(p => p.id);
        [ids[index], ids[nextIndex]] = [ids[nextIndex], ids[index]];
        router.post('/menus/' + menu.id + '/categories/' + category.id + '/products/reorder', { product_ids: ids });
    };

    const addVariant = (event: FormEvent, categoryId: number, productId: number) => {
        event.preventDefault();
        if (!variantName.trim()) return;
        if (variantPricingMode === 'fixed' && !variantPrice) return;
        if (variantPricingMode === 'delta' && !variantDelta) return;
        router.post('/menus/' + menu.id + '/categories/' + categoryId + '/products/' + productId + '/variants', {
            name: variantName,
            pricing_mode: variantPricingMode,
            price: variantPricingMode === 'fixed' ? variantPrice : null,
            price_delta: variantPricingMode === 'delta' ? variantDelta : null,
            is_active: true,
        }, { onSuccess: () => {
            setVariantName(''); setVariantPrice(''); setVariantDelta(''); setVariantPricingMode('fixed'); setVariantProductId(null);
        }});
    };

    const startEditingVariant = (variant: NonNullable<Category['products'][number]['variants']>[number]) => {
        setEditingVariantId(variant.id);
        setEditingVariantName(variant.name);
        if (variant.price !== null && variant.price !== undefined) {
            setEditingVariantPricingMode('fixed'); setEditingVariantPrice(variant.price); setEditingVariantDelta('');
        } else {
            setEditingVariantPricingMode('delta'); setEditingVariantPrice(''); setEditingVariantDelta(variant.price_delta ?? '0');
        }
    };

    const saveVariant = (event: FormEvent, categoryId: number, productId: number, variantId: number) => {
        event.preventDefault();
        if (!editingVariantName.trim()) return;
        if (editingVariantPricingMode === 'fixed' && !editingVariantPrice) return;
        if (editingVariantPricingMode === 'delta' && !editingVariantDelta) return;
        const product = menu.categories.find(c => c.id === categoryId)?.products.find(p => p.id === productId);
        const variant = product?.variants?.find(v => v.id === variantId);
        if (!variant) return;
        router.put('/menus/' + menu.id + '/categories/' + categoryId + '/products/' + productId + '/variants/' + variantId, {
            name: editingVariantName,
            pricing_mode: editingVariantPricingMode,
            price: editingVariantPricingMode === 'fixed' ? editingVariantPrice : null,
            price_delta: editingVariantPricingMode === 'delta' ? editingVariantDelta : null,
            is_active: variant.is_active,
            sort_order: variant.sort_order,
        }, { onSuccess: () => setEditingVariantId(null) });
    };

    const toggleVariant = (categoryId: number, productId: number, variant: NonNullable<Category['products'][number]['variants']>[number]) => {
        router.put('/menus/' + menu.id + '/categories/' + categoryId + '/products/' + productId + '/variants/' + variant.id, {
            name: variant.name,
            pricing_mode: variant.price !== null && variant.price !== undefined ? 'fixed' : 'delta',
            price: variant.price ?? null,
            price_delta: variant.price !== null && variant.price !== undefined ? null : variant.price_delta ?? '0',
            is_active: !variant.is_active,
            sort_order: variant.sort_order,
        });
    };

    const moveVariant = (categoryId: number, productId: number, variants: NonNullable<Category['products'][number]['variants']>, index: number, direction: -1 | 1) => {
        const nextIndex = index + direction;
        if (nextIndex < 0 || nextIndex >= variants.length) return;
        const ids = variants.map(v => v.id);
        [ids[index], ids[nextIndex]] = [ids[nextIndex], ids[index]];
        router.post('/menus/' + menu.id + '/categories/' + categoryId + '/products/' + productId + '/variants/reorder', { variant_ids: ids });
    };

    const deleteVariant = (categoryId: number, productId: number, variantId: number) => {
        if (window.confirm('حذف الـVariant؟')) router.delete('/menus/' + menu.id + '/categories/' + categoryId + '/products/' + productId + '/variants/' + variantId);
    };

    const finishDrag = () => {
        setDragging(null);
        setDragOver(null);
    };

    const reorderCategoriesByDrop = (targetId: number) => {
        if (!dragging || dragging.type !== 'category' || dragging.id === targetId) return finishDrag();
        const ids = menu.categories.map(c => c.id);
        const from = ids.indexOf(dragging.id);
        const to = ids.indexOf(targetId);
        if (from < 0 || to < 0) return finishDrag();
        ids.splice(from, 1);
        ids.splice(to, 0, dragging.id);
        router.post('/menus/' + menu.id + '/categories/reorder', { category_ids: ids }, { onFinish: finishDrag });
    };

    const reorderProductsByDrop = (category: Category, targetId: number) => {
        if (!dragging || dragging.type !== 'product' || dragging.categoryId !== category.id || dragging.id === targetId) return finishDrag();
        const ids = category.products.map(p => p.id);
        const from = ids.indexOf(dragging.id);
        const to = ids.indexOf(targetId);
        if (from < 0 || to < 0) return finishDrag();
        ids.splice(from, 1);
        ids.splice(to, 0, dragging.id);
        router.post('/menus/' + menu.id + '/categories/' + category.id + '/products/reorder', { product_ids: ids }, { onFinish: finishDrag });
    };

    const reorderVariantsByDrop = (categoryId: number, productId: number, variants: NonNullable<Category['products'][number]['variants']>, targetId: number) => {
        if (!dragging || dragging.type !== 'variant' || dragging.productId !== productId || dragging.id === targetId) return finishDrag();
        const ids = variants.map(v => v.id);
        const from = ids.indexOf(dragging.id);
        const to = ids.indexOf(targetId);
        if (from < 0 || to < 0) return finishDrag();
        ids.splice(from, 1);
        ids.splice(to, 0, dragging.id);
        router.post('/menus/' + menu.id + '/categories/' + categoryId + '/products/' + productId + '/variants/reorder', { variant_ids: ids }, { onFinish: finishDrag });
    };

    const reorderModifiersByDrop = (categoryId: number, productId: number, modifiers: NonNullable<Category['products'][number]['modifiers']>, targetId: number) => {
        if (!dragging || dragging.type !== 'modifier' || dragging.productId !== productId || dragging.id === targetId) return finishDrag();
        const ids = modifiers.map(m => m.id);
        const from = ids.indexOf(dragging.id);
        const to = ids.indexOf(targetId);
        if (from < 0 || to < 0) return finishDrag();
        ids.splice(from, 1);
        ids.splice(to, 0, dragging.id);
        router.post('/menus/' + menu.id + '/categories/' + categoryId + '/products/' + productId + '/modifiers/reorder', { modifier_ids: ids }, { onFinish: finishDrag });
    };

    const beginDrag = (event: React.DragEvent, type: 'category' | 'product' | 'variant' | 'modifier', id: number, categoryId?: number, productId?: number) => {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(id));
        setDragging({ type, id, categoryId, productId });
    };

    const allowDrop = (event: React.DragEvent, type: 'category' | 'product' | 'variant' | 'modifier', id: number) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        setDragOver({ type, id });
    };

    const moveCategory = (index: number, direction: -1 | 1) => {
        const nextIndex = index + direction;
        if (nextIndex < 0 || nextIndex >= menu.categories.length) return;
        const ids = menu.categories.map(c => c.id);
        [ids[index], ids[nextIndex]] = [ids[nextIndex], ids[index]];
        router.post('/menus/' + menu.id + '/categories/reorder', { category_ids: ids });
    };

    const addModifier = (event: FormEvent, categoryId: number, productId: number) => {
        event.preventDefault();
        if (!modifierName.trim() || !modifierDelta) return;
        router.post('/menus/' + menu.id + '/categories/' + categoryId + '/products/' + productId + '/modifiers', {
            name: modifierName,
            price_delta: modifierDelta,
            is_required: modifierRequired,
            is_active: true,
        }, { onSuccess: () => {
            setModifierName(''); setModifierDelta(''); setModifierRequired(false); setModifierProductId(null);
        }});
    };

    const startEditingModifier = (modifier: NonNullable<Category['products'][number]['modifiers']>[number]) => {
        setEditingModifierId(modifier.id);
        setEditingModifierName(modifier.name);
        setEditingModifierDelta(modifier.price_delta);
        setEditingModifierRequired(modifier.is_required);
    };

    const saveModifier = (event: FormEvent, categoryId: number, productId: number, modifierId: number) => {
        event.preventDefault();
        if (!editingModifierName.trim() || !editingModifierDelta) return;
        const product = menu.categories.find(c => c.id === categoryId)?.products.find(p => p.id === productId);
        const modifier = product?.modifiers?.find(m => m.id === modifierId);
        if (!modifier) return;
        router.put('/menus/' + menu.id + '/categories/' + categoryId + '/products/' + productId + '/modifiers/' + modifierId, {
            name: editingModifierName,
            price_delta: editingModifierDelta,
            is_required: editingModifierRequired,
            is_active: modifier.is_active,
            sort_order: modifier.sort_order,
        }, { onSuccess: () => setEditingModifierId(null) });
    };

    const toggleModifier = (categoryId: number, productId: number, modifier: NonNullable<Category['products'][number]['modifiers']>[number]) => {
        router.put('/menus/' + menu.id + '/categories/' + categoryId + '/products/' + productId + '/modifiers/' + modifier.id, {
            name: modifier.name,
            price_delta: modifier.price_delta,
            is_required: modifier.is_required,
            is_active: !modifier.is_active,
            sort_order: modifier.sort_order,
        });
    };

    const deleteModifier = (categoryId: number, productId: number, modifierId: number) => {
        if (window.confirm('حذف الـ Modifier؟')) {
            router.delete('/menus/' + menu.id + '/categories/' + categoryId + '/products/' + productId + '/modifiers/' + modifierId);
        }
    };

    const moveModifier = (categoryId: number, productId: number, modifiers: NonNullable<Category['products'][number]['modifiers']>, index: number, direction: -1 | 1) => {
        const nextIndex = index + direction;
        if (nextIndex < 0 || nextIndex >= modifiers.length) return;
        const ids = modifiers.map(m => m.id);
        [ids[index], ids[nextIndex]] = [ids[nextIndex], ids[index]];
        router.post('/menus/' + menu.id + '/categories/' + categoryId + '/products/' + productId + '/modifiers/reorder', { modifier_ids: ids });
    };

    return (
        <div dir="rtl" className="min-h-screen bg-slate-100">
            <Head title={'Builder · ' + menu.name} />
            <div className="border-b border-slate-200 bg-white px-4 py-3">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-4">
                    <div>
                        <span className="text-xs font-bold text-amber-600">MENU BUILDER</span>
                        <h1 className="font-black">{menu.name}</h1>
                    </div>
                    <Link href="/menus" className="text-sm font-bold underline">كل المنيوهات</Link>
                </div>
            </div>

            <main className="mx-auto grid max-w-7xl gap-6 p-4 lg:grid-cols-[24rem_1fr] lg:p-6">
                <aside className="space-y-4">
                    <section className="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="text-lg font-black">إعدادات المنيو</h2>
                        <form onSubmit={save} className="mt-5 space-y-4">
                            <label className="block">
                                <span className="mb-2 block text-sm font-bold">اسم المنيو</span>
                                <input value={name} onChange={(e) => setName(e.target.value)} maxLength={120} required className="w-full rounded-xl border border-slate-200 px-3 py-2 outline-none focus:border-amber-500" />
                            </label>
                            <label className="block">
                                <span className="mb-2 block text-sm font-bold">Template</span>
                                <select value={templateKey} onChange={(e) => setTemplateKey(e.target.value)} required className="w-full rounded-xl border border-slate-200 px-3 py-2">
                                    {templates.map((item) => <option key={item.id} value={item.key}>{item.name}</option>)}
                                </select>
                            </label>
                            <button type="submit" className="w-full rounded-xl bg-slate-900 px-4 py-2.5 font-bold text-white">حفظ التعديلات</button>
                        </form>
                        <button type="button" onClick={remove} className="mt-3 w-full rounded-xl border border-red-200 px-4 py-2.5 font-bold text-red-600">حذف المنيو</button>
                    </section>

                    <section className="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <div>
                                <span className="text-xs font-bold text-amber-600">THEME EDITOR</span>
                                <h2 className="mt-1 text-lg font-black">ألوان وشكل المنيو</h2>
                            </div>
                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">Live</span>
                        </div>
                        <form onSubmit={saveTheme} className="mt-4 space-y-3">
                            <div className="grid grid-cols-2 gap-2">
                                <label className="rounded-xl border border-slate-200 p-2"><span className="mb-1 block text-[10px] font-black text-slate-500">primary</span><div className="flex items-center gap-2"><input type="color" value={themePrimary} onChange={(e) => setThemePrimary(e.target.value)} className="h-9 w-10 rounded-lg border-0 bg-transparent p-0" /><input value={themePrimary} onChange={(e) => setThemePrimary(e.target.value)} maxLength={7} className="min-w-0 flex-1 rounded-lg border px-2 py-1.5 text-xs font-mono uppercase" /></div></label><label className="rounded-xl border border-slate-200 p-2"><span className="mb-1 block text-[10px] font-black text-slate-500">accent</span><div className="flex items-center gap-2"><input type="color" value={themeAccent} onChange={(e) => setThemeAccent(e.target.value)} className="h-9 w-10 rounded-lg border-0 bg-transparent p-0" /><input value={themeAccent} onChange={(e) => setThemeAccent(e.target.value)} maxLength={7} className="min-w-0 flex-1 rounded-lg border px-2 py-1.5 text-xs font-mono uppercase" /></div></label><label className="rounded-xl border border-slate-200 p-2"><span className="mb-1 block text-[10px] font-black text-slate-500">background</span><div className="flex items-center gap-2"><input type="color" value={themeBackground} onChange={(e) => setThemeBackground(e.target.value)} className="h-9 w-10 rounded-lg border-0 bg-transparent p-0" /><input value={themeBackground} onChange={(e) => setThemeBackground(e.target.value)} maxLength={7} className="min-w-0 flex-1 rounded-lg border px-2 py-1.5 text-xs font-mono uppercase" /></div></label><label className="rounded-xl border border-slate-200 p-2"><span className="mb-1 block text-[10px] font-black text-slate-500">foreground</span><div className="flex items-center gap-2"><input type="color" value={themeForeground} onChange={(e) => setThemeForeground(e.target.value)} className="h-9 w-10 rounded-lg border-0 bg-transparent p-0" /><input value={themeForeground} onChange={(e) => setThemeForeground(e.target.value)} maxLength={7} className="min-w-0 flex-1 rounded-lg border px-2 py-1.5 text-xs font-mono uppercase" /></div></label>
                            </div>
                            <label className="block"><span className="mb-1 block text-[10px] font-black text-slate-500">زوايا العناصر</span><select value={themeRadius} onChange={(e) => setThemeRadius(e.target.value as typeof themeRadius)} className="w-full rounded-xl border px-3 py-2 text-sm"><option value="sm">ناعمة</option><option value="md">متوسطة</option><option value="lg">دائرية</option><option value="xl">Premium</option><option value="2xl">Extra Round</option></select></label>
                            <button type="submit" className="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-black text-white">حفظ الـTheme</button>
                        </form>
                    </section>

                    <section className="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <div>
                                <span className="text-xs font-bold text-amber-600">CATEGORY BUILDER</span>
                                <h2 className="mt-1 text-lg font-black">أقسام المنيو</h2>
                            </div>
                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{menu.categories.length}</span>
                        </div>

                        <form onSubmit={addCategory} className="mt-4 space-y-2">
                            <input value={categoryName} onChange={(e) => setCategoryName(e.target.value)} placeholder="اسم القسم" maxLength={120} required className="w-full rounded-xl border border-slate-200 px-3 py-2 outline-none focus:border-amber-500" />
                            <textarea value={categoryDescription} onChange={(e) => setCategoryDescription(e.target.value)} placeholder="وصف اختياري" maxLength={1000} rows={2} className="w-full rounded-xl border border-slate-200 px-3 py-2 outline-none focus:border-amber-500" />
                            <button type="submit" className="w-full rounded-xl bg-amber-500 px-4 py-2.5 font-black text-white">+ إضافة قسم</button>
                        </form>

                        <div className="mt-5 space-y-2">
                            {menu.categories.map((category, index) => (
                                <div key={category.id} draggable onDragStart={(e) => beginDrag(e, 'category', category.id)} onDragOver={(e) => allowDrop(e, 'category', category.id)} onDrop={(e) => { e.preventDefault(); reorderCategoriesByDrop(category.id); }} onDragEnd={finishDrag} className={'rounded-2xl border p-3 cursor-grab active:cursor-grabbing ' + (dragOver?.type === 'category' && dragOver.id === category.id ? 'ring-2 ring-amber-400 ' : '') + (category.is_active ? 'border-slate-200 bg-slate-50' : 'border-dashed border-slate-300 bg-slate-100 opacity-70')}>
                                    {editingId === category.id ? (
                                        <form onSubmit={updateCategory} className="space-y-2">
                                            <input value={editingName} onChange={(e) => setEditingName(e.target.value)} maxLength={120} required className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2" />
                                            <textarea value={editingDescription} onChange={(e) => setEditingDescription(e.target.value)} maxLength={1000} rows={2} className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2" />
                                            <div className="flex gap-2">
                                                <button type="submit" className="flex-1 rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white">حفظ</button>
                                                <button type="button" onClick={() => setEditingId(null)} className="rounded-lg border px-3 py-2 text-xs font-bold">إلغاء</button>
                                            </div>
                                        </form>
                                    ) : (
                                        <>
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <span className="mb-1 inline-flex rounded-full bg-slate-200 px-2 py-0.5 text-[9px] font-black text-slate-500">⠿ اسحب لترتيب القسم</span>
                                                    <p className="font-black">{category.name}</p>
                                                    <p className="mt-0.5 text-xs text-slate-500">{category.products.length} منتج · {category.is_active ? 'نشط' : 'مخفي'}</p>
                                                </div>
                                                <div className="flex shrink-0 gap-1">
                                                    <button type="button" disabled={index === 0} onClick={() => moveCategory(index, -1)} className="rounded-lg border px-2 py-1 text-xs disabled:opacity-30">↑</button>
                                                    <button type="button" disabled={index === menu.categories.length - 1} onClick={() => moveCategory(index, 1)} className="rounded-lg border px-2 py-1 text-xs disabled:opacity-30">↓</button>
                                                </div>
                                            </div>
                                            <div className="mt-3 flex flex-wrap gap-2">
                                                <button type="button" onClick={() => startEditing(category)} className="rounded-lg border px-3 py-1.5 text-xs font-bold">تعديل</button>
                                                <button type="button" onClick={() => toggleCategory(category)} className="rounded-lg border px-3 py-1.5 text-xs font-bold">{category.is_active ? 'إخفاء' : 'تفعيل'}</button>
                                                <button type="button" onClick={() => deleteCategory(category)} className="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-bold text-red-600">حذف</button>
                                            </div>
                                        </>
                                    )}
                                </div>
                            ))}
                            {menu.categories.map((category) => (
                                <div key={'products-' + category.id} className="mt-4 rounded-2xl border border-slate-200 bg-white p-3">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-black text-slate-500">PRODUCTS · {category.name}</span>
                                        <button type="button" onClick={() => setProductCategoryId(productCategoryId === category.id ? null : category.id)} className="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-bold text-white">
                                            {productCategoryId === category.id ? 'إغلاق' : '+ منتج'}
                                        </button>
                                    </div>
                                    {productCategoryId === category.id && (
                                        <form onSubmit={(e) => addProduct(e, category.id)} className="mt-3 space-y-2">
                                            <input value={productName} onChange={(e) => setProductName(e.target.value)} placeholder="اسم المنتج" maxLength={160} required className="w-full rounded-lg border px-3 py-2" />
                                            <textarea value={productDescription} onChange={(e) => setProductDescription(e.target.value)} placeholder="الوصف" maxLength={2000} rows={2} className="w-full rounded-lg border px-3 py-2" />
                                            <div className="grid grid-cols-2 gap-2">
                                                <input value={productPrice} onChange={(e) => setProductPrice(e.target.value)} type="number" min="0" step="0.01" placeholder="السعر" required className="rounded-lg border px-3 py-2" />
                                                <input value={productImage} onChange={(e) => setProductImage(e.target.value)} placeholder="رابط الصورة" className="rounded-lg border px-3 py-2" />
                                            </div>
                                            <label className="flex items-center gap-2 text-xs font-bold"><input type="checkbox" checked={productFeatured} onChange={(e) => setProductFeatured(e.target.checked)} /> مميز</label>
                                            <button type="submit" className="w-full rounded-lg bg-amber-500 px-3 py-2 text-xs font-black text-white">إضافة المنتج</button>
                                        </form>
                                    )}
                                    <div className="mt-3 space-y-2">
                                        {category.products.map((product, index) => (
                                            <React.Fragment key={product.id}>
                                            <div draggable onDragStart={(e) => beginDrag(e, 'product', product.id, category.id)} onDragOver={(e) => allowDrop(e, 'product', product.id)} onDrop={(e) => { e.preventDefault(); reorderProductsByDrop(category, product.id); }} onDragEnd={finishDrag} className={'rounded-xl bg-slate-50 p-3 cursor-grab active:cursor-grabbing ' + (dragOver?.type === 'product' && dragOver.id === product.id ? 'ring-2 ring-amber-400 ' : '')}>
                                                {editingProductId === product.id ? (
                                                    <form onSubmit={(e) => saveProduct(e, category.id, product.id)} className="grid grid-cols-[1fr_7rem_auto] gap-2">
                                                        <input value={editingProductName} onChange={(e) => setEditingProductName(e.target.value)} required className="rounded-lg border px-2 py-1.5 text-sm" />
                                                        <input value={editingProductPrice} onChange={(e) => setEditingProductPrice(e.target.value)} type="number" min="0" step="0.01" required className="rounded-lg border px-2 py-1.5 text-sm" />
                                                        <button type="submit" className="rounded-lg bg-slate-900 px-3 text-xs font-bold text-white">حفظ</button>
                                                    </form>
                                                ) : (
                                                    <div className="space-y-2">
                                                        <div className="flex items-center gap-2">
                                                            {product.image_path ? (
                                                                <img src={product.image_path.startsWith('http') || product.image_path.startsWith('/') ? product.image_path : '/storage/' + product.image_path} alt="" className="h-12 w-12 shrink-0 rounded-lg object-cover" />
                                                            ) : (
                                                                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-slate-200 text-lg">🍽️</div>
                                                            )}
                                                            <div className="min-w-0 flex-1">
                                                                <span className="mb-1 inline-flex rounded-full bg-slate-200 px-2 py-0.5 text-[9px] font-black text-slate-500">⠿ اسحب المنتج</span>
                                                                <p className="truncate text-sm font-black">{product.name}</p>
                                                                <p className="text-xs text-slate-500">{Number(product.price).toFixed(2)} ج.م · {product.is_available ? 'متاح' : 'مخفي'}{product.is_featured ? ' · ⭐ مميز' : ''}</p>
                                                            </div>
                                                            <button type="button" disabled={index === 0} onClick={() => moveProduct(category, index, -1)} className="rounded border px-1.5 text-xs disabled:opacity-30">↑</button>
                                                            <button type="button" disabled={index === category.products.length - 1} onClick={() => moveProduct(category, index, 1)} className="rounded border px-1.5 text-xs disabled:opacity-30">↓</button>
                                                            <button type="button" onClick={() => startEditingProduct(product)} className="rounded border px-2 py-1 text-xs">تعديل</button>
                                                            <button type="button" onClick={() => toggleProduct(category, product)} className="rounded border px-2 py-1 text-xs">{product.is_available ? 'إخفاء' : 'تفعيل'}</button>
                                                            <button type="button" onClick={() => deleteProduct(category, product)} className="rounded border border-red-200 px-2 py-1 text-xs text-red-600">حذف</button>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <label className="cursor-pointer rounded-lg border border-dashed border-slate-300 px-2 py-1 text-[10px] font-bold hover:bg-white">
                                                                {product.image_path ? 'تغيير الصورة' : 'رفع صورة'}
                                                                <input type="file" accept="image/jpeg,image/png,image/webp" className="hidden" onChange={(e) => uploadProductImage(e, category.id, product.id)} />
                                                            </label>
                                                            {product.image_path && <button type="button" onClick={() => removeProductImage(category.id, product.id)} className="rounded-lg border border-red-200 px-2 py-1 text-[10px] font-bold text-red-600">حذف الصورة</button>}
                                                            <span className="text-[10px] text-slate-400">JPG / PNG / WebP · حتى 5MB</span>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                            <div className="mt-2 rounded-xl border border-slate-200 bg-white p-2">
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="text-[11px] font-black text-slate-500">VARIANTS · {product.variants?.length ?? 0}</span>
                                                    <button type="button" onClick={() => setVariantProductId(variantProductId === product.id ? null : product.id)} className="rounded-lg border px-2 py-1 text-[11px] font-bold">{variantProductId === product.id ? 'إغلاق' : '+ Variant'}</button>
                                                </div>
                                                {variantProductId === product.id && (
                                                    <form onSubmit={(e) => addVariant(e, category.id, product.id)} className="mt-2 space-y-2">
                                                        <input value={variantName} onChange={(e) => setVariantName(e.target.value)} placeholder="مثال: Large / Extra Cheese" maxLength={120} required className="w-full rounded-lg border px-2 py-1.5 text-xs" />
                                                        <div className="grid grid-cols-2 gap-2">
                                                            <select value={variantPricingMode} onChange={(e) => setVariantPricingMode(e.target.value as 'fixed' | 'delta')} className="rounded-lg border px-2 py-1.5 text-xs">
                                                                <option value="fixed">سعر ثابت</option><option value="delta">فرق عن السعر الأساسي</option>
                                                            </select>
                                                            {variantPricingMode === 'fixed'
                                                                ? <input value={variantPrice} onChange={(e) => setVariantPrice(e.target.value)} type="number" min="0" step="0.01" placeholder="السعر" required className="rounded-lg border px-2 py-1.5 text-xs" />
                                                                : <input value={variantDelta} onChange={(e) => setVariantDelta(e.target.value)} type="number" step="0.01" placeholder="+ / - من السعر" required className="rounded-lg border px-2 py-1.5 text-xs" />}
                                                        </div>
                                                        <button type="submit" className="w-full rounded-lg bg-amber-500 px-2 py-1.5 text-xs font-black text-white">إضافة Variant</button>
                                                    </form>
                                                )}
                                                <div className="mt-2 space-y-1.5">
                                                    {(product.variants ?? []).map((variant, variantIndex, variants) => (
                                                        <div key={variant.id} draggable onDragStart={(e) => beginDrag(e, 'variant', variant.id, category.id, product.id)} onDragOver={(e) => allowDrop(e, 'variant', variant.id)} onDrop={(e) => { e.preventDefault(); reorderVariantsByDrop(category.id, product.id, variants, variant.id); }} onDragEnd={finishDrag} className={'rounded-lg p-2 cursor-grab active:cursor-grabbing ' + (dragOver?.type === 'variant' && dragOver.id === variant.id ? 'ring-2 ring-amber-400 ' : '') + (variant.is_active ? 'bg-slate-50' : 'bg-slate-100 opacity-60')}>
                                                            {editingVariantId === variant.id ? (
                                                                <form onSubmit={(e) => saveVariant(e, category.id, product.id, variant.id)} className="space-y-2">
                                                                    <input value={editingVariantName} onChange={(e) => setEditingVariantName(e.target.value)} required className="w-full rounded border px-2 py-1 text-xs" />
                                                                    <div className="grid grid-cols-2 gap-2">
                                                                        <select value={editingVariantPricingMode} onChange={(e) => setEditingVariantPricingMode(e.target.value as 'fixed' | 'delta')} className="rounded border px-2 py-1 text-xs"><option value="fixed">سعر ثابت</option><option value="delta">فرق</option></select>
                                                                        {editingVariantPricingMode === 'fixed'
                                                                            ? <input value={editingVariantPrice} onChange={(e) => setEditingVariantPrice(e.target.value)} type="number" min="0" step="0.01" required className="rounded border px-2 py-1 text-xs" />
                                                                            : <input value={editingVariantDelta} onChange={(e) => setEditingVariantDelta(e.target.value)} type="number" step="0.01" required className="rounded border px-2 py-1 text-xs" />}
                                                                    </div>
                                                                    <div className="flex gap-1.5"><button type="submit" className="flex-1 rounded bg-slate-900 px-2 py-1 text-[11px] font-bold text-white">حفظ</button><button type="button" onClick={() => setEditingVariantId(null)} className="rounded border px-2 py-1 text-[11px] font-bold">إلغاء</button></div>
                                                                </form>
                                                            ) : (
                                                                <div className="flex items-center gap-1.5">
                                                                    <div className="min-w-0 flex-1"><span className="mr-1 text-[9px] text-slate-400">⠿</span><p className="truncate text-xs font-black">{variant.name}</p><p className="text-[10px] text-slate-500">{variant.price !== null && variant.price !== undefined ? Number(variant.price).toFixed(2) + ' ج.م ثابت' : (Number(variant.price_delta) >= 0 ? '+' : '') + Number(variant.price_delta).toFixed(2) + ' ج.م'}</p></div>
                                                                    <button type="button" disabled={variantIndex === 0} onClick={() => moveVariant(category.id, product.id, variants, variantIndex, -1)} className="rounded border px-1.5 text-[10px] disabled:opacity-30">↑</button>
                                                                    <button type="button" disabled={variantIndex === variants.length - 1} onClick={() => moveVariant(category.id, product.id, variants, variantIndex, 1)} className="rounded border px-1.5 text-[10px] disabled:opacity-30">↓</button>
                                                                    <button type="button" onClick={() => startEditingVariant(variant)} className="rounded border px-1.5 py-1 text-[10px]">تعديل</button>
                                                                    <button type="button" onClick={() => toggleVariant(category.id, product.id, variant)} className="rounded border px-1.5 py-1 text-[10px]">{variant.is_active ? 'إخفاء' : 'تفعيل'}</button>
                                                                    <button type="button" onClick={() => deleteVariant(category.id, product.id, variant.id)} className="rounded border border-red-200 px-1.5 py-1 text-[10px] text-red-600">حذف</button>
                                                                </div>
                                                            )}
                                                        </div>
                                                    ))}
                                                    {(product.variants ?? []).length === 0 && <p className="py-1 text-center text-[10px] text-slate-400">مفيش Variants.</p>}
                                                </div>
                                            </div>
                                            <div className="mt-2 rounded-xl border border-emerald-200 bg-emerald-50/40 p-2">
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="text-[11px] font-black text-emerald-700">MODIFIERS · {product.modifiers?.length ?? 0}</span>
                                                    <button type="button" onClick={() => setModifierProductId(modifierProductId === product.id ? null : product.id)} className="rounded-lg border border-emerald-200 bg-white px-2 py-1 text-[11px] font-bold">{modifierProductId === product.id ? 'إغلاق' : '+ Modifier'}</button>
                                                </div>
                                                {modifierProductId === product.id && (
                                                    <form onSubmit={(e) => addModifier(e, category.id, product.id)} className="mt-2 space-y-2">
                                                        <input value={modifierName} onChange={(e) => setModifierName(e.target.value)} placeholder="مثال: Extra Cheese" maxLength={120} required className="w-full rounded-lg border px-2 py-1.5 text-xs" />
                                                        <div className="grid grid-cols-[1fr_auto] gap-2">
                                                            <input value={modifierDelta} onChange={(e) => setModifierDelta(e.target.value)} type="number" step="0.01" placeholder="+ / - من السعر" required className="rounded-lg border px-2 py-1.5 text-xs" />
                                                            <label className="flex items-center gap-1 rounded-lg border bg-white px-2 text-[10px] font-bold"><input type="checkbox" checked={modifierRequired} onChange={(e) => setModifierRequired(e.target.checked)} /> مطلوب</label>
                                                        </div>
                                                        <button type="submit" className="w-full rounded-lg bg-emerald-600 px-2 py-1.5 text-xs font-black text-white">إضافة Modifier</button>
                                                    </form>
                                                )}
                                                <div className="mt-2 space-y-1.5">
                                                    {(product.modifiers ?? []).map((modifier, modifierIndex, modifiers) => (
                                                        <div key={modifier.id} draggable onDragStart={(e) => beginDrag(e, 'modifier', modifier.id, category.id, product.id)} onDragOver={(e) => allowDrop(e, 'modifier', modifier.id)} onDrop={(e) => { e.preventDefault(); reorderModifiersByDrop(category.id, product.id, modifiers, modifier.id); }} onDragEnd={finishDrag} className={'rounded-lg p-2 cursor-grab active:cursor-grabbing ' + (dragOver?.type === 'modifier' && dragOver.id === modifier.id ? 'ring-2 ring-emerald-400 ' : '') + (modifier.is_active ? 'bg-white' : 'bg-slate-100 opacity-60')}>
                                                            {editingModifierId === modifier.id ? (
                                                                <form onSubmit={(e) => saveModifier(e, category.id, product.id, modifier.id)} className="space-y-2">
                                                                    <input value={editingModifierName} onChange={(e) => setEditingModifierName(e.target.value)} required className="w-full rounded border px-2 py-1 text-xs" />
                                                                    <div className="grid grid-cols-2 gap-2">
                                                                        <input value={editingModifierDelta} onChange={(e) => setEditingModifierDelta(e.target.value)} type="number" step="0.01" required className="rounded border px-2 py-1 text-xs" />
                                                                        <label className="flex items-center gap-1 rounded border px-2 text-[10px] font-bold"><input type="checkbox" checked={editingModifierRequired} onChange={(e) => setEditingModifierRequired(e.target.checked)} /> مطلوب</label>
                                                                    </div>
                                                                    <div className="flex gap-1.5"><button type="submit" className="flex-1 rounded bg-slate-900 px-2 py-1 text-[11px] font-bold text-white">حفظ</button><button type="button" onClick={() => setEditingModifierId(null)} className="rounded border px-2 py-1 text-[11px] font-bold">إلغاء</button></div>
                                                                </form>
                                                            ) : (
                                                                <div className="flex items-center gap-1.5">
                                                                    <div className="min-w-0 flex-1"><span className="mr-1 text-[9px] text-slate-400">⠿</span><p className="truncate text-xs font-black">{modifier.name}</p><p className="text-[10px] text-slate-500">{Number(modifier.price_delta) >= 0 ? '+' : ''}{Number(modifier.price_delta).toFixed(2)} ج.م · {modifier.is_required ? 'مطلوب' : 'اختياري'}</p></div>
                                                                    <button type="button" disabled={modifierIndex === 0} onClick={() => moveModifier(category.id, product.id, modifiers, modifierIndex, -1)} className="rounded border px-1.5 text-[10px] disabled:opacity-30">↑</button>
                                                                    <button type="button" disabled={modifierIndex === modifiers.length - 1} onClick={() => moveModifier(category.id, product.id, modifiers, modifierIndex, 1)} className="rounded border px-1.5 text-[10px] disabled:opacity-30">↓</button>
                                                                    <button type="button" onClick={() => startEditingModifier(modifier)} className="rounded border px-1.5 py-1 text-[10px]">تعديل</button>
                                                                    <button type="button" onClick={() => toggleModifier(category.id, product.id, modifier)} className="rounded border px-1.5 py-1 text-[10px]">{modifier.is_active ? 'إخفاء' : 'تفعيل'}</button>
                                                                    <button type="button" onClick={() => deleteModifier(category.id, product.id, modifier.id)} className="rounded border border-red-200 px-1.5 py-1 text-[10px] text-red-600">حذف</button>
                                                                </div>
                                                            )}
                                                        </div>
                                                    ))}
                                                    {(product.modifiers ?? []).length === 0 && <p className="py-1 text-center text-[10px] text-slate-400">مفيش Modifiers.</p>}
                                                </div>
                                            </div>
                                            </React.Fragment>
                                        ))}
                                        {category.products.length === 0 && <p className="py-2 text-center text-xs text-slate-400">مفيش منتجات في القسم.</p>}
                                    </div>
                                </div>
                            ))}
                            {menu.categories.length === 0 && <p className="rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-500">لسه مفيش أقسام. أضف أول قسم فوق.</p>}
                        </div>
                    </section>
                </aside>

                <section className="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-xl">
                    <TemplateRenderer menu={{ ...menu, template }} restaurantName="NEXORA Preview" preview />
                </section>
            </main>
        </div>
    );
}
