<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_0_3_0($module)
{
    $success = $module->installSchema()
        && $module->registerHook('displayProductAdditionalInfo')
        && $module->registerHook('actionFrontControllerSetMedia')
        && $module->registerHook('displayHome')
        && $module->registerHook('displayCheckoutSummaryTop');
    if (!$success) {
        return false;
    }
    // Git-pulled templates must replace production Smarty compilations too.
    Tools::clearSmartyCache();
    Media::clearCache();
    return true;
}
