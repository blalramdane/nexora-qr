import { Form, Head, Link } from '@inertiajs/react';

type Account = { name: string; email: string };

export default function OwnerAccount({ user }: { user: Account }) {
    return <main dir="rtl" className="min-h-screen bg-slate-50 px-4 py-8 text-slate-950 sm:px-6 lg:px-8">
        <Head title="حساب مالك NEXORA" />
        <div className="mx-auto max-w-3xl">
            <Link href="/owner/menus" className="text-sm font-bold text-slate-500 hover:text-slate-950">← الرجوع لاستوديو المنيوهات</Link>
            <header className="mt-5 rounded-3xl bg-slate-950 p-6 text-white sm:p-8">
                <p className="text-xs font-black tracking-[.22em] text-emerald-300">NEXORA · ACCOUNT SECURITY</p>
                <h1 className="mt-2 text-3xl font-black">بيانات حسابك</h1>
                <p className="mt-3 text-sm leading-7 text-slate-300">غيّر اسم الحساب أو الإيميل أو كلمة السر. التغييرات تتطلب كلمة السر الحالية لحماية حساب مالك المنصة.</p>
            </header>

            <section className="mt-5 rounded-3xl border border-slate-200 bg-white p-5 sm:p-7">
                <h2 className="text-xl font-black">اسم الحساب والإيميل</h2>
                <Form action="/owner/account" method="patch" className="mt-5 space-y-4">
                    {({ errors, processing, recentlySuccessful }) => <>
                        <label className="block text-sm font-bold">اسم الحساب<input name="name" defaultValue={user.name} required maxLength={120} autoComplete="name" className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-3 font-normal outline-none focus:border-emerald-500" />{errors.name && <small className="mt-1 block text-red-600">{errors.name}</small>}</label>
                        <label className="block text-sm font-bold">الإيميل المستخدم لتسجيل الدخول<input name="email" type="email" defaultValue={user.email} required maxLength={255} autoComplete="email" className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-3 font-normal outline-none focus:border-emerald-500" />{errors.email && <small className="mt-1 block text-red-600">{errors.email}</small>}</label>
                        <label className="block text-sm font-bold">كلمة السر الحالية<input name="current_password" type="password" required autoComplete="current-password" className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-3 font-normal outline-none focus:border-emerald-500" />{errors.current_password && <small className="mt-1 block text-red-600">{errors.current_password}</small>}</label>
                        {recentlySuccessful && <p className="text-sm font-bold text-emerald-700">تم الحفظ.</p>}
                        <button disabled={processing} className="rounded-xl bg-slate-950 px-5 py-3 text-sm font-black text-white disabled:opacity-50">حفظ بيانات الحساب</button>
                    </>}
                </Form>
            </section>

            <section className="mt-5 rounded-3xl border border-slate-200 bg-white p-5 sm:p-7">
                <h2 className="text-xl font-black">تغيير كلمة السر</h2>
                <p className="mt-2 text-sm leading-6 text-slate-500">استخدم كلمة سر جديدة لا تقل عن 12 حرفًا، وتحتوي على حروف كبيرة وصغيرة وأرقام ورموز.</p>
                <Form action="/owner/account/password" method="patch" className="mt-5 space-y-4">
                    {({ errors, processing, recentlySuccessful }) => <>
                        <label className="block text-sm font-bold">كلمة السر الحالية<input name="current_password" type="password" required autoComplete="current-password" className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-3 font-normal outline-none focus:border-emerald-500" />{errors.current_password && <small className="mt-1 block text-red-600">{errors.current_password}</small>}</label>
                        <label className="block text-sm font-bold">كلمة السر الجديدة<input name="password" type="password" required minLength={12} autoComplete="new-password" className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-3 font-normal outline-none focus:border-emerald-500" />{errors.password && <small className="mt-1 block text-red-600">{errors.password}</small>}</label>
                        <label className="block text-sm font-bold">تأكيد كلمة السر الجديدة<input name="password_confirmation" type="password" required minLength={12} autoComplete="new-password" className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-3 font-normal outline-none focus:border-emerald-500" /></label>
                        {recentlySuccessful && <p className="text-sm font-bold text-emerald-700">تم تغيير كلمة السر.</p>}
                        <button disabled={processing} className="rounded-xl bg-emerald-700 px-5 py-3 text-sm font-black text-white disabled:opacity-50">تغيير كلمة السر</button>
                    </>}
                </Form>
            </section>
        </div>
    </main>;
}
