BitcasaWebdav wrapper
=====================

Access Bitcasa using WebDAV. 

Installation:
-------------

Install php + nginx:

    sudo apt-get install php5 php5-curl php5-json php5-fpm php5-sqlite nginx -y
    # Change listen directive in /etc/php5/fpm/php5-fpm.ini
    echo "listen = 127.0.0.1:9001" >> /etc/php5/fpm/php-fpm.conf

Get WebDAV wrapper:

	git clone https://github.com/Yuav/bitcasawebdav
	cd bitcasawebdav
	sudo chown www-data cache
	sudo chown www-data config
	curl -sS https://getcomposer.org/installer | php
	mv composer.phar /usr/local/bin/composer
	composer install

Set up nginx vhost:

    server {
        listen 80;
        root  /var/www/bitcasawebdav/public;
        access_log  /var/log/nginx/bitcasa.access.log;
        error_log  /var/log/nginx/bitcasa.error.log;

		client_max_body_size 100G;
		client_body_timeout 3600;

        index   index.php;
        location / {
            dav_methods PUT DELETE MKCOL COPY MOVE;
            dav_ext_methods PROPFIND OPTIONS;
            if (!-f $request_filename) {
              rewrite ^(.*)$ /index.php last;
            }
        }

        location ~ \.php$ {
          fastcgi_pass   127.0.0.1:9001;
          fastcgi_param  SCRIPT_FILENAME $document_root/index.php;
          fastcgi_param   APPLICATION_ENV  development;
          fastcgi_read_timeout 180;
          include        fastcgi_params;
        }
    }

Browse to localhost/auth.php to retrieve access token.

Browse to http://localhost/ to verify your WebDAV server is working

Bitcasa WebDAV is now ready to be mounted in both Windows and Linux as a network drive

** Known issues:
 - Bitcasa API is very slow!
 - Limited amount of requests against Bitcasa (not approved for production atm.)
 - Cache never expires (changes done outside of WebDAV are not registered)
 - Files are not cached (thus experienced to be slow to access and upload)
