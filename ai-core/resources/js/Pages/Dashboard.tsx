import { Form, Head } from '@inertiajs/react';

type Restaurant = {
    id: number;
    name: string;
    slug: string;
    branches_count: number;
};

export default function Dashboard({ restaurant }: { restaurant: Restaurant }) {
    return (
        <main className="min-h-screen bg-slate-50">
            <Head title="Dashboard" />
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-5 py-4">
                    <div>
                        <p className="text-xs font-bold tracking-[0.2em] text-slate-400">NEXORA QR</p>
                        <h1 className="mt-1 text-xl font-bold">{restaurant.name}</h1>
                    </div>
                    <Form action="/logout" method="post">
                        <button className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold">خروج</button>
                    </Form>
                </div>
            </header>

            <section className="mx-auto max-w-6xl px-5 py-8">
                <div className="rounded-3xl bg-slate-950 p-7 text-white">
                    <p className="text-sm text-slate-300">Foundation</p>
                    <h2 className="mt-2 text-3xl font-bold">مطعمك جاهز نبني عليه.</h2>
                    <p className="mt-3 max-w-2xl text-slate-300">
                        الـTenant isolation شغال كأساس لكل الـfeatures الجاية.
                    </p>
                </div>

                <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div className="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <p className="text-sm text-slate-500">Restaurant ID</p>
                        <p className="mt-2 text-2xl font-bold">{restaurant.id}</p>
                    </div>
                    <div className="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <p className="text-sm text-slate-500">Branches</p>
                        <p className="mt-2 text-2xl font-bold">{restaurant.branches_count}</p>
                    </div>
                    <div className="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <p className="text-sm text-slate-500">Tenant</p>
                        <p className="mt-2 text-lg font-bold text-emerald-600">Isolated</p>
                    </div>
                </div>
            </section>
        </main>
    );
}
