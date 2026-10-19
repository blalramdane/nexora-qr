import { Head } from '@inertiajs/react';
import { useState } from 'react';
import TemplateRenderer from './TemplateRenderer';
import PublicOrderCart from './PublicOrderCart';
import CallWaiterButton from '../../Components/CallWaiterButton';
import AiWaiter from './AiWaiter';
import DietaryFilterChips from '../../Components/DietaryFilterChips';
import LocaleSwitcher from '../../Components/LocaleSwitcher';
import { useLocale } from '../../i18n/LocaleProvider';

type Restaurant = {
    name: string;
    slug: string;
    address?: string | null;
    logo_path?: string | null;
    phone?: string | null;
    whatsapp_phone?: string | null;
    delivery_fee?: string | number;
    delivery_areas?: string[];
    delivery_enabled?: boolean;
    pickup_enabled?: boolean;
};
type TableContext = { name: string; token: string; id?: number } | null;

type PublicMenu = {
    name: string;
    slug: string;
    theme?: {
        primary: string;
        accent: string;
        background: string;
        foreground: string;
        radius: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
    } | null;
    template?: { key: string; name: string } | null;
    template_version?: { version: number; is_active: boolean; schema?: { layout?: string; theme?: NonNullable<PublicMenu['theme']> } } | null;
    categories: Array<{
        id: number;
        name: string;
        slug: string;
        description?: string | null;
        is_active: boolean;
        products: Array<{
            id: number;
            name: string;
            description?: string | null;
            price: string;
            image_path?: string | null;
            is_available: boolean;
            is_featured: boolean;
            dietary_tags?: string[] | null;
            variants?: Array<{ id: number; name: string; price?: string | null; price_delta?: string; is_active: boolean }>;
            modifiers?: Array<{ id: number; name: string; price_delta: string; is_active: boolean; is_required: boolean; group_name?: string | null; group_min_choices?: number; group_max_choices?: number }>;
        }>;
    }>;
};

type AbTestAssignment = { id: number; variant: 'a' | 'b'; style: 'modern' | 'luxury' | 'playful' | 'classic' } | null;

const AB_THEME_PALETTES: Record<NonNullable<AbTestAssignment>['style'], NonNullable<PublicMenu['theme']>> = {
    modern: { primary: '#166534', accent: '#84cc16', background: '#f8fafc', foreground: '#17211b', radius: '2xl' },
    luxury: { primary: '#c6a96b', accent: '#f0dfae', background: '#080807', foreground: '#f5efe2', radius: 'lg' },
    playful: { primary: '#f97316', accent: '#fbbf24', background: '#fff7ed', foreground: '#431407', radius: 'xl' },
    classic: { primary: '#3b2418', accent: '#d6a45b', background: '#f8f2e8', foreground: '#2c211b', radius: 'xl' },
};

export default function Public({ restaurant, menu, tableContext, abTest = null }: { restaurant: Restaurant; menu: PublicMenu; tableContext?: TableContext; abTest?: AbTestAssignment }) {
    const { dir, t } = useLocale();
    const [selectedDietaryTags, setSelectedDietaryTags] = useState<string[]>([]);
    const [showDietaryFilters, setShowDietaryFilters] = useState(false);
    const isDePapa = /de\s*papa|دي\s*بابا/i.test(`${restaurant.name} ${menu.name}`);
    const publicMenuTitle = isDePapa ? 'DE PAPA' : menu.name;
    const renderedMenu: PublicMenu = abTest ? { ...menu, theme: AB_THEME_PALETTES[abTest.style] } : menu;
    return (
        <div dir={dir}>
            <Head title={`${publicMenuTitle} — ${isDePapa ? 'Café & Desserts' : restaurant.name}`}>
                <meta name="description" content={isDePapa ? 'DE PAPA Café & Desserts — اكتشف القهوة المختصة والحلويات والمشروبات المميزة.' : `${menu.name} من ${restaurant.name}. تصفح قائمة الطعام والأسعار.`} />
                <meta name="robots" content={tableContext ? 'noindex,nofollow' : 'index,follow'} />
            </Head>
            <div className={`relative z-30 border-b px-4 py-2 shadow-sm ${isDePapa ? 'border-[#d6a45b]/20 bg-[#100a07] text-[#f7ecdc]' : 'border-slate-200/80 bg-white'}`}>
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-3">
                    <button type="button" onClick={() => setShowDietaryFilters(value => !value)} aria-expanded={showDietaryFilters} className={`flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-bold transition ${isDePapa ? 'border-[#d6a45b]/25 text-[#f5e7cf]/80 hover:border-[#d6a45b]/60 hover:bg-white/[0.05]' : 'border-slate-200 text-slate-600 hover:border-slate-400 hover:bg-slate-50'}`}>
                        <span aria-hidden="true">☷</span>{t('components.dietaryFilters')}
                        {selectedDietaryTags.length > 0 && <span className={`grid h-5 min-w-5 place-items-center rounded-full px-1 text-[10px] ${isDePapa ? 'bg-[#d6a45b] text-[#1b100a]' : 'bg-emerald-600 text-white'}`}>{selectedDietaryTags.length}</span>}
                        <span aria-hidden="true" className="text-[10px]">{showDietaryFilters ? '▲' : '▼'}</span>
                    </button>
                    <LocaleSwitcher variant={isDePapa ? 'dark' : 'light'} />
                </div>
                {showDietaryFilters && <div className="mx-auto max-w-6xl pt-1"><DietaryFilterChips selected={selectedDietaryTags} onChange={setSelectedDietaryTags} variant={isDePapa ? 'dark' : 'light'} /></div>}
            </div>
            <TemplateRenderer menu={renderedMenu} restaurantName={restaurant.name} restaurantAddress={restaurant.address} logoPath={restaurant.logo_path} restaurantPhone={restaurant.phone} restaurantWhatsappPhone={restaurant.whatsapp_phone} dietaryFilter={selectedDietaryTags} />
            <PublicOrderCart restaurantSlug={restaurant.slug} restaurantName={restaurant.name} whatsappPhone={restaurant.whatsapp_phone} menuSlug={menu.slug} accent={renderedMenu.theme?.accent} primary={renderedMenu.theme?.primary} deliveryFee={restaurant.delivery_fee} deliveryAreas={restaurant.delivery_areas} deliveryEnabled={restaurant.delivery_enabled} pickupEnabled={restaurant.pickup_enabled} tableToken={tableContext?.token} tableName={tableContext?.name} />
            <AiWaiter restaurantSlug={restaurant.slug} menuSlug={menu.slug} accent={renderedMenu.theme?.primary} />
            <CallWaiterButton tableId={tableContext?.id} tableToken={tableContext?.token} accent={renderedMenu.theme?.primary} />
        </div>
    );
}
