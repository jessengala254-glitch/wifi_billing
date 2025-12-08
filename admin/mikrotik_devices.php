<?php
// mikrotik_devices.php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/routeros_api.class.php';

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
    $canConnect = false;

    if ($online) {
        $API = new RouterosAPI();
        $canConnect = $API->connect($n['nasname'], $config['mikrotik']['user'], $config['mikrotik']['pass'], $config['mikrotik']['port']);
        
        if ($canConnect) {
            $sys = $API->comm('/system/resource/print');

            if (isset($sys[0])) {
                // CPU load (can be 0 if router is idle)
                if (isset($sys[0]['cpu-load'])) {
                    $cpu = (int)$sys[0]['cpu-load'];
                }

                // Memory - calculate percentage used
                if (isset($sys[0]['total-memory']) && isset($sys[0]['free-memory'])) {
                    $totalMem = $sys[0]['total-memory'];
                    $freeMem = $sys[0]['free-memory'];
                    $usedMem = $totalMem - $freeMem;
                    $mem = round(($usedMem / $totalMem) * 100, 2);
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
        'online' => $online ? ($canConnect ? 'Online' : 'Auth Failed') : 'Offline',
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
            <td><span class="ip-badge"><?= htmlspecialchars($r['ip']) ?></span></td>
            <td><span class="board-badge"><?= htmlspecialchars($r['name']) ?></span></td>
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
            <td>
                <span class="status-badge <?= $r['online']=='Online'?'status-active':'status-expired' ?>"><?= $r['online'] ?></span>
            </td>
            <td class="remote-link"><a href="<?= $r['winbox'] ?>">Winbox</a></td>
            <td><?= htmlspecialchars($r['secret']) ?></td>
            <td>
                <a href="edit_router.php?id=<?= $r['id'] ?>" class="btn-sm edit">Edit</a>
                <a href="delete_router.php?id=<?= $r['id'] ?>" 
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
