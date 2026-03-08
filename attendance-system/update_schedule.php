<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Update ALL branches: work 20:00–00:00, geofence 4m
$stmt = db()->prepare("UPDATE branches SET 
    work_start_time = '20:00:00',
    work_end_time = '00:00:00',
    check_in_start_time = '19:30:00',
    check_in_end_time = '21:30:00',
    check_out_start_time = '23:00:00',
    check_out_end_time = '01:00:00',
    checkout_show_before = 30,
    geofence_radius = 4
");
$stmt->execute();

$affected = $stmt->rowCount();
echo "Updated $affected branches\n";

// Also update default settings
$defaults = [
    'work_start_time' => '20:00',
    'work_end_time' => '00:00',
    'check_in_start_time' => '19:30',
    'check_in_end_time' => '21:30',
    'check_out_start_time' => '23:00',
    'check_out_end_time' => '01:00',
    'checkout_show_before' => '30'
];

foreach($defaults as $key => $val) {
    $s = db()->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $s->execute([$key, $val]);
}
echo "Default settings updated\n";

// Verify
echo "\n=== VERIFICATION ===\n";
$s = db()->query("SELECT id, name, work_start_time, work_end_time, check_in_start_time, check_in_end_time, check_out_start_time, check_out_end_time, checkout_show_before FROM branches");
while($r = $s->fetch(PDO::FETCH_ASSOC)) {
    echo "Branch #" . $r['id'] . ": " . $r['work_start_time'] . " - " . $r['work_end_time'] . " | CI: " . $r['check_in_start_time'] . "-" . $r['check_in_end_time'] . " | CO: " . $r['check_out_start_time'] . "-" . $r['check_out_end_time'] . " | Before: " . $r['checkout_show_before'] . "min\n";
}
