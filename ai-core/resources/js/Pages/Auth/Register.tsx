import { Head, Link, useForm } from '@inertiajs/react';

export default function Register() {
    const form = useForm({ name: '', email: '', password: '', password_confirmation: '', restaurant_name: '' });
    return <main className="min-h-screen bg-slate-950 text-white flex items-center justify-center p-6">
        <Head title="Create account" />
        <form onSubmit={e => { e.preventDefault(); form.post('/register'); }} className="w-full max-w-md space-y-4 rounded-2xl bg-white/5 p-8">
            <h1 className="text-3xl font-bold">Create your restaurant</h1>
            {(['name','email','restaurant_name','password','password_confirmation'] as const).map(name =>
                <input key={name} className="w-full rounded-xl bg-white/10 p-3" type={name.includes('password') ? 'password' : name === 'email' ? 'email' : 'text'} placeholder={name.replaceAll('_',' ')} value={form.data[name]} onChange={e => form.setData(name, e.target.value)} />
            )}
            {Object.values(form.errors).map((error, i) => <p key={i} className="text-red-400 text-sm">{error}</p>)}
            <button disabled={form.processing} className="w-full rounded-xl bg-white px-4 py-3 font-semibold text-slate-950">Create account</button>
            <Link href="/login" className="block text-center text-cyan-400">Already have an account?</Link>
        </form>
    </main>;
}