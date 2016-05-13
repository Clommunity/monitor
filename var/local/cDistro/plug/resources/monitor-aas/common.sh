#!/bin/bash
##Common functions to bring extra information to light
## In the future this file should be the one to gather information and return a json text
PSPATH=/var/local/cDistro/plug/resources/peerstreamer
SERFSCR="/usr/share/avahi-ps/plugs/avahi-ps-serf"
TAHOE_PATH="/var/lib/tahoe-lafs"
INTRODUCER_PATH="$TAHOE_PATH/introducer"
NODE_PATH="$TAHOE_PATH/node"

gather_information() {
	local proc
	local pid

	proc=$1
	port=${2:-""}		#can come or we need to get it
	pid=${3:-""}		#can come or we need to get via ps
	if [ -z "$proc" ]; then
		#Error! no process was given
		return 1
	fi

	obj="{}"	#Json object	

	case "$proc" in
	  tahoe-lafs)
		obj=$(tahoe_lafs_info )
	;;
	  peerstreamer)
		obj=$(peerstreamer_info $port)
	;;
	  docker)
		obj=$(docker_info)
	;;
	  docker_peerstreamer)
		obj=$(docker_peerstreamer $sid $port)
	;;
	  docker_tahoe-lafs)
		obj=$(docker_tahoe-lafs $sid $port)
	;;
	  synchthing)
		shift
		obj=$(synchthing_info "$@")
		echo $obj
	exit
	;;
	  other)
		obj='{"service":"'$proc'","service.pid":"'$pid'","service.port":"'$port'","'$(get_stime)'}'
	;;
	esac
 	#Depending on the proc we may do different stuff
	#Peerstreamer => pscontroller info json
	#Tahoe => introducer directory
	#other services => depends upon the service itself

	echo "$obj" | jq -c .
}

docker_tahoe-lafs() {
	local PORT
	local SID

	SID=$1
	PORT=$2

	info=$(docker inspect $SID |jq -c .)
	cstate=$(echo $info | jq -c .[].State)
	cargs=$(echo $info | jq -c .[].Args)
	ccreated=$(echo $info | jq -c -r .[].Created)
        ccreated=$(date --date="$ccreated" +%s)
	## we can get more information from here

	echo '{"container.state":'$cstate"}" | jq -c ". + {\"container.args\":$cargs}" | jq -c ". + {\"container.created\":$ccreated}"
}

docker_peerstreamer() {
	local PORT
	local SID

	SID=$1
	PORT=$2

	## Getting information out of docker for SID
	info=$(docker inspect $SID | jq -c .)
	cstate=$(echo $info | jq -c .[].State)
	cargs=$(echo $info | jq -c .[].Args)
	ccreated=$(echo $info | jq -c -r .[].Created)
	ccreated=$(date --date="$ccreated" +%s)

	## We can get more information from here

	echo '{"container.state":'$cstate"}" | jq -c ". + {\"container.args\":$cargs}" | jq -c ". + {\"container.created\":$ccreated}"
}

peerstreamer_info() {
	local PORT
	psprog="streamer-udp-grapes-static"
	PORT=$1
	js=$(/bin/bash $PSPATH/pscontroller info json)
	
	if [ -z $PORT ] 
	then
	 ### maybe not exit complete, but try and find a port .. 
	 exit
	fi

	## cut all other except the one that has PORT
	## gather with the rest of information
	tmp=$(echo "$js"|jq -c .[])
	js=""
	for i in "${tmp[@]}"
	do
	 js+=$(echo $i| grep $PORT)
	done
	pid=$(echo $js|jq -c .peerstreamerpid)

	js=$(echo $js|jq -c ". + {\"$(get_stime)}")
	js=$(echo $js|jq -c ". + {\"$(get_rtime $pid)}")
	js=$(echo $js|jq -c ". + {\"exec\":\"$psprog\"}")
	js=$(echo $js|jq -c ". + {$(cpu_usage_by_process $psprog 5)}")
	js=$(echo $js|jq -c ". + {$(memory_usage_by_process $psprog 5)}")

	echo "$js" | jq -c .
}

get_stime() {
	time=$(date +%s)
	echo 'stime":"'$time'"'
}

get_rtime() {
	local pid
	local opt
	pid=$1
	opt=${2:-""}
	
	if [ -z $pid ]; then
	 rtime=0
	echo 'rtime":"'$rtime'"'
	exit 
	fi
	if [ -z $opt ]; then
	  rtime=$(ps aux|grep -v grep|grep -v avahi|grep $pid|awk 'BEGIN {FS=" "}{print $10}')
	else
	  rtime=$(ps aux|grep -v grep|grep -v avahi|grep $pid|grep $opt|awk 'BEGIN {FS=" "}{print $10}')
	fi
	echo 'rtime":"'$rtime'"'
}

get_nodetime() {
	nrtime=$(ps aux|grep -v grep|grep tahoe|grep node|awk 'BEGIN {FS=" "}{print $10}')
	echo 'ntime":"'$nrtime'"'
}

tahoe_lafs_info() {

	PID_FILE=$(cat "/var/lib/tahoe-lafs/introducer/twistd.pid" 2> /dev/null)
	GRID_NAME_FILE=$(cat "/var/lib/tahoe-lafs/introducer/grid.name")
	INTRODUCER_FURL=$(cat "/var/lib/tahoe-lafs/introducer/introducer.furl")
	if [ -z "$INTRODUCER_FURL" ]; then
	  INTRODUCER_PRIVATE_FURL=$(cat "/var/lib/tahoe-lafs/introducer/private/introducer.furl")
	fi
	INTRODUCER_PORT=$(cat "/var/lib/tahoe-lafs/introducer/introducer.port")
	WEB_PORT=$(cat "/var/lib/tahoe-lafs/introducer/web.port")

	if [ ! -z "$PID_FILE" ]; then
	pid='"introducer.pid":"'$PID_FILE'",'
	fi
	gridname='"introducer.gname":"'$GRID_NAME_FILE'",'
	ifurl='"introducer.furl":"'$INTRODUCER_FURL'",'
	if [ -z "$INTRODUCER_FURL" ]; then
	  ifurl='"introducer.furl":"'$INTRODUCER_PRIVATE_FURL'",'
	fi
	iport='"introducer.port":"'$INTRODUCER_PORT'",'
	wport='"introducer.web":"http://'$(ip r|grep src|grep 10\.|awk '{FS=" "}{print $9}')":"$WEB_PORT'",'
	stime='"introducer.'$(get_stime)','
	PID_FILE=$(cat "/var/lib/tahoe-lafs/introducer/twistd.pid" 2> /dev/null)
	## to fix something here, why no pid yet?? nobody knows
	if [ -z $PID_FILE ]; then
	echo "{"$pid$gridname$ifurl$iport$wport${stime::-1}"}"
	exit
	fi
	rtime='"introducer.'$(get_rtime $PID_FILE)','
	icpu='"introducer.cpu":'$(echo "{" $(cpu_usage_by_process $PID_FILE 5) "}"|jq -c .[])','
	imem='"introducer.memory":'$(echo "{" $(memory_usage_by_process $PID_FILE 5) "}"|jq -c .[])

	echo "{"$pid$gridname$ifurl$iport$wport$stime$rtime$icpu$imem"}"

}

synchthing_info() {
	local cfg_file
	cfg="/opt/synchthing/config/config.xml"

	cfg_file=""
	for i in "$@"
	do
	cfg_file+="$i\n"
	done

	if [ -z "${cfg_file}" ]; then
		[ ! -f "$cfg" ] && echo "{}"; return 2
		cfg="$(cat $cfg)"
	else
		cfg="${cfg_file}"
	fi

	obj='"synchthing.'$(get_stime)','
	obj+='"synchthing.'$(get_rtime synchthing www)','
	val=$(echo -e "${cfg}"|grep listenAddress|cut -d':' -f2|cut -d'<' -f1)
	obj+='"synchthing.port":"'$val'",'
	val=$(echo -e "${cfg}"|grep "id="|cut -d'=' -f2|cut -d' ' -f1)
	obj+='"synchthing.nodeid":'$val','
	val=$(echo -e "${cfg}"|grep maxSendKbps|cut -d'>' -f2|cut -d'<' -f1)
	obj+='"synchthing.maxSendKbps":"'$val'",'
	val=$(echo -e "${cfg}"|grep maxRecvKbps|cut -d'>' -f2|cut -d'<' -f1)
	obj+='"synchthing.maxRecvKbps":"'$val'",'
	obj+=$(cpu_usage_by_process synchthing 5)','
	obj+=$(memory_usage_by_process synchthing 5)
	echo "{"$obj"}" | jq -c .
}

cpu_usage_by_process() {
	#Gets cpu usage of process in percentage per second
	local proc
	local defn	

	proc=($1)
	defn=${2:-"1"}

	TMPFILE=`mktemp -t top.XXXXXXXXXX` && {	
  		top -S -cb -w 500 -n$defn > $TMPFILE
		for p in $proc; do
		 value=$(cat $TMPFILE | grep -v grep | grep $p | awk 'BEGIN { SUM = 0 } { SUM += $9} END { print SUM }')
		 defn=$(cat $TMPFILE | grep -c $p)
		 if [ "$defn" -ne "0" ] || [ "$value" -ne "0" ]; then
    		  echo '"'$p'.cpu":"'$(awk 'BEGIN { res = sprintf("%.4f", '$value/$defn'); print res }')'"'
		 else
		  echo '"'$p'.cpu":"0.0"'
		 fi
  		done
 		rm -f $TMPFILE
	}
}

memory_usage_by_process() {
	#Gets memory usage of process in percentage per second
	local proc
	local defn	

	proc=($1)
	defn=${2:-"1"}

	TMPFILE=`mktemp -t top.XXXXXXXXXX` && {	
  		top -S -cb -w 500 -n$defn > $TMPFILE
		tmem=($(cat $TMPFILE | grep "KiB Mem.*.total" | awk '{ print $3 }'))
		for p in $proc; do
		 defn=$(cat $TMPFILE | grep -v grep | grep -c $p)
		if [ "$defn" -ne "0" ] || [ "$value" -ne "0" ]; then
		 value=$(cat $TMPFILE | grep -v grep | grep $p | awk 'BEGIN { SUM = 0 } { SUM += $6 } END { print (SUM/'$defn')/'${tmem[0]}' }')	
    		 echo '"'$p'.memory":"'$(awk 'BEGIN { res = sprintf("%.4f", '$value'); print res }')'"'
		else
		  echo '"'$p'.memory":"0"'
		 fi
  		done
 		rm -f $TMPFILE
	}
}

status() {
	# gets the variable from globals
	setfile="/var/local/cDistro/config/global.php"
	[ ! -f "$setfile" ] && avahi_extra="false" || {
	avahi_extra=$(cat "$setfile" | grep -o 'avahi_extra.*' | cut -d'=' -f2| cut -d';' -f1)
	avahi_extra=$(echo "$avahi_extra" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')
	}
	echo $avahi_extra
}

aupdate() {
	#auto-updates the monitor scripts/cloudy
	local upd
	## upd non empty means install
	upd=$1

	GH_USER=Clommunity
	GH_REPO=monitor
	NAME=monitor
	
	REF=`curl -s https://api.github.com/repos/$GH_USER/$GH_REPO/git/refs/heads/master | grep 'sha'|awk -F':' '{print $2}'|awk -F'"' '{print $2}'`
	
	CREF=`[ -e /etc/cloudy/${GH_USER}-${GH_REPO}.sha ] && cat /etc/cloudy/${GH_USER}-${GH_REPO}.sha`
	
	## not done like this anymore
	#[ ! -z "${CREF}" ] && [ "${CREF}" == "${REF}" ] && echo "No update necessary" && exit
	
	echo "Updating Monitor to $REF"
	
	if [ ! -z "${upd}" ]; then
		# this will update by install
		echo "Doing by Install"
	
	exit
	fi
	
	echo "Doing by Diff"
	# we will diff and files and update instead of install
	cd /tmp/
	TMPDIR=`mktemp -d monitor.upd-XXXX` && {
		cd $TMPDIR
		curl -k "https://codeload.github.com/${GH_USER}/${GH_REPO}/zip/master" > ${GH_REPO}.zip
		unzip ${GH_REPO}.zip
		[ "$(update_needed ${CREF} ${REF} /tmp/${TMPDIR}/${GH_REPO}-master)" == "1" ] && echo "Files need update." && apply_diff "/tmp/$TMPDIR" ${GH_REPO} || echo "No update needed"
		cd ..
		rm -rf $TMPDIR
	}

	echo ${REF} > /etc/cloudy/${GH_USER}-${GH_REPO}.sha
	/etc/init.d/serf stop
	/etc/init.d/serf start
	echo "Auto-update is done"
}

update_needed() {
	local CREF
	local REF
	local dir
	local need
	## verifies if update is necessary
	CREF=$1
	REF=$2
	dir=$3
	## not only with the sha refs but also by looking if files were modified after install
	need="0"

	 # List of files
        files=(`find "${dir}/var" -type f`)
        files+=(`find "${dir}/usr" -type f`)
        files+=(`find "${dir}/etc" -type f`)
        ### only files in var/ usr/ etc/ needed

        for f in ${files[@]}
        do
                #Diff for each file and create patch
                #get the file to compare
                cf=`echo ${f} | sed "s%${dir}%%g"`
                fn=`basename ${f}`
		PATCH=`diff -u ${cf} ${f}`
		[ -z "${PATCH}" ] && continue

		need="1"
        done
	
	## Just to make sure
	[ "${CREF}" == "${REF}" ] || need="1"

	echo ${need}
}

apply_diff(){
	## Will create diff files and apply them
	local GH_REPO
	local dir
	dir=$1
	GH_REPO=$2

	TEMP="-s"	#--dry-run
	
	# List of files
	files=(`find "${GH_REPO}-master/var" -type f`)
	files+=(`find "${GH_REPO}-master/usr" -type f`)
	files+=(`find "${GH_REPO}-master/etc" -type f`)
	### only files in var/ usr/ etc/ needed

	for f in ${files[@]}
	do
		#Diff for each file and create patch
		#get the file to compare
		cf=`echo ${f} | sed "s%${GH_REPO}-master%%g"`
		fn=`basename ${f}`
		diff -u ${cf} ${f} > ${fn}.patch

		## now that we have the patch, apply it
		patch $TEMP -p2 ${cf} < ${fn}.patch

		echo "File ${cf} patched."
	done

}

install_cron() {
	cmd="@daily [ -e /var/local/cDistro/plug/resources/monitor-aas/common.sh ] && cd /var/local/cDistro/plug/resources/monitor-aas/ && /bin/bash common.sh auto-update > /dev/null 2>&1"

	## add to crontab
	cd /tmp
	TMPDIR=`mktemp -d monitor-cron.XXX` && {
		cd $TMPDIR
		crontab -l > mycron
		echo $cmd >> mycron
		crontab mycron
		cd ..
		rm -rf $TMPDIR
	}

	echo "Cron job installed"
}

case "$1" in 
  cpu_usage_by_process)
	shift
	cpu_usage_by_process $1 $2
	;;
  memory_usage_by_process)
	shift
	memory_usage_by_process $1 $2
	;;
  gather_information)
	shift
	gather_information $@
	;;
  enabled)
	shift
	status
	;;
  auto-update)
	shift
	aupdate $@
	;;
  install-cron)
	shift
	install_cron
	;;
  *)
	exit
	;;
esac
