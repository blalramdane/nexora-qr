import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import TemplateRenderer from './TemplateRenderer';

type Template = { id: number; key: string; name: string };
type Category = {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    sort_order: number;
    is_active: boolean;
    products: Array<{ id: number; name: string; description?: string | null; price: string; image_path?: string | null; is_available: boolean; is_featured: boolean; variants?: Array<{ id: number; name: string; price?: string | null; price_delta?: string }> }>;
};
type RenderableMenu = Parameters<typeof TemplateRenderer>[0]['menu'];
type Menu = RenderableMenu & {
    id: number;
    slug: string;
    is_published: boolean;
    template_id: number | null;
    categories: Category[];
};

export default function MenuBuilder({ menu, template, templates }: { menu: Menu; template: Template | null; templates: Template[] }) {
    const [name, setName] = useState(menu.name);
    const [templateKey, setTemplateKey] = useState(template?.key ?? '');
    const [categoryName, setCategoryName] = useState('');
    const [categoryDescription, setCategoryDescription] = useState('');
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editingName, setEditingName] = useState('');
    const [editingDescription, setEditingDescription] = useState('');

    const save = (event: FormEvent) => {
        event.preventDefault();
        router.put('/menus/' + menu.id, { name, template_key: templateKey });
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

    const moveCategory = (index: number, direction: -1 | 1) => {
        const nextIndex = index + direction;
        if (nextIndex < 0 || nextIndex >= menu.categories.length) return;
        const ids = menu.categories.map(c => c.id);
        [ids[index], ids[nextIndex]] = [ids[nextIndex], ids[index]];
        router.post('/menus/' + menu.id + '/categories/reorder', { category_ids: ids });
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
                                <div key={category.id} className={'rounded-2xl border p-3 ' + (category.is_active ? 'border-slate-200 bg-slate-50' : 'border-dashed border-slate-300 bg-slate-100 opacity-70')}>
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
