INSTALLDIR = $(DESTDIR)
ARCH ?= $(shell uname -m|sed 's/i.86/i386/'|sed 's/^arm.*/arm/')

all:
	@echo "all"
clean:
	@echo "clean"
install:
	@echo "Make directory"
	mkdir -p $(INSTALLDIR)/etc/serf
	mkdir -p $(INSTALLDIR)/etc/serf/handlers
	mkdir -p $(INSTALLDIR)/var/local/cDistro/plug/resources/monitor-aas/
	mkdir -p $(INSTALLDIR)/var/local/cDistro/plug/resources/peerstreamer/
	mkdir -p $(INSTALLDIR)/var/local/cDistro/plug/controllers/	
	mkdir -p $(INSTALLDIR)/var/local/cDistro/plug/menus/
	mkdir -p $(INSTALLDIR)/var/local/cDistro/lang/
	mkdir -p $(INSTALLDIR)/var/local/cDistro/config/
	mkdir -p $(INSTALLDIR)/etc/init.d/
	mkdir -p $(INSTALLDIR)/usr/share/avahi-ps/plugs/
	mkdir -p $(INSTALLDIR)/usr/share/avahi-service/files/
	
	@echo "Install files"

	### SERF update
	install -m 0755 etc/init.d/serf $(INSTALLDIR)/etc/init.d/
	@echo "SERF updated"
	# adding handlers
	install -m 0755 etc/serf/handlers/handle $(INSTALLDIR)/etc/serf/handlers/
	install -m 0755 etc/serf/handlers/user-* $(INSTALLDIR)/etc/serf/handlers/
	@echo "SERF handlers updated"
	###

	### Avahi-ps update
	install -m 0755 usr/share/avahi-ps/plugs/avahi-ps-serf $(INSTALLDIR)/usr/share/avahi-ps/plugs/
	install -m 0755 usr/share/avahi-service/files/*.service $(INSTALLDIR)/usr/share/avahi-service/files/
	@echo "Avahi-ps updated"
	###
	
	### Other services
	install -m 0755 var/local/cDistro/plug/resources/peerstreamer/* $(INSTALLDIR)/var/local/cDistro/plug/resources/peerstreamer/
	@echo "Updated other services"
	###

	### Cloudy update
	# new monitor-aas scripts
	install -m 0755 var/local/cDistro/plug/resources/monitor-aas/common.sh $(INSTALLDIR)/var/local/cDistro/plug/resources/monitor-aas/ 
	install -m 0755 var/local/cDistro/plug/controllers/monitor-aas.php $(INSTALLDIR)/var/local/cDistro/plug/controllers/
	# adding menus, and other updates
	install -m 0755 var/local/cDistro/plug/controllers/cloudyupdate.php $(INSTALLDIR)/var/local/cDistro/plug/controllers/
	install -m 0755 var/local/cDistro/plug/menus/cloudy.menu.php $(INSTALLDIR)/var/local/cDistro/plug/menus/
	install -m 0755 var/local/cDistro/lang/*.menus.php $(INSTALLDIR)/var/local/cDistro/lang/
	install -m 0755 var/local/cDistro/config/global.php $(INSTALLDIR)/var/local/cDistro/config/
	@echo "Cloudy updated"
.PHONY: all clean install
