<?php
/**
 * The php configuration file for the remote part of 
 * moodle qtype_javaunittext.
 *
 * To use the remote server make sure a remoteserver URL is given in the clients config.
 *
 * @package    qtype
 * @subpackage javaunittest
 * @author     Michael Rumler, rumler@ni.tu-berlin.de
 * @author     Martin Gauk, gauk@math.tu-berlin.de
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

/**
 * White list of all allowed moodle client domains for security reason. Edit and add your moodle domain and/or ip here.
 * e.g. $whitelist = array (); $whitelist [] = 'my.server.tld';
 *
 * @staticvar array whitelist
 */
$whitelist = array ();
//$whitelist[] = '127.0.0.1';

/**
 * Username and password to access service
 *
 * @staticvar string USERNAME
 * @staticvar string PASSWORD
 */
define ( 'USERNAME', '' );
define ( 'PASSWORD', '' );

/**
 * Path to store files and compilation. In the non remote version the moodle variable $CFG->dataroot is used. Make sure
 * the PHP/webserver user has enough rights here, we propose chmod 01700. e.g. define ( 'DATAROOT',
 * '/var/www/moodledataremote/' );
 *
 * @staticvar string DATAROOT
 */
define ( 'DATAROOT', '/var/www/moodledataremote' );

/**
 * Memory limit (heap) in MB This sets the option -Xmx for the Java VM. Specifies the maximum size of the memory
 * allocation pool.
 *
 * @staticvar int MEMORY_XMX
 */
define ( 'MEMORY_XMX', 64 );

/**
 * Memory limit (output) in KB Limits the size of outputs during test executions.
 *
 * @staticvar int MEMORY_LIMIT_OUTPUT
 */
define ( 'MEMORY_LIMIT_OUTPUT', 8 );

/**
 * Timeout in seconds (real time) for test executions.
 *
 * @staticvar int TIMEOUT_REAL
 */
define ( 'TIMEOUT_REAL', 35 );

/**
 * Command before test execution This will be executed on shell before the tests. You may
 * use ulimit to limit resources (e.g. cpu time) for the tests.
 * e.g. 'ulimit -t 8' to set a limit of a maximum cpu time of 8 secs
 *
 * @staticvar int TIMEOUT_REAL
 */
define ( 'PRECOMMAND', 'ulimit -t 8' );

/**
 * Configure local path settings here.
 *
 * @staticvar string PATH_TO_JAVAC
 * @staticvar string PATH_TO_JAVA
 * @staticvar string PATH_TO_JUNIT
 * @staticvar string PATH_TO_HAMCREST
 * @staticvar string PATH_TO_POLICY
 */
// e.g. define('PATH_TO_JAVAC', '/usr/lib/jvm/java-7-openjdk-amd64/bin/javac');
define ( 'PATH_TO_JAVAC', '/usr/lib/jvm/java-7-openjdk-amd64/bin/javac' );

// e.g. define ( 'PATH_TO_JAVA', '/usr/lib/jvm/java-7-openjdk-amd64/bin/java' );
define ( 'PATH_TO_JAVA', '/usr/lib/jvm/java-7-openjdk-amd64/bin/java' );

// e.g. define('PATH_TO_JUNIT', '/usr/share/java/junit.jar');
define ( 'PATH_TO_JUNIT', '/usr/share/java/junit4.jar' );

// e.g. define('PATH_TO_HAMCREST', '/usr/share/java/hamcrest.jar');
define ( 'PATH_TO_HAMCREST', '/usr/share/java/hamcrest-core.jar' );

// e.g. define('PATH_TO_POLICY', dirname(__FILE__) . '/polfiles/defaultpolicy');
define ( 'PATH_TO_POLICY', dirname ( __FILE__ ) . '/polfiles/defaultpolicy' );

/**
 * Debug options, enable only for testing!
 * 
 * @staticvar bool DEBUG_NOCLEANUP do not delete temporary directories and files
 */
define ( 'DEBUG_NOCLEANUP', 0 );

/**
 * Log all actions to a file (absolute path) or leave empty if there should be no log.
 *
 * @staticvar string LOGFILE
 */
define ( 'LOGFILE', '/var/www/moodledataremote/serverlogfile.txt' );