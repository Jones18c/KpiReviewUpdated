<?php
$header['pageTitle'] = "Form: KPI Manager Report";
$header['securityModuleName'] = 'report_scorecard';
require("../../includes/header.inc.php");

// Try to include Mail.php - it should be available via PEAR
// Try multiple possible locations
$mailPaths = [
    '/usr/share/php/Mail.php',
    '/usr/share/pear/Mail.php',
    __DIR__ . '/Mail.php',
    '/usr/lib/php/Mail.php'
];

$mimePaths = [
    '/usr/share/php/Mail/mime.php',
    '/usr/share/pear/Mail/mime.php',
    __DIR__ . '/Mail/mime.php',
    '/usr/lib/php/Mail/mime.php'
];

$mailLoaded = false;
$mimeLoaded = false;

foreach ($mailPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $mailLoaded = true;
        break;
    }
}

foreach ($mimePaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $mimeLoaded = true;
        break;
    }
}

// Also try via include_path (PEAR style)
if (!$mailLoaded) {
    @include_once 'Mail.php';
    $mailLoaded = class_exists('Mail');
}
if (!$mimeLoaded) {
    @include_once 'Mail/mime.php';
    $mimeLoaded = class_exists('Mail_mime');
}

?>
<style>
  .kpi-section-header {
    color: white;
    font-weight: bold;
    padding: 10px;
    margin-bottom: 15px;
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
    font-size: 0.9rem;
  }
  .kpi-table th {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    padding: 5px 8px;
    text-align: left;
    font-size: 0.85rem;
  }
  .kpi-table td {
    border: 1px solid #dee2e6;
    padding: 4px 6px;
  }
  .kpi-table input[type="text"],
  .kpi-table textarea {
    width: 100%;
    border: none;
    padding: 3px 5px;
    font-size: 0.9rem;
  }
  .kpi-table textarea {
    resize: vertical;
    min-height: 40px;
  }
  .subsection-header {
    font-weight: bold;
    background-color: #e9ecef;
    padding: 4px 8px;
    margin-bottom: 8px;
    font-size: 0.9rem;
  }
  .section-container {
    margin-bottom: 20px;
  }
  .kpi-name-label {
    font-weight: normal;
    padding: 4px 6px;
    background-color: transparent;
    border: none;
    vertical-align: top;
    color: #000;
  }
</style>

<?php
// Define KPI names - Update these as needed
$kpiNames = [
    1 => 'EBITDA',
    2 => 'Gross Margin',
    3 => 'GM vs Payroll',
    4 => 'Payroll % of Sales',
    5 => 'Sales'
];

// Get location number from session (set during login)
$locationNumber = isset($_SESSION['locID']) ? $_SESSION['locID'] : '';

// Get branch manager from database (same as BranchLookBack)
$branchManager = '';
$conn = new mysqli($config['dbServer'], $config['dbUser'], $config['dbPassword'], $config['dbName']);
if (!$conn->connect_error) {
    $sql = "SELECT manager FROM locations WHERE name = '".$conn->real_escape_string($_SESSION['locationName'])."' LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $branchManager = $row['manager'];
    }
    $conn->close();
}

$currentMonth = date("F", strtotime("-2 months")); // Default to two months ago
$currentYear = date("Y");

// Check if there's an existing draft - either from URL parameter or for this location/month/year
$existingDraft = null;
$draftId = isset($_GET['draft']) ? intval($_GET['draft']) : 0;

if ($draftId > 0) {
    // Load specific draft by ID
    $conn = new mysqli($config['dbServer'], $config['dbUser'], $config['dbPassword'], $config['dbName']);
    if (!$conn->connect_error) {
        // Check for both 'guest' and 'Guest User' to handle old entries
        $draftSql = "SELECT * FROM kpiReview WHERE id = $draftId AND status = 'DRAFT' LIMIT 1";
        $draftResult = $conn->query($draftSql);
        if ($draftResult && $draftResult->num_rows > 0) {
            $existingDraft = $draftResult->fetch_assoc();
            // Update current month/year/location from draft
            if ($existingDraft) {
                $currentMonth = $existingDraft['month'];
                $currentYear = $existingDraft['year'];
                $locationNumber = $existingDraft['location_number'];
            }
        }
        $conn->close();
    }
} else if (!empty($locationNumber)) {
    // Check for draft for current location/month/year
    $conn = new mysqli($config['dbServer'], $config['dbUser'], $config['dbPassword'], $config['dbName']);
    if (!$conn->connect_error) {
        // Only check for drafts belonging to the current user
        $usernameEscaped = $conn->real_escape_string($_SESSION['username'] ?? '');
        
        // Build the submitted_by condition - only match current user or 'Guest User' if username is empty/guest
        $submittedByCondition = "submitted_by = '$usernameEscaped'";
        if (empty($usernameEscaped) || strtolower($usernameEscaped) === 'guest') {
            // If user is guest, also check for 'Guest User' legacy entries
            $submittedByCondition = "(submitted_by = '$usernameEscaped' OR submitted_by = 'Guest User')";
        }
        
        $draftSql = "SELECT * FROM kpiReview WHERE location_number = '" . $conn->real_escape_string($locationNumber) . "' 
                     AND month = '" . $conn->real_escape_string($currentMonth) . "' 
                     AND year = $currentYear 
                     AND status = 'DRAFT' 
                     AND $submittedByCondition
                     LIMIT 1";
        $draftResult = $conn->query($draftSql);
        if ($draftResult && $draftResult->num_rows > 0) {
            $existingDraft = $draftResult->fetch_assoc();
        }
        $conn->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] <> 'POST') {
?>
<script>
    $(function(){
    $('#btn_Save').click(function(e) {
        // Check if form is valid before showing loading overlay
        var form = $(this).closest('form')[0];
        if (form && !form.checkValidity()) {
            // Form is invalid - trigger HTML5 validation
            form.reportValidity();
            return false;
        }
        // Form is valid - show loading overlay
        $.LoadingOverlay("show");
    });
    
    // Hide loading overlay if form submission is prevented
    $('form').on('submit', function(e) {
        // If form is invalid, hide the overlay that might have been shown
        var form = this;
        if (!form.checkValidity()) {
            $.LoadingOverlay("hide");
        }
    });
    
    // Add confirmation for Publish button
    $('#btn_Publish').click(function(e) {
        var action = $(this).val();
        if (action === 'publish') {
            // COMMENTED OUT: Field validation removed - allow publishing with incomplete fields
            // Users can now publish reviews even if not all fields are filled
            /*
            // Validate all fields are filled out
            var missingFields = [];
            
            // Check month
            if (!$('#month').val()) {
                missingFields.push('Month');
            }
            
            // Check branch manager
            if (!$('#branchManager').val() || $('#branchManager').val().trim() === '') {
                missingFields.push('Branch Manager');
            }
            
            // Check Morale Meter
            if (!$('#moraleMeter').val()) {
                missingFields.push('Morale Meter');
            }
            
            // Check Positive Results - Month (5 KPIs)
            for (var i = 1; i <= 5; i++) {
                var commentsVal = $('textarea[name="positive_month_comments_' + i + '"]').val();
                if (!commentsVal || commentsVal.trim() === '') {
                    missingFields.push('Positive Results - Month KPI ' + i);
                }
            }
            
            // Check Positive Results - YTD (5 KPIs)
            for (var i = 1; i <= 5; i++) {
                var commentsVal = $('textarea[name="positive_ytd_comments_' + i + '"]').val();
                if (!commentsVal || commentsVal.trim() === '') {
                    missingFields.push('Positive Results - YTD KPI ' + i);
                }
            }
            
            // Check Challenges - Month (5 KPIs)
            for (var i = 1; i <= 5; i++) {
                var commentsVal = $('textarea[name="challenge_month_comments_' + i + '"]').val();
                if (!commentsVal || commentsVal.trim() === '') {
                    missingFields.push('Challenges - Month KPI ' + i);
                }
            }
            
            // Check Challenges - YTD (5 KPIs)
            for (var i = 1; i <= 5; i++) {
                var commentsVal = $('textarea[name="challenge_ytd_comments_' + i + '"]').val();
                if (!commentsVal || commentsVal.trim() === '') {
                    missingFields.push('Challenges - YTD KPI ' + i);
                }
            }
            
            // If any fields are missing, prevent submission
            if (missingFields.length > 0) {
                e.preventDefault();
                var message = 'Please fill out all required fields before publishing:\n\n';
                missingFields.forEach(function(field) {
                    message += '• ' + field + '\n';
                });
                message += '\nAll KPI fields and their corresponding comments must be completed.';
                alert(message);
                return false;
            }
            */
            
            // Show confirmation (validation removed - users can publish with incomplete fields)
            if (!confirm('Are you sure you want to publish this KPI Manager Report?\n\nThis cannot be undone.\n\nClick OK to publish or Cancel to go back.')) {
                e.preventDefault();
                return false;
            }
        }
        $.LoadingOverlay("show");
    });
    
    // Delete draft from form page
    $(document).on('click', '.delete-draft-form', function() {
        var draftId = $(this).data('draft-id');
        var draftName = $(this).data('draft-name');
        var button = $(this);
        
        if (confirm('Are you sure you want to delete the draft for "' + draftName + '"?\n\nThis action cannot be undone.')) {
            // Show loading state
            button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
            
            $.ajax({
                url: 'KPIReviewManage.php',
                method: 'POST',
                data: {
                    action: 'delete_draft',
                    draft_id: draftId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Reload page to refresh drafts list
                        location.reload();
                    } else {
                        alert('Error deleting draft: ' + (response.error || 'Unknown error'));
                        button.prop('disabled', false).html('<span class="bi bi-trash"></span> Delete');
                    }
                },
                error: function() {
                    alert('Error deleting draft. Please try again.');
                    button.prop('disabled', false).html('<span class="bi bi-trash"></span> Delete');
                }
            });
        }
    });
    
});
</script>
<div class="p-md-2 m-md-2 bg-white">
<div class="container">

<?php
// Get user's drafts for display on form page
$userDrafts = [];
if (isset($_SESSION['username'])) {
    $conn = new mysqli($config['dbServer'], $config['dbUser'], $config['dbPassword'], $config['dbName']);
    if (!$conn->connect_error) {
        $username = $_SESSION['username'];
        // Query for drafts submitted by this user (including 'guest')
        // Check for both 'guest' and 'Guest User' to handle old entries
        $usernameEscaped = $conn->real_escape_string($username);
        $draftSql = "SELECT id, month, year, location_name, location_number, created_at, updated_at, status, submitted_by
                     FROM kpiReview 
                     WHERE (submitted_by = '$usernameEscaped' OR submitted_by = 'Guest User')
                     AND status = 'DRAFT' 
                     ORDER BY updated_at DESC, year DESC, month DESC";
        $draftResult = $conn->query($draftSql);
        if ($draftResult && $draftResult->num_rows > 0) {
            while($draftRow = $draftResult->fetch_assoc()) {
                $userDrafts[] = $draftRow;
            }
        }
        $conn->close();
    }
}

?>

<?php if ($existingDraft): ?>
<div class="alert alert-warning" role="alert">
<span class="bi bi-file-earmark-text" style="vertical-align: middle;"> <Strong>Editing Draft:</strong>
You are editing a saved draft for <strong><?php echo htmlspecialchars($existingDraft['location_name']); ?></strong> - <?php echo htmlspecialchars($existingDraft['month']); ?> <?php echo htmlspecialchars($existingDraft['year']); ?>. 
Your previous entries have been loaded below. You can continue editing and either <strong>Save Draft</strong> again or <strong>Publish</strong> when ready.
<?php if ($existingDraft['updated_at']): ?>
<br><small class="text-muted">Last saved: <?php echo date('m/d/Y g:i A', strtotime($existingDraft['updated_at'])); ?></small>
<?php endif; ?>
</div>
<?php else: ?>
<div class="alert alert-info" role="alert">
<span class="bi bi-info-circle" style="vertical-align: middle;"> <Strong>Notice:</strong>
This form saves your information as you enter it. You can close your browser and return later to continue filling it out. Use "Save Draft" to save without publishing, or "Publish" to make it visible in the management page.
</div>
<?php endif; ?>
<div class="alert alert-primary" role="alert">
<span class="bi bi-graph-up" style="vertical-align: middle;"></span> <strong>Reference:</strong> 
<a href="https://app.powerbi.com/groups/32b8582a-aa55-4309-a5ca-fb0b015f8e69/reports/22dff34a-2408-4c10-b002-6393856dd270/9db27aad8d9f5f756af6?experience=power-bi" target="_blank" class="alert-link">View Branch KPIs Dashboard (Power BI)</a>
</div>
</div>
</div>

<div class="bg-white">
  <div class="container border-bottom">

<form data-persist="garlic" method="post" enctype="multipart/form-data">
<input type="hidden" name="supp_Name" value="<?php echo $_SESSION['username'];?>">
<input type="hidden" name="locationNumber" value="<?php echo htmlspecialchars($locationNumber);?>">
<input type="hidden" name="year" value="<?php echo $currentYear;?>">

<div class="d-flex justify-content-between align-items-center border-bottom mt-4 pb-2">
  <h3 class="mb-0" style="line-height: 1.2;"><span class="bi bi-graph-up" style="vertical-align: middle;"></span> KPI Manager Report - LOC #<?php echo htmlspecialchars($locationNumber);?></h3>
  <div class="d-flex align-items-center">
    <?php
    // Always show "Manage" button to view submitted reviews
    echo '<a href="Manage.php" class="btn btn-info btn-sm me-2" style="line-height: 1.5;" title="View and manage all submitted KPI reviews">';
    echo '<span class="bi bi-list-check"></span> Manage Reviews';
    echo '</a>';
    
    // Show "View Drafts" button if user has drafts - positioned next to title
    // Also show "Start New Form" button when editing a draft
    if (!empty($userDrafts) || $existingDraft) {
        if (!empty($userDrafts)) {
            $draftCount = count($userDrafts);
            echo '<button type="button" class="btn btn-warning btn-sm me-2" data-bs-toggle="modal" data-bs-target="#draftsModal" style="line-height: 1.5;">';
            echo '<span class="bi bi-file-earmark-text"></span> Drafts <span class="badge bg-dark">' . $draftCount . '</span>';
            echo '</button>';
        }
        if ($existingDraft) {
            echo '<a href="index.php" class="btn btn-outline-secondary btn-sm" style="line-height: 1.5;"><span class="bi bi-plus-circle"></span> New</a>';
        }
    }
    ?>
  </div>
</div>

<div class="row mb-3 mt-2">
  <div class="col-sm-4 mt-2 mb-2">
    <label for="month" style="width:100px; font-size:0.9em;"><b>Month:</b></label>
    <select name="month" id="month" class="form-control form-control-sm" style="width:180px" required>
      <option <?php if (($existingDraft ? $existingDraft['month'] : $currentMonth) == 'January') {echo "Selected";}?> value="January">January</option>
      <option <?php if (($existingDraft ? $existingDraft['month'] : $currentMonth) == 'February') {echo "Selected";}?> value="February">February</option>
      <option <?php if (($existingDraft ? $existingDraft['month'] : $currentMonth) == 'March') {echo "Selected";}?> value="March">March</option>
      <option <?php if (($existingDraft ? $existingDraft['month'] : $currentMonth) == 'April') {echo "Selected";}?> value="April">April</option>
      <option <?php if (($existingDraft ? $existingDraft['month'] : $currentMonth) == 'May') {echo "Selected";}?> value="May">May</option>
      <option <?php if (($existingDraft ? $existingDraft['month'] : $currentMonth) == 'June') {echo "Selected";}?> value="June">June</option>
      <option <?php if (($existingDraft ? $existingDraft['month'] : $currentMonth) == 'July') {echo "Selected";}?> value="July">July</option>
      <option <?php if (($existingDraft ? $existingDraft['month'] : $currentMonth) == 'August') {echo "Selected";}?> value="August">August</option>
      <option <?php if (($existingDraft ? $existingDraft['month'] : $currentMonth) == 'September') {echo "Selected";}?> value="September">September</option>
      <option <?php if (($existingDraft ? $existingDraft['month'] : $currentMonth) == 'October') {echo "Selected";}?> value="October">October</option>
      <option <?php if (($existingDraft ? $existingDraft['month'] : $currentMonth) == 'November') {echo "Selected";}?> value="November">November</option>
      <option <?php if (($existingDraft ? $existingDraft['month'] : $currentMonth) == 'December') {echo "Selected";}?> value="December">December</option>
    </select>
  </div>
  <div class="col-sm-4 mt-2 mb-2">
    <label for="branchManager" style="width:100px; font-size:0.9em;"><b>Manager:</b></label>
    <input type="text" name="branchManager" id="branchManager" class="form-control form-control-sm" style="width:180px" value="<?php echo htmlspecialchars($existingDraft ? $existingDraft['branch_manager'] : $branchManager);?>" readonly>
  </div>
  <div class="col-sm-4 mt-2 mb-2">
    <label for="locationName" style="width:100px; font-size:0.9em;"><b>Location:</b></label>
    <input type="text" name="locationName" id="locationName" class="form-control form-control-sm" style="width:180px" value="<?php echo htmlspecialchars($_SESSION['locationName']);?>" readonly>
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
      <div class="subsection-header">MONTH</div>
      <table class="kpi-table">
        <thead>
          <tr>
            <th style="width: 30%;">KPI</th>
            <th style="width: 70%;">Comments</th>
          </tr>
        </thead>
        <tbody>
          <?php for ($i = 1; $i <= 5; $i++): ?>
          <tr>
            <td class="kpi-name-label"><?php echo htmlspecialchars($kpiNames[$i]); ?></td>
            <td><textarea name="positive_month_comments_<?php echo $i; ?>" rows="2" placeholder="Enter comments"><?php echo htmlspecialchars($existingDraft ? ($existingDraft['positive_month_comments_' . $i] ?? '') : '');?></textarea></td>
          </tr>
          <?php endfor; ?>
          <tr>
            <td><strong>Other Comments</strong></td>
            <td><textarea name="positive_month_other" rows="2" placeholder="Enter other comments"><?php echo htmlspecialchars($existingDraft ? ($existingDraft['positive_month_other'] ?? '') : '');?></textarea></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- YEAR TO DATE Subsection -->
    <div class="col-md-6">
      <div class="subsection-header">YEAR TO DATE</div>
      <table class="kpi-table">
        <thead>
          <tr>
            <th style="width: 30%;">KPI</th>
            <th style="width: 70%;">Comments</th>
          </tr>
        </thead>
        <tbody>
          <?php for ($i = 1; $i <= 5; $i++): ?>
          <tr>
            <td class="kpi-name-label"><?php echo htmlspecialchars($kpiNames[$i]); ?></td>
            <td><textarea name="positive_ytd_comments_<?php echo $i; ?>" rows="2" placeholder="Enter comments"><?php echo htmlspecialchars($existingDraft ? ($existingDraft['positive_ytd_comments_' . $i] ?? '') : '');?></textarea></td>
          </tr>
          <?php endfor; ?>
          <tr>
            <td><strong>Other Comments</strong></td>
            <td><textarea name="positive_ytd_other" rows="2" placeholder="Enter other comments"><?php echo htmlspecialchars($existingDraft ? ($existingDraft['positive_ytd_other'] ?? '') : '');?></textarea></td>
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
      <div class="subsection-header">MONTH</div>
      <table class="kpi-table">
        <thead>
          <tr>
            <th style="width: 30%;">KPI</th>
            <th style="width: 70%;">Comments</th>
          </tr>
        </thead>
        <tbody>
          <?php for ($i = 1; $i <= 5; $i++): ?>
          <tr>
            <td class="kpi-name-label"><?php echo htmlspecialchars($kpiNames[$i]); ?></td>
            <td><textarea name="challenge_month_comments_<?php echo $i; ?>" rows="2" placeholder="Enter comments"><?php echo htmlspecialchars($existingDraft ? ($existingDraft['challenge_month_comments_' . $i] ?? '') : '');?></textarea></td>
          </tr>
          <?php endfor; ?>
          <tr>
            <td><strong>Other Comments</strong></td>
            <td><textarea name="challenge_month_other" rows="2" placeholder="Enter other comments"><?php echo htmlspecialchars($existingDraft ? ($existingDraft['challenge_month_other'] ?? '') : '');?></textarea></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- YEAR TO DATE Subsection -->
    <div class="col-md-6">
      <div class="subsection-header">YEAR TO DATE</div>
      <table class="kpi-table">
        <thead>
          <tr>
            <th style="width: 30%;">KPI</th>
            <th style="width: 70%;">Comments</th>
          </tr>
        </thead>
        <tbody>
          <?php for ($i = 1; $i <= 5; $i++): ?>
          <tr>
            <td class="kpi-name-label"><?php echo htmlspecialchars($kpiNames[$i]); ?></td>
            <td><textarea name="challenge_ytd_comments_<?php echo $i; ?>" rows="2" placeholder="Enter comments"><?php echo htmlspecialchars($existingDraft ? ($existingDraft['challenge_ytd_comments_' . $i] ?? '') : '');?></textarea></td>
          </tr>
          <?php endfor; ?>
          <tr>
            <td><strong>Other Comments</strong></td>
            <td><textarea name="challenge_ytd_other" rows="2" placeholder="Enter other comments"><?php echo htmlspecialchars($existingDraft ? ($existingDraft['challenge_ytd_other'] ?? '') : '');?></textarea></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Morale Meter Section -->
<div class="section-container">
  <div class="kpi-section-header morale-meter">
  <div class="kpi-section-header morale-meter">
    <h4 class="mb-0">MORALE METER</h4>
  </div>
  <div class="alert alert-info mt-3">
    <p class="mb-2"><strong>A measure of 'BranchTeam' sentiment. 1 = Poor, 3 = Good, 5 = Excellent</strong></p>
    <div class="row">
      <div class="col-md-4">
        <div class="form-group">
          <label for="moraleMeter"><b>Select Rating:</b></label>
          <select name="moraleMeter" id="moraleMeter" class="form-control">
            <option value="">Select Rating</option>
            <option value="1" <?php echo ($existingDraft && $existingDraft['morale_meter'] == 1) ? 'selected' : '';?>>1 - Poor</option>
            <option value="2" <?php echo ($existingDraft && $existingDraft['morale_meter'] == 2) ? 'selected' : '';?>>2 - Below Average</option>
            <option value="3" <?php echo ($existingDraft && $existingDraft['morale_meter'] == 3) ? 'selected' : '';?>>3 - Good</option>
            <option value="4" <?php echo ($existingDraft && $existingDraft['morale_meter'] == 4) ? 'selected' : '';?>>4 - Very Good</option>
            <option value="5" <?php echo ($existingDraft && $existingDraft['morale_meter'] == 5) ? 'selected' : '';?>>5 - Excellent</option>
          </select>
        </div>
      </div>
      <div class="col-md-8">
        <div class="form-group">
          <label for="moraleNotes"><b>Notes:</b></label>
          <textarea name="moraleNotes" id="moraleNotes" class="form-control" rows="3" placeholder="Enter any additional notes or comments about team morale..."><?php echo htmlspecialchars($existingDraft ? ($existingDraft['morale_notes'] ?? '') : '');?></textarea>
        </div>
      </div>
    </div>
  </div>
</div>

<br><br>
<center>
<button type="submit" name="action" value="save" class="btn btn-secondary btn-lg me-3" id="btn_Save">Save Draft</button>
<button type="submit" name="action" value="publish" class="btn btn-primary btn-lg" id="btn_Publish">Publish</button>
</center>
</form>
</div></div></div>

<?php
// Drafts Modal
if (!empty($userDrafts)) {
    echo '<div class="modal fade" id="draftsModal" tabindex="-1" aria-labelledby="draftsModalLabel" aria-hidden="true">';
    echo '<div class="modal-dialog modal-lg">';
    echo '<div class="modal-content">';
    echo '<div class="modal-header bg-warning bg-opacity-10">';
    echo '<h5 class="modal-title" id="draftsModalLabel"><span class="bi bi-file-earmark-text"></span> My Drafts</h5>';
    echo '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
    echo '</div>';
    echo '<div class="modal-body">';
    echo '<p class="text-muted mb-3">You have <strong>' . count($userDrafts) . '</strong> saved draft(s). Click on a draft to continue editing:</p>';
    echo '<div class="list-group">';
    foreach ($userDrafts as $draft) {
        $draftUrl = 'index.php?draft=' . $draft['id'];
        $lastUpdated = date('m/d/Y g:i A', strtotime($draft['updated_at']));
        $isCurrentDraft = ($existingDraft && $existingDraft['id'] == $draft['id']);
        $itemClass = $isCurrentDraft ? 'list-group-item-warning active' : '';
        
        echo '<div class="list-group-item ' . $itemClass . '">';
        echo '<div class="d-flex w-100 justify-content-between align-items-center">';
        echo '<div class="flex-grow-1">';
        if ($isCurrentDraft) {
            echo '<h6 class="mb-1"><span class="bi bi-file-earmark-text-fill text-warning"></span> <strong>CURRENTLY EDITING:</strong> ' . htmlspecialchars($draft['location_name']) . ' - ' . htmlspecialchars($draft['month']) . ' ' . htmlspecialchars($draft['year']) . '</h6>';
        } else {
            echo '<a href="' . htmlspecialchars($draftUrl) . '" class="text-decoration-none">';
            echo '<h6 class="mb-1"><span class="bi bi-file-earmark-text text-warning"></span> ' . htmlspecialchars($draft['location_name']) . ' - ' . htmlspecialchars($draft['month']) . ' ' . htmlspecialchars($draft['year']) . '</h6>';
            echo '</a>';
        }
        echo '<small class="text-muted">LOC #' . htmlspecialchars($draft['location_number']) . ' | Last updated: ' . $lastUpdated . '</small>';
        echo '</div>';
        echo '<div class="ms-3">';
        if (!$isCurrentDraft) {
            echo '<a href="' . htmlspecialchars($draftUrl) . '" class="btn btn-sm btn-warning me-2"><span class="bi bi-pencil"></span> Edit</a>';
            echo '<button type="button" class="btn btn-sm btn-danger delete-draft-form" data-draft-id="' . $draft['id'] . '" data-draft-name="' . htmlspecialchars($draft['location_name'] . ' - ' . $draft['month'] . ' ' . $draft['year']) . '"><span class="bi bi-trash"></span> Delete</button>';
        } else {
            echo '<span class="badge bg-warning text-dark">Currently Editing</span>';
        }
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



<?php
} else {
    // Form submitted - Save to database
    $conn = new mysqli($config['dbServer'], $config['dbUser'], $config['dbPassword'], $config['dbName']);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Determine action: 'save' (draft) or 'publish'
    $action = isset($_POST['action']) ? $_POST['action'] : 'save';
    $status = ($action == 'publish') ? 'PUBLISHED' : 'DRAFT';
    // COMMENTED OUT: Email sending disabled
    // $sendEmail = ($action == 'publish');
    $sendEmail = false; // Emails disabled
    
    // Escape all form inputs
    $month = $conn->real_escape_string($_POST['month']);
    $year = intval($_POST['year']);
    $branchManager = !empty($_POST['branchManager']) ? $conn->real_escape_string($_POST['branchManager']) : NULL;
    $locationName = $conn->real_escape_string($_POST['locationName']);
    $locationNumber = $conn->real_escape_string($_POST['locationNumber']);
    $submittedBy = $conn->real_escape_string($_POST['supp_Name']);
    
    // Positive Results - Month (KPI names are pre-populated, so kpi fields are NULL)
    $positive_month_comments_1 = !empty($_POST['positive_month_comments_1']) ? $conn->real_escape_string($_POST['positive_month_comments_1']) : NULL;
    $positive_month_comments_2 = !empty($_POST['positive_month_comments_2']) ? $conn->real_escape_string($_POST['positive_month_comments_2']) : NULL;
    $positive_month_comments_3 = !empty($_POST['positive_month_comments_3']) ? $conn->real_escape_string($_POST['positive_month_comments_3']) : NULL;
    $positive_month_comments_4 = !empty($_POST['positive_month_comments_4']) ? $conn->real_escape_string($_POST['positive_month_comments_4']) : NULL;
    $positive_month_comments_5 = !empty($_POST['positive_month_comments_5']) ? $conn->real_escape_string($_POST['positive_month_comments_5']) : NULL;
    $positive_month_other = !empty($_POST['positive_month_other']) ? $conn->real_escape_string($_POST['positive_month_other']) : NULL;
    
    // Positive Results - YTD (KPI names are pre-populated, so kpi fields are NULL)
    $positive_ytd_comments_1 = !empty($_POST['positive_ytd_comments_1']) ? $conn->real_escape_string($_POST['positive_ytd_comments_1']) : NULL;
    $positive_ytd_comments_2 = !empty($_POST['positive_ytd_comments_2']) ? $conn->real_escape_string($_POST['positive_ytd_comments_2']) : NULL;
    $positive_ytd_comments_3 = !empty($_POST['positive_ytd_comments_3']) ? $conn->real_escape_string($_POST['positive_ytd_comments_3']) : NULL;
    $positive_ytd_comments_4 = !empty($_POST['positive_ytd_comments_4']) ? $conn->real_escape_string($_POST['positive_ytd_comments_4']) : NULL;
    $positive_ytd_comments_5 = !empty($_POST['positive_ytd_comments_5']) ? $conn->real_escape_string($_POST['positive_ytd_comments_5']) : NULL;
    $positive_ytd_other = !empty($_POST['positive_ytd_other']) ? $conn->real_escape_string($_POST['positive_ytd_other']) : NULL;
    
    // Challenges - Month (KPI names are pre-populated, so kpi fields are NULL)
    $challenge_month_comments_1 = !empty($_POST['challenge_month_comments_1']) ? $conn->real_escape_string($_POST['challenge_month_comments_1']) : NULL;
    $challenge_month_comments_2 = !empty($_POST['challenge_month_comments_2']) ? $conn->real_escape_string($_POST['challenge_month_comments_2']) : NULL;
    $challenge_month_comments_3 = !empty($_POST['challenge_month_comments_3']) ? $conn->real_escape_string($_POST['challenge_month_comments_3']) : NULL;
    $challenge_month_comments_4 = !empty($_POST['challenge_month_comments_4']) ? $conn->real_escape_string($_POST['challenge_month_comments_4']) : NULL;
    $challenge_month_comments_5 = !empty($_POST['challenge_month_comments_5']) ? $conn->real_escape_string($_POST['challenge_month_comments_5']) : NULL;
    $challenge_month_other = !empty($_POST['challenge_month_other']) ? $conn->real_escape_string($_POST['challenge_month_other']) : NULL;
    
    // Challenges - YTD (KPI names are pre-populated, so kpi fields are NULL)
    $challenge_ytd_comments_1 = !empty($_POST['challenge_ytd_comments_1']) ? $conn->real_escape_string($_POST['challenge_ytd_comments_1']) : NULL;
    $challenge_ytd_comments_2 = !empty($_POST['challenge_ytd_comments_2']) ? $conn->real_escape_string($_POST['challenge_ytd_comments_2']) : NULL;
    $challenge_ytd_comments_3 = !empty($_POST['challenge_ytd_comments_3']) ? $conn->real_escape_string($_POST['challenge_ytd_comments_3']) : NULL;
    $challenge_ytd_comments_4 = !empty($_POST['challenge_ytd_comments_4']) ? $conn->real_escape_string($_POST['challenge_ytd_comments_4']) : NULL;
    $challenge_ytd_comments_5 = !empty($_POST['challenge_ytd_comments_5']) ? $conn->real_escape_string($_POST['challenge_ytd_comments_5']) : NULL;
    $challenge_ytd_other = !empty($_POST['challenge_ytd_other']) ? $conn->real_escape_string($_POST['challenge_ytd_other']) : NULL;
    
    // Morale Meter
    $moraleMeter = !empty($_POST['moraleMeter']) ? intval($_POST['moraleMeter']) : NULL;
    $moraleNotes = !empty($_POST['moraleNotes']) ? $conn->real_escape_string($_POST['moraleNotes']) : NULL;
    
    // Check if draft exists - either by ID from URL or for this location/month/year
    $checkDraftId = isset($_GET['draft']) ? intval($_GET['draft']) : 0;
    $draftExists = false;
    $draftId = 0;
    
    if ($checkDraftId > 0) {
        // Check if this specific draft ID exists and belongs to current user
        // Check for both 'guest' and 'Guest User' to handle old entries
        $checkDraft = "SELECT id FROM kpiReview WHERE id = $checkDraftId AND status = 'DRAFT' AND (submitted_by = '$submittedBy' OR submitted_by = 'Guest User') LIMIT 1";
        $draftResult = $conn->query($checkDraft);
        if ($draftResult && $draftResult->num_rows > 0) {
            $draftRow = $draftResult->fetch_assoc();
            $draftId = $draftRow['id'];
            $draftExists = true;
        }
    } else {
        // Check for draft for this location/month/year
        // Check for both 'guest' and 'Guest User' to handle old entries
        $checkDraft = "SELECT id FROM kpiReview WHERE location_number = '$locationNumber' AND month = '$month' AND year = $year AND status = 'DRAFT' AND (submitted_by = '$submittedBy' OR submitted_by = 'Guest User') LIMIT 1";
        $draftResult = $conn->query($checkDraft);
        if ($draftResult && $draftResult->num_rows > 0) {
            $draftRow = $draftResult->fetch_assoc();
            $draftId = $draftRow['id'];
            $draftExists = true;
        }
    }
    
    // Build SQL - UPDATE if draft exists, INSERT if not
    if ($draftExists && $draftId > 0) {
        // Update existing draft
        $publishedAt = ($status == 'PUBLISHED') ? ", published_at = NOW()" : "";
        $sql = "UPDATE kpiReview SET
            branch_manager = " . ($branchManager ? "'$branchManager'" : "NULL") . ",
            positive_month_kpi_1 = NULL,
            positive_month_comments_1 = " . ($positive_month_comments_1 ? "'$positive_month_comments_1'" : "NULL") . ",
            positive_month_kpi_2 = NULL,
            positive_month_comments_2 = " . ($positive_month_comments_2 ? "'$positive_month_comments_2'" : "NULL") . ",
            positive_month_kpi_3 = NULL,
            positive_month_comments_3 = " . ($positive_month_comments_3 ? "'$positive_month_comments_3'" : "NULL") . ",
            positive_month_kpi_4 = NULL,
            positive_month_comments_4 = " . ($positive_month_comments_4 ? "'$positive_month_comments_4'" : "NULL") . ",
            positive_month_kpi_5 = NULL,
            positive_month_comments_5 = " . ($positive_month_comments_5 ? "'$positive_month_comments_5'" : "NULL") . ",
            positive_month_other = " . ($positive_month_other ? "'$positive_month_other'" : "NULL") . ",
            positive_ytd_kpi_1 = NULL,
            positive_ytd_comments_1 = " . ($positive_ytd_comments_1 ? "'$positive_ytd_comments_1'" : "NULL") . ",
            positive_ytd_kpi_2 = NULL,
            positive_ytd_comments_2 = " . ($positive_ytd_comments_2 ? "'$positive_ytd_comments_2'" : "NULL") . ",
            positive_ytd_kpi_3 = NULL,
            positive_ytd_comments_3 = " . ($positive_ytd_comments_3 ? "'$positive_ytd_comments_3'" : "NULL") . ",
            positive_ytd_kpi_4 = NULL,
            positive_ytd_comments_4 = " . ($positive_ytd_comments_4 ? "'$positive_ytd_comments_4'" : "NULL") . ",
            positive_ytd_kpi_5 = NULL,
            positive_ytd_comments_5 = " . ($positive_ytd_comments_5 ? "'$positive_ytd_comments_5'" : "NULL") . ",
            positive_ytd_other = " . ($positive_ytd_other ? "'$positive_ytd_other'" : "NULL") . ",
            challenge_month_kpi_1 = NULL,
            challenge_month_comments_1 = " . ($challenge_month_comments_1 ? "'$challenge_month_comments_1'" : "NULL") . ",
            challenge_month_kpi_2 = NULL,
            challenge_month_comments_2 = " . ($challenge_month_comments_2 ? "'$challenge_month_comments_2'" : "NULL") . ",
            challenge_month_kpi_3 = NULL,
            challenge_month_comments_3 = " . ($challenge_month_comments_3 ? "'$challenge_month_comments_3'" : "NULL") . ",
            challenge_month_kpi_4 = NULL,
            challenge_month_comments_4 = " . ($challenge_month_comments_4 ? "'$challenge_month_comments_4'" : "NULL") . ",
            challenge_month_kpi_5 = NULL,
            challenge_month_comments_5 = " . ($challenge_month_comments_5 ? "'$challenge_month_comments_5'" : "NULL") . ",
            challenge_month_other = " . ($challenge_month_other ? "'$challenge_month_other'" : "NULL") . ",
            challenge_ytd_kpi_1 = NULL,
            challenge_ytd_comments_1 = " . ($challenge_ytd_comments_1 ? "'$challenge_ytd_comments_1'" : "NULL") . ",
            challenge_ytd_kpi_2 = NULL,
            challenge_ytd_comments_2 = " . ($challenge_ytd_comments_2 ? "'$challenge_ytd_comments_2'" : "NULL") . ",
            challenge_ytd_kpi_3 = NULL,
            challenge_ytd_comments_3 = " . ($challenge_ytd_comments_3 ? "'$challenge_ytd_comments_3'" : "NULL") . ",
            challenge_ytd_kpi_4 = NULL,
            challenge_ytd_comments_4 = " . ($challenge_ytd_comments_4 ? "'$challenge_ytd_comments_4'" : "NULL") . ",
            challenge_ytd_kpi_5 = NULL,
            challenge_ytd_comments_5 = " . ($challenge_ytd_comments_5 ? "'$challenge_ytd_comments_5'" : "NULL") . ",
            challenge_ytd_other = " . ($challenge_ytd_other ? "'$challenge_ytd_other'" : "NULL") . ",
            morale_meter = " . ($moraleMeter ? "$moraleMeter" : "NULL") . ",
            morale_notes = " . ($moraleNotes ? "'$moraleNotes'" : "NULL") . ",
            status = '$status'$publishedAt
            WHERE id = $draftId";
    } else {
        // Insert new record
        $publishedAt = ($status == 'PUBLISHED') ? ", published_at = NOW()" : "";
        $sql = "INSERT INTO kpiReview (
            month, year, branch_manager, location_name, location_number, submitted_by, status,
            positive_month_kpi_1, positive_month_comments_1, positive_month_kpi_2, positive_month_comments_2, 
            positive_month_kpi_3, positive_month_comments_3, positive_month_kpi_4, positive_month_comments_4,
            positive_month_kpi_5, positive_month_comments_5, positive_month_other,
            positive_ytd_kpi_1, positive_ytd_comments_1, positive_ytd_kpi_2, positive_ytd_comments_2,
            positive_ytd_kpi_3, positive_ytd_comments_3, positive_ytd_kpi_4, positive_ytd_comments_4,
            positive_ytd_kpi_5, positive_ytd_comments_5, positive_ytd_other,
            challenge_month_kpi_1, challenge_month_comments_1, challenge_month_kpi_2, challenge_month_comments_2,
            challenge_month_kpi_3, challenge_month_comments_3, challenge_month_kpi_4, challenge_month_comments_4,
            challenge_month_kpi_5, challenge_month_comments_5, challenge_month_other,
            challenge_ytd_kpi_1, challenge_ytd_comments_1, challenge_ytd_kpi_2, challenge_ytd_comments_2,
            challenge_ytd_kpi_3, challenge_ytd_comments_3, challenge_ytd_kpi_4, challenge_ytd_comments_4,
            challenge_ytd_kpi_5, challenge_ytd_comments_5, challenge_ytd_other,
            morale_meter, morale_notes
        ) VALUES (
            '$month', $year, " . ($branchManager ? "'$branchManager'" : "NULL") . ", '$locationName', '$locationNumber', '$submittedBy', '$status',
            NULL, " . ($positive_month_comments_1 ? "'$positive_month_comments_1'" : "NULL") . ",
            NULL, " . ($positive_month_comments_2 ? "'$positive_month_comments_2'" : "NULL") . ",
            NULL, " . ($positive_month_comments_3 ? "'$positive_month_comments_3'" : "NULL") . ",
            NULL, " . ($positive_month_comments_4 ? "'$positive_month_comments_4'" : "NULL") . ",
            NULL, " . ($positive_month_comments_5 ? "'$positive_month_comments_5'" : "NULL") . ",
            " . ($positive_month_other ? "'$positive_month_other'" : "NULL") . ",
            NULL, " . ($positive_ytd_comments_1 ? "'$positive_ytd_comments_1'" : "NULL") . ",
            NULL, " . ($positive_ytd_comments_2 ? "'$positive_ytd_comments_2'" : "NULL") . ",
            NULL, " . ($positive_ytd_comments_3 ? "'$positive_ytd_comments_3'" : "NULL") . ",
            NULL, " . ($positive_ytd_comments_4 ? "'$positive_ytd_comments_4'" : "NULL") . ",
            NULL, " . ($positive_ytd_comments_5 ? "'$positive_ytd_comments_5'" : "NULL") . ",
            " . ($positive_ytd_other ? "'$positive_ytd_other'" : "NULL") . ",
            NULL, " . ($challenge_month_comments_1 ? "'$challenge_month_comments_1'" : "NULL") . ",
            NULL, " . ($challenge_month_comments_2 ? "'$challenge_month_comments_2'" : "NULL") . ",
            NULL, " . ($challenge_month_comments_3 ? "'$challenge_month_comments_3'" : "NULL") . ",
            NULL, " . ($challenge_month_comments_4 ? "'$challenge_month_comments_4'" : "NULL") . ",
            NULL, " . ($challenge_month_comments_5 ? "'$challenge_month_comments_5'" : "NULL") . ",
            " . ($challenge_month_other ? "'$challenge_month_other'" : "NULL") . ",
            NULL, " . ($challenge_ytd_comments_1 ? "'$challenge_ytd_comments_1'" : "NULL") . ",
            NULL, " . ($challenge_ytd_comments_2 ? "'$challenge_ytd_comments_2'" : "NULL") . ",
            NULL, " . ($challenge_ytd_comments_3 ? "'$challenge_ytd_comments_3'" : "NULL") . ",
            NULL, " . ($challenge_ytd_comments_4 ? "'$challenge_ytd_comments_4'" : "NULL") . ",
            NULL, " . ($challenge_ytd_comments_5 ? "'$challenge_ytd_comments_5'" : "NULL") . ",
            " . ($challenge_ytd_other ? "'$challenge_ytd_other'" : "NULL") . ",
            " . ($moraleMeter ? "$moraleMeter" : "NULL") . ", " . ($moraleNotes ? "'$moraleNotes'" : "NULL") . "
        )";
    }
    
    if ($conn->query($sql) === TRUE) {
        $entryId = $draftExists ? $draftId : $conn->insert_id;
        
        // COMMENTED OUT: Actual folder creation disabled - using visual folders instead
        /*
        // Create folder structure for published reviews: shared/KPIReviews/{LocationName}/{Month}
        if ($action == 'publish') {
            // Base directory for KPI reviews - use absolute path from root
            // From Modules/KPIReview/ we need to go up 2 levels to get to root
            // __DIR__ = /home/cjones/Updates to KPI/Modules/KPIReview
            // dirname(dirname(__DIR__)) = /home/cjones/Updates to KPI
            $rootDir = dirname(dirname(__DIR__));
            $baseDir = $rootDir . '/shared/KPIReviews';
            
            // Sanitize location name for folder name (remove special characters, spaces become underscores)
            $locationFolder = preg_replace('/[^a-zA-Z0-9_-]/', '_', $locationName);
            
            // Use the month from the form (not current date) - e.g., "December", "January", etc.
            $monthFolder = $month;
            
            // Full path: shared/KPIReviews/{LocationName}/{Month}
            $folderPath = $baseDir . '/' . $locationFolder . '/' . $monthFolder;
            
            // Create directories if they don't exist (recursive)
            if (!is_dir($folderPath)) {
                if (!mkdir($folderPath, 0775, true) && !is_dir($folderPath)) {
                    error_log("Failed to create KPI Review folder: " . $folderPath);
                    echo '<div class="alert alert-warning">Warning: Could not create folder structure. Please check permissions.</div>';
                } else {
                    error_log("Created KPI Review folder: " . $folderPath);
                }
            }
        }
        */
        
        echo '<div class="p-md-2 m-md-2 bg-white">';
        echo '<div class="container border-bottom text-center"><br>';
        
        if ($action == 'publish') {
            echo '<div class="m-4"><h3>Thank you for publishing your KPI Manager Report!</h3><br>';
            echo '<h4>Your review has been published and is now visible in the management page.</h4><br>';
        } else {
            echo '<div class="m-4"><h3>Draft Saved Successfully!</h3><br>';
            echo '<h4>Your KPI Manager Report has been saved as a draft. You can continue editing it later.</h4><br>';
        }
        
        // COMMENTED OUT: Email sending disabled
        /*
        // Try to send email only if publishing
        $emailSent = false;
        $emailError = '';
        $debugInfo = [];
        
        if ($sendEmail) {
        try {
        
        // Debug: Always log that we're attempting email
        $debugInfo[] = "=== EMAIL ATTEMPT STARTED ===";
        
        // Load Mail classes if not already loaded (they should be from top of file, but ensure they're available)
        if (!class_exists('Mail')) {
            require_once '/usr/share/php/Mail.php';
        }
        if (!class_exists('Mail_mime')) {
            require_once '/usr/share/php/Mail/mime.php';
        }
        
        // Check if Mail class is available
        $mailClassExists = class_exists('Mail');
        $mimeClassExists = class_exists('Mail_mime');
        
        $debugInfo[] = "Mail class exists: " . ($mailClassExists ? "YES" : "NO");
        $debugInfo[] = "Mail_mime class exists: " . ($mimeClassExists ? "YES" : "NO");
        $debugInfo[] = "/usr/share/php/Mail.php: " . (file_exists('/usr/share/php/Mail.php') ? "EXISTS" : "NOT FOUND");
        $debugInfo[] = "/usr/share/php/Mail/mime.php: " . (file_exists('/usr/share/php/Mail/mime.php') ? "EXISTS" : "NOT FOUND");
        
        // Check if Net_SMTP is available (required for SMTP)
        // Try to load Net_SMTP if not already loaded
        if (!class_exists('Net_SMTP')) {
            @require_once '/usr/share/php/Net/SMTP.php';
        }
        $netSMTPExists = @class_exists('Net_SMTP');
        $debugInfo[] = "Net_SMTP class exists: " . ($netSMTPExists ? "YES" : "NO");
        if (!$netSMTPExists) {
            $debugInfo[] = "Tried to load: /usr/share/php/Net/SMTP.php - " . (file_exists('/usr/share/php/Net/SMTP.php') ? "FILE EXISTS" : "FILE NOT FOUND");
        }
        
        if ($mailClassExists && $mimeClassExists && $netSMTPExists) {
            // Use PEAR Mail with SMTP
            // Build email message
        $messageOut = "<h3>KPI Manager Report Submission</h3>";
        $messageOut .= "<p><strong>Location:</strong> " . htmlspecialchars($_POST['locationName']) . " (LOC #" . htmlspecialchars($_POST['locationNumber']) . ")</p>";
        $messageOut .= "<p><strong>Month:</strong> " . htmlspecialchars($_POST['month']) . " " . htmlspecialchars($_POST['year']) . "</p>";
        $messageOut .= "<p><strong>Manager:</strong> " . htmlspecialchars($_POST['branchManager']) . "</p>";
        $messageOut .= "<p><strong>Submitted by:</strong> " . htmlspecialchars($_POST['supp_Name']) . "</p>";
        $messageOut .= "<hr>";
        
        // Positive Results / Wins
        $messageOut .= "<h4>POSITIVE RESULTS / WINS</h4>";
        $messageOut .= "<h5><strong>MONTH</strong></h5>";
        for ($i = 1; $i <= 5; $i++) {
            $kpiName = $kpiNames[$i];
            $comments = $_POST['positive_month_comments_' . $i] ?? '';
            if (!empty($comments)) {
                $messageOut .= "<p><strong>" . htmlspecialchars($kpiName) . ":</strong> " . nl2br(htmlspecialchars($comments)) . "</p>";
            }
        }
        if (!empty($_POST['positive_month_other'])) {
            $messageOut .= "<p><strong>Other Comments:</strong> " . nl2br(htmlspecialchars($_POST['positive_month_other'])) . "</p>";
        }
        
        $messageOut .= "<h5><strong>YEAR TO DATE</strong></h5>";
        for ($i = 1; $i <= 5; $i++) {
            $kpiName = $kpiNames[$i];
            $comments = $_POST['positive_ytd_comments_' . $i] ?? '';
            if (!empty($comments)) {
                $messageOut .= "<p><strong>" . htmlspecialchars($kpiName) . ":</strong> " . nl2br(htmlspecialchars($comments)) . "</p>";
            }
        }
        if (!empty($_POST['positive_ytd_other'])) {
            $messageOut .= "<p><strong>Other Comments:</strong> " . nl2br(htmlspecialchars($_POST['positive_ytd_other'])) . "</p>";
        }
        
        $messageOut .= "<hr>";
        
        // Challenges / Opportunities
        $messageOut .= "<h4>CHALLENGES / OPPORTUNITIES</h4>";
        $messageOut .= "<h5><strong>MONTH</strong></h5>";
        for ($i = 1; $i <= 5; $i++) {
            $kpiName = $kpiNames[$i];
            $comments = $_POST['challenge_month_comments_' . $i] ?? '';
            if (!empty($comments)) {
                $messageOut .= "<p><strong>" . htmlspecialchars($kpiName) . ":</strong> " . nl2br(htmlspecialchars($comments)) . "</p>";
            }
        }
        if (!empty($_POST['challenge_month_other'])) {
            $messageOut .= "<p><strong>Other Comments:</strong> " . nl2br(htmlspecialchars($_POST['challenge_month_other'])) . "</p>";
        }
        
        $messageOut .= "<h5><strong>YEAR TO DATE</strong></h5>";
        for ($i = 1; $i <= 5; $i++) {
            $kpiName = $kpiNames[$i];
            $comments = $_POST['challenge_ytd_comments_' . $i] ?? '';
            if (!empty($comments)) {
                $messageOut .= "<p><strong>" . htmlspecialchars($kpiName) . ":</strong> " . nl2br(htmlspecialchars($comments)) . "</p>";
            }
        }
        if (!empty($_POST['challenge_ytd_other'])) {
            $messageOut .= "<p><strong>Other Comments:</strong> " . nl2br(htmlspecialchars($_POST['challenge_ytd_other'])) . "</p>";
        }
        
        $messageOut .= "<hr>";
        
        // Morale Meter
        $messageOut .= "<h4>MORALE METER</h4>";
        $messageOut .= "<p><strong>Rating:</strong> " . htmlspecialchars($_POST['moraleMeter']) . "</p>";
        if (!empty($_POST['moraleNotes'])) {
            $messageOut .= "<p><strong>Notes:</strong> " . nl2br(htmlspecialchars($_POST['moraleNotes'])) . "</p>";
        }
        
        // Send email
        $headers['From'] = "mailout@mayesh.com";
        $headers['Subject'] = "[KPI Manager Report] " . $_POST['month'] . " " . $_POST['year'] . " KPI Manager Report for " . $_POST['locationName'] . " Published!";
        
        $mail_object = Mail::factory('smtp',
            array ('host' => "smtp.office365.com",
                'auth' => true,
                'port' => 587,
                'username' => "mailout@mayesh.com",
                'password' => "Nav2013"));
        
        $mime = new Mail_mime();
        $mime->setHTMLBody($messageOut);
        $body = $mime->get();
        $headers = $mime->headers($headers);
        
        // Determine email recipients based on location
        $locationName = $_POST['locationName'];
        $locationNumber = $_POST['locationNumber'];
        
        // Base emails (always sent to these)
        $baseEmails = "pdahlson@mayesh.com,bpowell@mayesh.com,vdemetriou@mayesh.com,dburrows@mayesh.com";
        
        // COMMENTED OUT - Location-based email logic (uncomment when ready to implement)
        // Nested comment converted to // style to avoid comment block issues
        // $additionalEmails = [];
        
        // Group 1: Add psessler@mayesh.com
        // Atlanta (026), Miami (017), New Orleans (801), Orlando (802), Detroit (018), 
        // Charlotte (022), Houston (023), Cleveland (024), Raleigh (027), Charleston (028)
        if (in_array($locationNumber, ['026', '017', '801', '802', '018', '022', '023', '024', '027', '028'])) {
            $additionalEmails[] = "psessler@mayesh.com";
        }
        
        // Group 2: Add bfoster@mayesh.com
        // LA Market (001), LAX/Shipping (009), Chandler (008), Phoenix (010), 
        // Seattle (021), San Francisco (025)
        if (in_array($locationNumber, ['001', '009', '008', '010', '021', '025'])) {
            $additionalEmails[] = "bfoster@mayesh.com";
        }
        
        // Group 3: Specialty Market Floral (004), Portland (019)
        // Only base emails (no additional)
        // No action needed - already handled by base emails
        
        // Group 4: Add dgeorgatos@mayesh.com
        // Carlsbad (005), Las Vegas (011), Oxnard (012)
        if (in_array($locationNumber, ['005', '011', '012'])) {
            $additionalEmails[] = "dgeorgatos@mayesh.com";
        }
        
        // Group 5: Add tdahlson@mayesh.com
        // Orange County (003), Riverside (006), Bakersfield (007)
        if (in_array($locationNumber, ['003', '006', '007'])) {
            $additionalEmails[] = "tdahlson@mayesh.com";
        }
        
        // Special case: Raleigh (027) also gets nbarnhill@mayesh.com
        if ($locationNumber == '027') {
            $additionalEmails[] = "nbarnhill@mayesh.com";
        }
        
        // Combine base emails with additional emails
        if (!empty($additionalEmails)) {
            $outEmails = $baseEmails . "," . implode(",", $additionalEmails);
        } else {
            $outEmails = $baseEmails;
        }
        // End of location-based email logic comment
        
        // TEMPORARY: For now, send to cjones@mayesh.com and dburrows@mayesh.com for testing
        $outEmails = "cjones@mayesh.com,dburrows@mayesh.com";
        
        $debugInfo[] = "Email recipients: " . $outEmails;
        
        $mail = $mail_object->send($outEmails, $headers, $body);
        
            // Check for email errors
            if (PEAR::isError($mail)) {
                $emailError = $mail->getMessage();
                $debugInfo[] = "PEAR Error: " . $emailError;
                error_log("KPI Manager Report email error: " . $emailError);
            } else {
                $emailSent = true;
                $debugInfo[] = "Email sent successfully";
            }
        } else {
            // Fallback: Use PHP's built-in mail() function (Net_SMTP not available or PEAR Mail not fully configured)
            if (!$netSMTPExists) {
                $debugInfo[] = "Net_SMTP not available - using PHP mail() function instead";
            } else {
                $debugInfo[] = "PEAR Mail not available, trying PHP mail() fallback";
            }
            
            // Build simple email message
            // COMMENTED OUT - Location-based email logic (uncomment when ready to implement)
            // Nested comment converted to // style to avoid comment block issues
            // Base emails (always sent to these)
            // $baseEmails = "pdahlson@mayesh.com,bpowell@mayesh.com,vdemetriou@mayesh.com,dburrows@mayesh.com";
            
            $locationNumber = $_POST['locationNumber'];
            $additionalEmails = [];
            
            // Group 1: Add psessler@mayesh.com
            // Atlanta (026), Miami (017), New Orleans (801), Orlando (802), Detroit (018), 
            // Charlotte (022), Houston (023), Cleveland (024), Raleigh (027), Charleston (028)
            if (in_array($locationNumber, ['026', '017', '801', '802', '018', '022', '023', '024', '027', '028'])) {
                $additionalEmails[] = "psessler@mayesh.com";
            }
            
            // Group 2: Add bfoster@mayesh.com
            // LA Market (001), LAX/Shipping (009), Chandler (008), Phoenix (010), 
            // Seattle (021), San Francisco (025)
            if (in_array($locationNumber, ['001', '009', '008', '010', '021', '025'])) {
                $additionalEmails[] = "bfoster@mayesh.com";
            }
            
            // Group 3: Specialty Market Floral (004), Portland (019)
            // Only base emails (no additional)
            // No action needed - already handled by base emails
            
            // Group 4: Add dgeorgatos@mayesh.com
            // Carlsbad (005), Las Vegas (011), Oxnard (012)
            if (in_array($locationNumber, ['005', '011', '012'])) {
                $additionalEmails[] = "dgeorgatos@mayesh.com";
            }
            
            // Group 5: Add tdahlson@mayesh.com
            // Orange County (003), Riverside (006), Bakersfield (007)
            if (in_array($locationNumber, ['003', '006', '007'])) {
                $additionalEmails[] = "tdahlson@mayesh.com";
            }
            
            // Special case: Raleigh (027) also gets nbarnhill@mayesh.com
            if ($locationNumber == '027') {
                $additionalEmails[] = "nbarnhill@mayesh.com";
            }
            
            // Combine base emails with additional emails
            if (!empty($additionalEmails)) {
                $to = $baseEmails . "," . implode(",", $additionalEmails);
            } else {
                $to = $baseEmails;
            }
            // End of location-based email logic comment
            
            // TEMPORARY: For now, send to cjones@mayesh.com and dburrows@mayesh.com for testing
            $to = "cjones@mayesh.com,dburrows@mayesh.com";
            $subject = "[KPI Manager Report] " . $_POST['month'] . " " . $_POST['year'] . " KPI Manager Report for " . $_POST['locationName'] . " Submitted!";
            
            $debugInfo[] = "Email recipients (fallback): " . $to;
            
            // Convert HTML to plain text for simple email
            $message = "KPI Manager Report Submission\n\n";
            $message .= "Location: " . $_POST['locationName'] . " (LOC #" . $_POST['locationNumber'] . ")\n";
            $message .= "Month: " . $_POST['month'] . " " . $_POST['year'] . "\n";
            $message .= "Manager: " . $_POST['branchManager'] . "\n";
            $message .= "Submitted by: " . $_POST['supp_Name'] . "\n\n";
            $message .= "--- POSITIVE RESULTS / WINS ---\n";
            $message .= "MONTH:\n";
            for ($i = 1; $i <= 5; $i++) {
                $kpiName = $kpiNames[$i];
                $comments = $_POST['positive_month_comments_' . $i] ?? '';
                if (!empty($comments)) {
                    $message .= $kpiName . ": " . $comments . "\n\n";
                }
            }
            if (!empty($_POST['positive_month_other'])) $message .= "Other: " . $_POST['positive_month_other'] . "\n\n";
            $message .= "YEAR TO DATE:\n";
            for ($i = 1; $i <= 5; $i++) {
                $kpiName = $kpiNames[$i];
                $comments = $_POST['positive_ytd_comments_' . $i] ?? '';
                if (!empty($comments)) {
                    $message .= $kpiName . ": " . $comments . "\n\n";
                }
            }
            if (!empty($_POST['positive_ytd_other'])) $message .= "Other: " . $_POST['positive_ytd_other'] . "\n\n";
            $message .= "\n--- CHALLENGES / OPPORTUNITIES ---\n";
            $message .= "MONTH:\n";
            for ($i = 1; $i <= 5; $i++) {
                $kpiName = $kpiNames[$i];
                $comments = $_POST['challenge_month_comments_' . $i] ?? '';
                if (!empty($comments)) {
                    $message .= $kpiName . ": " . $comments . "\n\n";
                }
            }
            if (!empty($_POST['challenge_month_other'])) $message .= "Other: " . $_POST['challenge_month_other'] . "\n\n";
            $message .= "YEAR TO DATE:\n";
            for ($i = 1; $i <= 5; $i++) {
                $kpiName = $kpiNames[$i];
                $comments = $_POST['challenge_ytd_comments_' . $i] ?? '';
                if (!empty($comments)) {
                    $message .= $kpiName . ": " . $comments . "\n\n";
                }
            }
            if (!empty($_POST['challenge_ytd_other'])) $message .= "Other: " . $_POST['challenge_ytd_other'] . "\n\n";
            $message .= "\n--- MORALE METER ---\n";
            $message .= "Rating: " . $_POST['moraleMeter'] . "\n";
            if (!empty($_POST['moraleNotes'])) $message .= "Notes: " . $_POST['moraleNotes'] . "\n";
            
            $emailHeaders = "From: mailout@mayesh.com\r\n";
            $emailHeaders .= "Reply-To: mailout@mayesh.com\r\n";
            $emailHeaders .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            $mailResult = @mail($to, $subject, $message, $emailHeaders);
            
            if ($mailResult) {
                $emailSent = true;
                $debugInfo[] = "Email sent via PHP mail() function (fallback)";
            } else {
                $emailError = "PHP mail() function also failed - check mail server configuration";
                $debugInfo[] = "PHP mail() function failed";
                error_log("KPI Manager Report: Both PEAR Mail and PHP mail() failed");
                error_log("KPI Manager Report Debug Info: " . implode(" | ", $debugInfo));
            }
        } // End of if/else for email sending
        } catch (Throwable $e) {
            $emailError = "Exception/Error: " . $e->getMessage();
            $debugInfo[] = "FATAL ERROR: " . $e->getMessage();
            $debugInfo[] = "File: " . $e->getFile() . " Line: " . $e->getLine();
            error_log("KPI Manager Report email exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        }
        } // End if ($sendEmail)
        */
        
        // COMMENTED OUT: Email status display
        /*
        // Show email status and debug info (only if publishing)
        if ($sendEmail) {
            echo '<div class="mt-4" style="border-top: 2px solid #ccc; padding-top: 20px;">';
            echo '<h5>Email Status:</h5>';
            if ($emailSent) {
                echo '<p class="text-success"><strong>✓ Email notification sent to cjones@mayesh.com and dburrows@mayesh.com</strong></p>';
            } else {
                echo '<p class="text-warning"><strong>⚠ Email notification could not be sent. Data was saved successfully.</strong></p>';
                if ($emailError) {
                    error_log("KPI Manager Report email error details: " . $emailError);
                }
            }
            
            echo '</div>';
        }
        */
        
        echo '<a href="index.php" class="btn btn-success">Submit Another</a>';
        echo ' <a href="../../" class="btn btn-secondary">Return Home</a>';
        echo '</div></div></div>';
    } else {
        echo '<div class="p-md-2 m-md-2 bg-white">';
        echo '<div class="container border-bottom">';
        echo '<div class="alert alert-danger mt-4">';
        echo '<h4>Error submitting KPI Manager Report</h4>';
        echo '<p>Error: ' . $conn->error . '</p>';
        echo '<p><strong>Note:</strong> The database table may need to be created first. Please contact your administrator.</p>';
        echo '<br><a href="index.php" class="btn btn-primary">Try Again</a>';
        echo '</div></div></div>';
    }
    
    $conn->close();
    
    // Optional: Display submitted data for confirmation
    echo '<div class="container mt-4">';
    echo '<div class="card">';
    echo '<div class="card-header bg-info text-white"><h5>Submitted Data:</h5></div>';
    echo '<div class="card-body">';
    echo '<p><strong>Location:</strong> ' . htmlspecialchars($_POST['locationName']) . ' (LOC #' . htmlspecialchars($_POST['locationNumber']) . ')</p>';
    echo '<p><strong>Month:</strong> ' . htmlspecialchars($_POST['month']) . ' ' . htmlspecialchars($_POST['year']) . '</p>';
    echo '<p><strong>Submitted by:</strong> ' . htmlspecialchars($_POST['supp_Name']) . '</p>';
    echo '<p><strong>Morale Meter:</strong> ' . htmlspecialchars($_POST['moraleMeter']) . '</p>';
    if (!empty($_POST['moraleNotes'])) {
        echo '<p><strong>Morale Notes:</strong> ' . nl2br(htmlspecialchars($_POST['moraleNotes'])) . '</p>';
    }
    echo '<hr>';
    echo '<h5>POSITIVE RESULTS / WINS</h5>';
    echo '<h6>MONTH</h6>';
    for ($i = 1; $i <= 5; $i++) {
        $kpiName = $kpiNames[$i];
        $comments = $_POST['positive_month_comments_' . $i] ?? '';
        if (!empty($comments)) {
            echo '<p><strong>' . htmlspecialchars($kpiName) . ':</strong> ' . nl2br(htmlspecialchars($comments)) . '</p>';
        }
    }
    if (!empty($_POST['positive_month_other'])) {
        echo '<p><strong>Other Comments:</strong> ' . nl2br(htmlspecialchars($_POST['positive_month_other'])) . '</p>';
    }
    echo '<h6>YEAR TO DATE</h6>';
    for ($i = 1; $i <= 5; $i++) {
        $kpiName = $kpiNames[$i];
        $comments = $_POST['positive_ytd_comments_' . $i] ?? '';
        if (!empty($comments)) {
            echo '<p><strong>' . htmlspecialchars($kpiName) . ':</strong> ' . nl2br(htmlspecialchars($comments)) . '</p>';
        }
    }
    if (!empty($_POST['positive_ytd_other'])) {
        echo '<p><strong>Other Comments:</strong> ' . nl2br(htmlspecialchars($_POST['positive_ytd_other'])) . '</p>';
    }
    echo '<hr>';
    echo '<h5>CHALLENGES / OPPORTUNITIES</h5>';
    echo '<h6>MONTH</h6>';
    for ($i = 1; $i <= 5; $i++) {
        $kpiName = $kpiNames[$i];
        $comments = $_POST['challenge_month_comments_' . $i] ?? '';
        if (!empty($comments)) {
            echo '<p><strong>' . htmlspecialchars($kpiName) . ':</strong> ' . nl2br(htmlspecialchars($comments)) . '</p>';
        }
    }
    if (!empty($_POST['challenge_month_other'])) {
        echo '<p><strong>Other Comments:</strong> ' . nl2br(htmlspecialchars($_POST['challenge_month_other'])) . '</p>';
    }
    echo '<h6>YEAR TO DATE</h6>';
    for ($i = 1; $i <= 5; $i++) {
        $kpiName = $kpiNames[$i];
        $comments = $_POST['challenge_ytd_comments_' . $i] ?? '';
        if (!empty($comments)) {
            echo '<p><strong>' . htmlspecialchars($kpiName) . ':</strong> ' . nl2br(htmlspecialchars($comments)) . '</p>';
        }
    }
    if (!empty($_POST['challenge_ytd_other'])) {
        echo '<p><strong>Other Comments:</strong> ' . nl2br(htmlspecialchars($_POST['challenge_ytd_other'])) . '</p>';
    }
    echo '</div></div></div>';
}
?>
</body>
</html>
