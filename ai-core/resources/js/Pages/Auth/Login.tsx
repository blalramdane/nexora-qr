import { Form, Head, Link } from '@inertiajs/react';

export default function Login() {
    return (
        <main className="flex min-h-screen items-center justify-center bg-slate-950 p-6">
            <Head title="تسجيل الدخول" />
            <div className="w-full max-w-md rounded-3xl bg-white p-8 shadow-2xl">
                <div className="mb-8">
                    <p className="text-sm font-semibold text-slate-500">NEXORA QR</p>
                    <h1 className="mt-2 text-3xl font-bold text-slate-950">أهلاً بيك</h1>
                    <p className="mt-2 text-slate-500">ادخل لإدارة مطعمك.</p>
                </div>

                <Form action="/login" method="post" className="space-y-5">
                    {({ errors, processing }) => (
                        <>
                            <label className="block">
                                <span className="mb-2 block text-sm font-semibold">البريد الإلكتروني</span>
                                <input name="email" type="email" required className="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-slate-900" />
                                {errors.email && <span className="mt-1 block text-sm text-red-600">{errors.email}</span>}
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-sm font-semibold">كلمة المرور</span>
                                <input name="password" type="password" required className="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-slate-900" />
                            </label>

                            <label className="flex items-center gap-2 text-sm text-slate-600">
                                <input name="remember" type="checkbox" value="1" />
                                تذكرني
                            </label>

                            <button disabled={processing} className="w-full rounded-2xl bg-slate-950 px-4 py-3 font-semibold text-white disabled:opacity-50">
                                {processing ? 'جاري الدخول...' : 'دخول'}
                            </button>
                        </>
                    )}
                </Form>

                <p className="mt-6 text-center text-sm text-slate-500">
                    أول مرة؟ <Link href="/register" className="font-semibold text-slate-950">أنشئ حسابك</Link>
                </p>
            </div>
        </main>
    );
}
