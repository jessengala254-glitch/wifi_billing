<?php
require_once __DIR__ . '/../inc/functions.php';
$pdo = db();
$pdo->exec("SET time_zone = '+03:00'");

$stmt = $pdo->query("SELECT id, username, plan_type, rate_limit, expiry, status, created_at, phone FROM vouchers ORDER BY created_at DESC LIMIT 20");
$vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<style>
.status-active { color: green; font-weight: bold; }
.status-expired { color: red; font-weight: bold; }
</style>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Plan Type</th>
            <th>Rate Limit</th>
            <th>Expiry</th>
            <th>Status</th>
            <th>Created At</th>
            <th>Phone</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($vouchers as $v): ?>
        <tr>
            <td><?= htmlspecialchars($v['id'] ?? '') ?></td>
            <td><?= htmlspecialchars($v['username'] ?? 'N/A') ?></td>
            <td><?= htmlspecialchars($v['plan_type'] ?? 'Unknown') ?></td>
            <td><?= htmlspecialchars($v['rate_limit'] ?? '-') ?></td>
            <td><?= htmlspecialchars($v['expiry'] ?? '-') ?></td>
            <td>
                <span class="status-<?= htmlspecialchars($v['status']) ?>">
                    <?= htmlspecialchars($v['status']) ?>
                </span>
            </td>
            <td><?= htmlspecialchars($v['created_at'] ?? '-') ?></td>
            <td><?= htmlspecialchars($v['phone'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
