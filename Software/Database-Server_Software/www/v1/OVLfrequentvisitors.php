<?php

// Purpose: Display visitors who have been to the makerspace more than 3 times in the last 3 months
// Author: Jim Schrempp
// Copywrite: 2024 Maker Nexus
// Creative Commons: Attribution/Share Alike/Non Commercial (cc) 2022 Maker Nexus
// By Jim Schrempp
//
//
// Date: 2024-10-02
//

include 'OVLcommonfunctions.php';
$AUTH_BASE_PATH = '../';  
include $AUTH_BASE_PATH . 'auth_check.php';
requireRole(['manager', 'admin']);  // Require manager, admin, or MoD role


$today = new DateTime();
$today->setTimeZone(new DateTimeZone("America/Los_Angeles"));
$today->add(new DateInterval('P1D'));  // end date for select will be midnight tonight
$nowSQL = $today->format("Y-m-d");

// get date from 3 months ago
$today->sub(new DateInterval('P3M'));
$today->setTime(0,0,0);
$threeMonthsAgoSQL = $today->format("Y-m-d");

$OVLdebug = false; // set to true to see debug messages
debugToUser( "OVLdebug is active. " . $nowSQL .  "<br>");

// allowWebAccess();  // if IP not allowed, then die

// get the HTML skeleton
ob_start();
include $AUTH_BASE_PATH . 'auth_header.php';
echo ob_get_clean();

$html = file_get_contents("OVLfrequentvisitors.html");
if (!$html){
  die("unable to open file");
}

// Get the data
$ini_array = parse_ini_file("OVLconfig.ini", true);
$dbUser = $ini_array["SQL_DB"]["readOnlyUser"];
$dbPassword = $ini_array["SQL_DB"]["readOnlyPassword"];
$dbName = $ini_array["SQL_DB"]["dataBaseName"];

$con = mysqli_connect("localhost",$dbUser,$dbPassword,$dbName);

// Check connection
if (mysqli_connect_errno()) {
    echo "Failed to connect to MySQL: " . mysqli_connect_error();
    //logfile("Failed to connect to MySQL: " . mysqli_connect_error());
}

// Count visits per person (unique by nameLast + nameFirst) in the last 3 months,
// only include those with more than 3 visits
$sql = "SELECT nameFirst, nameLast, email, COUNT(*) as visitCount "
        . " FROM ovl_visits "
        . " WHERE dateCheckinLocal between '" . $threeMonthsAgoSQL . "'"
        . "         AND '" . $nowSQL . "'"
        . " GROUP BY nameLast, nameFirst"
        . " HAVING COUNT(*) > 4"
        . " ORDER BY visitCount DESC, nameLast ASC, nameFirst ASC";

$result = mysqli_query($con, $sql);
if (!$result) {

    echo "Error: " . $sql . "<br>" . mysqli_error($con);
    //logfile("Error: " . $sql . "<br>" . mysqli_error($con));
    exit;

} else {

    // create the divs

    $outputTable = "<TABLE class='visitor-table'>"
        . "<tr><th class='col-name'>Name</th><th class='col-email'>Email</th><th class='col-count'>Visit Count</th><th class='col-reasons'>Reasons</th></tr>";
    if (mysqli_num_rows($result) == 0) {
        $outputTable .= "<tr><td colspan='4'><p class='no-visitors'>No frequent visitors found (more than 3 visits in last 3 months)</p></td></tr>";
    } else {
        // loop over all rows
        while ($row = mysqli_fetch_assoc($result)) {
            // Get the breakdown of visit reasons for this person
            $reasonBreakdown = getReasonBreakdown($con, $row["nameFirst"], $row["nameLast"], $threeMonthsAgoSQL, $nowSQL);
            $outputTable .= makeRow($row["nameFirst"], $row["nameLast"], $row["email"], $row["visitCount"], $reasonBreakdown);
        }
    }
    $outputTable = $outputTable . "</TABLE>";

    // replace the divs in the html
    $html = str_replace("<<DIVSHERE>>", $outputTable, $html);
    echo $html;

}

// close the database connection
mysqli_close($con);

// end the php
die;

// -------------------------------------
// Functions

// make a row
function makeRow($nameFirst, $nameLast, $email, $visitCount, $reasonBreakdown) {

    $div = "<tr>"
        . "<td>" . htmlspecialchars($nameFirst . " " . $nameLast) . "</td>"
        . "<td>" . htmlspecialchars($email) . "</td>"
        . "<td class='col-count'>" . htmlspecialchars($visitCount) . "</td>"
        . "<td class='col-reasons'>" . htmlspecialchars($reasonBreakdown) . "</td>"
        . "</tr>\r\n";
    return $div;
}


// Query the breakdown of visitReasons for a given person and return a formatted string like "tour:4; guest:1"
function getReasonBreakdown($con, $nameFirst, $nameLast, $dateFrom, $dateTo) {
    $sql = "SELECT visitReason, COUNT(*) as cnt "
            . " FROM ovl_visits "
            . " WHERE nameFirst = '" . mysqli_real_escape_string($con, $nameFirst) . "'"
            . "   AND nameLast = '" . mysqli_real_escape_string($con, $nameLast) . "'"
            . "   AND dateCheckinLocal between '" . $dateFrom . "'"
            . "           AND '" . $dateTo . "'"
            . " GROUP BY visitReason"
            . " ORDER BY cnt DESC, visitReason ASC";

    $result = mysqli_query($con, $sql);
    if (!$result) {
        return "error";
    }

    $parts = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $parts[] = $row["visitReason"] . ":" . $row["cnt"];
    }

    return implode("; ", $parts);
}


// Echo a string to the user for debugging
function debugToUser ($data) {
    global $OVLdebug;
    if ($OVLdebug){
        echo "<br>" . $data . "<br>";
    }
}

?>