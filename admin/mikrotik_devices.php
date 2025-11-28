<?php
// mikrotik_devices.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/mikrotik.php';

// Load DB
$config = require __DIR__ . '/../inc/config.php';
$pdo = db($config['db']); // Ensure db() accepts config array

// Fetch NAS devices (all routers)
$stmt = $pdo->query("SELECT * FROM nas ORDER BY nasname ASC");
$nas_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper: ping check
function ping($host, $timeout = 1) {
    $pingresult = exec("ping -c 1 -W $timeout " . escapeshellarg($host), $output, $status);
    return $status === 0;
}

// Fetch live router stats
$routers = [];
foreach ($nas_list as $n) {
    $online = ping($n['nasname']);
    $cpu = $mem = 0;

    if ($online) {
        $API = new RouterosAPI();
        if ($API->connect($n['nasname'], $config['mikrotik']['user'], $config['mikrotik']['pass'], $config['mikrotik']['port'])) {
            $sys = $API->comm('/system/resource/print');

            if (isset($sys[0])) {
                // CPU
                if (isset($sys[0]['cpu-load'])) {
                    $cpu = $sys[0]['cpu-load'];
                } elseif (isset($sys[0]['cpu'])) {
                    $cpu = $sys[0]['cpu'];
                }

                // Free memory in MB
                if (isset($sys[0]['free-memory'])) {
                    $mem = round($sys[0]['free-memory'] / 1024 / 1024, 2);
                } elseif (isset($sys[0]['free-memory-mb'])) {
                    $mem = $sys[0]['free-memory-mb'];
                }
            }

            $API->disconnect();
        }
    }

    $routers[] = [ 
        'id' => $n['id'],
        'ip' => $n['nasname'], 
        'name' => $n['shortname'],
        'description' => $n['description'],
        'provisioning' => $n['type'], 
        'winbox' => 'winbox://'.$n['nasname'],
        'secret' => $n['secret'], 
        'online' => $online ? 'Online' : 'Offline',
        'cpu' => $cpu,
        'memory' => $mem
    ];
}

?>

<!DOCTYPE html>

<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="admin_style.css">
<title>MikroTik Routers Dashboard</title>
<style>
.outer-board { max-width:1200px; margin:auto; background:#fff; border-radius:10px; padding:20px; margin-bottom: 30px; box-shadow:0 2px 10px rgba(0,0,0,0.1);}
.search-bar { margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;}
.search-bar input { width:300px; padding:5px 10px; border-radius:5px; border:1px solid #ccc;}

.status-online { color: green; font-weight:bold; }
.status-offline { color: red; font-weight:bold; }
.cpu-bar, .memory-bar { height:10px; border-radius:5px; background:#e0e0e0; position:relative; width:100px; display:inline-block; margin-right:5px; }
.cpu-fill, .memory-fill { height:100%; border-radius:5px; background:#4caf50; position:absolute; top:0; left:0; }
.remote-link a { color:#2196f3; text-decoration:none; font-weight:bold; }
.add-btn { padding:8px 15px; background:#4caf50; color:#fff; border:none; border-radius:5px; cursor:pointer; margin-bottom:10px; } 
.btn-sm {
    padding: 5px 10px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 0.85em;
    margin-right: 5px;
    display: inline-block;
}

.btn-sm.edit {
    background-color: #4caf50;
    color: #fff;
}

.btn-sm.delete {
    background-color: #f44336;
    color: #fff;
}

</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<h1>MikroTik Routers Dashboard</h1>

<!-- Live Routers Table -->
<div class="outer-board">
    <div class="search-bar">
        <input type="text" placeholder="Search router..." id="searchInputLive">
        <button class="add-btn" onclick="window.location.href='add_router.php'">Add New Router</button>
    </div>
    <table id="routersTable">
    <thead>
        <tr>
            <th>IP Address</th>
            <th>Board Name</th>
            <th>Provisioning</th>
            <th>CPU</th>
            <th>Memory</th>
            <th>Status</th>
            <th>Remote Winbox</th>
            <th>Secret</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach($routers as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['ip']) ?></td>
            <td><?= htmlspecialchars($r['name']) ?></td>
            <td><?= htmlspecialchars($r['provisioning'] ?? 'N/A') ?></td>
            <td>
                <div class="cpu-bar">
                    <div class="cpu-fill" style="width:<?= $r['cpu'] ?>%;background:<?= $r['cpu']>80?'red':'#4caf50' ?>"></div>
                </div>
                <?= $r['cpu'] ?>%
            </td>
            <td>
                <div class="memory-bar">
                    <div class="memory-fill" style="width:<?= $r['memory'] ?>%;background:<?= $r['memory']>80?'red':'#4caf50' ?>"></div>
                </div>
                <?= $r['memory'] ?> MB
            </td>
            <td class="<?= $r['online']=='Online'?'status-online':'status-offline' ?>"><?= $r['online'] ?></td>
            <td class="remote-link"><a href="<?= $r['winbox'] ?>">Winbox</a></td>
            <td><?= htmlspecialchars($r['secret']) ?></td>
            <td>
                <a href="edit_router.php?id=<?= $r['id'] ?>" class="btn-sm edit">Edit</a>
                <!-- <a href="delete_router.php?id=<?= $r['id'] ?>" class="btn-sm delete" onclick="return confirm('Delete this router?');">Delete</a> -->
                 <a href="delete_router.php?id=<?= $row['id']; ?>" 
                    onclick="return confirm('Are you sure you want to delete this router?');"
                    class="delete-btn">Delete</a>

            </td>

        </tr>
    <?php endforeach; ?>
    </tbody>
</table>


<div class="pagination" id="paginationLive"></div>

</div>

<script>
let routersData = <?= json_encode($routers) ?>;
const rowsPerPage = 5;
let currentPage = 1;

function displayTable() {
    const tbody = document.querySelector('#routersTable tbody');
    tbody.innerHTML = '';
    const searchFilter = document.getElementById('searchInputLive').value.toLowerCase();

    const filtered = routersData.filter(r => r.name.toLowerCase().includes(searchFilter));
    const start = (currentPage - 1) * rowsPerPage;
    const pageData = filtered.slice(start, start + rowsPerPage);

    pageData.forEach(r => {
        const row = document.createElement('tr');
        const cpuColor = r.cpu > 80 ? 'red' : '#4caf50';
        const memColor = r.memory > 80 ? 'red' : '#4caf50';
        row.innerHTML = `
            <td>${r.ip}</td> 
            <td>${r.name}</td>
            <td>${r.provisioning}</td>
            <td><div class="cpu-bar"><div class="cpu-fill" style="width:${r.cpu}%;background:${cpuColor}"></div></div> ${r.cpu}%</td>
            <td><div class="memory-bar"><div class="memory-fill" style="width:${r.memory}%;background:${memColor}"></div></div> ${r.memory} MB</td>
            <td class="${r.online === 'Online' ? 'status-online' : 'status-offline'}">${r.online}</td>
            <td class="remote-link"><a href="${r.winbox}">Winbox</a></td>
            <td>${r.secret}</td>
            <td>
                <a href="edit_router.php?id=${r.id}">Edit</a> |
                <a href="delete_router.php?id=${r.id}" onclick="return confirm('Delete router?')">Delete</a>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function setupPagination() {
    const pagination = document.getElementById('paginationLive');
    pagination.innerHTML = '';
    const searchFilter = document.getElementById('searchInputLive').value.toLowerCase();
    const filtered = routersData.filter(r => r.name.toLowerCase().includes(searchFilter));
    const totalPages = Math.ceil(filtered.length / rowsPerPage);

    for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement('button');
        btn.textContent = i;
        if (i === currentPage) btn.classList.add('active');
        btn.addEventListener('click', () => { currentPage = i; displayTable(); setupPagination(); });
        pagination.appendChild(btn);
    }
}

document.getElementById('searchInputLive').addEventListener('keyup', () => {
    currentPage = 1;
    displayTable();
    setupPagination();
});

// Initial table render
displayTable();
setupPagination();
</script>

</body>
</html>
