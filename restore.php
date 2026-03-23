<?php
// =============================================================
// restore.php - استعادة البيانات من النسخة الاحتياطية
// =============================================================
// تشغيله مرة واحدة فقط لاستعادة الموظفين والفروع والإعدادات
// =============================================================

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAdminLogin();

header('Content-Type: text/html; charset=utf-8');
echo "<html><head><meta charset='utf-8'><title>استعادة البيانات</title>";
echo "<style>body{font-family:Arial;direction:rtl;padding:20px;background:#0F172A;color:#E2E8F0}";
echo "h2{color:#D4A841} ul{list-style:none;padding:0} li{margin:6px 0;padding:8px;background:#1E293B;border-radius:6px}</style></head><body>";
echo "<h2>🔄 استعادة البيانات</h2>";

$pdo = db();
$log = [];
$errors = [];

try {
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // =================== استعادة الفروع ===================
    $pdo->exec("TRUNCATE TABLE branches");
    $stmt = $pdo->prepare("INSERT INTO branches 
        (id, name, latitude, longitude, geofence_radius, 
         work_start_time, work_end_time, check_in_start_time, check_in_end_time,
         check_out_start_time, check_out_end_time, checkout_show_before,
         allow_overtime, overtime_start_after, overtime_min_duration, is_active, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $branches = [
        [1, 'صرح الاوروبي', 24.57231000, 46.60256100, 25, '08:00:00', '16:00:00', '07:00:00', '10:00:00', '15:00:00', '20:00:00', 30, 1, 60, 30, 1, '2026-03-15 09:36:11'],
        [2, 'صرح الرئيسي', 24.57236300, 46.60278800, 25, '08:00:00', '16:00:00', '07:00:00', '10:00:00', '15:00:00', '20:00:00', 30, 1, 60, 30, 1, '2026-03-15 09:36:11'],
        [3, 'فضاء 1', 24.57107600, 46.61104800, 25, '08:00:00', '16:00:00', '07:00:00', '10:00:00', '15:00:00', '20:00:00', 30, 1, 60, 30, 1, '2026-03-15 09:36:11'],
        [4, 'فضاء 2', 24.56932700, 46.61478200, 25, '08:00:00', '16:00:00', '07:00:00', '10:00:00', '15:00:00', '20:00:00', 30, 1, 60, 30, 1, '2026-03-15 09:36:11'],
        [5, 'صرح الامريكي', 24.57246600, 46.60298500, 25, '08:00:00', '16:00:00', '07:00:00', '10:00:00', '15:00:00', '20:00:00', 30, 1, 60, 30, 1, '2026-03-15 09:36:11'],
    ];
    
    foreach ($branches as $b) {
        $stmt->execute($b);
    }
    $log[] = "✅ تم استعادة " . count($branches) . " فروع";

    // =================== استعادة الموظفين ===================
    $pdo->exec("TRUNCATE TABLE employees");
    $stmt = $pdo->prepare("INSERT INTO employees 
        (id, name, job_title, pin, pin_changed_at, phone, unique_token, branch_id,
         device_fingerprint, device_registered_at, device_bind_mode, security_level,
         is_active, deleted_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $employees = [
        [1, 'إسلام', 'موظف', '1001', NULL, '+966549820672', '05b430acca24462c4cdba1ee5ca751e83a8299e1a961d6f87399d0ef59f0f91a', 1, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [2, 'حسني', 'موظف', '1002', NULL, '+966537491699', 'f96637b50250aff723c9c0204a0a195614b27b8d68dbeadc253e3676ff270760', 1, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [3, 'بخاري', 'موظف', '1003', NULL, '+923095734018', 'bf660e4393842c17e378961ed452447f8f322edc0b69636f948831f0c685724b', 1, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [4, 'أبو سليمان', 'موظف', '1004', NULL, '+966500651865', '4474bd60b9eee1246260b7e4bd6d2384cdf9739d0f77cafa7c8ab2512c2206ae', 1, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [5, 'صابر', 'موظف', '1005', NULL, '+966570899595', 'c7340494f97b6977f2872c99a849b24db3b3bf3be2d010e77fb86b6396ac7030', 1, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [6, 'زاهر', 'موظف', '1006', NULL, '+966546481759', '587abaae51b124e298750a948ceaead5176ebb5f370afd7dd53ef33466478a30', 2, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [7, 'أيمن', 'موظف', '1007', NULL, '+966555090870', 'f088d40ca858e2462e0552f9df89b817213a39beed881f3db040d68f0b902f78', 2, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [8, 'أمجد', 'موظف', '1008', NULL, '+966555106370', 'b18273dd41058f0b7a8d0714f3c78538464578ef98424f869886ef6066ed331b', 2, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [9, 'نجيب', 'موظف', '1009', NULL, '+923475914157', '3cd22eb91d7f0fb85b6b660ca1248ee6755983925e3556b9da508b75835ae69d', 2, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [10, 'محمد جلال', 'موظف', '1010', NULL, '+966573603727', '7bdff2eae23454a9756dfb617337a057ef2d28a502f565738764ce3a0427a191', 3, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [11, 'محمد بلال', 'موظف', '1011', NULL, '+966503863694', '414ac893a84ecaa251a4a45fa3157473b0ce3bd8add426b59830832e83757343', 3, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [12, 'رمضان عباس علي', 'موظف', '1012', NULL, '+966594119151', '015011a405ebfb4fc60ef293ef67b428ce35bdd5d0b93560a749c78db5bcc5ed', 3, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [13, 'محمد أفريدي', 'موظف', '1013', NULL, '+966565722089', 'b55c0b670b6cde9ece94ca94d34670c517c2b68eaae177c7ebefaf31fded86ce', 3, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [14, 'سلفادور ديلا', 'موظف', '1014', NULL, '+966541756875', 'c141d86c2aa7f5b6baaece5bccc2b4ed2efbdc488dd15175b2614254a3606ff3', 3, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [15, 'محمد خان', 'موظف', '1015', NULL, '+966594163035', 'f56e45fed0ae25fdf20cbe04dca32ecdb40f146cf02730e22d273683d6f5974c', 3, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [16, 'أندريس بورتس', 'موظف', '1016', NULL, '+966590087140', 'bc85e46b30e16d67338dfdfc10f8340de4d1336c7a2b74f0ad87a602a80a03fb', 3, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [17, 'حسن (آصف)', 'موظف', '1017', NULL, '+966582670736', 'e2ee6401d77ac26f09d4ef37c5a537237a7b4c0782277d3d90f064e98c685d2e', 3, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [18, 'رمضان أويس علي', 'موظف', '1018', NULL, '+966531096640', '90cc31e73fee9bc1b102b5f872814ac67445e5c07f82886b03b8483f8d85a80e', 3, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [19, 'ساكدا بندولا', 'موظف', '1019', NULL, '+966572746930', 'ae4d8c02093b567385ca09a650ba4a124022f8977ef4a074d036afd159cd5fc6', 3, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [20, 'شحاتة', 'موظف', '1020', NULL, '+966545677065', '16b190476fb0c75a6fbbfdc92dd496999c37e211909a37fd3a7d8b8646a79e54', 3, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [21, 'منذر محمد', 'موظف', '1021', NULL, '+966556593723', '44f2323dd6c7bc417ee80d2333120ff67c1cc0165b29500571517856d58c6047', 3, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [22, 'مصطفى عوض سعد', 'موظف', '1022', NULL, '+966555106370', '14c9b711c06ffe197ce3dc60b117d22c589d90731f00ef5c574791bd8f56247e', 3, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [23, 'عنايات', 'موظف', '1023', NULL, '+966582329361', '91102659106d60a8e353560b775fd7bb9f79f0d714e2de1c2b8fd27f590f4ed0', 3, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [24, 'محمد خميس', 'موظف', '1024', NULL, '+966153254390', '8572587c08c941fc6e2646004b5f4a91b809f0d2ec231f67bc6723a840561ff9', 3, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [25, 'عبد الهادي يونس', 'موظف', '1025', NULL, '+966159626196', '3496da4e570c48a5dadd6063a2c22f33ea434824a95895874d4b42191ee27412', 3, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [26, 'عبدالله اليمني', 'موظف', '1026', NULL, '+966536765655', 'e0e08a2f385825f0a871af8d15574150d08b17c262e1faaca21bfa943a5cfc36', 4, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [27, 'أفضل', 'موظف', '1027', NULL, '+966599258117', '7ca87c8d64b065d3e19082be03a035cf0a3ef21514e181e57fbaa084911c863f', 4, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [28, 'حبيب', 'موظف', '1028', NULL, '+966573263203', 'c5b7e8c5b40d6e6c05936579cf8a68956d54eaad98d415de1d3502119b61f9fc', 4, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [29, 'إمتي', 'موظف', '1029', NULL, '+966595806604', 'bf7a88daee0835a1dd3d26cb724e461f60c52cd9b67530ec8adf45162ef51737', 4, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [30, 'عرنوس', 'موظف', '1030', NULL, '+966500089178', '4831c0c496604291fc3cea7b55984ba1dc218aa681fc8a4ee8da69ba843a5ed8', 4, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [31, 'عرفان', 'موظف', '1031', NULL, '+966597255093', 'b72e6f8f5b1d126a69ae99d42cbb966e224dbbe77fcd74fa9123f23f73fd5190', 4, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [32, 'وسيم', 'موظف', '1032', NULL, '+966531806242', 'a5f40a068f75277506931477310fd6d875066ac5133a9bb62f6561670efdf007', 4, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [33, 'جهاد', 'موظف', '1033', NULL, '+966508512355', 'cac3719d3e3e9a78e89a3e476656df63825449371ab58e6183fe65f7c7142b54', 4, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [34, 'ابانوب', 'موظف', '1034', NULL, '+966536781886', 'e42f4fc21bcb34e43675aff5e035c3c4fe73b1f25cb5f3152b06f68ce57299a2', 4, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [35, 'قتيبة', 'موظف', '1035', NULL, '+966597024453', 'ed11dadf5520b64f37a6e02f69316737d16ebeacf40d0b12a972b084a4c196e9', 4, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [36, 'وداعة الله', 'موظف', '1036', NULL, '+966571761401', '5855429a9089bd0ca29a8bee151fbda0052e83079858b2e4d1a474e03740ff50', 5, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [37, 'وقاص', 'موظف', '1037', NULL, '+966598997295', 'e69ad4973bb6c8a599f8363778256639b1d7fb0244b3c454bd9462b4d1a9005e', 5, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [38, 'شعبان', 'موظف', '1038', NULL, '+966595153544', '1fc273b443b5f0ddf864f8fb416d9f924b7f61c579f907fbe277ca1c9ea1d7af', 5, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [39, 'مصعب', 'موظف', '1039', NULL, '+966555792273', 'a3151fa05c99a29167b117896b9e05e56c9cc19fa1e239181e58af332383ba08', 5, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
        [40, 'بلال', 'موظف', '1040', NULL, '+966594154009', '0e8fbbe9da89f4a8245c68e7115edf5a385fcc4e5155a95317af1787dd45f2b1', 5, NULL, NULL, 0, 2, 1, NULL, '2026-03-15 09:36:11'],
    ];
    
    foreach ($employees as $e) {
        // تحويل NULL strings إلى null فعلي
        $row = array_map(fn($v) => ($v === 'NULL' || $v === null) ? null : $v, $e);
        $stmt->execute($row);
    }
    $log[] = "✅ تم استعادة " . count($employees) . " موظف";

    // =================== استعادة الإعدادات ===================
    $pdo->exec("TRUNCATE TABLE settings");
    $stmt = $pdo->prepare("INSERT INTO settings (id, setting_key, setting_value, description) VALUES (?, ?, ?, ?)");
    
    $settings = [
        [1, 'work_latitude', '24.572307', 'خط عرض موقع العمل'],
        [2, 'work_longitude', '46.602552', 'خط طول موقع العمل'],
        [3, 'geofence_radius', '25', 'نصف قطر الجيوفينس بالمتر'],
        [4, 'work_start_time', '08:00', 'بداية الدوام الرسمي'],
        [5, 'work_end_time', '16:00', 'نهاية الدوام الرسمي'],
        [6, 'check_in_start_time', '07:00', 'بداية وقت تسجيل الدخول'],
        [7, 'check_in_end_time', '10:00', 'نهاية وقت تسجيل الدخول'],
        [8, 'check_out_start_time', '15:00', 'بداية وقت تسجيل الانصراف'],
        [9, 'check_out_end_time', '20:00', 'نهاية وقت تسجيل الانصراف'],
        [10, 'checkout_show_before', '30', 'دقائق قبل إظهار زر الانصراف'],
        [11, 'allow_overtime', '1', 'السماح بالدوام الإضافي'],
        [12, 'overtime_start_after', '60', 'دقائق بعد نهاية الدوام لبدء الإضافي'],
        [13, 'overtime_min_duration', '30', 'الحد الأدنى للدوام الإضافي بالدقائق'],
        [14, 'site_name', 'نظام الحضور والانصراف', 'اسم النظام'],
        [15, 'company_name', '', 'اسم الشركة'],
        [16, 'timezone', 'Asia/Riyadh', 'المنطقة الزمنية'],
    ];
    
    foreach ($settings as $s) {
        $stmt->execute($s);
    }
    $log[] = "✅ تم استعادة " . count($settings) . " إعداد";

    // =================== استعادة المديرين ===================
    $pdo->exec("TRUNCATE TABLE admins");
    $stmt = $pdo->prepare("INSERT INTO admins (id, username, password_hash, full_name, last_login, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    
    $admins = [
        [1, 'admin', '$2y$12$vrJul5Y7eKG3gVdjQG7O7.vCRRC7AZaXsbAXOYMedNf0BCwNrMEr.', 'مدير النظام', '2026-03-15 20:22:05', '2026-03-15 09:36:11'],
    ];
    
    foreach ($admins as $a) {
        $row = array_map(fn($v) => ($v === 'NULL' || $v === null) ? null : $v, $a);
        $stmt->execute($row);
    }
    $log[] = "✅ تم استعادة " . count($admins) . " مدير";

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // إنشاء ملف قفل
    file_put_contents(__DIR__ . '/install.lock', date('Y-m-d H:i:s') . " - restored\n");

} catch (Exception $e) {
    $errors[] = "❌ خطأ: " . htmlspecialchars($e->getMessage());
}

echo "<ul>";
foreach ($log as $l) {
    $color = str_contains($l, '✅') ? '#10B981' : '#F59E0B';
    echo "<li style='color:$color'>$l</li>";
}
foreach ($errors as $e) {
    echo "<li style='color:#EF4444'>$e</li>";
}
echo "</ul>";

if (empty($errors)) {
    echo "<h3 style='color:#10B981'>✅ تمت الاستعادة بنجاح!</h3>";
    echo "<p><a href='admin/dashboard.php' style='color:#3B82F6'>الذهاب للوحة التحكم →</a></p>";
} else {
    echo "<h3 style='color:#EF4444'>⚠️ انتهى مع أخطاء</h3>";
}

echo "</body></html>";
