<?php

// reports from Online Visitor Log DATABASE
//
// Visitor summary checkin reports
//
// Creative Commons: Attribution/Share Alike/Non Commercial (cc) 2022 Maker Nexus
// By Jim Schrempp
//
//  Nov 2022: 
//      Moved JS to its own file. 
//      Daily graph now uses humanistic labels.
//      Removed unneeded code


include 'OVLcommonfunctions.php';
$AUTH_BASE_PATH = '../'; 

// Include auth if it exists
if (file_exists($AUTH_BASE_PATH . 'auth_check.php')) {
    include $AUTH_BASE_PATH . 'auth_check.php';
    requireRole(['manager', 'admin']);  // Require admin role only
} else {
    // Auth system not available - for development/testing only
    echo "<!-- Warning: Auth system not loaded - development mode -->\n";
}

// get the HTML skeleton

$html = file_get_contents("OVLcheckinreport.txt");
if (!$html){
  die("unable to open file");
}

// Generate auth header
ob_start();
$AUTH_BASE_PATH = '../';  
$authHeader = ob_get_clean();
$html = str_replace("<<AUTH_HEADER>>", $authHeader, $html);

// Get the data
$ini_array = parse_ini_file("OVLconfig.ini", true);
$dbUser = $ini_array["SQL_DB"]["readOnlyUser"];
$dbPassword = $ini_array["SQL_DB"]["readOnlyPassword"];
$dbName = $ini_array["SQL_DB"]["dataBaseName"];

$con = mysqli_connect("localhost",$dbUser,$dbPassword,$dbName);

// Check connection
if (mysqli_connect_errno()) {
    echo "Failed to connect to MySQL: " . mysqli_connect_error();
}


// ------------ TABLE 1  
  
$selectSQLVisitorsPerMonth = "
SELECT COUNT(*) as cnt, mnth, yr
FROM
(
SELECT MONTH(dateCheckinLocal) as mnth, YEAR(dateCheckinLocal) as yr, email, nameFirst, nameLast
FROM `ovl_visits`
WHERE dateCheckinLocal > '20191001'
  and dateCheckinLocal IS NOT NULL
group by YEAR(dateCheckinLocal), MONTH(dateCheckinLocal), email
order by YEAR(dateCheckinLocal), MONTH(dateCheckinLocal)
) as X
GROUP BY yr, mnth
ORDER BY yr, mnth;
";

$result = mysqli_query($con, $selectSQLVisitorsPerMonth);
$dataX = "";
$dataY = "";

// Construct the page
if (mysqli_num_rows($result) > 0) {

    while($row = mysqli_fetch_assoc($result)) {
    	
        // build detail table for month data
    	$thisTableRow = "<tr><td>" . $row["yr"] . "</td><td>" . $row["mnth"] . "</td><td>" . $row["cnt"] . "</td></tr>";

        // build graph data for month data
        $dataX = $dataX . "|" . $row["yr"] . "/" . $row["mnth"];
        $dataY = $dataY . "|" . $row["cnt"];
    	$tableRows = $tableRows . $thisTableRow;
    }
    
    $html = str_replace("<<GRAPH1DATAX>>",$dataX,$html);
    $html = str_replace("<<GRAPH1DATAY>>",$dataY,$html);

    $html = str_replace("<<TABLEHEADER_MembersPerMonth>>",
    	"<thead><tr><th>Year</th><th>Month</th><th>Unique Visitors</th></tr></thead><tbody>",
    	$html);
    $html = str_replace("<<TABLEROWS_MembersPerMonth>>", $tableRows . "</tbody>",$html);

} else {
    echo "0 results";
}


// ------------ TABLE 2  

$tableRows = "";

$selectSQLVisitorsPerDay = "
SELECT COUNT(*) as cnt, dy, mnth, yr, DOY
FROM
(
SELECT DAYOFYEAR(dateCheckinLocal) as DOY, DAY(dateCheckinLocal) as dy, MONTH(dateCheckinLocal) as mnth, YEAR(dateCheckinLocal) as yr, email, nameFirst, nameLast
FROM `ovl_visits`
WHERE dateCheckinLocal > '20191001'
  and dateCheckinLocal IS NOT NULL
group by YEAR(dateCheckinLocal), MONTH(dateCheckinLocal), DAY(dateCheckinLocal), email
order by YEAR(dateCheckinLocal), MONTH(dateCheckinLocal), DAY(dateCheckinLocal)
) as X
GROUP BY yr, mnth, dy, DOY
ORDER BY yr, mnth, dy, DOY;
";

$result2 = mysqli_query($con, $selectSQLVisitorsPerDay);

// Construct the page
$dataX = "";
$dataY = "";

if (mysqli_num_rows($result2) > 0) {

    while($row = mysqli_fetch_assoc($result2)) {
    
        // build detail table for days
        $thisTableRow = "<tr><td>" . $row["yr"] . "</td><td>" . $row["mnth"] . "</td><td>" . $row["dy"] . "</td><td>" . $row["cnt"] . "</td></tr>";

        $tableRows = $tableRows . $thisTableRow;

        // build graph data for days
        $thisDOY = intval($row["DOY"]);
        $dateValue = date_format(DateTime::createFromFormat("Y z", $row["yr"] . " " . $thisDOY ), "Y-m-d");
        $dataX = $dataX . "|" . $dateValue;
        $dataY = $dataY . "|" . $row["cnt"];

    }
    
    $html = str_replace("<<GRAPH2DATAX>>",$dataX,$html);
    $html = str_replace("<<GRAPH2DATAY>>",$dataY,$html);

    $html = str_replace("<<TABLEHEADER_MembersPerDay>>",
    	"<thead><tr><th>Year</th><th>Month</th><th>Day</th><th>Unique Visitors</th></tr></thead><tbody>",
    	$html);
    $html = str_replace("<<TABLEROWS_MembersPerDay>>", $tableRows . "</tbody>",$html);

} else {
    echo "0 results";
}

// ------------- Report Data 3 ---------------

$tableRows = "";

$SQLDateRange = date("'Y-m-d'",strtotime("60 days ago")) . " AND " .  date("'Y-m-d'",time()) ;

$selectSQLVisitorsLast60Days = "
SELECT COUNT(DISTINCT email) as numUnique
FROM ovl_visits
WHERE dateCheckinLocal BETWEEN "
. $SQLDateRange .
" and dateCheckinLocal IS NOT NULL;
";

$result3 = mysqli_query($con, $selectSQLVisitorsLast60Days);

if (mysqli_num_rows($result3) > 0) {

    $row = mysqli_fetch_assoc($result3);
    $html = str_replace("<<UNIQUE90>>", $row["numUnique"],$html);

} else {
    $html = str_replace("<<UNIQUE90>>", "no results found",$html);
}

// ------------- final output ----------

echo $html;

mysqli_close($con);

return;

?>