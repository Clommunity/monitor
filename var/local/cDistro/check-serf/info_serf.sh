#!/bin/bash
DIRECTORY="$(pwd)"
SQLITE3="/usr/bin/sqlite3"
DATABASEPWD="${DIRECTORY}/db"
DATABASENAME="neighbours.db"
DATABASE="${DATABASEPWD}/${DATABASENAME}"
BACKUP="168"
SERFCONF="/etc/avahi-ps-serf.conf"
GTCONF="/etc/getinconf-client.conf"
SERFSCR="/usr/share/avahi-ps/plugs/avahi-ps-serf"

[[ -f $SERFSCR ]] || err "\"$SERFSCR\" not available"
. "$SERFSCR"

readSerf() {
	ADVERTISE_IP=""
	test -f $SERFCONF && . $SERFCONF
	test -f $GTCONF && . $GTCONF
	[ -z "$ADVERTISE_IP" ] && ADVERTISE_IP=$(ip addr show dev $INTERNAL_DEV|grep "global.* $INTERNAL_DEV\$"|awk '{print $2}'|awk -F "/" {'print $1'})
	SerfNode="$ADVERTISE_IP"
	date=$(date +%s)
	printf "Reading data from SERF and storing in %s\n" $DATABASE
	while read i
	do
		ip=$(echo "$i"|cut -d " " -f 2)
		status=$(echo "$i"|cut -d " " -f 3)
		services=$(echo "$i"|cut -d " " -f 4|tr "," " "|grep -o "$SERFTAG".*| sed "s/^services=//g")
		[ ! -z "$services" ] && services=$(echo $services|base64 -d|bunzip2|sed s/\"/\'/g)
		echo "INSERT INTO neigh VALUES (NULL, \"$SerfNode\", \"$ip\", \"$status\", \"$services\", $date);"|$SQLITE3 $DATABASE
	done < <(/opt/serf/serf members|grep -v test= |sed "s/\s\+/ /g")
	
	printf "Inserting extra information into database:\n"
	while read i
	do
		#ONLY FOR ETAG
		nodeName=$(echo "$i"|cut -d " " -f 1)
		nodeIP=$(echo "$i"|cut -d " " -f 2)
		extra=$(echo "$i"|cut -d " " -f 4|tr "," " "|grep -o "$SERFETAG".*|sed "s/^"$SERFETAG"=//g")
		[ ! -z "$extra" ] && extra=$(echo "$extra"|sed 's/^"\(.*\)"$/\1/'|descomprimir|sed s/\"/\'/g)
		echo "INSERT INTO extra VALUES (NULL, \"$nodeName\", \"$nodeIP\",\"$extra\",$date);" |$SQLITE3 $DATABASE
		printf "INSERT INTO extra VALUES (NULL,\"%s\",\"%s\",\"%s\",%s);\n" "$nodeName" "$nodeIP" "$extra" "$date"
	done < <($SERFBIN members|grep -v test= |sed "s/\s\+/ /g")
	#Now we can issue a clear cache Payload is json we can add more if needed like 
	#clearonlyif condition that way we may have some control over what stays/goes
	$SERFBIN event cleareinfo '{"date":"'$date'","monitor":"true"}'
}

createDB() {
	[ ! -f "$DATABASE" ] && {
		mkdir -p ${DATABASEPWD}
		echo "CREATE TABLE neigh(id integer primary key autoincrement, serfnode STRING, ip STRING, status STRING, services TEXT, Timestamp DATETIME DEFAULT CURRENT_TIMESTAMP);" | $SQLITE3 $DATABASE ;
		echo "CREATE TABLE extra(id integer primary key autoincrement, serfnodename STRING, serfnodeIP STRING, extra TEXT, Timestamp DATETIME DEFAULT CURRENT_TIMESTAMP);"|$SQLITE3 $DATABASE
	}
}

compressDB() {
	[ -f "$DATABASE" ] && {
		bzip2 $DATABASE
	}
}

uncompressDB() {
	[ -f "$DATABASE.bz2" ] && {
		bunzip2 $DATABASE.bz2
	}
}


uncompressDB
# If Database not exist... create.
createDB
# Read serf information and save in Database.
readSerf
compressDB
