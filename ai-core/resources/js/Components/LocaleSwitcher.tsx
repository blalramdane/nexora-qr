import { useLocale } from '../i18n/LocaleProvider';

type Props = { value?: string; onChange?: (locale: string) => void; variant?: 'light' | 'dark' };

export default function LocaleSwitcher({ value, onChange, variant = 'light' }: Props) {
  const context = useLocale();
  const activeLocale = value ?? context.locale;
  const changeLocale = onChange ?? ((locale: string) => context.setLocale(locale === 'en' ? 'en' : 'ar'));
  const dark = variant === 'dark';
  return (
    <div className={`inline-flex overflow-hidden rounded-xl border ${dark ? 'border-[#d6a45b]/30 bg-[#1b100a]' : 'border-slate-200 bg-white'}`} role="group" aria-label={context.t('common.language')}>
      {[{ key: 'ar', label: 'العربية' }, { key: 'en', label: 'English' }].map((l) => (
        <button
          key={l.key}
          type="button"
          aria-pressed={activeLocale === l.key}
          onClick={() => changeLocale(l.key)}
          className={`px-3 py-2 text-xs font-bold transition ${dark ? (activeLocale === l.key ? 'bg-[#d6a45b] text-[#1b100a]' : 'text-[#f5e7cf]/75 hover:bg-white/[0.08] hover:text-white') : (activeLocale === l.key ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-50')}`}
        >
          {l.label}
        </button>
      ))}
    </div>
  );
}
