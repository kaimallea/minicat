all:	updatecode

updatecode:
ifneq "$(wildcard .git )" ""
	git pull origin master
	git submodule init
	git submodule update
endif

install:
	mkdir -p /usr/local/minicat/
	cp -r . /usr/local/minicat/
	chmod +x /usr/local/minicat/minicat.php
	ln -f -s /usr/local/minicat/minicat.php /usr/local/bin/minicat