<?php
#ob_start();
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['ref'] = $_SERVER['SCRIPT_NAME'];

if (gethostname() == 'dev962') {
echo "<div class=\"bg-danger text-white d-print-none\"><span class=\"ml-2\">Development Site</span></div>";
} 



if(!isset($_SESSION['username'])) {
  header("Location: \login.php");
  exit();
} else {

  require(__DIR__ . '/config.inc.php');
  require(__DIR__ . '/security.inc.php');
  require(__DIR__ . '/functions.inc.php');

  global $header;
  if (!isset($header) || !isset($header['securityModuleName'])) {
    $header = array(
      'pageTitle' => 'BranchTools',
      'securityModuleName' => 'Core'
    );
  }

  if (getAccess($_SESSION['username'],$header['securityModuleName']) == 0) {
      echo "Permission Denied...";
      exit;
  }
  
  // Log page access (skip if user is admin)
  if (isset($_SESSION['username']) && isset($header['pageTitle'])) {
      if (function_exists('getAccess') && getAccess($_SESSION['username'],'admin') != 1) {
          logPageAccess($_SESSION['username'], $header['pageTitle']);
      }
  }
}

// Function to log page access to MySQL
function logPageAccess($username, $pageTitle) {
    global $config;
    
    // Skip logging if user is admin
    if (getAccess($username, 'admin') == 1) {
        return;
    }
    
    if (empty($username) || empty($pageTitle)) {
        return; // Skip logging if required data is missing
    }
    
    // Clean username (remove @mayesh.com if present)
    if (strpos($username, '@mayesh.com') !== false) {
        $username = substr($username, 0, strpos($username, '@'));
    }
    
    try {
        $conn = new mysqli($config['dbServer'], $config['dbUser'], $config['dbPassword'], $config['dbName']);
        
        if ($conn->connect_error) {
            error_log("Page access log connection failed: " . $conn->connect_error);
            return;
        }
        
        // Try to insert with extended fields first, fall back to basic if columns don't exist
        $pagePath = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        $pageUrl = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $locationId = isset($_SESSION['locID']) ? $_SESSION['locID'] : null;
        $locationName = isset($_SESSION['locationName']) ? $_SESSION['locationName'] : null;
        
        // First try with extended fields
        $stmt = $conn->prepare("INSERT INTO accessLog(site, module, username, page_path, page_url, location_id, location_name, access_time) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        
        if ($stmt) {
            $site = 'branchtools';
            $stmt->bind_param("sssssss", $site, $pageTitle, $username, $pagePath, $pageUrl, $locationId, $locationName);
            
            if (!$stmt->execute()) {
                // If extended insert fails, try basic insert (fallback)
                $stmt->close();
                $stmt = $conn->prepare("INSERT INTO accessLog(site, module, username) VALUES (?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("sss", $site, $pageTitle, $username);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                $stmt->close();
            }
        } else {
            // If prepare fails, try basic insert
            $stmt = $conn->prepare("INSERT INTO accessLog(site, module, username) VALUES (?, ?, ?)");
            if ($stmt) {
                $site = 'branchtools';
                $stmt->bind_param("sss", $site, $pageTitle, $username);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        $conn->close();
    } catch (Exception $e) {
        error_log("Page access log error: " . $e->getMessage());
    }
}

// Determine base path for assets based on how the site is accessed
$basePath = '/';
if (isset($_SERVER['REQUEST_URI'])) {
    $requestUri = $_SERVER['REQUEST_URI'];
    if (strpos($requestUri, '/branchtools/') === 0 || strpos($requestUri, '/branchtools') === 0) {
        $basePath = '/branchtools';
    } elseif (strpos($requestUri, '/kpi/') === 0 || strpos($requestUri, '/kpi') === 0) {
        $basePath = '/kpi';
    }
}

?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Mayesh Branch Manager Tools">
    <meta name="author" content="Chris Cunningham">


    <link rel="stylesheet" href="<?php echo $basePath; ?>/node_modules/bootstrap/dist/css/bootstrap.css">
    <link  rel="stylesheet" href="<?php echo $basePath; ?>/node_modules/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>/node_modules/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>/node_modules/datatables.net-dt/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>/css/branchtools.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>/node_modules/jquery-ui/dist/themes/start/jquery-ui.min.css">

    <script src="<?php echo $basePath; ?>/node_modules/jquery/dist/jquery.min.js"></script>
    <script src="<?php echo $basePath; ?>/node_modules/jquery-ui/dist/jquery-ui.min.js"></script>
    <script src="<?php echo $basePath; ?>/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $basePath; ?>/node_modules/@popperjs/core/dist/umd/popper.js"></script>
    <Script src="<?php echo $basePath; ?>/node_modules/datatables.net/js/jquery.dataTables.min.js"></Script>
    <script src="https://cdn.jsdelivr.net/npm/gasparesganga-jquery-loading-overlay@2.1.6/dist/loadingoverlay.min.js"></script>
 
 <script type="text/javascript" charset="utf-8">
  
    var refreshTime = 1200000; // every 10 minutes in milliseconds
    window.setInterval( function() {
    $.ajax({
        cache: false,
        type: "GET",
        url: "<?php echo $basePath; ?>/includes/Session.php",
        
        success: function(data) {
          console.log("Session updated..");
        }
    });
}, refreshTime );

    
    $(document).ready(function() {
				$('#exportTable').DataTable(),
        $('#loading').hide(),
        $('#latest-features').show(),
        $('#salesBudgets').DataTable(),
        $('#historicalTopCustomers').DataTable( {
        "order": [[ 2, "desc" ]],
        paging: false
      } )
      
    } );      
   
    </script>

<script>
		/*
		 * jQuery UI Autocomplete: Custom HTML in Dropdown
		 * https://salman-w.blogspot.com/2013/12/jquery-ui-autocomplete-examples.html
		 */
		$(function() {
     
			$("#autocomplete").autocomplete({
				delay: 500,
				minLength: 3,
				source: "<?php echo $basePath; ?>/search.php",
        position: {
        my : "right top",
        at: "right bottom"
    },
				focus: function(event, ui) {
					// prevent autocomplete from updating the textbox
					event.preventDefault();
				},
				select: function(event, ui) {
					// prevent autocomplete from updating the textbox
					event.preventDefault();
					// navigate to the selected item's url
					//window.open(ui.item.url);
          window.location.href= "<?php echo $basePath; ?>/" + ui.item.cat + "/" + ui.item.url;
          //this.value = '';
				}			
				
			}).data("ui-autocomplete")._renderItem = function(ul, item) {
				var $div = $("<div style='width:280px'></div>");
       // $("<ion-icon style='font-size: 12px;padding-right:4px'></ion-icon>").attr("name",item.icon).appendTo($div);//.text(item.desc).appendTo($div);
				$("<span class='m-name'></span>").text(item.desc).appendTo($div);
				return $("<li class='border-bottom'></li>").append($div).appendTo(ul);
			};
		});

    
	</script>


    <script type="text/javascript" charset="utf-8">
			$(document).ready(function() {
				$('#EODexportTable').DataTable({
          "searching": false,
          "lengthChange": false
        }
        ),
        $('#example').DataTable({
          "searching": false,
          "lengthChange": false,
          "order": [[ 1, "asc" ]],
          "paging": false
        });        
      } );    
		</script>

<title><?php echo "BranchTools: ".$header['pageTitle'];?></title>


 </head>


<body>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/core-js/2.6.10/shim.min.js"></script>
<script lang="javascript" src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark d-print-none">
<div class="container-fluid">
  
<a class="navbar-brand" href="<?php echo $basePath; ?>/index.php"><img src="<?php echo $basePath; ?>/images/CircleLogo.png" style="height:26px"></a>
  <div class="navbar-brand"><b style="font-size:24px;color:#CFDE00;">BranchTools</b></div>
  
  <div class="navbar-nav">
    <li class="nav-item dropdown mx-2">
        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <span class="bi bi-pencil-square" style="vertical-align: middle;"> Applications</span></a>
        <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
 <div class="ml-4 text-secondary text-center">Applications</div>
<div class="dropdown-divider"></div>
        <li class="dropdown-submenu"><a class="dropdown-item dropdown-toggle" data-bs-toggle="dropdown" href="#"><span class="bi bi-truck" style="vertical-align: middle;"> Deliveries</a></span>
                   <ul class="dropdown-menu">
                   <div class="ml-4 text-secondary text-center">Deliveries</div>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="\Applications\Deliveries\RouteBuilder.php" style="color: #000;"><span class="bi bi-truck" style="vertical-align: middle;"> Route Builder</a></span>
                    <a class="dropdown-item" href="\Applications\Deliveries\AddDelivery.php" style="color: #000;"><span class="bi bi-truck" style="vertical-align: middle;"> Add Delivery</a></span>
                    <a class="dropdown-item" href="\Applications\Deliveries\SchedulePickup.php" style="color: #000;"><span class="bi bi-calendar-plus" style="vertical-align: middle;"> Schedule Pickup</a></span>
                    <a class="dropdown-item" href="\Applications\Deliveries\PickupLocations.php" style="color: #000;"><span class="bi bi-geo-alt" style="vertical-align: middle;"> Manage Pickup Locations</a></span>
                    <a class="dropdown-item" href="\Applications\Deliveries\CustomerSettings.php" style="color: #000;"><span class="bi bi-gear" style="vertical-align: middle;"> Customer Settings</a></span>
                    <a class="dropdown-item" href="\Reports\DeliveryManifest.php" style="color: #000;"><span class="bi bi-file-text" style="vertical-align: middle;"> Delivery Manifest</a></span>
                    <div class="dropdown-divider"></div>
                    <li class="dropdown-submenu">
                      

                        <a class="dropdown-item dropdown-toggle" data-bs-toggle="dropdown" href="#" onmouseover="this.nextElementSibling.style.display='block'" onmouseout="this.nextElementSibling.style.display='none'">
                            <span class="bi bi-clipboard2-data" style="vertical-align: middle;"> Reports</span>
                        </a>
                        <ul class="dropdown-menu dropdown-submenu" style="display: none;" onmouseover="this.style.display='block'" onmouseout="this.style.display='none'">
                            <div class="ml-4 text-secondary text-center">Delivery Reports</div>
                            <div class="dropdown-divider"></div>
                          
                            <a class="dropdown-item" href="\Reports\DeliveriesWithoutFees.php" style="color: #000;">
                                <span class="bi bi-file-text" style="vertical-align: middle;"> Zero-Fee Deliveries</span>
                            </a>
                        </ul>
                    </li>
                   </ul></li>



        <?php
echo '<a class="dropdown-item" href="\Utilities\PricesheetBuilder.php"><span class="bi bi-table" style="vertical-align: middle;"> Pricesheet Builder</a></span>';
$hasPricesheet = getPricesheet($_SESSION['locID']);
        if ($hasPricesheet) {
          echo "<li class=\"nav-item  me-4\">".
          "<a class=\"dropdown-item\" target=\"_blank\" href=\"/Pricesheets/Pricesheet.php?authToken=".$hasPricesheet."\"><span class=\"bi bi-currency-dollar\" style=\"vertical-align: middle;\"> Pricesheet/Availability</a></span>".
          "</li>";
        };
        

https://branchtools-dev.mayesh.com/Applications/GrowerPickup/index.php

                
        ?>
</ul>
</li>
        <li class="nav-item dropdown  me-2">
        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <span class="bi bi-file-earmark-text" style="vertical-align: middle;"> Reports</a></span>
        
        <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
 <div class="ml-4 text-secondary text-center">Reports</div>
<div class="dropdown-divider"></div>
<?php
        echo '<li class="dropdown-submenu"><a class="dropdown-item dropdown-toggle" data-bs-toggle="dropdown" href="#"><span class="bi bi-wrench-adjustable" style="vertical-align: middle;"> Management</a></span>';
        echo '<ul class="dropdown-menu">';
        echo '<div class="ml-4 text-secondary text-center">Management Reports</div>';
       echo '<div class="dropdown-divider"></div>';

        
                  
         echo '<a class="dropdown-item" href="https://app.powerbi.com/groups/32b8582a-aa55-4309-a5ca-fb0b015f8e69/reports/22dff34a-2408-4c10-b002-6393856dd270/2f4875754fa5d5c8ab4b?experience=power-bi" style="color: #000;"><span class="bi bi-speedometer2" style="vertical-align: middle;"> KPI Dashboard</a></span>';

        if (getAccess($_SESSION['username'],'report_scorecard') == 1 or getAccess($_SESSION['username'],'admin') == 1) {
          echo '<a class="dropdown-item" href="'.$basePath.'/Modules/KPIReview/index.php" style="color: #000;"><span class="bi bi-file-earmark-text" style="vertical-align: middle;"> KPI Manager Report</a></span>';
        }  
        if (getAccess($_SESSION['username'],'report_branchlookback') == 1 or getAccess($_SESSION['username'],'admin') == 1) {
          echo '<a class="dropdown-item" href="\Reports\BranchLookBack.php" style="color: #000;"><span class="bi bi-file-earmark-text" style="vertical-align: middle;"> Branch Look-Back</a></span>';
        }
        if (getAccess($_SESSION['username'],'report_scorecard') == 1 or getAccess($_SESSION['username'],'admin') == 1) {
          echo '<a class="dropdown-item" href="\Reports\ScoreCards.php" style="color: #000;"><span class="bi bi-file-earmark-text" style="vertical-align: middle;"> Branch ScoreCard</a></span>';
          }
        echo "</ul></li>";
        ?>
        
        <li class="dropdown-submenu"><a class="dropdown-item dropdown-toggle" data-bs-toggle="dropdown" href="#"><span class="bi bi-cash-coin" style="vertical-align: middle;"> Finance</a></span>
                   <ul class="dropdown-menu">
                   <div class="ml-4 text-secondary text-center">Finance Reports</div>
                    <div class="dropdown-divider"></div>

            <?php
            if (getAccess($_SESSION['username'],'report_eod') == 1 or getAccess($_SESSION['username'],'admin') == 1) {
            echo '<a class="dropdown-item" href="\Reports\EndOfDay.php" style="color: #000;"><span class="bi bi-file-earmark-text" style="vertical-align: middle;"> End Of Day</a></span>';
            }
            if (getAccess($_SESSION['username'],'report_scorecard') == 1 or getAccess($_SESSION['username'],'admin') == 1) {
            echo '<a class="dropdown-item" href="\Reports\Financials.php" style="color: #000;"><span class="bi bi-file-earmark-text" style="vertical-align: middle;"> Branch Financials</a></span>';
            }
            if (getAccess($_SESSION['username'],'report_scorecard') == 1 or getAccess($_SESSION['username'],'admin') == 1) {
              echo '<a class="dropdown-item" href="\Reports\FinancialBudgets.php" style="color: #000;"><span class="bi bi-file-earmark-text" style="vertical-align: middle;"> Financial Budgets</a></span>';
              }
              
        
        ?>
        </ul></li>
 <li class="dropdown-submenu"><a class="dropdown-item dropdown-toggle" data-bs-toggle="dropdown" href="#"><span class="bi bi-cash" style="vertical-align: middle;"> Sales & Credits</a></span>
            <ul class="dropdown-menu">
            <div class="ml-4 text-secondary text-center">Sales & Credits Reports</div>
<div class="dropdown-divider"></div>

        <?php
        if (getAccess($_SESSION['username'],'report_locsalesummary') == 1 or getAccess($_SESSION['username'],'admin') == 1) {
          echo '<a class="dropdown-item" href="\Reports\PurchasingCredits.php" style="color: #000;"><span class="bi bi-file-earmark-text" style="vertical-align: middle;"> Location Purchasing Credits</a></span>';
        }
        ?>
        </ul>
</li>
<li class="dropdown-submenu"><a class="dropdown-item dropdown-toggle" data-bs-toggle="dropdown" href="#"><span class="bi bi-airplane" style="vertical-align: middle;"> Distribution</a></span>
                   <ul class="dropdown-menu">
                   <div class="ml-4 text-secondary text-center">Distribution</div>
                    <div class="dropdown-divider"></div>

            <?php
            
            if (getAccess($_SESSION['username'],'report_mmdist') == 1 or getAccess($_SESSION['username'],'admin') == 1) {
              echo '<a class="dropdown-item" href="\Reports\MayeshMarketPending.php" style="color: #000;"><span class="bi bi-file-earmark-text" style="vertical-align: middle;"> MayeshMarket Pending orders over 24h</a></span>';
            }

            
  
        ?>
        </ul></li>


        <li class="dropdown-submenu"><a class="dropdown-item dropdown-toggle" data-bs-toggle="dropdown" href="#"><span class="bi bi-cart4" style="vertical-align: middle;"> Ecommerce</a></span>
                   <ul class="dropdown-menu">
                   <div class="ml-4 text-secondary text-center">Ecommerce</div>
                    <div class="dropdown-divider"></div>

            <?php
            
            if (getAccess($_SESSION['username'],'report_mayeshmarketlocsales') == 1 or getAccess($_SESSION['username'],'admin') == 1) {
              echo '<a class="dropdown-item" href="\Reports\MayeshMarketSalesSummary.php" style="color: #000;"><span class="bi bi-file-earmark-text" style="vertical-align: middle;"> MayeshMarket Location Sales Summary</a></span>';
            }
  
            if (getAccess($_SESSION['username'],'report_ecommerce') == 1 or getAccess($_SESSION['username'],'admin') == 1) {
              echo '<a class="dropdown-item" href="\Reports\EcomLocationSalesSummary.php" style="color: #000;"><span class="bi bi-file-earmark-text" style="vertical-align: middle;"> Location Sales Summary</a></span>';
            }

            if (getAccess($_SESSION['username'],'report_ecommerce') == 1 or getAccess($_SESSION['username'],'admin') == 1) {
              echo '<a class="dropdown-item" href="\Reports\EcomSalesBySalesPerson.php" style="color: #000;"><span class="bi bi-file-earmark-text" style="vertical-align: middle;"> Sales by SalesPerson</a></span>';
            }
    
  
        ?>
        </ul></li>


        <div class="dropdown-divider"></div>
<?php
if (getAccess($_SESSION['username'],'report_maywearsummary') == 1 or getAccess($_SESSION['username'],'admin') == 1) {
  echo '<a class="dropdown-item" href="\Reports\MayWearSummary.php" style="color: #000;"><span class="bi bi-file-earmark-text" style="vertical-align: middle;"> MayWear Summary</a></span>';
}
echo '<a class="dropdown-item" href="\Reports\MayeshMarketShipments.php" style="color: #000;"><span class="bi bi-file-earmark-text" style="vertical-align: middle;"> MayeshMarket Shipments</a></span>';
echo '<a class="dropdown-item" href="\Reports\MiamiBoxlotsProcessing" style="color: #000;"><span class="bi bi-file-earmark-text" style="vertical-align: middle;"> Miami Boxlots Processing</a></span>';
?>
        </ul>
      </li>




        <li class="nav-item dropdown  me-2">
        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <span class="bi bi-input-cursor-text" style="vertical-align: middle;">  Forms</a></span>
        
        <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
        
 <div class="ml-4 text-secondary text-center">Online Forms</div>
<div class="dropdown-divider"></div>
        <?php
        if (getAccess($_SESSION['username'],'report_branchlookback') == 1 or getAccess($_SESSION['username'],'admin') == 1) {
          echo '<li class="dropdown-submenu"><a class="dropdown-item dropdown-toggle" data-bs-toggle="dropdown" href="#"><span class="bi bi-eye" style="vertical-align: middle;"> Branch Look-Back</a></span>';
          echo '<ul class="dropdown-menu">';
          echo '<div class="ml-4 text-secondary text-center">Branch Look-Back</div>';
          echo '<div class="dropdown-divider"></div>';
          echo '<a class="dropdown-item" href="\Forms\BranchLookBack.php"><span class="bi bi-file-earmark-text" style="vertical-align: middle;"> BLB Entry Form</a></span>';
          echo '<a class="dropdown-item" href="\Reports\BranchLookBack.php"><span class="bi bi-file-earmark-text" style="vertical-align: middle;"> BLB Historical Reports</a></span>';
          echo '</ul></li>' ;
        }
        ?>

<?php

 if (getAccess($_SESSION['username'],'form_newuser') == 1 or getAccess($_SESSION['username'],'admin') == 1) {
  echo '<li class="dropdown-submenu"><a class="dropdown-item dropdown-toggle" data-bs-toggle="dropdown" href="#"><span class="bi bi-people" style="vertical-align: middle;"> Employee Forms</a></span>';
  echo '<ul class="dropdown-menu">';
  echo '<div class="ml-4 text-secondary text-center"> Employee Forms</div>';
  echo '<div class="dropdown-divider"></div>';
  echo '<a class="dropdown-item" href="\Forms\EmployeeSetup.php"><span class="bi bi-file-earmark-text" style="vertical-align: middle;"> New Employee Setup</a></span>';
  echo '<a class="dropdown-item" href="\Forms\EmployeeTermination.php"><span class="bi bi-file-earmark-text" style="vertical-align: middle;"> Employee Termination</a></span>';
  
  if (in_array($_SESSION['locID'],$cprLocIds)) {
  echo '<a class="dropdown-item" href="\Forms\CellPhoneReimbursement.php"><span class="bi bi-file-earmark-text" style="vertical-align: middle;"> Cell Phone Reimbursement</a></span>';
  }

  echo "</ul></li>" ; 
}
  echo '<a class="dropdown-item" href="\module.php?id=10002"><span class="bi bi-file-earmark-text" style="vertical-align: middle;"> Order Warehouse Supplies</a></span>';
?>
        </ul>
      </li>

<li class="nav-item dropdown me-2">
        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <span class="bi bi-wrench" style="vertical-align: middle;"> Utilities</a></span>
        <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
        <?php
        if (getAccess($_SESSION['username'],'util_customerimport') == 1 or getAccess($_SESSION['username'],'admin') == 1) {
          echo '<a class="dropdown-item" href="\Utilities\CustomerImport.php"><span class="bi bi-gear-wide-connected" style="vertical-align: middle;"> Customer Import</a></span>';
        } 
        if (getAccess($_SESSION['username'],'util_Itemimport') == 1 or getAccess($_SESSION['username'],'admin') == 1) {
          echo '<a class="dropdown-item" href="\Utilities\ItemImport.php"><span class="bi bi-gear-wide-connected" style="vertical-align: middle;"> Komet Item Import</a></span>';
        }  
        
                
        
        echo "   </ul>";?>

        <?php
                
        echo '<li class="nav-item dropdown  me-2">';
        echo '<a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <span class="bi bi-bar-chart-line-fill" style="vertical-align: middle;"> Geckoboards</a></span>
        <div class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">';
        echo '<a class="dropdown-item" target="_blank" href="/Pages/viewgb.php?locNo='.$_SESSION['locID'].'"><span class="bi bi-speedometer" style="vertical-align: middle;"> '.$_SESSION['locationName'].' (main)</a></span>';
        buildGeckoboardLinks($_SESSION['username']);
        echo '<div class="dropdown-divider"></div>';
        echo '<a class="dropdown-item" href="'.$basePath.'/Pages/viewgb.php?locNo=MM"><span class="bi bi-speedometer" style="vertical-align: middle;"> MayeshMarket</a></span>';
        
        if (getAccess($_SESSION['username'],'page_geckoboards') == 1 or getAccess($_SESSION['username'],'admin') == 1) {
          echo '<div class="dropdown-divider"></div>';
          echo '<a class="dropdown-item" target="_blank" href="'.$basePath.'/Pages/viewgb.php?locNo=990"><span class="bi bi-speedometer" style="vertical-align: middle;"> Miami Distribution</a></span>';
        }
        if (getAccess($_SESSION['username'],'g8') == 1 or getAccess($_SESSION['username'],'admin') == 1) {
          echo '<div class="dropdown-divider"></div>';
          echo '<a class="dropdown-item" target="_blank" href="'.$basePath.'/Pages/viewgb.php?locNo=CT"><span class="bi bi-speedometer" style="vertical-align: middle;"> Company Totals</a></span>';
        }

        if (getAccess($_SESSION['username'],'page_geckoboards951') == 1 or getAccess($_SESSION['username'],'admin') == 1 or getAccess($_SESSION['username'],'page_geckoboards') == 1) {
          echo '<div class="dropdown-divider"></div>';
          echo '<a class="dropdown-item" target="_blank" href="'.$basePath.'/Pages/Geckoboards951.php"><span class="bi bi-speedometer" style="vertical-align: middle;"> Ecommerce</a></span>';
        }

        if (getAccess($_SESSION['username'],'admin') == 1 or getAccess($_SESSION['username'],'page_geckoboards') == 1) {
          echo '<div class="dropdown-divider"></div>';
          echo '<a class="dropdown-item" target="_blank" href="'.$basePath.'/Pages/Geckoboards.php"><span class="bi bi-speedometer" style="vertical-align: middle;"> All Geckoboards</a></span>';
        }
        
        echo '</li>';


      #}
      ?>

    <li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle " href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <span class="bi bi-collection" style="vertical-align: middle;"> All Tools!</a></span>
    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdownMenuLink">
    <a class="dropdown-item" href="\index.php" style="color: #000">Welcome Page</a>
    <div class="dropdown-divider"></div>
<?php
$conn = new mysqli($config['dbServer'], $config['dbUser'], $config['dbPassword'], $config['dbName']);
$sql =  "SELECT userModuleLinks.description,userModuleLinks.extendedDescription,userModuleLinks.module,userModuleLinks.url,userModuleLinks.category,userModuleLinks.icon FROM MayeshApps.userModuleLinks inner join userAccess on userModuleLinks.module = userAccess.module where userAccess.username = '".$_SESSION['username']."' UNION SELECT userModuleLinks.description,userModuleLinks.extendedDescription,userModuleLinks.module,userModuleLinks.url,userModuleLinks.category,userModuleLinks.icon FROM MayeshApps.userModuleLinks inner join userModules on userModuleLinks.module = userModules.moduleName where userModules.allUsers = '1' order by description asc";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {   
      echo "<a class='dropdown-item' href='/".$row['category']."/".$row['url']."' style='color: #000;'>".$row['description']."</a>";
     }
 $conn->close();
}

        ?>
          
        </li>
      </div>
    <div class="navbar-nav ms-auto">
    
            <?php
           
            if (getAccess($_SESSION['username'],'admin') == 1 or getAccess($_SESSION['username'],'admin') == 1) {
              echo "<li class=\"nav-item\">".
              "<a class=\"nav-link\" target=\"_blank\" href=\"/admin/\"><span class=\"bi bi-tools\" style=\"vertical-align: middle;\"> Admin</a></span>".
              "</li>";
            };

            ?>
          <?php
          
 
?>
        </div>
      </li>
</div>
  </div>
</nav>

<!--  HEADER END -->
<div class="d-print-none">
<div class="p-2 border-bottom  border-dark text-light" style="background-color:#505050">


  <div class="row">
  <div class="col-10">
<?php
if(isset($_SESSION['username']))   {
  
  echo "&nbsp;Welcome <b>".$_SESSION['fullName']."</b> from <b>Mayesh ". $_SESSION['locationName'] . "</b> (#". ltrim($_SESSION['locID'],"0").")";
}
echo " (<A href=/logout.php>logout</a>)";
echo "</div>";
?>
<div class="col-2">
<form class="form-inline float-right mb-0">
<div class="form-group m-0 p-0 mb-0 float-right">
<input class="form-control" id="autocomplete" type="text" placeholder="Quick Launch" name="q" data-toggle="tooltip" data-placement="left" title="" onfocus="this.value=''" onfocusout="this.value=''" data-original-title="Coming Soon!" autocomplete="off">    <span class="ml-2 loading"></span>
</div>
</form>

</div></div>
</div></div>
