<?php

require realpath(__DIR__ . "/../resources/syncthing/common.php");

$urlpath="$staticFile/syncthing";

function index() {
	global $title, $urlpath, $sc_webui_user, $sc_webui_pass, $sc_webui_port;

	$page = hlc(t("syncthing_title"));
	$page .= hl(t("syncthing_desc"), 4);

	if (!isInstalled()) {
		$page .= "<div class='alert alert-error text-center'>".t("syncthing_not_installed")."</div>\n";
		$page .= addButton(array('label'=>t("syncthing_install"),'class'=>'btn btn-success', 'href'=>"$urlpath/download"));
		return array('type'=>'render','page'=>$page);
	} elseif (!isConfigured()) {
		$page .= "<div class='alert alert-error text-center'>".t("syncthing_not_configured")."</div>\n";
		$page .= addButton(array('label'=>t("syncthing_configure"),'class'=>'btn btn-success', 'href'=>"$urlpath/configure"));
		$page .= addButton(array('label'=>t("syncthing_remove"),'class'=>'btn btn-danger', 'href'=>"$urlpath/remove"));
		return array('type'=>'render','page'=>$page);
	} elseif (!isRunning()) {
		$page .= "<div class='alert alert-error text-center'>".t("syncthing_not_running")."</div>\n";
		$page .= addButton(array('label'=>t("syncthing_start"),'class'=>'btn btn-success', 'href'=>"$urlpath/start"));
		$page .= addButton(array('label'=>t("syncthing_remove"),'class'=>'btn btn-danger', 'href'=>"$urlpath/remove"));
		return array('type'=>'render','page'=>$page);
	} else {
		$config = readConfig();
		$page .= "<div class='alert alert-success text-center'>".t("syncthing_running")."</div>\n";
		if (!passwordChanged($config)) {
			$page .= "<div class='alert alert-error text-center'>"
				.t("syncthing_pass_unchanged")
				."<br/>\n"
				.t("syncthing_def_user").": $sc_webui_user"
				."<br/>\n"
				.t("syncthing_def_pass").": $sc_webui_pass"
				."</div>\n";
		}
		$host = explode(':', $_SERVER['HTTP_HOST'])[0];
		$page .= par(t("syncthing_repos_web"));

		$page .= addButton(array('label'=>t('syncthing_web_interface'),'href'=>"https://$host:$sc_webui_port",'target'=>'_blank'));
		$page .= addButton(array('label'=>t("syncthing_stop"),'class'=>'btn btn-danger', 'href'=>"$urlpath/stop"));

		return array('type' => 'render','page' => $page);
	}
}

function connect() {
	global $urlpath;

	$ip = $_GET['ip'];
	$port = $_GET['port'];
	$host = $_GET['host'];
	$node_id = $_GET['node_id'];

	stopprogram(); // Make sure the config file is ours
	$config = readConfig();
	connectTo($config, $ip, $port, $host, $node_id);
	writeConfig($config);
	startprogram(); // Make it load the new config
	setFlash(t("syncthing_connected_node"));

	return array('type'=>'redirect','url'=>"$urlpath");
}

function disconnect() {
	global $urlpath;

	$ip = $_GET['ip'];
	$port = $_GET['port'];
	$host = $_GET['host'];
	$node_id = $_GET['node_id'];

	stopprogram(); // Make sure the config file is ours
	$config = readConfig();
	disconnectFrom($config, $ip, $port);
	writeConfig($config);
	startprogram(); // Make it load the new config
	setFlash(t("syncthing_disconnected_node"));

	return array('type'=>'redirect','url'=>"$urlpath");
}

function download_get() {
	global $sc_dirpath, $sc_cfgpath, $sc_repospath, $sc_binpath, $urlpath, $sc_initd, $sc_initd_orig;
	$name = nameForArch(php_uname("m"));
	$url = downloadUrl($name);
	execute_program_shell(
		"mkdir -p $sc_dirpath $sc_cfgpath $sc_repospath && " .
		"cd $sc_dirpath && " .
		"curl -L -k $url -o $name.tar.gz && " .
		"tar -xf $name.tar.gz && " .
		"mv $name/syncthing syncthing && " .
		"rm -rf $name.tar.gz $name && " .
		"cp $sc_initd_orig $sc_initd && " .
		"chown -R www-data:www-data $sc_dirpath && " .
		"chmod 0755 $sc_binpath $sc_initd && " .
		"update-rc.d syncthing defaults");
	if (isConfigured()) {
		return array('type'=>'redirect','url'=>"$urlpath/start");
	}
	return array('type'=>'redirect','url'=>"$urlpath/configure");
}

function remove_get() {
	global $sc_binpath, $initpath, $urlpath, $sc_initd;
	if (!isInstalled()) {
		setFlash(t("syncthing_remove_not_installed"));
		return array('type'=>'redirect','url'=>"$urlpath");
	}
	if (isRunning()) {
		setFlash(t("syncthing_remove_running"));
		return array('type'=>'redirect','url'=>"$urlpath");
	}
	while (isInstalled()) {
		execute_program_shell(
			"update-rc.d -f syncthing remove && " .
			"rm -f $sc_binpath $initpath $sc_initd");
		sleep(1);
	}
	return array('type'=>'redirect','url'=>"$urlpath");
}

function stopprogram() {
	global $sc_initd, $sc_user, $sc_avahi_type, $sc_port;
	avahi_unpublish($sc_avahi_type, $sc_port);
	$counter = 0;
	while (isRunning() && $counter < sc_counter) {
		$counter++;
		exec("$sc_initd stop", $sc_user);
		sleep(1);
	}
	if ($counter == sc_counter){
		return FALSE;
	}
	return TRUE;
}

function startprogram() {
	global $sc_initd, $sc_avahi_type, $sc_avahi_desc, $sc_port, $sc_user;
	if (isRunning()) {
		return TRUE;
	}
	execute_program_detached("$sc_initd start");
	$counter = 0;
	while (!isRunning() && $counter < sc_counter) {
		$counter ++;
		sleep(1);
	}
	if ($counter == sc_counter){
		return FALSE;
	}
	$config = readConfig();
	$sc_id = getNodeID($config);

	//Extra Information added ONLY if $avahi_extra is true
	if (isset($avahi_extra) && $avahi_extra) {
		$oldinfo="node_id=$sc_id";
		//common.sh needs the config file, otherwise no access to it
		$dom=dom_import_simplexml($config)->ownerDocument;
		$dom->formatOutput = true;
		$jobj=execute_program_shell("/bin/bash /var/local/cDistro/plug/resources/monitor-aas/common.sh gather_information synchthing $(echo '".$dom->saveXML()."') ")['output'];
		//2x addslashes NEED to be applied
		$newinfo=",einfo=".addslashes(addslashes(trim(strtr($jobj,array(' '=>'',','=>';')))));
		avahi_publish($sc_avahi_type, $sc_avahi_desc, $sc_port, $oldinfo.$newinfo);
	} else {
		avahi_publish($sc_avahi_type, $sc_avahi_desc, $sc_port, $sc_id);
	}

	return TRUE;
}

function configure_get() {
	global $sc_user, $title, $sc_cfgpath, $sc_binpath, $urlpath, $sc_webui_port, $sc_webui_user,
		$sc_webui_pass_bc, $sc_port, $sc_nodeidpath, $sc_dirpath;

	if (!isInstalled()) {
		setFlash(t("syncthing_install_failed"));
		return array('type'=>'redirect','url'=>"$urlpath");
	}
	execute_program_shell("/bin/su $sc_user -c '$sc_binpath -generate=$sc_cfgpath'");

	startprogram(); // Start it to generate the default config
	$counter = 0;
	while (!hasConfig()&& $counter < sc_counter) {
		$counter ++;
		sleep(1);
	}
	if ($counter == sc_counter){
		return array('type'=>'redirect','url'=>"$urlpath");
	}
	if (!stopprogram()) {
		return array('type'=>'redirect','url'=>"$urlpath");
	} // Make sure the config file is ours
	$config = readConfig();
	unset($config->folder);
	$config->gui->attributes()->enabled="true";
	$config->gui->attributes()->tls="true";
	$config->gui->address="0.0.0.0:$sc_webui_port";
	$config->gui->user=$sc_webui_user;
	$config->gui->password=$sc_webui_pass_bc;
	$config->options->listenAddress="0.0.0.0:$sc_port";
	$config->options->globalAnnounceEnabled="false";
	$config->options->autoUpgradeIntervalH="0";
	writeConfig($config);
	file_put_contents($sc_nodeidpath, getNodeID($config));
	execute_program_shell("chown -R www-data:www-data $sc_dirpath");

	if (!startprogram()) {  // Make it load the new config
		return array('type'=>'redirect','url'=>"$urlpath");
	}
	return array('type'=>'redirect','url'=>"$urlpath");
}

function start_get() {
	global $title, $urlpath;

	if (!isInstalled()) {
		setFlash(t("syncthing_install_failed"));
		return array('type'=>'redirect','url'=>"$urlpath");
	}
	if (!isConfigured()) {
		setFlash(t("syncthing_configure_failed"));
		return array('type'=>'redirect','url'=>"$urlpath");
	}
	startprogram();
	return array('type'=>'redirect','url'=>"$urlpath");
}

function stop_get() {
	global $urlpath;

	stopprogram();
	return array('type'=>'redirect','url'=>"$urlpath");
}
