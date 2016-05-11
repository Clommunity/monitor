<?php
//controllers/monitor-aas.php

$webpage="./check-serf/extra.php";
$script="./plug/resources/monitor-aas/common.sh";
$execpath="/../../check-serf";
$urlpath="/monitor-aas";
$admin = false;
$install_script="https://raw.githubusercontent.com/Clommunity/monitor/master/getgithub";

function _installed_monitor_aas(){
	global $webpage, $script;
	
	//$webpage and $script need to be installed!
	return (is_file($webpage) && is_file($script));


}

function _run_monitor_aas(){
	global $graph;
	return "true";
}

function index() {
	global $webpage, $admin, $urlpath, $staticFile;
	$admin = $_GET['admin'];
	$page = "";
	$buttons = "";

	$page .= hlc(t("Monitor as a Service"));
	$page .= hl(t("Monitor/Loggging extended service to Cloudy"),4);
//	$page .= par(t("This will generate a graphic of the SERF network, giving relevant information about the status of the nodes and the services."));

	if(!_installed_monitor_aas()){
	 	$page .= "<div class='alert alert-error text-center'>".t("Monitor as a Service not installed yet")."</div>\n";
		$page .= par(t("How to install?<br>Just click Install, and wait till it finishes. It will update Cloudy and services accordingly, and restart SERF."));
		$buttons .= addButton(array('label'=>t("Install"),'class'=>'btn btn-success', 'href'=>"$staticFile$urlpath/install"));
	} else {
	$page .= "<div class='alert alert-success text-center'>".t("Monitor as a Service installed")."</div>\n";
	//$buttons .= addButton(array('label'=>t("Show Graph"),'class'=>'btn btn-primary', 'type'=>'redirect','href'=>"$urlpath/monitor-aas/graph_show"));
	if($admin)
		$buttons .= addButton(array('label'=>t("Show Graph Extended"),'class'=>'btn btn-primary','href'=>"/check-serf/extra.php"));
	else
		$page .= ptxt("The service is still in development, more to come..");
	}

	$page .= $buttons;
	return(array('type' => 'render','page' => $page));
}

function install() {
	global $install_script,$staticFile,$urlpath;
	
	if(!_installed_monitor_aas()) {
	  //Just to make sure we get the install script error!
	 $cmd = "cd /tmp/ && mkdir -d monitor_inst && cd monitor_inst";
	 execute_program_shell($cmd)['output'];

	 $cmd = "cd /tmp/monitor_inst/ && curl -k  ".$install_script." | sh - 2>&1";
	 $ret = execute_program_shell($cmd)['output'];

	 $cmd = "cd /tmp ; rm -rf monitor_inst";
	 execute_program_shell($cmd)['output'];
	
	//$page = "";
	 if (strpos($ret,"Not") !== false)
		//$page .= "".$cmd." RET: ".$ret;
		setFlash(t("Monitor Service Error, msg: ".$ret),"error");
	 else
		setFlash(t("Monitor Service installed"),"success");
		//$page .= "INSTALLED: ".$ret;
	//configure?
	//return(array('type' => 'render','page' => $page));
	} else {
		setFlash(t("Monitor Service is already installed"),"message");
	}

	return(array('type'=>'redirect','url'=>$staticFile.$urlpath));
	
}

function graph_show() {
	global $webpage, $script, $css, $js, $js_end;

	$page ="";
	$buttons = "";
	
//	require "../../check-serf/extra.php";

	$page .= hlc(t("Monitor as a Service"));
        $page .= hl(t("Monitor"),4);
//        $page .= par(t("This will generate a graphic of the SERF network, giving relevant information about the status of the nodes and the services."));

	$page .= "<div>Getting graph from ".$webpage."</div>\n";
	
	$page .= "<div id='canvas' class=''>GRAPH: </div>";
	//$js[]=givejs($js);

	//$page .= $js;

	$page .= $buttons;
	return(array('type'=>'render','page'=>$page));
}
