<!--
* PHP script (partly HTML) works in conjunction with Cloudlog Database V.6.13
* It interogates based on selection Satellite, Band and or Mode
* Calculates VUCC worked / confirmed and displays on footer
* Displays results per DXCC and first contact (and all worked unique stations) per DXCC
* Colors the DXCC code for worked / confirmed
*
* Reworked version 2-12-2024
*
-->

<html>
<head>
	<style>
		h1	{font-size: 16px;}
		td	{font-size: 11px;}
		a	{text-decoration: none;	color: black;}
	</style>
</head>

<body>
	<?
	// define variables
	$sat="";
	$prefix_confirmed="";
	$qrz="";
	$figures="";
	$band="%";
	$mode="";
	$qso_made=0;
	$call_before="";
	$call_string="";
	$dxcc_confirmed=0;
	$dxcc_unconfirmed=0;
	$qso_prfx=0;
	$stations=0;
	$grids_worked=0;
	$grids_confirmed=0;
	$modes_worked=0;
	$sats_worked=0;
	$bands_worked=0;

	//Fetch the parameters from POST and GET type
	if (isset($_REQUEST['band'])) $band = $_REQUEST['band'];
	if (isset($_REQUEST['mode'])) $mode = $_REQUEST['mode'];
	if (isset($_REQUEST['sat']))  $sat =  $_REQUEST['sat'];
	if (isset($_REQUEST['qrz']))  $qrz =  $_REQUEST['qrz'];

	//connect Cloudlog database
	if (!$cloudlog_db = new mysqli('localhost', '*USERNAME*', '*PASSWORD*','*DBNAME*')) {
	    echo 'Could not connect to database';
	    exit;
	}

	// if page is called from qrz.com then satellite is QO-100 and all modes. Skip selection menu.
	if ($qrz=="yes") {
		$sat="QO-100"; $mode="%"; $band="13cm";
	} else {
    	// read worked satelites into array
    	$sql_statement 	  	= 'SELECT distinct COL_SAT_NAME as sats	FROM `TABLE_HRD_CONTACTS_V01` order by col_sat_name';
    	$sql_sats 		  	= $cloudlog_db->query($sql_statement);
		// put them in an array to further search
    	while ($row = $sql_sats->fetch_assoc()) {
			$sats_array[$sats_worked] = $row["sats"];
			$sats_worked++;
		}

    	// in stead of read worked bands into array defined manually
		$bands_array = array("160m","80m","60m","40m","30m","20m","17m","15m","12m","10m","6m","4m","2m","70cm");
		$bands_worked=14;

    	// read worked modes for given sat or band into array
    	$sql_statement 	= 'SELECT DISTINCT COL_MODE as modes FROM `TABLE_HRD_CONTACTS_V01` WHERE ';
		if ($sat) 	{
			$sql_statement = $sql_statement .'COL_SAT_NAME="'.$sat.'"';
		} else {
			$sql_statement = $sql_statement .'COL_BAND like "'.$band.'"';
		}
		$sql_statement 	= $sql_statement.' ORDER BY COL_MODE';
    	$sql_modes 		= $cloudlog_db->query($sql_statement);
		// put them in an array to further search
    	while ($row = $sql_modes->fetch_assoc()) {
			$modes_array[$modes_worked] = $row["modes"];
			$modes_worked++;
		}

		// check if mode is valid for sat or band if not change to "All"
		if (!in_array($mode, $modes_array)) $mode="%";

   		echo "<h1>";
		echo "<form method='post' onsubmit='this.form.submit()'>";

		if ($sat == "" ) {
		echo "Select Band : &nbsp;&nbsp;<select name='band' onchange='this.form.submit()'>";
		$i=0;
   	   	while ($i < $bands_worked) {
   	   		echo '<option value="'.$bands_array[$i].'"';
   	   		if ($band == $bands_array[$i]) echo 'selected ';
   	   		echo '>'.$bands_array[$i].'</option>';
   	   		$i++;
   	   	}
		echo "</select>";
		}

		if ($sat <> "" and $qrz<>"yes") {
		echo "Select Satelite : &nbsp;&nbsp;<select name='sat' onchange='this.form.submit()'>";
		$i=1;  // De eerste waarde lijkt een blanco satelliet te zijn?
   	   	while ($i < $sats_worked) {
   	   		echo '<option value="'.$sats_array[$i].'"';
   	   		if ($sat == $sats_array[$i]) echo 'selected ';
   	   		echo '>'.$sats_array[$i].'</option>';
   	   		$i++;
   	   	}
		echo "</select>";
		}

		echo "&nbsp;&nbsp;&nbsp;&nbsp;Select Mode : &nbsp;&nbsp;<select name='mode' onchange='this.form.submit()'>";
   	   	if ($modes_worked >1) {
   	   		echo "<option value='%'"; if ($mode == "%") { echo "'selected'"; } echo">All</option>";

   	   	} else { $mode = $modes_array[0]; }
		$i=0;
   	   	while ($i < $modes_worked) {
   	   		echo '<option value="'.$modes_array[$i].'"';
   	   		if ($mode == $modes_array[$i]) echo 'selected ';
   	   		echo '>'.$modes_array[$i].'</option>';
   	   		$i++;
   	   	}
		echo "</select>&nbsp;&nbsp;&nbsp;&nbsp;<input name='refresh' type='submit' value='Refresh' />&nbsp;&nbsp;</form></h1>";
	}	// end selection menu

	// common part of WHERE cluase used.
	$sql_whereclause=' COL_SAT_NAME="'.$sat.'" and COL_BAND like "'.$band.'" and COL_MODE like "'.$mode.'"';

	// read number of dxcc workd to calculate number of lines in table, assuming all selectable bands / satellite to have logged qso.
 	$sql_statement    	= 'SELECT COUNT(DISTINCT COL_DXCC) as dxcc FROM `TABLE_HRD_CONTACTS_V01` WHERE' . $sql_whereclause;
	$sql_dxcc 		  	= $cloudlog_db->query($sql_statement);
    $dxcc     		  	= $sql_dxcc->fetch_assoc();
    $tot_dxcc 		  	= $dxcc['dxcc'];
    $number_of_rows 	= ceil(($tot_dxcc-1)/4);

	// read the DXCC file for all worked DXCC (use distinct on the logfile to avoid multiple reads of DXCC)
	$sql_statement    	= 'SELECT DISTINCT col_dxcc, dxcc_entities.prefix, dxcc_entities.name, dxcc_entities.adif
			   			   FROM `TABLE_HRD_CONTACTS_V01` INNER JOIN dxcc_entities ON dxcc_entities.adif = TABLE_HRD_CONTACTS_V01.COL_DXCC
			   			   WHERE ' . $sql_whereclause . ' ORDER BY dxcc_entities.prefix';
	$dxcc 			  	= $cloudlog_db->query($sql_statement);

	// first calculate the VUCC
    // read number of unique grid(4) worked into array !!ommit empty COL_GRIDSQUARE when VUCC field is used!!
    $sql_statement 	  	= 'SELECT distinct substring(upper(COL_GRIDSQUARE), 1, 4) as grids
    					   FROM `TABLE_HRD_CONTACTS_V01`
    					   WHERE ' . $sql_whereclause . ' and COL_GRIDSQUARE<>""';
    $sql_grids 		  	= $cloudlog_db->query($sql_statement);

	// put them in an array to further search
    while ($row = $sql_grids->fetch_assoc()) {
		$grids_array[$grids_worked] = $row["grids"];
		$grids_worked++;
	}

    // read in COL_VUCC_GRIDS the additional grid(4) locators and check for doubles within the array and the distinct selected.
    $sql_statement 	  	= 'SELECT distinct COL_VUCC_GRIDS as grids
    					   FROM `TABLE_HRD_CONTACTS_V01`
    					   WHERE ' . $sql_whereclause . ' and COL_VUCC_GRIDS<>""';
    $sql_vucc_grids 	= $cloudlog_db->query($sql_statement);

    // could be either 2 or 4  grid(4)
    while ($row = $sql_vucc_grids->fetch_assoc()) {
		for ($i=0; $i<strlen($row["grids"]); $i=$i+6) {
			$grid = substr($row["grids"], $i, 4);
			if (!in_array($grid, $grids_array))
			{
				$grids_array[$grids_worked] = $grid;
				$grids_worked++;
			}
		}
	}

	// clear grid counter and do the sql again for confirmed grids
	unset($grids_array);
    // read confirmed number of unique grid(4) worked into array !!ommit empty COL_GRIDSQUARE when VUCC field is used!!
    $sql_statement 	  	= 'SELECT distinct substring(upper(COL_GRIDSQUARE), 1, 4) as grids
    					   FROM `TABLE_HRD_CONTACTS_V01`
    					   WHERE (COL_LOTW_QSL_RCVD="Y" or COL_EQSL_QSL_RCVD="Y" or COL_QSL_RCVD="Y") and ' . $sql_whereclause . ' and COL_GRIDSQUARE<>""';
    $sql_grids 		  	= $cloudlog_db->query($sql_statement);

	// put them in an array to further search
    while ($row = $sql_grids->fetch_assoc()) {
		$grids_array[$grids_confirmed] = $row["grids"];
		$grids_confirmed++;
	}

    // read confirmed in COL_VUCC_GRIDS the additional grid(4) locators and check for doubles within the array and the distinct selected.
    $sql_statement 	  	= 'SELECT distinct col_vucc_grids as grids
    					   FROM `TABLE_HRD_CONTACTS_V01`
   						   WHERE (COL_LOTW_QSL_RCVD="Y" or COL_EQSL_QSL_RCVD="Y" or COL_QSL_RCVD="Y") and ' . $sql_whereclause . ' and COL_VUCC_GRIDS<>""';
    $sql_vucc_grids 	= $cloudlog_db->query($sql_statement);

    // could be either 2 or 4  grid(4)
    while ($row = $sql_vucc_grids->fetch_assoc()) {
		for ($i=0; $i<strlen($row["grids"]); $i=$i+6) {
			$grid = substr($row["grids"], $i, 4);
			if (!in_array($grid, $grids_array))
			{
				$grids_array[$grids_confirmed] = $grid;
				$grids_confirmed++;
			}
		}
	}

 	// now continue the DXCC search with station details
 	// create table and heading
	echo "<table width=\"100%\">";
    $row_number = 0;
    echo "<tr style='vertical-align:top;'><td vertical-align=top><table>";

	// read all fetched worked DXCC entries which are alphabetic sorted and display the calls in the table
	while ($row = $dxcc->fetch_assoc()) {
		// save dxcc values
	    $prefix 		= $row["prefix"];
	    $adif			= $row["adif"];
	    $name   		= $row["name"];
	    // read the qsos in ascending timestamp order for this dxcc using the adif number for the prefix
	    $sql_statement	= 'SELECT col_call, col_qsl_rcvd, col_eqsl_qsl_rcvd, col_lotw_qsl_rcvd, col_time_on, col_gridsquare
						   FROM `TABLE_HRD_CONTACTS_V01`
			   			   WHERE ' . $sql_whereclause . ' and COL_DXCC=' . $adif . ' ORDER BY COL_TIME_ON';
    	$calls 			= $cloudlog_db->query($sql_statement);

		$first_date="";
		// prepare the callsigns for printing in the table
		while ($row = $calls->fetch_assoc()) {
			$qso_made++;
			$qso_prfx++;

			// get first date
			if ($first_date == "") {
				$first_date = substr($row["col_time_on"], 0, 10) . " - " . $row["col_call"] . ", ";
				$stations++;
			}
			else {
				// check if call exist in the list and write only new calls
			    if (strpos($first_date.',', $row["col_call"].',') == false) {
					if (!$qrz) {
						$first_date = $first_date . $row["col_call"] . ", ";
					}
					$stations++;
				}
			}

			// define DXCC color based on confirmation
			if ($row["col_eqsl_qsl_rcvd"] == "Y" || $row["col_lotw_qsl_rcvd"] == "Y" || $row["col_qsl_rcvd"] == "Y" ) {
				$prefix_confirmed = "Y";
			}
		}	// end while reading the dxcc qso

		// write the line in the table and color the DXCC if confirmed or not
		// truncate the last added comma and space
		$first_date = substr($first_date, 0, -2);

		echo "<tr>";
		if ($prefix_confirmed == "Y") {
			$dxcc_confirmed++;
			echo "<td style='color: green;'>";
		} else {
			$dxcc_unconfirmed++;
			echo "<td style='color: red;'>";
		}
		echo $prefix . "</a></td><td><a href='#' title='" . $first_date . "'>" . $name . "<span style='color: grey;'> (" . $stations . ")</span></a></td></tr>";
		$qso_prfx=0;
		$stations=0;
		$row_number++;
		// countries per column
		if ($row_number==$number_of_rows) {
			$row_number=0;
			echo "</table></td><td><table>";
		}

		// reset the above used variables
		$call_string="";
		$prefix_confirmed="N";
	} // end while reading the worked dxcc

	echo "</table></td></tr></table>";
	echo "<h1>";
	echo "DXCC worked (<span style='color: green';>confirmed</span>) : ";
	echo $dxcc_unconfirmed+$dxcc_confirmed;
	echo "(<span style='color: green';>";
	echo $dxcc_confirmed;
	echo "</span>), with ";
	echo $qso_made;
	echo " QSO's";
	if ( str_replace(' ','', $sat) <> "" || $band == "4m" || $band == "6m" || $band == "2m" || $band == "70cm" ) {
		echo ", in " . $grids_worked . "(<span style='color: green';>" . $grids_confirmed . "</span>) VUCC. <span style='font-size: 14px';></span>";
	} else {
		echo ".";
	}
	echo "</h1>";
	?>

</body>
</html>
