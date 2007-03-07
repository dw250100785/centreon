<?
/**
Oreon is developped with GPL Licence 2.0 :
http://www.gnu.org/licenses/gpl.txt
Developped by : Cedrick Facon

The Software is provided to you AS IS and WITH ALL FAULTS.
OREON makes no representation and gives no warranty whatsoever,
whether express or implied, and without limitation, with regard to the quality,
safety, contents, performance, merchantability, non-infringement or suitability for
any particular or intended purpose of the Software found on the OREON web site.
In no event will OREON be liable for any direct, indirect, punitive, special,
incidental or consequential damages however they may arise and even if OREON has
been previously advised of the possibility of such damages.

For information : contact@oreon-project.org
*/

	$debug = 0;
	
	# pearDB init
	$buffer = '';	
	
	require_once 'DB.php';

	$oreonPath = isset($_POST["fileOreonConf"]) ? $_POST["fileOreonConf"] : "";
	$oreonPath = isset($_GET["fileOreonConf"]) ? $_GET["fileOreonConf"] : $oreonPath;

	if($oreonPath == ""){
		$buffer .= '<reponse>';	
		$buffer .= 'none';
		$buffer .= '</reponse>';
		header('Content-Type: text/xml');
		echo $buffer;
	}

	include_once($oreonPath . "www/oreon.conf.php");
	include_once($oreonPath . "www/include/common/common-Func-ACL.php");
	
	/* Connect to oreon DB */
	
	$dsn = array(
		     'phptype'  => 'mysql',
		     'username' => $conf_oreon['user'],
		     'password' => $conf_oreon['password'],
		     'hostspec' => $conf_oreon['host'],
		     'database' => $conf_oreon['db'],
		     );
	
	$options = array(
			 'debug'       => 2,
			 'portability' => DB_PORTABILITY_ALL ^ DB_PORTABILITY_LOWERCASE,
			 );
	
	$pearDB =& DB::connect($dsn, $options);
	if (PEAR::isError($pearDB)) die("Connecting problems with oreon database : " . $pearDB->getMessage());
	$pearDB->setFetchMode(DB_FETCHMODE_ASSOC);

	
	# class init
	class Duration
	{
		function toString ($duration, $periods = null)
	    {
	        if (!is_array($duration)) {
	            $duration = Duration::int2array($duration, $periods);
	        }
	        return Duration::array2string($duration);
	    }
	 
	    function int2array ($seconds, $periods = null)
	    {
	        // Define time periods
	        if (!is_array($periods)) {
	            $periods = array (
	                    'y'	=> 31556926,
	                    'M' => 2629743,
	                    'w' => 604800,
	                    'd' => 86400,
	                    'h' => 3600,
	                    'm' => 60,
	                    's' => 1
	                    );
	        }

	        // Loop
	        $seconds = (int) $seconds;
	        foreach ($periods as $period => $value) {
	            $count = floor($seconds / $value);
	 
	            if ($count == 0) {
	                continue;
	            }
	 
	            $values[$period] = $count;
	            $seconds = $seconds % $value;
	        }
	 
	        // Return
	        if (empty($values)) {
	            $values = null;
	        }
	        return $values;
	    }
	 
	    function array2string ($duration)
	    {
	        if (!is_array($duration)) {
	            return false;
	        }
	        foreach ($duration as $key => $value) {
	            $segment = $value . '' . $key;
	            $array[] = $segment;
	        }
	        $str = implode(' ', $array);
	        return $str;
	    }
	}

	function read($time,$arr,$flag,$type,$version,$sid,$file,$num, $search, $limit,$sort_type,$order,$search_type_host,$search_type_service,$date_time_format_status){
		global $pearDB, $flag;
	
		$MyLog = date('l dS \of F Y h:i:s A'). "\n";
	
		$_GET["sort_types"] = $sort_type;
		$_GET["order"] = $order;
		$_GET["o"] = "svcpb";
		$_GET["sort_typeh"] = 0;
	
		$buffer = null;
		$buffer  = '<?xml version="1.0" encoding="ISO-8859-1"?>';
		$buffer .= '<reponse>';
	
		$ntime = $time;
		if( filectime($file) > $ntime)
			$ntime = filectime($file);	
	
		$buffer .= '<infos>';
		$buffer .= '<flag>'. $flag . '</flag>';
		$buffer .= '<time>'.$ntime. '</time>';
		$buffer .= '<filetime>'.filectime($file). '</filetime>';
		$buffer .= '</infos>';
	
		if( filectime($file) > $time){		
			$oreon = "oreon";
	
			include("../load_status_log.php");
			$mtab = array();
			$mtab = explode(',', $arr);
	
			# calcul stat for statistic
			$statistic_host = array("UP" => 0, "DOWN" => 0, "UNREACHABLE" => 0, "PENDING" => 0);
			$statistic_service = array("OK" => 0, "WARNING" => 0, "CRITICAL" => 0, "UNKNOWN" => 0, "PENDING" => 0);
			
			# services infos
			if (isset($service_status) &&  (
			$type == "svc" || $type == "svcpb" || $type == "svc_ok" || $type == "svc_warning" || $type == "svc_critical" || $type == "svc_unknown"
			)){
				$gtab = array();
				for($a=0,$b=1; sizeof($mtab) > $b;$a+=2,$b+=2)
					$gtab[$mtab[$a] . $mtab[$b]] = $a / 2 + $a % 2;
				$rows = 0;
				$service_status_num = array();
				if (isset($service_status))
					foreach ($service_status as $name => $svc){
						if($type == "svc" ||
						 ($type == "svc_ok" && $svc["current_state"] == "OK") ||
						 (($type == "svcpb" || $type == "svc_warning") && $svc["current_state"] == "WARNING") ||
						 (($type == "svcpb" || $type == "svc_unknown") && $svc["current_state"] == "UNKNOWN") ||
						 (($type == "svcpb" || $type == "svc_critical") && $svc["current_state"] == "CRITICAL")
						)
						{

							$tmp = array();
							$tmp[0] = $name;		
							$service_status[$name]["status"] = $svc["current_state"];
							$service_status[$name]["flapping"] = $svc["is_flapping"];
							$tmp[1] = $service_status[$name];
							$service_status_num[$rows++] = $tmp;
						}
					}
	
				# view tab
				$displayTab = array();
				$start = $num*$limit;
				for($i=$start; $i < ($limit+$start) && isset($service_status_num[$i]);$i++)
					$displayTab[$service_status_num[$i][0]] = $service_status_num[$i][1];
				$service_status = $displayTab;
				$ct = 0;
				$flag = 0;
							

				$tab_color_host = array();
				foreach ($service_status as $name => $svc){
					if((isset($gtab[$svc["host_name"] . $svc["service_description"]]) && $gtab[$svc["host_name"] . $svc["service_description"]] != $ct))
						$flag = 1;
					if(!isset($gtab[$svc["host_name"] . $svc["service_description"]]))
						$flag = 1;
					$MyLog .= "flag=" . $flag . " host=" . $svc["host_name"] . " svc=" . $svc["service_description"]  . "\n";
					$passive = ($svc["passive_checks_enabled"] && $svc["active_checks_enabled"] == 0) ? 1 : 0;
					$active = ($svc["passive_checks_enabled"] == 0 && $svc["active_checks_enabled"] == 0) ? 1 : 0;										
	//					$plugin_output = ($svc["plugin_output"]) ? htmlentities($svc["plugin_output"]) : " ";					
					$plugin_output = ($svc["plugin_output"]) ? $svc["plugin_output"] : " N/A ";

					if($host_status[$svc["host_name"]]["current_state"] == "DOWN" && !isset($tab_color_host[$svc["host_name"]])){
						$tab_color_host[$svc["host_name"]] = 1;
						$color_host = '#FD8B46';
					}
					else
						$color_host = 'normal';


					$buffer .= '<line>';
					$buffer .= '<order>'. $ct++ . '</order>';
					$buffer .= '<flag>'. $flag . '</flag>';
					$buffer .= '<host_color>'.$color_host.'</host_color>';
					$buffer .= '<host_name>'. $svc["host_name"] . '</host_name>';
					$buffer .= '<host_status>'. $host_status[$svc["host_name"]]["current_state"]  . '</host_status>';///
					$buffer .= '<service_description>'. $svc["service_description"] . '</service_description>';
					$buffer .= '<current_state>'. $svc["current_state"] . '</current_state>';
					$buffer .= '<plugin_output>' . $plugin_output . '</plugin_output>';
					$buffer .= '<current_attempt>'. $svc["current_attempt"] . '</current_attempt>';
					$buffer .= '<notifications_enabled>'. $svc["notifications_enabled"] . '</notifications_enabled>';
					$buffer .= '<problem_has_been_acknowledged>'. $svc["problem_has_been_acknowledged"] . '</problem_has_been_acknowledged>';
					$buffer .= '<accept_passive_check>'. $passive . '</accept_passive_check>';
					$buffer .= '<accept_active_check>'. $active . '</accept_active_check>';
					$buffer .= '<event_handler_enabled>'. $svc["event_handler_enabled"] . '</event_handler_enabled>';
					$buffer .= '<is_flapping>'. $svc["is_flapping"] . '</is_flapping>';
					$buffer .= '<flap_detection_enabled>'. $svc["flap_detection_enabled"] . '</flap_detection_enabled>';
	
					$last_check = " ";
					if($svc["last_check"] > 0)
					$last_check = date($date_time_format_status, $svc["last_check"]);
					$buffer .= '<last_check>'. $last_check . '</last_check>';
	
					$duration = " ";
					if($svc["last_state_change"] > 0) 
						$duration = Duration::toString(time() - $svc["last_state_change"]);
	
					$buffer .= '<last_state_change>'. $duration . '</last_state_change>';
					$buffer .= '</line>';
				}
			}
		}
		
	//	$buffer = html_entity_decode($buffer);
		$buffer .= '</reponse>';
		header('Content-Type: text/xml');
		echo $buffer;
	
		global $debug;
		if($debug == 1){
			$file = "log.xml";
			$inF = fopen($file,"w");
			fwrite($inF,$buffer);
			fclose($inF);
			$file = "log.txt";
			$inF = fopen($file,"w");
			fwrite($inF,"---log:\n ".$MyLog."\n\n");
			fwrite($inF,"sid:\n ".$sid."\n\n");
			fwrite($inF,"uid:\n ".$uid."\n\n");
			fwrite($inF,"admin:\n ".$IsAdmin."\n\n");
			foreach($oreonLCA as $key => $h)
				fwrite($inF,"lca h: ".$h."\n\n");
			fwrite($inF,$buffer."log:\n----------\n\n");
			fclose($inF);
		}
	}
	
	# sessionID check and refresh
	$flag = 0;
	if(isset($_POST["sid"]) && isset($_POST["slastreload"]) && isset($_POST["smaxtime"])){
		$DBRESULT =& $pearDB->query("SELECT * FROM session WHERE session_id = '".$_POST["sid"]."'");
		if (PEAR::isError($DBRESULT))
			print "DB Error : ".$DBRESULT->getDebugInfo()."<br>";
		if($session =& $DBRESULT->fetchRow()){
			$flag = $_POST["slastreload"];
			if(time() - $_POST["slastreload"] > ($_POST["smaxtime"] / 4)){		
				$flag = time();
				$DBRESULT2 =& $pearDB->query("UPDATE `session` SET `last_reload` = '".time()."', `ip_address` = '".$_SERVER["REMOTE_ADDR"]."' WHERE CONVERT( `session_id` USING utf8 ) = '".$_POST["sid"]."' LIMIT 1");
				if (PEAR::isError($DBRESULT2))
					print "DB Error : ".$DBRESULT2->getDebugInfo()."<br>";
			}
		}
	}

/*
	if (!$flag)
		exit(1);
*/
	
	if (isset($_POST["time"]) && isset($_POST["arr"]) && isset($_POST["type"])  && isset($_POST["version"]) && isset($_POST["sid"])&& isset($_POST["fileStatus"])&& isset($_POST["num"])&& isset($_POST["search"]) && isset($_POST["limit"])&& isset($_POST["order"])&& isset($_POST["sort_type"])&& isset($_POST["search_type_service"])&& isset($_POST["search_type_host"])&& isset($_POST["date_time_format_status"])){
		read($_POST["time"], $_POST["arr"],$flag,$_POST["type"],$_POST["version"],$_POST["sid"],$_POST["fileStatus"],$_POST["num"],$_POST["search"],$_POST["limit"],$_POST["sort_type"],$_POST["order"],$_POST["search_type_host"],$_POST["search_type_service"],$_POST["date_time_format_status"]);
	} else if(isset($_GET["time"])&& isset($_GET["arr"]) && isset($_GET["type"])  && isset($_GET["version"]) && isset($_GET["sid"])&& isset($_GET["fileStatus"])&& isset($_GET["num"])&& isset($_GET["search"]) && isset($_GET["limit"])&& isset($_GET["order"])&& isset($_GET["sort_type"])&& isset($_GET["search_type_service"])&& isset($_GET["search_type_host"])&& isset($_GET["date_time_format_status"])){
		$_POST["sid"] = $_GET["sid"];
		read($_GET["time"], $_GET["arr"],$flag,$_GET["type"],$_GET["version"],$_GET["sid"],$_GET["fileStatus"],$_GET["num"],$_GET["search"],$_GET["limit"],$_GET["sort_type"],$_GET["order"],$_GET["search_type_host"],$_GET["search_type_service"],$_GET["date_time_format_status"]);
	} else {
		$buffer = null;
		$buffer .= '<reponse>';	
		$buffer .= 'none';	
		$buffer .= '</reponse>';	
		header('Content-Type: text/xml');
		echo $buffer;
	}
?>