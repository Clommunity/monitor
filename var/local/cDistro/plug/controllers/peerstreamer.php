<?php
//peerstreamer
$pspath="/opt/peerstreamer/";
$psprogram="streamer-udp-grapes-static";
define("pspath", "/opt/peerstreamer/");
define("psprogram", "streamer-udp-grapes-static");
$title="Peer Streamer";

//VLC
$vlcpath="/usr/bin/";
$vlcprogram="cvlc";
$vlcuser="nobody";

//Avahi type
$avahi_type="peerstreamer";

//psutils
$psutils=dirname(__FILE__)."/../resources/peerstreamer/pscontroller";

//curl
$curlprogram="/usr/bin/curl";

// Aquest paquest no existeix encarà i per tant pot donar algun problema.
$pspackages="peer_web_gui";

function index(){
	global $paspath,$title;
	global $staticFile;

	$page=hlc(t($title));
	$page .= hl(t("A cloud P2P Media Stream system"),4);
	$page .= par("<a href='http://peerstreamer.org'>".t("PeerStreamer")."</a>". t(" is an open source P2P Media Streaming framework written in C.").' '.t("It includes a streaming engine for the efficient distribution of media streams.") .' '. t("A source application for the creation of channels and a player applications to visualize the streams."));
	$page .= txt(t("PeerStreamer status:"));

	if ( ! softwareinstalled() ) {
		$page .= "<div class='alert alert-error text-center'>".t("PeerStreamer is not installed")."</div>\n";
		$page .= par(t("Click on the button to install PeerStreamer and view share videos from users."));
		$buttons .= addButton(array('label'=>t("Install PeerStreamer"),'class'=>'btn btn-success', 'href'=>$staticFile.'/peerstreamer/getprogram'));
		$page .= $buttons;
		return(array('type'=>'render','page'=>$page));
	} else {
		$page .= "<div class='alert alert-success text-center'>".t("PeerStreamer installed")."</div>\n";
		$page .= _listPSProcs();
		
		$page .= addButton(array('label'=>t('Connect to Peer'),'href'=>$staticFile.'/peerstreamer/connect'));
		$page .= addButton(array('label'=>t('Publish a video stream'),'href'=>$staticFile.'/peerstreamer/publish'));
		$page .= "<div class=text-right>";
		$page .= addButton(array('label'=>t('Uninstall Peerstreamer'),'class'=>'btn btn-danger', 'pull-right', 'href'=>$staticFile.'/peerstreamer/uninstall'));
		$page .= "</div>";


		return(array('type' => 'render','page' => $page));
	}
}

function connect_get(){
	global $paspath,$title;
	global $staticFile;

	if (isset($_GET['ip']))
		$peerip = $_GET['ip'];
	else
		$peerip = "";

	if (isset($_GET['port']))
		$peerport = $_GET['port'];
	else
		$peerport = "";

	$page = hlc(t($title));
	$page .= hlc(t('Connect to a Peer'),2);
	$page .= par(t("You can join a stream through a Peer in the network, or you can find channels in the avahi menu option."));
	$page .= createForm(array('class'=>'form-horizontal'));
	$page .= t('Peer:');
	$page .= addInput('ip',t('IP Address'),$peerip);
	$page .= addInput('port',t('Port Address'),$peerport);
	$page .= t('You:');
	$page .= addCheckbox('type', t('Server Type'), array('RTSP'=>t('Create RTSP Server'),'UDP'=>t('Send to UDP Server')));
	$page .= addInput('myport',t('Port'));
	$page .= addSubmit(array('label'=>t('Connect'),'class'=>'btn btn-primary'));
	$page .= addButton(array('label'=>t('Cancel'),'href'=>$staticFile.'/peerstreamer'));


	return(array('type' => 'render','page' => $page));
}

function connect_post(){
	//Validar dades
	$ip = $_POST['ip'];
	$port = $_POST['port'];
	$myport = $_POST['myport'];
	$tipo = $_POST['type'];

	if (! softwareinstalled()) {
		$error = "Requiered software is not installed";
		return (array('type' => 'render','page' => pserror($error)));
	}

	if ( 0 == 0 ){  // validar
		return(array('type' => 'render','page' => _psshell($ip,$port,$myport,$tipo)));
	}
}

function uninstall(){
	global $psutils;
	global $staticFile;
	
	$cmd = $psutils." uninstall";
	execute_program_detached($cmd);

	$output = ptxt("Peerstreamer has been removed");

	setFlash($output);

	return(array('type'=>'redirect','url'=>$staticFile.'/peerstreamer'));
	return page;
}

function publish_get(){
	global $paspath,$title;
	global $staticFile;

	$page = hlc(t($title));
	$page .= hlc(t('Publish a video stream'),2);
	$page .= par(t("Please write a stream source"));
	$page .= par(t("If the URL is a rtmp, please make sure to introduce all the requiered parameters separated ONLY by a simple comma."));
	$page .= createForm(array('class'=>'form-horizontal'));
	$page .= addInput('url',t('URL Source'),'',array('class'=>'input-xxlarge'));
	$page .= addInput('port',t('Port Address'));
	$page .= addInput('description',t('Describe this channel'));
	$page .= addSubmit(array('label'=>t('Publish'),'class'=>'btn btn-primary'));
	$page .= addButton(array('label'=>t('Cancel'),'href'=>$staticFile.'/peerstreamer'));

	return(array('type' => 'render','page' => $page));
}

function publish_post(){
	$url = $_POST['url'];
	$port = $_POST['port'];
	$description = $_POST['description'];
	$ip = "";

	$page = "<pre>";
	$page .= _pssource($url,$ip,$port,$description);
	//foreach ($_POST as $k => $v) {
	//	$page .= "$k:$v\n";
	//}
	//$page .= "Datos....description:".$description;
	$page .= "</pre>";

	return(array('type' => 'render','page' => $page));

}

function vlcobject($url){

	$o = "";
	$o .= '<div id="vlc-plugin" >';
	$o .= '<!-- <object classid="clsid:9BE31822-FDAD-461B-AD51-BE1D1C159921" codebase="http://download.videolan.org/pub/videolan/vlc/last/win32/axvlc.cab"></object> -->';
	$o .= '<embed pluginspage="http://www.videolan.org"';
	$o .= 'type="application/x-vlc-plugin"';
	$o .= 'version="VideoLAN.VLCPlugin.2"';
	$o .= 'width="720" volume="50"';
	$o .= 'height="480"';
	$o .= 'name="vlc" id="vlc"';
	$o .= 'autoplay="true" allowfullscreen="true" windowless="true" loop="true" toolbar="false"';
	$o .= ' target="'.$url.'">';
	$o .= '</embed>';
	$o .= '</div>';

	return($o);
}

// Utils
function _psshell($ip,$port,$myport,$type)
{
	global $pspath,$psprogram,$vlcpath,$vlcprogram,$vlcuser,$psutils;

	$ipclient = $_SERVER['REMOTE_ADDR'];
	$portclient = $myport;
	$device = getCommunityDev()['output'][0];
	$ipserver = getCommunityIP()['output'][0];

	/*
	$page .= par(t('Start peerstreamer:'));
	//$cmd = $pspath  . $psprogram . " -i " . $ip . " -p " . $port . " -P " . $port . " -F null,dechunkiser=udp,port0=" . $portclient . ",addr=" . $ipclient . " -I ". $device .  " &";
	$cmd = $pspath  . $psprogram . " -i " . $ip . " -p " . $port . " -P " . $port . " -F null,dechunkiser=udp,port0=" . $portclient . ",addr=127.0.0.1 -I ". $device ;
	$page .= ptxt($cmd);

	execute_program_detached($cmd);

	$page .= par(t('Start vlc like rtsp server:'));

	$cmd = $vlcpath."/". $vlcprogram .' udp://@127.0.0.1:' . $portclient.' --sout=#rtp{sdp=rtsp://:' . $port . '/} --sout-keep';
	$page .= ptxt($cmd);

	execute_program_detached_user($cmd,$vlcuser);
	$page .= par(t('Please open your Video Player with <b>'). 'rtsp://' . $ipserver . ":" . $port . '/</b>');


	//Checking requiered software
	if(!softwareInstalled) {
		$error = "Requiered software is not installed.";
		return(pserror($error));
	}
	*/

	if ($type == "UDP") {
		$cmd = $psutils." connectudp $ip $port $ipclient $myport $device";
		execute_program_detached($cmd);
		$page .= _psviewer("udp://@".$ipclient.":".$myport);
	} else {
		$cmd = $psutils." connectrtsp $ip $port $myport $ipserver $device";
		execute_program_detached($cmd);
		$page .= _psviewer("rtsp://".$ipserver.":".$myport."/");
	}
	return($page);
}

function softwareinstalled() {

	global $pspath,$psprogram,$vlcpath,$vlcprogram;

	return( file_exists($pspath . $psprogram) && file_exists($vlcpath . $vlcprogram) );
}

function _isInstalled() {

	return( file_exists(pspath . psprogram) );
}

function _psviewer($url){

	global $title;

	$page = hlc(t($title));
	$page .= par(t("PeerStreamer s'està executant en segon pla, si tens el connector de vlc podràs veure el video al teu navegador."));
	$page .= vlcobject($url);
	$page .= par(t("Alternativament pots accedir al video usant el següent enllaç al teu player preferit."));
	$page .= ptxt($url );

	return($page);
}

function psviewer(){

	global $staticFile;

	$url = $_GET['u'];

	$p = _psviewer($url);
	$p .=  addButton(array('label'=>t('List'),'href'=>$staticFile.'/peerstreamer'));
	return(array('type' => 'render','page' => $p));
}

function pserror($error) {

	global $title;

	$page = hlc(t("Oooops!"));
	$page .= par(t("An error occurred while executing Peerstreamer: " . $error));

	return($page);
}

function _pssource($url,$ip,$port,$description){

	global $pspath,$psprogram,$title,$vlcpath,$vlcprogram,$vlcuser,$psutils,$avahi_type,$avahi_extra;

	$page = "";
	$device = getCommunityDev()['output'][0];

	if ($description == "") $description = $type;

/*
	// Crear Stream con vlc

	$page .= par(t('Started VLC to get stream to pass PeerStreamer.'));
	$cmd = "/bin/su " . $vlcuser . " -c '" . $vlcpath . "/". $vlcprogram .' "'.$url.'"  --sout "#std{access=udp,mux=ts,dst='. $vlcipclient .':'. $portclient .'} "'."'";
	$temp = $cmd."\n";
	execute_program_detached($cmd);
	$page .= ptxt($temp);

	// Activar ps
	$page .= par(t('Started PeerStreamer instance, and send stream to client.'));
	$cmd = $pspath  . $psprogram . " -f null,chunkiser=udp,port0=" . $portclient . ",addr=" . $psipclient .  " -P " . $port . " -I " . $device ."";
	$temp = $cmd."\n";
	execute_program_detached_user($cmd,$vlcuser);
	$page .= ptxt($temp);
*/
	//Adding stuff to txt as stime=,rtime=,exec=


	$cmd = $psutils." publish $url $port $device $description";
	execute_program_shell($cmd);

	// Publish in avahi system.
	$page .= par(t('Published this stream.'));
	$description = str_replace(' ', '', $description);
	//Only use extra monitor IF avahi_extra is true
	if (isset($avahi_extra) && $avahi_extra) {
		//New way of dealing is calling common.sh
		$line=execute_program_shell("/bin/bash /var/local/cDistro/plug/resources/monitor-aas/common.sh gather_information ".$avahi_type." ".$port)['output'];
		$einfo="einfo=".trim(strtr($line, array('['=>'',']'=>'',' '=>'',','=>';')));
		//2x Addslashes needed!
		$einfo=addslashes(addslashes($einfo));
		$temp = avahi_publish($avahi_type, $description, $port, $einfo);
		$page .= ptxt($temp);
	} else {
		$temp = avahi_publish($avahi_type, $description, $port, "");
		$page .= ptxt($temp); 
	}

	$page .= addButton(array('label'=>t('Back'),'href'=>$staticFile.'/peerstreamer'));

	return($page);
}

function _listPSProcs(){

	global $psutils,$staticFile;
	// Fer un llistat del PS actius!
	$page="";
	$ret = execute_program($psutils." info json");
	$datos = json_decode(implode("\n",$ret['output']),true);

	if (count($datos) > 0) {
		$page = hl(t('List PeerStreamer'),3);

		$table = addTableHeader(array('Type', 'Port', 'Internal Port', 'Others', 'Actions'));
		foreach($datos as $v){
			switch ($v['type']) {
				case 'PeerRTSPServer':
				case 'PeerUDP':
					$actions = addButton(array('label'=>t('View'),'href'=>$staticFile.'/peerstreamer/psviewer?u='.urlencode($v['other'])));
					$actions .= addButton(array('label'=>t('Stop'),'href'=>$staticFile.'/peerstreamer/psstop?p='.$v['port']));
					break;
				case 'Source':
					$actions = "";
					$actions .= addButton(array('label'=>t('Stop'),'href'=>$staticFile.'/peerstreamer/psstop?p='.$v['port']));
					break;
			}
			$d = array($v['type'], $v['port'], $v['internalport'],$v['other'], $actions);
			$table .= addTableRow($d);
		}
		$table .= addTableFooter();
		$page .= $table;
	}
	//print_r($datos);
	//exit();

	return ($page);

}
function psstop(){

	global $psutils,$staticFile,$avahi_type;

	$port = $_GET['p'];

	// validar $port is integer!

	$cmd = $psutils." disconnect ".$port;
	execute_program_detached($cmd);
	$temp = avahi_unpublish($avahi_type, $port);
	$flash = ptxt($temp);
	setFlash($flash);

	return(array('type'=>'redirect','url'=>$staticFile.'/peerstreamer'));
}

function getprogram(){
	global $psutils,$staticFile;

/*	//Exist directory?
	if (is_dir($pspath)){

	}
	//Exist file?
	if (!file_exists($pspath.$psprogram)){

	}

	$machine_path="";
	$uname=posix_uname();
	switch($uname['machine']){
		case "i686":
		case "i386":
			$machine_path = "i386";
			break;
		case "x86_64":
			$machine_path = "amd64";
			break;
		case "armv6l":
			$machine_path = "arm";
			break;
	}

	$geturlfile=$ghpath.$machine_path."/".$psprogram;
	$savefile=$pspath.$psprogram;

	$output=execute_program($curlprogram." -k '".$geturlfile."' -o ".$savefile);

	$ret = ptxt(implode("\n", $output['output']));
	chmod($savefile, 755);

	*/

	$ret = execute_program($psutils." install");
	$output = ptxt(implode("\n",$ret['output']));

	setFlash($output);

	return(array('type'=>'redirect','url'=>$staticFile.'/peerstreamer'));


}

function getprogram_post(){

}

function isPSInstalled(){
	global $pspath, $psprogram;

	return(file_exists($pspath.$psprogram));
}
