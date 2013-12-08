BitcasaWebdav wrapper
=====================

**Tested with Bitcasa Infinity Drive only!**

Installation:
-------------

	git clone https://github.com/Yuav/bitcasawebdav
	cd BitcasaWebdav
	curl -sS https://getcomposer.org/installer | php
	mv composer.phar /usr/local/bin/composer
	composer install
	
Set up Apache2 virtualhost:

Edit config/bitcasa.php with client ID and secret

Browse to localhost/auth.php to retrieve access token.

Browse to localhost/index.php to verify your WebDAV server is working


Directories
------------

What works:

* List folders and files
* Enter subdirs for listing
* Persistent cache of tree
* Create new folders
* Delete folders (including contents)
* Transparent cache updates

Not implemented:

* Rename/move folder
* Copy folder
* Support for depth parameter

Known issues:

* Cache never expires
* Windows -> new folder doesn't work due to lack of rename support

Files:
------

What works:

* File download

Not implemented:

* File upload
* File rename/move
* File copy
* Transparent read cache for files

TODO:

* Error handling doesn't exist
* Retry on gateway timeout (BS varnish timeout, it's just to retry)
* Extract Doctrine 2 cache stuff into a plugin
* Implement background multi-thread workers doing refresh of cache (Gearman?)
 - Something like weighted LRU queue of refresh + min and max lifetime of cache objects
 	E.G - min 5 minute cache -> /Bitcasa Infinite Drive accessed the most times since last retrieval, 
 	and more than 5 minutes - move to top of queue.
* Simplify setup!
* Get production credentials for app, and hardcode into project