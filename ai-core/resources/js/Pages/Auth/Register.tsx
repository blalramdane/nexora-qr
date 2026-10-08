import { Form, Head, Link } from '@inertiajs/react';

export default function Register() {
    return (
        <main className="flex min-h-screen items-center justify-center bg-slate-950 p-6">
            <Head title="إنشاء حساب" />
            <div className="w-full max-w-md rounded-3xl bg-white p-8 shadow-2xl">
                <p className="text-sm font-semibold text-slate-500">NEXORA QR</p>
                <h1 className="mt-2 text-3xl font-bold text-slate-950">ابدأ مطعمك</h1>
                <p className="mt-2 text-slate-500">أنشئ حساب المالك والمطعم في خطوة واحدة.</p>

                <Form action="/register" method="post" className="mt-8 space-y-5">
                    {({ errors, processing }) => (
                        <>
                            <label className="block">
                                <span className="mb-2 block text-sm font-semibold">اسمك</span>
                                <input name="name" required className="w-full rounded-2xl border border-slate-200 px-4 py-3" />
                                {errors.name && <span className="mt-1 block text-sm text-red-600">{errors.name}</span>}
                            </label>
                            <label className="block">
                                <span className="mb-2 block text-sm font-semibold">اسم المطعم</span>
                                <input name="restaurant_name" required className="w-full rounded-2xl border border-slate-200 px-4 py-3" />
                                {errors.restaurant_name && <span className="mt-1 block text-sm text-red-600">{errors.restaurant_name}</span>}
                            </label>
                            <label className="block">
                                <span className="mb-2 block text-sm font-semibold">البريد الإلكتروني</span>
                                <input name="email" type="email" required className="w-full rounded-2xl border border-slate-200 px-4 py-3" />
                                {errors.email && <span className="mt-1 block text-sm text-red-600">{errors.email}</span>}
                            </label>
                            <label className="block">
                                <span className="mb-2 block text-sm font-semibold">كلمة المرور</span>
                                <input name="password" type="password" required className="w-full rounded-2xl border border-slate-200 px-4 py-3" />
                                {errors.password && <span className="mt-1 block text-sm text-red-600">{errors.password}</span>}
                            </label>
                            <label className="block">
                                <span className="mb-2 block text-sm font-semibold">تأكيد كلمة المرور</span>
                                <input name="password_confirmation" type="password" required className="w-full rounded-2xl border border-slate-200 px-4 py-3" />
                            </label>
                            <button disabled={processing} className="w-full rounded-2xl bg-slate-950 px-4 py-3 font-semibold text-white disabled:opacity-50">
                                {processing ? 'جاري الإنشاء...' : 'إنشاء الحساب'}
                            </button>
                        </>
                    )}
                </Form>

                <p className="mt-6 text-center text-sm text-slate-500">
                    عندك حساب؟ <Link href="/login" className="font-semibold text-slate-950">تسجيل الدخول</Link>
                </p>
            </div>
        </main>
    );
}
