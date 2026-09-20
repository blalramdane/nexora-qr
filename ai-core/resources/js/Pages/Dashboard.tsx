import { Head, useForm } from '@inertiajs/react';

type Props = { restaurant: { id: number; name: string; slug: string }; branchCount: number; stats: { categories:number; products:number; tables:number; today_orders:number; today_revenue:string } };

export default function Dashboard({ restaurant, branchCount, stats }: Props) {
    const logout = useForm();
    return <main className="min-h-screen bg-slate-100 p-6">
        <Head title="Dashboard" />
        <div className="mx-auto max-w-6xl">
            <div className="flex items-center justify-between">
                <div><p className="text-sm text-slate-500">Restaurant</p><h1 className="text-3xl font-bold">{restaurant.name}</h1></div>
                <div className="flex gap-3">
                    <div className="flex flex-wrap gap-2">
                        <a href="/menu" className="rounded-xl bg-white px-4 py-2 font-semibold text-slate-900 shadow-sm">Menu</a>
                        <a href="/orders" className="rounded-xl bg-white px-4 py-2 font-semibold text-slate-900 shadow-sm">Orders</a>
                        <a href="/tables" className="rounded-xl bg-white px-4 py-2 font-semibold text-slate-900 shadow-sm">Tables / QR</a>
                    </div>
                    <button onClick={() => logout.post('/logout')} className="rounded-xl bg-slate-900 px-4 py-2 text-white">Logout</button>
                </div>
            </div>
            <div className="mt-8 grid gap-4 sm:grid-cols-2">
                <div className="rounded-2xl bg-white p-6 shadow-sm"><p className="text-slate-500">Branches</p><p className="mt-2 text-3xl font-bold">{branchCount}</p></div>
                <div className="rounded-2xl bg-white p-6 shadow-sm"><p className="text-slate-500">Tenant</p><p className="mt-2 font-semibold">{restaurant.slug}</p></div>
            </div>
        </div>
    </main>;
}