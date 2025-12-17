<?php
// Can be used as AJAX endpoint OR standalone page
require_once(__DIR__ . '/../../includes/config.inc.php');
require_once(__DIR__ . '/../../includes/security.inc.php');

session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    // If standalone page, show full HTML
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>Access Denied - KPI Manager Report</title>
            <link rel="stylesheet" href="/node_modules/bootstrap/dist/css/bootstrap.css">
        </head>
        <body>
            <div class="container mt-5">
                <div class="alert alert-danger">Access denied. Please log in.</div>
                <a href="/login.php" class="btn btn-primary">Go to Login</a>
            </div>
        </body>
        </html>
        <?php
    } else {
        echo '<div class="alert alert-danger">Access denied. Please log in.</div>';
    }
    exit;
}

// Check if this is an AJAX request or standalone page
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// If standalone page, include header HTML
if (!$isAjax) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>KPI Manager Report Details</title>
        <link rel="stylesheet" href="/node_modules/bootstrap/dist/css/bootstrap.css">
        <link rel="stylesheet" href="/node_modules/bootstrap-icons/font/bootstrap-icons.css">
        <link rel="stylesheet" href="/css/branchtools.css">
    </head>
    <body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/index.php"><img src="/images/CircleLogo.png" style="height:26px"></a>
            <div class="navbar-brand"><b style="font-size:24px;color:#CFDE00;">BranchTools</b></div>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/Modules/KPIReview/KPIReviewManage.php"><span class="bi bi-arrow-left"></span> Back to Management</a>
            </div>
        </div>
    </nav>
    <div class="p-md-2 m-md-2 bg-white">
    <div class="container">
    <?php
}

// Check module access - use report_scorecard permissions (same as KPI Dashboard)
if (getAccess($_SESSION['username'], 'report_scorecard') == 0 && getAccess($_SESSION['username'], 'admin') == 0) {
    if ($isAjax) {
        echo '<div class="alert alert-danger">You do not have permission to view KPI Manager Reports.</div>';
    } else {
        echo '<div class="alert alert-danger mt-4"><h4>Access Denied</h4><p>You do not have permission to view KPI Manager Reports.</p><br><a href="../../" class="btn btn-secondary">Return Home</a></div>';
    }
    exit;
}

$entryId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($entryId <= 0) {
    echo '<div class="alert alert-danger">Invalid entry ID.</div>';
    exit;
}

$conn = new mysqli($config['dbServer'], $config['dbUser'], $config['dbPassword'], $config['dbName']);
if ($conn->connect_error) {
    echo '<div class="alert alert-danger">Database connection failed.</div>';
    exit;
}

// Define KPI names - Must match the names in index.php
$kpiNames = [
    1 => 'EBITDA',
    2 => 'Gross Margin',
    3 => 'GM vs Payroll',
    4 => 'Payroll % of Sales',
    5 => 'Sales'
];

// TEMPORARILY BYPASS LOCATION ACCESS CHECK FOR TESTING
// Fetch entry (temporarily without location filter for testing)
$sql = "SELECT * FROM kpiReview WHERE id = " . intval($entryId);
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    // Include the same CSS styles as the form
    echo '<style>
      .kpi-section-header {
        color: white;
        font-weight: bold;
        padding: 8px;
        margin-bottom: 10px;
        border-radius: 5px;
        font-size: 1.1em;
      }
      .positive-results {
        background-color: #28a745; /* Green */
      }
      .challenges {
        background-color: #fd7e14; /* Orange */
      }
      .morale-meter {
        background-color: #17a2b8; /* Light blue */
      }
      .kpi-table {
        width: 100%;
        margin-bottom: 10px;
        border-collapse: collapse;
        font-size: 0.9em;
      }
      .kpi-table th, .kpi-table td {
        border: 1px solid #dee2e6;
        padding: 5px;
        text-align: left;
        vertical-align: top;
      }
      .kpi-table th {
        background-color: #f8f9fa;
      }
      .subsection-header {
        font-weight: bold;
        background-color: #e9ecef;
        padding: 5px;
        margin-bottom: 5px;
        border-radius: 3px;
      }
      .section-container {
        margin-bottom: 20px;
      }
    </style>';
    
    echo '<div class="kpi-review-details">';
    
    // Header matching form format
    echo '<h3 class="border-bottom mt-4"> <span class="bi bi-graph-up" style="vertical-align: middle;"> KPI Manager Report - LOC #' . htmlspecialchars($row['location_number']) . '</h3>';
    
    // Basic info row (matching form layout)
    echo '<div class="row mb-4 mt-3">';
    echo '<div class="col-sm-4">';
    echo '<label style="width:150px"><b>Month:</b></label>';
    echo '<div class="form-control" style="width:250px; display: inline-block;">' . htmlspecialchars($row['month']) . '</div>';
    echo '</div>';
    echo '<div class="col-sm-4">';
    echo '<label style="width:150px"><b>Branch Manager:</b></label>';
    echo '<div class="form-control" style="width:250px; display: inline-block;">' . htmlspecialchars($row['branch_manager'] ?? 'N/A') . '</div>';
    echo '</div>';
    echo '<div class="col-sm-4">';
    echo '<label style="width:150px"><b>Location:</b></label>';
    echo '<div class="form-control" style="width:250px; display: inline-block;">' . htmlspecialchars($row['location_name']) . '</div>';
    echo '</div>';
    echo '</div>';
    
    // POSITIVE RESULTS / WINS Section (matching form format exactly)
    echo '<div class="section-container">';
    echo '<div class="kpi-section-header positive-results">';
    echo '<h4 class="mb-0">POSITIVE RESULTS / WINS</h4>';
    echo '</div>';
    
    echo '<div class="row">';
    // MONTH Subsection
    echo '<div class="col-md-6">';
    echo '<div class="subsection-header mb-2">MONTH</div>';
    echo '<table class="kpi-table">';
    echo '<thead><tr><th style="width: 30%;">KPI</th><th style="width: 70%;">Comments</th></tr></thead>';
    echo '<tbody>';
    for ($i = 1; $i <= 5; $i++) {
        $kpiName = $kpiNames[$i];
        $comments = $row['positive_month_comments_' . $i] ?? '';
        echo '<tr>';
        echo '<td>' . htmlspecialchars($kpiName) . '</td>';
        echo '<td>' . nl2br(htmlspecialchars($comments ?: '')) . '</td>';
        echo '</tr>';
    }
    echo '<tr>';
    echo '<td><strong>Other Comments</strong></td>';
    echo '<td>' . nl2br(htmlspecialchars($row['positive_month_other'] ?? '')) . '</td>';
    echo '</tr>';
    echo '</tbody></table>';
    echo '</div>';
    
    // YEAR TO DATE Subsection
    echo '<div class="col-md-6">';
    echo '<div class="subsection-header mb-2">YEAR TO DATE</div>';
    echo '<table class="kpi-table">';
    echo '<thead><tr><th style="width: 30%;">KPI</th><th style="width: 70%;">Comments</th></tr></thead>';
    echo '<tbody>';
    for ($i = 1; $i <= 5; $i++) {
        $kpiName = $kpiNames[$i];
        $comments = $row['positive_ytd_comments_' . $i] ?? '';
        echo '<tr>';
        echo '<td>' . htmlspecialchars($kpiName) . '</td>';
        echo '<td>' . nl2br(htmlspecialchars($comments ?: '')) . '</td>';
        echo '</tr>';
    }
    echo '<tr>';
    echo '<td><strong>Other Comments</strong></td>';
    echo '<td>' . nl2br(htmlspecialchars($row['positive_ytd_other'] ?? '')) . '</td>';
    echo '</tr>';
    echo '</tbody></table>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // CHALLENGES / OPPORTUNITIES Section (matching form format exactly)
    echo '<div class="section-container">';
    echo '<div class="kpi-section-header challenges">';
    echo '<h4 class="mb-0">CHALLENGES / OPPORTUNITIES</h4>';
    echo '</div>';
    
    echo '<div class="row">';
    // MONTH Subsection
    echo '<div class="col-md-6">';
    echo '<div class="subsection-header mb-2">MONTH</div>';
    echo '<table class="kpi-table">';
    echo '<thead><tr><th style="width: 30%;">KPI</th><th style="width: 70%;">Comments</th></tr></thead>';
    echo '<tbody>';
    for ($i = 1; $i <= 5; $i++) {
        $kpiName = $kpiNames[$i];
        $comments = $row['challenge_month_comments_' . $i] ?? '';
        echo '<tr>';
        echo '<td>' . htmlspecialchars($kpiName) . '</td>';
        echo '<td>' . nl2br(htmlspecialchars($comments ?: '')) . '</td>';
        echo '</tr>';
    }
    echo '<tr>';
    echo '<td><strong>Other Comments</strong></td>';
    echo '<td>' . nl2br(htmlspecialchars($row['challenge_month_other'] ?? '')) . '</td>';
    echo '</tr>';
    echo '</tbody></table>';
    echo '</div>';
    
    // YEAR TO DATE Subsection
    echo '<div class="col-md-6">';
    echo '<div class="subsection-header mb-2">YEAR TO DATE</div>';
    echo '<table class="kpi-table">';
    echo '<thead><tr><th style="width: 30%;">KPI</th><th style="width: 70%;">Comments</th></tr></thead>';
    echo '<tbody>';
    for ($i = 1; $i <= 5; $i++) {
        $kpiName = $kpiNames[$i];
        $comments = $row['challenge_ytd_comments_' . $i] ?? '';
        echo '<tr>';
        echo '<td>' . htmlspecialchars($kpiName) . '</td>';
        echo '<td>' . nl2br(htmlspecialchars($comments ?: '')) . '</td>';
        echo '</tr>';
    }
    echo '<tr>';
    echo '<td><strong>Other Comments</strong></td>';
    echo '<td>' . nl2br(htmlspecialchars($row['challenge_ytd_other'] ?? '')) . '</td>';
    echo '</tr>';
    echo '</tbody></table>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // MORALE METER Section (matching form format)
    echo '<div class="section-container">';
    echo '<div class="kpi-section-header morale-meter">';
    echo '<h4 class="mb-0">MORALE METER</h4>';
    echo '</div>';
    echo '<div class="alert alert-info mt-3">';
    echo '<p class="mb-2"><strong>A measure of \'BranchTeam\' sentiment. 1 = Poor, 3 = Good, 5 = Excellent</strong></p>';
    echo '<div class="row">';
    echo '<div class="col-md-4">';
    echo '<div class="form-group">';
    echo '<label><b>Select Rating:</b></label>';
    echo '<div class="form-control">' . htmlspecialchars($row['morale_meter'] ?? 'N/A');
    if (!empty($row['morale_meter'])) {
        $rating = intval($row['morale_meter']);
        $ratings = [1 => 'Poor', 2 => 'Below Average', 3 => 'Good', 4 => 'Very Good', 5 => 'Excellent'];
        echo ' - ' . ($ratings[$rating] ?? '');
    }
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="col-md-8">';
    echo '<div class="form-group">';
    echo '<label><b>Notes:</b></label>';
    echo '<div class="form-control" style="min-height: 80px;">' . nl2br(htmlspecialchars($row['morale_notes'] ?? '')) . '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // Footer info
    echo '<hr>';
    echo '<div class="text-muted small">';
    echo '<p><strong>Submitted by:</strong> ' . htmlspecialchars($row['submitted_by']) . '</p>';
    echo '<p><strong>Submitted on:</strong> ' . (!empty($row['created_at']) ? date('F j, Y g:i A', strtotime($row['created_at'])) : 'N/A') . '</p>';
    echo '</div>';
    
    echo '</div>';
} else {
    echo '<div class="alert alert-danger">Entry not found or you do not have access to view it.</div>';
}

$conn->close();

// If standalone page, close HTML
if (!$isAjax) {
    ?>
    </div>
    </div>
    <footer class="container d-print-none mt-5">
        <p class="text-muted text-center">&copy <?php echo date("Y");?>, Mayesh Wholesale Florist, Inc.</p>
    </footer>
    <script src="/node_modules/jquery/dist/jquery.min.js"></script>
    <script src="/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}
?>
