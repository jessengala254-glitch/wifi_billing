<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/functions.php';
session_start();

// Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

$pdo = db();
$admin_id = $_SESSION['user_id'];

// -----------------------------------------
// Read GET parameters
// -----------------------------------------
$search = $_GET['search'] ?? '';
$sort   = $_GET['sort'] ?? 'desc'; 
$filter = $_GET['filter'] ?? 'all';
$page   = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

$perPage = 20;
$offset  = ($page - 1) * $perPage;

// -----------------------------------------
// Get statistics for tabs
// -----------------------------------------
// Total payments
$totalPaymentsStmt = $pdo->query("SELECT COUNT(*) FROM payments");
$totalPayments = $totalPaymentsStmt->fetchColumn();

// Success payments
$successStmt = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'success'");
$successPayments = $successStmt->fetchColumn();

// Pending payments
$pendingStmt = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'");
$pendingPayments = $pendingStmt->fetchColumn();

// Failed payments
$failedStmt = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'failed'");
$failedPayments = $failedStmt->fetchColumn();

// Today's payments
$todayStmt = $pdo->query("SELECT COUNT(*) FROM payments WHERE DATE(created_at) = CURDATE()");
$todayPayments = $todayStmt->fetchColumn();

// -----------------------------------------
// Build the main query
// -----------------------------------------
$query = "
    SELECT 
        p.id,
        p.mpesa_receipt,
        p.amount,
        p.phone,
        p.status,
        p.created_at,
        p.transaction_time,
        u.name as user_name,
        v.username as voucher_username
    FROM payments p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN vouchers v ON p.phone = v.phone AND p.id = (
        SELECT MAX(p2.id) FROM payments p2 WHERE p2.phone = p.phone
    )
    WHERE 1=1
";

// Apply filter
if ($filter === 'success') {
    $query .= " AND p.status = 'success'";
} elseif ($filter === 'pending') {
    $query .= " AND p.status = 'pending'";
} elseif ($filter === 'failed') {
    $query .= " AND p.status = 'failed'";
} elseif ($filter === 'today') {
    $query .= " AND DATE(p.created_at) = CURDATE()";
}

// Apply search
if ($search) {
    $query .= " AND (
        p.phone LIKE :search 
        OR p.mpesa_receipt LIKE :search 
        OR u.name LIKE :search
        OR v.username LIKE :search
    )";
}

// Apply sorting
$query .= " ORDER BY p.created_at " . ($sort === 'asc' ? 'ASC' : 'DESC');

// Count total matching records
$countQuery = preg_replace('/SELECT.*?FROM/s', 'SELECT COUNT(DISTINCT p.id) FROM', $query);
$countStmt = $pdo->prepare($countQuery);
if ($search) {
    $countStmt->bindValue(':search', "%$search%");
}
$countStmt->execute();
$totalRecords = $countStmt->fetchColumn();
$totalPages   = ceil($totalRecords / $perPage);

// Get paginated data
$query .= " LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($query);
if ($search) {
    $stmt->bindValue(':search', "%$search%");
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Management | Leo Konnect</title>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .router-dashboard { 
            max-width:1200px; 
            margin:auto; 
            background:#fff; 
            border-radius:10px; 
            padding:20px; 
            box-shadow:0 2px 10px rgba(0,0,0,0.1);
        }
        
        /* Clickable row style */
        tbody tr {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        tbody tr:hover {
            background-color: #f0f7ff;
        }
        
        /* Tabs styling */
        .payment-tabs {
            display: flex;
            gap: 0;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
            background: #f8f9fa;
            border-radius: 8px 8px 0 0;
            overflow: hidden;
        }
        
        .payment-tab {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 15px 25px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            text-decoration: none;
            color: #666;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.3s;
            cursor: pointer;
            margin-bottom: -2px;
        }
        
        .payment-tab:hover {
            background: #e3f2fd;
        }
        
        .payment-tab.active {
            background: white;
            color: var(--secondary);
            border-bottom: 3px solid var(--secondary);
        }
        
        .tab-icon {
            font-size: 16px;
        }
        
        .tab-count {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            background: rgba(0,0,0,0.1);
            font-size: 12px;
            font-weight: 600;
        }
        
        .payment-tab.active .tab-count {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-success {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: bold;
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .badge-pending {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: bold;
            background: #fff3e0;
            color: #e65100;
        }
        
        .badge-failed {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: bold;
            background: #ffebee;
            color: #c62828;
        }
        
        .mpesa-code {
            font-family: 'Courier New', monospace;
            background: #f0f0f0;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            color: #333;
        }
        
        .amount-cell {
            font-weight: 600;
            color: #2e7d32;
            font-size: 14px;
        }
        
        .username-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #e3f2fd;
            color: #1976d2;
            border-radius: 4px;
            font-size: 13px;
            font-weight: bold;
        }
        
        .verified-icon {
            color: #2e7d32;
            font-weight: bold;
            font-size: 16px;
        }
        
        .unverified-icon {
            color: #c62828;
            font-weight: bold;
            font-size: 16px;
        }
        
        .search-filter-grid {
            display: grid;
            grid-template-columns: 15fr 10fr 3fr;
            gap: 10px;
            width: 100%;
            margin-bottom: 10px;
            margin-left:auto;
        }
        .search-filter-grid input,
        .search-filter-grid select,
        .search-filter-grid button {
            padding: 8px 20px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .search-filter-grid button {
            cursor: pointer;
        }
        
        .pagination {
            margin-top: 15px;
            text-align: center;
        }
        .pagination a {
            display: inline-block;
            padding: 6px 12px;
            margin: 0 2px;
            border: 1px solid #ccc;
            text-decoration: none;
            color: #333;
            border-radius: 4px;
        }
        .pagination a.active {
            background-color: var(--secondary);
            color: white;
            border-color: var(--secondary);
        }
        .pagination a:hover {
            background-color: #ddd;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h1>Manage Payments</h1>
        
        <div class="router-dashboard">
            <h2>Payments</h2>
            <p style="color: #666; margin-bottom: 20px;">Track and manage all payment transactions. Click on any payment to view detailed information.</p>

            <!-- Filter Tabs -->
            <div class="payment-tabs">
            <a href="?filter=all&sort=<?= $sort ?>&search=<?= urlencode($search) ?>" 
               class="payment-tab <?= $filter === 'all' ? 'active' : '' ?>">
                <span class="tab-icon">📊</span>
                <span>All Payments</span>
                <span class="tab-count"><?= $totalPayments ?></span>
            </a>
            <a href="?filter=success&sort=<?= $sort ?>&search=<?= urlencode($search) ?>" 
               class="payment-tab <?= $filter === 'success' ? 'active' : '' ?>">
                <span class="tab-icon">✅</span>
                <span>Success</span>
                <span class="tab-count"><?= $successPayments ?></span>
            </a>
            <a href="?filter=pending&sort=<?= $sort ?>&search=<?= urlencode($search) ?>" 
               class="payment-tab <?= $filter === 'pending' ? 'active' : '' ?>">
                <span class="tab-icon">⏳</span>
                <span>Pending</span>
                <span class="tab-count"><?= $pendingPayments ?></span>
            </a>
            <a href="?filter=failed&sort=<?= $sort ?>&search=<?= urlencode($search) ?>" 
               class="payment-tab <?= $filter === 'failed' ? 'active' : '' ?>">
                <span class="tab-icon">❌</span>
                <span>Failed</span>
                <span class="tab-count"><?= $failedPayments ?></span>
            </a>
            <a href="?filter=today&sort=<?= $sort ?>&search=<?= urlencode($search) ?>" 
               class="payment-tab <?= $filter === 'today' ? 'active' : '' ?>">
                <span class="tab-icon">📅</span>
                <span>Today</span>
                <span class="tab-count"><?= $todayPayments ?></span>
            </a>
        </div>

        <!-- Search and Sort -->
        <form method="GET" class="search-filter-grid">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            <input 
                type="text" 
                name="search" 
                value="<?= htmlspecialchars($search) ?>" 
                placeholder="Search by phone, M-Pesa code, username, or name"
            >
            <select name="sort">
                <option value="desc" <?= $sort === 'desc' ? 'selected' : '' ?>>Newest First</option>
                <option value="asc" <?= $sort === 'asc' ? 'selected' : '' ?>>Oldest First</option>
            </select>
            <button type="submit">Apply</button>
        </form>

        <!-- Payments Table -->
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Phone Number</th>
                    <th>M-Pesa Code</th>
                    <th>Amount (Ksh)</th>
                    <th>Verified</th>
                    <th>Status</th>
                    <th>Time Paid</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                        No payments found
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($payments as $payment): ?>
                    <tr onclick="window.location.href='payment_detail.php?id=<?= $payment['id'] ?>'">
                        <td><?= htmlspecialchars($payment['id']) ?></td>
                        <td>
                            <?php if ($payment['voucher_username']): ?>
                                <span class="username-badge"><?= htmlspecialchars($payment['voucher_username']) ?></span>
                            <?php else: ?>
                                <span style="color: #999;">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($payment['phone']) ?></td>
                        <td>
                            <?php if ($payment['mpesa_receipt']): ?>
                                <span class="mpesa-code"><?= htmlspecialchars($payment['mpesa_receipt']) ?></span>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="amount-cell">Ksh <?= number_format($payment['amount'], 2) ?></td>
                        <td style="text-align: center;">
                            <?php if ($payment['mpesa_receipt']): ?>
                                <span class="verified-icon" title="Verified">✓</span>
                            <?php else: ?>
                                <span class="unverified-icon" title="Not Verified">✗</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($payment['status'] === 'success'): ?>
                                <span class="badge-success">Success</span>
                            <?php elseif ($payment['status'] === 'pending'): ?>
                                <span class="badge-pending">Pending</span>
                            <?php else: ?>
                                <span class="badge-failed">Failed</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($payment['transaction_time']): ?>
                                <?= date('M d, Y H:i', strtotime($payment['transaction_time'])) ?>
                            <?php else: ?>
                                <?= date('M d, Y H:i', strtotime($payment['created_at'])) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?filter=<?= $filter ?>&sort=<?= $sort ?>&search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>">
                    &laquo; Previous
                </a>
            <?php endif; ?>

            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            if ($startPage > 1): ?>
                <a href="?filter=<?= $filter ?>&sort=<?= $sort ?>&search=<?= urlencode($search) ?>&page=1">1</a>
                <?php if ($startPage > 2): ?>
                    <span>...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <a href="?filter=<?= $filter ?>&sort=<?= $sort ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>" 
                   class="<?= $i === $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                    <span>...</span>
                <?php endif; ?>
                <a href="?filter=<?= $filter ?>&sort=<?= $sort ?>&search=<?= urlencode($search) ?>&page=<?= $totalPages ?>">
                    <?= $totalPages ?>
                </a>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?filter=<?= $filter ?>&sort=<?= $sort ?>&search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>">
                    Next &raquo;
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px; text-align: center; color: #666;">
            Showing <?= count($payments) ?> of <?= $totalRecords ?> payment(s)
        </div>
        </div>
    </div>
</body>
</html>
