import { Form, Head, Link } from '@inertiajs/react';

type Menu = { id: number; name: string };

export default function MenuImport({ menu, managementBase, menusIndexUrl, photoScanEnabled = false }: { menu: Menu; managementBase?: string; menusIndexUrl?: string; photoScanEnabled?: boolean }) {
    const menuBasePath = managementBase ?? `/menus/${menu.id}`;
    const menuIndexPath = menusIndexUrl ?? '/menus';
    return <main dir="rtl" className="min-h-screen bg-slate-50 px-4 py-8 text-slate-950 sm:px-8">
        <Head title={`استيراد منيو · ${menu.name}`} />
        <div className="mx-auto max-w-3xl">
            <header className="mb-6 flex items-center justify-between gap-3"><div><p className="text-xs font-black tracking-widest text-amber-600">NEXORA QR · MENU STUDIO</p><h1 className="mt-1 text-2xl font-black sm:text-3xl">استيراد منيو موجود</h1><p className="mt-2 text-sm text-slate-500">{menu.name}</p></div><div className="flex gap-2"><Link href={menuIndexPath} className="rounded-xl border bg-white px-4 py-3 text-sm font-bold">كل المنيوهات</Link><Link href={menuBasePath} className="rounded-xl border bg-white px-4 py-3 text-sm font-bold">العودة للـBuilder</Link></div></header>
            <section className="rounded-3xl border bg-white p-5 shadow-sm sm:p-8">
                <div className="mb-8 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 sm:p-5">
                    <div className="flex items-start gap-3">
                        <span aria-hidden="true" className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-emerald-100 text-xl">▧</span>
                        <div className="min-w-0 flex-1">
                            <h2 className="text-lg font-black">حوّل صور المنيو إلى أصناف قابلة للتعديل</h2>
                            <p className="mt-1 text-sm leading-6 text-slate-600">ارفع صور واضحة للصفحات. هنستخرج الأقسام والأسماء والأسعار والأحجام في مسودة، وتراجعها بنفسك قبل ما أي بيانات تتضاف للمنيو.</p>
                            {!photoScanEnabled && <p className="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-6 text-amber-900">قراءة الصور غير مفعّلة على السيرفر حتى الآن. تحتاج ضبط OPENAI_API_KEY في إعدادات البيئة على السيرفر؛ لا تضع المفتاح في الواجهة أو GitHub.</p>}
                        </div>
                    </div>
                    <Form action={`${menuBasePath}/scan`} method="post" encType="multipart/form-data" className="mt-5 space-y-3">
                        {({ errors, processing }) => <>
                            <label className="block text-sm font-bold">صور المنيو (حتى 8 صور، كل صورة بحد أقصى 10 ميجابايت)
                                <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required disabled={!photoScanEnabled || processing} className="mt-2 block w-full rounded-xl border border-emerald-200 bg-white p-3 text-sm file:ml-3 file:rounded-lg file:border-0 file:bg-emerald-800 file:px-3 file:py-2 file:font-bold file:text-white disabled:opacity-50" />
                            </label>
                            {errors.images && <p role="alert" className="text-sm text-rose-700">{errors.images}</p>}
                            {errors['images.0'] && <p role="alert" className="text-sm text-rose-700">{errors['images.0']}</p>}
                            <button disabled={!photoScanEnabled || processing} className="w-full rounded-xl bg-emerald-800 px-4 py-3 font-black text-white disabled:cursor-not-allowed disabled:opacity-50">{processing ? 'جارٍ قراءة الصور…' : 'قراءة الصور ومراجعة الأصناف'}</button>
                        </>}
                    </Form>
                    <p className="mt-3 text-xs leading-6 text-slate-500">تنبيه: الاستخراج آلي وقد يخطئ في الأرقام؛ الأسعار غير المقروءة تظهر للمراجعة. الصور لا تُحفظ داخل سجل المنيو، ولا يتم الاستيراد أو النشر إلا بعد تأكيدك.</p>
                </div>

                <h2 className="text-lg font-black">استيراد CSV بدون تكرار الأصناف</h2>
                <p className="mt-2 text-sm leading-7 text-slate-600">هنستخدم نفس الأقسام والمنتجات والأحجام والإضافات الموجودة. لو الاسم موجود داخل نفس القسم، المنتج يتحدّث بدل ما يتكرر. العملية كلها بتتم معًا؛ لو فيه خطأ مش هيتحفظ جزء من الملف.</p>
                <div className="mt-5 rounded-2xl bg-slate-50 p-4 text-sm leading-7">
                    <p className="font-black">أعمدة الملف (بالإنجليزي):</p>
                    <code dir="ltr" className="mt-2 block overflow-x-auto rounded-lg bg-slate-950 p-3 text-xs text-white">category,product,price,description,variant,variant_price,variant_delta,modifier,modifier_price,modifier_required,modifier_group,group_min,group_max</code>
                    <p className="mt-3 text-xs text-slate-500">category وproduct وprice أعمدة أساسية. كل صف يمثل منتجًا أو حجمًا أو إضافة؛ كرر اسم المنتج في الصفوف الخاصة بأحجامه وإضافاته. variant_price سعر نهائي ثابت، أما variant_delta فرق السعر عن سعر المنتج. modifier_price فرق سعر الإضافة. اجمع الخيارات تحت نفس modifier_group وحدد group_min وgroup_max؛ مثال: اختار صوص واحد على الأقل من 3 خيارات. استخدم UTF-8 لحفظ العربي بشكل سليم.</p>
                    <a href="/menu-import-template.csv" download className="mt-3 inline-flex rounded-xl border border-slate-300 bg-white px-4 py-2 font-bold">تحميل نموذج CSV</a>
                </div>
                <Form action={`${menuBasePath}/import`} method="post" encType="multipart/form-data" className="mt-6 space-y-4">
                    {({ errors, processing, recentlySuccessful }) => <>
                        <label className="block text-sm font-bold">ملف CSV<input type="file" name="file" accept=".csv,.txt,text/csv" required className="mt-2 block w-full rounded-xl border border-slate-300 bg-white p-3 text-sm file:ml-3 file:rounded-lg file:border-0 file:bg-slate-950 file:px-3 file:py-2 file:font-bold file:text-white" />{errors.file && <span className="mt-2 block text-sm text-red-600">{errors.file}</span>}</label>
                        {recentlySuccessful && <p className="text-sm font-bold text-emerald-700">تمت العملية بنجاح.</p>}
                        <button disabled={processing} className="w-full rounded-xl bg-slate-950 px-4 py-3 font-black text-white disabled:opacity-50">{processing ? 'جارٍ التحقق والاستيراد…' : 'تحقق واستيراد المنيو'}</button>
                    </>}
                </Form>
                <p className="mt-4 text-xs leading-6 text-slate-500">الاستيراد لا ينشئ طلبات ولا يغيّر الطلبات القديمة. ربط الـPOS اختياري ومستقل عن استيراد المنيو.</p>
            </section>
        </div>
    </main>;
}
