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
    public function install(): bool
    {
        return parent::install() && $this->registerHook('displayProductAdditionalInfo');
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
        $signals= Tools::getValue('trust_signals', []);
        if (!is_array($signals)) {
            return 'Trust signals should be an array';
        }
        foreach($signals as $signal) {
            if (!is_string($signal)) {
                return 'Invalid signal type';
            }

            $signal = trim($signal);
            $validatedSignals[] = $signal;
        }

        return;
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