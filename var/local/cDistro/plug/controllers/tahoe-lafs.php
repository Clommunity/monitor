<?php
//tahoe-lafs.php

$RESOURCES_PATH=$_SERVER['DOCUMENT_ROOT'].'/plug/resources/tahoe-lafs';
$TAHOELAFS_CONF="tahoe-lafs.conf";
$TAHOE_VARS=load_conffile($RESOURCES_PATH.'/'.$TAHOELAFS_CONF);

function index(){
	global $TAHOE_VARS;
	global $staticFile;

	$page = "";
	$buttons = "";

	$page .= hlc(t("tahoe-lafs_common_title"));
	$page .= hl(t("tahoe-lafs_index_subtitle"),4);
	$page .= par(t("tahoe-lafs_index_description1") . ' ' .t("tahoe-lafs_index_description2") . ' ' . t("tahoe-lafs_index_description3"));

	$page .= txt(t("tahoe-lafs_common_status:"));

	if ( ! isTahoeInstalled($TAHOE_VARS['PACKAGE_NAME']) ) {
		$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_not_installed")."</div>\n";
		$page .= par(t("tahoe-lafs_index_instructions_1") . t("tahoe-lafs_index_instructions_2"));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_install"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/install'));

		$page .= $buttons;
		return(array('type' => 'render','page' => $page));
	}

	if( ! ( tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],'introducer') || tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],'node') ) ) {
		$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_installed_empty")."</div>\n";
		$page .= par(t("tahoe-lafs_index_instructions_1") . ' ' . t("tahoe-lafs_index_instructions_3"));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_create_introducer_start_grid"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/createIntroducer'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_create_storage_join_grid"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/createNode'));

		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_uninstall"),'class'=>'btn btn-danger', 'href'=>$staticFile.'/tahoe-lafs/uninstall','divOptions'=>array('class'=>'btn-group')));

		$page .= $buttons;
		return(array('type' => 'render','page' => $page));
	}

	if ( tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],'introducer') ) {
		if ( introducerStarted($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['TAHOE_PID_FILE']) )
			$page .= "<div class='alert alert-success text-center'>".t("tahoe-lafs_alert_introducer_running")."</div>\n";
		else
			$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_introducer_stopped")."</div>\n";

		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_manage_introducer"),'class'=>'btn btn-primary', 'href'=>$staticFile.'/tahoe-lafs/introducer','divOptions'=>array('class'=>'btn-group')));
	}
	if ( tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],'node') ) {
		if ( nodeStarted($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['TAHOE_PID_FILE']) )
			$page .= "<div class='alert alert-success text-center'>".t("tahoe-lafs_alert_storage_running")."</div>\n";
		else
			$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_storage_stopped")."</div>\n";

		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_manage_storage"),'class'=>'btn btn-primary', 'href'=>$staticFile.'/tahoe-lafs/node','divOptions'=>array('class'=>'btn-group')));
	}
	else
			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_create_storage"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/createNode'));


	$page .= $buttons;
	return(array('type' => 'render','page' => $page));
}

function install(){
	global $RESOURCES_PATH;
	global $TAHOE_VARS;
	global $staticFile;

	$page = "";
	$buttons = "";

	$page .= hlc(t("tahoe-lafs_common_title"));
	$page .= hl(t("tahoe-lafs_install_subtitle"),4);

	if ( isTahoeInstalled($TAHOE_VARS['PACKAGE_NAME']) ) {
		$page .= txt(t("tahoe-lafs_install_result"));
 		$page .= "<div class='alert alert-success text-center'>".t("tahoe-lafs_alert_installed_already")."</div>\n";
		$page .= txt(t("tahoe-lafs_install_details"));
		$page .= ptxt(packageInstallationInfo($TAHOE_VARS['PACKAGE_NAME']));
 		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));

 		$page .= $buttons;
		return(array('type' => 'render','page' => $page));
	}

 	$pkgInstall = ptxt(installPackage($TAHOE_VARS['PACKAGE_NAME']));

	if ( isPackageInstall($TAHOE_VARS['PACKAGE_NAME']) ) {
		$page .= txt(t("tahoe-lafs_install_result"));
		$page .= "<div class='alert alert-success text-center'>".t("tahoe-lafs_alert_installed_successful")."</div>\n";
		$page .= txt(t("tahoe-lafs_install_details"));
		$page .= $pkgInstall;

		$postInstall = array();
		$postInstallAll = "";

		$page .= txt(t("tahoe-lafs_install_post"));
		foreach (execute_program( 'addgroup --system tahoe' )['output'] as $key => $value) { $postInstall[] = $value; }
		foreach (execute_program( 'adduser --system --ingroup tahoe --home '.$TAHOE_VARS['DAEMON_HOMEDIR'].' --shell '.$TAHOE_VARS['DAEMON_SHELL'].' '.$TAHOE_VARS['DAEMON_USERNAME'] )['output'] as $key => $value) { $postInstall[] = $value; }
		execute_program_shell( 'touch ' .$TAHOE_VARS['DAEMON_HOMEDIR'] );
		foreach (execute_program( 'chown -vR '.$TAHOE_VARS['DAEMON_USERNAME'].':'.$TAHOE_VARS['DAEMON_GROUP'].' '.$TAHOE_VARS['DAEMON_HOMEDIR'])['output'] as $key => $value) { $postInstall[] = $value;}
		$postInstall[] = execute_program( 'cp -fv '.$RESOURCES_PATH.'/'.$TAHOE_VARS['TAHOE_INITD_FILE'].' '.$TAHOE_VARS['TAHOE_ETC_INITD_FILE'])['output'][0];
		$postInstall[] = execute_program( 'cp -fv '.$RESOURCES_PATH.'/'.$TAHOE_VARS['TAHOE_DEFAULT_FILE'].' '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE'])['output'][0];
		foreach (execute_program( 'chmod -v +x '.$TAHOE_VARS['TAHOE_ETC_INITD_FILE'] )['output'] as $key => $value) { $postInstall[] = $value; }
		$postInstall[] = execute_program( 'update-rc.d '.$TAHOE_VARS['TAHOE_ETC_INITD_FILENAME'].' defaults' )['output'][0];

		foreach ($postInstall as $k => $v) { $postInstallAll .= $v.'<br/>'; }
		$page .= ptxt($postInstallAll);
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));

		$page .= $buttons;
		return(array('type' => 'render','page' => $page));
		}

	$page .= txt(t("tahoe-lafs_install_result"));
	$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_installed_failed")."</div>\n";
	$page .= txt(t("tahoe-lafs_install_details"));
	$page .= $pkgInstall;
	$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
	$buttons .= addButton(array('label'=>t("tahoe-lafs_button_retry_install"),'class'=>'btn btn-warning', 'href'=>$staticFile.'/tahoe-lafs/install'));

	$page .= $buttons;
	return(array('type' => 'render','page' => $page));
}

function uninstall(){
	global $TAHOE_VARS;
	global $staticFile;

	$page = "";
	$buttons = "";

	$page .= hlc(t("tahoe-lafs_common_title"));
	$page .= hl(t("tahoe-lafs_uninstall_subtitle"),4);

	if ( ! isTahoeInstalled($TAHOE_VARS['PACKAGE_NAME']) ) {
			$page .= txt(t("tahoe-lafs_uninstall_result"));
			$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_uninstalled_already")."</div>\n";
 			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));

 			$page .= $buttons;
			return(array('type' => 'render','page' => $page));
	}

 	if ( tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],'introducer') || tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],'node')) {
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		if ( tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],'introducer') ){
		$page .= txt(t("tahoe-lafs_uninstall_result"));
		$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_introducer_configured") . '. '. t("tahoe-lafs_alert_introducer_stop_uninstall") . '.' . "</div>\n";
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_manage_introducer"),'class'=>'btn btn-primary', 'href'=>$staticFile.'/tahoe-lafs/introducer'));
		}
		if ( tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],'node') ){
		$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_storage_configured") . '. ' . t("tahoe-lafs_alert_storage_stop_uninstall") . '.' . "</div>\n";
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_manage_storage"),'class'=>'btn btn-primary', 'href'=>$staticFile.'/tahoe-lafs/node'));
		}

		$page .= $buttons;
		return(array('type' => 'render','page' => $page));
	}

	$pkgUninstall = ptxt(uninstallPackage("tahoe-lafs"));

	if ( isTahoeInstalled($TAHOE_VARS['PACKAGE_NAME']) ) {
		$page .= txt(t("tahoe-lafs_uninstall_result"));
		$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_uninstalled_failed")."</div>\n";
		$page .= txt(t("tahoe-lafs_uninstall_result"));
		$page .= $pkgUninstall;
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_retry_uninstall"),'class'=>'btn btn-warning', 'href'=>$staticFile.'/tahoe-lafs/uninstall'));

		$page .= $buttons;
		return(array('type' => 'render','page' => $page));
	}

	$page .= txt(t("tahoe-lafs_uninstall_result"));
	$page .= "<div class='alert alert-success text-center'>".t("tahoe-lafs_alert_uninstalled_successful")."</div>\n";
	$page .= txt(t("tahoe-lafs_uninstall_details"));
	$page .= $pkgUninstall;

	$page .= txt(t("tahoe-lafs_uninstall_post"));

	$postUninstall = array();
	$postUninstallAll = "";

	foreach (execute_program( 'update-rc.d tahoe-lafs remove' )['output'] as $key => $value) { $postUninstall[] = $value.'<br/>'; }
	$postUninstall[] = execute_program_shell( 'rm -fv /etc/init.d/tahoe-lafs' )['output'];
	$postUninstall[] = execute_program_shell( 'rm -fv /etc/default/tahoe-lafs' )['output'];
	foreach (execute_program( 'deluser --system --remove-home tahoe' )['output'] as $key => $value) { $postUninstall[] = $value.'<br/>'; }
	foreach (execute_program( 'delgroup --system tahoe' )['output'] as $key => $value) { $postUninstall[] = $value.'<br/>'; }
	$postUninstall[] = execute_program_shell( 'rm -rvf '.$TAHOE_VARS['DAEMON_HOMEDIR'])['output'] ;

	foreach ($postUninstall as $v) { $postUninstallAll .= $v; }
	$page .= ptxt($postUninstallAll);

	$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));

	$page .= $buttons;
	return(array('type' => 'render','page' => $page));
}

function introducer(){
	global $TAHOE_VARS;
	global $staticFile;

	$page = "";
	$buttons = "";

	$page .= hlc(t("tahoe-lafs_common_title"));
	$page .= hl(t("tahoe-lafs_introducer_subtitle"),4);

	if ( ! isTahoeInstalled($TAHOE_VARS['PACKAGE_NAME']) ) {
			$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_not_installed")."</div>\n";
 			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
 			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_install"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/install'));

			$page .= $buttons;
 			return(array('type' => 'render','page' => $page));
	}

 	if ( ! tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],'introducer')) {
		$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_introducer_not_created")."</div>\n";
		$page .= par(t("tahoe-lafs_introducer_instructions_1") . ' ' . t("tahoe-lafs_introducer_instructions_2") .' '. t("tahoe-lafs_introducer_instructions_3"));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_create_introducer"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/createIntroducer'));

		$page .= $buttons;
 		return(array('type' => 'render','page' => $page));
	}

	$page .= ajaxStr('introducerStt',t("tahoe-lafs_alert_checking_introducer"));
	$page .= "\n<script>\n";
	$page .= "loadIntroducerStatus = function() { $('#introducerStt').load('".$staticFile."/tahoe-lafs/introducerStatus') ; } ;";
	$page .= "setInterval( loadIntroducerStatus ,5000) ; loadIntroducerStatus();\n";
	$page .= "</script>\n";

	$page .= $buttons;
 	return(array('type' => 'render','page' => $page));

}

function introducerStatus(){
	global $TAHOE_VARS;

	$r = _introducerStatus($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['TAHOE_PID_FILE']);
	return (array('type'=>'ajax', 'page' => $r));
}

function _introducerStatus($homedir,$pidfile) {
	global $TAHOE_VARS;
	global $staticFile;

	$page = '';
	$buttons = '';

	$page .= txt(t("tahoe-lafs_introducer_status"));

 	if ( ! tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],'introducer')) {
		$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_introducer_not_created")."</div>\n";
		$page .= par(t("tahoe-lafs_introducer_instructions_1") .' '. t("tahoe-lafs_introducer_instructions_2") .' '. t("tahoe-lafs_introducer_instructions_3"));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_create_introducer"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/createIntroducer'));

		$page .= $buttons;
		return($page);
	}

	if ( introducerStarted($homedir, $pidfile) ) {
		$page .= "<div class='alert alert-success text-center'>".t("tahoe-lafs_alert_introducer_running")."</div>\n";

		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_stop_introducer"),'class'=>'btn btn-danger', 'href'=>$staticFile.'/tahoe-lafs/stopIntroducer'));
		if (file_exists($TAHOE_VARS['DAEMON_HOMEDIR'].'/introducer/introducer.public'))
			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_introducer_private"),'class'=>'btn btn-warning', 'href'=>$staticFile.'/tahoe-lafs/unpublishIntroducer'));
		else
			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_introducer_public"),'class'=>'btn btn-warning', 'href'=>$staticFile.'/tahoe-lafs/publishIntroducer'));
	}
	else {
		$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_introducer_stopped")."</div>\n";
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_start_introducer"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/startIntroducer'));
		if (file_exists($TAHOE_VARS['DAEMON_HOMEDIR'].'/introducer/introducer.public'))
			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_introducer_private"),'class'=>'btn btn-warning', 'href'=>$staticFile.'/tahoe-lafs/unpublishIntroducer'));
		else
			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_introducer_public"),'class'=>'btn btn-warning', 'href'=>$staticFile.'/tahoe-lafs/publishIntroducer'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_delete_introducer"),'class'=>'btn btn-danger', 'href'=>$staticFile.'/tahoe-lafs/deleteIntroducer'));
	}

	$page .= txt(t("tahoe-lafs_introducer_grid"));
	$page .= ptxt(file_get_contents($TAHOE_VARS['DAEMON_HOMEDIR'].'/introducer/grid.name'));
	$page .= txt(t("tahoe-lafs_introducer_FURL"));
	//Introducer.FURL may not be in here!!!!
	$page .= ptxt(execute_program("sed 's/,127\.0\.0\.1:.*\//\//' ".$TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['INTRODUCER_DIRNAME'].'/'.$TAHOE_VARS['INTRODUCER_FURLFILE']. " | sed 's/,192\.168\..*\..*:.*\//\//' ")['output'][0]);
	$page .= txt(t("tahoe-lafs_introducer_web"));
	$webPage = 'http://'.substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':')+1). file_get_contents($TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['INTRODUCER_DIRNAME'].'/'.$TAHOE_VARS['WEBPORT_FILENAME']); ;
	$page .= ptxt('<a href="'.$webPage.'" target=_blank>'.$webPage.'</a>');
	$page .= txt(t("tahoe-lafs_introducer_announcement"));
	if (file_exists($TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['INTRODUCER_DIRNAME'].'/'.$TAHOE_VARS['INTRODUCER_PUBLIC_FILE']))
		$page .= ptxt(t("tahoe-lafs_introducer_public"));
	else
		$page .= ptxt(t("tahoe-lafs_introducer_private"));

	$page .= $buttons;

	return($page);
}

function createIntroducer(){
	global $TAHOE_VARS;
	global $staticFile;

	$page = "";
	$buttons = "";

	$page .= hlc(t("tahoe-lafs_common_title"));
	$page .= hl(t("tahoe-lafs_createintroducer_subtitle"),4);

	if ( ! isTahoeInstalled($TAHOE_VARS['PACKAGE_NAME']) ) {
		$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_not_installed")."</div>\n";
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_install"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/install'));

		$page .= $buttons;
		return(array('type' => 'render','page' => $page));
	}

	if ( ! tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],'introducer')) {
		$page .= par(t("tahoe-lafs_createintroducer_instructions_1") .' '. t("tahoe-lafs_createintroducer_instructions_2") .' '. t("tahoe-lafs_createintroducer_instructions_3"));
		$page .= createForm(array('class'=>'form-horizontal'));
		$page .= addInput('GRID_NAME',t("tahoe-lafs_createintroducer_grid_name"),t("tahoe-lafs_createintroducer_grid_name_example").sprintf('%03d',mt_rand(0,999)),'','',t("tahoe-lafs_createintroducer_grid_name_tooltip"));
		$page .= addInput('INTRODUCER_NAME',t('tahoe-lafs_createintroducer_introducer_name'),t("tahoe-lafs_createintroducer_introducer_name_example"),'','',t("tahoe-lafs_createintroducer_introducer_name_tooltip"));
		$page .= addInput('INTRODUCER_WEBPORT',t('tahoe-lafs_createintroducer_web_port'),8228,array('type'=>'number','min'=>'1024','max'=>'65535'),'',t("tahoe-lafs_createintroducer_web_port_tooltip"));
		$page .= addInput('INTRODUCER_DIR',t('tahoe-lafs_createintroducer_folder'),$TAHOE_VARS['DAEMON_HOMEDIR'].'/introducer','','readonly',t("tahoe-lafs_createintroducer_folder_tooltip"));
		$page .= addInput('INTRODUCER_PUBLIC',t('tahoe-lafs_createintroducer_public'), true, array('type'=>'checkbox'),'checked',t("tahoe-lafs_createintroducer_public_tooltip"),'no');
 		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
 		$buttons .= addSubmit(array('label'=>t('tahoe-lafs_button_create_introducer'),'class'=>'btn btn-success'));

		$page .= $buttons;
 		return(array('type' => 'render','page' => $page));
	}

	$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_introducer_configured")."</div>\n";
	$page .= txt(t("tahoe-lafs_introducer_status"));

	if ( introducerStarted($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['TAHOE_PID_FILE']))
		$page .= "<div class='alert alert-success text-center'>".t("tahoe-lafs_alert_introducer_running")."</div>\n";
	else
		$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_introducer_stopped")."</div>\n";

		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_manage_introducer"),'class'=>'btn btn-primary', 'href'=>$staticFile.'/tahoe-lafs/introducer'));

		$page .= $buttons;
 		return(array('type' => 'render','page' => $page));
}

function createIntroducer_post(){
	global $TAHOE_VARS;
	global $staticFile;

	$page = "";
	$buttons = "";

	$page .= hlc(t("tahoe-lafs_common_title"));
	$page .= hl(t("tahoe-lafs_subtitle_introducer_creation"),4);

	if ( ! isTahoeInstalled($TAHOE_VARS['PACKAGE_NAME']) ) {
		$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_not_installed")."</div>\n";
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_install"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/install'));

		$page .= $buttons;
		return(array('type' => 'render','page' => $page));
	}

 	if ( tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],'introducer')) {
		$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_introducer_configured")."</div>\n";
		$page .= txt(t("tahoe-lafs_introducer_status"));

		if ( introducerStarted($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['TAHOE_PID_FILE']))
			$page .= "<div class='alert alert-success text-center'>".t("tahoe-lafs_alert_introducer_running")."</div>\n";
		else
			$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_introducer_stopped")."</div>\n";

		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_manage_introducer"),'class'=>'btn btn-primary', 'href'=>$staticFile.'/tahoe-lafs/introducer'));

		$page .= $buttons;
 		return(array('type' => 'render','page' => $page));
 	}

	if (empty($_POST)) {
			$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_introducer_failed")."</div>\n";
			$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_request_incorrect")."</div>\n";
			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_introducer_retry"),'class'=>'btn btn-warning', 'href'=>$staticFile.'/tahoe-lafs/createIntroducer'));

			$page .= $buttons;
 			return(array('type' => 'render','page' => $page));
 	}

	$validPost = true;

	if ( empty($_POST['GRID_NAME']) || preg_match("/[^-\w]/i", $_POST['GRID_NAME']) || strlen($_POST['GRID_NAME'] > 80) ) {
		$validPost = false;
		$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_introducer_invalid_grid").': ' . htmlspecialchars(substr($_POST['GRID_NAME'],0,70)) . "</div>\n";
	}
	if ( empty($_POST['INTRODUCER_NAME']) || preg_match("/[^-\w]/i", $_POST['INTRODUCER_NAME']) || strlen($_POST['INTRODUCER_NAME'] > 80) ) {
		$validPost = false;
		$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_introducer_invalid_name").': ' . htmlspecialchars(substr($_POST['INTRODUCER_NAME'],0,70)) . "</div>\n";
	}
	if ( empty($_POST['INTRODUCER_WEBPORT']) || ( $_POST['INTRODUCER_WEBPORT'] > 65535 || $_POST['INTRODUCER_WEBPORT'] < 1024 ) )  {
		$validPost = false;
		$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_introducer_invalid_port").': ' . htmlspecialchars(substr($_POST['INTRODUCER_WEBPORT'],0,10)) . "</div>\n";
	}
	if(!$validPost) {
			$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_introducer_maximum")."</div>\n";
			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_introducer_retry"),'class'=>'btn btn-warning', 'href'=>$staticFile.'/tahoe-lafs/createIntroducer'));
			$page .= $buttons;
 			return(array('type' => 'render','page' => $page));
	}

	$postCreate = array();
	foreach (execute_program( $TAHOE_VARS['TAHOE_COMMAND'].' create-introducer '.$TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['INTRODUCER_DIRNAME'])['output'] as $k => $v) { $postCreate[] = $v; }
	execute_program_shell( 'sed -i "s/^nickname.*$/nickname = '.$_POST['INTRODUCER_NAME'].'/" '.$TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['INTRODUCER_DIRNAME'].'/'.$TAHOE_VARS['TAHOE_CONFIG_FILE'] );
	execute_program_shell( 'echo '.$_POST['INTRODUCER_WEBPORT'].' >> '.$TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['INTRODUCER_DIRNAME'].'/'.$TAHOE_VARS['WEBPORT_FILENAME']);
	if ($_POST['INTRODUCER_PUBLIC']){
		execute_program_shell( 'touch '.$TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['INTRODUCER_DIRNAME'].'/'.$TAHOE_VARS['INTRODUCER_PUBLIC_FILE']);
		execute_program_shell( 'sed -i "s/^web\.port.*$/web\.port = tcp:'.intval($_POST['INTRODUCER_WEBPORT']).':interface=0.0.0.0/" '.$TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['INTRODUCER_DIRNAME'].'/'.$TAHOE_VARS['TAHOE_CONFIG_FILE']);
	}
	else
		execute_program_shell( 'sed -i "s/^web\.port.*$/web\.port = tcp:'.intval($_POST['INTRODUCER_WEBPORT']).':interface=127.0.0.1/" '.$TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['INTRODUCER_DIRNAME'].'/'.$TAHOE_VARS['TAHOE_CONFIG_FILE']);
	execute_program_shell( 'echo '.$_POST['GRID_NAME'].' >> '.$TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['INTRODUCER_DIRNAME'].'/'.$TAHOE_VARS['INTRODUCER_GRIDNAME_FILE']);

	if( execute_shell( "grep -q '^AUTOSTART' ".$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE'] )['return'] == 0 ) {
		execute_program_shell( 'sed -i "s/\" /\"/" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE'] );
		execute_program_shell( 'sed -i "s/\" /\"/" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE'] );
		execute_program_shell( 'sed -i "s/^AUTOSTART=\"[^\"]*/& introducer /" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE'] );
		execute_program_shell( 'sed -i "s/ \"/\"/" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE'] );
		execute_program_shell( 'sed -i "s/ \"/\"/" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE'] );
	}
	else
		execute_program_shell( 'echo "AUTOSTART=\"introducer\"" >> '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE'] );

	foreach (execute_program( 'chown -vR tahoe:tahoe '.$TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['INTRODUCER_DIRNAME'] )['output'] as $key => $value) { $postCreate[] = $value; }

	$postCreateAll = "";
		foreach ($postCreate as $k => $v) { $postCreateAll .= $v.'<br/>'; }

	$page .= txt(t("tahoe-lafs_createintroducer_result"));
	if (tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['INTRODUCER_DIRNAME']))
		$page .= "<div class='alert alert-success text-center'>".t("tahoe-lafs_alert_introducer_successful")."</div>\n";
	else
		$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_introducer_failed")."</div>\n";

	$page .= ptxt($postCreateAll);

	$postStart = array();
	foreach (execute_program_shell( $TAHOE_VARS['TAHOE_ETC_INITD_FILE'].' start '.$TAHOE_VARS['INTRODUCER_DIRNAME'])['output'] as $k => $v) { $postStart[] = $v; }

	if(!is_file($TAHOE_VARS['DAEMON_HOMEDIR']."/".$TAHOE_VARS['INTRODUCER_DIRNAME']."/".$TAHOE_VARS['INTRODUCER_FURLFILE']))
		execute_program_shell( 'echo "pb://$(cat '.$TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['INTRODUCER_DIRNAME'].'/my_nodeid)@'.substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':')+1).intval($_POST['INTRODUCER_WEBPORT']).'/introducer" > '.$TAHOE_VARS['DAEMON_HOMEDIR']."/".$TAHOE_VARS['INTRODUCER_DIRNAME']."/".$TAHOE_VARS['INTRODUCER_FURLFILE'] );

	//This pause is needed in order to let the server start before showing the success/error text
	sleep(2);
	//We may have to create manually the introducer.furl file!!!
	foreach (execute_program_shell( 'ps aux | grep tahoe | grep introducer | grep python | grep -v grep') as $k => $v) { $postStart[] = $v; }

	execute_program_shell($TAHOE_VARS['AVAHI_SERVICE_COMMAND'].' enable '.$TAHOE_VARS['AVAHI_SERVICE_TAHOE'].' >/dev/null 2>&1');
	execute_program_shell($TAHOE_VARS['AVAHI_SERVICE_COMMAND'].' start '.$TAHOE_VARS['AVAHI_SERVICE_TAHOE'].' >/dev/null 2>&1');

	$postStartAll = "";
		foreach ($postStart as $k => $v) { $postStartAll .= $v.'<br/>'; }


	$page .= txt(t("tahoe-lafs_createintroducer_starting"));
	if ( introducerStarted($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['TAHOE_PID_FILE']) )
		$page .= "<div class='alert alert-success text-center'>".t("tahoe-lafs_alert_introducer_started")."</div>\n";
	else {
		$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_introducer_start_fail")."</div>\n";
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_start_introducer"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/startIntroducer'));
		}
	$page .= ptxt($postStartAll);

	$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
	$buttons .= addButton(array('label'=>t("tahoe-lafs_button_manage_introducer"),'class'=>'btn btn-primary', 'href'=>$staticFile.'/tahoe-lafs/introducer','divOptions'=>array('class'=>'btn-group')));

	$page .= $buttons;
 	return(array('type' => 'render','page' => $page));
}

function node(){

	global $TAHOE_VARS;
	global $staticFile;

	$page = "";
	$buttons = "";

	$page .= hlc(t("tahoe-lafs_common_title"));
	$page .= hl(t("tahoe-lafs_node_subtitle"),4);

	if ( ! isTahoeInstalled($TAHOE_VARS['PACKAGE_NAME']) ) {
			$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_not_installed")."</div>\n";
 			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
 			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_install"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/install'));

			$page .= $buttons;
 			return(array('type' => 'render','page' => $page));
	}

 	if ( !tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['NODE_DIRNAME'])) {
		$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_storage_not_created")."</div>\n";
		$page .= par(t("tahoe-lafs_node_instructions_1") .' '. t("tahoe-lafs_node_instructions_2"));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_create_storage"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/createNode'));

		$page .= $buttons;
 		return(array('type' => 'render','page' => $page));
	}

	$page .= ajaxStr('nodeStt',t("tahoe-lafs_alert_checking_storage"));
	$page .= "\n<script>\n";
	$page .= "loadNodeStatus = function() { $('#nodeStt').load('".$staticFile."/tahoe-lafs/nodeStatus') ; } ;";
	$page .= "setInterval( loadNodeStatus ,5000) ; loadNodeStatus();\n";
	$page .= "</script>\n";

	$page .= $buttons;
 	return(array('type' => 'render','page' => $page));

}

function nodeStatus(){
	global $TAHOE_VARS;

	$r = _nodeStatus($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['TAHOE_PID_FILE']);
	return (array('type'=>'ajax', 'page' => $r));
}

function _nodeStatus($homedir,$pidfile) {
	global $TAHOE_VARS;
	global $staticFile;

	$page = '';
	$buttons = '';

	$page .= txt(t("tahoe-lafs_node_status"));

 	if ( ! tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['NODE_DIRNAME'])) {
		$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_storage_not_created")."</div>\n";
		$page .= par(t("tahoe-lafs_node_instructions_1") .' '. t("tahoe-lafs_node_instructions_2"));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_create_storage"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/createNode'));

		$page .= $buttons;
		return($page);
	}

	if ( nodeStarted($homedir, $pidfile) ) {
		$page .= "<div class='alert alert-success text-center'>".t("tahoe-lafs_alert_storage_running")."</div>\n";
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_stop_storage"),'class'=>'btn btn-danger', 'href'=>$staticFile.'/tahoe-lafs/stopNode'));
	}
	else {
		$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_storage_stopped")."</div>\n";
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_start_storage"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/startNode'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_delete_storage"),'class'=>'btn btn-danger', 'href'=>$staticFile.'/tahoe-lafs/deleteNode'));
	}

	$page .= txt(t("tahoe-lafs_node_FURL"));
	$page .= ptxt(execute_program_shell('grep "^introducer\.furl" '.$TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['NODE_DIRNAME'].'/'.$TAHOE_VARS['TAHOE_CONFIG_FILE']." | sed 's/introducer\.furl = //'")['output']);
	$page .= txt(t("tahoe-lafs_node_web"));
	$webPage = 'http://'.substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':')+1).'3456';
	$page .= ptxt('<a href="'.$webPage.'" >'.$webPage.'</a>');

	$page .= $buttons;

	return($page);
}

function createNode_get(){
	global $TAHOE_VARS;
	global $staticFile;

	$page = "";
	$buttons = "";

	$page .= hlc(t("tahoe-lafs_common_title"));
	$page .= hl(t("tahoe-lafs_createnode_subtitle"),4);

	if ( ! isTahoeInstalled($TAHOE_VARS['PACKAGE_NAME']) ) {
			$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_not_installed")."</div>\n";
 			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
 			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_install"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/install'));

			$page .= $buttons;
 			return(array('type' => 'render','page' => $page));
	}

	if ( !tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['NODE_DIRNAME'])) {
		$page .= par(t("tahoe-lafs_createnode_instructions_1") .' '. t("tahoe-lafs_createnode_instructions_2"));
		$page .= createForm(array('class'=>'form-horizontal'));
		$page .= addInput('NODE_NICKNAME',t("tahoe-lafs_createnode_name"),t("tahoe-lafs_createnode_name_example"),'','',t("tahoe-lafs_createnode_name_tooltip"));
		if(isset($_GET['furl']) && (! is_null($_GET['furl'])))
			$page .= addInput('NODE_INTRODUCER_FURL',t('tahoe-lafs_createnode_FURL'),$_GET['furl'],array('class'=>'input-xxlarge'),'readonly',t("tahoe-lafs_createnode_FURL_tooltip_1")." ".t("tahoe-lafs_createnode_FURL_tooltip_2")."<br/>".t("tahoe-lafs_createnode_FURL_tooltip_3"));
		else
			if (tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['INTRODUCER_DIRNAME']))
				$page .= addInput('NODE_INTRODUCER_FURL',t("tahoe-lafs_createnode_FURL"),file_get_contents($TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['INTRODUCER_DIRNAME'].'/'.$TAHOE_VARS['INTRODUCER_FURLFILE']),array('class'=>'input-xxlarge'),'',t("tahoe-lafs_createnode_FURL_tooltip_1")." ".t("tahoe-lafs_createnode_FURL_tooltip_4")."<br/>".t("tahoe-lafs_createnode_FURL_tooltip_5"));
			else
				$page .= addInput('NODE_INTRODUCER_FURL',t("tahoe-lafs_createnode_FURL"),'',array('class'=>'input-xxlarge'),'',t("tahoe-lafs_createnode_FURL_tooltip_1"));

		$page .= addInput('NODE_DIR',t('tahoe-lafs_createnode_folder'),$TAHOE_VARS['DAEMON_HOMEDIR'].'/node','','readonly',t("tahoe-lafs_createnode_folder_tooltip"));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		$buttons .= addSubmit(array('label'=>t('tahoe-lafs_button_create_storage'),'class'=>'btn btn-success'));

		$page .= $buttons;
 		return(array('type' => 'render','page' => $page));
	}

	if ( nodeStarted($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['TAHOE_PID_FILE']) ) {
		$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_storage_configured")."</div>\n";
		$page .= "<div class='alert alert-success text-center'>".t("tahoe-lafs_alert_storage_running")."</div>\n";
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_stop_storage"),'class'=>'btn btn-danger', 'href'=>$staticFile.'/tahoe-lafs/stopNode'));
	}
	else {
		$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_storage_configured")."</div>\n";
		$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_storage_stopped")."</div>\n";
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_start_storage"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/startNode'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_delete_storage"),'class'=>'btn btn-danger', 'href'=>$staticFile.'/tahoe-lafs/deleteNode'));
	}

	$page .= $buttons;
 	return(array('type' => 'render','page' => $page));
}

function createNode_post(){
	global $TAHOE_VARS;
	global $staticFile,$avahi_extra;

	$page = "";
	$buttons = "";

	$page .= hlc(t("tahoe-lafs_common_title"));
	$page .= hl(t("tahoe-lafs_createnode_subtitle"),4);

	if ( ! isTahoeInstalled($TAHOE_VARS['PACKAGE_NAME']) ) {
			$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_not_installed")."</div>\n";
 			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
 			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_install"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/install'));

			$page .= $buttons;
 			return(array('type' => 'render','page' => $page));
	}

 	if (tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],'node')) {
			$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_storage_configured")."</div>\n";
 			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_manage_storage"),'class'=>'btn btn-primary', 'href'=>$staticFile.'/tahoe-lafs/node'));

			$page .= $buttons;
 			return(array('type' => 'render','page' => $page));
 	}

 	$validPost = true;

	if ( empty($_POST['NODE_NICKNAME']) ) {
		$validPost = false;
		$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_storage_invalid_name").': (' . t("tahoe-lafs_common_empty") . ")</div>\n";
	}
	elseif ( preg_match('/[^a-z_\-0-9]/i', $_POST['NODE_NICKNAME'] )) {
		$validPost = false;
		$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_storage_invalid_name").': ' . htmlspecialchars(substr($_POST['NODE_NICKNAME'],0,90)) . "</div>\n";
		$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_storage_maximum")."</div>\n";
	}
	elseif( strlen($_POST['NODE_NICKNAME']) > 80)  {
		$validPost = false;
		$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_storage_invalid_name").': ' . htmlspecialchars(substr($_POST['NODE_NICKNAME'],0,90)) . "</div>\n";
		$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_storage_maximum")."</div>\n";
	}

	if ( empty($_POST['NODE_INTRODUCER_FURL']) ) {
		$validPost = false;
		$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_storage_invalid_FURL").': (' . t("tahoe-lafs_common_empty") . ")</div>\n";
	}
	elseif( !preg_match('/'.'^((pb:\/\/)([a-zA-Z0-9]{16,256})(@)([a-zA-Z0-9@:%_\+,.~#\?&\/=]{5,256})(:)([0-9]+)(\/)([a-zA-Z0-9\._-]{1,81}))'.'/', $_POST['NODE_INTRODUCER_FURL']) ) {
		$validPost = false;
		$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_storage_invalid_FURL").': ' . htmlspecialchars(substr($_POST['NODE_INTRODUCER_FURL'],0,100)) . "</div>\n";
	}


	if(!$validPost) {

			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
			$buttons .= addButton(array('label'=>t("tahoe-lafs_button_storage_retry"),'class'=>'btn btn-warning', 'href'=>$staticFile.'/tahoe-lafs/createNode'));
			$page .= $buttons;
 			return(array('type' => 'render','page' => $page));
	}

	$postCreate = array();
	foreach (execute_program( $TAHOE_VARS['TAHOE_COMMAND'].' create-node '.$TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['NODE_DIRNAME'])['output'] as $k => $v) { $postCreate[] = $v; }
	execute_program_shell( 'sed -i "s/^nickname.*$/nickname = '.$_POST['NODE_NICKNAME'].'/ "'.$TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['NODE_DIRNAME'].'/'.$TAHOE_VARS['TAHOE_CONFIG_FILE'] );
	execute_program_shell( 'sed -i "s%^introducer\.furl.*$%introducer\.furl = '.$_POST['NODE_INTRODUCER_FURL'].'%" '.$TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['NODE_DIRNAME'].'/'.$TAHOE_VARS['TAHOE_CONFIG_FILE'] );

	if( execute_shell( "grep -q '^AUTOSTART' ".$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE'] )['return'] == 0 ) {
		execute_program_shell( 'sed -i "s/\" /\"/" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE'] );
		execute_program_shell( 'sed -i "s/\" /\"/" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE'] );
		execute_program_shell( 'sed -i "s/^AUTOSTART=\"[^\"]*/& node /" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE'] );
		execute_program_shell( 'sed -i "s/ \"/\"/" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE'] );
		execute_program_shell( 'sed -i "s/ \"/\"/" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE'] );
	}
	else
		execute_program_shell( 'echo "AUTOSTART=\"node\"" >> '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE'] );

	foreach (execute_program( 'chown -vR tahoe:tahoe '.$TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['NODE_DIRNAME'])['output'] as $key => $value) { $postCreate[] = $value; }
	$postCreateAll = "";
		foreach ($postCreate as $k => $v) { $postCreateAll .= $v.'<br/>'; }

	$page .= txt(t("tahoe-lafs_createnode_result"));
	if ( tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['NODE_DIRNAME']))
		$page .= "<div class='alert alert-success text-center'>".t("tahoe-lafs_alert_storage_successful")."</div>\n";
	else
		$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_storage_failed")."</div>\n";

	$page .= ptxt($postCreateAll);

	$postStart = array();
	foreach (execute_program( '/etc/init.d/tahoe-lafs start node')['output'] as $k => $v) { $postStart[] = $v; }
	//This pause is needed in order to let the server start before showing the success/error text
	sleep(2);

	//Maybe we need to create introducer.furl here!
	foreach (execute_program( 'ps aux | grep tahoe | grep node | grep -v grep')['output'] as $k => $v) { $postStart[] = $v; }
	
	if(isset($avahi_extra) && $avahi_extra)
	execute_program_shell('/bin/sh /usr/share/avahi-service/files/tahoe-lafs.service nodeStart  >/dev/null 2>&1');

	$postStartAll = "";
		foreach ($postStart as $k => $v) { $postStartAll .= $v.'<br/>'; }

	$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
	$buttons .= addButton(array('label'=>t("tahoe-lafs_button_manage_storage"),'class'=>'btn btn-primary', 'href'=>$staticFile.'/tahoe-lafs/node'));

	$page .= txt(t("tahoe-lafs_createintroducer_starting"));
	if ( nodeStarted($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['TAHOE_PID_FILE']) )
		$page .= "<div class='alert alert-success text-center'>".t("tahoe-lafs_alert_storage_started")."</div>\n";
	else {
		$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_storage_start_fail")."</div>\n";
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_start_storage"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/startNode'));
		}
	$page .= ptxt($postStartAll);

	$page .= $buttons;

 	return(array('type' => 'render','page' => $page));
}


function deleteIntroducer(){
	global $TAHOE_VARS;
	global $staticFile;

	$page = "";
	$buttons = "";

	$page .= hlc(t("tahoe-lafs_common_title"));
	$page .= hl(t("tahoe-lafs_deleteintroducer_subtitle"),4);

	if ( ! isTahoeInstalled($TAHOE_VARS['PACKAGE_NAME']) ) {
		$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_not_installed")."</div>\n";
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_install"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/install'));

		$page .= $buttons;
		return(array('type' => 'render','page' => $page));
	}

 	if ( ! tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['INTRODUCER_DIRNAME'])) {
 		$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_introducer_not_created")."</div>\n";
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));

		$page .= $buttons;
		return(array('type' => 'render','page' => $page));
 	}

	if ( introducerStarted($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['TAHOE_PID_FILE']) ) {
		$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_introducer_running")."</div>\n";
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_manage_introducer"),'class'=>'btn btn-primary', 'href'=>$staticFile.'/tahoe-lafs/introducer',));

		$page .= $buttons;
		return(array('type' => 'render','page' => $page));
 	}

	$postDelete = array();
	$postDeleteAll = "";

	execute_program_shell($TAHOE_VARS['AVAHI_SERVICE_COMMAND'].' stop '.$TAHOE_VARS['AVAHI_SERVICE_TAHOE'].' >/dev/null 2>&1');
	execute_program_shell($TAHOE_VARS['AVAHI_SERVICE_COMMAND'].' disable '.$TAHOE_VARS['AVAHI_SERVICE_TAHOE'].' >/dev/null 2>&1');

	foreach (execute_program( 'rm -vrf '.$TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['INTRODUCER_DIRNAME'].'/*')['output'] as $k => $v) { $postDelete[] = $v; }

	if( execute_shell( "grep -q '^AUTOSTART' /etc/default/tahoe-lafs" )['return'] == 0 ) {
		execute_program_shell( 'sed -i "s/\" /\"/" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE']);
		execute_program_shell( 'sed -i "s/ \"/\"/" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE']);
		execute_program_shell( 'sed -i "s/introducer/ /" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE']);
		execute_program_shell( 'sed -i "s/\" /\"/" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE']);
		execute_program_shell( 'sed -i "s/ \"/\"/" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE']);
		execute_program_shell( 'sed -i "s/\" /\"/" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE']);
		execute_program_shell( 'sed -i "s/ \"/\"/" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE']);
	}

	foreach ($postDelete as $k => $v) { $postDeleteAll .= $v.'<br/>'; }
	rmdir($TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['INTRODUCER_DIRNAME']);

	$page .= txt(t("tahoe-lafs_deleteintroducer_result"));

	if (tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['INTRODUCER_DIRNAME']))
		$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_introducer_deletion_failed")."</div>\n";
	else
		$page .= "<div class='alert alert-success text-center'>".t("tahoe-lafs_alert_introducer_deletion_successful")."</div>\n";
	$page .= ptxt($postDeleteAll);

	$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));

	$page .= $buttons;
	return(array('type' => 'render','page' => $page));
}

function deleteNode(){
	global $TAHOE_VARS;
	global $staticFile;

	$page = "";
	$buttons = "";

	$page .= hlc(t("tahoe-lafs_common_title"));
	$page .= hl(t("tahoe-lafs_deletenode_subtitle"),4);

	if ( ! isTahoeInstalled($TAHOE_VARS['PACKAGE_NAME']) ) {
		$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_not_installed")."</div>\n";
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_install"),'class'=>'btn btn-success', 'href'=>$staticFile.'/tahoe-lafs/install'));

		$page .= $buttons;
		return(array('type' => 'render','page' => $page));
	}

 	if ( ! tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['NODE_DIRNAME'])) {
 		$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_storage_not_created")."</div>\n";
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));

		$page .= $buttons;
		return(array('type' => 'render','page' => $page));
 	}

	if ( nodeStarted($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['TAHOE_PID_FILE']) ) {
		$page .= "<div class='alert alert-warning text-center'>".t("tahoe-lafs_alert_storage_running")."</div>\n";
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));
		$buttons .= addButton(array('label'=>t("tahoe-lafs_button_manage_storage"),'class'=>'btn btn-primary', 'href'=>$staticFile.'/tahoe-lafs/node'));

		$page .= $buttons;
		return(array('type' => 'render','page' => $page));
 	}

	$postDelete = array();
	$postDeleteAll = "";
	foreach (execute_program( 'rm -vrf '.$TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['NODE_DIRNAME'].'/*')['output'] as $k => $v) { $postDelete[] = $v; }

	if( execute_shell( "grep -q '^AUTOSTART' /etc/default/tahoe-lafs" )['return'] == 0 ) {
		execute_program_shell( 'sed -i "s/\" /\"/" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE']);
		execute_program_shell( 'sed -i "s/ \"/\"/" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE']);
		execute_program_shell( 'sed -i "s/node/ /" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE']);
		execute_program_shell( 'sed -i "s/\" /\"/" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE']);
		execute_program_shell( 'sed -i "s/ \"/\"/" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE']);
		execute_program_shell( 'sed -i "s/\" /\"/" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE']);
		execute_program_shell( 'sed -i "s/ \"/\"/" '.$TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE']);
	}

	foreach ($postDelete as $k => $v) { $postDeleteAll .= $v.'<br/>'; }
	rmdir($TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['NODE_DIRNAME']);
	$page .= txt(t("tahoe-lafs_deletenode_result"));

	if (tahoeCreated($TAHOE_VARS['DAEMON_HOMEDIR'],$TAHOE_VARS['NODE_DIRNAME']))
		$page .= "<div class='alert alert-error text-center'>".t("tahoe-lafs_alert_storage_deletion_failed")."</div>\n";
	else
		$page .= "<div class='alert alert-success text-center'>".t("tahoe-lafs_alert_storage_deletion_successful")."</div>\n";
	$page .= ptxt($postDeleteAll);

	$buttons .= addButton(array('label'=>t("tahoe-lafs_button_back"),'class'=>'btn btn-default', 'href'=>$staticFile.'/tahoe-lafs'));

	$page .= $buttons;
	return(array('type' => 'render','page' => $page));
}

function detached_exec($cmd) {

	$pid = pcntl_fork();

   switch($pid) {
		case -1 :
			return false;
		case 0 :
          posix_setsid();
          exec($cmd);
          break;
      default:
          return $pid;
    }
}

function startIntroducer(){
	global $staticFile;
	global $TAHOE_VARS;

   $pid = detached_exec($TAHOE_VARS['TAHOE_ETC_INITD_FILE'].' start introducer >/dev/null 2>&1');

	setFlash(t('tahoe-lafs_flash_starting_introducer'));
	return(array('type'=> 'redirect', 'url' => $staticFile.'/'.'tahoe-lafs/introducer'));
}

function stopIntroducer(){
	global $staticFile;
	global $TAHOE_VARS;

   $pid = detached_exec($TAHOE_VARS['TAHOE_ETC_INITD_FILE'].' stop introducer >/dev/null 2>&1');
   execute_program_shell($TAHOE_VARS['AVAHI_SERVICE_COMMAND'].' stop '.$TAHOE_VARS['AVAHI_SERVICE_TAHOE'].' >/dev/null 2>&1');
	setFlash(t('tahoe-lafs_flash_stopping_introducer'));
	return(array('type'=> 'redirect', 'url' => $staticFile.'/'.'tahoe-lafs/introducer'));
}

function restartIntroducer(){
	global $staticFile;
	global $TAHOE_VARS;

   $pid = detached_exec($TAHOE_VARS['TAHOE_ETC_INITD_FILE'].' restart introducer >/dev/null 2>&1');

	setFlash(t('tahoe-lafs_flash_restarting_introducer'));
	return(array('type'=> 'redirect', 'url' => $staticFile.'/'.'tahoe-lafs/introducer'));
}

function restartAndPublishIntroducer(){
	global $staticFile;
	global $TAHOE_VARS;

   $pid = detached_exec($TAHOE_VARS['TAHOE_ETC_INITD_FILE'].' restart introducer >/dev/null 2>&1');
	sleep (1);

	setFlash(t('tahoe-lafs_flash_restarting_introducer'));
	return(array('type'=> 'redirect', 'url' => $staticFile.'/'.'tahoe-lafs/startAvahi'));
}

function startNode(){
	global $staticFile;
	global $TAHOE_VARS, $avahi_extra;

   $pid = detached_exec($TAHOE_VARS['TAHOE_ETC_INITD_FILE'].' start node >/dev/null 2>&1');
  if(isset($avahi_extra) && $avahi_extra)
   execute_program_shell('/bin/sh /usr/share/avahi-service/files/tahoe-lafs.service nodeStart  >/dev/null 2>&1 ');

	setFlash(t('tahoe-lafs_flash_starting_storage'));
	return(array('type'=> 'redirect', 'url' => $staticFile.'/'.'tahoe-lafs/node'));
}

function stopNode(){
	global $staticFile;
	global $TAHOE_VARS, $avahi_extra;

   $pid = detached_exec($TAHOE_VARS['TAHOE_ETC_INITD_FILE'].' stop node >/dev/null 2>&1');
  if(isset($avahi_extra) && $avahi_extra)
   execute_program_shell('/bin/sh /usr/share/avahi-service/files/tahoe-lafs.service nodeStop  >/dev/null 2>&1');

	setFlash(t('tahoe-lafs_flash_stopping_storage'));
	return(array('type'=> 'redirect', 'url' => $staticFile.'/'.'tahoe-lafs/node'));
}

function startAvahi(){
	global $staticFile;
	global $TAHOE_VARS;

	sleep (1);
   $pid = detached_exec($TAHOE_VARS['AVAHI_SERVICE_COMMAND'].' start '.$TAHOE_VARS['AVAHI_SERVICE_TAHOE'].' >/dev/null 2>&1');
   $pid = detached_exec($TAHOE_VARS['AVAHI_SERVICE_COMMAND'].' start '.$TAHOE_VARS['AVAHI_SERVICE_TAHOE'].' >/dev/null 2>&1');

	setFlash(t('tahoe-lafs_flash_publishing_introducer'));
	return(array('type'=> 'redirect', 'url' => $staticFile.'/'.'tahoe-lafs/introducer'));
}

function stopAvahi(){
	global $staticFile;
	global $TAHOE_VARS;

   $pid = detached_exec($TAHOE_VARS['AVAHI_SERVICE_COMMAND'].' stop '.$TAHOE_VARS['AVAHI_SERVICE_TAHOE'].' >/dev/null 2>&1');

	setFlash(t('tahoe-lafs_flash_unpublishing_introducer'));
	return(array('type'=> 'redirect', 'url' => $staticFile.'/'.'tahoe-lafs/restartIntroducer'));
}

function publishIntroducer(){
	global $staticFile;
	global $TAHOE_VARS;

	$page = "";
	$buttons = "";

	execute_program_shell( 'touch '.$TAHOE_VARS['DAEMON_HOMEDIR'].'/introducer/introducer.public');
	execute_program_shell( 'chown '.$TAHOE_VARS['DAEMON_USERNAME'].':'.$TAHOE_VARS['DAEMON_GROUP'].' '.$TAHOE_VARS['DAEMON_HOMEDIR'].'/introducer/introducer.public');
	execute_program_shell( "sed -i 's/^web\.port.*/web\.port = tcp:".intval(file_get_contents($TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['INTRODUCER_DIRNAME'].'/'.$TAHOE_VARS['WEBPORT_FILENAME'])).":interface=0\.0\.0\.0/' ".$TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['INTRODUCER_DIRNAME'].'/'.$TAHOE_VARS['TAHOE_CONFIG_FILE'] );

	setFlash(t('tahoe-lafs_flash_publishing_introducer'));
	return(array('type'=> 'redirect', 'url' => $staticFile.'/'.'tahoe-lafs/restartAndPublishIntroducer'));
}

function unpublishIntroducer(){
	global $staticFile;
	global $TAHOE_VARS;

	execute_program_shell( 'rm -vf '.$TAHOE_VARS['DAEMON_HOMEDIR'].'/introducer/introducer.public');
	execute_program_shell( "sed -i 's/^web\.port.*/web\.port = tcp:".intval(file_get_contents($TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['INTRODUCER_DIRNAME'].'/'.$TAHOE_VARS['WEBPORT_FILENAME'])).":interface=127\.0\.0\.1/' ".$TAHOE_VARS['DAEMON_HOMEDIR'].'/'.$TAHOE_VARS['INTRODUCER_DIRNAME'].'/'.$TAHOE_VARS['TAHOE_CONFIG_FILE'] );

	setFlash(t('tahoe-lafs_flash_unpublishing_introducer'));
	return(array('type'=> 'redirect', 'url' => $staticFile.'/'.'tahoe-lafs/stopAvahi'));
}

function tahoeCreated($dir,$name) {
	return (is_dir($dir.'/'.$name));
}

function introducerStarted($dir,$pidfile){
	if (is_file("$dir/introducer/$pidfile"))
		return 1;
	else
		return 0;
}

function nodeStarted($dir,$pidfile){
	if (is_file("$dir/node/$pidfile"))
		return 1;
	else
		return 0;
}

function isTahoeInstalled($pkg){
	global $TAHOE_VARS;

	if ( isPackageInstall($pkg) && is_dir($TAHOE_VARS['DAEMON_HOMEDIR']) && is_file($TAHOE_VARS['TAHOE_ETC_INITD_FILE']) && is_file($TAHOE_VARS['TAHOE_ETC_DEFAULT_FILE']))
		return 1;
	else
		return 0;
}


?>
