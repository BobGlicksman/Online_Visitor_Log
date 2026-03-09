<?php

// Purpose: Export visitors to CSV files grouped by visit reason
// Author: Jim Schrempp
// Copyright: 2026 Maker Nexus
// Creative Commons: Attribution/Share Alike/Non Commercial (cc) 2026 Maker Nexus
//
// Date: 2026-03-08
//

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'OVLcommonfunctions.php';
$AUTH_BASE_PATH = '../';  

// Include auth if it exists
if (file_exists($AUTH_BASE_PATH . 'auth_check.php')) {
    include $AUTH_BASE_PATH . 'auth_check.php';
    requireRole(['admin']);  // Require admin role only
} else {
    // Auth system not available - for development/testing only
    echo "<!-- Warning: Auth system not loaded - development mode -->\n";
}

$OVLdebug = false; // set to true to see debug messages

// Set default date range - last 3 weeks
$today = new DateTime();
$today->setTimeZone(new DateTimeZone("America/Los_Angeles"));
$endDate = $today->format("Y-m-d");

$startDateObj = clone $today;
$startDateObj->sub(new DateInterval('P21D')); // 3 weeks = 21 days
$startDate = $startDateObj->format("Y-m-d");

// Check if form was submitted
if (isset($_POST['generateCSV'])) {
    $startDate = $_POST['startDate'];
    $endDate = $_POST['endDate'];
    
    // Generate CSV files
    $csvFiles = generateCSVFiles($startDate, $endDate);
}

// Get the HTML skeleton
if (file_exists($AUTH_BASE_PATH . 'auth_header.php')) {
    ob_start();
    include $AUTH_BASE_PATH . 'auth_header.php';
    echo ob_get_clean();
}

$html = file_get_contents("OVLexportvisitors.html");
if (!$html){
    die("unable to open file");
}

// Replace date placeholders
$html = str_replace("<<STARTDATE>>", $startDate, $html);
$html = str_replace("<<ENDDATE>>", $endDate, $html);

// Add CSV download links if files were generated
$csvLinksHTML = "";
if (isset($csvFiles) && !empty($csvFiles)) {
    $csvLinksHTML = '<div class="csv-links">';
    $csvLinksHTML .= '<h4>CSV Files Generated:</h4>';
    
    foreach ($csvFiles as $file) {
        $fileName = basename($file['path']);
        $count = $file['count'];
        $category = $file['category'];
        $csvLinksHTML .= '<a href="' . $fileName . '" download>' . 
                         $category . ' (' . $count . ' records)</a>';
    }
    
    $csvLinksHTML .= '</div>';
}

$html = str_replace("<<CSVLINKS>>", $csvLinksHTML, $html);

echo $html;

// -------------------------------------
// Functions

/**
 * Generate CSV files for each visit reason category
 */
function generateCSVFiles($startDate, $endDate) {
    global $OVLdebug;
    
    // Get database connection
    $ini_array = parse_ini_file("OVLconfig.ini", true);
    $dbUser = $ini_array["SQL_DB"]["readOnlyUser"];
    $dbPassword = $ini_array["SQL_DB"]["readOnlyPassword"];
    $dbName = $ini_array["SQL_DB"]["dataBaseName"];
    
    $con = mysqli_connect("localhost", $dbUser, $dbPassword, $dbName);
    
    // Check connection
    if (mysqli_connect_errno()) {
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
        return array();
    }
    
    // Define the five categories
    $categories = array(
        array(
            'name' => 'Class or Workshop',
            'filename' => 'visitors_class_workshop.csv',
            'condition' => "visitReason LIKE '%classOrworkshop%'"
        ),
        array(
            'name' => 'Tour',
            'filename' => 'visitors_tour.csv',
            'condition' => "visitReason LIKE '%tour%'"
        ),
        array(
            'name' => 'Guest',
            'filename' => 'visitors_guest.csv',
            'condition' => "visitReason LIKE '%guest%'"
        ),
        array(
            'name' => 'Meetup',
            'filename' => 'visitors_meetup.csv',
            'condition' => "visitReason LIKE '%meetup%'"
        ),
        array(
            'name' => 'None Given',
            'filename' => 'visitors_none_given.csv',
            'condition' => "(visitReason LIKE '%none given%' OR visitReason LIKE '%other%' OR visitReason = '' OR visitReason IS NULL)"
        )
    );
    
    $csvFiles = array();
    
    foreach ($categories as $category) {
        // Build SQL query with base conditions
        $sql = "SELECT nameFirst, nameLast, email, visitReason FROM ovl_visits "
             . "WHERE dateCreatedLocal BETWEEN '" . mysqli_real_escape_string($con, $startDate) . "' "
             . "      AND '" . mysqli_real_escape_string($con, $endDate) . " 23:59:59' "
             . "  AND email != '' "
             . "  AND visitReason NOT LIKE '%forgotbadge%' "
             . "  AND " . $category['condition']
             . " ORDER BY nameLast ASC, nameFirst ASC";
        
        if ($OVLdebug) {
            echo "SQL for " . $category['name'] . ": " . $sql . "<br>";
        }
        
        $result = mysqli_query($con, $sql);
        
        if (!$result) {
            echo "Error: " . $sql . "<br>" . mysqli_error($con);
            continue;
        }
        
        $rowCount = mysqli_num_rows($result);
        
        if ($rowCount > 0) {
            // Create CSV file
            $filepath = $category['filename'];
            $fp = fopen($filepath, 'w');
            
            if ($fp === false) {
                echo "Error: Unable to create file " . $filepath . "<br>";
                continue;
            }
            
            // Write CSV header
            fputcsv($fp, array('First Name', 'Last Name', 'Email', 'Visit Reason'));
            
            // Write data rows
            while ($row = mysqli_fetch_assoc($result)) {
                fputcsv($fp, array(
                    $row['nameFirst'],
                    $row['nameLast'],
                    $row['email'],
                    $row['visitReason']
                ));
            }
            
            fclose($fp);
            
            $csvFiles[] = array(
                'path' => $filepath,
                'category' => $category['name'],
                'count' => $rowCount
            );
        }
    }
    
    mysqli_close($con);
    
    return $csvFiles;
}

/**
 * Echo a string to the user for debugging
 */
function debugToUser($data) {
    global $OVLdebug;
    if ($OVLdebug) {
        echo "<br>" . $data . "<br>";
    }
}

?>
