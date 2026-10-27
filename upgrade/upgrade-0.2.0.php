<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/** Add frontend media registration without touching schema, configuration or uploaded data. */
function upgrade_module_0_2_0($module)
{
    return $module->registerHook('actionFrontControllerSetMedia');
}
