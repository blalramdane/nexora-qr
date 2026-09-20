import { Head, Link, useForm } from '@inertiajs/react';

export default function Register() {
  const form = useForm({
    name:'', email:'', restaurant_name:'', restaurant_phone:'',
    password:'', password_confirmation:'',
  });

  return <main className="flex min-h-screen items-center justify-center bg-slate-950 p-6 text-white">
    <Head title="Create account" />
    <form onSubmit={e=>{e.preventDefault();form.post('/register');}} className="w-full max-w-md space-y-4 rounded-2xl bg-white/5 p-8">
      <div><p className="text-sm text-cyan-400">Nexora QR</p><h1 className="mt-1 text-3xl font-bold">Create your restaurant</h1></div>
      <input className="w-full rounded-xl bg-white/10 p-3" placeholder="Your name" value={form.data.name} onChange={e=>form.setData('name',e.target.value)} />
      <input className="w-full rounded-xl bg-white/10 p-3" type="email" placeholder="Email" value={form.data.email} onChange={e=>form.setData('email',e.target.value)} />
      <input className="w-full rounded-xl bg-white/10 p-3" placeholder="Restaurant name" value={form.data.restaurant_name} onChange={e=>form.setData('restaurant_name',e.target.value)} />
      <input className="w-full rounded-xl bg-white/10 p-3" placeholder="Restaurant WhatsApp / phone" value={form.data.restaurant_phone} onChange={e=>form.setData('restaurant_phone',e.target.value)} />
      <input className="w-full rounded-xl bg-white/10 p-3" type="password" placeholder="Password" value={form.data.password} onChange={e=>form.setData('password',e.target.value)} />
      <input className="w-full rounded-xl bg-white/10 p-3" type="password" placeholder="Confirm password" value={form.data.password_confirmation} onChange={e=>form.setData('password_confirmation',e.target.value)} />
      {Object.values(form.errors).map((error,i)=><p key={i} className="text-sm text-red-400">{error}</p>)}
      <button disabled={form.processing} className="w-full rounded-xl bg-white px-4 py-3 font-semibold text-slate-950">Create account</button>
      <Link href="/login" className="block text-center text-cyan-400">Already have an account?</Link>
    </form>
  </main>;
}