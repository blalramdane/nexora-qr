import { Head, Link } from '@inertiajs/react';

export default function Home() {
    return (
        <>
            <Head title="Nexora QR" />
            <main className="min-h-screen bg-slate-950 text-white flex items-center justify-center p-6">
                <div className="max-w-2xl text-center">
                    <p className="mb-3 text-sm font-medium uppercase tracking-[0.25em] text-cyan-400">
                        Nexora QR
                    </p>
                    <h1 className="text-4xl font-bold tracking-tight sm:text-6xl">
                        Digital Menu Platform
                    </h1>
                    <p className="mt-5 text-lg text-slate-300">
                        Foundation ready. Authentication, tenancy, menu and ordering
                        will be built on top of this application shell.
                    </p>
                    <Link
                        href="/"
                        className="mt-8 inline-flex rounded-xl bg-white px-5 py-3 font-semibold text-slate-950"
                    >
                        Nexora Home
                    </Link>
                </div>
            </main>
        </>
    );
}