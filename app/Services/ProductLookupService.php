<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Looks a packaged product up by barcode in Open Food Facts, a free public database.
 *
 * Used only while registering products (Smart Scanner). Checkout never calls this,
 * it always works from the products already saved in the shop's own database.
 *
 * find() returns ['status' => 'found'|'not_found'|'unavailable', 'data' => array|null]
 * and never throws, so a failed lookup can never block registration.
 */
class ProductLookupService
{
    const ENDPOINT = 'https://world.openfoodfacts.org/api/v2/product/';

    const FIELDS = 'code,product_name,product_name_en,generic_name,brands,quantity,image_front_url';

    const USER_AGENT = 'DantePOS-SmartScanner/1.0';

    // Pictures are only copied from Open Food Facts' own image servers.
    const IMAGE_HOSTS = ['images.openfoodfacts.org', 'static.openfoodfacts.org'];

    const MAX_IMAGE_BYTES = 2097152; // 2 MB

    const MAX_IMAGE_SIDE = 4000; // pixels

    const CACHE_FOUND_SECONDS = 2592000; // 30 days

    const CACHE_NOT_FOUND_SECONDS = 86400; // 1 day

    public static function normalizeCode($code): string
    {
        return preg_replace('/\s+/', '', trim((string) $code));
    }

    /**
     * Only plain retail barcodes (EAN-8, UPC-A, EAN-13, ITF-14 lengths) are sent online.
     * Short shop codes like L001 are never sent anywhere.
     */
    public static function isLookupable(string $code): bool
    {
        return (bool) preg_match('/^\d{8,14}$/', $code);
    }

    public function find(string $code): array
    {
        $none = ['status' => 'not_found', 'data' => null];

        if (! self::isLookupable($code)) {
            return $none;
        }

        $key = 'smartscan.off.'.$code;

        try {
            $cached = Cache::get($key);
            if (is_array($cached) && isset($cached['status'])) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // A broken cache must never stop the lookup.
        }

        try {
            $response = Http::timeout(6)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->withOptions(['allow_redirects' => false])
                ->get(self::ENDPOINT.$code.'.json', ['fields' => self::FIELDS]);
        } catch (\Throwable $e) {
            Log::warning('SmartScanner lookup failed: '.$e->getMessage());

            return ['status' => 'unavailable', 'data' => null];
        }

        // Open Food Facts answers 404 when it does not know the barcode.
        if ($response->status() === 404) {
            $result = $none;
        } elseif ($response->successful()) {
            $result = self::parse($response->json());
        } else {
            return ['status' => 'unavailable', 'data' => null];
        }

        try {
            Cache::put(
                $key,
                $result,
                $result['status'] === 'found' ? self::CACHE_FOUND_SECONDS : self::CACHE_NOT_FOUND_SECONDS
            );
        } catch (\Throwable $e) {
        }

        return $result;
    }

    /**
     * Turns an Open Food Facts response into the few fields the shop needs.
     * Nothing is invented: a missing field stays empty.
     */
    public static function parse($json): array
    {
        $none = ['status' => 'not_found', 'data' => null];

        if (! is_array($json) || (int) ($json['status'] ?? 0) !== 1 || empty($json['product']) || ! is_array($json['product'])) {
            return $none;
        }

        $product = $json['product'];
        $name = self::buildName($product);
        if ($name === '') {
            return $none;
        }

        $brands = self::clean($product['brands'] ?? '');
        $first_brand = $brands === '' ? '' : trim(explode(',', $brands)[0]);

        return [
            'status' => 'found',
            'data' => [
                'name' => $name,
                'brand' => $first_brand,
                'quantity' => self::clean($product['quantity'] ?? ''),
                'image_url' => self::safeImageUrl($product['image_front_url'] ?? null),
            ],
        ];
    }

    /**
     * "Coca-Cola Original" + "330 ml" becomes "Coca-Cola Original 330 ml".
     */
    public static function buildName(array $product): string
    {
        $name = '';
        foreach (['product_name', 'product_name_en', 'generic_name'] as $field) {
            $candidate = self::clean($product[$field] ?? '');
            if ($candidate !== '') {
                $name = $candidate;
                break;
            }
        }

        if ($name === '') {
            return '';
        }

        $quantity = self::clean($product['quantity'] ?? '');
        if ($quantity !== '') {
            $squash = function ($s) {
                return str_replace(' ', '', mb_strtolower($s));
            };
            if (mb_strpos($squash($name), $squash($quantity)) === false) {
                $name .= ' '.$quantity;
            }
        }

        return mb_substr($name, 0, 150);
    }

    public static function safeImageUrl($url): ?string
    {
        if (! is_string($url) || $url === '' || strlen($url) > 500) {
            return null;
        }

        $parts = parse_url($url);
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['port'])) {
            return null;
        }

        return in_array(strtolower($parts['host'] ?? ''), self::IMAGE_HOSTS, true) ? $url : null;
    }

    /**
     * Copies a picture onto the shop's own server, so it never depends on the outside site later.
     * Returns the saved file name, or null if the picture is missing, too big or not a real image.
     */
    public function downloadImage(?string $url, string $code): ?string
    {
        $url = self::safeImageUrl($url);
        if ($url === null) {
            return null;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->withOptions(['allow_redirects' => false])
                ->get($url);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();
        if ($body === '' || strlen($body) > self::MAX_IMAGE_BYTES) {
            return null;
        }

        $info = @getimagesizefromstring($body);
        if (! $info || $info[0] > self::MAX_IMAGE_SIDE || $info[1] > self::MAX_IMAGE_SIDE) {
            return null;
        }

        $extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        $extension = $extensions[$info[2]] ?? null;
        if ($extension === null) {
            return null;
        }

        $dir = public_path('uploads/'.config('constants.product_img_path'));
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $file_name = 'scan_'.time().'_'.preg_replace('/\D/', '', $code).'.'.$extension;
        if (@file_put_contents($dir.'/'.$file_name, $body) === false) {
            return null;
        }

        return $file_name;
    }

    private static function clean($value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', (string) $value));
    }
}
