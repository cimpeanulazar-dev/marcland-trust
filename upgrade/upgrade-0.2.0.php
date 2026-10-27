<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/** Ensure additive schema setup and both hooks; never replace existing merchant data. */
function upgrade_module_0_2_0($module)
{
    return $module->installSchema()
        && $module->registerHook('displayProductAdditionalInfo')
        && $module->registerHook('actionFrontControllerSetMedia');
}
