import { Form, Head, Link } from '@inertiajs/react';

type Props = { token: string; email: string };

export default function ResetPassword({ token, email }: Props) {
    return (
        <main dir="rtl" className="flex min-h-screen items-center justify-center bg-slate-950 p-5 text-slate-950">
            <Head title="تعيين كلمة السر | NEXORA QR" />
            <section className="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl sm:p-9">
                <p className="text-xs font-black tracking-[.2em] text-emerald-700">NEXORA QR · ACCOUNT SETUP</p>
                <h1 className="mt-3 text-2xl font-black sm:text-3xl">تعيين كلمة السر</h1>
                <p className="mt-2 text-sm leading-7 text-slate-500">عيّن كلمة سر جديدة لحسابك، وبعد الحفظ سجّل الدخول بالبريد الإلكتروني وكلمة السر الجديدة.</p>

                <Form action="/reset-password" method="post" className="mt-6 space-y-4">
                    {({ errors, processing }) => (
                        <>
                            <input type="hidden" name="token" value={token} />
                            <label className="block text-sm font-bold">البريد الإلكتروني
                                <input name="email" type="email" required maxLength={255} defaultValue={email} autoComplete="email" dir="ltr" className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-3 text-left font-normal outline-none focus:border-emerald-600 focus:ring-4 focus:ring-emerald-50" />
                                {errors.email && <span className="mt-1 block text-xs text-rose-600">{errors.email}</span>}
                            </label>
                            <label className="block text-sm font-bold">كلمة السر الجديدة
                                <input name="password" type="password" required minLength={12} autoComplete="new-password" className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-3 font-normal outline-none focus:border-emerald-600 focus:ring-4 focus:ring-emerald-50" />
                                {errors.password && <span className="mt-1 block text-xs text-rose-600">{errors.password}</span>}
                            </label>
                            <label className="block text-sm font-bold">تأكيد كلمة السر
                                <input name="password_confirmation" type="password" required minLength={12} autoComplete="new-password" className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-3 font-normal outline-none focus:border-emerald-600 focus:ring-4 focus:ring-emerald-50" />
                            </label>
                            <p className="rounded-xl bg-slate-50 p-3 text-xs leading-6 text-slate-600">استخدم 12 حرفًا على الأقل، مع حروف كبيرة وصغيرة وأرقام ورموز.</p>
                            <button disabled={processing} className="w-full rounded-xl bg-emerald-700 px-4 py-3 font-black text-white transition hover:bg-emerald-800 disabled:opacity-50">
                                {processing ? 'جاري حفظ كلمة السر…' : 'حفظ كلمة السر'}
                            </button>
                        </>
                    )}
                </Form>

                <p className="mt-5 text-center text-sm text-slate-500"><Link href="/login" className="font-bold text-slate-950 underline">العودة لتسجيل الدخول</Link></p>
            </section>
        </main>
    );
}
