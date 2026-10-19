import { createContext, ReactNode, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import ar from './ar.json';
import en from './en.json';

export type Locale = 'ar' | 'en';
interface Dictionary {
    [key: string]: string | Dictionary;
}
type TranslationValues = Record<string, string | number>;
type LocaleContextValue = { locale: Locale; dir: 'rtl' | 'ltr'; setLocale: (locale: Locale) => void; t: (key: string, values?: TranslationValues) => string };

const dictionaries: Record<Locale, Dictionary> = { ar: ar as Dictionary, en: en as Dictionary };
const LocaleContext = createContext<LocaleContextValue | null>(null);

function lookup(dictionary: Dictionary, key: string): string | null {
    let value: string | Dictionary | undefined = dictionary;
    for (const part of key.split('.')) {
        if (!value || typeof value === 'string') return null;
        value = value[part];
    }
    return typeof value === 'string' ? value : null;
}

export function LocaleProvider({ children }: { children: ReactNode }) {
    const [locale, setLocaleState] = useState<Locale>(() => {
        if (typeof window === 'undefined') return 'ar';
        try {
            const saved = window.localStorage.getItem('nexora-locale');
            return saved === 'en' || saved === 'ar' ? saved : 'ar';
        } catch {
            return 'ar';
        }
    });

    const setLocale = useCallback((next: Locale) => setLocaleState(next), []);
    const t = useCallback((key: string, values?: TranslationValues) => {
        const template = lookup(dictionaries[locale], key) ?? lookup(dictionaries.ar, key) ?? key;
        if (!values) return template;
        return template.replace(/\{([a-zA-Z0-9_]+)\}/g, (match, name: string) => {
            const value = values[name];
            return value === undefined ? match : String(value);
        });
    }, [locale]);
    const dir: 'rtl' | 'ltr' = locale === 'ar' ? 'rtl' : 'ltr';

    useEffect(() => {
        document.documentElement.lang = locale;
        document.documentElement.dir = dir;
        try { window.localStorage.setItem('nexora-locale', locale); } catch { /* Storage is optional; locale still works in memory. */ }
    }, [locale, dir]);

    const value = useMemo(() => ({ locale, dir, setLocale, t }), [locale, dir, setLocale, t]);
    return <LocaleContext.Provider value={value}>{children}</LocaleContext.Provider>;
}

export function useLocale(): LocaleContextValue {
    const value = useContext(LocaleContext);
    if (!value) throw new Error('useLocale must be used inside LocaleProvider.');
    return value;
}
