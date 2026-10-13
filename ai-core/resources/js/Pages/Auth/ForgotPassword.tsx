import { Form, Head, Link } from '@inertiajs/react';

export default function ForgotPassword() {
    return (
        <main dir="rtl" className="flex min-h-screen items-center justify-center bg-slate-950 p-5 text-slate-950">
            <Head title="استعادة كلمة السر | NEXORA QR" />
            <section className="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl sm:p-9">
                <p className="text-xs font-black tracking-[.2em] text-emerald-700">NEXORA QR · ACCOUNT RECOVERY</p>
                <h1 className="mt-3 text-2xl font-black sm:text-3xl">نسيت كلمة السر؟</h1>
                <p className="mt-2 text-sm leading-7 text-slate-500">اكتب بريد الحساب. لو مسجل عندنا، هتوصلك رسالة فيها رابط مؤقت لتعيين كلمة سر جديدة.</p>

                <Form action="/forgot-password" method="post" className="mt-6 space-y-4">
                    {({ errors, processing, recentlySuccessful }) => (
                        <>
                            <label className="block text-sm font-bold">البريد الإلكتروني
                                <input name="email" type="email" required maxLength={255} autoComplete="email" dir="ltr" className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-3 text-left font-normal outline-none focus:border-emerald-600 focus:ring-4 focus:ring-emerald-50" placeholder="owner@example.com" />
                                {errors.email && <span className="mt-1 block text-xs text-rose-600">{errors.email}</span>}
                            </label>
                            {recentlySuccessful && <p role="status" className="rounded-xl bg-emerald-50 p-3 text-sm leading-6 text-emerald-900">تم استلام الطلب. لو البريد مسجل وإعداد البريد صحيح، هتوصلك رسالة الاستعادة.</p>}
                            <button disabled={processing} className="w-full rounded-xl bg-emerald-700 px-4 py-3 font-black text-white transition hover:bg-emerald-800 disabled:opacity-50">
                                {processing ? 'جاري إرسال الطلب…' : 'إرسال رابط الاستعادة'}
                            </button>
                        </>
                    )}
                </Form>

                <p className="mt-5 text-center text-sm text-slate-500"><Link href="/login" className="font-bold text-slate-950 underline">العودة لتسجيل الدخول</Link></p>
            </section>
        </main>
    );
}
