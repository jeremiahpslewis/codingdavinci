skeleton web-application
========================

Installation
------------
Create a fitting directory and change to it

    git init

Add the remote repository

    git remote add origin git@github.com:username/skeleton.git

Get the code from remote

    git pull origin master

Copy resources/config/local.yml-dist to resources/config/local.yml and adjust the settings to your setup.

### Fetch third party dependencies

Fetch the external libraries which are handled through composer.json. In the repository-root

    $ wget http://getcomposer.org/composer.phar

or

    $ curl -O http://getcomposer.org/composer.phar

And then

    $ php composer.phar install

If this doesn't work (e.g. on Debian with Suhosin-path), set suhosin.executor.include.whitelist="phar"
in /etc/php5/cli/conf.d/suhosin.ini

If you installed with composer before and need to update or add the dependencies, then

    $ php composer.phar update

### Create and populate the database

Create a proper database and create the table-structure

    mysql -u cdavinci -p cdavinci < data.sql

Test installation
-------------------
If you have PHP 5.4 or later, you can change to the web-directory and start the built-in web-server

    $ cd web/
    $ php -S localhost:8000

Go to

    http://localhost:8000/

Directory Structure
-------------------
	# Accessed by Web-Application
	/resources
	/src
	/web
		/js
		/css
	/vendor
