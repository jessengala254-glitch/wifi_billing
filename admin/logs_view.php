<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/functions.php';
session_start();
$pdo = db();
$pdo->exec("SET time_zone = '+03:00'");

/* --- Search --- */
$search = $_GET['search'] ?? '';

/* --- Pagination --- */
$limit = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

/* --- Build WHERE clause --- */
$where = "";
$params = [];

if (!empty($search)) {
    $where = "WHERE 
        l.action LIKE :search OR 
        l.description LIKE :search OR 
        l.ip_address LIKE :search OR 
        u.name LIKE :search OR 
        u.role LIKE :search";
    $params[':search'] = "%$search%";
}

/* --- Fetch logs with pagination --- */
$sql = "
    SELECT l.*, u.name AS username, u.role
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    $where
    ORDER BY l.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

/* --- Count total logs for pagination --- */
$countSql = "
    SELECT COUNT(*) 
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    $where
";

$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val, PDO::PARAM_STR);
}
$countStmt->execute();
$totalLogs = $countStmt->fetchColumn();

$totalPages = ceil($totalLogs / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Admin</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .search-bar {
            width: 100%;
            display: flex;
            justify-content: flex-end; /* right side */
            margin-bottom: 10px;
        }

        .search-bar form {
            margin: 0;
        }

        .search-bar input {
            width: 260px;
            padding: 8px 12px;
            border-radius: 5px;
            border: 1px solid #ccc;
            background: #fff;
            font-size: 14px;
            padding: 8px 35px 8px 10px;
            background: url('https://cdn-icons-png.flaticon.com/512/622/622669.png') no-repeat right 10px center;
            background-size: 16px;
        }

    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="container">    
    <h1>System Activity Logs</h1>

    <div class="outer-board">
        <!-- Search Bar -->
        <div class="search-bar">
            <form method="GET">
                <input type="text" name="search" placeholder="Search logs..." value="<?=htmlspecialchars($search)?>">
            </form>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="6" style="text-align:center;">No logs found</td></tr>
            <?php endif; ?>

            <?php foreach ($logs as $log): ?>
                <tr>
                    <td data-label="Date"><?=htmlspecialchars($log['created_at'])?></td>
                    <td data-label="Name"><?=htmlspecialchars($log['username'] ?? 'System')?></td>
                    <td data-label="Role"><?=htmlspecialchars($log['role'] ?? 'N/A')?></td>
                    <td data-label="Action"><?=htmlspecialchars($log['action'])?></td>
                    <td data-label="Description"><?=htmlspecialchars($log['description'])?></td>
                    <td data-label="IP"><?=htmlspecialchars($log['ip_address'])?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    

        <!-- Pagination -->
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?=($page-1)?>&search=<?=urlencode($search)?>">« Prev</a>
            <?php endif; ?>

            <?php
            // Show pages 1-5
            $maxVisible = 5;
            $end = min($totalPages, $maxVisible);
            
            for ($i = 1; $i <= $end; $i++): ?>
                <a href="?page=<?=$i?>&search=<?=urlencode($search)?>" 
                class="<?=($i == $page ? 'active' : '')?>">
                <?=$i?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?=($page+1)?>&search=<?=urlencode($search)?>">Next »</a>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
