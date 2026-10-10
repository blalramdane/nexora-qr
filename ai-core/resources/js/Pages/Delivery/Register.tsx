import { Form, Head, Link } from '@inertiajs/react';

export default function DeliveryRegister() {
    return <main dir="rtl" className="flex min-h-screen items-center justify-center bg-slate-950 p-4 sm:p-8">
        <Head title="التسجيل كمندوب توصيل" />
        <section className="w-full max-w-2xl rounded-[28px] bg-white p-6 shadow-2xl sm:p-9">
            <p className="text-xs font-black tracking-[.25em] text-emerald-700">NEXORA / DELIVERY NETWORK</p>
            <h1 className="mt-3 text-3xl font-black text-slate-950">انضم لشبكة الدليفري</h1>
            <p className="mt-2 text-sm leading-6 text-slate-500">سجّل بياناتك ومناطق شغلك مرة واحدة. بعد التسجيل تقدر تختار إتاحة ملفك للمطاعم في مناطقك، وتقبل التعاون المناسب ليك بنفسك.</p>
            <Form action="/delivery/register" method="post" className="mt-7 grid gap-4 sm:grid-cols-2">
                {({ errors, processing }) => <>
                    <label className="block"><span className="mb-2 block text-sm font-bold">الاسم بالكامل</span><input name="name" required maxLength={120} className="w-full rounded-xl border border-slate-200 px-4 py-3" />{errors.name && <small className="text-rose-600">{errors.name}</small>}</label>
                    <label className="block"><span className="mb-2 block text-sm font-bold">رقم الموبايل</span><input name="phone" required maxLength={30} type="tel" className="w-full rounded-xl border border-slate-200 px-4 py-3" />{errors.phone && <small className="text-rose-600">{errors.phone}</small>}</label>
                    <label className="block sm:col-span-2"><span className="mb-2 block text-sm font-bold">البريد الإلكتروني</span><input name="email" required type="email" autoComplete="email" className="w-full rounded-xl border border-slate-200 px-4 py-3" />{errors.email && <small className="text-rose-600">{errors.email}</small>}</label>
                    <label className="block"><span className="mb-2 block text-sm font-bold">وسيلة التوصيل</span><select name="vehicle_type" defaultValue="motorcycle" className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3"><option value="walking">مشياً</option><option value="bicycle">عجلة</option><option value="motorcycle">موتوسيكل</option><option value="car">سيارة</option></select></label>
                    <label className="block"><span className="mb-2 block text-sm font-bold">مناطق التغطية</span><input name="coverage_areas" required placeholder="مثال: الزرقا، دمياط، فارسكور" className="w-full rounded-xl border border-slate-200 px-4 py-3" />{errors.coverage_areas && <small className="text-rose-600">{errors.coverage_areas}</small>}</label>
                    <label className="block"><span className="mb-2 block text-sm font-bold">كلمة المرور</span><input name="password" required type="password" minLength={8} autoComplete="new-password" className="w-full rounded-xl border border-slate-200 px-4 py-3" />{errors.password && <small className="text-rose-600">{errors.password}</small>}</label>
                    <label className="block"><span className="mb-2 block text-sm font-bold">تأكيد كلمة المرور</span><input name="password_confirmation" required type="password" autoComplete="new-password" className="w-full rounded-xl border border-slate-200 px-4 py-3" /></label>
                    <button disabled={processing} className="rounded-xl bg-slate-950 px-5 py-3.5 font-black text-white disabled:opacity-50 sm:col-span-2">{processing ? 'جارٍ إنشاء الحساب…' : 'إنشاء حساب المندوب'}</button>
                </>}
            </Form>
            <p className="mt-5 text-center text-sm text-slate-500">عندك حساب؟ <Link href="/login" className="font-bold text-slate-950 underline">تسجيل الدخول</Link></p>
            <p className="mt-2 text-center text-sm text-slate-500">صاحب مطعم؟ <Link href="/register" className="font-bold text-slate-950 underline">أنشئ حساب المطعم</Link></p>
        </section>
    </main>;
}
