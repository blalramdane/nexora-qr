import { Head, Link, useForm } from '@inertiajs/react';

export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });
    return <main className="min-h-screen bg-slate-950 text-white flex items-center justify-center p-6">
        <Head title="Login" />
        <form onSubmit={e => { e.preventDefault(); form.post('/login'); }} className="w-full max-w-md space-y-5 rounded-2xl bg-white/5 p-8">
            <h1 className="text-3xl font-bold">Sign in</h1>
            <input className="w-full rounded-xl bg-white/10 p-3" type="email" placeholder="Email" value={form.data.email} onChange={e => form.setData('email', e.target.value)} />
            <input className="w-full rounded-xl bg-white/10 p-3" type="password" placeholder="Password" value={form.data.password} onChange={e => form.setData('password', e.target.value)} />
            {form.errors.email && <p className="text-red-400">{form.errors.email}</p>}
            <button disabled={form.processing} className="w-full rounded-xl bg-white px-4 py-3 font-semibold text-slate-950">Sign in</button>
            <Link href="/register" className="block text-center text-cyan-400">Create account</Link>
        </form>
    </main>;
}