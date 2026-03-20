<?php

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../init.php';

use WHMCS\Database\Capsule;

$settings = Capsule::table('tbladdonmodules')
    ->where('module', 'contact_monitor_for_whmcs')
    ->whereIn('setting', ['enabled', 'bearer_token'])
    ->pluck('value', 'setting')
    ->toArray();

$enabled = $settings['enabled'] ?? '';
$storedToken = $settings['bearer_token'] ?? '';

if ($enabled !== 'on') {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$providedToken = '';

if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
    $providedToken = $matches[1];
}

if ($storedToken === '' || !hash_equals($storedToken, $providedToken)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$resource = $_GET['resource'] ?? '';

if ($resource === '') {
    echo json_encode([
        'ok'       => true,
        'service'  => 'contact-monitor-for-whmcs',
        'time_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        'status'   => 'healthy',
    ]);
    exit;
}

if ($resource === 'config') {
    // Detect WHMCS system URL from configuration table
    $sysUrlRow = Capsule::table('tblconfiguration')
        ->where('setting', 'SystemURL')
        ->first();
    $baseUrl = rtrim($sysUrlRow ? $sysUrlRow->value : '', '/');

    // Detect admin directory by scanning WHMCS root for the admin area
    // api.php lives at <whmcs_root>/modules/addons/contact_monitor_for_whmcs/api.php
    $whmcsRoot = dirname(dirname(dirname(__DIR__)));
    $adminDir  = 'admin'; // fallback
    foreach (scandir($whmcsRoot) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (is_dir($whmcsRoot . '/' . $entry)
            && file_exists($whmcsRoot . '/' . $entry . '/clientssummary.php')
        ) {
            $adminDir = $entry;
            break;
        }
    }

    echo json_encode([
        'ok'        => true,
        'base_url'  => $baseUrl,
        'admin_dir' => $adminDir,
    ]);
    exit;
}

$valid_resources = ['clients', 'contacts', 'services', 'tickets'];

if (!in_array($resource, $valid_resources, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}

$limit = max(1, (int)($_GET['limit'] ?? 100));

if ($resource === 'tickets') {
    $limit           = min(200, $limit);
    $after_sent_at   = $_GET['after_sent_at']   ?? '';
    $after_ticket_id = max(0, (int)($_GET['after_ticket_id'] ?? 0));
    $max_msg_chars   = min(12000, max(1, (int)($_GET['max_message_chars'] ?? 4000)));
    $query_params    = [
        'limit'           => $limit,
        'after_sent_at'   => $after_sent_at,
        'after_ticket_id' => $after_ticket_id,
    ];
} else {
    $limit        = min(500, $limit);
    $after_id     = max(0, (int)($_GET['after_id'] ?? 0));
    $query_params = ['limit' => $limit, 'after_id' => $after_id];
}

$query_dir = __DIR__ . '/queries/';
$class_map  = [
    'clients'  => ['ClientsQuery',  'ClientsQuery.php'],
    'contacts' => ['ContactsQuery', 'ContactsQuery.php'],
    'services' => ['ServicesQuery', 'ServicesQuery.php'],
    'tickets'  => ['TicketsQuery',  'TicketsQuery.php'],
];

[$class_name, $class_file] = $class_map[$resource];
require_once $query_dir . $class_file;

try {
    $rows = $class_name::run($query_params);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
    exit;
}

if ($resource === 'tickets') {
    foreach ($rows as &$row) {
        $msg          = $row['message'] ?? '';
        $original_len = mb_strlen($msg, 'UTF-8');
        $truncated    = false;
        if ($original_len > $max_msg_chars) {
            $row['message'] = mb_substr($msg, 0, $max_msg_chars, 'UTF-8');
            $truncated      = true;
        }
        $row['message_truncated']       = $truncated;
        $row['message_length_original'] = $original_len;
    }
    unset($row);
}

$count       = count($rows);
$next_cursor = null;

if ($count >= $limit && $count > 0) {
    $last = $rows[$count - 1];
    if ($resource === 'clients') {
        $next_cursor = ['after_id' => (int)$last['clientid']];
    } elseif ($resource === 'contacts') {
        $next_cursor = ['after_id' => (int)$last['contactid']];
    } elseif ($resource === 'services') {
        $next_cursor = ['after_id' => (int)$last['serviceid']];
    } else {
        $next_cursor = [
            'after_sent_at'   => $last['sent_at'],
            'after_ticket_id' => (int)$last['ticket_id'],
        ];
    }
}

echo json_encode([
    'ok'          => true,
    'resource'    => $resource,
    'count'       => $count,
    'next_cursor' => $next_cursor,
    'data'        => $rows,
]);
