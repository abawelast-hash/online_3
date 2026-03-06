<?php
// admin/employees.php - إدارة الموظفين (CRUD + WhatsApp)
// =============================================================

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminLogin();

$pageTitle  = 'إدارة الموظفين';
$activePage = 'employees';
$message    = '';
$msgType    = '';

// =================== إجراءات POST ===================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'طلب غير صالح';
        $msgType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        // --- إضافة موظف ---
        if ($action === 'add') {
            $name    = sanitize($_POST['name'] ?? '');
            $job     = sanitize($_POST['job_title'] ?? '');
            $pin     = sanitize($_POST['pin'] ?? '');
            $phone   = sanitize($_POST['phone'] ?? '');
            $branchId = (int)($_POST['branch_id'] ?? 0) ?: null;

            if ($name && $job && $pin) {
                try {
                    $token = generateUniqueToken();
                    $stmt  = db()->prepare("INSERT INTO employees (name, job_title, pin, phone, branch_id, unique_token) VALUES (?,?,?,?,?,?)");
                    $stmt->execute([$name, $job, $pin, $phone ?: null, $branchId, $token]);
                    $newId = (int)db()->lastInsertId();
                    auditLog('add_employee', "إضافة موظف: {$name}", $newId);
                    $message = "تم إضافة الموظف {$name} بنجاح";
                    $msgType = 'success';
                } catch (PDOException $e) {
                    $message = 'PIN أو بيانات مكررة: ' . $e->getMessage();
                    $msgType = 'error';
                }
            } else {
                $message = 'أدخل الاسم والوظيفة والرقم السري';
                $msgType = 'error';
            }
        }

        // --- تعديل موظف ---
        if ($action === 'edit') {
            $id    = (int)($_POST['emp_id'] ?? 0);
            $name  = sanitize($_POST['name'] ?? '');
            $job   = sanitize($_POST['job_title'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $active = (int)($_POST['is_active'] ?? 1);
            $branchId = (int)($_POST['branch_id'] ?? 0) ?: null;

            if ($id && $name && $job) {
                $stmt = db()->prepare("UPDATE employees SET name=?, job_title=?, phone=?, branch_id=?, is_active=? WHERE id=?");
                $stmt->execute([$name, $job, $phone ?: null, $branchId, $active, $id]);
                auditLog('edit_employee', "تعديل موظف: {$name}", $id);
                $message = "تم تحديث بيانات الموظف";
                $msgType = 'success';
            }
        }

        // --- حذف موظف (Soft Delete) ---
        if ($action === 'delete') {
            $id = (int)($_POST['emp_id'] ?? 0);
            if ($id) {
                db()->prepare("UPDATE employees SET deleted_at=NOW(), is_active=0 WHERE id=?")->execute([$id]);
                auditLog('delete_employee', "أرشفة موظف ID={$id}", $id);
                $message = "تم أرشفة الموظف (يمكن استعادته لاحقاً)";
                $msgType = 'success';
            }
        }

        // --- استعادة موظف محذوف ---
        if ($action === 'restore') {
            $id = (int)($_POST['emp_id'] ?? 0);
            if ($id) {
                db()->prepare("UPDATE employees SET deleted_at=NULL, is_active=1 WHERE id=?")->execute([$id]);
                auditLog('restore_employee', "استعادة موظف ID={$id}", $id);
                $message = "تم استعادة الموظف بنجاح";
                $msgType = 'success';
            }
        }

        // --- إعادة توليد Token ---
        if ($action === 'regen_token') {
            $id = (int)($_POST['emp_id'] ?? 0);
            if ($id) {
                $token = generateUniqueToken();
                db()->prepare("UPDATE employees SET unique_token=? WHERE id=?")->execute([$token, $id]);
                $message = "تم توليد رابط جديد للموظف";
                $msgType = 'success';
            }
        }

        // --- تفعيل/تعطيل موظف ---
        if ($action === 'toggle') {
            $id = (int)($_POST['emp_id'] ?? 0);
            if ($id) {
                db()->prepare("UPDATE employees SET is_active = 1 - is_active WHERE id=?")->execute([$id]);
                $message = "تم تغيير حالة الموظف";
                $msgType = 'success';
            }
        }

        // --- إعادة تعيين بصمة الجهاز ---
        if ($action === 'reset_device') {
            $id = (int)($_POST['emp_id'] ?? 0);
            if ($id) {
                db()->prepare("UPDATE employees SET device_fingerprint=NULL, device_registered_at=NULL, device_bind_mode=0 WHERE id=?")->execute([$id]);
                $message = "تم إعادة تعيين الجهاز — الرابط الآن حر بدون ربط";
                $msgType = 'success';
            }
        }

        // --- تفعيل ربط الجهاز (يربط عند الدخول التالي) ---
        if ($action === 'enable_bind') {
            $id = (int)($_POST['emp_id'] ?? 0);
            if ($id) {
                db()->prepare("UPDATE employees SET device_bind_mode=1 WHERE id=?")->execute([$id]);
                auditLog('enable_bind', "تفعيل ربط الجهاز للموظف ID={$id}", $id);
                $message = "تم تفعيل وضع الربط — سيُربط الجهاز عند الدخول التالي للموظف";
                $msgType = 'success';
            }
        }

        // --- فك ربط جميع الأجهزة ---
        if ($action === 'reset_all_devices') {
            $result = db()->exec("UPDATE employees SET device_fingerprint=NULL, device_registered_at=NULL, device_bind_mode=0 WHERE deleted_at IS NULL");
            auditLog('reset_all_devices', "فك ربط جميع الأجهزة — {$result} موظف");
            $message = "تم فك ربط جميع الأجهزة — {$result} موظف";
            $msgType = 'success';
        }

        // --- تفعيل الربط التلقائي لجميع الموظفين عند الدخول القادم ---
        if ($action === 'enable_bind_all') {
            $result = db()->exec("UPDATE employees SET device_bind_mode=1 WHERE is_active=1 AND deleted_at IS NULL AND device_fingerprint IS NULL");
            auditLog('enable_bind_all', "تفعيل ربط الجهاز لجميع الموظفين — {$result} موظف");
            $message = "تم تفعيل الربط التلقائي عند الدخول القادم — {$result} موظف";
            $msgType = 'success';
        }
    }
    header('Location: employees.php?msg=' . urlencode($message) . '&t=' . $msgType);
    exit;
}

// عرض الرسالة من redirect
if (!empty($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
    $msgType = $_GET['t'] ?? 'success';
}

// =================== جلب الموظفين ===================
$search = trim($_GET['search'] ?? '');
$filterBranch = (int)($_GET['branch'] ?? 0);

$whereClause = '';
$params      = [];
$conditions  = ['e.deleted_at IS NULL'];
if ($search) {
    $conditions[] = "(e.name LIKE ? OR e.job_title LIKE ? OR e.pin LIKE ?)";
    $params       = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($filterBranch) {
    $conditions[] = "e.branch_id = ?";
    $params[]     = $filterBranch;
}
if ($conditions) {
    $whereClause = "WHERE " . implode(' AND ', $conditions);
}

$totalStmt = db()->prepare("SELECT COUNT(*) FROM employees e $whereClause");
$totalStmt->execute($params);
$total     = (int)$totalStmt->fetchColumn();

$empStmt = db()->prepare("SELECT e.*, b.name AS branch_name FROM employees e LEFT JOIN branches b ON e.branch_id = b.id $whereClause ORDER BY COALESCE(b.name, 'zzz') ASC, e.name ASC");
$empStmt->execute($params);
$employees = $empStmt->fetchAll();

// جلب الفروع لعرضها في القوائم
$allBranches = db()->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll();

// ألوان مميزة لكل فرع
$_bColors = ['#E74C3C', '#3498DB', '#2ECC71', '#9B59B6', '#F39C12', '#1ABC9C', '#E67E22', '#34495E', '#16A085', '#C0392B'];
$branchColorMap = [];
foreach ($allBranches as $_i => $br) {
    $branchColorMap[$br['id']] = $_bColors[$_i % count($_bColors)];
}

$csrf = generateCsrfToken();

require_once __DIR__ . '/../includes/admin_layout.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>"><?= $message ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:18px;padding:14px">
    <div class="top-actions" style="display:flex;gap:12px;flex-wrap:wrap;justify-content:space-between;align-items:flex-end">
        <!-- بحث -->
        <form method="GET" class="filter-bar" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;flex:1;min-width:200px">
            <input class="form-control" name="search" placeholder="بحث بالاسم أو الوظيفة أو PIN..."
                value="<?= htmlspecialchars($search) ?>" style="max-width:240px">
            <select class="form-control" name="branch" style="max-width:180px">
                <option value="0">— كل الفروع —</option>
                <?php foreach ($allBranches as $br): ?>
                    <option value="<?= $br['id'] ?>" <?= $filterBranch == $br['id'] ? 'selected' : '' ?>><?= htmlspecialchars($br['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary">بحث</button>
            <?php if ($search || $filterBranch): ?><a href="employees.php" class="btn btn-secondary">إلغاء</a><?php endif; ?>
        </form>
        <div class="top-actions" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <button class="btn btn-primary" onclick="openModal('addModal')">+ إضافة موظف</button>
            <div class="dropdown-wrap" style="position:relative">
                <button class="btn btn-secondary" onclick="this.nextElementSibling.classList.toggle('show')" type="button">
                    ⚙️ إجراءات جماعية ▾
                </button>
                <div class="dropdown-menu">
                    <button type="button" class="dropdown-item" onclick="regenerateAllTokens();this.closest('.dropdown-menu').classList.remove('show')">
                        <?= svgIcon('attendance', 16) ?> تجديد جميع الروابط
                    </button>
                    <button type="button" class="dropdown-item" onclick="copyAllLinks();this.closest('.dropdown-menu').classList.remove('show')" id="btnCopyAll">
                        <?= svgIcon('copy', 16) ?> نسخ جميع الروابط
                    </button>
                    <button type="button" class="dropdown-item" onclick="checkAllLinks();this.closest('.dropdown-menu').classList.remove('show')" id="btnCheckLinks">
                        🔍 فحص الروابط
                    </button>
                    <div style="border-top:1px solid var(--border);margin:4px 0"></div>
                    <form method="POST" onsubmit="return confirm('فك ربط جميع الأجهزة؟')">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="reset_all_devices">
                        <button type="submit" class="dropdown-item" style="color:var(--red)">
                            <?= svgIcon('lock', 16) ?> فك جميع الأجهزة
                        </button>
                    </form>
                    <form method="POST" onsubmit="return confirm('تفعيل الربط التلقائي للجميع؟')">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="enable_bind_all">
                        <button type="submit" class="dropdown-item" style="color:var(--green)">
                            <?= svgIcon('key', 16) ?> تفعيل الربط للجميع
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title"><span class="card-title-bar"></span> قائمة الموظفين (<?= $total ?>)</span>
        <span class="badge badge-blue">جميع الموظفين</span>
    </div>
    <div style="overflow-x:auto">
        <table class="emp-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الاسم</th>
                    <th>الوظيفة</th>
                    <th>الفرع</th>
                    <th>الحالة</th>
                    <th>الجهاز</th>
                    <th>حالة الرابط</th>
                    <th>رابط الحضور</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php $lastBranchName = null;
                $seq = 0;
                foreach ($employees as $i => $emp):
                    $curBranch = $emp['branch_name'] ?? 'بدون فرع';
                    $rowColor  = $branchColorMap[$emp['branch_id'] ?? 0] ?? '#94A3B8';
                    if ($curBranch !== $lastBranchName):
                        $lastBranchName = $curBranch;
                        // حساب عدد موظفي هذا الفرع
                        $brCount = 0;
                        foreach ($employees as $_e) {
                            if (($_e['branch_name'] ?? 'بدون فرع') === $curBranch) $brCount++;
                        }
                ?>
                        <tr>
                            <td colspan="9" style="background:<?= $rowColor ?>12;border-right:4px solid <?= $rowColor ?>;padding:8px 14px;font-weight:700;font-size:.88rem;color:<?= $rowColor ?>;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                                <span>
                                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $rowColor ?>;margin-left:6px;vertical-align:middle"></span>
                                    <?= htmlspecialchars($curBranch) ?>
                                    <span style="font-size:.72rem;font-weight:400;color:var(--text3);margin-right:6px">(<?= $brCount ?> موظف)</span>
                                </span>
                                <button class="btn btn-green btn-sm" onclick="copyBranchLinks('<?= htmlspecialchars(addslashes($curBranch), ENT_QUOTES) ?>')" style="font-size:.72rem;padding:4px 10px;white-space:nowrap">
                                    <?= svgIcon('copy', 12) ?> نسخ روابط الفرع
                                </button>
                            </td>
                        </tr>
                    <?php endif;
                    $seq++; ?>
                    <tr style="border-right:3px solid <?= $rowColor ?>">
                        <td style="color:var(--text3)"><?= $seq ?></td>
                        <td>
                            <strong><?= htmlspecialchars($emp['name']) ?></strong>
                            <?php if ($emp['phone']): ?>
                                <br><small style="color:var(--text3)"><?= htmlspecialchars($emp['phone']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($emp['job_title']) ?></td>
                        <td style="font-size:.8rem;font-weight:600;color:<?= $rowColor ?>"><?= htmlspecialchars($emp['branch_name'] ?? '—') ?></td>
                        <td>
                            <?php if ($emp['is_active']): ?>
                                <span class="badge badge-green">مفعّل</span>
                            <?php else: ?>
                                <span class="badge badge-red">معطّل</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center">
                            <?php if (!empty($emp['device_fingerprint'])): ?>
                                <span title="مربوط بجهاز — <?= $emp['device_registered_at'] ? date('Y-m-d', strtotime($emp['device_registered_at'])) : '' ?>" style="color:var(--green);cursor:default"><?= svgIcon('lock', 18) ?></span>
                            <?php elseif (!empty($emp['device_bind_mode'])): ?>
                                <span class="badge badge-yellow" style="font-size:.65rem" title="ينتظر ربط الجهاز عند الدخول التالي">⏳ ينتظر</span>
                            <?php else: ?>
                                <span class="badge badge-blue" style="font-size:.65rem" title="حر — لا يحتاج ربط جهاز">🔓 حر</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center">
                            <span class="link-status" data-emp-id="<?= $emp['id'] ?>" style="font-size:.7rem;color:var(--text3)">—</span>
                        </td>
                        <td>
                            <?php $link = SITE_URL . '/employee/attendance.php?token=' . $emp['unique_token']; ?>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                <a href="<?= $link ?>" target="_blank" class="btn btn-secondary btn-sm">الرابط</a>
                                <?php if ($emp['phone']): ?>
                                    <a href="<?= generateWhatsAppLink($emp['phone'], $emp['unique_token']) ?>"
                                        target="_blank" class="btn btn-wa btn-sm">واتساب</a>
                                <?php else: ?>
                                    <button class="btn btn-wa btn-sm"
                                        onclick="copyLink('<?= $link ?>')" title="نسخ الرابط"><?= svgIcon('copy', 14) ?> نسخ</button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="actions-wrap" style="display:flex;gap:6px;flex-wrap:wrap">
                                <button class="btn btn-secondary btn-sm" onclick='openEditModal(<?= json_encode($emp, JSON_UNESCAPED_UNICODE) ?>)' title="تعديل"><?= svgIcon('settings', 14) ?></button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('إعادة توليد رابط؟ الرابط القديم سيتوقف.')">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="action" value="regen_token">
                                    <input type="hidden" name="emp_id" value="<?= $emp['id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm" title="إعادة توليد الرابط"><?= svgIcon('checkout', 14) ?></button>
                                </form>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="emp_id" value="<?= $emp['id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm" title="تفعيل/تعطيل">
                                        <?php if ($emp['is_active']): ?>
                                            <span style="color:var(--red)"><?= svgIcon('absent', 14) ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--green)"><?= svgIcon('checkin', 14) ?></span>
                                        <?php endif; ?>
                                    </button>
                                </form>
                                <?php if (!empty($emp['device_fingerprint'])): ?>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('إعادة تعيين الجهاز؟ سيصبح الرابط حراً بدون ربط.')">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="reset_device">
                                        <input type="hidden" name="emp_id" value="<?= $emp['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" title="فك ربط الجهاز"><?= svgIcon('lock', 14) ?></button>
                                    </form>
                                <?php elseif (empty($emp['device_bind_mode'])): ?>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('تفعيل ربط الجهاز؟ سيُربط تلقائياً عند الدخول التالي للموظف.')">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="enable_bind">
                                        <input type="hidden" name="emp_id" value="<?= $emp['id'] ?>">
                                        <button type="submit" class="btn btn-green btn-sm" title="ربط الجهاز عند الدخول التالي"><?= svgIcon('key', 14) ?></button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('أرشفة الموظف؟ يمكن استعادته لاحقاً.')">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="emp_id" value="<?= $emp['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" title="أرشفة"><?= svgIcon('absent', 14) ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center;padding:30px;color:var(--text3)">لا توجد نتائج</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- =================== Modal إضافة موظف =================== -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-title"><?= svgIcon('employees', 20) ?> إضافة موظف جديد</div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="add">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">الاسم الكامل *</label>
                    <input class="form-control" name="name" required placeholder="محمد أحمد ...">
                </div>
                <div class="form-group">
                    <label class="form-label">المسمى الوظيفي *</label>
                    <input class="form-control" name="job_title" required placeholder="مهندس">
                </div>
                <div class="form-group">
                    <label class="form-label">PIN (رقم سري) *</label>
                    <input class="form-control" name="pin" required placeholder="1234" style="direction:ltr">
                </div>
                <div class="form-group">
                    <label class="form-label">الفرع</label>
                    <select class="form-control" name="branch_id">
                        <option value="">— بدون فرع —</option>
                        <?php foreach ($allBranches as $br): ?>
                            <option value="<?= $br['id'] ?>"><?= htmlspecialchars($br['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">رقم الواتساب (اختياري)</label>
                    <input class="form-control" name="phone" placeholder="966501234567" style="direction:ltr">
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">إلغاء</button>
                <button type="submit" class="btn btn-primary">حفظ →</button>
            </div>
        </form>
    </div>
</div>

<!-- =================== Modal تعديل موظف =================== -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-title"><?= svgIcon('settings', 20) ?> تعديل بيانات الموظف</div>
        <form method="POST" id="editForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="emp_id" id="editId">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">الاسم الكامل *</label>
                    <input class="form-control" name="name" id="editName" required>
                </div>
                <div class="form-group">
                    <label class="form-label">المسمى الوظيفي *</label>
                    <input class="form-control" name="job_title" id="editJob" required>
                </div>
                <div class="form-group">
                    <label class="form-label">الفرع</label>
                    <select class="form-control" name="branch_id" id="editBranch">
                        <option value="">— بدون فرع —</option>
                        <?php foreach ($allBranches as $br): ?>
                            <option value="<?= $br['id'] ?>"><?= htmlspecialchars($br['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">رقم الواتساب</label>
                    <input class="form-control" name="phone" id="editPhone" style="direction:ltr">
                </div>
                <div class="form-group">
                    <label class="form-label">الحالة</label>
                    <select class="form-control" name="is_active" id="editActive">
                        <option value="1">مفعّل</option>
                        <option value="0">معطّل</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
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

    function openEditModal(emp) {
        document.getElementById('editId').value = emp.id;
        document.getElementById('editName').value = emp.name;
        document.getElementById('editJob').value = emp.job_title;
        document.getElementById('editPhone').value = emp.phone || '';
        document.getElementById('editBranch').value = emp.branch_id || '';
        document.getElementById('editActive').value = emp.is_active;
        openModal('editModal');
    }

    function copyLink(link) {
        navigator.clipboard.writeText(link).then(() => alert('تم نسخ الرابط!')).catch(() => {
            prompt('انسخ الرابط:', link);
        });
    }

    // تحديث جميع الروابط
    function regenerateAllTokens() {
        if (!confirm('هل أنت متأكد من تجديد جميع الروابط؟\n\nسيتم إنشاء روابط جديدة لجميع الموظفين النشطين.\nالروابط القديمة لن تعمل بعد ذلك.')) {
            return;
        }

        const btn = document.getElementById('btnRegenerate');
        btn.disabled = true;
        btn.innerHTML = '⏳ جاري التجديد...';

        const formData = new FormData();
        formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
        formData.append('action', 'all');

        fetch('../api/regenerate-tokens.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    location.reload();
                } else {
                    alert('❌ خطأ: ' + data.message);
                    btn.disabled = false;
                    btn.innerHTML = '<?= svgIcon('attendance', 16) ?> تجديد جميع الروابط';
                }
            })
            .catch(err => {
                alert('❌ حدث خطأ: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = '<?= svgIcon('attendance', 16) ?> تجديد جميع الروابط';
            });
    }

    // بيانات الروابط حسب الفرع — يُبنى من PHP
    const branchLinksData = <?php
                            // بناء بيانات الفروع والروابط
                            $branchLinks = [];
                            foreach ($employees as $emp) {
                                if (!$emp['is_active']) continue;
                                $bName = $emp['branch_name'] ?? 'بدون فرع';
                                if (!isset($branchLinks[$bName])) $branchLinks[$bName] = [];
                                $branchLinks[$bName][] = [
                                    'name' => $emp['name'],
                                    'link' => SITE_URL . '/employee/attendance.php?token=' . $emp['unique_token']
                                ];
                            }
                            echo json_encode($branchLinks, JSON_UNESCAPED_UNICODE);
                            ?>;

    function formatBranchMessage(branchName, emps) {
        const today = new Date().toLocaleDateString('ar-SA', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        let msg = '📋 *روابط تسجيل الحضور والانصراف*\n';
        msg += '🏢 *الفرع: ' + branchName + '*\n';
        msg += '📅 *التاريخ: ' + today + '*\n';
        msg += '━━━━━━━━━━━━━━━\n\n';
        emps.forEach((e, i) => {
            msg += (i + 1) + '. *' + e.name + '*\n';
            msg += '🔗 ' + e.link + '\n\n';
        });
        msg += '━━━━━━━━━━━━━━━\n';
        msg += '⚠️ _الرابط خاص بك، لا تشاركه مع أحد_';
        return msg;
    }

    function copyBranchLinks(branchName) {
        const emps = branchLinksData[branchName];
        if (!emps || emps.length === 0) {
            alert('لا يوجد موظفين نشطين في هذا الفرع');
            return;
        }
        const msg = formatBranchMessage(branchName, emps);
        navigator.clipboard.writeText(msg).then(() => {
            alert('✅ تم نسخ روابط فرع "' + branchName + '" (' + emps.length + ' موظف) — الصقها في مجموعة الواتساب');
        }).catch(() => {
            prompt('انسخ النص:', msg);
        });
    }

    function copyAllLinks() {
        const btn = document.getElementById('btnCopyAll');
        const today = new Date().toLocaleDateString('ar-SA', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        let msg = '📋 *جميع روابط تسجيل الحضور والانصراف*\n';
        msg += '📅 *التاريخ: ' + today + '*\n';
        msg += '━━━━━━━━━━━━━━━\n\n';
        const branches = Object.keys(branchLinksData);
        branches.forEach(branchName => {
            const emps = branchLinksData[branchName];
            if (emps.length === 0) return;
            msg += '🏢 *' + branchName + '* (' + emps.length + ' موظف)\n';
            msg += '───────────────\n';
            emps.forEach((e, i) => {
                msg += (i + 1) + '. *' + e.name + '*\n';
                msg += '🔗 ' + e.link + '\n\n';
            });
        });
        msg += '━━━━━━━━━━━━━━━\n';
        msg += '⚠️ _الرابط خاص بك، لا تشاركه مع أحد_';
        navigator.clipboard.writeText(msg).then(() => {
            const total = branches.reduce((s, b) => s + branchLinksData[b].length, 0);
            btn.innerHTML = '✅ تم النسخ (' + total + ' موظف)';
            setTimeout(() => {
                btn.innerHTML = '<?= svgIcon('copy', 16) ?> نسخ جميع الروابط';
            }, 3000);
        }).catch(() => {
            prompt('انسخ النص:', msg);
        });
    }

    // فحص جميع الروابط
    function checkAllLinks() {
        const btn = document.getElementById('btnCheckLinks');
        btn.disabled = true;
        btn.innerHTML = '⏳ جاري الفحص...';

        // تعيين كل الحالات لـ "جاري..."
        document.querySelectorAll('.link-status').forEach(el => {
            el.innerHTML = '<span style="color:var(--text3)">⏳</span>';
        });

        fetch('../api/check-links.php')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    data.results.forEach(r => {
                        const el = document.querySelector(`.link-status[data-emp-id="${r.id}"]`);
                        if (!el) return;
                        if (r.status === 'ok') {
                            el.innerHTML = '<span class="badge badge-green" style="font-size:.65rem">✅ يعمل</span>';
                        } else if (r.status === 'inactive') {
                            el.innerHTML = '<span class="badge badge-yellow" style="font-size:.65rem">⚠️ معطّل</span>';
                        } else {
                            el.innerHTML = '<span class="badge badge-red" style="font-size:.65rem">❌ خطأ ' + r.code + '</span>';
                        }
                    });
                    btn.innerHTML = '✅ تم الفحص (' + data.ok + '/' + data.total + ')';
                    setTimeout(() => {
                        btn.disabled = false;
                        btn.innerHTML = '🔍 فحص الروابط';
                    }, 5000);
                } else {
                    alert('❌ خطأ: ' + data.message);
                    btn.disabled = false;
                    btn.innerHTML = '🔍 فحص الروابط';
                }
            })
            .catch(err => {
                alert('❌ حدث خطأ: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = '🔍 فحص الروابط';
            });
    }

    // إغلاق modal عند الضغط خارجه
    document.querySelectorAll('.modal-overlay').forEach(o => {
        o.addEventListener('click', e => {
            if (e.target === o) o.classList.remove('show');
        });
    });

    // إغلاق dropdown عند الضغط خارجه
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown-wrap')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
        }
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
