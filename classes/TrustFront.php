<?php

require_once __DIR__ . '/TrustConfiguration.php';

/** Presentation data only; configuration remains shared across shops. */
final class MarclandTrustFront
{
    public static function zones($db, $context, string $directory, string $base): array
    {
        $language = (int) $context->language->id;
        $shop = (int) $context->shop->id;
        if ($language <= 0 || $shop <= 0) {
            return [];
        }
        $rows = $db->executeS(
            'SELECT z.*, l.text, CASE WHEN cs.id_cms IS NOT NULL AND cl.id_cms IS NOT NULL THEN c.id_cms ELSE NULL END AS cms_id
            FROM `' . _DB_PREFIX_ . 'marclandtrust_zone` z
            INNER JOIN `' . _DB_PREFIX_ . 'marclandtrust_zone_lang` l ON l.id_zone = z.id_zone AND l.id_lang = ' . $language . '
            LEFT JOIN `' . _DB_PREFIX_ . 'cms` c ON z.link_type = \'cms\' AND c.id_cms = CAST(z.link_target AS UNSIGNED) AND c.active = 1
            LEFT JOIN `' . _DB_PREFIX_ . 'cms_shop` cs ON cs.id_cms = c.id_cms AND cs.id_shop = ' . $shop . '
            LEFT JOIN `' . _DB_PREFIX_ . 'cms_lang` cl ON cl.id_cms = c.id_cms AND cl.id_lang = ' . $language . ' AND cl.id_shop = ' . $shop . '
            WHERE z.active = 1 AND TRIM(l.text) <> \'\'
            ORDER BY z.position, z.id_zone',
            true,
            false
        );
        if (!is_array($rows)) {
            return [];
        }
        $zones = [];
        foreach ($rows as $row) {
            if ((int) $row['active'] !== 1 || !is_string($row['text']) || trim($row['text']) === '') {
                continue;
            }
            $href = null;
            $target = (string) ($row['link_target'] ?? '');
            if ($row['link_type'] === 'anchor' && preg_match('/\A[A-Za-z][A-Za-z0-9_-]{0,127}\z/D', $target)) {
                $href = '#' . $target;
            } elseif ($row['link_type'] === 'cms' && preg_match('/\A[1-9][0-9]{0,9}\z/D', $target)
                && (string) ($row['cms_id'] ?? '') === $target) {
                try {
                    $url = $context->link->getCMSLink((int) $target, null, null, $language, $shop);
                    // Accept only absolute HTTP(S) URLs produced by PrestaShop's link generator.
                    if (is_string($url) && preg_match('~\Ahttps?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL)) {
                        $href = $url;
                    }
                } catch (Throwable $ignored) {
                    // A removed/unavailable CMS destination leaves the message as plain text.
                }
            }
            $zones[] = [
                'text' => $row['text'],
                'href' => $href,
                'icon' => self::icon($row, $directory, $base),
            ];
            if (count($zones) === 3) {
                break;
            }
        }
        return $zones;
    }

    private static function icon(array $zone, string $directory, string $base): ?string
    {
        if ($zone['icon_type'] === 'predefined' && in_array($zone['icon_name'], MarclandTrustConfiguration::ICONS, true)) {
            $path = 'views/img/icons/' . $zone['icon_name'] . '.svg';
            return is_readable($directory . '/' . $path) ? $base . $path : null;
        }
        $path = (string) ($zone['custom_icon_path'] ?? '');
        if ($zone['icon_type'] !== 'custom' || !MarclandTrustConfiguration::validPath($path)
            || is_link($directory . '/uploads') || is_link($directory . '/' . $path)) {
            return null;
        }
        // At most three bounded reads. Reject missing, corrupted or tampered stored assets.
        $svg = @file_get_contents($directory . '/' . $path, false, null, 0, MarclandTrustSvg::MAX_BYTES + 1);
        if ($svg === false) {
            return null;
        }
        try {
            if (MarclandTrustSvg::sanitize($svg) !== $svg) {
                return null;
            }
        } catch (Throwable $ignored) {
            return null;
        }
        return $base . $path;
    }
}
