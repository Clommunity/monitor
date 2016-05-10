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
  *)
	exit
	;;
esac
