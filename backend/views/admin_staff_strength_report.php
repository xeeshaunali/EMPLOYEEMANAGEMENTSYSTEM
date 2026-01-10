<?php 
include __DIR__ . '/header.php';

$reportDate = $_GET['date'] ?? date('Y-m-d');

// Handle footer save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_footer'])) {
    $admn_no = trim($_POST['admn_no'] ?? '');
    $report_date_text = trim($_POST['report_date_text'] ?? '');
    $judge_id = $_POST['judge_id'] ?? null;

    $stmt = $pdo->prepare("
        INSERT INTO staff_report_meta (report_date, admn_no, report_date_text, judge_id)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        admn_no = VALUES(admn_no),
        report_date_text = VALUES(report_date_text),
        judge_id = VALUES(judge_id)
    ");
    $stmt->execute([$reportDate, $admn_no, $report_date_text, $judge_id]);
}

// Fetch posts
$stmt = $pdo->query("
    SELECT serial_no, post_name, bps, sanctioned_strength, working_strength,
           (sanctioned_strength - working_strength) AS vacant,
           court_name
    FROM posts
    ORDER BY serial_no ASC
");
$posts = $stmt->fetchAll();

// Fetch saved footer data for this date
$stmtMeta = $pdo->prepare("SELECT admn_no, report_date_text, judge_id FROM staff_report_meta WHERE report_date = ?");
$stmtMeta->execute([$reportDate]);
$meta = $stmtMeta->fetch();
$admn_no_val = $meta['admn_no'] ?? '';
$report_date_text_val = $meta['report_date_text'] ?? '';
$selectedJudgeId = $meta['judge_id'] ?? null;

// Fetch ONLY District & Sessions Judges who are currently Posted
$judgesStmt = $pdo->query("
    SELECT id, name 
    FROM judicial_officers 
    WHERE post = 'District & Sessions Judge' 
      AND status = 'Posted'
    ORDER BY name ASC
");
$judges = $judgesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected judge name (for display in print/footer)
$selectedJudgeName = null;
if ($selectedJudgeId) {
    $stmt = $pdo->prepare("SELECT name FROM judicial_officers WHERE id = ?");
    $stmt->execute([$selectedJudgeId]);
    $judge = $stmt->fetch(PDO::FETCH_ASSOC);
    $selectedJudgeName = $judge['name'] ?? null;
}

// Split into groups
$districtPosts = array_filter($posts, fn($p) => trim(strtolower($p['court_name'])) === 'district court jamshoro');
$consumerPosts = array_filter($posts, fn($p) => trim(strtolower($p['court_name'])) === 'consumer protection court');

// Calculate totals
function calculateTotals($rows) {
    $sanctioned = array_sum(array_column($rows, 'sanctioned_strength'));
    $working = array_sum(array_column($rows, 'working_strength'));
    $vacant = array_sum(array_column($rows, 'vacant'));
    return [$sanctioned, $working, $vacant];
}

list($distSanctioned, $distWorking, $distVacant) = calculateTotals($districtPosts);
list($consSanctioned, $consWorking, $consVacant) = calculateTotals($consumerPosts);
?>

<style>
    /* Card Styling */
    .report-card {
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        background: #fff;
        margin: 1rem auto;
    }

    .top-heading {
        font-size: 12px !important;
    }

    .report-card .card-header {
        background: linear-gradient(90deg, #005566, #007bff);
        border-radius: 8px 8px 0 0;
        padding: 1rem;
        text-align: center;
    }
    .report-card .card-title {
        color: #fff;
        font-weight: 600;
        margin-bottom: 0;
        font-size: 1.25rem;
    }
    .report-card .card-body {
        padding: 1.5rem;
    }

    /* Form Styling */
    .form-control, .form-select {
        border-radius: 5px;
        border: 1px solid #ced4da;
        font-size: 0.9rem;
        transition: border-color 0.2s ease;
    }
    .form-control:focus, .form-select:focus {
        border-color: #005566;
        box-shadow: 0 0 0 0.2rem rgba(0, 85, 102, 0.25);
    }
    .form-label {
        font-size: 0.85rem;
        color: #333;
        margin-bottom: 0.25rem;
    }
    .btn-sm {
        font-weight: 500;
        border-radius: 5px;
        padding: 0.4rem 1rem;
        transition: background-color 0.2s ease, transform 0.1s ease;
    }
    .btn-primary:hover, .btn-secondary:hover, .btn-success:hover {
        transform: translateY(-2px);
    }
    .form-inline {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    /* Table Styling */
    .table {
        border-radius: 8px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    .table thead {
        background: #f8f9fa;
        color: #333;
    }
    .table th, .table td {
        border: 1px solid #dee2e6;
        padding: 0.75rem;
        vertical-align: middle;
    }
    .table tr:hover {
        background: #f1f3f5;
    }
    .table-title {
        background: #e9ecef;
        font-weight: 600;
        text-align: center;
    }
    .fw-bold {
        font-weight: 600;
    }

    /* Footer Styling */
    .footer-text {
        font-size: 0.9rem;
        margin-top: 1.5rem;
    }
    .footer-row {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .footer-center {
        text-align: center;
        font-size: 0.85rem;
        margin-top: 1rem;
        color: #333;
    }
    .footer-right {
        text-align: right;
        font-size: 0.85rem;
        margin-top: 1rem;
    }
    .footer-right em {
        color: #6c757d;
    }

    /* Print Styling */
@media print {

    @page {
        size: legal portrait;
        margin: 5mm;
    }

    html, body {
        width: 100%;
        height: 100%;
        margin: 0 !important;
        padding: 0 !important;
        font-family: Arial, sans-serif;
        font-size: 20px;
        color: #000;
        background: #fff;
    }

    .container,
    .container-fluid {
        max-width: 100% !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    header,
    nav,
    aside,
    .sidebar,
    .main-sidebar,
    .navbar,
    .topbar,
    .breadcrumb,
    .content-header,
    .judicial-officer,
    .bi,
    .bi-list,
    .judicial-bar,
    .header-info {
        display: none !important;
    }

    .btn,
    form.form-inline,
    .form-control,
    .form-select,
    .judge-select {
        display: none !important;
    }

    .report-card {
        margin: 0 !important;
        box-shadow: none !important;
        border: none !important;
    }

    .card-header {
        padding: 0 0 6px 12px !important;
        margin: 0 !important;
        background: none !important;
        color: #000 !important;
    }

    .card-body {
        padding: 0 !important;
        margin: 0 !important;
    }

    h5.card-title {
        font-size: 12px;
        font-weight: bold;
        text-align: center;
        margin: 0 0 8px 0 !important;
        line-height: 1.3;
    }

    .table-responsive {
        overflow: visible !important;
    }

    .table {
        width: 100% !important;
        border-collapse: collapse !important;
        font-size: 12px;
        margin: 0 !important;
    }

    .table th,
    .table td {
        border: 1px solid #000 !important;
        padding: 3px 4px !important;
        vertical-align: middle !important;
    }

    .table thead th {
        background: #f0f0f0 !important;
        font-weight: bold;
    }

    .table-title {
        background: #e9ecef !important;
        font-weight: bold;
        text-align: center;
    }

    .footer-text {
        margin-top: 6px !important;
        font-size: 10px;
    }

    .footer-row {
        display: flex;
        justify-content: space-between;
    }

    .footer-center,
    .footer-right {
        margin-top: 4px;
        font-size: 10px;
    }

    .print-value {
        display: inline !important;
    }
}

    .footer-text {
        font-size: 10px;
        margin-top: 10px;
    }

    .footer-row {
        display: flex;
        justify-content: space-between;
    }

    .footer-center,
    .footer-right {
        font-size: 10px;
        margin-top: 5px;
    }

    h5.card-title {
        font-size: 12px;
        text-align: center;
        margin-bottom: 10px;
    }

    @media (max-width: 576px) {
        .report-card .card-body {
            padding: 1rem;
        }
        .form-control, .form-select {
            font-size: 0.85rem;
        }
        .table {
            font-size: 0.85rem;
        }
        .table th, .table td {
            padding: 0.5rem;
        }
        .footer-row {
            flex-direction: column;
        }
        .footer-right {
            text-align: left;
        }
    }

    .form-control:focus, .form-select:focus, .btn-sm:focus {
        outline: 2px solid #007bff;
        outline-offset: 2px;
    }
</style>

<div class="container">
    <div class="report-card shadow-sm border-0">
        <div class="card-header">
            <h6 class="card-title top-heading">
                STATEMENT SHOWING THE SANCTIONED, WORKING AND VACANT STRENGTH OF STAFF OF DISTRICT AND SUB-ORDINATE COURTS INCLUDING CONSUMER PROTECTION COURT DISTRICT JAMSHORO AS STOOD ON
                <?= date('d-m-Y', strtotime($reportDate)) ?>
            </h6>
        </div>
        <div class="card-body">
            <!-- Date Picker Form -->
            <form method="get" class="mb-4 form-inline">
                <input type="hidden" name="page" value="staff_strength_report">
                <div class="input-group">
                    <input type="date" name="date" value="<?= htmlspecialchars($reportDate) ?>" class="form-control form-control-sm" required aria-label="Select Report Date">
                    <button type="submit" class="btn btn-primary btn-sm">Show</button>
                    <button type="button" onclick="window.print();" class="btn btn-secondary btn-sm">Print</button>
                </div>
            </form>

            <!-- Staff Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th style="width: 5%;">S.No</th>
                            <th style="width: 45%;">Name of Post</th>
                            <th style="width: 10%;">Scale (BPS)</th>
                            <th style="width: 13%;">Sanctioned Strength</th>
                            <th style="width: 13%;">Working Strength</th>
                            <th style="width: 14%;">Vacant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($districtPosts)): ?>
                            <tr><td colspan="6" class="text-center">No District Court data available</td></tr>
                        <?php else: ?>
                            <?php foreach ($districtPosts as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['serial_no']) ?></td>
                                    <td><?= htmlspecialchars($p['post_name']) ?></td>
                                    <td><?= htmlspecialchars($p['bps']) ?></td>
                                    <td><?= htmlspecialchars($p['sanctioned_strength']) ?></td>
                                    <td><?= htmlspecialchars($p['working_strength']) ?></td>
                                    <td><?= htmlspecialchars($p['vacant']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="fw-bold">
                                <td colspan="3" class="text-end">Total</td>
                                <td><?= $distSanctioned ?></td>
                                <td><?= $distWorking ?></td>
                                <td><?= $distVacant ?></td>
                            </tr>
                        <?php endif; ?>

                        <?php if (!empty($consumerPosts)): ?>
                            <tr>
                                <td colspan="6" class="table-title">
                                    SANCTIONED, WORKING & VACANT STRENGTH OF STAFF OF CONSUMER PROTECTION COURT, DISTRICT JAMSHORO
                                </td>
                            </tr>
                            <?php foreach ($consumerPosts as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['serial_no']) ?></td>
                                    <td><?= htmlspecialchars($p['post_name']) ?></td>
                                    <td><?= htmlspecialchars($p['bps']) ?></td>
                                    <td><?= htmlspecialchars($p['sanctioned_strength']) ?></td>
                                    <td><?= htmlspecialchars($p['working_strength']) ?></td>
                                    <td><?= htmlspecialchars($p['vacant']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="fw-bold">
                                <td colspan="3" class="text-end">Total</td>
                                <td><?= $consSanctioned ?></td>
                                <td><?= $consWorking ?></td>
                                <td><?= $consVacant ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Footer Form -->
            <form method="post" class="mt-4">
                <div class="footer-text">
                    <div class="footer-row">
                        <span>
                            No.Admn/
                            <input type="text" name="admn_no" value="<?= htmlspecialchars($admn_no_val) ?>" class="form-control form-control-sm d-inline-block" style="width: 150px;" aria-label="Admin Number">
                            <span class="print-value"><?= htmlspecialchars($admn_no_val) ?></span>
                        </span>
                        <span>
                            Jamshoro Dated:
                            <input type="text" name="report_date_text" value="<?= htmlspecialchars($report_date_text_val) ?>" class="form-control form-control-sm d-inline-block" style="width: 150px;" aria-label="Report Date Text">
                            <span class="print-value"><?= htmlspecialchars($report_date_text_val) ?></span>
                        </span>
                    </div>
                    <div class="footer-center">
                        Submitted to the Registrar, Honourable High Court of Sindh, Karachi for information.
                    </div>
                    <div class="footer-right">
                        <div class="judge-select">
                            <label for="judge_id" class="form-label visually-hidden">Judge Name</label>
                            <select name="judge_id" id="judge_id" class="form-select form-select-sm d-inline-block" style="width: 200px;" aria-label="Select Judge">
                                <option value="">-- Select Judge --</option>
                                <?php foreach ($judges as $j): ?>
                                    <option value="<?= $j['id'] ?>" <?= ($j['id'] == $selectedJudgeId ? 'selected' : '') ?>>
                                        <?= htmlspecialchars($j['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <strong class="print-value"><?= htmlspecialchars($selectedJudgeName ?? '') ?></strong><br>
                            <em>District & Sessions Judge Jamshoro</em>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <button type="submit" name="save_footer" class="btn btn-success btn-sm">Save Footer Info</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Print Container for Print Layout -->
<div class="print-container" style="display: none;">
    <h5 class="card-title">
        STATEMENT SHOWING THE SANCTIONED, WORKING AND VACANT STRENGTH OF STAFF OF DISTRICT AND SUB-ORDINATE COURTS INCLUDING CONSUMER PROTECTION COURT DISTRICT JAMSHORO AS STOOD ON
        <?= date('d-m-Y', strtotime($reportDate)) ?>
    </h5>
    <table class="table table-bordered table-sm">
        <thead>
            <tr>
                <th style="width: 5%;">S.No</th>
                <th style="width: 45%;">Name of Post</th>
                <th style="width: 10%;">Scale (BPS)</th>
                <th style="width: 13%;">Sanctioned Strength</th>
                <th style="width: 13%;">Working Strength</th>
                <th style="width: 14%;">Vacant</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($districtPosts)): ?>
                <tr><td colspan="6" class="text-center">No District Court data available</td></tr>
            <?php else: ?>
                <?php foreach ($districtPosts as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['serial_no']) ?></td>
                        <td><?= htmlspecialchars($p['post_name']) ?></td>
                        <td><?= htmlspecialchars($p['bps']) ?></td>
                        <td><?= htmlspecialchars($p['sanctioned_strength']) ?></td>
                        <td><?= htmlspecialchars($p['working_strength']) ?></td>
                        <td><?= htmlspecialchars($p['vacant']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="fw-bold">
                    <td colspan="3" class="text-end">Total</td>
                    <td><?= $distSanctioned ?></td>
                    <td><?= $distWorking ?></td>
                    <td><?= $distVacant ?></td>
                </tr>
            <?php endif; ?>

            <?php if (!empty($consumerPosts)): ?>
                <tr>
                    <td colspan="6" class="table-title">
                        SANCTIONED, WORKING & VACANT STRENGTH OF STAFF OF CONSUMER PROTECTION COURT, DISTRICT JAMSHORO
                    </td>
                </tr>
                <?php foreach ($consumerPosts as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['serial_no']) ?></td>
                        <td><?= htmlspecialchars($p['post_name']) ?></td>
                        <td><?= htmlspecialchars($p['bps']) ?></td>
                        <td><?= htmlspecialchars($p['sanctioned_strength']) ?></td>
                        <td><?= htmlspecialchars($p['working_strength']) ?></td>
                        <td><?= htmlspecialchars($p['vacant']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="fw-bold">
                    <td colspan="3" class="text-end">Total</td>
                    <td><?= $consSanctioned ?></td>
                    <td><?= $consWorking ?></td>
                    <td><?= $consVacant ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <div class="footer-text">
        <div class="footer-row">
            <span>No.Admn/ <?= htmlspecialchars($admn_no_val) ?></span>
            <span>Jamshoro Dated: <?= htmlspecialchars($report_date_text_val) ?></span>
        </div>
        <div class="footer-center">
            Submitted to the Registrar, Honourable High Court of Sindh, Karachi for information.
        </div>
        <div class="footer-right">
            <strong><?= htmlspecialchars($selectedJudgeName ?? '') ?></strong><br>
            <em>District & Sessions Judge Jamshoro</em>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>