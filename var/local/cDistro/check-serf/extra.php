<?php	
	 // Instead it will give elements to be shown in other pages
	$DEBUG=false;
	$embbed=true;
	$db="/db/neighbours.db";
	$start_time="1400000000"; // 2014
	
	if($embbed) {
	//embbeding with cloudy elements
	require "../config/global.php";
	require "../core.php";
	require "../lib/session.php";
	require "../lib/lang.php";
	require "../lib/form.php";
	require "../lib/view.php";
	require "../lib/errors.php";
	require "../lib/utilio.php";
	require "../lib/auth.php";
	require "../lib/guifi_api.php";
	
        require "../lib/menus.php";
        require "../lib/avahi.php";

	$staticPath=$staticPath."/";
	//Need to modify staticFile for the menus to work
	$staticFile="/index.php";

	$css = array('../../css/bootstrap.min','../../css/bootstrap-responsive.min', '../../css/jquery.dataTables','../../css/main', 'vis.min', 'mine');
	$js = array('../../js/jquery-1.11.0.min','../../js/jquery.dataTables.min','../../js/bootstrap.min','vis.min');
	$js_end = array('../../js/main');
	
	require "../templates/header.php";
        require "../templates/menu.php";
        require "../templates/begincontent.php";
        require "../templates/flash.php";

	echo hlc(t("Monitor-aAS"));
	echo hl(t("Graph"),4);

	echo "<div id=\"line-chart-legend\"></div><div id=\"canvas\"></div></br>";


        $func=$_GET['func'];
	if($func == "getdata")
	getdata();
	else
	start_graph();

	echo "<br>".addButton(array('label'=>t("Reload Graph"),'class'=>'btn btn-success','href'=>'./extra.php')); //buttons
	echo "".addButton(array('label'=>t("Get Data"),'class'=>'btn btn-success','href'=>'./extra.php?func=getdata')); //buttons
	echo "".addButton(array('label'=>t("Back"),'class'=>'btn btn-success','href'=>'/..'.$staticFile.'/monitor-aas')); //buttons

        require "../templates/endcontent.php";
        require "../templates/footer.php";
        require "../templates/endpage.php";

	} else {
	echo "<!DOCTYPE html>";
	echo "<html><head><title>Analisis</title><script src=\"js/Chart.js\"></script><script src=\"js/vis.min.js\"></script><link href=\"css/vis.min.css\" rel=\"stylesheet\" type=\"text/css\"/><link href=\"css/mine.css\" rel=\"stylesheet\" type=\"text/css\"/>";
	echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1, user-scalable=no\">";
	echo "<link rel=\"stylesheet\" href=\"http://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css\"/>
 		<script src=\"https://ajax.googleapis.com/ajax/libs/jquery/1.12.0/jquery.min.js\"></script>
  		<script src=\"http://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js\"></script>";
	echo "<style> canvas{ } h1 { text-align: center; } </style></head>";
	echo "<body><div id=\"line-chart-legend\"></div><div id=\"canvas\"></div>";
	echo "<button type=\"button\" onclick=\"window.location.href='./extra.php?func=getdata'\">Get data</button>";
	echo "<button type=\"button\" onclick=\"window.location.href='./extra.php'\">Graph</button>";
	echo "<p>The data below is unprocessed directly from database and should be used to create Graphics</p>";
	echo "<p>The data is also duplicated, since every time we gather data from SERF it will retrieve old and new data, therefore it needs to account with that and remove duplications</p>";
	$func=$_GET['func'];
	if ($func == "getdata")
	 getdata();
	else
	 start_graph();
	}


	//echo "</body></html>";
	//echo "";
	//GET DATA globals func=getdata
	function getdata() {
	 global $embbed, $start_time;
	 //Will run the script info_serf.sh
	 $scrp="./info_serf.sh";
	 if (file_exists($scrp))
		$ret=shell_exec($scrp);
	 $ret=str_replace("\n","<br>", $ret);
	 if(!$embbed)
	 echo "<p>".$ret."</p>";
	 if($embbed)
	 echo ptxt($ret);
	 if(!$embbed) echo "</body></html>";
	}

	function substr_startswith($haystack, $needle) {
	    return substr($haystack, 0, strlen($needle)) === $needle;
	}

	function start_graph() {
	global $DEBUG, $embbed;
	 uncompressDB();
	
	 $data=getDataDB();
	 $groups=array();

	foreach ($data as $arr) {
		$tmparr = array($arr['extra'], $arr['Timestamp']);
	
		if(empty($groups[$arr['serfnodename']]))// {
		// $groups[$arr['serfnodename']][]=$tmparr;
		//} else {
		 $groups[$arr['serfnodename']]=array();
		//}
		$groups[$arr['serfnodename']][]=$tmparr;
		//$groups[$arr['serfnodename']]=array_push($groups[$arr['serfnodename']], $tmparr);
		
		//print_r($groups[$arr['serfnodename']]);
		//foreach ($arr as $key => $value) {
			//We have now each row as key=>value
			
		//}
	}

	$graph=array();
	foreach ($groups as $n => $ga) {
		if($DEBUG) echo "<p>";
		if($DEBUG) echo "Node: ".$n."<br>";
		foreach ($ga as $a) {
			if($DEBUG) echo "Time: ".$a[1]."<br>";
			if($DEBUG) echo "<p>JSON: ";//.str_replace('\'','"',$a[0])."<br><br>";
			$obj=json_decode(str_replace('\'','"',$a[0]));

			if(empty($obj) ) continue;

			//We are going to process only peerstreamer for now
			foreach ($obj as $serv) {
			if($DEBUG) echo "Service: ".$serv->h."<br>";
				foreach ($serv->v as $info) {
					if($DEBUG)
					echo "Started at: ".$info->stime." Ended at: ".$info->ttime."<br>";
					//Graph elements created here!
					//do we need $serv->h ?
					if(empty($graph[$n]))
					 $tmpg=array();
					else
					 $tmpg=$graph[$n];
				// Depending on the service :
				$st = null;
				$tt = null;
				$name = null;
				$other = null;
				if(substr_startswith($serv->h, "tahoe")) {
					$st = $info->{'introducer.stime'};
					if(empty($st)) $st = $info->{'stime'};
					$tt = $info->{'ttime'};
					if(empty($tt)) $tt = $info->{'introducer.ttime'};
					$name = $serv->h;
					$other = $info->{'introducer.memory'}." ".$info->{'introducer.cpu'};
					//IF there is node information should be as a new service
					if(!empty($info->{'node.pid'})) {
					 array_push($tmpg, array($info->{'node.stime'}, $info->{'node.ttime'}, $name."-Storage", ""));
					}
				} else {
					$st = $info->stime;
					$tt = $info->ttime;
					$name = $serv->h;
					$other = $info->other;
				}
					array_push($tmpg, array($st, $tt, $name, $other));
					$graph[$n]=$tmpg;
				}
			}
		}
	}

	$tmpgraph=$graph;
	// Here will remove duplicates
	foreach ($graph as $key => $value) {
	//in value = array(stime, ttime) if stime == another and ttime the same than erase
	$tmpa = array();
	foreach ($value as $a) {
  	  $tmpb = -1;
	  //if service previously had no ttime and $a has update
	  if(($tmpb=arraySearchP($tmpa, $a)) >= 0)
	   $tmpa[$tmpb]=$a;
	  else if(arraySearchPR($tmpa, $a))
		continue;
	  else if(!arraySearch($tmpa, $a)) {
	   array_push($tmpa, $a);
//	echo "PUSHED ".$a[0]."<br>";
		}
	 }
	$tmpgraph[$key]=$tmpa;
	}
	$graph=$tmpgraph;
	
	if($DEBUG) echo "<p><br></p>";

	compressDB();
	if ($DEBUG) {
	echo "<p>DATA PROCESSED:</p>";
		foreach ($graph as $key => $value) {
			echo "<p>Node: ".$key."<br>";
			foreach ($value as $a) {
				echo "Service: ".$a[2]."<br>";
				echo "Start Time: ".$a[0]." End Time: ".$a[1]."<br>";
			}
			echo "</p>";
		}
	}
	if(!$embbed)
	 echo "</body></html>";

	// createGraph($graph);
	 //Creates Gantt graph
	 createVisGraph($graph);
	}

	function arraySearchPR($total, $s)  {
	foreach ($total as $uu) {
	  if(!empty($uu) && $uu[0] == $s[0] &&
		$uu[2] == $s[2] && !empty($uu[1])
		&& empty($s[1]))
		return true;
	}

	return false;
	}

	function arraySearchP($total, $s) {
	//Will search $s partially in $total ([1] = ttime)
	// if there is in $total an $s even without ttime return its location on array
	$i=0;
	foreach ($total as $uu) {
		if (!empty($uu) && $uu[0] == $s[0] 
                && $uu[2] == $s[2] && empty($uu[1]) 
                && !empty($s[1]))
		return $i;

	  $i++;
	}
	
	return -1;
	}

	function arraySearch($total, $s) {
	//Will search s in total if there is than return true else false
		foreach ($total as $k) {
//			echo "COMP: ".!empty($k)." && ".$k[0]." == ".$s[0]." && ".$k[1]." == ".$s[1]." && ".$k[2]." == ".$s[2]."<br>";
			if(!empty($k) && $k[0] == $s[0] && $k[1] == $s[1] && $k[2] == $s[2])
				return true;
		}
		return false;
	}
	
	function rearrange_db($matrix) {
		//Because same service as to stop when another instance begins (no ttime)
		global $start_time;
//file_put_contents("./test.txt", json_encode($matrix, JSON_PRETTY_PRINT));
		$tmpm = $matrix;
		 //foreach node
		foreach ($tmpm as $key => $arr) {
		 //for each service
		 $ss = null;

		 $tmp = $arr;
		 for ($i=0; $i<count($arr); $i++) {
		 $pos = s_least_time($tmp, date(DATE_ATOM));
		 $tmp[$pos] = array();
//file_put_contents("./test.txt", $pos."\n", FILE_APPEND);
		if(!empty($arr[$pos][1])) continue;
		 $next = s_least_time($tmp, date(DATE_ATOM), $arr[$pos][2]);
 		 $tmpm[$key][$pos][1] = $arr[$next][0]-10;
		}
		

		}

//		file_put_contents("./test1.txt", json_encode($tmpm, JSON_PRETTY_PRINT));
		return $tmpm;
	}

	function s_least_time($l, $time, $name = null) {
	  $pos = -1;
	 for ($j = 0; $j<count($l); $j++) {
	  if (empty($l[$j])) continue;
	  if ($name != null && $name != $l[$j][2]) continue;
	  if($l[$j][0] < $time) {
	  $pos = $j;
	  $time = $l[$j][0];
	  }
	 }
 	 return $pos;
	}

	function createVisGraph($matrix) {
	global $start_time;
	echo "<script type=\"text/javascript\"> 
		//var stime = new Date('".date(DATE_ATOM,1455907102)."');
		//var ttime = new Date('".date(DATE_ATOM,1455907402)."');
		var container = document.getElementById(\"canvas\");", PHP_EOL;
	$i=0;

	//Vis groups = nodes
	echo "var groupCount = ".count($matrix).";", PHP_EOL; //is it like this?
	echo "var names = [";
	foreach ($matrix as $key => $lines)
	 echo "'".$key."',";
	echo "];", PHP_EOL;
	echo "var groups = new vis.DataSet();", PHP_EOL;
	for ($g=0;$g<count($matrix);$g++)
	 echo "groups.add({id: ".$g.", content: names[".$g."]});", PHP_EOL;
	
	//Items = unique service of the node
	echo "var items = new vis.DataSet();", PHP_EOL;
	$g=0;

	//Needs a Hard Fix!
	$matrix = rearrange_db($matrix);

	foreach ($matrix as $key => $lines) {	
	  for ($j=0;$j<count($lines);$j++) {
		if (empty($lines[$j][0]) && empty($lines[$j][1])) continue;
		 echo "items.add({id:".$i++.", group: ".$g.", header: '".$lines[$j][2]."', ";
		 echo "description: \"memory: ,&#x0a;cpu: ,&#x0a;other:".$lines[$j][3]."\", ";
		if(empty($lines[$j][0]))
		 echo "start: '${start_time}',"; //NEED TO CHANGE TO START AT ONE POINT IN TIME instead of NULL
		else
		 echo "start: '".date(DATE_ATOM,$lines[$j][0])."',";
		if(empty($lines[$j][1]))
		 echo "end: '".date(DATE_ATOM)."'}";
		else
		 echo "end: '".date(DATE_ATOM,$lines[$j][1])."'}";
		echo ");", PHP_EOL;
	 } 
	 $g++;
	}
	echo "var options={ groupOrder: 'content'
			,
			template: function (item) {
			 return '<u1 class=\"list-inline\"><li><a href=\"#\" aria-describedby=\"\" data-toggle=\"tooltip\" data-placement=\"rigth\" title=\"' + item.id + '-' + item.description + '\">' + item.header +'</a></li>';
			}
			};", PHP_EOL;
	//		timeAxis: {scale: 'hour', step: 1}
	//		};

	echo "var timeline = new vis.Timeline(container);
		timeline.setOptions(options);
		timeline.setGroups(groups);
		timeline.setItems(items);";
	echo "
		$(document).ready(function(){
    		$('[data-toggle=\"tooltip\"]').tooltip();
		});
		</script>";

	}


	function createGraph($matrix) {
		//This creates Line Graph - From Agusti scripts
		echo "<script> ";
		echo " var xhr = new XMLHttpRequest(); xhr.open('GET','db/neighbours.db.bz2', true); xhr.responseType = 'arraybuffer'; xhr.onload = function(e) {";
		echo " var datacolor={\"97BBCD\":\"On-line\", \"781678\":\"SERF\", \"78DC78\":\"DNS\", \"167878\":\"Proxy\", \"787816\":\"Graph Server\", \"E53D00\":\"Syncthing\", \"7878DC\":\"PeerStreamer\", \"DC7878\":\"OpenVZ Web Panel\"};";
		
		// THE MATRIX NEEDS TO BE CONVERTED HERE as ECHO of the arrays
		//echo " var peerst = [];";
		$i=0;
		$py0=array();
		$py1=array();
		//echo " 
                  //                var py0 = [];
                    //              var py1 = [];";
		//Each node as a PY
		foreach ($matrix as $times) {
			$pytxt = "
				  var py".$i." = [];";
			
			for ($j=0;$j<count($times);$j++) {
				  //var py1 = [];";
			if (!empty($times[$j][0])) {
			$pytxt .= " py".$i.".push(".$times[$j][0].");";
			} else $pytxt .= " py".$i.".push(0);"; //NOT 0 but start time
			if( !empty($times[$j][1])) {
			$pytxt .= " py".$i.".push(".$times[$j][1].");";
			} else $pytxt .= " py".$i.".push(1456400000);"; //End Time
			}
			$py[$i++]=$pytxt;
		}
		foreach ($py as $ys) echo $ys;
		//Needs Labels Here!
		echo "
			var label = [];";
		
		//We also need to include each PY as a dataset
		echo " var ChartData = { labels : label,
                	datasets : [";
		$i=0;
		//for ($i=0; $i<2; $i++) {
		foreach ($py as $ys) {	
		  echo "
                        {
                                fillColor : \"rgba(0,0,0,0)\",
                                strokeColor : \"rgba(151,187,205,1)\",
                                pointColor : \"rgba(151,187,205,1)\",
                                pointStrokeColor : \"#fff\",
                                label: 'PeerStreamer".$i."',
                                data : py".$i++."
                        },";
			
		}
		echo "	]  }";
		echo " 
		      var legend = \"<ul style=\\\"list-style-type: none;margin: 0;padding: 0;\\\">\";";
		echo " for (var k in datacolor){ legend += \"<li style=\\\"display: inline;float: left;\\\"><div style=\\\"background-color: #\" + k  + \"; height: 20px; width: 20px; margin 5px;float:left\\\"></div><div style=\\\"margin-left: 5px; margin-right: 20px;display:inline\\\">\"+datacolor[k]+\"</div></li>\";";
		echo " } legend += \"</ul>\";";

		echo " document.getElementById(\"line-chart-legend\").innerHTML = legend;";
		echo " var ctx = document.getElementById(\"canvas\").getContext(\"2d\");";
		echo " var grafic = new Chart(ctx).Line(ChartData, {
                bezierCurve: false,
                multiTooltipTemplate: \"<%= value %><%if (datasetLabel){%> <%=datasetLabel%><%}%>\",
                responsive : true,
                pointDotRadius : 3,
                pointDot : true,
                animation: false, }); 
		}";

		echo " 
			xhr.send();";
		echo " 
			</script>";
	}

	function createGoogleGraph($matrix) {
	  echo "<script type=\"text/javascript\">
    		google.charts.load('current', {'packages':['gantt']});
    		google.charts.setOnLoadCallback(drawChart);

    		function daysToMilliseconds(days) {
     		 return days * 24 * 60 * 60 * 1000;
    		}

    		function drawChart() {

      		var data = new google.visualization.DataTable();
      		data.addColumn('string', 'Task ID');
      		data.addColumn('string', 'Task Name');
      		data.addColumn('date', 'Start Date');
      		data.addColumn('date', 'End Date');
      		data.addColumn('number', 'Duration');
      		data.addColumn('number', 'Percent Complete');
      		data.addColumn('string', 'Dependencies');

      		data.addRows([
       		 ['Research', 'Find sources',
        		 new Date(2015, 0, 1), new Date(2015, 0, 5), null,  100,  null],
       		 ['Write', 'Write paper',
        		 null, new Date(2015, 0, 9), daysToMilliseconds(3), 25, 'Research,Outline'],
       		 ['Cite', 'Create bibliography',
       		  	 null, new Date(2015, 0, 7), daysToMilliseconds(1), 20, 'Research'],
       		 ['Complete', 'Hand in paper',
        		 null, new Date(2015, 0, 10), daysToMilliseconds(1), 0, 'Cite,Write'],
        	['Outline', 'Outline paper',
        		 null, new Date(2015, 0, 6), daysToMilliseconds(1), 100, 'Research']
      		]);

      		var options = {
       		 height: 275
      		};

      		var chart = new google.visualization.Gantt(document.getElementById('canvas'));

      		chart.draw(data, options);
    }
  </script>";

	}


	function getDataDB() {
		global $db;
		$data="";
		$handle=sql_open(getcwd().$db, "rw");
		$query="SELECT * FROM extra order by id";
		$data=sqlite_query($handle, $query);
		//We need to get all data
		$row = array();

		$i = 0;
		while ( $res = sqlite_fetch_array($data, $data) ) {
		  $row[$i++] = $res;
		 }
		return $row;
	}

	function uncompressDB() {
		global $db, $embbed;
		if ( file_exists(getcwd().$db.".bz2") )
			shell_exec("bunzip2 ".getcwd().$db.".bz2");
		if(!$embbed)
		 echo "<p>DB Uncompressed</p>";
	}

	function compressDB() {
		global $db, $embbed;
		if ( file_exists(getcwd().$db) )
			shell_exec("bzip2 ".getcwd().$db);
		if(!$embbed)
		 echo "<p>DB Compressed</p>";
	}
	
	function sql_open($location, $mode) {
		$handle = new SQLite3($location);
		return $handle;
	}
	
	function sqlite_query($dbhandle,$query) 
	{ 
	    $array['dbhandle'] = $dbhandle; 
	    $array['query'] = $query; 
	    $result = $dbhandle->query($query); 
	    return $result; 
	} 
	

	function sqlite_fetch_array(&$result,$type) 
	{ 
	    $i = 0; 
	    while ($result->columnName($i)) 
	    { 
	        $columns[ ] = $result->columnName($i); 
	        $i++; 
	    } 
	    
	    $resx = $result->fetchArray(SQLITE3_ASSOC); 
	    return $resx; 
	} 

?>
