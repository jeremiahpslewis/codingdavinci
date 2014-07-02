Web-site verbrannte-und-verbannte.de
====================================

License
-------
    Web-site displaying the List of authors banned during the Third Reich
    Copyright (C) 2014 Daniel Burckhardt

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as
    published by the Free Software Foundation, either version 3 of the
    License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

Installation
------------
Clone the repository

    git clone https://github.com/jlewis91/codingdavinci.git

And change into to php-subdirectory

    $ cd codingdavinci/php

Copy resources/config/local.yml-dist to resources/config/local.yml and adjust the settings to your setup.

### Fetch third party dependencies

Fetch the external libraries which are handled through composer.json. In the repository-root

    $ wget http://getcomposer.org/composer.phar

or

    $ curl -O http://getcomposer.org/composer.phar

And then

    $ php composer.phar install

If this doesn't work (e.g. on Debian with Suhosin-patch), set suhosin.executor.include.whitelist="phar"
in /etc/php5/cli/conf.d/suhosin.ini

If you installed with composer before and need to update or add the dependencies, then

    $ php composer.phar update

### Create and populate the database

Create a proper database and import the table-structure and data
    $ cd ../
    $ mysql -u cdavinci -p cdavinci < data.sql

Test installation
-------------------
If you have PHP 5.4 or later, you can change to the web-directory and start the built-in web-server

    $ cd php/web/
    $ php -S localhost:8000

Go to

    http://localhost:8000/

Directory Structure
-------------------
	# Accessed by Web-Application
	/app
    /resources
	/src
	/vendor
	/web
		/js
		/css
