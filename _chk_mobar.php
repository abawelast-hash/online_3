<?php
require __DIR__ . '/includes/db.php';
// حذف الموظفين المكررين (ID 56-66) — النسخ اللي PIN فيها "1" زيادة
$ids = [56,57,58,59,60,61,62,63,64,65,66];
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$del = db()->prepare("DELETE FROM employees WHERE id IN ($placeholders) AND branch_id = 6");
$del->execute($ids);
echo "Deleted: " . $del->rowCount() . " duplicate employees\n";
// تأكيد
$cnt = db()->query("SELECT COUNT(*) FROM employees WHERE branch_id = 6")->fetchColumn();
echo "Remaining branch 6 employees: $cnt\n";
@unlink(__FILE__);
