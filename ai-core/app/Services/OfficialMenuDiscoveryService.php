<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Finds menu links only on a restaurant's verified, same-host HTTPS website.
 * It deliberately does not crawl Google/Facebook or bypass robots.txt.
 */
class OfficialMenuDiscoveryService
{
    private const MAX_HTML_BYTES = 2_000_000;

    private const MAX_MENU_BYTES = 8_000_000;

    private const MAX_CANDIDATES = 30;

    private const USER_AGENT = 'NEXORA-MenuResearch/1.0';

    /** @return array{source_url:string,site_name:string|null,candidates:array<int,array<string,string>>,content_hash:string} */
    public function discover(string $websiteUrl): array
    {
        $url = $this->assertPublicHttpsUrl($websiteUrl);
        $this->assertRobotsAllows($url);
        $response = $this->request($url, self::MAX_HTML_BYTES, 'text/html,application/xhtml+xml', true);
        $contentType = strtolower((string) $response->header('Content-Type'));

        if (! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'application/xhtml+xml')) {
            throw new RuntimeException('الموقع الرسمي لم يُرجع صفحة HTML قابلة للفحص. افتح رابط المنيو يدويًا أو ارفع صورة منه.');
        }

        $html = $response->body();
        $this->assertBodySize($html, self::MAX_HTML_BYTES);
        $candidates = $this->extractCandidates($html, $url);

        if ($candidates === [] && preg_match('/(?:منيو|قائمة\s+الطعام|menu|food\s+menu)/iu', strip_tags($html))) {
            $candidates[] = [
                'url' => $url,
                'label' => 'الصفحة الرسمية تحتوي على إشارة للمنيو',
                'kind' => 'html',
                'host' => (string) parse_url($url, PHP_URL_HOST),
            ];
        }

        return [
            'source_url' => $url,
            'site_name' => $this->extractSiteName($html),
            'candidates' => array_slice($candidates, 0, self::MAX_CANDIDATES),
            'content_hash' => hash('sha256', $html),
        ];
    }

    private function extractSiteName(string $html): ?string
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        $xpath = new \DOMXPath($dom);
        $candidates = [];
        foreach ([
            '//meta[translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="og:site_name"]/@content',
            '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="application-name"]/@content',
            '//meta[@itemprop="name"]/@content',
            '//h1[1]',
            '//title[1]',
        ] as $query) {
            $nodes = $xpath->query($query);
            if (! $nodes) {
                continue;
            }
            foreach ($nodes as $node) {
                $value = trim(preg_replace('/\s+/u', ' ', $node->nodeValue ?? $node->textContent ?? '') ?? '');
                $value = trim($value, " \t\n\r\0\x0B-|–—·:");
                if ($value !== '' && mb_strlen($value) >= 2) {
                    $candidates[] = mb_substr($value, 0, 180);
                }
            }
            if ($candidates !== []) {
                break;
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach ($candidates as $name) {
            if (! preg_match('/^(?:home|homepage|home page|menu|المنيو|قائمة الطعام|الرئيسية|مطعم)$/iu', $name)) {
                return $name;
            }
        }

        return null;
    }

    /** @param array<int,array<string,string>> $candidates @return array{kind:string,mime:string,body:string,content_hash:string} */
    public function fetchCandidate(string $websiteUrl, string $candidateUrl, array $candidates): array
    {
        $website = $this->assertPublicHttpsUrl($websiteUrl);
        $candidate = $this->assertPublicHttpsUrl($candidateUrl);
        $allowed = collect($candidates)->contains(fn ($item) => is_array($item) && isset($item['url']) && hash_equals((string) $item['url'], $candidate));

        if (! $allowed || ! $this->sameHost($website, $candidate)) {
            throw new RuntimeException('رابط المنيو لازم يكون من الروابط التي اكتشفناها على الموقع الرسمي نفسه.');
        }

        $this->assertRobotsAllows($candidate);
        $response = $this->request($candidate, self::MAX_MENU_BYTES, 'text/html,application/xhtml+xml,application/pdf,image/jpeg,image/png,image/webp', true);
        $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $body = $response->body();
        $this->assertBodySize($body, self::MAX_MENU_BYTES);

        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $kind = 'image';
        } elseif ($mime === 'application/pdf' || str_ends_with(strtolower((string) parse_url($candidate, PHP_URL_PATH)), '.pdf')) {
            $kind = 'pdf';
            $mime = 'application/pdf';
        } elseif (str_contains($mime, 'text/html') || str_contains($mime, 'application/xhtml+xml')) {
            $kind = 'html';
            $body = $this->visibleText($body);
            if (mb_strlen($body) > 40000) {
                $body = mb_substr($body, 0, 40000);
            }
        } else {
            throw new RuntimeException('نوع الملف غير مدعوم للمعالجة الآلية. استخدم صفحة HTML أو صورة JPG/PNG/WEBP من الموقع الرسمي.');
        }

        return ['kind' => $kind, 'mime' => $mime, 'body' => $body, 'content_hash' => hash('sha256', $body)];
    }

    private function extractCandidates(string $html, string $baseUrl): array
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $results = [];
        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $href = trim((string) $anchor->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#') || preg_match('/^(?:javascript|data|mailto|tel):/i', $href)) {
                continue;
            }

            $absolute = $this->resolveUrl($baseUrl, $href);
            if (! $absolute || ! $this->sameHost($baseUrl, $absolute)) {
                continue;
            }

            $path = strtolower((string) parse_url($absolute, PHP_URL_PATH));
            $label = trim(preg_replace('/\s+/u', ' ', $anchor->textContent ?? '') ?? '');
            $hints = mb_strtolower($label.' '.$href);
            $kind = null;
            if (preg_match('/\.pdf$/i', $path)) {
                $kind = 'pdf';
            } elseif (preg_match('/\.(?:jpe?g|png|webp)$/i', $path)) {
                $kind = 'image';
            } elseif (preg_match('/(?:منيو|قائمة(?:\s+الطعام)?|menu|food[-_ ]?menu|our-food)/iu', $hints)) {
                $kind = 'html';
            }

            if (! $kind) {
                continue;
            }

            $results[$absolute] = [
                'url' => $absolute,
                'label' => $label !== '' ? mb_substr($label, 0, 160) : basename($path),
                'kind' => $kind,
                'host' => (string) parse_url($absolute, PHP_URL_HOST),
            ];

            if (count($results) >= self::MAX_CANDIDATES) {
                break;
            }
        }

        return array_values($results);
    }

    private function visibleText(string $html): string
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//script|//style|//noscript|//svg|//nav|//footer|//form') ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }
        $text = preg_replace('/\s+/u', ' ', $dom->textContent ?? '') ?? '';
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function resolveUrl(string $baseUrl, string $href): ?string
    {
        $base = parse_url($baseUrl);
        if (! is_array($base) || empty($base['host'])) {
            return null;
        }
        if (str_starts_with($href, '//')) {
            $candidate = 'https:'.$href;
        } elseif (parse_url($href, PHP_URL_SCHEME)) {
            $candidate = $href;
        } elseif (str_starts_with($href, '/')) {
            $candidate = 'https://'.$base['host'].$href;
        } else {
            $basePath = (string) ($base['path'] ?? '/');
            $directory = substr($basePath, 0, (int) strrpos($basePath, '/') + 1);
            $candidate = 'https://'.$base['host'].($directory ?: '/').$href;
        }

        $parts = parse_url($candidate);
        if (! is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $path = (string) ($parts['path'] ?? '/');
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
            } else {
                $segments[] = $segment;
            }
        }
        $normalPath = '/'.implode('/', $segments);

        return 'https://'.strtolower($parts['host']).$normalPath.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function assertPublicHttpsUrl(string $url): string
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('رابط الموقع غير صالح.');
        }
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? null) !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
            throw new RuntimeException('الفحص الآلي يقبل HTTPS على المنفذ القياسي فقط، بدون بيانات دخول داخل الرابط.');
        }

        if (in_array($host, ['localhost', 'localhost.localdomain'], true) || preg_match('/\.(?:localhost|local|internal|lan)$/i', $host)) {
            throw new RuntimeException('لا يمكن فحص عناوين الشبكات الداخلية.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('العنوان يشير إلى شبكة غير عامة.');
            }
        } elseif (app()->environment('testing') && preg_match('/\.(?:test|example)$/', $host)) {
            // Reserved test domains keep HTTP fakes deterministic and never
            // weaken production URL checks.
        } else {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
            $ips = [];
            foreach ($records as $record) {
                if (! empty($record['ip'])) {
                    $ips[] = $record['ip'];
                }
                if (! empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
            if ($ips === []) {
                throw new RuntimeException('تعذر التأكد أن الموقع عنوان عام؛ أوقفنا الاتصال للحماية.');
            }
            foreach ($ips as $ip) {
                if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    throw new RuntimeException('الموقع يحل إلى عنوان غير عام؛ أوقفنا الاتصال للحماية.');
                }
            }
        }

        return rtrim($url, '/');
    }

    private function assertRobotsAllows(string $url): void
    {
        $parts = parse_url($url);
        $robotsUrl = 'https://'.$parts['host'].'/robots.txt';
        $response = $this->request($robotsUrl, 200_000, 'text/plain', true);
        if ($response->status() === 404) {
            return;
        }
        if (! $response->successful()) {
            throw new RuntimeException('تعذر التحقق من robots.txt للموقع؛ أوقفنا الفحص احترامًا لقواعد الموقع.');
        }

        $groups = [];
        $agents = [];
        foreach (preg_split('/\r?\n/', $response->body()) ?: [] as $line) {
            $line = trim(explode('#', $line, 2)[0]);
            if (! str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $key = strtolower($key);
            if ($key === 'user-agent') {
                if ($groups === [] || ! empty($groups[count($groups) - 1]['rules'])) {
                    $groups[] = ['agents' => [], 'rules' => []];
                }
                $groups[count($groups) - 1]['agents'][] = strtolower($value);
            } elseif (in_array($key, ['allow', 'disallow'], true) && $groups !== []) {
                $groups[count($groups) - 1]['rules'][] = ['kind' => $key, 'path' => $value];
            }
        }
        foreach ($groups as $group) {
            if (in_array('nexora-menuresearch', $group['agents'], true) || in_array('*', $group['agents'], true)) {
                $agents = array_merge($agents, $group['rules']);
            }
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        $matched = null;
        foreach ($agents as $rule) {
            if ($rule['path'] === '' || ! str_starts_with($path, $rule['path'])) {
                continue;
            }
            if ($matched === null || strlen($rule['path']) > strlen($matched['path']) || (strlen($rule['path']) === strlen($matched['path']) && $rule['kind'] === 'allow')) {
                $matched = $rule;
            }
        }
        if ($matched && $matched['kind'] === 'disallow') {
            throw new RuntimeException('الموقع يمنع فحص هذا المسار في robots.txt.');
        }
    }

    private function request(string $url, int $maxBytes, string $accept, bool $allowNotFound = false)
    {
        $response = Http::accept($accept)
            ->withHeaders(['User-Agent' => self::USER_AGENT, 'Range' => 'bytes=0-'.($maxBytes - 1)])
            ->connectTimeout(4)
            ->timeout(12)
            ->withOptions(['allow_redirects' => false, 'verify' => true])
            ->get($url);

        if ($response->redirect()) {
            throw new RuntimeException('الموقع أعاد تحويلًا؛ افتح الرابط النهائي الرسمي يدويًا وأعد المحاولة.');
        }
        if ($allowNotFound && $response->status() === 404) {
            return $response;
        }
        if (! $response->successful()) {
            throw new RuntimeException('تعذر فتح صفحة الموقع. HTTP '.$response->status());
        }
        $this->assertBodySize($response->body(), $maxBytes);

        return $response;
    }

    private function assertBodySize(string $body, int $maxBytes): void
    {
        if (strlen($body) > $maxBytes) {
            throw new RuntimeException('حجم الصفحة أو المنيو أكبر من الحد المسموح للفحص الآمن.');
        }
    }

    private function sameHost(string $left, string $right): bool
    {
        return strtolower((string) parse_url($left, PHP_URL_HOST)) === strtolower((string) parse_url($right, PHP_URL_HOST));
    }
}
