<?php
include __DIR__ . '/header.php';
require_once __DIR__ . '/../config/db.php';

$uploadsUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/../uploads/employees/';

// Handle saving official order
$saveMessage = '';
if (isset($_POST['save_official_order'])) {
    $orderNo = trim($_POST['order_no'] ?? '');
    $orderDate = $_POST['order_date'] ?? '';
    $judgeId = (int)($_POST['judge_id'] ?? 0);
    $includeSuper = isset($_POST['include_superintendent']) ? 1 : 0;
    $employeesJson = $_POST['employees_data'] ?? '';

    $decodedEmployees = json_decode($employeesJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $decodedEmployees = [];
    }

    if ($orderNo && $orderDate && $judgeId && !empty($decodedEmployees)) {
        try {
            $check = $pdo->prepare("SELECT id FROM official_transfer_orders WHERE order_no = ?");
            $check->execute([$orderNo]);
            if ($check->fetch()) {
                $stmt = $pdo->prepare("UPDATE official_transfer_orders SET order_date = ?, judge_id = ?, include_superintendent = ?, employees_data = ? WHERE order_no = ?");
                $stmt->execute([$orderDate, $judgeId, $includeSuper, $employeesJson, $orderNo]);
                $saveMessage = '<div class="alert alert-success mt-3">Official Order updated successfully!</div>';
            } else {
                $stmt = $pdo->prepare("INSERT INTO official_transfer_orders (order_no, order_date, judge_id, include_superintendent, employees_data) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$orderNo, $orderDate, $judgeId, $includeSuper, $employeesJson]);
                $saveMessage = '<div class="alert alert-success mt-3">Official Order saved successfully!</div>';
            }
        } catch (Exception $e) {
            $saveMessage = '<div class="alert alert-danger mt-3">Save failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        $saveMessage = '<div class="alert alert-danger mt-3">Please fill all fields and add at least one employee with valid data.</div>';
    }
}

// Handle search
$searchOrderNo = trim($_GET['search_order'] ?? '');
$loadedOrder = null;
if ($searchOrderNo !== '') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM official_transfer_orders WHERE order_no = ? LIMIT 1");
        $stmt->execute([$searchOrderNo]);
        $loadedOrder = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Silent
    }
}

// Fetch employees
$employees = $pdo->query("
    SELECT ed.id, ed.name, ed.pic, p.post_name, c.name AS court_name, ed.court_id
    FROM employee_details ed
    JOIN posts p ON p.id = ed.post_id
    JOIN courts c ON c.id = ed.court_id
    ORDER BY ed.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch courts
$courts = $pdo->query("SELECT id, name FROM courts ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch judicial officers
$officers = $pdo->query("
    SELECT id, name, post
    FROM judicial_officers
    WHERE post LIKE '%District & Sessions Judge%'
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Build employee lookup
$employeeData = [];
foreach ($employees as $emp) {
    $emp['pic_url'] = !empty($emp['pic']) ? ($uploadsUrl . $emp['pic']) : null;
    $employeeData[$emp['id']] = $emp;
}
?>

<div class="container my-4">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">📋 Transfer / Posting Order</h5>
        </div>
        <div class="card-body">
            <!-- Search Saved Order -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <form method="get" class="d-flex gap-2">
                        <input type="hidden" name="page" value="transfer_posting">
                        <input type="text" name="search_order" class="form-control" placeholder="Enter Office Order No. to load" value="<?= htmlspecialchars($searchOrderNo) ?>">
                        <button type="submit" class="btn btn-outline-secondary">Search & Load</button>
                    </form>
                    <?php if ($searchOrderNo && !$loadedOrder): ?>
                        <div class="alert alert-warning mt-2">No saved order found.</div>
                    <?php elseif ($searchOrderNo && $loadedOrder): ?>
                        <div class="alert alert-success mt-2">Order <?= htmlspecialchars($searchOrderNo) ?> loaded!</div>
                    <?php endif; ?>
                </div>
            </div>

            <form method="post" action="?page=save_transfer_posting" id="transferForm">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Transfer / Posting Date</label>
                        <input type="date" name="transfer_date" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Type</label>
                        <select name="transfer_type" class="form-select" required>
                            <option value="Transfer">Transfer</option>
                            <option value="Posting">Posting</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Select Employee</label>
                        <select id="employeeSelect" class="form-select" onchange="previewEmployee()">
                            <option value="">-- Select Employee --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>">
                                    <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['post_name']) ?>, <?= htmlspecialchars($emp['court_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 align-self-end">
                        <button type="button" class="btn btn-outline-primary w-100" onclick="addEmployee()">Add Employee</button>
                    </div>
                </div>

                <div id="employeePreview" class="mb-3 d-none">
                    <div class="card border shadow-sm">
                        <div class="card-body d-flex align-items-center">
                            <img id="previewPic" src="" alt="Photo" class="rounded me-3 d-none" style="width:72px;height:72px;object-fit:cover;">
                            <div>
                                <h6 class="fw-bold mb-1" id="previewName"></h6>
                                <div class="small text-muted">
                                    <span><b>Post:</b> <span id="previewPost"></span></span> |
                                    <span><b>Court:</b> <span id="previewCourt"></span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered text-center" id="transferTable">
                        <thead class="table-light">
                            <tr>
                                <th>Sr. No</th>
                                <th>Employee Name</th>
                                <th>Post</th>
                                <th>Present Court</th>
                                <th>Transferred To</th>
                                <th>Remarks</th>
                                <th>Remove</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-success">Save Posting</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Official Order Section -->
<div class="container my-5" id="officialOrderSection">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h5 class="text-center fw-bold mb-4 text-uppercase">OFFICE OF THE DISTRICT AND SESSIONS JUDGE, JAMSHORO</h5>
            <div class="d-flex justify-content-start align-items-center mb-4">
                <label class="fw-bold me-2">OFFICE ORDER NO.</label>
                <input type="text" id="orderNo" class="form-control w-auto d-inline-block" placeholder="Enter Order No." value="<?= $loadedOrder['order_no'] ?? '' ?>">
            </div>
            <p class="mb-5">
                The transfer and posting orders of the following Ministerial Staff are hereby made in public interest:
            </p>

            <div class="table-responsive mb-5">
                <table class="table table-bordered text-center w-100" id="officialOrderTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:8%">S.No</th>
                            <th style="width:32%">Name of Official</th>
                            <th style="width:20%">From</th>
                            <th style="width:20%">To</th>
                            <th style="width:20%">Remarks</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <p class="mt-5 mb-5">Orders to take immediate effect.</p>

            <div class="row mt-5">
                <div class="col-6 text-start">
                    <label class="form-label fw-bold">Date</label>
                    <input type="date" id="printDate" class="form-control w-auto d-inline-block" value="<?= $loadedOrder['order_date'] ?? '' ?>">
                </div>
                <div class="col-6 text-end">
                    <select id="judgeSelect" class="form-select w-75 d-inline-block text-center mb-3">
                        <?php foreach ($officers as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= ($loadedOrder['judge_id'] ?? '') == $o['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($o['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="fw-bold mb-0">District and Sessions Judge, Jamshoro</p>

                    <div class="form-check text-start mt-3">
                        <input class="form-check-input" type="checkbox" id="showSuperintendent" <?= $loadedOrder && $loadedOrder['include_superintendent'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="showSuperintendent">
                            Include Superintendent signature line in print
                        </label>
                    </div>
                </div>
            </div>

            <!-- Save & Print Buttons -->
            <div class="text-end mt-4">
                <button type="button" class="btn btn-primary me-2" onclick="printOrder()">Print Order</button>

                <!-- Hidden form for saving official order -->
                <form method="post" style="display:inline-block;" id="saveOfficialForm">
                    <input type="hidden" name="save_official_order" value="1">
                    <input type="hidden" name="order_no" id="hidden_order_no">
                    <input type="hidden" name="order_date" id="hidden_order_date">
                    <input type="hidden" name="judge_id" id="hidden_judge_id">
                    <input type="hidden" name="include_superintendent" id="hidden_include_super" value="0">
                    <input type="hidden" name="employees_data" id="hidden_employees_data">
                    <button type="button" class="btn btn-success" onclick="prepareAndSaveOrder()">Save Official Order</button>
                </form>
            </div>

            <?php if ($saveMessage): ?>
                <?= $saveMessage ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const employeeData = <?= json_encode($employeeData) ?>;
const courts = <?= json_encode($courts) ?>;
let rowCount = 0;

// Load saved employees (fixed version)
<?php 
if ($loadedOrder && !empty($loadedOrder['employees_data'])) {
    $saved = json_decode($loadedOrder['employees_data'], true);
    if (is_array($saved)) {
        foreach ($saved as $empId => $data) {
            $empId = (int)$empId;
            if (isset($employeeData[$empId])) {
                $to = htmlspecialchars(addslashes($data['to'] ?? ''), ENT_QUOTES);
                $remarks = htmlspecialchars(addslashes($data['remarks'] ?? ''), ENT_QUOTES);
                echo "addSavedEmployee($empId, '$to', '$remarks');\n";
            }
        }
    }
}
?>

function addSavedEmployee(empId, toCourtId, remarks) {
    if (document.getElementById('row-' + empId)) return;
    const emp = employeeData[empId];
    if (!emp) return;

    rowCount++;

    const tableBody = document.querySelector("#transferTable tbody");
    const orderBody = document.querySelector("#officialOrderTable tbody");

    let courtOptions = `<option value="">-- Select Court --</option>`;
    courts.forEach(c => {
        const selected = c.id == toCourtId ? 'selected' : '';
        courtOptions += `<option value="${c.id}" ${selected}>${escapeHtml(c.name)}</option>`;
    });

    // Editing table row
    const row = document.createElement("tr");
    row.id = "row-" + empId;
    row.innerHTML = `
        <td class="srno"></td>
        <td class="text-start">${escapeHtml(emp.name)}</td>
        <td>${escapeHtml(emp.post_name)}</td>
        <td>${escapeHtml(emp.court_name)}</td>
        <td>
            <select name="transfer_to[${emp.id}]" class="form-select form-select-sm" onchange="syncToOrder('${empId}')">
                ${courtOptions}
            </select>
        </td>
        <td>
            <input type="text" name="remarks[${emp.id}]" class="form-control form-control-sm" value="${escapeHtml(remarks)}" oninput="syncToOrder('${empId}')">
        </td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="removeRow('${empId}')">Remove</button></td>
    `;
    tableBody.appendChild(row);

    // Printable Official Order row
    const orderRow = document.createElement("tr");
    orderRow.id = "order-" + empId;
    const toCourtName = courts.find(c => c.id == toCourtId)?.name || "--";
    const remarkText = remarks ? escapeHtml(remarks) : "--";

    orderRow.innerHTML = `
        <td class="srno"></td>
        <td class="text-start">
            <strong>${escapeHtml(emp.name)}</strong>
            <div class="small text-muted">${escapeHtml(emp.post_name)}</div>
        </td>
        <td>${escapeHtml(emp.court_name)}</td>
        <td class="order-to">${toCourtName}</td>
        <td class="order-remarks">${remarkText}</td>
    `;
    orderBody.appendChild(orderRow);

    syncToOrder(empId);
    renumberTables();
}

function previewEmployee() {
    const select = document.getElementById('employeeSelect');
    const empId = select.value;
    const preview = document.getElementById('employeePreview');
    const picEl = document.getElementById('previewPic');

    if (!empId || !employeeData[empId]) {
        preview.classList.add('d-none');
        return;
    }

    const emp = employeeData[empId];
    document.getElementById('previewName').textContent = emp.name;
    document.getElementById('previewPost').textContent = emp.post_name;
    document.getElementById('previewCourt').textContent = emp.court_name;

    if (emp.pic_url) {
        picEl.src = emp.pic_url;
        picEl.classList.remove('d-none');
    } else {
        picEl.classList.add('d-none');
    }
    preview.classList.remove('d-none');
}

function addEmployee() {
    const empId = document.getElementById('employeeSelect').value;
    if (!empId || !employeeData[empId]) return;
    if (document.getElementById('row-' + empId)) {
        alert("This employee is already added.");
        return;
    }

    rowCount++;
    const emp = employeeData[empId];

    const tableBody = document.querySelector("#transferTable tbody");
    const orderBody = document.querySelector("#officialOrderTable tbody");

    let courtOptions = `<option value="">-- Select Court --</option>`;
    courts.forEach(c => {
        courtOptions += `<option value="${c.id}">${escapeHtml(c.name)}</option>`;
    });

    const row = document.createElement("tr");
    row.id = "row-" + empId;
    row.innerHTML = `
        <td class="srno"></td>
        <td class="text-start">${escapeHtml(emp.name)}</td>
        <td>${escapeHtml(emp.post_name)}</td>
        <td>${escapeHtml(emp.court_name)}</td>
        <td>
            <select name="transfer_to[${emp.id}]" class="form-select form-select-sm" onchange="syncToOrder('${empId}')">
                ${courtOptions}
            </select>
        </td>
        <td>
            <input type="text" name="remarks[${emp.id}]" class="form-control form-control-sm" placeholder="Remarks" oninput="syncToOrder('${empId}')">
        </td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="removeRow('${empId}')">Remove</button></td>
    `;
    tableBody.appendChild(row);

    const orderRow = document.createElement("tr");
    orderRow.id = "order-" + empId;
    orderRow.innerHTML = `
        <td class="srno"></td>
        <td class="text-start">
            <div><strong>${escapeHtml(emp.name)}</strong></div>
            <div class="small text-muted">${escapeHtml(emp.post_name)}</div>
        </td>
        <td>${escapeHtml(emp.court_name)}</td>
        <td class="order-to">--</td>
        <td class="order-remarks">--</td>
    `;
    orderBody.appendChild(orderRow);

    renumberTables();
    document.getElementById('employeeSelect').value = "";
    document.getElementById('employeePreview').classList.add('d-none');
}

function syncToOrder(empId) {
    const orderRow = document.getElementById("order-" + empId);
    if (!orderRow) return;

    const transferSelect = document.querySelector(`#row-${empId} select`);
    const remarksInput = document.querySelector(`#row-${empId} input`);

    const courtName = transferSelect?.value
        ? courts.find(c => c.id == transferSelect.value)?.name || "--"
        : "--";

    const remarks = remarksInput?.value.trim() ? escapeHtml(remarksInput.value) : "--";

    orderRow.querySelector(".order-to").textContent = courtName;
    orderRow.querySelector(".order-remarks").textContent = remarks;
}

function removeRow(empId) {
    document.getElementById("row-" + empId)?.remove();
    document.getElementById("order-" + empId)?.remove();
    renumberTables();
}

function renumberTables() {
    document.querySelectorAll("#transferTable tbody tr, #officialOrderTable tbody tr").forEach((tr, i) => {
        tr.querySelector(".srno").textContent = i + 1;
    });
}

function escapeHtml(str) {
    return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function printOrder() {
    const section = document.getElementById("officialOrderSection").cloneNode(true);

    const orderNo = document.getElementById("orderNo").value || "________";
    section.querySelector("#orderNo").outerHTML = `<span class="fw-bold">${escapeHtml(orderNo)}</span>`;

    const printDate = document.getElementById("printDate").value || "________";
    section.querySelector("#printDate").outerHTML = `<span class="fw-bold">${escapeHtml(printDate)}</span>`;

    const judgeName = document.getElementById("judgeSelect").selectedOptions[0]?.text || "";
    section.querySelector("#judgeSelect").outerHTML = `<span class="fw-bold">${escapeHtml(judgeName)}</span>`;

    if (document.getElementById("showSuperintendent").checked) {
        const judgeBlock = section.querySelector(".text-end");
        const superBlock = document.createElement("div");
        superBlock.className = "mt-5 text-end";
        superBlock.innerHTML = `<p class="fw-bold mb-0">Superintendent</p><p class="mb-0">District & Sessions Court Jamshoro</p>`;
        judgeBlock.parentNode.appendChild(superBlock);
    }

    section.querySelectorAll('.no-print, button, input, select, .form-check').forEach(el => el.remove());

    const printWin = window.open("", "_blank");
    printWin.document.write(`
        <html>
            <head>
                <title>Transfer Order</title>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
            </head>
            <body onload="window.print(); window.close();">
                ${section.innerHTML}
            </body>
        </html>
    `);
    printWin.document.close();
}

function prepareAndSaveOrder() {
    const orderNo = document.getElementById("orderNo").value.trim();
    if (!orderNo) {
        alert("Please enter Office Order No.");
        return;
    }

    const orderDate = document.getElementById("printDate").value;
    if (!orderDate) {
        alert("Please select order date.");
        return;
    }

    const employees = {};
    document.querySelectorAll("#transferTable tbody tr").forEach(tr => {
        const empId = tr.id.replace("row-", "");
        const toSelect = tr.querySelector("select");
        const remarksInput = tr.querySelector("input[type=text]");
        if (toSelect && remarksInput) {
            employees[empId] = {
                to: toSelect.value,
                remarks: remarksInput.value
            };
        }
    });

    if (Object.keys(employees).length === 0) {
        alert("Please add at least one employee.");
        return;
    }

    document.getElementById("hidden_order_no").value = orderNo;
    document.getElementById("hidden_order_date").value = orderDate;
    document.getElementById("hidden_judge_id").value = document.getElementById("judgeSelect").value;
    document.getElementById("hidden_include_super").value = document.getElementById("showSuperintendent").checked ? "1" : "0";
    document.getElementById("hidden_employees_data").value = JSON.stringify(employees);

    document.getElementById("saveOfficialForm").submit();
}
</script>
<!-- Saved Official Orders History Section -->
<div class="container my-5">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">📜 Saved Official Transfer Orders History</h5>
        </div>
        <div class="card-body">
            <?php
            // Pagination settings
            $perPage = 10;
            $page = isset($_GET['history_page']) ? max(1, (int)$_GET['history_page']) : 1;
            $offset = ($page - 1) * $perPage;

            // Search
            $search = trim($_GET['search_history'] ?? '');
            $where = '';
            $params = [];
            if ($search !== '') {
                $where = "WHERE order_no LIKE ?";
                $params[] = '%' . $search . '%';
            }

            // Count total
            $countSql = "SELECT COUNT(*) FROM official_transfer_orders $where";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $totalRows = $countStmt->fetchColumn();
            $totalPages = max(1, ceil($totalRows / $perPage));

            // Fetch orders
            $sql = "SELECT o.*, j.name AS judge_name 
                    FROM official_transfer_orders o
                    LEFT JOIN judicial_officers j ON j.id = o.judge_id
                    $where
                    ORDER BY o.order_date DESC, o.id DESC
                    LIMIT $perPage OFFSET $offset";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <!-- Search Form -->
            <form method="get" class="mb-4">
                <input type="hidden" name="page" value="transfer_posting">
                <div class="row g-3 align-items-center">
                    <div class="col-auto">
                        <label class="form-label fw-bold">Search Order No:</label>
                    </div>
                    <div class="col-auto">
                        <input type="text" name="search_history" class="form-control" placeholder="e.g. 300" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <?php if ($search !== ''): ?>
                            <a href="?page=transfer_posting" class="btn btn-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- Latest 5 Orders Summary -->
            <?php if (empty($search)): ?>
                <?php
                $latestStmt = $pdo->query("
                    SELECT order_no, order_date, judge_id, 
                           (SELECT name FROM judicial_officers WHERE id = judge_id) AS judge_name,
                           employees_data
                    FROM official_transfer_orders 
                    ORDER BY order_date DESC, id DESC 
                    LIMIT 5
                ");
                $latestOrders = $latestStmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <?php if (!empty($latestOrders)): ?>
                    <div class="alert alert-info mb-4">
                        <strong>🕒 Latest 5 Official Orders:</strong>
                        <div class="mt-2">
                            <?php foreach ($latestOrders as $i => $ord): 
                                $empCount = count(json_decode($ord['employees_data'] ?? '[]', true));
                            ?>
                                <div class="d-inline-block me-4">
                                    <strong><?= htmlspecialchars($ord['order_no']) ?></strong> 
                                    (<?= date('d-m-Y', strtotime($ord['order_date'])) ?>) 
                                    - <?= htmlspecialchars($ord['judge_name'] ?? 'N/A') ?>
                                    <span class="badge bg-secondary"><?= $empCount ?> employee<?= $empCount != 1 ? 's' : '' ?></span>
                                </div>
                                <?php if ($i < 4 && $i < count($latestOrders)-1): ?><span class="text-muted">|</span><?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Orders Table -->
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Order No.</th>
                            <th>Date</th>
                            <th>Judge</th>
                            <th>Employees</th>
                            <th>Include Sup.</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No official orders found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $index => $order): 
                                $empData = json_decode($order['employees_data'] ?? '[]', true);
                                $empCount = is_array($empData) ? count($empData) : 0;
                            ?>
                                <tr>
                                    <td><?= $offset + $index + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($order['order_no']) ?></strong></td>
                                    <td><?= date('d-m-Y', strtotime($order['order_date'])) ?></td>
                                    <td><?= htmlspecialchars($order['judge_name'] ?? 'N/A') ?></td>
                                    <td><span class="badge bg-primary"><?= $empCount ?> employee<?= $empCount != 1 ? 's' : '' ?></span></td>
                                    <td><?= $order['include_superintendent'] ? 'Yes' : 'No' ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-success" onclick="loadOrder('<?= htmlspecialchars($order['order_no']) ?>')">
                                            Load
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteOrder(<?= $order['id'] ?>, '<?= htmlspecialchars($order['order_no']) ?>')">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Orders pagination">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=transfer_posting&history_page=<?= $page-1 ?>&search_history=<?= urlencode($search) ?>">Previous</a>
                        </li>
                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=transfer_posting&history_page=<?= $i ?>&search_history=<?= urlencode($search) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=transfer_posting&history_page=<?= $page+1 ?>&search_history=<?= urlencode($search) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Load order by searching and redirecting
function loadOrder(orderNo) {
    if (confirm(`Load Official Order No. ${orderNo}? This will replace current data.`)) {
        window.location.href = '?page=transfer_posting&search_order=' + encodeURIComponent(orderNo);
    }
}

// Delete order
function deleteOrder(id, orderNo) {
    if (confirm(`Permanently delete Official Order No. ${orderNo}? This cannot be undone.`)) {
        fetch('', {  // Same page POST
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'delete_order=1&order_id=' + id
        }).then(() => location.reload());
    }
}
</script>

<?php
// Handle delete request
if (isset($_POST['delete_order']) && isset($_POST['order_id'])) {
    $id = (int)$_POST['order_id'];
    try {
        $pdo->prepare("DELETE FROM official_transfer_orders WHERE id = ?")->execute([$id]);
        echo "<script>alert('Order deleted successfully!'); window.location.href = '?page=transfer_posting';</script>";
        exit;
    } catch (Exception $e) {
        echo "<script>alert('Delete failed.');</script>";
    }
}
?>
<?php include __DIR__ . '/footer.php'; ?>