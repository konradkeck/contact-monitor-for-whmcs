<?php

if (!defined('WHMCS')) {
    die('Access denied');
}

function contact_monitor_for_whmcs_config()
{
    return [
        'name' => 'Contact Monitor for WHMCS',
        'description' => 'API endpoint for Contact Monitor synchronization',
        'version' => '1.0.0',
        'author' => 'Contact Monitor',
        'fields' => [
            'enabled' => [
                'FriendlyName' => 'Enabled',
                'Type' => 'yesno',
                'Description' => 'Enable the API endpoint',
                'Default' => 'no',
            ],
            'bearer_token' => [
                'FriendlyName' => 'Bearer Token',
                'Type' => 'text',
                'Size' => 64,
                'Description' => 'Secret token for API authentication',
                'Default' => '',
            ],
        ],
    ];
}

function contact_monitor_for_whmcs_activate()
{
    return ['status' => 'success', 'description' => 'Contact Monitor for WHMCS activated'];
}

function contact_monitor_for_whmcs_deactivate()
{
    return ['status' => 'success', 'description' => 'Contact Monitor for WHMCS deactivated'];
}

function contact_monitor_for_whmcs_output($vars)
{
    echo '<p>Contact Monitor for WHMCS is ' . ($vars['enabled'] === 'on' ? 'enabled' : 'disabled') . '.</p>';
    echo '<p>API endpoint: <code>' . htmlspecialchars($_SERVER['HTTP_HOST']) . '/modules/addons/contact_monitor_for_whmcs/api.php</code></p>';
}
