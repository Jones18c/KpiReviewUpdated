<?php
/**
 * Script to organize existing published KPI Reviews into folder structure
 * Run this once to organize all existing published reviews
 */

require("../../includes/config.inc.php");

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has access to KPI Review
if(!isset($_SESSION['username'])) {
    header("Location: ../../login.php");
    exit;
}

require("../../includes/security.inc.php");
if (getAccess($_SESSION['username'],'report_scorecard') != 1) {
    die("Access denied. You need permission to access KPI Reviews.");
}

$header['pageTitle'] = "Organize KPI Review Folders";
$header['securityModuleName'] = 'report_scorecard';
require("../../includes/header.inc.php");
?>

<div class="container-fluid mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0"><span class="bi bi-folder-plus"></span> Organize Existing KPI Reviews into Folders</h3>
        </div>
        <div class="card-body">
            <pre style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; max-height: 600px; overflow-y: auto;">
<?php
// Base directory for KPI reviews
// From Modules/KPIReview/ we need to go up 2 levels to get to root
$rootDir = dirname(dirname(__DIR__));
$baseDir = $rootDir . '/shared/KPIReviews';

// Connect to database
$conn = new mysqli($config['dbServer'], $config['dbUser'], $config['dbPassword'], $config['dbName']);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get all published reviews
$sql = "SELECT id, location_name, location_number, month, year, published_at, created_at 
        FROM kpiReview 
        WHERE status = 'PUBLISHED' 
        ORDER BY location_name, month, year";
$result = $conn->query($sql);

if (!$result) {
    die("Error: " . $conn->error);
}

$foldersCreated = 0;
$reviewsProcessed = 0;

if ($result->num_rows > 0) {
    echo "Found " . $result->num_rows . " published reviews to organize.\n\n";
    
    while($row = $result->fetch_assoc()) {
        // Sanitize location name for folder name
        $locationFolder = preg_replace('/[^a-zA-Z0-9_-]/', '_', $row['location_name']);
        $monthFolder = $row['month'];
        
        // Full path: shared/KPIReviews/{LocationName}/{Month}
        $folderPath = $baseDir . '/' . $locationFolder . '/' . $monthFolder;
        
        // Create directories if they don't exist (recursive)
        if (!is_dir($folderPath)) {
            if (mkdir($folderPath, 0775, true)) {
                echo "Created folder: " . $folderPath . "\n";
                $foldersCreated++;
            } else {
                echo "ERROR: Failed to create folder: " . $folderPath . "\n";
            }
        } else {
            echo "Folder already exists: " . $folderPath . "\n";
        }
        
        $reviewsProcessed++;
    }
    
    echo "\n";
    echo "========================================\n";
    echo "Summary:\n";
    echo "Reviews processed: " . $reviewsProcessed . "\n";
    echo "Folders created: " . $foldersCreated . "\n";
    echo "========================================\n";
} else {
    echo "No published reviews found in database.\n";
}

$conn->close();
?>
            </pre>
            <div class="mt-3">
                <a href="Manage.php" class="btn btn-primary"><span class="bi bi-arrow-left"></span> Back to Management Page</a>
            </div>
        </div>
    </div>
</div>

<?php
require("../../includes/footer.inc.php");
?>
