import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import TemplateRenderer from './TemplateRenderer';

type Template = { id: number; key: string; name: string };

type Menu = {
    id: number;
    name: string;
    slug: string;
    is_published: boolean;
    template_id: number | null;
};

export default function MenuBuilder({ menu, template, templates }: { menu: Menu; template: Template | null; templates: Template[] }) {
    const [name, setName] = useState(menu.name);
    const [templateKey, setTemplateKey] = useState(template?.key ?? '');

    const save = (event: FormEvent) => {
        event.preventDefault();
        router.put('/menus/' + menu.id, { name, template_key: templateKey });
    };

    const remove = () => {
        if (window.confirm('حذف المنيو نهائيًا؟')) {
            router.delete('/menus/' + menu.id);
        }
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

            <main className="mx-auto grid max-w-7xl gap-6 p-4 lg:grid-cols-[22rem_1fr] lg:p-6">
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

                <section className="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-xl">
                    <TemplateRenderer menu={{ ...menu, template }} restaurantName="NEXORA Preview" preview />
                </section>
            </main>
        </div>
    );
}
