import { Head } from '@inertiajs/react';
import TemplateRenderer from './TemplateRenderer';
import PublicOrderCart from './PublicOrderCart';
import AiWaiter from './AiWaiter';

type Restaurant = {
    name: string;
    slug: string;
    logo_path?: string | null;
    phone?: string | null;
    whatsapp_phone?: string | null;
    delivery_fee?: string | number;
    delivery_areas?: string[];
    delivery_enabled?: boolean;
    pickup_enabled?: boolean;
};
type TableContext = { name: string; token: string } | null;

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
            variants?: Array<{ id: number; name: string; price?: string | null; price_delta?: string; is_active: boolean }>;
            modifiers?: Array<{ id: number; name: string; price_delta: string; is_active: boolean; is_required: boolean; group_name?: string | null; group_min_choices?: number; group_max_choices?: number }>;
        }>;
    }>;
};

export default function Public({ restaurant, menu, tableContext }: { restaurant: Restaurant; menu: PublicMenu; tableContext?: TableContext }) {
    return (
        <>
            <Head title={`${menu.name} — ${restaurant.name}`}>
                <meta name="description" content={`${menu.name} من ${restaurant.name}. تصفح قائمة الطعام والأسعار.`} />
                <meta name="robots" content={tableContext ? 'noindex,nofollow' : 'index,follow'} />
            </Head>
            <TemplateRenderer menu={menu} restaurantName={restaurant.name} logoPath={restaurant.logo_path} restaurantPhone={restaurant.phone} restaurantWhatsappPhone={restaurant.whatsapp_phone} />
            <PublicOrderCart restaurantSlug={restaurant.slug} restaurantName={restaurant.name} whatsappPhone={restaurant.whatsapp_phone} menuSlug={menu.slug} accent={menu.theme?.accent} primary={menu.theme?.primary} deliveryFee={restaurant.delivery_fee} deliveryAreas={restaurant.delivery_areas} deliveryEnabled={restaurant.delivery_enabled} pickupEnabled={restaurant.pickup_enabled} tableToken={tableContext?.token} tableName={tableContext?.name} />
            <AiWaiter restaurantSlug={restaurant.slug} menuSlug={menu.slug} accent={menu.theme?.primary} />
        </>
    );
}
