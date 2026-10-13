import { useEffect, useState } from 'react';

type InstallChoiceEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed'; platform: string }>;
};

export default function PwaInstallPrompt() {
    const [installPrompt, setInstallPrompt] = useState<InstallChoiceEvent | null>(null);
    const [isIos, setIsIos] = useState(false);
    const [isInstalled, setIsInstalled] = useState(false);
    const [showIosHelp, setShowIosHelp] = useState(false);
    const [installing, setInstalling] = useState(false);
    const [dismissed, setDismissed] = useState(false);

    useEffect(() => {
        const standalone = window.matchMedia('(display-mode: standalone)').matches
            || (navigator as Navigator & { standalone?: boolean }).standalone === true;
        setIsInstalled(standalone);
        setIsIos(/iphone|ipad|ipod/i.test(navigator.userAgent) && !standalone);

        const onBeforeInstall = (event: Event) => {
            event.preventDefault();
            setInstallPrompt(event as InstallChoiceEvent);
        };
        const onInstalled = () => {
            setIsInstalled(true);
            setInstallPrompt(null);
            setShowIosHelp(false);
        };

        window.addEventListener('beforeinstallprompt', onBeforeInstall);
        window.addEventListener('appinstalled', onInstalled);
        return () => {
            window.removeEventListener('beforeinstallprompt', onBeforeInstall);
            window.removeEventListener('appinstalled', onInstalled);
        };
    }, []);

    const install = async () => {
        if (isIos) {
            setShowIosHelp(value => !value);
            return;
        }
        if (!installPrompt || installing) return;

        setInstalling(true);
        try {
            await installPrompt.prompt();
            const choice = await installPrompt.userChoice;
            if (choice.outcome === 'accepted') setIsInstalled(true);
            setInstallPrompt(null);
        } finally {
            setInstalling(false);
        }
    };

    if (isInstalled || dismissed || (!isIos && !installPrompt)) return null;

    return (
        <section dir="rtl" className="mx-auto mb-4 max-w-7xl px-4 sm:px-6 lg:px-8" aria-label="تثبيت NEXORA على الجهاز">
            <div className="rounded-2xl border border-emerald-200 bg-gradient-to-l from-emerald-50 via-white to-white p-4 shadow-sm sm:p-5">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-3">
                        <span aria-hidden="true" className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-slate-950 text-xl text-emerald-300">↗</span>
                        <div>
                            <h2 className="font-black text-slate-950">خلّي NEXORA على شاشة موبايلك</h2>
                            <p className="mt-1 text-sm leading-6 text-slate-600">افتح لوحة المطعم من أيقونة مستقلة، من غير ما تدور على رابط الموقع كل مرة.</p>
                        </div>
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                        <button type="button" onClick={install} className="rounded-xl bg-emerald-700 px-4 py-3 text-sm font-black text-white transition hover:bg-emerald-800">
                            {isIos ? (showIosHelp ? 'إخفاء خطوات التثبيت' : 'طريقة الإضافة للشاشة') : installing ? 'جاري فتح التثبيت…' : 'تثبيت NEXORA'}
                        </button>
                        <button type="button" onClick={() => setDismissed(true)} aria-label="إخفاء تلميح تثبيت NEXORA" className="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 text-lg text-slate-500 hover:bg-slate-50">×</button>
                    </div>
                </div>
                {isIos && showIosHelp && <ol className="mt-4 space-y-2 rounded-xl border border-emerald-100 bg-white p-4 text-sm leading-6 text-slate-700">
                    <li>١. افتح الصفحة في Safari.</li>
                    <li>٢. اضغط زر المشاركة في المتصفح.</li>
                    <li>٣. اختار «إضافة إلى الشاشة الرئيسية» ثم اضغط «إضافة».</li>
                </ol>}
                <p className="mt-3 text-xs leading-5 text-slate-500">تحتاج اتصال إنترنت لتحديث المنيو واستقبال الطلبات. التثبيت لا يحفظ الطلبات أو بيانات المطعم للاستخدام دون اتصال.</p>
            </div>
        </section>
    );
}
