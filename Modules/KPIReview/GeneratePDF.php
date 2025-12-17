<?php
/**
 * Generate PDF for KPI Review - Matches site layout exactly
 * Usage: GeneratePDF.php?id={review_id}
 */

require_once(__DIR__ . '/../../includes/config.inc.php');

session_start();

// Set default session values for guest users
if (!isset($_SESSION['username'])) {
    $_SESSION['username'] = 'guest';
    $_SESSION['fullName'] = 'Guest User';
    $_SESSION['locationName'] = 'Guest Location';
    $_SESSION['locID'] = '000';
}

// Get review ID
$reviewId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($reviewId <= 0) {
    die("Invalid review ID");
}

// Connect to database
$conn = new mysqli($config['dbServer'], $config['dbUser'], $config['dbPassword'], $config['dbName']);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch review data
$sql = "SELECT * FROM kpiReview WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $reviewId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Review not found");
}

$row = $result->fetch_assoc();
$stmt->close();
$conn->close();

// KPI Names
$kpiNames = [
    1 => 'Sales Growth',
    2 => 'Gross Margin',
    3 => 'Inventory Turn',
    4 => 'Customer Count',
    5 => 'Average Order Value'
];

// Determine base path for assets
$basePath = '/';
if (isset($_SERVER['REQUEST_URI'])) {
    $requestUri = $_SERVER['REQUEST_URI'];
    if (strpos($requestUri, '/branchtools/') === 0 || strpos($requestUri, '/branchtools') === 0) {
        $basePath = '/branchtools';
    } elseif (strpos($requestUri, '/kpi/') === 0 || strpos($requestUri, '/kpi') === 0) {
        $basePath = '/kpi';
    }
}

// Generate HTML matching the site layout exactly
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>KPI Manager Report - <?php echo htmlspecialchars($row['location_name']); ?> - <?php echo htmlspecialchars($row['month']); ?> <?php echo htmlspecialchars($row['year']); ?></title>
    <link rel="stylesheet" href="<?php echo $basePath; ?>/node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>/node_modules/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        @media print {
            @page {
                size: letter landscape;
                margin: 0.15in;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .section-container {
                page-break-inside: avoid;
                margin-bottom: 8px !important;
            }
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 7pt;
            line-height: 1.1;
            margin: 0;
            padding: 5px;
            color: #000;
        }
        .container-fluid {
            padding: 0;
        }
        h3 {
            font-size: 10pt;
            margin: 3px 0;
            padding-bottom: 2px;
        }
        .row {
            margin: 0;
        }
        .mb-4 {
            margin-bottom: 5px !important;
        }
        .mt-3, .mt-4 {
            margin-top: 3px !important;
        }
        .kpi-section-header {
            padding: 3px 6px;
            color: white;
            font-weight: bold;
            margin-bottom: 3px;
            font-size: 8pt;
        }
        .kpi-section-header h4 {
            font-size: 8pt;
            margin: 0;
        }
        .kpi-section-header.positive-results {
            background-color: #28a745; /* Green */
        }
        .kpi-section-header.challenges {
            background-color: #fd7e14; /* Orange */
        }
        .kpi-section-header.morale-meter {
            background-color: #17a2b8; /* Light blue */
        }
        .subsection-header {
            font-weight: bold;
            background-color: #e9ecef;
            padding: 2px 4px;
            margin-bottom: 2px;
            border-radius: 2px;
            font-size: 7pt;
        }
        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
            font-size: 6.5pt;
        }
        .kpi-table th {
            background-color: #f8f9fa;
            padding: 2px 3px;
            text-align: left;
            border: 1px solid #dee2e6;
            font-weight: bold;
            font-size: 6.5pt;
        }
        .kpi-table td {
            padding: 2px 3px;
            border: 1px solid #dee2e6;
            vertical-align: top;
            font-size: 6.5pt;
        }
        .section-container {
            margin-bottom: 5px;
            page-break-inside: avoid;
        }
        .form-control {
            padding: 1px 3px;
            font-size: 7pt;
            height: auto;
            line-height: 1.2;
        }
        label {
            font-size: 7pt;
            margin-bottom: 1px;
        }
        .alert {
            padding: 3px;
            margin: 3px 0;
            font-size: 6.5pt;
        }
        .alert p {
            margin: 1px 0;
            font-size: 6.5pt;
        }
        .form-group {
            margin-bottom: 3px;
        }
        .col-md-6 {
            padding: 0 3px;
        }
        .col-md-4, .col-md-8 {
            padding: 0 3px;
        }
        .col-sm-4 {
            padding: 0 3px;
        }
        hr {
            margin: 3px 0;
        }
        .text-muted {
            font-size: 6pt;
        }
        .small {
            font-size: 6pt;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <h3 class="border-bottom mt-4">
            <span class="bi bi-graph-up" style="vertical-align: middle;"></span> KPI Manager Report - LOC #<?php echo htmlspecialchars($row['location_number']); ?>
        </h3>
        
        <!-- Basic info row -->
        <div class="row mb-4 mt-3">
            <div class="col-sm-4">
                <label style="width:150px"><b>Month:</b></label>
                <div class="form-control" style="width:250px; display: inline-block;"><?php echo htmlspecialchars($row['month']); ?></div>
            </div>
            <div class="col-sm-4">
                <label style="width:150px"><b>Branch Manager:</b></label>
                <div class="form-control" style="width:250px; display: inline-block;"><?php echo htmlspecialchars($row['branch_manager'] ?? 'N/A'); ?></div>
            </div>
            <div class="col-sm-4">
                <label style="width:150px"><b>Location:</b></label>
                <div class="form-control" style="width:250px; display: inline-block;"><?php echo htmlspecialchars($row['location_name']); ?></div>
            </div>
        </div>
        
        <!-- POSITIVE RESULTS / WINS Section -->
        <div class="section-container">
            <div class="kpi-section-header positive-results">
                <h4 class="mb-0">POSITIVE RESULTS / WINS</h4>
            </div>
            
            <div class="row">
                <!-- MONTH Subsection -->
                <div class="col-md-6">
                    <div class="subsection-header mb-2">MONTH</div>
                    <table class="kpi-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;">KPI</th>
                                <th style="width: 70%;">Comments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            for ($i = 1; $i <= 5; $i++) {
                                $kpiName = $kpiNames[$i];
                                $comments = $row['positive_month_comments_' . $i] ?? '';
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($kpiName) . '</td>';
                                echo '<td>' . nl2br(htmlspecialchars($comments ?: '')) . '</td>';
                                echo '</tr>';
                            }
                            ?>
                            <tr>
                                <td><strong>Other Comments</strong></td>
                                <td><?php echo nl2br(htmlspecialchars($row['positive_month_other'] ?? '')); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- YEAR TO DATE Subsection -->
                <div class="col-md-6">
                    <div class="subsection-header mb-2">YEAR TO DATE</div>
                    <table class="kpi-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;">KPI</th>
                                <th style="width: 70%;">Comments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            for ($i = 1; $i <= 5; $i++) {
                                $kpiName = $kpiNames[$i];
                                $comments = $row['positive_ytd_comments_' . $i] ?? '';
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($kpiName) . '</td>';
                                echo '<td>' . nl2br(htmlspecialchars($comments ?: '')) . '</td>';
                                echo '</tr>';
                            }
                            ?>
                            <tr>
                                <td><strong>Other Comments</strong></td>
                                <td><?php echo nl2br(htmlspecialchars($row['positive_ytd_other'] ?? '')); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- CHALLENGES / OPPORTUNITIES Section -->
        <div class="section-container">
            <div class="kpi-section-header challenges">
                <h4 class="mb-0">CHALLENGES / OPPORTUNITIES</h4>
            </div>
            
            <div class="row">
                <!-- MONTH Subsection -->
                <div class="col-md-6">
                    <div class="subsection-header mb-2">MONTH</div>
                    <table class="kpi-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;">KPI</th>
                                <th style="width: 70%;">Comments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            for ($i = 1; $i <= 5; $i++) {
                                $kpiName = $kpiNames[$i];
                                $comments = $row['challenge_month_comments_' . $i] ?? '';
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($kpiName) . '</td>';
                                echo '<td>' . nl2br(htmlspecialchars($comments ?: '')) . '</td>';
                                echo '</tr>';
                            }
                            ?>
                            <tr>
                                <td><strong>Other Comments</strong></td>
                                <td><?php echo nl2br(htmlspecialchars($row['challenge_month_other'] ?? '')); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- YEAR TO DATE Subsection -->
                <div class="col-md-6">
                    <div class="subsection-header mb-2">YEAR TO DATE</div>
                    <table class="kpi-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;">KPI</th>
                                <th style="width: 70%;">Comments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            for ($i = 1; $i <= 5; $i++) {
                                $kpiName = $kpiNames[$i];
                                $comments = $row['challenge_ytd_comments_' . $i] ?? '';
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($kpiName) . '</td>';
                                echo '<td>' . nl2br(htmlspecialchars($comments ?: '')) . '</td>';
                                echo '</tr>';
                            }
                            ?>
                            <tr>
                                <td><strong>Other Comments</strong></td>
                                <td><?php echo nl2br(htmlspecialchars($row['challenge_ytd_other'] ?? '')); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- MORALE METER Section -->
        <div class="section-container">
            <div class="kpi-section-header morale-meter">
                <h4 class="mb-0">MORALE METER</h4>
            </div>
            <div class="alert alert-info mt-3">
                <p class="mb-2"><strong>A measure of 'BranchTeam' sentiment. 1 = Poor, 3 = Good, 5 = Excellent</strong></p>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><b>Select Rating:</b></label>
                            <div class="form-control">
                                <?php 
                                echo htmlspecialchars($row['morale_meter'] ?? 'N/A');
                                if (!empty($row['morale_meter'])) {
                                    $rating = intval($row['morale_meter']);
                                    $ratings = [1 => 'Poor', 2 => 'Below Average', 3 => 'Good', 4 => 'Very Good', 5 => 'Excellent'];
                                    echo ' - ' . ($ratings[$rating] ?? '');
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="form-group">
                            <label><b>Notes:</b></label>
                            <div class="form-control" style="min-height: 80px;"><?php echo nl2br(htmlspecialchars($row['morale_notes'] ?? '')); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer info -->
        <hr>
        <div class="text-muted small">
            <p><strong>Submitted by:</strong> <?php echo htmlspecialchars($row['submitted_by']); ?></p>
            <p><strong>Submitted on:</strong> <?php echo (!empty($row['created_at']) ? date('F j, Y g:i A', strtotime($row['created_at'])) : 'N/A'); ?></p>
        </div>
    </div>

    <script>
        // Auto-trigger print dialog when page loads
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
