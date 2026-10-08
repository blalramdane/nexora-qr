import { Head } from '@inertiajs/react';
import TemplateRenderer from './TemplateRenderer';

export default function MenuBuilder({ menu, template }: { menu: any; template: any }) {
    return <div dir="rtl" className="min-h-screen bg-slate-100">
        <Head title={'Builder · ' + menu.name} />
        <div className="border-b border-slate-200 bg-white px-4 py-3"><div className="mx-auto flex max-w-7xl items-center justify-between"><div><span className="text-xs font-bold text-amber-600">MENU BUILDER</span><h1 className="font-black">{menu.name}</h1></div><span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{template?.name}</span></div></div>
        <div className="mx-auto max-w-7xl p-4 lg:p-6"><div className="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-xl"><TemplateRenderer menu={{ ...menu, template }} restaurantName="NEXORA Preview" preview /></div></div>
    </div>;
}
