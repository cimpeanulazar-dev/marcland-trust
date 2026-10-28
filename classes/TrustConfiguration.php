<?php

require_once __DIR__ . '/TrustSvg.php';

/** Shared multistore configuration. One master connection owns every query and lock. */
final class MarclandTrustConfiguration
{
    public const PLACEMENTS = ['home', 'product', 'checkout'];
    public const MAX_METADATA_BYTES = 2097152;
    public const MAX_UPLOAD_BYTES = 2097152;
    public const ICONS = ['truck', 'returns', 'shield', 'lock', 'support', 'verified'];
    private $db;
    private $directory;

    public function __construct($db, string $directory)
    {
        $this->db = $db;
        $this->directory = $directory;
    }

    private function execute(string $sql): void
    {
        if (!$this->db->execute($sql)) {
            throw new RuntimeException('Unable to save configuration. No changes were confirmed; reload before retrying.');
        }
    }

    public function load(): array
    {
        $zones = $this->db->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'marclandtrust_zone` ORDER BY `position`, `id_zone`', true, false);
        $texts = $this->db->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'marclandtrust_zone_lang` ORDER BY `id_zone`, `id_lang`', true, false);
        $placements = $this->db->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'marclandtrust_zone_placement` ORDER BY `id_zone`, `placement`', true, false);
        if (!is_array($zones) || !is_array($texts) || !is_array($placements)) {
            throw new RuntimeException('Unable to load Trust Signals. Check the installed schema and database access.');
        }
        return ['zones' => $zones, 'translations' => $texts, 'placements' => $placements];
    }

    public static function revision(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private static function id($value): int
    {
        if (!is_string($value) || !preg_match('/\A[1-9][0-9]{0,9}\z/D', $value) || (float) $value > 4294967295) {
            throw new InvalidArgumentException('Invalid zone or language identifier.');
        }
        return (int) $value;
    }

    /** Complete validation happens before database writes or publishing uploaded files. */
    public static function validate(array $post, array $languageIds, callable $cmsExists): array
    {
        if (($post['trust_signals_present'] ?? null) !== '1'
            || ($post['trust_payload_complete'] ?? null) !== '1'
            || !is_string($post['trust_revision'] ?? null)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $post['trust_revision'])
            || !is_array($post['trust_signals'] ?? []) || !is_array($post['trust_deleted'] ?? [])) {
            throw new InvalidArgumentException('Incomplete configuration request. Reload the editor and try again.');
        }
        $input = $post['trust_signals'] ?? [];
        if (strlen(json_encode($post, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)) > self::MAX_METADATA_BYTES) {
            throw new InvalidArgumentException('Configuration exceeds the 2 MiB metadata budget. Reduce content or split the configuration.');
        }
        $deleted = [];
        foreach ($post['trust_deleted'] ?? [] as $value) {
            $id = self::id($value);
            if (isset($deleted[$id])) {
                throw new InvalidArgumentException('Duplicate deleted zone identifier.');
            }
            $deleted[$id] = true;
        }
        $knownCms = [];
        $cachedCmsExists = static function ($id) use (&$knownCms, $cmsExists) {
            if (!array_key_exists($id, $knownCms)) {
                $knownCms[$id] = (bool) $cmsExists($id);
            }
            return $knownCms[$id];
        };
        $zones = [];
        $seen = [];
        $positions = [];
        $fields = ['id_zone', 'position', 'active', 'icon_type', 'icon_name', 'custom_icon_path', 'upload_selected', 'text', 'placements'];
        foreach ($input as $index => $row) {
            if (!is_int($index) || $index < 0 || $index > 4294967295 || !is_array($row)
                || array_diff(array_keys($row), $fields) || array_diff($fields, array_keys($row))) {
                throw new InvalidArgumentException('Invalid zone structure.');
            }
            foreach ($fields as $field) {
                if (!in_array($field, ['text', 'placements'], true) && !is_string($row[$field])) {
                    throw new InvalidArgumentException('Invalid zone field.');
                }
            }
            $id = $row['id_zone'] === '' ? null : self::id($row['id_zone']);
            if ($id !== null && (isset($seen[$id]) || isset($deleted[$id]))) {
                throw new InvalidArgumentException('Duplicate or conflicting zone identifier.');
            }
            if ($id !== null) {
                $seen[$id] = true;
            }
            if (!in_array($row['upload_selected'], ['0', '1'], true)
                || ($row['upload_selected'] === '1' && $row['icon_type'] !== 'custom')
                || !in_array($row['active'], ['0', '1'], true)
                || !preg_match('/\A[1-9][0-9]{0,9}\z/D', $row['position'])
                || (float) $row['position'] > 4294967295
                || (int) $row['position'] > count($input) || isset($positions[$row['position']])) {
                throw new InvalidArgumentException('Invalid visibility or zone ordering.');
            }
            $positions[$row['position']] = true;
            if (!in_array($row['icon_type'], ['predefined', 'custom'], true)
                || !in_array($row['icon_name'], self::ICONS, true)) {
                throw new InvalidArgumentException('Choose a supported icon.');
            }
            if ($row['custom_icon_path'] !== '' && !self::validPath($row['custom_icon_path'])) {
                throw new InvalidArgumentException('Invalid custom icon reference.');
            }
            if (!is_array($row['placements'])
                || array_diff(array_keys($row['placements']), self::PLACEMENTS)
                || array_diff(self::PLACEMENTS, array_keys($row['placements']))) {
                throw new InvalidArgumentException('Invalid or incomplete placement identifiers.');
            }
            foreach ($row['placements'] as $placement => $settings) {
                $expected = ['enabled', 'position', 'link_type', 'link_target'];
                if (!is_array($settings) || array_diff(array_keys($settings), $expected)
                    || array_diff($expected, array_keys($settings))) {
                    throw new InvalidArgumentException('Invalid placement structure.');
                }
                foreach ($settings as $value) {
                    if (!is_string($value)) {
                        throw new InvalidArgumentException('Invalid placement value.');
                    }
                }
                if (!in_array($settings['enabled'], ['0', '1'], true)
                    || !preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D', $settings['position'])
                    || (float) $settings['position'] > 4294967295) {
                    throw new InvalidArgumentException('Invalid placement visibility or ordering.');
                }
                self::validateLink($settings['link_type'], $settings['link_target'], $placement, $cachedCmsExists);
            }
            if (!is_array($row['text'])) {
                throw new InvalidArgumentException('Invalid translations.');
            }
            $texts = [];
            foreach ($row['text'] as $lang => $text) {
                if (!in_array((int) $lang, $languageIds, true) || (string) (int) $lang !== (string) $lang
                    || !is_string($text) || !preg_match('//u', $text) || mb_strlen($text, 'UTF-8') > 500
                    || strip_tags($text) !== $text || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $text)) {
                    throw new InvalidArgumentException('Messages must be plain text, at most 500 characters, in installed languages.');
                }
                if ($text !== '') {
                    $texts[(int) $lang] = $text;
                }
            }
            // Require every installed language in the payload to detect truncated POST data.
            if (array_diff($languageIds, array_map('intval', array_keys($row['text'])))) {
                throw new InvalidArgumentException('Some translations are missing. Reload the editor and try again.');
            }
            $row['id_zone'] = $id;
            $row['texts'] = $texts;
            $zones[$index] = $row;
        }
        return ['zones' => $zones, 'deleted' => array_keys($deleted), 'revision' => $post['trust_revision']];
    }

    private static function validateLink(string $type, string $target, string $placement, callable $cmsExists): void
    {
        if (!in_array($type, ['none', 'cms', 'anchor'], true)) {
            throw new InvalidArgumentException('Unsupported link type.');
        }
        if ($type === 'none' && $target !== '') {
            throw new InvalidArgumentException('A signal without a link must have an empty destination.');
        }
        if ($type === 'anchor' && ($placement === 'checkout'
            || !preg_match('/\A[A-Za-z][A-Za-z0-9_-]{0,127}\z/D', $target))) {
            throw new InvalidArgumentException('Use a safe local anchor on the homepage or product page. Checkout anchors are unsupported.');
        }
        if ($type === 'cms' && !$cmsExists(self::id($target))) {
            throw new InvalidArgumentException('The selected CMS page does not exist.');
        }
    }

    public static function validPath(string $path): bool
    {
        return (bool) preg_match('/\Auploads\/[a-f0-9]{32}\.svg\z/D', $path);
    }

    /** Validate PHP upload metadata and sanitize into memory; never trust MIME or extension alone. */
    public static function uploads(array $files, array $zones): array
    {
        if (!$files) {
            foreach ($zones as $zone) {
                if ($zone['upload_selected'] === '1') {
                    throw new InvalidArgumentException('A selected upload is missing. Select the file again and check PHP upload limits.');
                }
            }
            return [];
        }
        foreach (['name', 'tmp_name', 'error', 'size', 'type'] as $key) {
            if (!isset($files[$key]) || !is_array($files[$key])) {
                throw new InvalidArgumentException('Invalid upload structure.');
            }
        }
        $result = [];
        $totalBytes = 0;
        foreach ($files['error'] as $index => $error) {
            if (!array_key_exists($index, $zones) || $zones[$index]['icon_type'] !== 'custom'
                || !is_int($error) || !is_string($files['name'][$index] ?? null)
                || !is_string($files['tmp_name'][$index] ?? null)) {
                throw new InvalidArgumentException('An uploaded icon is not associated with a valid custom zone.');
            }
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($zones[$index]['upload_selected'] !== '1') {
                throw new InvalidArgumentException('Unexpected icon upload. Reload the editor and try again.');
            }
            $tmp = $files['tmp_name'][$index];
            if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)
                || strtolower(pathinfo($files['name'][$index], PATHINFO_EXTENSION)) !== 'svg'
                || filesize($tmp) > MarclandTrustSvg::MAX_BYTES || filesize($tmp) === 0) {
                throw new InvalidArgumentException('SVG upload failed. Use an SVG file no larger than 100 KB.');
            }
            $totalBytes += filesize($tmp);
            if ($totalBytes > self::MAX_UPLOAD_BYTES) {
                throw new InvalidArgumentException('Selected icons exceed the 2 MiB total upload budget.');
            }
            $xml = @file_get_contents($tmp, false, null, 0, MarclandTrustSvg::MAX_BYTES + 1);
            if ($xml === false) {
                throw new RuntimeException('Unable to read the uploaded icon.');
            }
            $result[$index] = MarclandTrustSvg::sanitize($xml);
        }
        foreach ($zones as $index => $zone) {
            if ($zone['upload_selected'] === '1' && !isset($result[$index])) {
                throw new InvalidArgumentException('A selected upload failed or is missing. Select the file again.');
            }
        }
        return $result;
    }

    /** The connection-scoped advisory lock also serializes saves when no zone rows exist. */
    public function save(array $configuration, array $uploads): void
    {
        $lock = 'marclandtrust_' . hash('sha256', _DB_NAME_ . ':' . _DB_PREFIX_);
        $lock = substr($lock, 0, 64);
        if ((string) $this->db->getValue("SELECT GET_LOCK('" . pSQL($lock) . "', 10)", false) !== '1') {
            throw new RuntimeException('Another configuration save is in progress. Try again.');
        }
        $created = [];
        $transaction = false;
        $commitAttempted = false;
        try {
            $this->execute('START TRANSACTION');
            $transaction = true;
            $current = $this->load();
            if (!hash_equals(self::revision($current), $configuration['revision'])) {
                throw new InvalidArgumentException('Configuration changed in another window. Copy your edits, reload and try again.');
            }
            $existing = [];
            foreach ($current['zones'] as $zone) {
                $existing[(int) $zone['id_zone']] = $zone;
            }
            $submitted = array_filter(array_column($configuration['zones'], 'id_zone'), static function ($id) { return $id !== null; });
            $accounted = array_merge($submitted, $configuration['deleted']);
            if (count($accounted) !== count(array_unique($accounted))
                || array_diff($accounted, array_keys($existing)) || array_diff(array_keys($existing), $accounted)) {
                throw new InvalidArgumentException('Unknown or omitted zone identifiers. Reload before saving.');
            }
            $retired = [];
            foreach ($configuration['zones'] as $index => $zone) {
                $id = $zone['id_zone'];
                $oldPath = $id === null ? '' : (string) $existing[$id]['custom_icon_path'];
                if ($zone['custom_icon_path'] !== $oldPath) {
                    throw new InvalidArgumentException('Custom icon references cannot be assigned by the browser.');
                }
                $path = null;
                if ($zone['icon_type'] === 'custom') {
                    if (isset($uploads[$index])) {
                        $path = $this->publish($uploads[$index]);
                        $created[] = $path;
                    } elseif ($oldPath !== '' && self::validPath($oldPath)
                        && is_file($this->directory . '/' . basename($oldPath))
                        && !is_link($this->directory . '/' . basename($oldPath))) {
                        $path = $oldPath;
                    } else {
                        throw new InvalidArgumentException('Select an SVG file for each new custom icon.');
                    }
                }
                if ($oldPath !== '' && $oldPath !== $path) {
                    $retired[] = $oldPath;
                }
                $data = [
                    'position' => (int) $zone['position'], 'active' => (int) $zone['active'],
                    'icon_type' => pSQL($zone['icon_type']),
                    'icon_name' => $zone['icon_type'] === 'predefined' ? pSQL($zone['icon_name']) : null,
                    'custom_icon_path' => $path === null ? null : pSQL($path),
                ];
                if ($id === null) {
                    if (!$this->db->insert('marclandtrust_zone', $data, true)) {
                        throw new RuntimeException('Unable to insert the zone.');
                    }
                    $id = (int) $this->db->Insert_ID();
                    if ($id <= 0) {
                        throw new RuntimeException('Unable to retrieve the new zone identifier.');
                    }
                } elseif (!$this->db->update('marclandtrust_zone', $data, 'id_zone = ' . (int) $id, 0, true)) {
                    throw new RuntimeException('Unable to update the zone.');
                }
                foreach ($zone['placements'] as $placement => $settings) {
                    // Keep unknown future placement rows intact; only current registry entries are replaced.
                    $this->execute("DELETE FROM `" . _DB_PREFIX_ . "marclandtrust_zone_placement` WHERE id_zone = " . (int) $id . " AND placement = '" . pSQL($placement) . "'");
                    if (!$this->db->insert('marclandtrust_zone_placement', [
                        'id_zone' => $id, 'placement' => pSQL($placement), 'enabled' => (int) $settings['enabled'],
                        'position' => (int) $settings['position'], 'link_type' => pSQL($settings['link_type']),
                        'link_target' => $settings['link_type'] === 'none' ? null : pSQL($settings['link_target']),
                    ], true)) {
                        throw new RuntimeException('Unable to save placement configuration.');
                    }
                }
                $this->execute('DELETE FROM `' . _DB_PREFIX_ . 'marclandtrust_zone_lang` WHERE id_zone = ' . (int) $id);
                foreach ($zone['texts'] as $lang => $text) {
                    // HTML escaping is for rendering. SQL escaping preserves quotes and Unicode.
                    if (!$this->db->insert('marclandtrust_zone_lang', [
                        'id_zone' => $id, 'id_lang' => (int) $lang, 'text' => pSQL($text, true),
                    ])) {
                        throw new RuntimeException('Unable to save translations.');
                    }
                }
            }
            foreach ($configuration['deleted'] as $id) {
                $retired[] = (string) $existing[$id]['custom_icon_path'];
                $this->execute('DELETE FROM `' . _DB_PREFIX_ . 'marclandtrust_zone_placement` WHERE id_zone = ' . (int) $id);
                $this->execute('DELETE FROM `' . _DB_PREFIX_ . 'marclandtrust_zone_lang` WHERE id_zone = ' . (int) $id);
                $this->execute('DELETE FROM `' . _DB_PREFIX_ . 'marclandtrust_zone` WHERE id_zone = ' . (int) $id);
            }
            $commitAttempted = true;
            $this->execute('COMMIT');
            $transaction = false;
            // Cleanup remains under the advisory lock and only touches unreferenced generated files.
            foreach (array_unique($retired) as $path) {
                try {
                    if (self::validPath($path)) {
                        $references = $this->db->getValue("SELECT COUNT(*) FROM `" . _DB_PREFIX_ . "marclandtrust_zone` WHERE custom_icon_path = '" . pSQL($path) . "'", false);
                        if ($references !== false && (int) $references === 0) {
                            @unlink($this->directory . '/' . basename($path));
                        }
                    }
                } catch (Throwable $ignored) {
                    // A retained orphan is safer than reporting failure after a successful COMMIT.
                }
            }
        } catch (Throwable $exception) {
            $rolledBack = !$transaction;
            if ($transaction) {
                try {
                    $this->execute('ROLLBACK');
                    $rolledBack = true;
                } catch (Throwable $ignored) { /* Preserve files if transaction outcome is unknown. */ }
            }
            // A lost COMMIT response is ambiguous: retain files rather than break committed references.
            if (!$commitAttempted && $rolledBack) {
                foreach ($created as $path) {
                    @unlink($this->directory . '/' . basename($path));
                }
            }
            throw $exception;
        } finally {
            try { $this->db->getValue("SELECT RELEASE_LOCK('" . pSQL($lock) . "')", false); } catch (Throwable $ignored) { /* Connection-scoped lock expires on disconnect. */ }
        }
    }

    private function publish(string $svg): string
    {
        if (!is_dir($this->directory) || is_link($this->directory) || !is_writable($this->directory)) {
            throw new RuntimeException('The module uploads directory must exist and be writable.');
        }
        $path = 'uploads/' . bin2hex(random_bytes(16)) . '.svg';
        $file = $this->directory . '/' . basename($path);
        $handle = @fopen($file, 'x');
        if ($handle === false) {
            throw new RuntimeException('Unable to create the sanitized icon.');
        }
        try {
            if (@fwrite($handle, $svg) !== strlen($svg) || !@fflush($handle)) {
                throw new RuntimeException('Unable to store the sanitized icon.');
            }
        } catch (Throwable $exception) {
            fclose($handle);
            @unlink($file);
            throw $exception;
        }
        fclose($handle);
        @chmod($file, 0644);
        return $path;
    }
}
