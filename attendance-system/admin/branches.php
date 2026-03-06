<?php
// =============================================================
// admin/branches.php - إدارة الفروع (CRUD + مواعيد + موقع)
// =============================================================

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminLogin();

$pageTitle  = 'إدارة الفروع';
$activePage = 'branches';
$message    = '';
$msgType    = '';

// =================== إجراءات POST ===================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'طلب غير صالح';
        $msgType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        // --- إضافة فرع ---
        if ($action === 'add') {
            $name     = sanitize($_POST['name'] ?? '');
            $lat      = (float)($_POST['latitude'] ?? 0);
            $lon      = (float)($_POST['longitude'] ?? 0);
            $radius   = (int)($_POST['geofence_radius'] ?? 25);
            $wsTime   = sanitize($_POST['work_start_time'] ?? '08:00');
            $weTime   = sanitize($_POST['work_end_time'] ?? '16:00');
            $ciStart  = sanitize($_POST['check_in_start_time'] ?? '07:00');
            $ciEnd    = sanitize($_POST['check_in_end_time'] ?? '10:00');
            $coStart  = sanitize($_POST['check_out_start_time'] ?? '15:00');
            $coEnd    = sanitize($_POST['check_out_end_time'] ?? '20:00');
            $coShow   = (int)($_POST['checkout_show_before'] ?? 30);
            $allowOT  = (int)($_POST['allow_overtime'] ?? 1);
            $otAfter  = (int)($_POST['overtime_start_after'] ?? 60);
            $otMin    = (int)($_POST['overtime_min_duration'] ?? 30);

            if ($name && $lat != 0 && $lon != 0) {
                try {
                    $stmt = db()->prepare("INSERT INTO branches (name, latitude, longitude, geofence_radius,
                        work_start_time, work_end_time, check_in_start_time, check_in_end_time,
                        check_out_start_time, check_out_end_time, checkout_show_before,
                        allow_overtime, overtime_start_after, overtime_min_duration)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                    $stmt->execute([
                        $name,
                        $lat,
                        $lon,
                        $radius,
                        $wsTime,
                        $weTime,
                        $ciStart,
                        $ciEnd,
                        $coStart,
                        $coEnd,
                        $coShow,
                        $allowOT,
                        $otAfter,
                        $otMin
                    ]);
                    $message = "تم إضافة الفرع «{$name}» بنجاح";
                    $msgType = 'success';
                } catch (PDOException $e) {
                    $message = 'خطأ: ' . ($e->getCode() == 23000 ? 'اسم الفرع مكرر' : $e->getMessage());
                    $msgType = 'error';
                }
            } else {
                $message = 'أدخل اسم الفرع والإحداثيات';
                $msgType = 'error';
            }
        }

        // --- تعديل فرع ---
        if ($action === 'edit') {
            $id       = (int)($_POST['branch_id'] ?? 0);
            $name     = sanitize($_POST['name'] ?? '');
            $lat      = (float)($_POST['latitude'] ?? 0);
            $lon      = (float)($_POST['longitude'] ?? 0);
            $radius   = (int)($_POST['geofence_radius'] ?? 25);
            $wsTime   = sanitize($_POST['work_start_time'] ?? '08:00');
            $weTime   = sanitize($_POST['work_end_time'] ?? '16:00');
            $ciStart  = sanitize($_POST['check_in_start_time'] ?? '07:00');
            $ciEnd    = sanitize($_POST['check_in_end_time'] ?? '10:00');
            $coStart  = sanitize($_POST['check_out_start_time'] ?? '15:00');
            $coEnd    = sanitize($_POST['check_out_end_time'] ?? '20:00');
            $coShow   = (int)($_POST['checkout_show_before'] ?? 30);
            $allowOT  = (int)($_POST['allow_overtime'] ?? 1);
            $otAfter  = (int)($_POST['overtime_start_after'] ?? 60);
            $otMin    = (int)($_POST['overtime_min_duration'] ?? 30);
            $active   = (int)($_POST['is_active'] ?? 1);

            if ($id && $name) {
                $stmt = db()->prepare("UPDATE branches SET name=?, latitude=?, longitude=?, geofence_radius=?,
                    work_start_time=?, work_end_time=?, check_in_start_time=?, check_in_end_time=?,
                    check_out_start_time=?, check_out_end_time=?, checkout_show_before=?,
                    allow_overtime=?, overtime_start_after=?, overtime_min_duration=?, is_active=?
                    WHERE id=?");
                $stmt->execute([
                    $name,
                    $lat,
                    $lon,
                    $radius,
                    $wsTime,
                    $weTime,
                    $ciStart,
                    $ciEnd,
                    $coStart,
                    $coEnd,
                    $coShow,
                    $allowOT,
                    $otAfter,
                    $otMin,
                    $active,
                    $id
                ]);
                $message = "تم تحديث الفرع «{$name}»";
                $msgType = 'success';
            }
        }

        // --- حذف فرع ---
        if ($action === 'delete') {
            $id = (int)($_POST['branch_id'] ?? 0);
            if ($id) {
                // تحقق من عدم وجود موظفين
                $empCount = db()->prepare("SELECT COUNT(*) FROM employees WHERE branch_id = ?");
                $empCount->execute([$id]);
                if ((int)$empCount->fetchColumn() > 0) {
                    $message = 'لا يمكن حذف فرع يحتوي على موظفين. انقل الموظفين أولاً.';
                    $msgType = 'error';
                } else {
                    db()->prepare("DELETE FROM branches WHERE id=?")->execute([$id]);
                    $message = "تم حذف الفرع";
                    $msgType = 'success';
                }
            }
        }

        // --- تفعيل/تعطيل ---
        if ($action === 'toggle') {
            $id = (int)($_POST['branch_id'] ?? 0);
            if ($id) {
                db()->prepare("UPDATE branches SET is_active = 1 - is_active WHERE id=?")->execute([$id]);
                $message = "تم تغيير حالة الفرع";
                $msgType = 'success';
            }
        }
    }
    header('Location: branches.php?msg=' . urlencode($message) . '&t=' . $msgType);
    exit;
}

// عرض الرسالة من redirect
if (!empty($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
    $msgType = $_GET['t'] ?? 'success';
}

// =================== جلب الفروع ===================
$branches = db()->query("SELECT b.*, (SELECT COUNT(*) FROM employees WHERE branch_id = b.id) AS emp_count FROM branches b ORDER BY b.id ASC")->fetchAll();

$csrf = generateCsrfToken();

require_once __DIR__ . '/../includes/admin_layout.php';
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" crossorigin="" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" crossorigin=""></script>

<style>
    .branch-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
        gap: 18px;
        margin-bottom: 22px;
    }

    .branch-card {
        background: var(--surface);
        border-radius: var(--radius);
        border: 1px solid var(--border);
        box-shadow: var(--shadow);
        overflow: hidden;
        transition: transform .2s, box-shadow .2s;
    }

    .branch-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .branch-card.inactive {
        opacity: .6;
    }

    .bc-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-d));
        padding: 16px 20px;
        color: #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .bc-name {
        font-size: 1.1rem;
        font-weight: 700;
    }

    .bc-badge {
        padding: 3px 10px;
        border-radius: 20px;
        font-size: .7rem;
        font-weight: 700;
        background: rgba(255, 255, 255, .2);
        border: 1px solid rgba(255, 255, 255, .3);
    }

    .bc-badge.off {
        background: rgba(0, 0, 0, .3);
    }

    .bc-body {
        padding: 16px 20px;
    }

    .bc-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px 16px;
        margin-bottom: 12px;
    }

    .bc-info {
        font-size: .78rem;
    }

    .bc-label {
        color: var(--text3);
        font-weight: 600;
        margin-bottom: 2px;
    }

    .bc-val {
        font-weight: 700;
        color: var(--text);
    }

    .bc-section {
        font-size: .72rem;
        font-weight: 700;
        color: var(--primary-d);
        margin: 10px 0 6px;
        padding-bottom: 4px;
        border-bottom: 1px solid var(--primary-l);
    }

    .bc-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        padding: 12px 20px;
        border-top: 1px solid var(--border);
        background: var(--surface2);
    }

    /* Modal styles */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, .5);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .modal-overlay.show {
        display: flex;
    }

    .modal-branch {
        background: #fff;
        border-radius: 16px;
        width: 95%;
        max-width: 700px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 25px 50px rgba(0, 0, 0, .25);
        padding: 0;
    }

    .modal-branch-head {
        background: linear-gradient(135deg, var(--primary), var(--primary-d));
        padding: 20px 24px;
        color: #fff;
        border-radius: 16px 16px 0 0;
        font-size: 1.1rem;
        font-weight: 700;
    }

    .modal-branch-body {
        padding: 24px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        margin-bottom: 14px;
    }

    .form-row.col3 {
        grid-template-columns: 1fr 1fr 1fr;
    }

    .form-row.col1 {
        grid-template-columns: 1fr;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-label {
        font-size: .78rem;
        font-weight: 600;
        color: var(--text2);
        margin-bottom: 4px;
    }

    .form-control {
        padding: 8px 12px;
        border: 1.5px solid var(--border);
        border-radius: 8px;
        font-family: inherit;
        font-size: .88rem;
        transition: border-color .2s;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(249, 115, 22, .15);
    }

    .form-section {
        font-size: .82rem;
        font-weight: 700;
        color: var(--primary-d);
        margin: 16px 0 8px;
        padding: 6px 0;
        border-bottom: 2px solid var(--primary-l);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .modal-branch-foot {
        padding: 16px 24px;
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    #branchMapAdd,
    #branchMapEdit {
        width: 100%;
        height: 250px;
        border-radius: 10px;
        margin-bottom: 10px;
        border: 2px solid var(--border);
    }
</style>

<?php if ($message): ?>
    <div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>"><?= $message ?></div>
<?php endif; ?>

<div class="card-header" style="margin-bottom:18px">
    <span class="card-title"><span class="card-title-bar"></span> الفروع (<?= count($branches) ?>)</span>
    <button class="btn btn-primary" onclick="openAddModal()">+ إضافة فرع</button>
</div>

<!-- بطاقات الفروع -->
<div class="branch-grid">
    <?php foreach ($branches as $b): ?>
        <div class="branch-card <?= $b['is_active'] ? '' : 'inactive' ?>">
            <div class="bc-header">
                <span class="bc-name"><?= htmlspecialchars($b['name']) ?></span>
                <span class="bc-badge <?= $b['is_active'] ? '' : 'off' ?>">
                    <?= $b['is_active'] ? 'مفعّل' : 'معطّل' ?>
                </span>
            </div>
            <div class="bc-body">
                <div class="bc-info-grid">
                    <div class="bc-info">
                        <div class="bc-label">عدد الموظفين</div>
                        <div class="bc-val"><?= $b['emp_count'] ?></div>
                    </div>
                    <div class="bc-info">
                        <div class="bc-label">نصف القطر</div>
                        <div class="bc-val"><?= $b['geofence_radius'] ?> م</div>
                    </div>
                </div>

                <div class="bc-section">مواعيد الدوام</div>
                <div class="bc-info-grid">
                    <div class="bc-info">
                        <div class="bc-label">بدء الدوام</div>
                        <div class="bc-val"><?= $b['work_start_time'] ?></div>
                    </div>
                    <div class="bc-info">
                        <div class="bc-label">نهاية الدوام</div>
                        <div class="bc-val"><?= $b['work_end_time'] ?></div>
                    </div>
                    <div class="bc-info">
                        <div class="bc-label">بدء تسجيل الدخول</div>
                        <div class="bc-val"><?= $b['check_in_start_time'] ?></div>
                    </div>
                    <div class="bc-info">
                        <div class="bc-label">نهاية تسجيل الدخول</div>
                        <div class="bc-val"><?= $b['check_in_end_time'] ?></div>
                    </div>
                    <div class="bc-info">
                        <div class="bc-label">بدء الانصراف</div>
                        <div class="bc-val"><?= $b['check_out_start_time'] ?></div>
                    </div>
                    <div class="bc-info">
                        <div class="bc-label">نهاية الانصراف</div>
                        <div class="bc-val"><?= $b['check_out_end_time'] ?></div>
                    </div>
                </div>

                <div class="bc-section">الدوام الإضافي</div>
                <div class="bc-info-grid">
                    <div class="bc-info">
                        <div class="bc-label">مسموح</div>
                        <div class="bc-val"><?= $b['allow_overtime'] ? 'نعم' : 'لا' ?></div>
                    </div>
                    <div class="bc-info">
                        <div class="bc-label">يبدأ بعد</div>
                        <div class="bc-val"><?= $b['overtime_start_after'] ?> دقيقة</div>
                    </div>
                </div>
            </div>
            <div class="bc-actions">
                <button class="btn btn-secondary btn-sm" onclick='openEditModal(<?= json_encode($b, JSON_UNESCAPED_UNICODE) ?>)'>تعديل</button>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="branch_id" value="<?= $b['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm"><?= $b['is_active'] ? 'تعطيل' : 'تفعيل' ?></button>
                </form>
                <?php if ($b['emp_count'] == 0): ?>
                    <form method="POST" style="display:inline" onsubmit="return confirm('حذف الفرع نهائياً؟')">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="branch_id" value="<?= $b['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">حذف</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($branches)): ?>
        <div class="card" style="text-align:center;padding:40px;color:var(--text3)">لا توجد فروع بعد. اضغط "إضافة فرع" لبدء الإعداد.</div>
    <?php endif; ?>
</div>

<!-- =================== Modal إضافة فرع =================== -->
<div class="modal-overlay" id="addModal">
    <div class="modal-branch">
        <div class="modal-branch-head">إضافة فرع جديد</div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="add">
            <div class="modal-branch-body">
                <div class="form-section">بيانات الفرع</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">اسم الفرع *</label>
                        <input class="form-control" name="name" required placeholder="مثال: صرح الرئيسي">
                    </div>
                    <div class="form-group">
                        <label class="form-label">نصف قطر النطاق (م) *</label>
                        <input class="form-control" type="number" name="geofence_radius" value="25" min="1" required>
                    </div>
                </div>
                <div class="form-section">الموقع الجغرافي — اضغط على الخريطة لتحديد الموقع</div>
                <div id="branchMapAdd"></div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">خط العرض *</label>
                        <input class="form-control" type="number" step="any" name="latitude" id="addLat" required style="direction:ltr">
                    </div>
                    <div class="form-group">
                        <label class="form-label">خط الطول *</label>
                        <input class="form-control" type="number" step="any" name="longitude" id="addLon" required style="direction:ltr">
                    </div>
                </div>

                <div class="form-section">أوقات الدوام
                    <button type="button" class="btn btn-green btn-sm" onclick="calcOptimal('add')" style="margin-right:auto;font-size:.72rem;padding:3px 10px">✨ النسب المثالية</button>
                </div>
                <div class="form-row col3">
                    <div class="form-group">
                        <label class="form-label">بدء الدوام</label>
                        <input class="form-control" type="time" name="work_start_time" id="addWS" value="08:00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">نهاية الدوام</label>
                        <input class="form-control" type="time" name="work_end_time" id="addWE" value="16:00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">عرض الانصراف قبل (دقيقة)</label>
                        <input class="form-control" type="number" name="checkout_show_before" id="addCOShow" value="30" min="0">
                    </div>
                </div>

                <div class="form-section">نوافذ التسجيل</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">بدء تسجيل الدخول</label>
                        <input class="form-control" type="time" name="check_in_start_time" id="addCIS" value="07:00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">نهاية تسجيل الدخول</label>
                        <input class="form-control" type="time" name="check_in_end_time" id="addCIE" value="10:00">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">بدء الانصراف</label>
                        <input class="form-control" type="time" name="check_out_start_time" id="addCOS" value="15:00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">نهاية الانصراف</label>
                        <input class="form-control" type="time" name="check_out_end_time" id="addCOE" value="20:00">
                    </div>
                </div>

                <div class="form-section">الدوام الإضافي</div>
                <div class="form-row col3">
                    <div class="form-group">
                        <label class="form-label">مسموح</label>
                        <select class="form-control" name="allow_overtime">
                            <option value="1">نعم</option>
                            <option value="0">لا</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">يبدأ بعد (دقيقة)</label>
                        <input class="form-control" type="number" name="overtime_start_after" id="addOTAfter" value="60" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الحد الأدنى (دقيقة)</label>
                        <input class="form-control" type="number" name="overtime_min_duration" id="addOTMin" value="30" min="0">
                    </div>
                </div>
            </div>
    </div>
    <div class="modal-branch-foot">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">إلغاء</button>
        <button type="submit" class="btn btn-primary">حفظ →</button>
    </div>
    </form>
</div>
</div>

<!-- =================== Modal تعديل فرع =================== -->
<div class="modal-overlay" id="editModal">
    <div class="modal-branch">
        <div class="modal-branch-head">تعديل الفرع</div>
        <form method="POST" id="editBranchForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="branch_id" id="editBranchId">
            <div class="modal-branch-body">
                <div class="form-section">بيانات الفرع</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">اسم الفرع *</label>
                        <input class="form-control" name="name" id="eName" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">نصف قطر النطاق (م)</label>
                        <input class="form-control" type="number" name="geofence_radius" id="eRadius" min="1">
                    </div>
                </div>
                <div class="form-section">الموقع الجغرافي</div>
                <div id="branchMapEdit"></div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">خط العرض</label>
                        <input class="form-control" type="number" step="any" name="latitude" id="eLat" style="direction:ltr">
                    </div>
                    <div class="form-group">
                        <label class="form-label">خط الطول</label>
                        <input class="form-control" type="number" step="any" name="longitude" id="eLon" style="direction:ltr">
                    </div>
                </div>

                <div class="form-section">أوقات الدوام
                    <button type="button" class="btn btn-green btn-sm" onclick="calcOptimal('edit')" style="margin-right:auto;font-size:.72rem;padding:3px 10px">✨ النسب المثالية</button>
                </div>
                <div class="form-row col3">
                    <div class="form-group">
                        <label class="form-label">بدء الدوام</label>
                        <input class="form-control" type="time" name="work_start_time" id="eWS">
                    </div>
                    <div class="form-group">
                        <label class="form-label">نهاية الدوام</label>
                        <input class="form-control" type="time" name="work_end_time" id="eWE">
                    </div>
                    <div class="form-group">
                        <label class="form-label">عرض الانصراف قبل (دقيقة)</label>
                        <input class="form-control" type="number" name="checkout_show_before" id="eCOShow" min="0">
                    </div>
                </div>

                <div class="form-section">نوافذ التسجيل</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">بدء تسجيل الدخول</label>
                        <input class="form-control" type="time" name="check_in_start_time" id="eCIS">
                    </div>
                    <div class="form-group">
                        <label class="form-label">نهاية تسجيل الدخول</label>
                        <input class="form-control" type="time" name="check_in_end_time" id="eCIE">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">بدء الانصراف</label>
                        <input class="form-control" type="time" name="check_out_start_time" id="eCOS">
                    </div>
                    <div class="form-group">
                        <label class="form-label">نهاية الانصراف</label>
                        <input class="form-control" type="time" name="check_out_end_time" id="eCOE">
                    </div>
                </div>

                <div class="form-section">الدوام الإضافي</div>
                <div class="form-row col3">
                    <div class="form-group">
                        <label class="form-label">مسموح</label>
                        <select class="form-control" name="allow_overtime" id="eOT">
                            <option value="1">نعم</option>
                            <option value="0">لا</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">يبدأ بعد (دقيقة)</label>
                        <input class="form-control" type="number" name="overtime_start_after" id="eOTAfter" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الحد الأدنى (دقيقة)</label>
                        <input class="form-control" type="number" name="overtime_min_duration" id="eOTMin" min="0">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">الحالة</label>
                        <select class="form-control" name="is_active" id="eActive">
                            <option value="1">مفعّل</option>
                            <option value="0">معطّل</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-branch-foot">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">إلغاء</button>
                <button type="submit" class="btn btn-primary">حفظ →</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) {
        document.getElementById(id).classList.add('show');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    // خريطة الإضافة
    let addMap, addMarker;

    function openAddModal() {
        openModal('addModal');
        setTimeout(() => {
            if (!addMap) {
                addMap = L.map('branchMapAdd').setView([24.7136, 46.6753], 12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap'
                }).addTo(addMap);
                addMap.on('click', function(e) {
                    if (addMarker) addMap.removeLayer(addMarker);
                    addMarker = L.marker(e.latlng).addTo(addMap);
                    document.getElementById('addLat').value = e.latlng.lat.toFixed(8);
                    document.getElementById('addLon').value = e.latlng.lng.toFixed(8);
                });
            }
            addMap.invalidateSize();
        }, 300);
    }

    // خريطة التعديل
    let editMap, editMarker;

    function openEditModal(b) {
        document.getElementById('editBranchId').value = b.id;
        document.getElementById('eName').value = b.name;
        document.getElementById('eRadius').value = b.geofence_radius;
        document.getElementById('eLat').value = b.latitude;
        document.getElementById('eLon').value = b.longitude;
        document.getElementById('eWS').value = b.work_start_time;
        document.getElementById('eWE').value = b.work_end_time;
        document.getElementById('eCIS').value = b.check_in_start_time;
        document.getElementById('eCIE').value = b.check_in_end_time;
        document.getElementById('eCOS').value = b.check_out_start_time;
        document.getElementById('eCOE').value = b.check_out_end_time;
        document.getElementById('eCOShow').value = b.checkout_show_before;
        document.getElementById('eOT').value = b.allow_overtime;
        document.getElementById('eOTAfter').value = b.overtime_start_after;
        document.getElementById('eOTMin').value = b.overtime_min_duration;
        document.getElementById('eActive').value = b.is_active;
        openModal('editModal');
        setTimeout(() => {
            const lat = parseFloat(b.latitude);
            const lon = parseFloat(b.longitude);
            if (!editMap) {
                editMap = L.map('branchMapEdit').setView([lat, lon], 16);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap'
                }).addTo(editMap);
                editMap.on('click', function(e) {
                    if (editMarker) editMap.removeLayer(editMarker);
                    editMarker = L.marker(e.latlng).addTo(editMap);
                    document.getElementById('eLat').value = e.latlng.lat.toFixed(8);
                    document.getElementById('eLon').value = e.latlng.lng.toFixed(8);
                });
            } else {
                editMap.setView([lat, lon], 16);
            }
            if (editMarker) editMap.removeLayer(editMarker);
            editMarker = L.marker([lat, lon]).addTo(editMap);
            editMap.invalidateSize();
        }, 300);
    }

    // Auto-calculate optimal settings based on work start/end
    function calcOptimal(prefix) {
        const wsEl = document.getElementById(prefix === 'edit' ? 'eWS' : 'addWS');
        const weEl = document.getElementById(prefix === 'edit' ? 'eWE' : 'addWE');
        const cisEl = document.getElementById(prefix === 'edit' ? 'eCIS' : 'addCIS');
        const cieEl = document.getElementById(prefix === 'edit' ? 'eCIE' : 'addCIE');
        const cosEl = document.getElementById(prefix === 'edit' ? 'eCOS' : 'addCOS');
        const coeEl = document.getElementById(prefix === 'edit' ? 'eCOE' : 'addCOE');
        const coShowEl = document.getElementById(prefix === 'edit' ? 'eCOShow' : 'addCOShow');
        const otAfterEl = document.getElementById(prefix === 'edit' ? 'eOTAfter' : 'addOTAfter');
        const otMinEl = document.getElementById(prefix === 'edit' ? 'eOTMin' : 'addOTMin');

        if (!wsEl.value || !weEl.value) {
            alert('حدد بدء الدوام ونهايته أولاً');
            return;
        }

        // Parse times to minutes since midnight
        function toMin(t) {
            const p = t.split(':');
            return parseInt(p[0]) * 60 + parseInt(p[1]);
        }

        function toTime(m) {
            m = ((m % 1440) + 1440) % 1440; // normalize to 0-1439
            return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
        }

        const ws = toMin(wsEl.value);
        const we = toMin(weEl.value);

        // Calculate shift duration (handle midnight crossing)
        let duration = we - ws;
        if (duration <= 0) duration += 1440;

        // Optimal values:
        // Check-in start: 30 min before work start
        cisEl.value = toTime(ws - 30);
        // Check-in end: 1 hour after work start
        cieEl.value = toTime(ws + 60);
        // Checkout show before: 15 min
        coShowEl.value = 15;
        // Check-out start: 15 min before work end
        cosEl.value = toTime(ws + duration - 15);
        // Check-out end: 30 min after work end
        coeEl.value = toTime(ws + duration + 30);
        // Overtime after: 30 min
        if (otAfterEl) otAfterEl.value = 30;
        // Overtime min: 30 min
        if (otMinEl) otMinEl.value = 30;

        // Flash green briefly on changed fields
        [cisEl, cieEl, cosEl, coeEl, coShowEl, otAfterEl, otMinEl].forEach(el => {
            if (!el) return;
            el.style.transition = 'background .3s';
            el.style.background = '#D1FAE5';
            setTimeout(() => {
                el.style.background = '';
            }, 1500);
        });
    }

    // إغلاق modal عند الضغط خارجه
    document.querySelectorAll('.modal-overlay').forEach(o => {
        o.addEventListener('click', e => {
            if (e.target === o) o.classList.remove('show');
        });
    });

    function tick() {
        const el = document.getElementById('topbarClock');
        if (el) el.textContent = new Date().toLocaleString('ar-SA');
    }
    tick();
    setInterval(tick, 1000);

    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('show');
    }
    document.getElementById('sidebarOverlay')?.addEventListener('click', toggleSidebar);
</script>

</div>
</div>
</body>

</html>