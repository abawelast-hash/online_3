<?php
// =============================================================
// api/verify-device.php — التحقق من بصمة الجهاز وتسجيلها
// =============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json; charset=utf-8');

// Rate Limiting: 30 طلب/دقيقة لكل IP
if (isRateLimited(30, 60, 'verify')) {
    rateLimitResponse();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data        = json_decode(file_get_contents('php://input'), true);
$token       = trim($data['token']       ?? '');
$fingerprint = trim($data['fingerprint'] ?? '');

if (!$token || !$fingerprint) {
    echo json_encode(['success' => false, 'message' => 'بيانات ناقصة']);
    exit;
}

// جلب الموظف
$stmt = db()->prepare("SELECT id, device_fingerprint FROM employees WHERE unique_token = ? AND is_active = 1");
$stmt->execute([$token]);
$employee = $stmt->fetch();

if (!$employee) {
    echo json_encode(['success' => false, 'message' => 'رابط غير صالح']);
    exit;
}

// لا توجد بصمة محفوظة → نتحقق من وضع الربط
if (empty($employee['device_fingerprint'])) {
    // التحقق إن كان وضع الربط مفعّل من الإدارة
    $bindStmt = db()->prepare("SELECT device_bind_mode FROM employees WHERE id = ?");
    $bindStmt->execute([$employee['id']]);
    $bindMode = (int)($bindStmt->fetchColumn() ?? 0);

    if ($bindMode === 1) {
        // وضع الربط مفعّل يدوياً من الإدارة → ربط الجهاز
        $upd = db()->prepare("UPDATE employees SET device_fingerprint = ?, device_registered_at = NOW(), device_bind_mode = 0 WHERE id = ?");
        $upd->execute([$fingerprint, $employee['id']]);
        echo json_encode(['success' => true, 'first_time' => true, 'auto_bound' => true]);
        exit;
    }

    // وضع الربط غير مفعّل → اسمح بالدخول بدون ربط
    echo json_encode(['success' => true, 'first_time' => true, 'auto_bound' => false]);
    exit;
}

// التحقق من البصمة
if (hash_equals($employee['device_fingerprint'], $fingerprint)) {
    echo json_encode(['success' => true, 'first_time' => false]);
} else {
    // بصمة مختلفة — نسجل حالة تلاعب محتملة لكن نسمح بالدخول
    try {
        $logStmt = db()->prepare("INSERT INTO tampering_cases (employee_id, case_type, description, attendance_date, severity, details_json)
            VALUES (?, 'different_device', 'تسجيل من جهاز مختلف عن الجهاز المربوط', CURDATE(), 'medium', ?)");
        $logStmt->execute([$employee['id'], json_encode([
            'expected' => substr($employee['device_fingerprint'], 0, 12) . '...',
            'actual' => substr($fingerprint, 0, 12) . '...',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'time' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE)]);
    } catch (Exception $e) { /* الجدول قد لا يكون موجوداً */
    }

    // اسمح بالدخول بدون حجب
    echo json_encode(['success' => true, 'first_time' => false, 'device_mismatch' => true]);
}
