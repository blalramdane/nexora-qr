import { useState } from 'react';
import { modifierSelectionError, toggleModifierSelection } from './modifierChoices';
import { requestProductConfiguration } from './ProductConfigurator';
import { useLocale } from '../../i18n/LocaleProvider';

type Props = { menu: any; restaurantName: string; logoPath?: string | null; restaurantPhone?: string | null; restaurantWhatsappPhone?: string | null; searchActive?: boolean; preview?: boolean };

const money = (value: string | number) => Number(value).toFixed(0) + ' ج.م';
const imageUrl = (path?: string | null) =>
    path && (path.startsWith('http') || path.startsWith('/') || path.startsWith('blob:') || path.startsWith('data:')) ? path : path ? '/storage/' + path : null;
const radiusMap = { sm: '0.65rem', md: '0.85rem', lg: '1rem', xl: '1.25rem', '2xl': '1.75rem' } as const;
const cafeSprite = '/images/de-papa/food-sprite.webp';
const dePapaCategoryLabels: Record<string, { ar: string; en: string }> = {
    'fresh juice': { ar: 'العصائر الطازجة', en: 'Fresh Juice' },
    'ice cream': { ar: 'آيس كريم', en: 'Ice Cream' },
    'speciality coffee': { ar: 'قهوة مختصة', en: 'Specialty Coffee' },
    'ice matcha': { ar: 'ماتشا باردة', en: 'Iced Matcha' },
    'mini pancakes': { ar: 'ميني بان كيك', en: 'Mini Pancakes' },
    coffee: { ar: 'القهوة', en: 'Coffee' },
    'hot matcha': { ar: 'ماتشا ساخنة', en: 'Hot Matcha' },
    cocktail: { ar: 'كوكتيلات', en: 'Cocktails' },
    waffle: { ar: 'وافل', en: 'Waffles' },
    'hot drink': { ar: 'مشروبات ساخنة', en: 'Hot Drinks' },
    additions: { ar: 'الإضافات', en: 'Add-ons' },
    'soft drink': { ar: 'مشروبات غازية', en: 'Soft Drinks' },
    soda: { ar: 'الصودا', en: 'Soda' },
    'french pastry': { ar: 'مخبوزات فرنسية', en: 'French Pastries' },
    'espresso bar': { ar: 'مشروبات الإسبريسو', en: 'Espresso Bar' },
    bakery: { ar: 'المخبوزات', en: 'Bakery' },
    'milk shake': { ar: 'ميلك شيك', en: 'Milkshakes' },
    'ice coffee': { ar: 'قهوة باردة', en: 'Iced Coffee' },
    dessert: { ar: 'الحلويات', en: 'Desserts' },
    'sweet bakery': { ar: 'حلويات المخبوزات', en: 'Sweet Bakery' },
    smoothy: { ar: 'سموذي', en: 'Smoothies' },
};

function cafeSpritePosition(label: string): string {
    const value = label.toLocaleLowerCase();
    // When the category is included in spriteLabel, prefer it over product keywords
    // (for example, “Milk Shake فراولة” must not be shown as fruit juice).
    if (/fresh juice|juice|عصير/.test(value)) return '0% 0%';
    if (/ice matcha|hot matcha|matcha|ماتشا/.test(value)) return '0% 50%';
    if (/mini pancakes|pancake|بان كيك|بانكيك/.test(value)) return '50% 50%';
    if (/waffle|وافل/.test(value)) return '100% 50%';
    if (/milk shake|smooth|shake|فرابيه|ميلك شيك|سموذي|سموثي/.test(value)) return '0% 100%';
    if (/ice coffee|soft drink|soda|cocktail|آيس كوفي|مشروبات غازية|مشروب بارد|كوكتيل|موهيتو|بيبسي|مياه|ريد بول/.test(value)) return '100% 100%';
    if (/coffee|espresso|latte|cappuccino|hot drink|قهوة|اسبرسو|إسبريسو|لاتيه|كابتشينو|موكا|مشروبات ساخنة|شاي|ينسون|زنجبيل|قرفة/.test(value)) return '100% 0%';
    if (/ice cream|dessert|sweet bakery|bakery|pastry|آيس كريم|حلويات|مخبوز|معجن|كيك|كرواسون|كروسان/.test(value)) return '50% 100%';
    if (/avocado|أفوكادو|موز بالحليب|بستاشيو|shake|smooth|فراولة نوتيلا/.test(value)) return '0% 100%';
    if (/مانجو|ليمون|برتقال|فراولة|جوافة|رمان|orange|mango|lemon|guava|pomegranate/.test(value)) return '0% 0%';
    return '50% 100%';
}

function placeholderEmoji(label: string): string {
    const value = label.toLocaleLowerCase();
    if (/matcha|ماتشا/.test(value)) return '🍵';
    if (/ice cream|آيس كريم|ايس كريم/.test(value)) return '🍨';
    if (/pancake|بان كيك|بانكيك/.test(value)) return '🥞';
    if (/waffle|وافل/.test(value)) return '🧇';
    if (/croissant|pastry|bakery|مخبوز|كروسان|كرواسون|معجن/.test(value)) return '🥐';
    if (/cake|dessert|sweet|كيك|حلويات|حلوى|مولتن|تيراميسو/.test(value)) return '🍰';
    if (/shake|smooth|فرابيه|ميلك شيك|سموذي|سموثي/.test(value)) return '🥤';
    if (/juice|عصير|مانجو|ليمون|فراولة|كوكتيل|cocktail/.test(value)) return '🍹';
    if (/coffee|espresso|latte|cappuccino|قهوة|اسبرسو|إسبريسو|لاتيه|كابتشينو|موكا/.test(value)) return '☕';
    if (/soda|drink|مشروب|بيبسي|مياه|ريد بول/.test(value)) return '🥤';
    if (/additions|إضافات|اضافات|topping/.test(value)) return '🍫';
    if (/avocado|أفوكادو|افوكادو/.test(value)) return '🥑';
    return '✦';
}

const motionCss = "@keyframes nxSteam{0%{opacity:0;transform:translateY(12px) scaleX(.8)}45%{opacity:.35}100%{opacity:0;transform:translateY(-26px) scaleX(1.15)}}@keyframes nxGlow{0%,100%{opacity:.25;transform:scale(.92)}50%{opacity:.62;transform:scale(1.08)}}@keyframes nxCupFloat{0%,100%{transform:translateY(0) rotate(-2deg)}50%{transform:translateY(-8px) rotate(2deg)}}@keyframes nxReveal{from{opacity:0;transform:translateY(14px) scale(.985)}to{opacity:1;transform:translateY(0) scale(1)}}@keyframes nxGoldShimmer{0%{transform:translateX(-130%);opacity:0}20%{opacity:.35}100%{transform:translateX(130%);opacity:0}}.nx-cafe{background-image:radial-gradient(ellipse at 15% 0%,rgba(214,164,91,.16),transparent 30%),radial-gradient(ellipse at 90% 38%,rgba(102,51,23,.15),transparent 32%),linear-gradient(180deg,rgba(255,255,255,.018),transparent 28%);background-attachment:fixed}.nx-cafe-hero{isolation:isolate;box-shadow:0 32px 90px rgba(0,0,0,.52),inset 0 1px 0 rgba(255,255,255,.2),0 0 0 1px rgba(214,164,91,.06);transform-style:preserve-3d;will-change:transform}.nx-cafe-hero:before{content:'';position:absolute;inset:0;z-index:2;pointer-events:none;background:linear-gradient(115deg,transparent 18%,rgba(255,255,255,.1) 34%,transparent 47%);mix-blend-mode:screen;opacity:.45}.nx-cafe-hero:after{content:'';position:absolute;width:210px;height:210px;left:-70px;top:20px;border-radius:999px;background:rgba(214,164,91,.2);filter:blur(48px);animation:nxGlow 6s ease-in-out infinite;pointer-events:none}.nx-steam{position:absolute;width:2px;height:34px;border-radius:99px;background:linear-gradient(transparent,rgba(255,255,255,.25),transparent);filter:blur(1px);animation:nxSteam 3.8s ease-in-out infinite}.nx-cafe-card{transform-style:preserve-3d;transition:transform .32s cubic-bezier(.2,.8,.2,1),box-shadow .32s,border-color .32s,background-color .32s;backface-visibility:hidden}.nx-cafe-card:hover{transform:perspective(900px) translateY(-5px) rotateX(1.5deg);box-shadow:0 22px 48px rgba(0,0,0,.34),inset 0 1px 0 rgba(255,255,255,.07);border-color:rgba(214,164,91,.38)!important}.nx-cafe-category-card{isolation:isolate;transform-style:preserve-3d;box-shadow:0 14px 28px rgba(0,0,0,.32),inset 0 1px 0 rgba(255,255,255,.12);transition:transform .35s cubic-bezier(.2,.8,.2,1),box-shadow .35s,border-color .35s}.nx-cafe-category-card:after{content:'';position:absolute;inset:0;z-index:1;pointer-events:none;border-radius:inherit;background:linear-gradient(135deg,rgba(255,255,255,.18),transparent 35%,transparent 70%,rgba(214,164,91,.12));opacity:.55}.nx-cafe-category-card:hover{transform:perspective(850px) translateY(-6px) rotateX(2deg) rotateY(-1.5deg);box-shadow:0 24px 42px rgba(0,0,0,.4),0 0 0 1px rgba(214,164,91,.22)}.nx-cafe-category-card:active{transform:perspective(850px) translateY(-1px) scale(.985)}.nx-cafe-category-chip{transform:translateZ(0);transition:transform .22s,box-shadow .22s,background-color .22s}.nx-cafe-category-chip:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(0,0,0,.2)}.nx-cafe-product-row{position:relative;isolation:isolate;box-shadow:0 9px 22px rgba(0,0,0,.14),inset 0 1px 0 rgba(255,255,255,.09)}.nx-cafe-product-row:before{content:'';position:absolute;inset:0;z-index:-1;pointer-events:none;border-radius:inherit;background:linear-gradient(120deg,rgba(255,255,255,.045),transparent 48%,rgba(214,164,91,.035))}.nx-cafe-card-media{transform:translateZ(12px);box-shadow:0 12px 24px rgba(0,0,0,.36),inset 0 1px 0 rgba(255,255,255,.18)}.nx-cafe-image-placeholder{position:relative;isolation:isolate;overflow:hidden;background:radial-gradient(circle at 50% 26%,rgba(238,193,119,.34),transparent 25%),radial-gradient(circle at 15% 90%,rgba(105,55,28,.65),transparent 42%),linear-gradient(145deg,#392315 0%,#160d08 54%,#050403 100%);box-shadow:inset 0 1px 0 rgba(255,255,255,.12)}.nx-cafe-image-placeholder:before{content:'';position:absolute;width:70%;aspect-ratio:1;top:8%;left:15%;border-radius:50%;border:1px solid rgba(241,205,143,.18);box-shadow:0 0 30px rgba(214,164,91,.12),inset 0 0 22px rgba(214,164,91,.08);transform:rotateX(62deg) rotateZ(-18deg)}.nx-cafe-image-placeholder:after{content:'';position:absolute;inset:0;pointer-events:none;background:linear-gradient(115deg,transparent 25%,rgba(255,255,255,.13) 43%,transparent 58%);transform:translateX(-130%);animation:nxGoldShimmer 7s ease-in-out infinite}.nx-cafe-image-placeholder>span{position:relative;z-index:1;display:grid;width:68px;height:68px;place-items:center;border:1px solid rgba(241,205,143,.22);border-radius:24px;background:linear-gradient(145deg,rgba(255,255,255,.14),rgba(255,255,255,.025));box-shadow:0 18px 32px rgba(0,0,0,.45),inset 0 1px 0 rgba(255,255,255,.25);text-shadow:0 8px 20px rgba(0,0,0,.5);transform:perspective(500px) rotateX(8deg) rotateY(-8deg) translateZ(18px)}.nx-cafe-glass{background:linear-gradient(135deg,rgba(255,255,255,.18),rgba(255,255,255,.035));backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.2);box-shadow:inset 0 1px 0 rgba(255,255,255,.23),0 16px 38px rgba(0,0,0,.34)}.nx-cup-float{animation:nxCupFloat 5s ease-in-out infinite}@media(hover:none){.nx-cafe-category-card:hover,.nx-cafe-card:hover,.nx-cafe-category-chip:hover{transform:none}}@media(prefers-reduced-motion:reduce){.nx-cafe-hero:before,.nx-cafe-hero:after,.nx-cafe-image-placeholder:after,.nx-steam,.nx-cup-float{animation:none}.nx-cafe-card,.nx-cafe-category-card,.nx-cafe-category-chip{transition:none}.nx-cafe-category-card:hover,.nx-cafe-card:hover,.nx-cafe-category-chip:hover{transform:none}}";

const showcaseCss = "@keyframes nxShowcaseFloat{0%,100%{translate:0 0;rotate:-4deg}50%{translate:0 -12px;rotate:3deg}}.nx-cafe-hero-showcase{position:absolute;inset:0;overflow:hidden;background:radial-gradient(ellipse at 71% 24%,rgba(214,164,91,.3),transparent 27%),radial-gradient(ellipse at 32% 70%,rgba(96,41,18,.65),transparent 42%),linear-gradient(135deg,#452818,#180d08 58%,#050403)}.nx-cafe-hero-showcase:before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 50% 65%,transparent 20%,rgba(0,0,0,.62) 86%),linear-gradient(120deg,rgba(255,255,255,.07),transparent 34%);pointer-events:none}.nx-cafe-showcase-orbit{position:absolute;width:min(64%,480px);aspect-ratio:1;top:-24%;right:9%;border:1px solid rgba(230,190,121,.22);border-radius:50%;transform:perspective(700px) rotateX(68deg) rotateZ(-18deg);box-shadow:0 0 60px rgba(214,164,91,.1),inset 0 0 40px rgba(214,164,91,.06)}.nx-cafe-showcase-item{position:absolute;display:grid;place-items:center;width:clamp(72px,10vw,142px);aspect-ratio:1;border:1px solid rgba(241,205,143,.28);border-radius:28px;background:linear-gradient(145deg,rgba(255,255,255,.19),rgba(255,255,255,.025) 52%,rgba(214,164,91,.09));box-shadow:0 28px 44px rgba(0,0,0,.48),inset 0 1px 0 rgba(255,255,255,.24),0 0 35px rgba(214,164,91,.08);backdrop-filter:blur(8px);font-size:clamp(44px,7.4vw,96px);text-shadow:0 14px 24px rgba(0,0,0,.6);transform-style:preserve-3d;animation:nxShowcaseFloat 6s ease-in-out infinite;will-change:translate,rotate}.nx-cafe-showcase-coffee{right:18%;top:13%;transform:perspective(700px) rotateY(-12deg) rotateX(8deg) translateZ(40px)}.nx-cafe-showcase-dessert{right:45%;top:24%;animation-delay:-1.8s;transform:perspective(700px) rotateY(10deg) rotateX(-6deg) translateZ(24px)}.nx-cafe-showcase-pancake{left:16%;top:22%;animation-delay:-3.2s;transform:perspective(700px) rotateY(-10deg) rotateX(7deg) translateZ(14px)}.nx-cafe-showcase-frappe{right:31%;top:51%;width:clamp(66px,8vw,112px);font-size:clamp(42px,6vw,72px);animation-delay:-2.4s;transform:perspective(700px) rotateY(12deg) rotateX(4deg)}.nx-cafe-showcase-chocolate{left:32%;top:53%;width:clamp(58px,7vw,96px);font-size:clamp(36px,5.4vw,64px);animation-delay:-4s}.nx-cafe-brandmark{font-family:Georgia,serif;letter-spacing:-.12em;padding-right:.12em;line-height:1;box-shadow:inset 0 1px 0 rgba(255,255,255,.12),0 8px 24px rgba(0,0,0,.3)}@media(max-width:640px){.nx-cafe-showcase-coffee{right:9%;top:10%}.nx-cafe-showcase-dessert{right:36%;top:18%}.nx-cafe-showcase-pancake{left:8%;top:22%}.nx-cafe-showcase-frappe{right:18%;top:42%}.nx-cafe-showcase-chocolate{left:23%;top:43%}.nx-cafe-showcase-orbit{width:82%;top:-20%;right:-2%}}@media(prefers-reduced-motion:reduce){.nx-cafe-showcase-item{animation:none;will-change:auto}}";

const spriteCss = ".nx-cafe-photo-fallback{position:relative;isolation:isolate;overflow:hidden;background-color:#1b0e08;background-repeat:no-repeat;box-shadow:inset 0 1px 0 rgba(255,255,255,.18),0 12px 24px rgba(0,0,0,.3)}.nx-cafe-photo-fallback:after{content:'';position:absolute;inset:0;pointer-events:none;background:linear-gradient(145deg,rgba(255,245,224,.12),transparent 32%,rgba(0,0,0,.3)),linear-gradient(115deg,transparent 26%,rgba(255,255,255,.18) 43%,transparent 56%);background-size:cover,220% 100%;background-position:center,-130% 0;animation:nxGoldShimmer 8s ease-in-out infinite}@media(prefers-reduced-motion:reduce){.nx-cafe-photo-fallback:after{animation:none}}";

function HeroShowcaseIllustration({ type }: { type: 'coffee' | 'cake' | 'pancakes' | 'frappe' | 'chocolate' }) {
    if (type === 'coffee') return <svg viewBox="0 0 160 150" aria-hidden="true" className="h-[88%] w-[88%] drop-shadow-[0_18px_16px_rgba(0,0,0,.5)]"><defs><linearGradient id="dpCoffeeCup" x1="0" y1="0" x2="1" y2="1"><stop stopColor="#fff4d8"/><stop offset=".38" stopColor="#b98a54"/><stop offset=".72" stopColor="#3d2013"/><stop offset="1" stopColor="#100906"/></linearGradient><radialGradient id="dpCoffeeCrema"><stop stopColor="#cfa16b"/><stop offset=".7" stopColor="#5b2e19"/><stop offset="1" stopColor="#241108"/></radialGradient><linearGradient id="dpCoffeeGold" x1="0" y1="0" x2="1" y2="1"><stop stopColor="#fff0b7"/><stop offset=".5" stopColor="#c58c3b"/><stop offset="1" stopColor="#6b411c"/></linearGradient></defs><ellipse cx="78" cy="128" rx="58" ry="12" fill="#000" opacity=".45"/><ellipse cx="77" cy="115" rx="53" ry="15" fill="url(#dpCoffeeGold)"/><ellipse cx="77" cy="112" rx="45" ry="10" fill="#211006" stroke="#f4d69a" strokeWidth="2"/><path d="M112 59h16c20 0 22 34 1 38h-17" fill="none" stroke="url(#dpCoffeeGold)" strokeWidth="10"/><path d="M112 59h16c20 0 22 34 1 38h-17" fill="none" stroke="#29160d" strokeWidth="5"/><path d="M32 53h82l-8 53q-3 12-32 12t-34-12z" fill="url(#dpCoffeeCup)" stroke="#edc887" strokeWidth="2"/><ellipse cx="73" cy="53" rx="41" ry="13" fill="url(#dpCoffeeGold)"/><ellipse cx="73" cy="52" rx="35" ry="9" fill="url(#dpCoffeeCrema)"/><path d="M73 47c-13-11-22 2-8 9l8 5 8-5c14-7 5-20-8-9m0 9v-7m-8 0 8 12 8-12" fill="none" stroke="#f3dfb3" strokeWidth="2.2" strokeLinecap="round"/><path d="M50 66l5 34m7-32 4 34" stroke="#fff3db" strokeWidth="3" opacity=".26" strokeLinecap="round"/><path d="M50 34q-10-11 0-21m20 18q-8-12 1-22m17 25q10-11 4-20" fill="none" stroke="#e6ca9d" strokeWidth="2.4" strokeLinecap="round" opacity=".7"/></svg>;
    if (type === 'cake') return <svg viewBox="0 0 160 150" aria-hidden="true" className="h-[88%] w-[88%] drop-shadow-[0_18px_16px_rgba(0,0,0,.5)]"><defs><linearGradient id="dpCakeTop" x1="0" y1="0" x2="1" y2="1"><stop stopColor="#6c321d"/><stop offset=".5" stopColor="#32160d"/><stop offset="1" stopColor="#130805"/></linearGradient><linearGradient id="dpCakeCream" x1="0" y1="0" x2="0" y2="1"><stop stopColor="#fff1d1"/><stop offset="1" stopColor="#c99a64"/></linearGradient><linearGradient id="dpCakePlate" x1="0" y1="0" x2="1" y2="1"><stop stopColor="#fff1c7"/><stop offset=".45" stopColor="#c89a53"/><stop offset="1" stopColor="#604019"/></linearGradient></defs><ellipse cx="79" cy="124" rx="61" ry="12" fill="#000" opacity=".45"/><ellipse cx="78" cy="113" rx="61" ry="18" fill="url(#dpCakePlate)"/><ellipse cx="78" cy="110" rx="52" ry="12" fill="#201009" stroke="#f2d198" strokeWidth="1.5"/><path d="M30 76 92 48l38 21-63 31z" fill="url(#dpCakeTop)" stroke="#d9a45e" strokeWidth="1.5"/><path d="M30 76 67 100v24L30 100z" fill="#3a170e"/><path d="M67 100 130 69v24l-63 31z" fill="#21100b"/><path d="M30 84 67 108l63-31v-8L67 98 30 76z" fill="url(#dpCakeCream)"/><path d="M30 96 67 120l63-31v-8L67 110 30 88z" fill="#6b2d19"/><path d="M30 76 92 48l38 21-63 31z" fill="url(#dpCakeTop)"/><path d="M48 70 93 49q17 3 15 13L63 83z" fill="#e9c58b" opacity=".6"/><path d="M98 43q-5-9 3-12 8 3 7 12" fill="#8b1516"/><path d="M103 43l3-8" stroke="#60913b" strokeWidth="3" strokeLinecap="round"/></svg>;
    if (type === 'pancakes') return <svg viewBox="0 0 160 150" aria-hidden="true" className="h-[88%] w-[88%] drop-shadow-[0_18px_16px_rgba(0,0,0,.5)]"><defs><linearGradient id="dpPanGold" x1="0" y1="0" x2="0" y2="1"><stop stopColor="#ffe5a0"/><stop offset=".6" stopColor="#cc8838"/><stop offset="1" stopColor="#7a3e19"/></linearGradient><linearGradient id="dpPanSyrup" x1="0" y1="0" x2="1" y2="1"><stop stopColor="#a64b1e"/><stop offset="1" stopColor="#391309"/></linearGradient></defs><ellipse cx="79" cy="127" rx="61" ry="12" fill="#000" opacity=".4"/><ellipse cx="79" cy="115" rx="59" ry="17" fill="#b98543"/><ellipse cx="79" cy="111" rx="52" ry="12" fill="#2b150b" stroke="#f0c77e" strokeWidth="1.5"/><path d="M36 97c-7-9 3-17 13-16 8-8 20-6 26-1 8-7 19-4 24 2 11-2 21 5 20 14-2 10-18 14-43 14S39 106 36 97" fill="url(#dpPanGold)"/><path d="M38 86c-7-9 3-16 13-15 8-8 20-6 26-1 8-7 19-4 24 2 11-2 21 5 20 14-2 10-18 14-43 14S41 95 38 86" fill="url(#dpPanGold)" stroke="#f0c77e" strokeWidth="1.5"/><path d="M40 75c-6-9 4-16 14-15 8-8 19-6 25 0 9-7 19-4 24 2 11-2 20 5 19 14-2 10-17 14-41 14S43 84 40 75" fill="url(#dpPanGold)" stroke="#f9dfa3" strokeWidth="1.5"/><path d="M46 63c8 9 23 7 29 0 11 7 22 7 35-2 8 9-2 18-16 19-15 3-30-1-48-17" fill="url(#dpPanSyrup)"/><rect x="72" y="52" width="19" height="15" rx="4" fill="#ffdf9b" stroke="#9e6127" strokeWidth="1.5" transform="rotate(-8 82 59)"/><circle cx="57" cy="56" r="8" fill="#b61f30"/><path d="M57 49q1-5 7-5" stroke="#5f8a39" strokeWidth="3" strokeLinecap="round"/><circle cx="105" cy="74" r="4" fill="#fcdd96" opacity=".9"/></svg>;
    if (type === 'frappe') return <svg viewBox="0 0 160 150" aria-hidden="true" className="h-[88%] w-[88%] drop-shadow-[0_18px_16px_rgba(0,0,0,.5)]"><defs><linearGradient id="dpFrappeGlass" x1="0" y1="0" x2="1" y2="0"><stop stopColor="#fff0d0" stopOpacity=".75"/><stop offset=".22" stopColor="#b77d51" stopOpacity=".7"/><stop offset=".6" stopColor="#5c2919" stopOpacity=".95"/><stop offset="1" stopColor="#190b06"/></linearGradient><linearGradient id="dpFrappeCream" x1="0" y1="0" x2="0" y2="1"><stop stopColor="#fff7e7"/><stop offset="1" stopColor="#d8b68b"/></linearGradient></defs><ellipse cx="82" cy="130" rx="42" ry="10" fill="#000" opacity=".42"/><path d="M65 38 108 38 101 120q-1 7-16 7t-16-7z" fill="url(#dpFrappeGlass)" stroke="#f0d3a0" strokeWidth="2"/><path d="M73 67 102 67 99 115q-13 10-26-1z" fill="#6a2a13" opacity=".9"/><path d="M78 28 83 13 90 13 86 35" fill="none" stroke="#d4a35d" strokeWidth="5" strokeLinecap="round"/><path d="M65 42q-6-15 8-16 6-16 16-5 14-3 15 9 12 4 2 14L66 50z" fill="url(#dpFrappeCream)" stroke="#fff3d7" strokeWidth="1.3"/><path d="M67 44q13 7 29-3m-25 1q20 7 29-8" fill="none" stroke="#7a3e20" strokeWidth="4" strokeLinecap="round"/><path d="M74 58 78 108" stroke="#fff7e7" strokeWidth="4" opacity=".38" strokeLinecap="round"/><circle cx="93" cy="86" r="5" fill="#e7bd87" opacity=".85"/><circle cx="80" cy="99" r="4" fill="#e7bd87" opacity=".85"/></svg>;
    return <svg viewBox="0 0 160 150" aria-hidden="true" className="h-[88%] w-[88%] drop-shadow-[0_18px_16px_rgba(0,0,0,.5)]"><defs><linearGradient id="dpChoc" x1="0" y1="0" x2="1" y2="1"><stop stopColor="#87502c"/><stop offset=".4" stopColor="#3e2115"/><stop offset="1" stopColor="#140905"/></linearGradient><linearGradient id="dpChocGold" x1="0" y1="0" x2="1" y2="1"><stop stopColor="#ffe5a2"/><stop offset=".5" stopColor="#c28a41"/><stop offset="1" stopColor="#603a19"/></linearGradient></defs><ellipse cx="80" cy="126" rx="54" ry="11" fill="#000" opacity=".45"/><path d="M29 83 70 60 112 80 71 103z" fill="url(#dpChocGold)"/><path d="M29 83 71 103v23L29 105z" fill="#29120b" stroke="#9e6b35" strokeWidth="1.5"/><path d="M71 103 112 80v22l-41 24z" fill="#180a05" stroke="#9e6b35" strokeWidth="1.5"/><path d="M36 66 77 43 119 63 78 86z" fill="url(#dpChoc)" stroke="#d6a45e" strokeWidth="1.5"/><path d="M36 66 78 86v18L36 84z" fill="#3c1d10" stroke="#ad743b" strokeWidth="1.4"/><path d="M78 86 119 63v18L78 104z" fill="#1d0c06" stroke="#ad743b" strokeWidth="1.4"/><path d="M45 49 70 35 93 46 68 61z" fill="url(#dpChocGold)" stroke="#f4d18f" strokeWidth="1.2"/><path d="M70 35 93 46v15L70 50z" fill="#3c1c10"/><path d="M45 49 68 61v15L45 64z" fill="#5d2b15"/><path d="M52 54 64 61m23-12 14 7" stroke="#e5b978" strokeWidth="2" opacity=".7" strokeLinecap="round"/></svg>;
}

function Image({ src, alt, className, priority = false, spriteLabel, usePhotoSprite = false }: { src?: string | null; alt: string; className: string; priority?: boolean; spriteLabel?: string; usePhotoSprite?: boolean }) {
    if (src) return <img src={src} alt={alt} className={className} loading={priority ? 'eager' : 'lazy'} fetchPriority={priority ? 'high' : 'auto'} decoding="async" />;
    if (usePhotoSprite) return <div role="img" aria-label={alt} className={className + ' nx-cafe-photo-fallback'} style={{ backgroundImage: `url(${cafeSprite})`, backgroundSize: '300% 300%', backgroundPosition: cafeSpritePosition(spriteLabel ?? alt) }}><span className="sr-only">{alt}</span></div>;
    return <div className={className + ' nx-cafe-image-placeholder flex items-center justify-center text-4xl'}><span aria-hidden="true">{placeholderEmoji(alt)}</span></div>;
}

export default function CafeTemplate({ menu, restaurantName, logoPath, restaurantPhone, restaurantWhatsappPhone, searchActive = false, preview = false }: Props) {
    const { locale } = useLocale();
    const theme = menu.theme ?? { primary: '#2b1d14', accent: '#d6a45b', background: '#0d0907', foreground: '#f7ecdc', radius: 'xl' };
    const radius = radiusMap[theme.radius as keyof typeof radiusMap] ?? radiusMap.xl;
    const categories = menu.categories ?? [];
    const visibleCategories = categories.filter((category: any) => (category.products ?? []).some((product: any) => product.is_available !== false));
    const [activeId, setActiveId] = useState<number | undefined>(categories[0]?.id);
    const [selected, setSelected] = useState<any>(null);
    const [variant, setVariant] = useState<number | null>(null);
    const [modifierIds, setModifierIds] = useState<number[]>([]);
    const [modifierError, setModifierError] = useState('');
    const [quantity, setQuantity] = useState(1);
    const [heroTilt, setHeroTilt] = useState({ x: 0, y: 0 });

    const current = visibleCategories.find((c: any) => c.id === activeId) ?? visibleCategories[0];
    const products = categories.flatMap((c: any) => c.products ?? []);
    const hero = products.find((p: any) => p.is_featured && p.is_available)
        ?? products.find((p: any) => p.image_path && p.is_available)
        ?? products.find((p: any) => /لاتيه|قهوة|كابتشينو|ماتشا|ميلك شيك|وافل|بان كيك|كيك|coffee|latte|matcha|waffle|cake/i.test(p.name) && p.is_available)
        ?? products.find((p: any) => p.is_available)
        ?? products[0];
    const logo = imageUrl(logoPath);
    // Restaurant records can use an Arabic display name while the menu carries the brand.
    const isDePapa = /de\s*papa|دي\s*بابا/i.test(`${restaurantName} ${menu.name ?? ''}`);
    const brandName = isDePapa ? 'DE PAPA' : restaurantName;
    const displayMenuName = isDePapa ? 'DE PAPA' : menu.name;
    const categoryLabel = (name: string) => {
        if (!isDePapa) return name;
        const label = dePapaCategoryLabels[name.trim().toLocaleLowerCase('en-US')];
        return label ? label[locale === 'ar' ? 'ar' : 'en'] : name;
    };
    const phone = (restaurantPhone ?? '').trim();
    const whatsapp = (restaurantWhatsappPhone ?? '').replace(/\D/g, '');
    const open = (product: any) => {
        requestProductConfiguration(product, theme);
        return;
        // Legacy state retained for the v1 product sheet until old menu versions age out.
        setSelected(product);
        setVariant(product.variants?.find((v: any) => v.is_active !== false)?.id ?? null);
        setModifierIds([]);
        setQuantity(1);
    };
    const close = () => { setSelected(null); setVariant(null); setModifierIds([]); setQuantity(1); };
    const selectedVariant = selected?.variants?.find((v: any) => v.id === variant);
    const price = selected ? Number(selected.price) + (selectedVariant?.price ? Number(selectedVariant.price) - Number(selected.price) : Number(selectedVariant?.price_delta ?? 0)) : 0;
    const addSelectedToOrder = () => {
        if (!selected) return;
        const validationError = modifierSelectionError(selected.modifiers ?? [], modifierIds);
        if (validationError) { setModifierError(validationError); return; }
        const modifiers = (selected.modifiers ?? []).filter((m: any) => m.is_active && modifierIds.includes(m.id)).map((m: any) => ({ id: m.id, name: m.name, price: Number(m.price_delta ?? 0) }));
        window.dispatchEvent(new CustomEvent('nexora:add-to-cart', { detail: { productId: selected.id, name: selected.name, price: price + modifiers.reduce((sum: number, m: any) => sum + m.price, 0), variantId: variant, variantName: selectedVariant?.name, modifiers, quantity } }));
        close();
        window.dispatchEvent(new Event('nexora:open-cart'));
    };

    return (
        <>
            <style>{motionCss + showcaseCss + spriteCss}</style><div dir="rtl" style={{ backgroundColor: theme.background, color: theme.foreground }} className="nx-cafe min-h-screen overflow-x-hidden bg-[#0d0907]">
            <header className={`${isDePapa ? 'relative' : 'sticky top-0'} z-40 border-b border-white/10 bg-[#0d0907]/90 backdrop-blur-xl`}>
                <div className="mx-auto flex h-[68px] max-w-3xl items-center justify-between px-4">
                    <button type="button" aria-label="عرض الأقسام" onClick={() => document.getElementById('cafe-menu')?.scrollIntoView({ behavior: 'smooth', block: 'start' })} className="grid h-10 w-10 place-items-center rounded-full border border-white/10 bg-white/[0.04] text-xl">☰</button>
                    <div className="text-center">
                        <div className="flex items-center justify-center gap-2">
                            {logo ? <img src={logo} alt={`شعار ${brandName}`} className="h-9 w-9 rounded-xl bg-white object-contain p-0.5 shadow-[0_8px_24px_rgba(0,0,0,.3)]" /> : <span aria-hidden="true" className="grid h-9 w-9 place-items-center rounded-xl border font-serif text-lg font-black shadow-[0_8px_24px_rgba(0,0,0,.3)]" style={{ color: theme.accent, borderColor: `${theme.accent}66`, background: `linear-gradient(145deg, ${theme.accent}22, transparent)` }}>DP</span>}
                            <strong className="font-serif text-lg tracking-[0.12em] text-[#f3d18d]">{brandName}</strong>
                        </div>
                        {preview && <p className="text-[8px] tracking-[0.28em] text-white/30">NEXORA PREVIEW</p>}
                    </div>
                    <button type="button" aria-label="فتح السلة" onClick={() => window.dispatchEvent(new Event('nexora:open-cart'))} className="relative grid h-10 w-10 place-items-center rounded-full border border-white/10 text-xl" style={{ color: theme.accent }}>
                        🛒
                    </button>
                </div>
            </header>

            <main className="mx-auto max-w-6xl pb-28">
                <section className="relative px-3 pt-3">
                    <div className="nx-cafe-hero relative overflow-hidden animate-[nxReveal_.7s_ease-out_both] border border-white/10 shadow-2xl" style={{ borderRadius: '0 0 ' + radius + ' ' + radius }} onPointerMove={(event) => { if (event.pointerType === 'touch') return; const rect = event.currentTarget.getBoundingClientRect(); setHeroTilt({ x: ((event.clientY - rect.top) / rect.height - 0.5) * -7, y: ((event.clientX - rect.left) / rect.width - 0.5) * 9 }); }} onPointerLeave={() => setHeroTilt({ x: 0, y: 0 })}>
                        <div className="aspect-[0.9] min-h-[390px] transition-transform duration-300 ease-out sm:aspect-[1.25] lg:aspect-[2.1]" style={{ transform: `perspective(1100px) rotateX(${heroTilt.x * 0.55}deg) rotateY(${heroTilt.y * 0.55}deg) scale(1.035)` }}>
                            {hero?.image_path ? <Image priority src={imageUrl(hero.image_path)} alt={hero.name} className="h-full w-full object-cover" /> : <div className="nx-cafe-hero-showcase" aria-hidden="true"><span className="nx-cafe-showcase-orbit" /><span className="nx-cafe-showcase-item nx-cafe-showcase-coffee">{isDePapa ? <Image alt="قهوة DE PAPA" spriteLabel="Speciality Coffee" usePhotoSprite className="h-[92%] w-[92%] rounded-2xl object-cover" /> : <HeroShowcaseIllustration type="coffee" />}</span><span className="nx-cafe-showcase-item nx-cafe-showcase-dessert">{isDePapa ? <Image alt="حلويات DE PAPA" spriteLabel="Dessert" usePhotoSprite className="h-[92%] w-[92%] rounded-2xl object-cover" /> : <HeroShowcaseIllustration type="cake" />}</span><span className="nx-cafe-showcase-item nx-cafe-showcase-pancake">{isDePapa ? <Image alt="بان كيك DE PAPA" spriteLabel="Mini Pancakes" usePhotoSprite className="h-[92%] w-[92%] rounded-2xl object-cover" /> : <HeroShowcaseIllustration type="pancakes" />}</span><span className="nx-cafe-showcase-item nx-cafe-showcase-frappe">{isDePapa ? <Image alt="مشروبات DE PAPA" spriteLabel="Milk Shake" usePhotoSprite className="h-[92%] w-[92%] rounded-2xl object-cover" /> : <HeroShowcaseIllustration type="frappe" />}</span><span className="nx-cafe-showcase-item nx-cafe-showcase-chocolate">{isDePapa ? <Image alt="حلوى بالشوكولاتة من DE PAPA" spriteLabel="Dessert" usePhotoSprite className="h-[92%] w-[92%] rounded-2xl object-cover" /> : <HeroShowcaseIllustration type="chocolate" />}</span></div>}
                        </div>
                        <div className="absolute inset-0 bg-[linear-gradient(to_top,rgba(5,3,2,0.97),rgba(5,3,2,0.18)_58%,rgba(5,3,2,0.05))]" />
                        {hero && <div className="nx-cafe-glass absolute right-4 top-5 z-10 max-w-[68%] rounded-2xl p-3 shadow-2xl sm:right-6 sm:top-7 sm:p-4" style={{ transform: 'perspective(700px) rotateY(7deg) rotateX(3deg)' }}><p className="text-[8px] uppercase tracking-[0.24em] text-white/55">MENU HIGHLIGHT</p><p className="mt-1 truncate text-xs font-black text-white sm:text-sm">{hero.name}</p><p className="mt-1 text-sm font-black" style={{ color: theme.accent }}>{money(hero.price)}</p><div className="mt-2 flex gap-1">{[0, 1, 2, 3, 4].map(dot => <span key={dot} className="h-1 w-4 rounded-full" style={{ backgroundColor: theme.accent, opacity: dot < 4 ? .9 : .25 }} />)}</div></div>}
                        <div className="absolute inset-x-0 bottom-0 p-6 [transform:translateZ(28px)]">
                            <div className="nx-cafe-glass mb-4 inline-flex items-center gap-2 rounded-full px-4 py-2 text-[9px] text-white/90"><span className="nx-cup-float text-base" style={{ color: theme.accent }}>✦</span> قهوة معمولة على مزاجك</div>
                            <p className="text-[10px] font-bold uppercase tracking-[0.24em]" style={{ color: theme.accent }}>Café & Desserts</p>
                            <h1 className="mt-3 font-serif text-4xl font-bold leading-tight text-white">{displayMenuName}</h1>
                            <p className="mt-2 max-w-xs text-[11px] leading-6 text-white/65">{isDePapa ? 'قهوة مختصة، حلويات مميزة، ومشروبات معمولة علشان كل لحظة تبقى أحلى.' : 'من أول رشفة لآخر لقمة، اختار قهوتك وحلوياتك المفضلة واستمتع بلحظتك.'}</p>
                        </div>
                    </div>
                </section>

                <section className="px-4 pt-7">
                    <div className="mb-4">
                        <p className="text-[9px] text-white/30">استكشف</p>
                        <h2 className="mt-1 text-xl font-black">{searchActive ? 'نتائج البحث' : 'اختار لحظتك'}</h2>
                    </div>
                    <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                        {visibleCategories.slice(0, 4).map((category: any) => {
                            const photo = category.products?.find((p: any) => p.image_path) ?? category.products?.[0];
                            return <button key={category.id} type="button" onClick={() => {
                                setActiveId(category.id);
                                window.setTimeout(() => document.getElementById('cafe-menu')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 0);
                            }} className="nx-cafe-category-card group relative min-h-36 overflow-hidden border border-white/10 text-right" style={{ borderRadius: radius }}>
                                <Image src={imageUrl(photo?.image_path)} alt={category.name} spriteLabel={category.name} usePhotoSprite={isDePapa} className="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105" />
                                <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/25 to-transparent" />
                                <div className="absolute inset-x-0 bottom-0 p-3">
                                    <p className="text-[9px]" style={{ color: theme.accent }}>{category.products?.length ?? 0} صنف</p>
                                    <h3 className="mt-1 text-base font-black text-white">{categoryLabel(category.name)}</h3>
                                    <span className="mt-2 inline-flex h-7 w-7 items-center justify-center rounded-full text-black" style={{ backgroundColor: theme.accent }}>←</span>
                                </div>
                            </button>;
                        })}
                    </div>
                </section>

                <section id="cafe-menu" className="scroll-mt-20 px-4 pt-9">
                    <div className="mb-4 flex items-end justify-between">
                        <div><p className="text-[9px] uppercase tracking-[0.3em] text-white/30">Menu</p><h2 className="mt-1 text-2xl font-black">{current ? categoryLabel(current.name) : 'القائمة'}</h2></div>
                        <span className="text-[10px] text-white/30">{current?.products?.length ?? 0} اختيار</span>
                    </div>
                    <div className="flex gap-2 overflow-x-auto pb-2">
                        {visibleCategories.map((c: any) => <button key={c.id} type="button" aria-pressed={activeId === c.id} onClick={() => setActiveId(c.id)} className="nx-cafe-category-chip shrink-0 border px-4 py-2 text-xs font-bold" style={{ borderRadius: 999, borderColor: activeId === c.id ? theme.accent : 'rgba(255,255,255,.1)', backgroundColor: activeId === c.id ? theme.accent : 'rgba(255,255,255,.04)', color: activeId === c.id ? '#1a1009' : 'rgba(255,255,255,.68)', boxShadow: activeId === c.id ? `0 7px 22px ${theme.accent}35` : undefined }}>{categoryLabel(c.name)}</button>)}
                    </div>
                    <div className="mt-5 space-y-5">
                        {(searchActive ? visibleCategories : current ? [current] : []).map((category: any) => <div key={category.id} className="space-y-2.5">
                        {searchActive && <h3 className="border-r-2 pr-3 text-sm font-black" style={{ borderColor: theme.accent }}>{categoryLabel(category.name)}</h3>}
                        {(category.products ?? []).map((product: any) => <button key={product.id} type="button" disabled={!product.is_available} onClick={() => open(product)} className="nx-cafe-card nx-cafe-product-row group flex w-full items-center gap-3 border border-white/10 bg-white/[0.035] p-2 text-right active:scale-[.99] disabled:opacity-40" style={{ borderRadius: radius }}>
                            <div className="nx-cafe-card-media h-20 w-20 shrink-0 overflow-hidden rounded-xl ring-1 ring-white/10"><Image src={imageUrl(product.image_path)} alt={product.name} spriteLabel={`${category.name} ${product.name}`} usePhotoSprite={isDePapa} className="h-full w-full object-cover transition duration-500 group-hover:scale-110" /></div>
                            <div className="min-w-0 flex-1 py-1">
                                <div className="flex items-start justify-between gap-2"><h3 className="truncate text-sm font-black text-white">{product.name}</h3>{product.is_featured && <span className="text-[8px] font-bold" style={{ color: theme.accent }}>مميز</span>}</div>
                                {product.description && <p className="mt-1 line-clamp-2 text-[10px] leading-5 text-white/40">{product.description}</p>}
                                <div className="mt-2 flex items-center gap-2"><span className="text-sm font-black" style={{ color: theme.accent }}>{money(product.price)}</span>{product.variants?.length ? <span className="text-[9px] text-white/30">+ {product.variants.length} اختيارات</span> : null}</div>
                            </div>
                            <span className="nx-cafe-glass grid h-9 w-9 shrink-0 place-items-center rounded-full border text-lg" style={{ borderColor: theme.accent, color: theme.accent }}>+</span>
                        </button>)}
                        </div>)}
                    </div>
                </section>
            </main>

            <footer className="border-t border-white/10 px-4 py-7 text-center"><p className="font-bold tracking-[0.12em]">{brandName}</p><p className="mt-1 text-xs text-white/45">{isDePapa ? 'Café & Desserts' : displayMenuName} · NEXORA QR</p><div className="mt-4 flex justify-center gap-2">{phone && <a href={`tel:${phone}`} className="rounded-xl border border-white/15 px-4 py-2.5 text-xs font-bold">اتصال بالمطعم</a>}{whatsapp && <a href={`https://wa.me/${whatsapp}`} target="_blank" rel="noreferrer" className="rounded-xl border border-white/15 px-4 py-2.5 text-xs font-bold">واتساب</a>}<button type="button" onClick={() => window.dispatchEvent(new Event('nexora:open-cart'))} className="rounded-xl px-4 py-2.5 text-xs font-black text-black" style={{ backgroundColor: theme.accent }}>عرض السلة</button></div></footer>

            <div className="hidden">
                <div className="pointer-events-auto mx-auto flex max-w-3xl items-center gap-3 border border-white/10 bg-[#0d0b09]/95 p-2 shadow-2xl backdrop-blur-xl" style={{ borderRadius: radius }}>
                    <div className="flex min-w-0 flex-1 items-center gap-3 px-2"><span className="grid h-10 w-10 place-items-center rounded-xl" style={{ backgroundColor: theme.accent + '18', color: theme.accent }}>🛒</span><div><p className="text-[9px] uppercase tracking-widest text-white/30">Your order</p><p className="text-xs font-bold">السلة جاهزة للطلب</p></div></div>
                    <button type="button" onClick={() => window.dispatchEvent(new Event('nexora:open-cart'))} className="rounded-xl px-5 py-3 text-xs font-black text-black" style={{ backgroundColor: theme.accent }}>عرض الطلب</button>
                </div>
            </div>

            {selected && <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/75 backdrop-blur-sm sm:items-center sm:p-4" onClick={close}>
                <div role="dialog" aria-modal="true" className="max-h-[92vh] w-full max-w-lg overflow-y-auto border border-white/10 bg-[#100d0b] shadow-2xl" style={{ borderRadius: radius + ' ' + radius + ' 0 0' }} onClick={e => e.stopPropagation()}>
                    <div className="relative"><div className="aspect-[1.15]"><Image src={imageUrl(selected.image_path)} alt={selected.name} className="h-full w-full object-cover" /></div><div className="absolute inset-0 bg-gradient-to-t from-[#100d0b] via-transparent to-black/10" /><button type="button" onClick={close} aria-label="إغلاق" className="absolute right-4 top-4 grid h-9 w-9 place-items-center rounded-full bg-black/50 text-white">×</button></div>
                    <div className="p-5">
                        <div className="flex items-start justify-between gap-4"><div><h2 className="text-2xl font-black">{selected.name}</h2><p className="mt-2 text-xs leading-6 text-white/45">{selected.description}</p></div><strong style={{ color: theme.accent }}>{money(price)}</strong></div>
                        {selected.variants?.length > 0 && <div className="mt-6"><h3 className="mb-2 text-sm font-black">الحجم</h3><div className="grid grid-cols-3 gap-2">{selected.variants.filter((v: any) => v.is_active !== false).map((v: any) => <button key={v.id} type="button" onClick={() => setVariant(variant === v.id ? null : v.id)} className="border p-3 text-center" style={{ borderRadius: radius, borderColor: variant === v.id ? theme.accent : 'rgba(255,255,255,.08)', backgroundColor: variant === v.id ? theme.accent + '12' : 'rgba(255,255,255,.03)' }}><span className="block text-xs font-bold">{v.name}</span><span className="mt-1 block text-[10px]" style={{ color: theme.accent }}>{v.price ? money(v.price) : (Number(v.price_delta ?? 0) >= 0 ? '+' : '') + money(v.price_delta ?? 0)}</span></button>)}</div></div>}
                        {selected.modifiers?.filter((m: any) => m.is_active).length > 0 && <div className="mt-6"><h3 className="mb-2 text-sm font-black">ظبّط طلبك على مزاجك</h3><div className="space-y-2">{selected.modifiers.filter((m: any) => m.is_active).map((m: any) => { const checked = modifierIds.includes(m.id); return <button type="button" key={m.id} aria-pressed={checked} onClick={() => { setModifierIds(ids => toggleModifierSelection(selected.modifiers ?? [], ids, m)); setModifierError(''); }} className="flex w-full items-center justify-between border p-3 text-right transition" style={{ borderRadius: radius, borderColor: checked ? theme.accent : 'rgba(255,255,255,.1)', backgroundColor: checked ? theme.accent + '16' : 'rgba(255,255,255,.03)' }}><span className="flex items-center gap-2 text-xs font-bold"><span className="grid h-5 w-5 place-items-center rounded-md border" style={{ borderColor: checked ? theme.accent : 'rgba(255,255,255,.3)', backgroundColor: checked ? theme.accent : 'transparent', color: '#160f08' }}>{checked ? '✓' : ''}</span><span>{m.name}{m.group_name && <span className="block text-[9px] opacity-60">{m.group_name} · {m.group_min_choices ?? 0}–{m.group_max_choices ?? 1}</span>}</span></span><span className="text-xs" style={{ color: theme.accent }}>+{money(m.price_delta)}</span></button>; })}</div>{modifierError && <p role="alert" className="mt-2 text-xs font-bold text-rose-500">{modifierError}</p>}</div>}
                        <div className="mt-7 flex gap-3"><div className="flex items-center rounded-xl border border-white/10 p-1"><button type="button" onClick={() => setQuantity(Math.max(1, quantity - 1))} className="h-10 w-10">−</button><span className="w-8 text-center font-bold">{quantity}</span><button type="button" disabled={quantity >= 30} onClick={() => setQuantity(Math.min(30, quantity + 1))} className="h-10 w-10 disabled:opacity-30">+</button></div><button type="button" onClick={addSelectedToOrder} className="flex-1 py-3.5 text-sm font-black text-black" style={{ backgroundColor: theme.accent, borderRadius: radius }}>إضافة للطلب · {money((price + (selected.modifiers ?? []).filter((m: any) => modifierIds.includes(m.id)).reduce((sum: number, m: any) => sum + Number(m.price_delta ?? 0), 0)) * quantity)}</button></div>
                    </div>
                </div>
            </div>}
        </div>
        </>
    );
}
