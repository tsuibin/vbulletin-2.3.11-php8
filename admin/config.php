<?php
/////////////////////////////////////////////////////////////
// Please note that if you get any errors when connecting, //
// that you will need to email your host as we cannot tell //
// you what your specific values are supposed to be        //
/////////////////////////////////////////////////////////////

// type of database running
// 'mysql' (MySQL / MariaDB) or 'pgsql' (PostgreSQL 12+)
$dbservertype = 'mysql';

// hostname or ip of server
$servername = 'localhost';

// port of the database server. Leave empty to use the default of the
// selected $dbservertype (3306 for mysql, 5432 for pgsql)
$dbport = '';

// PostgreSQL only: schema that holds the vBulletin tables (default 'public')
$dbschema = '';

// username and password to log onto db server
$dbusername = 'root';
$dbpassword = 'passwd';

// name of database
$dbname = 'vbulletin';

// technical email address - any error messages will be emailed here
$technicalemail = 'yourname@domain.com';

// use persistant connections to the database
// 0 = don't use
// 1 = use
$usepconnect = 0;

// which users are allowed to view the admin log
// separate each userid with a comma
$canviewadminlog = '1';

// which users are allowed to prune the admin log
// separate each userid with a comma
$canpruneadminlog = '';

?>
