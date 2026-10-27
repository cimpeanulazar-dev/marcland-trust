<?php

if(!defined('_PS_VERSION_') ) {
    exit;
}

class MarclandTrust extends Module
{
    public function __construct()
    {
        $this->name = 'marclandtrust';
        $this->tab = 'front_office_features';
        $this->version = '0.1.0';
        $this->author = 'MARCLAND';

        parent::__construct();

        $this->displayName = 'MARCLAND Trust Signals';
        $this->description = 'Configurable product trust information';
    }
    private $submittedZones = null;
    private $submittedRevision = null;
    private $submittedDeleted = [];

    /** Maximum enforced by the validated configuration save, not by IDs. */
    public const MAX_ZONES = 3;

    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        try {
            if (!$this->installSchema()
                || !$this->registerHook('displayProductAdditionalInfo')) {
                throw new RuntimeException('Schema creation or hook registration failed.');
            }
        } catch (Throwable $exception) {
            $this->_errors[] = $this->l('Trust Signals installation failed. Any existing zone data has been preserved.');
            // DDL implicitly commits: never drop tables as an installation rollback.
            try {
                if (!parent::uninstall()) {
                    $this->_errors[] = $this->l('Module registration cleanup failed. Retry installation after checking module status.');
                }
            } catch (Throwable $cleanupException) {
                $this->_errors[] = $this->l('Module registration cleanup failed. Check module status before retrying.');
            }

            return false;
        }

        return true;
    }

    /**
     * Additive and repeatable; never alters or replaces an existing table.
     * Shared across all shops in this PrestaShop installation.
     */
    public function installSchema(): bool
    {
        $queries = [
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'marclandtrust_zone` (
                `id_zone` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `position` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `active` TINYINT UNSIGNED NOT NULL DEFAULT 1,
                `icon_type` VARCHAR(16) NOT NULL DEFAULT \'predefined\',
                `icon_name` VARCHAR(128) DEFAULT NULL,
                `custom_icon_path` VARCHAR(255) DEFAULT NULL,
                `link_type` VARCHAR(16) NOT NULL DEFAULT \'none\',
                `link_target` VARCHAR(2048) DEFAULT NULL,
                PRIMARY KEY (`id_zone`),
                KEY `active_position` (`active`, `position`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'marclandtrust_zone_lang` (
                `id_zone` INT UNSIGNED NOT NULL,
                `id_lang` INT UNSIGNED NOT NULL,
                `text` TEXT NOT NULL,
                PRIMARY KEY (`id_zone`, `id_lang`),
                KEY `id_lang` (`id_lang`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        ];

        try {
            foreach ($queries as $query) {
                if (!Db::getInstance()->execute($query)) {
                    $this->_errors[] = $this->l('Unable to create the Trust Signals database tables. Check database permissions and server logs, then retry.');

                    return false;
                }
            }
        } catch (Throwable $exception) {
            $this->_errors[] = $this->l('Unable to create the Trust Signals database tables. Check database permissions and server logs, then retry.');

            return false;
        }

        return true;
    }

    /** Unregister the module while deliberately retaining merchant data. */
    public function uninstall(): bool
    {
        return parent::uninstall();
    }


    /**
     * Handles the module configuration page.
     *
     * @return string Rendered configuration HTML.
     */
    public function getContent(): string
    {
        $submit = Tools::isSubmit('submitMarclandTrust');

        // Load JavaScript for the module configuration page.
        $this->context->controller->addJS(
            $this->_path . 'views/js/admin-config.js'
        );
        $this->context->controller->addCSS($this->_path . 'views/css/admin-config.css');
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            return $this->displayError($this->l('The submission exceeded the server upload limit. Reduce the upload size and try again.'))
                . $this->renderConfigurationForm();
        }
        if ($submit) {
            return $this->handleConfigurationSubmit();
        }

        return (Tools::getValue('trust_saved') === '1' ? $this->displayConfirmation($this->l('Trust Signals configuration saved.')) : '')
            . $this->renderConfigurationForm();
    }


    public function renderConfigurationForm()
    {
        $languages = Language::getLanguages(false);
        try {
            $saved = $this->configurationStore()->load();
        } catch (Throwable $exception) {
            return $this->displayError($this->l('Unable to load zones. Check that the module database schema is installed.'));
        }
        $zones = $saved['zones'];
        $translations = $saved['translations'];
        $revision = MarclandTrustConfiguration::revision($saved);
        $texts = [];
        foreach ($translations as $row) {
            $texts[(int) $row['id_zone']][(int) $row['id_lang']] = $row['text'];
        }
        foreach ($zones as &$zone) {
            $zone['id_zone'] = (int) $zone['id_zone'];
            $zone['active'] = (bool) $zone['active'];
            $zone['texts'] = (object) ($texts[$zone['id_zone']] ?? []);
        }
        unset($zone);
        if ($this->submittedZones !== null) {
            $zones = $this->submittedZones;
            $revision = $this->submittedRevision ?? $revision;
        }
        $cmsPages = [];
        foreach ($languages as $language) {
            $pages = CMS::getCMSPages((int) $language['id_lang'], null, false, (int) $this->context->shop->id);
            if ($pages === false) {
                return $this->displayError($this->l('Unable to load CMS pages. Check database access before editing zones.'));
            }
            foreach ($pages as $page) {
                $id = (int) $page['id_cms'];
                $cmsPages[$id]['id'] = $id;
                $cmsPages[$id]['titles'][(int) $language['id_lang']] = $page['meta_title'];
            }
        }
        $this->context->smarty->assign([
            'trust_form_action' => $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name]),
            'trust_token' => Tools::getAdminTokenLite('AdminModules'),
            'trust_revision' => $revision,
            'trust_editor_data' => json_encode([
                'languages' => $languages,
                'language' => (int) $this->context->language->id,
                'zones' => $zones,
                'deletedIds' => $this->submittedDeleted,
                'customBase' => $this->_path,
                'cmsPages' => array_values($cmsPages),
                'maxZones' => self::MAX_ZONES,
                'iconBase' => $this->_path . 'views/img/icons/',
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
        ]);

        return $this->fetch(
            'module:marclandtrust/views/templates/admin/config.tpl'
        );
    }

    private function configurationStore()
    {
        require_once __DIR__ . '/classes/TrustConfiguration.php';
        return new MarclandTrustConfiguration(Db::getInstance(true), __DIR__ . '/uploads');
    }

    private function handleConfigurationSubmit(): string
    {
        $token = $_POST['trust_token'] ?? null;
        if (!$this->context->employee || !$this->context->employee->isLoggedBack()
            || !$this->getPermission('configure') || !is_string($token)
            || !hash_equals(Tools::getAdminTokenLite('AdminModules'), $token)) {
            return $this->displayError($this->l('Invalid admin session or security token. Reload the page and try again.'));
        }
        try {
            $store = $this->configurationStore();
            $languages = array_map('intval', array_column(Language::getLanguages(false), 'id_lang'));
            $configuration = MarclandTrustConfiguration::validate($_POST, $languages, static function ($id) {
                return (bool) Validate::isLoadedObject(new CMS($id));
            });
            if (array_diff(array_keys($_FILES), ['trust_icons'])) {
                throw new InvalidArgumentException('Unexpected uploaded file fields.');
            }
            $uploads = MarclandTrustConfiguration::uploads($_FILES['trust_icons'] ?? [], $configuration['zones']);
            $store->save($configuration, $uploads);
        } catch (Throwable $exception) {
            $this->preserveSubmission();
            // Validation messages are authored by this module; DB/runtime errors stay private.
            $message = $exception instanceof InvalidArgumentException ? $exception->getMessage()
                : $this->l('Configuration could not be saved. Check database access, PHP DOM support and upload-directory permissions. Reload before retrying if a connection failed.');
            return $this->displayError(Tools::safeOutput($message)) . $this->renderConfigurationForm();
        }
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true, [], [
            'configure' => $this->name, 'trust_saved' => '1',
        ]));
        return '';
    }

    /** Bound and normalize only for safe redisplay; never use this data for database writes. */
    private function preserveSubmission(): void
    {
        $rows = $_POST['trust_signals'] ?? [];
        if (!is_array($rows)) {
            return;
        }
        $this->submittedZones = [];
        foreach (array_slice($rows, 0, 20) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $zone = [];
            foreach (['id_zone', 'position', 'icon_type', 'icon_name', 'custom_icon_path', 'link_type', 'link_target'] as $key) {
                $zone[$key] = is_string($row[$key] ?? null) ? substr($row[$key], 0, 2048) : '';
            }
            $zone['id_zone'] = ctype_digit($zone['id_zone']) && (int) $zone['id_zone'] > 0 ? (int) $zone['id_zone'] : null;
            $zone['active'] = ($row['active'] ?? null) === '1';
            $zone['texts'] = [];
            foreach (is_array($row['text'] ?? null) ? $row['text'] : [] as $lang => $text) {
                if (is_string($text) && ctype_digit((string) $lang)) {
                    $zone['texts'][(int) $lang] = substr($text, 0, 10000);
                }
            }
            $zone['texts'] = (object) $zone['texts'];
            $this->submittedZones[] = $zone;
        }
        if (is_string($_POST['trust_revision'] ?? null) && preg_match('/\A[a-f0-9]{64}\z/D', $_POST['trust_revision'])) {
            $this->submittedRevision = $_POST['trust_revision'];
        }
        foreach (is_array($_POST['trust_deleted'] ?? null) ? $_POST['trust_deleted'] : [] as $id) {
            if (is_string($id) && ctype_digit($id)) {
                $this->submittedDeleted[] = $id;
            }
        }
    }

    public function hookDisplayProductAdditionalInfo($params)
    {
        $message = Configuration::get('MARCLAND_TRUST_MESSAGE');
        $this->context->smarty->assign(array(
            // Mai jos nu am stiut daca e bine null sau empty sau isset... m-am gandit ca set este pentru ca o declar mai sus so.... ramane intre null si empty
            'trust_message' => is_string($message) && trim($message) !== ''?$message:'Livrare rapida si sigura',
        ));
        return $this->fetch('module:marclandtrust/views/templates/hook/trust-signals.tpl');
    }
}