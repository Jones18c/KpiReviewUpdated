<?php
// Handle draft deletion - MUST be before any HTML output or header includes
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_draft') {
    // Load config and start session before header output
    require("../../includes/config.inc.php");
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set JSON header before any output
    header('Content-Type: application/json');
    
    $conn = new mysqli($config['dbServer'], $config['dbUser'], $config['dbPassword'], $config['dbName']);
    
    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }
    
    $draftId = intval($_POST['draft_id'] ?? 0);
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
    
    if ($draftId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid draft ID']);
        $conn->close();
        exit;
    }
    
    // Verify draft belongs to current user and is a draft
    // Check for both 'guest' and 'Guest User' to handle old entries
    $usernameEscaped = $conn->real_escape_string($username);
    $checkSql = "SELECT id FROM kpiReview WHERE id = $draftId AND status = 'DRAFT' AND (submitted_by = '$usernameEscaped' OR submitted_by = 'Guest User') LIMIT 1";
    $checkResult = $conn->query($checkSql);
    
    if ($checkResult && $checkResult->num_rows > 0) {
        $deleteSql = "DELETE FROM kpiReview WHERE id = $draftId";
        if ($conn->query($deleteSql) === TRUE) {
            echo json_encode(['success' => true, 'message' => 'Draft deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error deleting draft: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Draft not found or you do not have permission to delete it']);
    }
    
    $conn->close();
    exit;
}

$header['pageTitle'] = "Manager KPI Review Management";
$header['securityModuleName'] = 'report_scorecard';
require("../../includes/header.inc.php");
?>
<style>
    /* Ensure action buttons stay on one line */
    #kpiReviewTable td:last-child {
        white-space: nowrap;
    }
    #kpiReviewTable td:last-child .btn {
        margin-right: 0.25rem;
    }
</style>

<div class="p-md-2 m-md-2 bg-white">
<div class="container">
<div class="d-flex justify-content-between align-items-center border-bottom mb-4 mt-4">
    <h3 class="mb-0"><span class="bi bi-graph-up" style="vertical-align: middle;"></span>Manager KPI Review</h3>
    <div>
        <a href="index.php" class="btn btn-primary" title="Go back to KPI Manager Report form"><span class="bi bi-arrow-left"></span> Back to KPI Manager Report</a>
    </div>
</div>

<?php

// Check for user's drafts
$userDrafts = [];
// Create database connection if not already available from header.inc.php
// Always create a fresh connection to avoid issues with closed connections from header.inc.php
if (!isset($conn)) {
    $conn = new mysqli($config['dbServer'], $config['dbUser'], $config['dbPassword'], $config['dbName']);
    if ($conn->connect_error) {
        $conn = null; // Set to null if connection failed
    }
} else {
    // If $conn exists but might be closed, create a new one
    try {
        // Try to access a property that will throw if connection is closed
        $test = $conn->server_info;
    } catch (Error $e) {
        // Connection is closed, create a new one
        $conn = new mysqli($config['dbServer'], $config['dbUser'], $config['dbPassword'], $config['dbName']);
        if ($conn->connect_error) {
            $conn = null;
        }
    }
}

// Removed draft fetching - drafts are not shown on this page (only published submissions)
$userDrafts = [];

// Removed draft fetching - drafts are not shown on this page (only published submissions)
$userDrafts = [];

?>

<!-- Search/Filter Section -->
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><span class="bi bi-funnel"></span> Filters</h5>
    </div>
    <div class="card-body">
        <div class="row align-items-end">
            <div class="col-md-3">
                <label for="filterLocation" class="form-label"><b>Location:</b></label>
                <select id="filterLocation" class="form-control">
                    <option value="">All Locations</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="filterMonth" class="form-label"><b>Month:</b></label>
                <select id="filterMonth" class="form-control form-control-sm">
                    <option value="">All Months</option>
                    <option value="January">January</option>
                    <option value="February">February</option>
                    <option value="March">March</option>
                    <option value="April">April</option>
                    <option value="May">May</option>
                    <option value="June">June</option>
                    <option value="July">July</option>
                    <option value="August">August</option>
                    <option value="September">September</option>
                    <option value="October">October</option>
                    <option value="November">November</option>
                    <option value="December">December</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="filterYear" class="form-label"><b>Year:</b></label>
                <input type="number" id="filterYear" class="form-control form-control-sm" placeholder="e.g., 2025" min="2020" max="2099">
            </div>
            <div class="col-md-2">
                <label for="filterSubmittedBy" class="form-label"><b>Submitted By:</b></label>
                <input type="text" id="filterSubmittedBy" class="form-control form-control-sm" placeholder="Search by name">
            </div>
            <div class="col-md-2">
                <label for="filterStatus" class="form-label"><b>Status:</b></label>
                <select id="filterStatus" class="form-control form-control-sm">
                    <option value="">All Statuses</option>
                    <option value="PUBLISHED">Published</option>
                    <option value="DRAFT">Draft</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="button" id="clearFilters" class="btn btn-secondary btn-sm w-100">Clear</button>
            </div>
        </div>
    </div>
</div>

<?php
// Get user's accessible locations using the same logic as MayeshMarketShipments.php
// Reuse existing connection if available, otherwise create a new one
if (!isset($conn) || !$conn || (is_object($conn) && $conn->connect_error)) {
    $conn = new mysqli($config['dbServer'], $config['dbUser'], $config['dbPassword'], $config['dbName']);
    if ($conn->connect_error) {
        echo '<div class="alert alert-danger">Database connection failed: ' . htmlspecialchars($conn->connect_error) . '</div>';
        $conn = null; // Set to null so we can check later
    }
}

// Get locations user has access to - same query as buildLocationsSelect function
// Only get locations from userLocationAccess table (matching MayeshMarketShipments.php logic)
$accessibleLocations = [];
if ($conn && !$conn->connect_error) {
    $sql = "SELECT userLocationAccess.username, userLocationAccess.locationid, locations.name 
            FROM userLocationAccess 
            INNER JOIN locations ON userLocationAccess.locationid = locations.locationNumber 
            WHERE userLocationAccess.username = '" . $conn->real_escape_string($_SESSION['username']) . "' 
            ORDER BY locations.name";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $accessibleLocations[] = [
                'locationNumber' => $row['locationid'],
                'name' => $row['name']
            ];
        }
    }
    
    // Also include the user's primary location from session (like MayeshMarketShipments.php does)
    if (!empty($_SESSION['locID']) && !empty($_SESSION['locationName'])) {
        // Check if primary location is already in the list
        $primaryLocationExists = false;
        foreach ($accessibleLocations as $loc) {
            if ($loc['locationNumber'] == $_SESSION['locID']) {
                $primaryLocationExists = true;
                break;
            }
        }
        
        // If not already in list, add it at the beginning
        if (!$primaryLocationExists) {
            array_unshift($accessibleLocations, [
                'locationNumber' => $_SESSION['locID'],
                'name' => $_SESSION['locationName']
            ]);
        }
    }
}

// Build location filter for SQL query
// Always filter by accessible locations - if user has no accessible locations, show nothing
$locationFilter = "";
if ($conn && !$conn->connect_error) {
    if (!empty($accessibleLocations)) {
        $locationNumbers = array_column($accessibleLocations, 'locationNumber');
        if (!empty($locationNumbers)) {
            $locationFilter = " AND location_number IN ('" . implode("','", array_map(function($loc) use ($conn) {
                return $conn->real_escape_string($loc);
            }, $locationNumbers)) . "')";
        } else {
            // User has no accessible locations - show nothing
            $locationFilter = " AND 1=0"; // Always false condition
        }
    } else {
        // User has no accessible locations - show nothing
        $locationFilter = " AND 1=0"; // Always false condition
    }
}

// Fetch KPI Review entries - only show PUBLISHED entries (no drafts)
$allRows = [];
if ($conn && !$conn->connect_error) {
    // Only show PUBLISHED entries, exclude DRAFT entries
    $sql = "SELECT * FROM kpiReview WHERE status = 'PUBLISHED'" . $locationFilter . " ORDER BY created_at DESC, year DESC, month DESC";
    $result = $conn->query($sql);
    
    // Debug: Check if query worked
    if ($result === false) {
        echo '<div class="alert alert-danger">Database query error: ' . htmlspecialchars($conn->error) . '</div>';
    } else {
        if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $allRows[] = $row;
            }
        }
    }
} else {
    echo '<div class="alert alert-danger">Cannot connect to database. Please check your connection settings.</div>';
}

// Get unique locations for filter dropdown
$uniqueLocations = [];
foreach ($allRows as $row) {
    if (!isset($uniqueLocations[$row['location_number']])) {
        $uniqueLocations[$row['location_number']] = $row['location_name'];
    }
}

?>

<!-- Data Table -->
<div class="card">
    <div class="card-header bg-light">
        <h5 class="mb-0"><span class="bi bi-table"></span> Manager KPI Review Submissions</h5>
    </div>
    <div class="card-body">
        <table id="kpiReviewTable" class="table table-striped table-bordered table-hover" style="width:100%; table-layout: auto;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Location</th>
                    <th>Month</th>
                    <th>Year</th>
                    <th>Branch Manager</th>
                    <th>Submitted By</th>
                    <th>Status</th>
                    <th>Submitted Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($allRows as $row) {
                        $createdDate = !empty($row['created_at']) ? date('Y-m-d H:i', strtotime($row['created_at'])) : 'N/A';
                        $statusBadge = '<span class="badge bg-success">PUBLISHED</span>';
                        
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['location_name']) . ' (#' . htmlspecialchars($row['location_number']) . ')</td>';
                        echo '<td>' . htmlspecialchars($row['month']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['year']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['branch_manager'] ?? 'N/A') . '</td>';
                        echo '<td>' . htmlspecialchars($row['submitted_by']) . '</td>';
                        echo '<td>' . $statusBadge . '</td>';
                        echo '<td>' . $createdDate . '</td>';
                        echo '<td>';
                        
                        // Only show view and open buttons for published entries
                        echo '<button class="btn btn-sm btn-info view-entry me-1" data-id="' . $row['id'] . '" title="View Details"><span class="bi bi-eye"></span></button>';
                        echo '<a href="index.php?id=' . $row['id'] . '" target="_blank" class="btn btn-sm btn-secondary me-1" title="Open in New Tab"><span class="bi bi-box-arrow-up-right"></span></a>';
                        echo '<a href="GeneratePDF.php?id=' . $row['id'] . '" target="_blank" class="btn btn-sm btn-danger" title="Download as PDF"><span class="bi bi-file-pdf"></span> PDF</a>';
                        echo '</td>';
                        echo '</tr>';
                }
                if (empty($allRows)) {
                    echo '<tr><td colspan="9" class="text-center">No Manager KPI Review submissions found.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$conn->close();
?>

<!-- View Entry Modal -->
<div class="modal fade" id="viewEntryModal" tabindex="-1" aria-labelledby="viewEntryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewEntryModalLabel">Manager KPI Review Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewEntryContent">
                <p>Loading...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

</div>
</div>

<script>
$(document).ready(function() {
    // Populate location filter dropdown
    var locations = <?php echo json_encode($accessibleLocations); ?>;
    var locationSelect = $('#filterLocation');
    locations.forEach(function(loc) {
        locationSelect.append('<option value="' + loc.locationNumber + '">' + loc.name + ' (#' + loc.locationNumber + ')</option>');
    });
    
    // Initialize DataTable - explicitly define columns to avoid count mismatch
    // Remove empty message row if it exists before initializing
    $('#kpiReviewTable tbody tr:has(td[colspan])').remove();
    
    var table = $('#kpiReviewTable').DataTable({
        "order": [[7, "desc"]], // Sort by submitted date descending (column 7)
        "pageLength": 25,
        "responsive": false, // Disable responsive to avoid column detection issues
        "autoWidth": false,
        "columnDefs": [
            { "orderable": false, "targets": [8] }, // Disable sorting on Actions column
            { "width": "5%", "targets": [0] }, // ID column
            { "width": "18%", "targets": [1] }, // Location column
            { "width": "9%", "targets": [2, 3] }, // Month and Year columns
            { "width": "13%", "targets": [4, 5] }, // Branch Manager and Submitted By columns
            { "width": "8%", "targets": [6, 7] }, // Status and Submitted Date columns
            { "width": "20%", "targets": [8] } // Actions column - increased to fit all buttons
        ],
        "language": {
            "emptyTable": "No Manager KPI Review submissions found."
        }
    });
    
    // Filter by location
    
    $('#filterLocation').on('change', function() {
        var locationNum = this.value;
        if (locationNum) {
            table.column(1).search('#' + locationNum, true, false).draw();
        } else {
            table.column(1).search('').draw();
        }
    });
    
    // Filter by month
    $('#filterMonth').on('change', function() {
        table.column(2).search(this.value).draw();
    });
    
    // Filter by year
    $('#filterYear').on('input', function() {
        table.column(3).search(this.value).draw();
    });
    
    // Filter by submitted by
    $('#filterSubmittedBy').on('keyup', function() {
        table.column(5).search(this.value).draw();
    });
    
    // Filter by status
    $('#filterStatus').on('change', function() {
        table.column(6).search(this.value).draw();
    });
    
    // Clear filters
    $('#clearFilters').on('click', function() {
        $('#filterLocation').val('');
        $('#filterMonth').val('');
        $('#filterYear').val('');
        $('#filterSubmittedBy').val('');
        $('#filterStatus').val('');
        table.search('').columns().search('').draw();
    });
    
    // View entry details
    $(document).on('click', '.view-entry', function() {
        var entryId = $(this).data('id');
        $('#viewEntryModal').modal('show');
        $('#viewEntryContent').html('<p>Loading...</p>');
        
        // Fetch entry details via AJAX
        $.ajax({
            url: 'KPIReviewView.php',
            method: 'GET',
            data: { id: entryId },
            success: function(response) {
                $('#viewEntryContent').html(response);
            },
            error: function() {
                $('#viewEntryContent').html('<div class="alert alert-danger">Error loading entry details.</div>');
            }
        });
    });
    
    // Delete draft with confirmation
    $(document).on('click', '.delete-draft', function() {
        var draftId = $(this).data('draft-id');
        var draftName = $(this).data('draft-name');
        var button = $(this);
        
        if (confirm('Are you sure you want to delete the draft for "' + draftName + '"?\n\nThis action cannot be undone.')) {
            // Show loading state
            button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
            
            $.ajax({
                url: 'index.php',
                method: 'POST',
                data: {
                    action: 'delete_draft',
                    draft_id: draftId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Remove the draft row or reload page
                        button.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                            // Reload page to refresh "My Drafts" section
                            location.reload();
                        });
                    } else {
                        alert('Error deleting draft: ' + (response.error || 'Unknown error'));
                        button.prop('disabled', false).html('<span class="bi bi-trash"></span>');
                    }
                },
                error: function() {
                    alert('Error deleting draft. Please try again.');
                    button.prop('disabled', false).html('<span class="bi bi-trash"></span>');
                }
            });
        }
    });
});
</script>

<?php
// Drafts Modal for Management Page
if (!empty($userDrafts)) {
    echo '<div class="modal fade" id="draftsModalManage" tabindex="-1" aria-labelledby="draftsModalManageLabel" aria-hidden="true">';
    echo '<div class="modal-dialog modal-lg">';
    echo '<div class="modal-content">';
    echo '<div class="modal-header bg-warning bg-opacity-10">';
    echo '<h5 class="modal-title" id="draftsModalManageLabel"><span class="bi bi-file-earmark-text"></span> My Drafts</h5>';
    echo '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
    echo '</div>';
    echo '<div class="modal-body">';
    echo '<p class="text-muted mb-3">You have <strong>' . count($userDrafts) . '</strong> saved draft(s). Click on a draft to continue editing:</p>';
    echo '<div class="list-group">';
    foreach ($userDrafts as $draft) {
        $draftUrl = 'index.php?draft=' . $draft['id'];
        $lastUpdated = date('m/d/Y g:i A', strtotime($draft['updated_at']));
        echo '<div class="list-group-item">';
        echo '<div class="d-flex w-100 justify-content-between align-items-center">';
        echo '<div class="flex-grow-1">';
        echo '<a href="' . htmlspecialchars($draftUrl) . '" class="text-decoration-none">';
        echo '<h6 class="mb-1"><span class="bi bi-file-earmark-text text-warning"></span> ' . htmlspecialchars($draft['location_name']) . ' - ' . htmlspecialchars($draft['month']) . ' ' . htmlspecialchars($draft['year']) . '</h6>';
        echo '</a>';
        echo '<small class="text-muted">LOC #' . htmlspecialchars($draft['location_number']) . ' | Last updated: ' . $lastUpdated . '</small>';
        echo '</div>';
        echo '<div class="ms-3">';
        echo '<a href="' . htmlspecialchars($draftUrl) . '" class="btn btn-sm btn-warning me-2" title="Edit Draft"><span class="bi bi-pencil"></span></a>';
        echo '<button type="button" class="btn btn-sm btn-danger delete-draft" data-draft-id="' . $draft['id'] . '" data-draft-name="' . htmlspecialchars($draft['location_name'] . ' - ' . $draft['month'] . ' ' . $draft['year']) . '"><span class="bi bi-trash"></span> Delete</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
    echo '<div class="modal-footer">';
    echo '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}
?>

  </body>
</html>
<?php
// TODO: Re-enable footer include once module is set up
// require("../includes/footer.inc.php");
?>

