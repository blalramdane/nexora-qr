import { Head, Link, router } from '@inertiajs/react';
import React, { FormEvent, useEffect, useState } from 'react';
import QRCode from 'qrcode';
import TemplateRenderer from './TemplateRenderer';
import PwaInstallPrompt from '../../Components/PwaInstallPrompt';

type Template = { id: number; key: string; name: string };
type Category = {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    sort_order: number;
    is_active: boolean;
    products: Array<{ id: number; name: string; description?: string | null; price: string; image_path?: string | null; is_available: boolean; is_featured: boolean; sort_order: number; variants?: Array<{ id: number; name: string; price?: string | null; price_delta?: string; sort_order: number; is_active: boolean }>;
        modifiers?: Array<{ id: number; name: string; price_delta: string; sort_order: number; is_required: boolean; is_active: boolean; group_name?: string | null; group_min_choices?: number; group_max_choices?: number }>;
    }>;
};
type RenderableMenu = Parameters<typeof TemplateRenderer>[0]['menu'];
type Menu = Omit<RenderableMenu, 'categories'> & {
    id: number;
    restaurant_id: number;
    slug: string;
    is_published: boolean;
    template_id: number | null;
    published_at?: string | null;
    theme: { primary: string; accent: string; background: string; foreground: string; radius: 'sm' | 'md' | 'lg' | 'xl' | '2xl' };
    categories: Category[];
};

const themePresets = [
    { id: 'ember', name: 'Ember Burger', primary: '#f97316', accent: '#fbbf24', background: '#11100f', foreground: '#fff7ed', radius: 'xl' as const, swatches: ['#11100f', '#f97316', '#fbbf24'] },
    { id: 'espresso', name: 'Espresso Bar', primary: '#3b2418', accent: '#d6a45b', background: '#0d0907', foreground: '#f7ecdc', radius: 'xl' as const, swatches: ['#0d0907', '#3b2418', '#d6a45b'] },
    { id: 'noir', name: 'Noir Luxe', primary: '#c6a96b', accent: '#f0dfae', background: '#080807', foreground: '#f5efe2', radius: 'lg' as const, swatches: ['#080807', '#c6a96b', '#f0dfae'] },
    { id: 'fresh', name: 'Fresh & Clean', primary: '#166534', accent: '#84cc16', background: '#f8fafc', foreground: '#17211b', radius: '2xl' as const, swatches: ['#f8fafc', '#166534', '#84cc16'] },
    { id: 'ocean', name: 'Ocean Pop', primary: '#155e75', accent: '#22d3ee', background: '#07141b', foreground: '#ecfeff', radius: 'xl' as const, swatches: ['#07141b', '#155e75', '#22d3ee'] },
    { id: 'rose', name: 'Rose Atelier', primary: '#9f1239', accent: '#fda4af', background: '#fff7f8', foreground: '#3b1520', radius: '2xl' as const, swatches: ['#fff7f8', '#9f1239', '#fda4af'] },
];

export default function MenuBuilder({ menu, template, templates, errors, flash, publicUrl, managementBase, menusIndexUrl, restaurantName }: { menu: Menu; template: Template | null; templates: Template[]; errors?: Record<string, string | string[]>; flash?: { success?: string | null }; publicUrl?: string; managementBase?: string; menusIndexUrl?: string; restaurantName?: string }) {
    const menuBasePath = managementBase ?? `/menus/${menu.id}`;
    const menuIndexPath = menusIndexUrl ?? '/menus';
    const [qrCodeDataUrl, setQrCodeDataUrl] = useState('');
    const [name, setName] = useState(menu.name);
    const [templateKey, setTemplateKey] = useState(template?.key ?? '');
    const initialTheme = menu.theme ?? { primary: '#111827', accent: '#f59e0b', background: '#ffffff', foreground: '#111827', radius: 'xl' as const };
    const [themePrimary, setThemePrimary] = useState(initialTheme.primary);
    const [themeAccent, setThemeAccent] = useState(initialTheme.accent);
    const [themeBackground, setThemeBackground] = useState(initialTheme.background);
    const [themeForeground, setThemeForeground] = useState(initialTheme.foreground);
    const [themeRadius, setThemeRadius] = useState(initialTheme.radius);
    useEffect(() => {
        let cancelled = false;
        if (!menu.is_published || !publicUrl) {
            setQrCodeDataUrl('');
            return () => { cancelled = true; };
        }
        QRCode.toDataURL(publicUrl, { width: 720, margin: 3, errorCorrectionLevel: 'H', color: { dark: '#111827', light: '#ffffff' } })
            .then(dataUrl => { if (!cancelled) setQrCodeDataUrl(dataUrl); })
            .catch(() => { if (!cancelled) setQrCodeDataUrl(''); });
        return () => { cancelled = true; };
    }, [menu.is_published, publicUrl]);
    const applyThemePreset = (preset: typeof themePresets[number]) => {
        setThemePrimary(preset.primary);
        setThemeAccent(preset.accent);
        setThemeBackground(preset.background);
        setThemeForeground(preset.foreground);
        setThemeRadius(preset.radius);
    };
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
    const [productImageFile, setProductImageFile] = useState<File | null>(null);
    const [productImagePreview, setProductImagePreview] = useState<string | null>(null);
    const [productFeatured, setProductFeatured] = useState(false);
    const [editingProductId, setEditingProductId] = useState<number | null>(null);
    const [editingProductName, setEditingProductName] = useState('');
    const [editingProductDescription, setEditingProductDescription] = useState('');
    const [editingProductPrice, setEditingProductPrice] = useState('');
    const [editingProductImage, setEditingProductImage] = useState('');
    const [editingProductFeatured, setEditingProductFeatured] = useState(false);
    const [editingProductImagePreview, setEditingProductImagePreview] = useState<string | null>(null);
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
    const [expandedVariantsProductId, setExpandedVariantsProductId] = useState<number | null>(null);
    const [expandedModifiersProductId, setExpandedModifiersProductId] = useState<number | null>(null);
    const [modifierName, setModifierName] = useState('');
    const [modifierDelta, setModifierDelta] = useState('');
    const [modifierRequired, setModifierRequired] = useState(false);
    const [modifierGroup, setModifierGroup] = useState('');
    const [modifierGroupMin, setModifierGroupMin] = useState('0');
    const [modifierGroupMax, setModifierGroupMax] = useState('1');
    const [editingModifierId, setEditingModifierId] = useState<number | null>(null);
    const [editingModifierName, setEditingModifierName] = useState('');
    const [editingModifierDelta, setEditingModifierDelta] = useState('');
    const [editingModifierRequired, setEditingModifierRequired] = useState(false);
    const [editingModifierGroup, setEditingModifierGroup] = useState('');
    const [editingModifierGroupMin, setEditingModifierGroupMin] = useState('0');
    const [editingModifierGroupMax, setEditingModifierGroupMax] = useState('1');
    const [dragging, setDragging] = useState<{ type: 'category' | 'product' | 'variant' | 'modifier'; id: number; categoryId?: number; productId?: number } | null>(null);
    const [dragOver, setDragOver] = useState<{ type: 'category' | 'product' | 'variant' | 'modifier'; id: number } | null>(null);
    const [contentTab, setContentTab] = useState<'products' | 'categories'>('products');
    const [productSearch, setProductSearch] = useState('');
    const [productStatusFilter, setProductStatusFilter] = useState<'all' | 'available' | 'hidden' | 'featured' | 'missing-image'>('all');
    const [productCategoryFilter, setProductCategoryFilter] = useState<'all' | number>('all');
    const [showProductForm, setShowProductForm] = useState(false);
    const [productImageError, setProductImageError] = useState('');
    const [productUploadProgress, setProductUploadProgress] = useState<number | null>(null);
    const [isSavingProduct, setIsSavingProduct] = useState(false);
    const [imageUploadErrors, setImageUploadErrors] = useState<Record<number, string>>({});
    const [imageUploadProgress, setImageUploadProgress] = useState<Record<number, number>>({});
    const [imageUploadPreviews, setImageUploadPreviews] = useState<Record<number, string>>({});
    const productStats = menu.categories.reduce((stats, category) => {
        for (const product of category.products) {
            stats.total += 1;
            if (product.is_available) stats.available += 1;
            if (product.is_featured) stats.featured += 1;
            if (!product.image_path) stats.missingImage += 1;
        }
        return stats;
    }, { total: 0, available: 0, featured: 0, missingImage: 0 });
    const activeCategoriesCount = menu.categories.filter(category => category.is_active).length;
    const availableProductsInActiveCategories = menu.categories
        .filter(category => category.is_active)
        .reduce((count, category) => count + category.products.filter(product => product.is_available).length, 0);
    const publishChecklist = [
        { label: 'قالب تصميم محفوظ', done: Boolean(menu.template_id && (menu.template_version || menu.templateVersion)) },
        { label: 'قسم نشط واحد على الأقل', done: activeCategoriesCount > 0, detail: `${activeCategoriesCount.toLocaleString('ar-EG')} قسم` },
        { label: 'صنف متاح داخل قسم نشط', done: availableProductsInActiveCategories > 0, detail: `${availableProductsInActiveCategories.toLocaleString('ar-EG')} صنف` },
    ];
    const productSearchQuery = productSearch.trim().toLocaleLowerCase('ar-EG');
    const matchesProductFilters = (product: Category['products'][number]) => {
        const textMatches = !productSearchQuery || `${product.name} ${product.description ?? ''}`.toLocaleLowerCase('ar-EG').includes(productSearchQuery);
        const statusMatches = productStatusFilter === 'all'
            || (productStatusFilter === 'available' && product.is_available)
            || (productStatusFilter === 'hidden' && !product.is_available)
            || (productStatusFilter === 'featured' && product.is_featured)
            || (productStatusFilter === 'missing-image' && !product.image_path);
        return textMatches && statusMatches;
    };
    const visibleProducts = menu.categories.flatMap(category => category.products.map((product, index) => ({ category, product, index })))
        .filter(({ category, product }) => matchesProductFilters(product) && (productCategoryFilter === 'all' || category.id === productCategoryFilter));

    const publishMenu = () => router.post(menuBasePath + '/publish');
    const unpublishMenu = () => router.post(menuBasePath + '/unpublish');

    const save = (event: FormEvent) => {
        event.preventDefault();
        router.put(menuBasePath, { name, template_key: templateKey });
    };

    const saveTheme = (event: FormEvent) => {
        event.preventDefault();
        router.put(menuBasePath + '/theme', {
            primary: themePrimary,
            accent: themeAccent,
            background: themeBackground,
            foreground: themeForeground,
            radius: themeRadius,
        });
    };

    const remove = () => {
        if (window.confirm('حذف المنيو نهائيًا؟')) {
            router.delete(menuBasePath);
        }
    };

    const publish = () => {
        router.post(menuBasePath + '/publish');
    };

    const unpublish = () => {
        router.post(menuBasePath + '/unpublish');
    };

    const previewTemplate = templates.find((item) => item.key === templateKey) ?? template;
    const previewDraftProduct = productCategoryId && (productName.trim() || productPrice || productDescription.trim() || productImage.trim() || productImageFile)
        ? {
            id: -1,
            name: productName.trim() || 'اسم المنتج',
            description: productDescription.trim() || 'وصف المنتج يظهر هنا',
            price: productPrice || '0',
            image_path: productImagePreview || productImage.trim() || null,
            is_available: true,
            is_featured: productFeatured,
            sort_order: -1,
        }
        : null;

    const editingProductContext = editingProductId
        ? menu.categories.find((category) => category.products.some((product) => product.id === editingProductId)) ?? null
        : null;

    const previewCategoryId = editingProductContext?.id ?? productCategoryId ?? undefined;

    const previewMenu: Menu = {
        ...menu,
        name,
        template: previewTemplate,
        theme: {
            primary: themePrimary,
            accent: themeAccent,
            background: themeBackground,
            foreground: themeForeground,
            radius: themeRadius,
        },
        categories: menu.categories.map((category) => {
            if (editingProductId && editingProductContext?.id === category.id) {
                return {
                    ...category,
                    products: category.products.map((product) =>
                        product.id === editingProductId
                            ? {
                                ...product,
                                name: editingProductName.trim() || product.name,
                                description: editingProductDescription.trim() || null,
                                price: editingProductPrice || product.price,
                                image_path: editingProductImagePreview || editingProductImage.trim() || product.image_path || null,
                                is_featured: editingProductFeatured,
                            }
                            : product,
                    ),
                };
            }

            if (category.id === productCategoryId && previewDraftProduct) {
                return { ...category, products: [previewDraftProduct, ...category.products] };
            }

            return category;
        }),
    };

    const [previewDevice, setPreviewDevice] = useState<'mobile' | 'tablet' | 'desktop'>('mobile');



    const addCategory = (event: FormEvent) => {
        event.preventDefault();
        if (!categoryName.trim()) return;
        router.post(menuBasePath + '/categories', {
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
        router.put(menuBasePath + '/categories/' + editingId, {
            name: editingName,
            description: editingDescription || null,
            is_active: menu.categories.find(c => c.id === editingId)?.is_active ?? true,
        }, {
            onSuccess: () => setEditingId(null),
        });
    };

    const toggleCategory = (category: Category) => {
        router.put(menuBasePath + '/categories/' + category.id, {
            name: category.name,
            description: category.description ?? null,
            sort_order: category.sort_order,
            is_active: !category.is_active,
        });
    };

    const deleteCategory = (category: Category) => {
        if (window.confirm('حذف القسم؟ المنتجات المرتبطة به سيتم حذفها أيضًا.')) {
            router.delete(menuBasePath + '/categories/' + category.id);
        }
    };

    const addProduct = (event: FormEvent, categoryId: number) => {
        event.preventDefault();
        if (!productName.trim() || !productPrice) return;

        const payload: Record<string, string | boolean | File | null> = {
            name: productName.trim(),
            description: productDescription.trim() || null,
            price: productPrice,
            image_path: productImage.trim() || null,
            is_featured: productFeatured,
        };

        if (productImageFile) {
            payload.image = productImageFile;
        }

        router.post(menuBasePath + '/categories/' + categoryId + '/products', payload, {
            forceFormData: Boolean(productImageFile),
            onStart: () => { setIsSavingProduct(true); setProductUploadProgress(productImageFile ? 0 : null); },
            onProgress: progress => setProductUploadProgress(productImageFile ? progress?.percentage ?? 0 : null),
            onSuccess: () => {
                setProductName('');
                setProductDescription('');
                setProductPrice('');
                setProductImage('');
                setProductImageFile(null);
                setProductImagePreview(null);
                setProductImageError('');
                setProductFeatured(false);
                setProductCategoryId(null);
                setShowProductForm(false);
            },
            onError: errors => setProductImageError(errors.image ?? errors.name ?? errors.price ?? 'تعذر حفظ المنتج. راجع البيانات وحاول مرة أخرى.'),
            onFinish: () => { setProductUploadProgress(null); setIsSavingProduct(false); },
        });
    };

    const selectNewProductImage = (file: File) => {
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            setProductImageFile(null);
            setProductImagePreview(null);
            setProductImageError('الصيغة غير مدعومة. استخدم JPG أو PNG أو WebP.');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            setProductImageFile(null);
            setProductImagePreview(null);
            setProductImageError('حجم الصورة أكبر من 5MB. اختار صورة أصغر.');
            return;
        }
        setProductImageError('');
        setProductImageFile(file);
        setProductImagePreview(URL.createObjectURL(file));
    };

    const handleNewProductImageInput = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (file) selectNewProductImage(file);
        event.target.value = '';
    };

    const handleNewProductImageDrop = (event: React.DragEvent<HTMLLabelElement>) => {
        event.preventDefault();
        const file = event.dataTransfer.files?.[0];
        if (file) selectNewProductImage(file);
    };

    const clearNewProductImage = () => {
        setProductImageFile(null);
        setProductImagePreview(null);
        setProductImageError('');
    };

    const closeProductForm = () => {
        setShowProductForm(false);
        setProductCategoryId(null);
        setProductName('');
        setProductDescription('');
        setProductPrice('');
        setProductImage('');
        setProductFeatured(false);
        clearNewProductImage();
        setProductImageError('');
    };

    useEffect(() => {
        return () => {
            if (productImagePreview) URL.revokeObjectURL(productImagePreview);
        };
    }, [productImagePreview]);

    const uploadProductImage = (file: File, categoryId: number, productId: number) => {
        if (imageUploadProgress[productId] !== undefined) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            setImageUploadErrors(previous => ({ ...previous, [productId]: 'الصيغة غير مدعومة. استخدم JPG أو PNG أو WebP.' }));
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            setImageUploadErrors(previous => ({ ...previous, [productId]: 'حجم الصورة أكبر من 5MB. اختار صورة أصغر.' }));
            return;
        }
        setImageUploadErrors(previous => { const next = { ...previous }; delete next[productId]; return next; });
        setImageUploadProgress(previous => ({ ...previous, [productId]: 0 }));

        const localPreview = URL.createObjectURL(file);
        setImageUploadPreviews(previous => {
            const existing = previous[productId];
            if (existing) URL.revokeObjectURL(existing);
            return { ...previous, [productId]: localPreview };
        });
        setEditingProductImagePreview(localPreview);

        router.post(menuBasePath + '/categories/' + categoryId + '/products/' + productId + '/image', { image: file }, {
            forceFormData: true,
            onProgress: progress => setImageUploadProgress(previous => ({ ...previous, [productId]: progress?.percentage ?? 0 })),
            onSuccess: page => {
                const returnedMenu = (page.props as { menu?: Menu }).menu;
                const savedProduct = returnedMenu?.categories.flatMap(category => category.products).find(product => product.id === productId);
                if (savedProduct?.image_path) {
                    window.dispatchEvent(new CustomEvent('nexora:product-image-updated', {
                        detail: { productId, imagePath: savedProduct.image_path },
                    }));
                }
                setEditingProductImage('');
                setEditingProductImagePreview(null);
                setImageUploadPreviews(previous => {
                    const next = { ...previous };
                    delete next[productId];
                    return next;
                });
                URL.revokeObjectURL(localPreview);
                setImageUploadErrors(previous => { const next = { ...previous }; delete next[productId]; return next; });
            },
            onError: errors => {
                setEditingProductImagePreview(null);
                setImageUploadPreviews(previous => {
                    const next = { ...previous };
                    delete next[productId];
                    return next;
                });
                URL.revokeObjectURL(localPreview);
                setImageUploadErrors(previous => ({ ...previous, [productId]: errors.image ?? 'تعذر رفع الصورة. راجع نوع الملف وحجمه.' }));
            },
            onFinish: () => setImageUploadProgress(previous => { const next = { ...previous }; delete next[productId]; return next; }),
        });
    };

    const handleProductImageInput = (event: React.ChangeEvent<HTMLInputElement>, categoryId: number, productId: number) => {
        const file = event.target.files?.[0];
        if (file) uploadProductImage(file, categoryId, productId);
        event.target.value = '';
    };

    const handleProductImageDrop = (event: React.DragEvent<HTMLLabelElement>, categoryId: number, productId: number) => {
        event.preventDefault();
        const file = event.dataTransfer.files?.[0];
        if (file) uploadProductImage(file, categoryId, productId);
    };

    const removeProductImage = (categoryId: number, productId: number) => {
        if (window.confirm('حذف صورة المنتج؟')) {
            router.delete(menuBasePath + '/categories/' + categoryId + '/products/' + productId + '/image', {
                onSuccess: () => {
                    setEditingProductImagePreview(null);
                    setEditingProductImage('');
                },
            });
        }
    };

    const startEditingProduct = (product: Category['products'][number]) => {
        setEditingProductId(product.id);
        setEditingProductName(product.name);
        setEditingProductDescription(product.description ?? '');
        setEditingProductPrice(product.price);
        setEditingProductImage(product.image_path ?? '');
        setEditingProductFeatured(product.is_featured);
        setEditingProductImagePreview(null);
    };

    const closeEditingProduct = () => {
        setEditingProductId(null);
        setEditingProductImagePreview(null);
    };

    const saveProduct = (event: FormEvent, categoryId: number, productId: number) => {
        event.preventDefault();
        if (!editingProductName.trim() || !editingProductPrice) return;
        const product = menu.categories.find(c => c.id === categoryId)?.products.find(p => p.id === productId);
        if (!product) return;
        router.put(menuBasePath + '/categories/' + categoryId + '/products/' + productId, {
            name: editingProductName.trim(),
            description: editingProductDescription.trim() || null,
            price: editingProductPrice,
            image_path: editingProductImage.trim() || product.image_path || null,
            is_available: product.is_available,
            is_featured: editingProductFeatured,
            sort_order: product.sort_order,
        }, { onSuccess: closeEditingProduct });
    };

    useEffect(() => {
        return () => {
            if (editingProductImagePreview) URL.revokeObjectURL(editingProductImagePreview);
        };
    }, [editingProductImagePreview]);

    const toggleProduct = (category: Category, product: Category['products'][number]) => {
        router.put(menuBasePath + '/categories/' + category.id + '/products/' + product.id, {
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
            router.delete(menuBasePath + '/categories/' + category.id + '/products/' + product.id);
        }
    };

    const moveProduct = (category: Category, index: number, direction: -1 | 1) => {
        const nextIndex = index + direction;
        if (nextIndex < 0 || nextIndex >= category.products.length) return;
        const ids = category.products.map(p => p.id);
        [ids[index], ids[nextIndex]] = [ids[nextIndex], ids[index]];
        router.post(menuBasePath + '/categories/' + category.id + '/products/reorder', { product_ids: ids });
    };

    const addVariant = (event: FormEvent, categoryId: number, productId: number) => {
        event.preventDefault();
        if (!variantName.trim()) return;
        if (variantPricingMode === 'fixed' && !variantPrice) return;
        if (variantPricingMode === 'delta' && !variantDelta) return;
        router.post(menuBasePath + '/categories/' + categoryId + '/products/' + productId + '/variants', {
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
        router.put(menuBasePath + '/categories/' + categoryId + '/products/' + productId + '/variants/' + variantId, {
            name: editingVariantName,
            pricing_mode: editingVariantPricingMode,
            price: editingVariantPricingMode === 'fixed' ? editingVariantPrice : null,
            price_delta: editingVariantPricingMode === 'delta' ? editingVariantDelta : null,
            is_active: variant.is_active,
            sort_order: variant.sort_order,
        }, { onSuccess: () => setEditingVariantId(null) });
    };

    const toggleVariant = (categoryId: number, productId: number, variant: NonNullable<Category['products'][number]['variants']>[number]) => {
        router.put(menuBasePath + '/categories/' + categoryId + '/products/' + productId + '/variants/' + variant.id, {
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
        router.post(menuBasePath + '/categories/' + categoryId + '/products/' + productId + '/variants/reorder', { variant_ids: ids });
    };

    const deleteVariant = (categoryId: number, productId: number, variantId: number) => {
        if (window.confirm('حذف الـVariant؟')) router.delete(menuBasePath + '/categories/' + categoryId + '/products/' + productId + '/variants/' + variantId);
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
        router.post(menuBasePath + '/categories/reorder', { category_ids: ids }, { onFinish: finishDrag });
    };

    const reorderProductsByDrop = (category: Category, targetId: number) => {
        if (!dragging || dragging.type !== 'product' || dragging.categoryId !== category.id || dragging.id === targetId) return finishDrag();
        const ids = category.products.map(p => p.id);
        const from = ids.indexOf(dragging.id);
        const to = ids.indexOf(targetId);
        if (from < 0 || to < 0) return finishDrag();
        ids.splice(from, 1);
        ids.splice(to, 0, dragging.id);
        router.post(menuBasePath + '/categories/' + category.id + '/products/reorder', { product_ids: ids }, { onFinish: finishDrag });
    };

    const reorderVariantsByDrop = (categoryId: number, productId: number, variants: NonNullable<Category['products'][number]['variants']>, targetId: number) => {
        if (!dragging || dragging.type !== 'variant' || dragging.productId !== productId || dragging.id === targetId) return finishDrag();
        const ids = variants.map(v => v.id);
        const from = ids.indexOf(dragging.id);
        const to = ids.indexOf(targetId);
        if (from < 0 || to < 0) return finishDrag();
        ids.splice(from, 1);
        ids.splice(to, 0, dragging.id);
        router.post(menuBasePath + '/categories/' + categoryId + '/products/' + productId + '/variants/reorder', { variant_ids: ids }, { onFinish: finishDrag });
    };

    const reorderModifiersByDrop = (categoryId: number, productId: number, modifiers: NonNullable<Category['products'][number]['modifiers']>, targetId: number) => {
        if (!dragging || dragging.type !== 'modifier' || dragging.productId !== productId || dragging.id === targetId) return finishDrag();
        const ids = modifiers.map(m => m.id);
        const from = ids.indexOf(dragging.id);
        const to = ids.indexOf(targetId);
        if (from < 0 || to < 0) return finishDrag();
        ids.splice(from, 1);
        ids.splice(to, 0, dragging.id);
        router.post(menuBasePath + '/categories/' + categoryId + '/products/' + productId + '/modifiers/reorder', { modifier_ids: ids }, { onFinish: finishDrag });
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
        router.post(menuBasePath + '/categories/reorder', { category_ids: ids });
    };

    const addModifier = (event: FormEvent, categoryId: number, productId: number) => {
        event.preventDefault();
        if (!modifierName.trim() || !modifierDelta) return;
        router.post(menuBasePath + '/categories/' + categoryId + '/products/' + productId + '/modifiers', {
            name: modifierName,
            price_delta: modifierDelta,
            is_required: modifierRequired && !modifierGroup.trim(),
            group_name: modifierGroup.trim() || null,
            group_min_choices: Number(modifierGroupMin),
            group_max_choices: Number(modifierGroupMax),
            is_active: true,
        }, { onSuccess: () => {
            setModifierName(''); setModifierDelta(''); setModifierRequired(false); setModifierGroup(''); setModifierGroupMin('0'); setModifierGroupMax('1'); setModifierProductId(null);
        }});
    };

    const startEditingModifier = (modifier: NonNullable<Category['products'][number]['modifiers']>[number]) => {
        setEditingModifierId(modifier.id);
        setEditingModifierName(modifier.name);
        setEditingModifierDelta(modifier.price_delta);
        setEditingModifierRequired(modifier.is_required);
        setEditingModifierGroup(modifier.group_name ?? '');
        setEditingModifierGroupMin(String(modifier.group_min_choices ?? 0));
        setEditingModifierGroupMax(String(modifier.group_max_choices ?? 1));
    };

    const saveModifier = (event: FormEvent, categoryId: number, productId: number, modifierId: number) => {
        event.preventDefault();
        if (!editingModifierName.trim() || !editingModifierDelta) return;
        const product = menu.categories.find(c => c.id === categoryId)?.products.find(p => p.id === productId);
        const modifier = product?.modifiers?.find(m => m.id === modifierId);
        if (!modifier) return;
        router.put(menuBasePath + '/categories/' + categoryId + '/products/' + productId + '/modifiers/' + modifierId, {
            name: editingModifierName,
            price_delta: editingModifierDelta,
            is_required: editingModifierRequired && !editingModifierGroup.trim(),
            group_name: editingModifierGroup.trim() || null,
            group_min_choices: Number(editingModifierGroupMin),
            group_max_choices: Number(editingModifierGroupMax),
            is_active: modifier.is_active,
            sort_order: modifier.sort_order,
        }, { onSuccess: () => setEditingModifierId(null) });
    };

    const toggleModifier = (categoryId: number, productId: number, modifier: NonNullable<Category['products'][number]['modifiers']>[number]) => {
        router.put(menuBasePath + '/categories/' + categoryId + '/products/' + productId + '/modifiers/' + modifier.id, {
            name: modifier.name,
            price_delta: modifier.price_delta,
            is_required: modifier.is_required,
            group_name: modifier.group_name ?? null,
            group_min_choices: modifier.group_min_choices ?? 0,
            group_max_choices: modifier.group_max_choices ?? 1,
            is_active: !modifier.is_active,
            sort_order: modifier.sort_order,
        });
    };

    const deleteModifier = (categoryId: number, productId: number, modifierId: number) => {
        if (window.confirm('حذف الـ Modifier؟')) {
            router.delete(menuBasePath + '/categories/' + categoryId + '/products/' + productId + '/modifiers/' + modifierId);
        }
    };

    const moveModifier = (categoryId: number, productId: number, modifiers: NonNullable<Category['products'][number]['modifiers']>, index: number, direction: -1 | 1) => {
        const nextIndex = index + direction;
        if (nextIndex < 0 || nextIndex >= modifiers.length) return;
        const ids = modifiers.map(m => m.id);
        [ids[index], ids[nextIndex]] = [ids[nextIndex], ids[index]];
        router.post(menuBasePath + '/categories/' + categoryId + '/products/' + productId + '/modifiers/reorder', { modifier_ids: ids });
    };

    return (
        <div dir="rtl" className="min-h-screen bg-slate-100">
            <Head title={'Builder · ' + menu.name} />
            <div className="sticky top-0 z-40 border-b border-slate-200 bg-white/95 px-4 py-3 shadow-sm backdrop-blur-xl">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-4">
                    <div>
                        <span className="text-xs font-bold text-amber-600">MENU BUILDER</span>
                        <h1 className="font-black">{menu.name}</h1>
                    </div>
                    <div className="flex flex-wrap items-center justify-end gap-2">
                        <Link href={`${menuBasePath}/import`} className="rounded-xl bg-amber-500 px-3 py-2 text-xs font-black text-slate-950 shadow-sm transition hover:bg-amber-400">ابدأ بصور المنيو أو CSV</Link>
                        {menu.is_published && publicUrl && <a href={publicUrl} target="_blank" rel="noreferrer" className="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-black text-white">فتح المنيو المنشورة ↗</a>}
                        {menu.is_published && publicUrl && <button type="button" onClick={() => navigator.clipboard.writeText(publicUrl)} className="rounded-xl border border-emerald-200 px-3 py-2 text-xs font-bold text-emerald-700">نسخ الرابط</button>}
                        <Link href={menuIndexPath} className="text-sm font-bold underline">كل المنيوهات</Link>
                    </div>
                </div>
            </div>

            <section className="mx-auto grid max-w-7xl grid-cols-2 gap-3 px-4 pt-5 sm:grid-cols-4 lg:px-6">
                {[
                    { label: 'إجمالي المنتجات', value: productStats.total, hint: 'PRODUCTS', tone: 'text-slate-900', icon: '▦' },
                    { label: 'متاح للبيع', value: productStats.available, hint: 'AVAILABLE', tone: 'text-emerald-700', icon: '✓' },
                    { label: 'منتجات مميزة', value: productStats.featured, hint: 'FEATURED', tone: 'text-amber-700', icon: '✦' },
                    { label: 'محتاجة صور', value: productStats.missingImage, hint: 'IMAGE AUDIT', tone: 'text-rose-700', icon: '▧' },
                ].map(stat => <button key={stat.label} type="button" onClick={() => {
                    const filter = stat.label === 'محتاجة صور' ? 'missing-image' : stat.label === 'متاح للبيع' ? 'available' : stat.label === 'منتجات مميزة' ? 'featured' : 'all';
                    setProductStatusFilter(filter);
                    setContentTab('products');
                    window.setTimeout(() => document.getElementById('menu-content-studio')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 0);
                }} className="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 text-right shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 sm:p-5">
                    <div className="flex items-center justify-between gap-2"><span className="text-[9px] font-black tracking-[.17em] text-slate-400">{stat.hint}</span><span className={`grid h-8 w-8 place-items-center rounded-xl bg-slate-50 text-base ${stat.tone}`}>{stat.icon}</span></div>
                    <p className="mt-3 text-2xl font-black tabular-nums sm:text-3xl">{stat.value.toLocaleString('ar-EG')}</p>
                    <p className="mt-1 text-xs font-bold text-slate-500">{stat.label}</p>
                    <p className="mt-2 text-[10px] font-bold text-emerald-700">اضغط للانتقال ↙</p>
                </button>)}
            </section>

            <PwaInstallPrompt />

            <nav aria-label="اختصارات إدارة المنيو من الموبايل" className="fixed inset-x-3 bottom-3 z-[70] grid grid-cols-3 gap-2 rounded-2xl border border-slate-200 bg-white/95 p-2 shadow-2xl backdrop-blur-xl lg:hidden">
                <a href="#menu-content-studio" className="flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-2 text-center text-xs font-black text-white">المنتجات والصور</a>
                <Link href={`${menuBasePath}/import`} className="flex min-h-11 items-center justify-center rounded-xl bg-amber-400 px-2 text-center text-xs font-black text-slate-950">استيراد المنيو</Link>
                <Link href={menuBasePath.startsWith('/owner/') ? `/owner/restaurants/${menu.restaurant_id}/orders` : '/orders'} className="flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-2 text-center text-xs font-black text-white">صندوق الطلبات</Link>
            </nav>

            <main className="mx-auto grid max-w-[1600px] grid-cols-1 gap-5 p-4 sm:p-5 lg:gap-6 lg:p-7">
                <aside className="min-w-0 flex flex-col gap-5">
                    <section className="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <span className="text-xs font-bold text-emerald-600">PUBLISH WORKFLOW</span>
                                <h2 className="mt-1 text-lg font-black">حالة المنيو</h2>
                            </div>
                            <span className={'rounded-full px-3 py-1 text-xs font-black ' + (menu.is_published ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600')}>
                                {menu.is_published ? 'Published' : 'Draft'}
                            </span>
                        </div>
                        {flash?.success && <p className="mt-3 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700">{flash.success}</p>}
                        {errors?.publish && <p className="mt-3 rounded-xl bg-red-50 px-3 py-2 text-xs font-bold text-red-700">{Array.isArray(errors.publish) ? errors.publish[0] : errors.publish}</p>}
                        {!menu.is_published ? (
                            <button type="button" onClick={publish} className="mt-4 w-full rounded-xl bg-emerald-600 px-4 py-3 font-black text-white shadow-sm transition hover:bg-emerald-700">
                                نشر المنيو
                            </button>
                        ) : (
                            <button type="button" onClick={unpublish} className="mt-4 w-full rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 font-black text-amber-700 transition hover:bg-amber-100">
                                إلغاء النشر
                            </button>
                        )}
                        <div className="mt-4 space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3" aria-label="متطلبات نشر المنيو">
                            <p className="mb-1 text-xs font-black text-slate-700">جاهزية النشر</p>
                            {publishChecklist.map(item => <div key={item.label} className="flex items-center gap-2 text-xs">
                                <span aria-hidden="true" className={'grid h-5 w-5 shrink-0 place-items-center rounded-full font-black ' + (item.done ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700')}>{item.done ? '✓' : '!'}</span>
                                <span className={item.done ? 'text-slate-700' : 'font-bold text-slate-800'}>{item.label}</span>
                                {'detail' in item && <span className="mr-auto text-[10px] text-slate-500">{item.detail}</span>}
                            </div>)}
                            {publishChecklist.some(item => !item.done) && <p className="pt-1 text-[11px] leading-5 text-amber-800">كمّل العناصر اللي عليها علامة !؛ هتظهر هنا حالتها قبل النشر عشان تعرف الخطوة الناقصة.</p>}
                        </div>
                        {menu.is_published && publicUrl && <div className="mt-3 rounded-xl bg-emerald-50 p-3"><p className="text-xs font-bold text-emerald-800">رابط المنيو للعميل</p><a href={publicUrl} target="_blank" rel="noreferrer" className="mt-1 block break-all text-xs text-emerald-700 underline">{publicUrl}</a>{qrCodeDataUrl && <div className="mt-4 rounded-xl border border-emerald-100 bg-white p-4 text-center"><img src={qrCodeDataUrl} alt={'QR code for ' + menu.name} className="mx-auto aspect-square w-48 max-w-full"/><p className="mt-2 text-sm font-black text-slate-900">امسح الكود لفتح المنيو</p><p className="mt-1 text-[10px] text-slate-500">كود عالي الدقة مناسب للطباعة على الترابيزات والمنيو الورقي.</p><a href={qrCodeDataUrl} download={'nexora-qr-' + menu.slug + '.png'} className="mt-3 block rounded-lg bg-emerald-700 px-3 py-2 text-center text-xs font-black text-white">تحميل QR للطباعة (PNG)</a></div>}<button type="button" onClick={() => navigator.clipboard.writeText(publicUrl)} className="mt-3 w-full rounded-lg border border-emerald-200 bg-white px-3 py-2 text-xs font-bold text-emerald-700">نسخ رابط المنيو</button><Link href="/orders" className="mt-3 block rounded-lg bg-slate-950 px-3 py-2 text-center text-xs font-black text-white">فتح طلبات المطعم</Link></div>}
                    </section>

                    <section className="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="text-lg font-black">إعدادات المنيو</h2>
                        <Link href={`${menuBasePath}/import`} className="mt-3 block rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-center text-sm font-black text-amber-800">استيراد الصور أو ملف CSV — اختصار تجهيز المنيو</Link>
                        <div className="mt-4 rounded-2xl border border-indigo-100 bg-indigo-50/60 p-3">
                            <p className="mb-3 text-sm font-black">معرض القوالب — اختار وشوف المعاينة فورًا</p>
                            <div className="grid grid-cols-2 gap-2">
                                {templates.map((item, index) => {
                                    const selected = templateKey === item.key;
                                    const palettes = [ ['#17120f', '#fb923c', '#facc15'], ['#f8f2e8', '#6f4e37', '#c49a6c'], ['#101010', '#c6a96b', '#f4e4bd'] ];
                                    const [background, primary, accent] = palettes[index % palettes.length];
                                    return <button key={item.id} type="button" onClick={() => setTemplateKey(item.key)} aria-pressed={selected} className={'overflow-hidden rounded-xl border text-right transition hover:shadow-md ' + (selected ? 'border-indigo-600 ring-2 ring-indigo-200' : 'border-slate-200')}>
                                        <span className="block p-3" style={{ background }}><span className="mb-3 block h-2 w-12 rounded-full" style={{ background: primary }} /><span className="block rounded-lg p-2" style={{ background: primary + '22', border: '1px solid ' + primary + '66' }}><span className="block h-2 w-3/4 rounded-full" style={{ background: accent }} /><span className="mt-2 flex gap-1"><span className="h-8 flex-1 rounded" style={{ background: accent + '44' }} /><span className="h-8 flex-1 rounded" style={{ background: primary + '66' }} /></span></span></span>
                                        <span className="flex items-center justify-between gap-1 bg-white px-2 py-2"><span className="truncate text-[11px] font-black">{item.name}</span><span className={'text-[9px] font-black ' + (selected ? 'text-indigo-700' : 'text-slate-400')}>{selected ? 'محدد ✓' : 'اختيار'}</span></span>
                                    </button>;
                                })}
                            </div>
                            <p className="mt-2 text-[10px] text-slate-500">التغيير يظهر في المعاينة الحية؛ اضغط «حفظ التعديلات» لتثبيت القالب.</p>
                        </div>
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
                        <div className="mt-4">
                            <p className="mb-2 text-xs font-black text-slate-500">اختار هوية جاهزة — المعاينة تتحدث فورًا</p>
                            <div className="grid grid-cols-2 gap-2">
                                {themePresets.map((preset) => {
                                    const activePreset = themePrimary.toLowerCase() === preset.primary.toLowerCase() && themeAccent.toLowerCase() === preset.accent.toLowerCase() && themeBackground.toLowerCase() === preset.background.toLowerCase() && themeForeground.toLowerCase() === preset.foreground.toLowerCase();
                                    return <button key={preset.id} type="button" onClick={() => applyThemePreset(preset)} aria-pressed={activePreset} className={'flex min-w-0 items-center gap-2 rounded-xl border p-2 text-right transition hover:-translate-y-0.5 hover:shadow-md ' + (activePreset ? 'border-slate-900 bg-slate-50 ring-2 ring-slate-900/10' : 'border-slate-200 bg-white')}>
                                        <span className="flex h-10 w-10 shrink-0 overflow-hidden rounded-lg border border-black/10"><span className="flex-1" style={{ background: preset.swatches[0] }} /><span className="flex-1" style={{ background: preset.swatches[1] }} /><span className="flex-1" style={{ background: preset.swatches[2] }} /></span>
                                        <span className="min-w-0 flex-1"><span className="block truncate text-[11px] font-black">{preset.name}</span><span className="mt-0.5 block text-[9px] text-slate-400">{activePreset ? 'محدد حاليًا' : 'تجربة فورية'}</span></span>
                                    </button>;
                                })}
                            </div>
                        </div>
                        <form onSubmit={saveTheme} className="mt-4 space-y-3">
                            <div className="grid grid-cols-2 gap-2">
                                <label className="rounded-xl border border-slate-200 p-2"><span className="mb-1 block text-[10px] font-black text-slate-500">primary</span><div className="flex items-center gap-2"><input type="color" value={themePrimary} onChange={(e) => setThemePrimary(e.target.value)} className="h-9 w-10 rounded-lg border-0 bg-transparent p-0" /><input value={themePrimary} onChange={(e) => setThemePrimary(e.target.value)} maxLength={7} className="min-w-0 flex-1 rounded-lg border px-2 py-1.5 text-xs font-mono uppercase" /></div></label><label className="rounded-xl border border-slate-200 p-2"><span className="mb-1 block text-[10px] font-black text-slate-500">accent</span><div className="flex items-center gap-2"><input type="color" value={themeAccent} onChange={(e) => setThemeAccent(e.target.value)} className="h-9 w-10 rounded-lg border-0 bg-transparent p-0" /><input value={themeAccent} onChange={(e) => setThemeAccent(e.target.value)} maxLength={7} className="min-w-0 flex-1 rounded-lg border px-2 py-1.5 text-xs font-mono uppercase" /></div></label><label className="rounded-xl border border-slate-200 p-2"><span className="mb-1 block text-[10px] font-black text-slate-500">background</span><div className="flex items-center gap-2"><input type="color" value={themeBackground} onChange={(e) => setThemeBackground(e.target.value)} className="h-9 w-10 rounded-lg border-0 bg-transparent p-0" /><input value={themeBackground} onChange={(e) => setThemeBackground(e.target.value)} maxLength={7} className="min-w-0 flex-1 rounded-lg border px-2 py-1.5 text-xs font-mono uppercase" /></div></label><label className="rounded-xl border border-slate-200 p-2"><span className="mb-1 block text-[10px] font-black text-slate-500">foreground</span><div className="flex items-center gap-2"><input type="color" value={themeForeground} onChange={(e) => setThemeForeground(e.target.value)} className="h-9 w-10 rounded-lg border-0 bg-transparent p-0" /><input value={themeForeground} onChange={(e) => setThemeForeground(e.target.value)} maxLength={7} className="min-w-0 flex-1 rounded-lg border px-2 py-1.5 text-xs font-mono uppercase" /></div></label>
                            </div>
                            <label className="block"><span className="mb-1 block text-[10px] font-black text-slate-500">زوايا العناصر</span><select value={themeRadius} onChange={(e) => setThemeRadius(e.target.value as typeof themeRadius)} className="w-full rounded-xl border px-3 py-2 text-sm"><option value="sm">ناعمة</option><option value="md">متوسطة</option><option value="lg">دائرية</option><option value="xl">Premium</option><option value="2xl">Extra Round</option></select></label>
                            <button type="submit" className="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-black text-white">حفظ الـTheme</button>
                        </form>
                    </section>

                    <section id="menu-content-studio" className="order-first scroll-mt-24 rounded-[2rem] border border-slate-200 bg-white p-5 pb-24 shadow-sm lg:pb-5">
                        <div className="flex items-center justify-between">
                            <div>
                                <span className="text-xs font-bold tracking-[.14em] text-amber-600">MENU CONTENT STUDIO</span>
                                <h2 className="mt-1 text-lg font-black">إدارة المنتجات والأقسام</h2>
                            </div>
                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{menu.categories.length}</span>
                        </div>

                        {contentTab === 'categories' && <form onSubmit={addCategory} className="mt-4 space-y-2 rounded-2xl border border-amber-100 bg-amber-50/50 p-3">
                            <input value={categoryName} onChange={(e) => setCategoryName(e.target.value)} placeholder="اسم القسم" maxLength={120} required className="w-full rounded-xl border border-slate-200 px-3 py-2 outline-none focus:border-amber-500" />
                            <textarea value={categoryDescription} onChange={(e) => setCategoryDescription(e.target.value)} placeholder="وصف اختياري" maxLength={1000} rows={2} className="w-full rounded-xl border border-slate-200 px-3 py-2 outline-none focus:border-amber-500" />
                            <button type="submit" className="w-full rounded-xl bg-amber-500 px-4 py-2.5 font-black text-white">+ إضافة قسم</button>
                        </form>}

                        <div className="mt-5 grid grid-cols-2 gap-1.5 rounded-2xl bg-slate-100 p-1.5" role="tablist" aria-label="إدارة محتويات المنيو">
                            <button type="button" role="tab" aria-selected={contentTab === 'products'} onClick={() => setContentTab('products')} className={'rounded-xl px-3 py-3 text-sm font-black transition ' + (contentTab === 'products' ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-500 hover:bg-white hover:text-slate-900')}>
                                المنتجات <span className="mr-1 text-[10px] opacity-70">{productStats.total.toLocaleString('ar-EG')}</span>
                            </button>
                            <button type="button" role="tab" aria-selected={contentTab === 'categories'} onClick={() => setContentTab('categories')} className={'rounded-xl px-3 py-3 text-sm font-black transition ' + (contentTab === 'categories' ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-500 hover:bg-white hover:text-slate-900')}>
                                الأقسام <span className="mr-1 text-[10px] opacity-70">{menu.categories.length.toLocaleString('ar-EG')}</span>
                            </button>
                        </div>

                        {contentTab === 'categories' && <div className="mt-4 space-y-2">
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
                        </div>}

                        {contentTab === 'products' && <div className="mt-4 space-y-3">
                            <div className="rounded-2xl border border-slate-200 bg-slate-50 p-3.5">
                                <label className="relative block">
                                    <span className="sr-only">البحث عن منتج</span>
                                    <span aria-hidden="true" className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">⌕</span>
                                    <input type="search" value={productSearch} onChange={event => setProductSearch(event.target.value)} placeholder="ابحث باسم المنتج أو الوصف…" className="w-full rounded-xl border border-slate-200 bg-white py-3 pr-10 pl-3 text-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100" />
                                </label>
                                <div className="mt-3 flex flex-wrap gap-1.5" role="group" aria-label="فلترة المنتجات">
                                    {([
                                        { key: 'all', label: 'الكل', count: productStats.total },
                                        { key: 'available', label: 'متاح', count: productStats.available },
                                        { key: 'hidden', label: 'مخفي', count: productStats.total - productStats.available },
                                        { key: 'featured', label: 'مميز', count: productStats.featured },
                                        { key: 'missing-image', label: 'بدون صورة', count: productStats.missingImage },
                                    ] as const).map(filter => <button key={filter.key} type="button" onClick={() => setProductStatusFilter(filter.key)} aria-pressed={productStatusFilter === filter.key} className={'rounded-full border px-3 py-2 text-[11px] font-black transition ' + (productStatusFilter === filter.key ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-400')}>
                                        {filter.label} <span className="mr-1 opacity-70">{filter.count.toLocaleString('ar-EG')}</span>
                                    </button>)}
                                </div>
                                <div className="mt-3 flex items-center justify-between border-t border-slate-200 pt-3 text-xs">
                                    <span className="text-slate-500">النتائج المطابقة</span>
                            <span className="font-black text-slate-900">{visibleProducts.length.toLocaleString('ar-EG')} منتج</span>
                                </div>
                            </div>
                            <section className="mt-4 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                                <div className="border-b border-slate-100 bg-gradient-to-l from-slate-50 via-white to-white p-4 sm:p-5">
                                    <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                                        <div>
                                            <p className="text-[10px] font-black tracking-[.2em] text-amber-600">PRODUCT CATALOG</p>
                                            <h3 className="mt-1 text-xl font-black">كتالوج المنتجات</h3>
                                            <p className="mt-1 text-xs text-slate-500">كل منتجات البراند في مساحة واحدة — صور، أسعار، حالة البيع والتعديل السريع.</p>
                                        </div>
                                        <div className="flex flex-col gap-2 sm:flex-row">
                                            <label className="min-w-0 sm:min-w-52">
                                                <span className="sr-only">فلترة المنتجات حسب القسم</span>
                                                <select value={productCategoryFilter} onChange={event => setProductCategoryFilter(event.target.value === 'all' ? 'all' : Number(event.target.value))} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100">
                                                    <option value="all">كل الأقسام</option>
                                                    {menu.categories.map(category => <option key={category.id} value={category.id}>{category.name}</option>)}
                                                </select>
                                            </label>
                                            <button type="button" disabled={menu.categories.length === 0} onClick={() => { if (showProductForm) closeProductForm(); else { setProductCategoryId(menu.categories[0]?.id ?? null); setShowProductForm(true); setProductImageError(''); } }} className="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40">
                                                <span className="text-lg">＋</span>{showProductForm ? 'إغلاق إضافة منتج' : 'إضافة منتج جديد'}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                {showProductForm && <form onSubmit={event => { if (productCategoryId) addProduct(event, productCategoryId); else event.preventDefault(); }} className="grid gap-4 border-b border-amber-100 bg-amber-50/40 p-4 sm:p-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_18rem]">
                                    {productImageError && <p role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 xl:col-span-3">{productImageError}</p>}
                                    <div className="space-y-3">
                                        <label className="block text-xs font-black">اسم المنتج<input value={productName} onChange={event => setProductName(event.target.value)} placeholder="مثال: Classic Burger" maxLength={160} required className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-normal outline-none focus:border-amber-400" /></label>
                                        <label className="block text-xs font-black">القسم<select value={productCategoryId ?? ''} onChange={event => setProductCategoryId(event.target.value ? Number(event.target.value) : null)} required className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-normal"><option value="" disabled>اختار القسم</option>{menu.categories.map(category => <option key={category.id} value={category.id}>{category.name}</option>)}</select></label>
                                        <label className="block text-xs font-black">الوصف<textarea value={productDescription} onChange={event => setProductDescription(event.target.value)} placeholder="وصف مختصر يوضح المنتج" maxLength={2000} rows={3} className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-normal" /></label>
                                    </div>
                                    <div className="space-y-3">
                                        <label className="block text-xs font-black">السعر (ج.م)<input value={productPrice} onChange={event => setProductPrice(event.target.value)} type="number" min="0" step="0.01" placeholder="0.00" required className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-normal" /></label>
                                        <label className="block text-xs font-black">رابط الصورة (اختياري)<input value={productImage} onChange={event => setProductImage(event.target.value)} placeholder="https://…" className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-normal" /></label>
                                        <label onDragOver={event => event.preventDefault()} onDrop={handleNewProductImageDrop} className="group relative flex min-h-36 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-slate-300 bg-white text-center transition hover:border-amber-400 hover:bg-amber-50/50">
                                            {productImagePreview || productImage.trim() ? <img src={productImagePreview || (productImage.startsWith('http') || productImage.startsWith('/') ? productImage : '/storage/' + productImage)} alt="معاينة صورة المنتج" className="absolute inset-0 h-full w-full object-cover" /> : <><span className="grid h-11 w-11 place-items-center rounded-2xl bg-slate-100 text-2xl">↥</span><span className="mt-2 text-sm font-black">ارفع صورة المنتج</span><span className="mt-1 text-[10px] text-slate-400">اسحب الصورة هنا أو اضغط للاختيار</span></>}
                                            <span className="absolute bottom-2 right-2 rounded-lg bg-slate-950/90 px-3 py-2 text-[10px] font-black text-white">{productImageFile ? 'تغيير الصورة' : 'اختيار صورة'}</span>
                                            <input type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" onChange={handleNewProductImageInput} />
                                        </label>
                                        {productUploadProgress !== null && <div className="rounded-xl border border-amber-200 bg-white p-3" aria-live="polite">
                                            <div className="mb-2 flex items-center justify-between gap-2 text-xs"><span className="font-black text-slate-700">جاري رفع صورة المنتج</span><span className="font-black tabular-nums text-amber-700">{productUploadProgress}%</span></div>
                                            <div className="h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="تقدم رفع صورة المنتج" aria-valuemin={0} aria-valuemax={100} aria-valuenow={productUploadProgress}><div className="h-full rounded-full bg-amber-500 transition-[width] duration-150" style={{ width: `${productUploadProgress}%` }} /></div>
                                        </div>}
                                        <label className="flex items-center gap-2 text-xs font-bold"><input type="checkbox" checked={productFeatured} onChange={event => setProductFeatured(event.target.checked)} className="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500" />إضافة إلى المنتجات المميزة ⭐</label>
                                    </div>
                                    <div className="flex flex-col justify-end gap-2 xl:pb-1">
                                        <div className="rounded-2xl border border-amber-100 bg-white p-4"><p className="text-xs font-black text-slate-800">نصيحة للصور</p><p className="mt-1 text-xs leading-6 text-slate-500">استخدم صورة واضحة مربعة أو أفقية بإضاءة جيدة. الصيغ المقبولة JPG وPNG وWebP، حتى 5MB.</p></div>
                                        <button type="submit" disabled={isSavingProduct || !productCategoryId || !productName.trim() || !productPrice} className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-amber-500 px-4 py-3 text-sm font-black text-white transition hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-50">{isSavingProduct && <span className="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true" />}{isSavingProduct ? productImageFile ? `جاري الحفظ والرفع ${productUploadProgress ?? 0}%` : 'جاري حفظ المنتج…' : 'حفظ وإضافة المنتج'}</button>
                                        <button type="button" disabled={isSavingProduct} onClick={closeProductForm} className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-600 disabled:cursor-not-allowed disabled:opacity-40">إلغاء</button>
                                    </div>
                                </form>}
                                <div className="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 sm:p-5 xl:grid-cols-3 2xl:grid-cols-4">
                                    {visibleProducts.map(({ category, product, index }) => (
                                            <article key={product.id} className="flex min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-lg">
                                            <div draggable onDragStart={(e) => beginDrag(e, 'product', product.id, category.id)} onDragOver={(e) => allowDrop(e, 'product', product.id)} onDrop={(e) => { e.preventDefault(); reorderProductsByDrop(category, product.id); }} onDragEnd={finishDrag} className={'flex flex-1 flex-col cursor-grab active:cursor-grabbing ' + (dragOver?.type === 'product' && dragOver.id === product.id ? 'ring-2 ring-inset ring-amber-400 ' : '')}>
                                                {editingProductId === product.id ? (
                                                    <form onSubmit={(e) => saveProduct(e, category.id, product.id)} className="space-y-3">
                                                        <div className="flex items-start gap-3">
                                                            <div className="relative h-20 w-20 shrink-0 overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
                                                                {(editingProductImagePreview || (editingProductImage && (editingProductImage.startsWith('http') || editingProductImage.startsWith('/') ? editingProductImage : '/storage/' + editingProductImage))) ? (
                                                                    <img src={editingProductImagePreview || (editingProductImage.startsWith('http') || editingProductImage.startsWith('/') ? editingProductImage : '/storage/' + editingProductImage)} alt="" className="h-full w-full object-cover" />
                                                                ) : (
                                                                    <div className="flex h-full w-full items-center justify-center text-2xl">🍽️</div>
                                                                )}
                                                                <span className="absolute bottom-1 right-1 rounded-full bg-slate-950/75 px-1.5 py-0.5 text-[8px] font-black text-white">LIVE</span>
                                                            </div>
                                                            <div className="min-w-0 flex-1">
                                                                <input value={editingProductName} onChange={(e) => setEditingProductName(e.target.value)} placeholder="اسم المنتج" required className="w-full rounded-lg border px-3 py-2 text-sm font-bold" />
                                                                <div className="mt-2 grid grid-cols-[1fr_7rem] gap-2">
                                                                    <input value={editingProductDescription} onChange={(e) => setEditingProductDescription(e.target.value)} placeholder="وصف المنتج" maxLength={2000} className="rounded-lg border px-3 py-2 text-xs" />
                                                                    <input value={editingProductPrice} onChange={(e) => setEditingProductPrice(e.target.value)} type="number" min="0" step="0.01" required placeholder="السعر" className="rounded-lg border px-3 py-2 text-sm" />
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div className="grid gap-2 sm:grid-cols-[1fr_auto]">
                                                            <label
                                                                onDragOver={(e) => e.preventDefault()}
                                                                onDrop={(e) => handleProductImageDrop(e, category.id, product.id)}
                                                                className="flex min-h-16 cursor-pointer items-center justify-between gap-3 rounded-xl border border-dashed border-slate-300 bg-white px-3 py-2 hover:border-amber-400 hover:bg-amber-50/40"
                                                            >
                                                                <div className="min-w-0">
                                                                    <p className="text-xs font-black">اسحب الصورة هنا أو اختار ملف</p>
                                                                    <p className="mt-0.5 text-[10px] text-slate-400">JPG / PNG / WebP · حتى 5MB · المعاينة فورية</p>
                                                                </div>
                                                                <span className="shrink-0 rounded-lg bg-slate-900 px-2.5 py-1.5 text-[10px] font-black text-white">اختيار صورة</span>
                                                                <input type="file" accept="image/jpeg,image/png,image/webp" className="hidden" onChange={(e) => handleProductImageInput(e, category.id, product.id)} />
                                                            </label>
                                                            <div className="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2">
                                                                <label className="flex items-center gap-2 text-xs font-bold">
                                                                    <input type="checkbox" checked={editingProductFeatured} onChange={(e) => setEditingProductFeatured(e.target.checked)} />
                                                                    ⭐ مميز
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <div className="flex gap-2">
                                                            <button type="submit" className="flex-1 rounded-lg bg-slate-900 px-3 py-2.5 text-xs font-black text-white">حفظ التعديلات</button>
                                                            <button type="button" onClick={closeEditingProduct} className="rounded-lg border px-3 py-2.5 text-xs font-bold">إلغاء</button>
                                                        </div>
                                                    </form>
                                                ) : (
                                                    <div className="flex h-full flex-col">
                                                        <div className="group/image relative h-44 overflow-hidden bg-slate-100 sm:h-48">
                                                            {(imageUploadPreviews[product.id] || product.image_path) ? <img src={imageUploadPreviews[product.id] ?? (product.image_path!.startsWith('http') || product.image_path!.startsWith('/') || product.image_path!.startsWith('blob:') || product.image_path!.startsWith('data:') ? product.image_path! : '/storage/' + product.image_path)} alt={product.name} loading="lazy" decoding="async" className="h-full w-full object-cover transition duration-500 group-hover/image:scale-105" /> : <div className="flex h-full flex-col items-center justify-center bg-[radial-gradient(circle_at_30%_20%,#fff_0%,#f1f5f9_62%,#e2e8f0_100%)] text-slate-400"><span className="grid h-14 w-14 place-items-center rounded-2xl border border-white bg-white/80 text-3xl shadow-sm">▧</span><span className="mt-2 text-xs font-black">أضف صورة المنتج</span><span className="mt-1 text-[10px]">الصورة هتظهر هنا</span></div>}
                                                            <div className="absolute inset-x-0 top-0 flex items-start justify-between gap-2 bg-gradient-to-b from-black/45 to-transparent p-3">
                                                                <span className={'rounded-full px-2.5 py-1 text-[10px] font-black backdrop-blur ' + (product.is_available ? 'bg-emerald-400/95 text-emerald-950' : 'bg-white/90 text-slate-600')}>{product.is_available ? '● متاح للبيع' : '○ مخفي'}</span>
                                                                {product.is_featured && <span className="rounded-full bg-amber-300 px-2.5 py-1 text-[10px] font-black text-amber-950">★ مميز</span>}
                                                            </div>
                                                            <label onDragOver={event => event.preventDefault()} onDrop={event => handleProductImageDrop(event, category.id, product.id)} className="absolute bottom-3 left-3 inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-white/70 bg-white/95 px-3 py-2 text-[11px] font-black text-slate-800 shadow-lg backdrop-blur transition hover:bg-white">
                                                                <span aria-hidden="true">{imageUploadProgress[product.id] !== undefined ? '◌' : '↥'}</span>{imageUploadProgress[product.id] !== undefined ? `جاري الرفع ${imageUploadProgress[product.id]}%` : product.image_path ? 'تغيير الصورة' : 'رفع صورة'}
                                                                <input type="file" accept="image/jpeg,image/png,image/webp" disabled={imageUploadProgress[product.id] !== undefined} className="sr-only" onChange={event => { const file = event.target.files?.[0]; if (file) uploadProductImage(file, category.id, product.id); event.target.value = ''; }} />
                                                            </label>
                                                            {imageUploadProgress[product.id] !== undefined && <div className="absolute inset-x-3 bottom-14 rounded-xl border border-white/20 bg-slate-950/85 p-3 text-white shadow-xl backdrop-blur" aria-live="polite">
                                                                <div className="mb-2 flex items-center justify-between gap-2 text-[10px]"><span className="font-black">جاري حفظ الصورة</span><span className="tabular-nums">{imageUploadProgress[product.id]}%</span></div>
                                                                <div className="h-1.5 overflow-hidden rounded-full bg-white/20" role="progressbar" aria-label={'تقدم رفع صورة ' + product.name} aria-valuemin={0} aria-valuemax={100} aria-valuenow={imageUploadProgress[product.id]}><div className="h-full rounded-full bg-amber-400 transition-[width] duration-150" style={{ width: `${imageUploadProgress[product.id]}%` }} /></div>
                                                            </div>}
                                                            <span className="absolute bottom-3 right-3 rounded-xl bg-slate-950/75 px-2.5 py-2 text-[9px] font-bold text-white backdrop-blur">{category.name}</span>
                                                        </div>
                                                        <div className="flex flex-1 flex-col p-4">
                                                            <div className="flex items-start justify-between gap-3">
                                                                <div className="min-w-0 flex-1">
                                                                    <p className="text-[10px] font-black tracking-wide text-slate-400">{category.name}</p>
                                                                    <h4 className="mt-1 line-clamp-2 text-base font-black leading-6 text-slate-900">{product.name}</h4>
                                                                    {product.description && <p className="mt-1.5 line-clamp-2 text-xs leading-5 text-slate-500">{product.description}</p>}
                                                                </div>
                                                                <div className="shrink-0 rounded-xl bg-amber-50 px-3 py-2 text-left">
                                                                    <span className="block text-[9px] font-bold text-amber-700">السعر</span>
                                                                    <strong className="mt-0.5 block whitespace-nowrap text-sm font-black text-slate-950">{Number(product.price).toLocaleString('ar-EG', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} <span className="text-[10px]">ج.م</span></strong>
                                                                </div>
                                                            </div>
                                                            <div className="mt-auto flex flex-wrap items-center gap-2 pt-4">
                                                                <div className="flex overflow-hidden rounded-lg border border-slate-200 bg-white">
                                                                    <button type="button" aria-label="نقل المنتج لأعلى" title="نقل لأعلى" disabled={index === 0} onClick={() => moveProduct(category, index, -1)} className="px-2.5 py-2 text-xs font-black text-slate-600 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-25">↑</button>
                                                                    <span className="w-px bg-slate-200" />
                                                                    <button type="button" aria-label="نقل المنتج لأسفل" title="نقل لأسفل" disabled={index === category.products.length - 1} onClick={() => moveProduct(category, index, 1)} className="px-2.5 py-2 text-xs font-black text-slate-600 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-25">↓</button>
                                                                </div>
                                                                <button type="button" onClick={() => startEditingProduct(product)} className="flex-1 rounded-lg bg-slate-950 px-3 py-2 text-xs font-black text-white transition hover:bg-slate-800">تعديل</button>
                                                                <button type="button" onClick={() => toggleProduct(category, product)} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50">{product.is_available ? 'إخفاء' : 'تفعيل'}</button>
                                                                <button type="button" aria-label={'حذف ' + product.name} onClick={() => deleteProduct(category, product)} className="rounded-lg border border-rose-200 px-3 py-2 text-xs font-black text-rose-600 transition hover:bg-rose-50">حذف</button>
                                                            </div>
                                                            {product.image_path && <button type="button" onClick={() => removeProductImage(category.id, product.id)} className="mt-2 self-start text-[10px] font-bold text-rose-600 transition hover:text-rose-700">حذف الصورة</button>}
                                                            {imageUploadErrors[product.id] && <p role="alert" className="mt-2 text-[10px] font-bold text-rose-600">{imageUploadErrors[product.id]}</p>}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                            <details open={expandedVariantsProductId === product.id} onToggle={event => { const isOpen = event.currentTarget.open; setExpandedVariantsProductId(current => isOpen ? product.id : current === product.id ? null : current); if (!isOpen) setVariantProductId(current => current === product.id ? null : current); }} className="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-white">
                                                <summary className="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-3 transition hover:bg-slate-50">
                                                    <span className="text-xs font-black text-slate-800">المقاسات والأحجام <span className="mr-1 text-[10px] font-bold text-slate-400">{product.variants?.length ?? 0} اختيار</span></span>
                                                    <span className="rounded-lg bg-slate-100 px-2.5 py-1.5 text-[10px] font-black text-slate-600">إدارة الخيارات ↓</span>
                                                </summary>
                                                {expandedVariantsProductId === product.id && <div className="border-t border-slate-100 p-3">
                                                    <div className="mb-3 flex justify-end">
                                                        <button type="button" onClick={() => setVariantProductId(variantProductId === product.id ? null : product.id)} className="rounded-lg bg-slate-950 px-3 py-2 text-[11px] font-black text-white">{variantProductId === product.id ? 'إغلاق النموذج' : '+ إضافة اختيار'}</button>
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
                                                </div>}
                                            </details>
                                            <details open={expandedModifiersProductId === product.id} onToggle={event => { const isOpen = event.currentTarget.open; setExpandedModifiersProductId(current => isOpen ? product.id : current === product.id ? null : current); if (!isOpen) setModifierProductId(current => current === product.id ? null : current); }} className="mt-2 overflow-hidden rounded-xl border border-emerald-200 bg-emerald-50/40">
                                                <summary className="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-3 transition hover:bg-emerald-50">
                                                    <span className="text-xs font-black text-emerald-800">الإضافات والاختيارات <span className="mr-1 text-[10px] font-bold text-emerald-700/60">{product.modifiers?.length ?? 0} إضافة</span></span>
                                                    <span className="rounded-lg bg-white px-2.5 py-1.5 text-[10px] font-black text-emerald-800">إدارة الإضافات ↓</span>
                                                </summary>
                                                {expandedModifiersProductId === product.id && <div className="border-t border-emerald-200 p-3">
                                                    <div className="mb-3 flex justify-end">
                                                        <button type="button" onClick={() => setModifierProductId(modifierProductId === product.id ? null : product.id)} className="rounded-lg bg-emerald-700 px-3 py-2 text-[11px] font-black text-white">{modifierProductId === product.id ? 'إغلاق النموذج' : '+ إضافة Modifier'}</button>
                                                    </div>
                                                {modifierProductId === product.id && (
                                                    <form onSubmit={(e) => addModifier(e, category.id, product.id)} className="mt-2 space-y-2">
                                                        <input value={modifierName} onChange={(e) => setModifierName(e.target.value)} placeholder="مثال: Extra Cheese" maxLength={120} required className="w-full rounded-lg border px-2 py-1.5 text-xs" />
                                                        <div className="grid grid-cols-[1fr_auto] gap-2">
                                                            <input value={modifierDelta} onChange={(e) => setModifierDelta(e.target.value)} type="number" step="0.01" placeholder="+ / - من السعر" required className="rounded-lg border px-2 py-1.5 text-xs" />
                                                            <label className="flex items-center gap-1 rounded-lg border bg-white px-2 text-[10px] font-bold"><input type="checkbox" checked={modifierRequired} onChange={(e) => setModifierRequired(e.target.checked)} /> مطلوب</label>
                                                        </div>
                                                        <label className="block text-[10px] font-bold">مجموعة اختيارات (اختياري)<input value={modifierGroup} onChange={(e) => setModifierGroup(e.target.value)} placeholder="مثال: اختار صوص" maxLength={120} className="mt-1 w-full rounded-lg border px-2 py-1.5 text-xs font-normal" /></label>
                                                        {modifierGroup.trim() && <div className="grid grid-cols-2 gap-2"><label className="text-[10px] font-bold">أقل اختيارات<input type="number" min="0" max="20" value={modifierGroupMin} onChange={(e) => setModifierGroupMin(e.target.value)} className="mt-1 w-full rounded-lg border px-2 py-1.5 text-xs font-normal" /></label><label className="text-[10px] font-bold">أقصى اختيارات<input type="number" min="1" max="20" value={modifierGroupMax} onChange={(e) => setModifierGroupMax(e.target.value)} className="mt-1 w-full rounded-lg border px-2 py-1.5 text-xs font-normal" /></label><p className="col-span-2 text-[10px] text-slate-500">كل الخيارات بنفس اسم المجموعة لازم تستخدم نفس الحد الأدنى والأقصى.</p></div>}
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
                                                                    <label className="block text-[10px] font-bold">مجموعة اختيارات<input value={editingModifierGroup} onChange={(e) => setEditingModifierGroup(e.target.value)} maxLength={120} placeholder="اتركه فارغًا للإضافة المستقلة" className="mt-1 w-full rounded border px-2 py-1.5 text-xs font-normal" /></label>
                                                                    {editingModifierGroup.trim() && <div className="grid grid-cols-2 gap-2"><label className="text-[10px] font-bold">الحد الأدنى<input type="number" min="0" max="20" value={editingModifierGroupMin} onChange={(e) => setEditingModifierGroupMin(e.target.value)} className="mt-1 w-full rounded border px-2 py-1.5 text-xs font-normal" /></label><label className="text-[10px] font-bold">الحد الأقصى<input type="number" min="1" max="20" value={editingModifierGroupMax} onChange={(e) => setEditingModifierGroupMax(e.target.value)} className="mt-1 w-full rounded border px-2 py-1.5 text-xs font-normal" /></label></div>}
                                                                    <div className="flex gap-1.5"><button type="submit" className="flex-1 rounded bg-slate-900 px-2 py-1 text-[11px] font-bold text-white">حفظ</button><button type="button" onClick={() => setEditingModifierId(null)} className="rounded border px-2 py-1 text-[11px] font-bold">إلغاء</button></div>
                                                                </form>
                                                            ) : (
                                                                <div className="flex items-center gap-1.5">
                                                                    <div className="min-w-0 flex-1"><span className="mr-1 text-[9px] text-slate-400">⠿</span><p className="truncate text-xs font-black">{modifier.name}</p><p className="text-[10px] text-slate-500">{Number(modifier.price_delta) >= 0 ? '+' : ''}{Number(modifier.price_delta).toFixed(2)} ج.م · {modifier.group_name ? `${modifier.group_name} (${modifier.group_min_choices ?? 0}–${modifier.group_max_choices ?? 1})` : modifier.is_required ? 'مطلوب' : 'اختياري'}</p></div>
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
                                                </div>}
                                            </details>
                                            </article>
                                        ))}
                                    {visibleProducts.length === 0 && <div className="col-span-full rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center">
                                        <span className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-white text-2xl text-slate-400 shadow-sm">⌕</span>
                                        <p className="mt-3 font-black">{menu.categories.length === 0 ? 'ابدأ بإضافة أول قسم للمنيو' : 'مفيش منتجات مطابقة'}</p>
                                        <p className="mt-1 text-xs leading-6 text-slate-500">{menu.categories.length === 0 ? 'افتح تبويب الأقسام وأنشئ قسمًا، وبعدها أضف المنتجات والصور.' : 'غيّر البحث أو الفلاتر لعرض نتائج أخرى.'}</p>
                                        {menu.categories.length > 0 && <button type="button" onClick={() => { setProductSearch(''); setProductStatusFilter('all'); setProductCategoryFilter('all'); }} className="mt-3 rounded-lg bg-slate-950 px-4 py-2 text-xs font-black text-white">عرض كل المنتجات</button>}
                                    </div>}
                                </div>
                            </section>
                        </div>}
                    </section>
                </aside>

                <section className="min-w-0 overflow-hidden rounded-[2rem] border border-slate-200 bg-slate-950 shadow-2xl">
                    <div className="border-b border-white/10 bg-slate-950 px-4 py-3 text-white">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-3">
                                <div>
                                    <p className="text-[10px] font-black uppercase tracking-[0.2em] text-amber-400">LIVE PREVIEW</p>
                                    <p className="text-xs text-white/60">كل تغيير في الـTheme والاسم والـTemplate يظهر هنا فورًا</p>
                                </div>
                                <span className="hidden rounded-full border border-emerald-400/30 bg-emerald-400/10 px-2.5 py-1 text-[10px] font-black text-emerald-300 sm:inline-flex">● LIVE DRAFT</span>
                            </div>
                            <div className="flex items-center gap-1 rounded-xl border border-white/10 bg-white/5 p-1">
                                {([
                                    ['mobile', 'Mobile'],
                                    ['tablet', 'Tablet'],
                                    ['desktop', 'Desktop'],
                                ] as const).map(([device, label]) => (
                                    <button
                                        key={device}
                                        type="button"
                                        onClick={() => setPreviewDevice(device)}
                                        className={'rounded-lg px-3 py-1.5 text-[10px] font-black transition ' + (previewDevice === device ? 'bg-white text-slate-900 shadow' : 'text-white/60 hover:text-white')}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="nx-studio-grid min-h-[760px] overflow-auto p-4 sm:p-6 lg:p-8">
                        <div className="mx-auto transition-[width] duration-300 ease-out">
                            <div
                                className={
                                    previewDevice === 'mobile'
                                        ? 'w-[390px] max-w-full'
                                        : previewDevice === 'tablet'
                                            ? 'w-[768px] max-w-full'
                                            : 'w-full max-w-[1100px]'
                                }
                            >
                                <div className={previewDevice === 'mobile' ? 'overflow-hidden rounded-[2.5rem] border-[8px] border-slate-900 bg-black shadow-2xl' : 'overflow-hidden rounded-[1.75rem] border border-white/10 bg-black shadow-2xl'}>
                                    {previewDevice === 'mobile' && <div className="flex h-7 items-center justify-center bg-slate-900"><div className="h-1.5 w-20 rounded-full bg-white/20" /></div>}
                                    <div className={previewDevice === 'mobile' ? 'max-h-[720px] overflow-y-auto' : 'max-h-[760px] overflow-y-auto'}>
                                        <TemplateRenderer menu={previewMenu} restaurantName={restaurantName ?? 'NEXORA'} preview previewCategoryId={previewCategoryId} />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    );
}
