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
    /** Maximum enforced by the future validated configuration save, not by IDs. */
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
        //Check if Submit is false
        if ($submit) {
            return $this->handleConfigurationSubmit();
        }

        return $this->renderConfigurationForm();

    }


    public function renderConfigurationForm()
    {
        $items = [];

        $this->context->smarty->assign([
            'trust_items' => $items,
        ]);

        return $this->fetch(
            'module:marclandtrust/views/templates/admin/config.tpl'
        );
    }

    private function handleConfigurationSubmit(): string
    {
        $signals = Tools::getValue('trust_signals', []);

        if (!is_array($signals)) {
            return $this->displayError('Invalid form data.')
                . $this->renderConfigurationForm();
        }

        foreach ($signals as $index => $text) {
            if (!is_string($text)) {
                continue;
            }

            $text = trim($text);

            if ($text === '') {
                continue;
            }

            PrestaShopLogger::addLog(
                sprintf('Trust signal [%s]: %s', $index, $text),
                1
            );
        }

        return $this->displayConfirmation('Form data received.')
            . $this->renderConfigurationForm();
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