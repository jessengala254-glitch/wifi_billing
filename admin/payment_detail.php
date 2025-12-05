<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/functions.php';
session_start();

// Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

$pdo = db();
$payment_id = $_GET['id'] ?? null;

if (!$payment_id) {
    header('Location: payments_manage.php');
    exit;
}

// Get payment details
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        u.name as user_name,
        u.email as user_email,
        u.phone as user_phone,
        u.status as user_status,
        pl.title as plan_title,
        pl.price as plan_price,
        pl.duration_minutes as plan_duration,
        pl.description as plan_description
    FROM payments p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN plans pl ON p.plan_id = pl.id
    WHERE p.id = ?
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if (!$payment) {
    header('Location: payments_manage.php');
    exit;
}

// Get voucher associated with this payment
$voucherStmt = $pdo->prepare("
    SELECT * FROM vouchers 
    WHERE phone = ? 
    AND DATE(created_at) = DATE(?)
    ORDER BY created_at DESC 
    LIMIT 1
");
$voucherStmt->execute([$payment['phone'], $payment['created_at']]);
$voucher = $voucherStmt->fetch();

// Get user's payment history
$historyStmt = $pdo->prepare("
    SELECT p.*, pl.title as plan_title 
    FROM payments p
    LEFT JOIN plans pl ON p.plan_id = pl.id
    WHERE p.phone = ?
    ORDER BY p.created_at DESC
    LIMIT 10
");
$historyStmt->execute([$payment['phone']]);
$paymentHistory = $historyStmt->fetchAll();

// Tab parameter
$activeTab = $_GET['tab'] ?? 'general';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Details #<?= $payment['id'] ?> - LeoKonnect</title>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .payment-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .payment-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .payment-info-left h2 {
            margin: 0 0 10px 0;
            font-size: 24px;
            color: #333;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-secondary {
            background: #757575;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .tabs {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .tab-headers {
            display: flex;
            border-bottom: 2px solid #e0e0e0;
            background: #f8f9fa;
        }
        
        .tab-header {
            padding: 15px 25px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 15px;
            font-weight: 500;
            color: #666;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
        }
        
        .tab-header.active {
            background: white;
            color: var(--secondary);
            border-bottom: 3px solid var(--secondary);
            margin-bottom: -2px;
        }
        
        .tab-header:hover {
            background: #e3f2fd;
        }
        
        .tab-content {
            display: none;
            padding: 20px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .info-item {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 3px solid #2196f3;
        }
        
        .info-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 15px;
            font-weight: 500;
            color: #333;
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
        .receipt-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            padding: 40px;
            border: 2px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .receipt-header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .receipt-logo {
            font-size: 32px;
            font-weight: bold;
            color: var(--secondary);
            margin-bottom: 10px;
        }
        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed #ddd;
        }
        .receipt-row.total {
            border-top: 2px solid #333;
            border-bottom: 2px solid #333;
            margin-top: 20px;
            font-size: 18px;
            font-weight: bold;
        }
        .receipt-footer {
            margin-top: 30px;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        .print-btn {
            background: var(--secondary);
            color: white;
            padding: 10px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        .print-btn:hover {
            background: var(--primary);
        }
        @media print {
            body * {
                visibility: hidden;
            }
            .receipt-container, .receipt-container * {
                visibility: visible;
            }
            .receipt-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .print-btn {
                display: none;
            }
        }
        .amount-big {
            font-size: 28px;
            color: #2e7d32;
            font-weight: bold;
        }
        .mpesa-code {
            font-family: 'Courier New', monospace;
            background: #f0f0f0;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 16px;
            color: #333;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table thead {
            background: #f8f9fa;
        }
        
        table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
        }
        
        table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        table tbody tr:hover {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- Payment Header -->
        <div class="payment-header">
            <div class="payment-header-top">
                <div class="payment-info-left">
                    <h2>Payment #<?= $payment['id'] ?> Details</h2>
                    <p style="color: #666; margin: 0;">Transaction information and receipt</p>
                </div>
                <div class="action-buttons">
                    <a href="payments_manage.php" class="btn btn-secondary">← Back to Payments</a>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab-headers">
                <a href="?id=<?= $payment_id ?>&tab=general" class="tab-header <?= $activeTab === 'general' ? 'active' : '' ?>">
                    📋 General Info
                </a>
                <a href="?id=<?= $payment_id ?>&tab=user" class="tab-header <?= $activeTab === 'user' ? 'active' : '' ?>">
                    👤 User Details
                </a>
                <a href="?id=<?= $payment_id ?>&tab=history" class="tab-header <?= $activeTab === 'history' ? 'active' : '' ?>">
                    📊 Payment History
                </a>
                <a href="?id=<?= $payment_id ?>&tab=receipt" class="tab-header <?= $activeTab === 'receipt' ? 'active' : '' ?>">
                    🧾 Receipt
                </a>
            </div>

            <!-- General Info Tab -->
            <div class="tab-content <?= $activeTab === 'general' ? 'active' : '' ?>">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Payment ID</div>
                        <div class="info-value">#<?= htmlspecialchars($payment['id']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Amount</div>
                        <div class="info-value amount-big">Ksh <?= number_format($payment['amount'], 2) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <?php if ($payment['status'] === 'success'): ?>
                                <span class="badge-success">Success</span>
                            <?php elseif ($payment['status'] === 'pending'): ?>
                                <span class="badge-pending">Pending</span>
                            <?php else: ?>
                                <span class="badge-failed">Failed</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">M-Pesa Receipt</div>
                        <div class="info-value">
                            <?php if ($payment['mpesa_receipt']): ?>
                                <span class="mpesa-code"><?= htmlspecialchars($payment['mpesa_receipt']) ?></span>
                            <?php else: ?>
                                <span style="color: #999;">Not Available</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone Number</div>
                        <div class="info-value"><?= htmlspecialchars($payment['phone']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Transaction Time</div>
                        <div class="info-value">
                            <?php if ($payment['transaction_time']): ?>
                                <?= date('F d, Y H:i:s', strtotime($payment['transaction_time'])) ?>
                            <?php else: ?>
                                <span style="color: #999;">Pending</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Created At</div>
                        <div class="info-value"><?= date('F d, Y H:i:s', strtotime($payment['created_at'])) ?></div>
                    </div>
                </div>

                <?php if ($payment['plan_title']): ?>
                <h3 style="margin-top: 30px; margin-bottom: 15px;">Plan Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Plan Name</div>
                        <div class="info-value"><?= htmlspecialchars($payment['plan_title']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Plan Price</div>
                        <div class="info-value">Ksh <?= number_format($payment['plan_price'], 2) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Duration</div>
                        <div class="info-value"><?= htmlspecialchars($payment['plan_duration']) ?> minutes</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Description</div>
                        <div class="info-value"><?= htmlspecialchars($payment['plan_description'] ?? 'N/A') ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($voucher): ?>
                <h3 style="margin-top: 30px; margin-bottom: 15px;">Voucher Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Username</div>
                        <div class="info-value" style="font-family: monospace; font-weight: 600;"><?= htmlspecialchars($voucher['username']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Plan Type</div>
                        <div class="info-value"><?= htmlspecialchars($voucher['plan_type']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Rate Limit</div>
                        <div class="info-value"><?= htmlspecialchars($voucher['rate_limit'] ?? 'N/A') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Expiry</div>
                        <div class="info-value"><?= date('F d, Y H:i:s', strtotime($voucher['expiry'])) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <?php if ($voucher['status'] === 'active' && strtotime($voucher['expiry']) > time()): ?>
                                <span class="badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge-failed">Expired</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- User Details Tab -->
            <div class="tab-content <?= $activeTab === 'user' ? 'active' : '' ?>">
                <?php if ($payment['user_name']): ?>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Name</div>
                        <div class="info-value"><?= htmlspecialchars($payment['user_name']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?= htmlspecialchars($payment['user_email'] ?? 'N/A') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone</div>
                        <div class="info-value"><?= htmlspecialchars($payment['user_phone']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Account Status</div>
                        <div class="info-value">
                            <?php if ($payment['user_status'] === 'active'): ?>
                                <span class="badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge-failed">Inactive</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">User ID</div>
                        <div class="info-value">#<?= htmlspecialchars($payment['user_id']) ?></div>
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <a href="user_detail.php?id=<?= $payment['user_id'] ?>" class="btn btn-primary" style="background: var(--secondary); color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-block;">
                        View Full User Profile →
                    </a>
                </div>
                <?php else: ?>
                <div style="text-align: center; color: #999; padding: 40px;">
                    No user account associated with this payment
                </div>
                <?php endif; ?>
            </div>

            <!-- Payment History Tab -->
            <div class="tab-content <?= $activeTab === 'history' ? 'active' : '' ?>">
                <h3 style="margin-bottom: 20px;">Payment History for <?= htmlspecialchars($payment['phone']) ?></h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>M-Pesa Code</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentHistory as $hist): ?>
                        <tr style="<?= $hist['id'] == $payment_id ? 'background: #e3f2fd;' : '' ?>">
                            <td><?= htmlspecialchars($hist['id']) ?></td>
                            <td><?= htmlspecialchars($hist['plan_title'] ?? 'N/A') ?></td>
                            <td>Ksh <?= number_format($hist['amount'], 2) ?></td>
                            <td>
                                <?php if ($hist['mpesa_receipt']): ?>
                                    <span class="mpesa-code"><?= htmlspecialchars($hist['mpesa_receipt']) ?></span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($hist['status'] === 'success'): ?>
                                    <span class="badge-success">Success</span>
                                <?php elseif ($hist['status'] === 'pending'): ?>
                                    <span class="badge-pending">Pending</span>
                                <?php else: ?>
                                    <span class="badge-failed">Failed</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($hist['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Receipt Tab -->
            <div class="tab-content <?= $activeTab === 'receipt' ? 'active' : '' ?>">
                <div class="receipt-container" id="receipt">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>M-Pesa Code</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentHistory as $hist): ?>
                        <tr style="<?= $hist['id'] == $payment_id ? 'background: #e3f2fd;' : '' ?>">
                            <td><?= htmlspecialchars($hist['id']) ?></td>
                            <td><?= htmlspecialchars($hist['plan_title'] ?? 'N/A') ?></td>
                            <td>Ksh <?= number_format($hist['amount'], 2) ?></td>
                            <td>
                                <?php if ($hist['mpesa_receipt']): ?>
                                    <span class="mpesa-code"><?= htmlspecialchars($hist['mpesa_receipt']) ?></span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($hist['status'] === 'success'): ?>
                                    <span class="badge-success">Success</span>
                                <?php elseif ($hist['status'] === 'pending'): ?>
                                    <span class="badge-pending">Pending</span>
                                <?php else: ?>
                                    <span class="badge-failed">Failed</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($hist['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Receipt Tab -->
        <div class="tab-content <?= $activeTab === 'receipt' ? 'active' : '' ?>">
            <div class="receipt-container" id="receipt">
                <div class="receipt-header">
                    <div class="receipt-logo">🌐 LeoKonnect</div>
                    <h3 style="margin: 5px 0; color: #666;">Payment Receipt</h3>
                    <p style="margin: 5px 0; color: #999; font-size: 14px;">
                        Receipt #<?= str_pad($payment['id'], 6, '0', STR_PAD_LEFT) ?>
                    </p>
                </div>

                <div class="receipt-row">
                    <span>Date:</span>
                    <span><?= date('F d, Y', strtotime($payment['created_at'])) ?></span>
                </div>
                <div class="receipt-row">
                    <span>Time:</span>
                    <span>
                        <?php if ($payment['transaction_time']): ?>
                            <?= date('H:i:s', strtotime($payment['transaction_time'])) ?>
                        <?php else: ?>
                            <?= date('H:i:s', strtotime($payment['created_at'])) ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="receipt-row">
                    <span>Phone Number:</span>
                    <span><?= htmlspecialchars($payment['phone']) ?></span>
                </div>
                <?php if ($payment['user_name']): ?>
                <div class="receipt-row">
                    <span>Customer Name:</span>
                    <span><?= htmlspecialchars($payment['user_name']) ?></span>
                </div>
                <?php endif; ?>
                <div class="receipt-row">
                    <span>Plan:</span>
                    <span><?= htmlspecialchars($payment['plan_title'] ?? 'N/A') ?></span>
                </div>
                <?php if ($payment['mpesa_receipt']): ?>
                <div class="receipt-row">
                    <span>M-Pesa Code:</span>
                    <span><?= htmlspecialchars($payment['mpesa_receipt']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($voucher): ?>
                <div class="receipt-row">
                    <span>Username:</span>
                    <span style="font-family: monospace; font-weight: 600;"><?= htmlspecialchars($voucher['username']) ?></span>
                </div>
                <div class="receipt-row">
                    <span>Expiry:</span>
                    <span><?= date('M d, Y H:i', strtotime($voucher['expiry'])) ?></span>
                </div>
                <?php endif; ?>

                <div class="receipt-row total">
                    <span>Total Amount Paid:</span>
                    <span>Ksh <?= number_format($payment['amount'], 2) ?></span>
                </div>

                <div class="receipt-footer">
                    <p style="margin: 10px 0;">Thank you for your payment!</p>
                    <p style="margin: 5px 0;">For support, contact us at support@leokonnect.com</p>
                    <p style="margin: 5px 0;">This is a computer-generated receipt</p>
                </div>
            </div>

            <div style="text-align: center;">
                <button onclick="window.print()" class="print-btn" style="background: var(--secondary); color: white; padding: 10px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-top: 20px;">🖨️ Print Receipt</button>
                <button onclick="downloadReceipt()" class="print-btn" style="background: #17a2b8; color: white; padding: 10px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-top: 20px;">📥 Download PDF</button>
            </div>
            </div>
        </div>
    </div>

    <script>
        function downloadReceipt() {
            // For a simple implementation, we'll just trigger print
            // In production, you might want to use a library like jsPDF
            window.print();
        }
    </script>
</body>
</html>
            window.print();
        }
    </script>
</body>
</html>
