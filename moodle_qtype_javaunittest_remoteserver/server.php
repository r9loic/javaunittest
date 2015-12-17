<?php
/**
 * This is the remote server for compiling and executing java and
 * the junit tests for the qtype_javaunittest module for moodle.
 *
 * @package    qtype
 * @subpackage javaunittest
 * @author     Michael Rumler, rumler@ni.tu-berlin.de, Berlin Institute of Technology
 * @author     Martin Gauk, gauk@math.tu-berlin.de
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once (dirname ( __FILE__ ) . '/config.php');
require_once (dirname ( __FILE__ ) . '/version.php');

define ( 'OPEN_PROCESS_SUCCESS', 0 );
define ( 'OPEN_PROCESS_TIMEOUT', 1 );
define ( 'OPEN_PROCESS_OUTPUT_LIMIT', 2 );
define ( 'OPEN_PROCESS_UNCAUGHT_SIGNAL', 3 );
define ( 'OPEN_PROCESS_OTHER_ERROR', 4 );

// check if client is allowed
if ( !in_array ( $_SERVER["REMOTE_ADDR"], $whitelist ) || !isset ( $_SERVER['PHP_AUTH_USER'] ) ||
         !isset ( $_SERVER['PHP_AUTH_PW'] ) || $_SERVER['PHP_AUTH_USER'] !== USERNAME ||
         $_SERVER['PHP_AUTH_PW'] !== PASSWORD ) {
    header ( "HTTP/1.1 403 Forbidden" );
    write_log ( '[error 403] unauthorized request from ' . $_SERVER["REMOTE_ADDR"] );
    echo "REMOTE SERVER ERROR: Request from unauthorized " . $_SERVER["REMOTE_ADDR"] .
             ".\n Please contact your webmaster.";
    die ();
}

// check if we got all needed post parameters
$parameters = array (
        'clientversion',
        'attemptid',
        'testclassname',
        'studentscode',
        'junitcode',
        'memory_xmx',
        'memory_limit_output',
        'timeoutreal' 
);

foreach ( $parameters as $val ) {
    if ( !isset ( $_POST[$val] ) ) {
        header ( "HTTP/1.1 400 Bad Request" );
        write_log ( '[error 400] bad request from ' . $_SERVER["REMOTE_ADDR"] );
        die ( "REMOTE SERVER ERROR: Bad Request: not all needed post parameters given" );
    }
}

// check versions
if ( $_POST['clientversion'] != $version ) {
    header ( "HTTP/1.1 422 Invalid Version" );
    write_log ( '[error 422] invalid version from ' . $_SERVER["REMOTE_ADDR"] );
    echo "REMOTE SERVER ERROR: Moodle client and server version differ (" . $_POST['clientversion'] . " and " . $version .
             ")\n" . "Please contact your webmaster for an update.\n";
    die ();
}

// handle request
$memory_xmx = min ( MEMORY_XMX, intval ( $_POST['memory_xmx'] ) );
$memory_limit_output = min ( MEMORY_LIMIT_OUTPUT, intval ( $_POST['memory_limit_output'] ) );
$timeoutreal = min ( TIMEOUT_REAL, intval ( $_POST['timeoutreal'] ) );

write_log ( '[questionattemptid ' . intval ( $_POST['attemptid'] ) . '] incoming request' );

$time_start = microtime ( true );
$ret = compile_execute ();
header ( "HTTP/1.1 200 OK" );
echo json_encode ( $ret );

write_log ( 
        '[questionattemptid ' . intval ( $_POST['attemptid'] ) . '] request took ' .
                 round ( (microtime ( true ) - $time_start), 2 ) . ' ms' );

/**
 * Execute a command on shell and return all outputs
 *
 * @param string $cmd command on shell
 * @param int $timeout_real timeout in secs (real time)
 * @param int $output_limit stops the process if the output on stdout/stderr reaches a limit (in Bytes)
 * @param string &$output stdout/stderr of process
 * @param float &$time time needed for execution (in s)
 * @return int OPEN_PROCESS_SUCCESS, OPEN_PROCESS_TIMEOUT, OPEN_PROCESS_OUTPUT_LIMIT or OPEN_PROCESS_OTHER_ERROR
 */
function open_process($cmd, $timeout_real, $output_limit, &$output, &$time) {
    $descriptorspec = array (
            0 => array (
                    "pipe",
                    "r" 
            ), // stdin
            1 => array (
                    "pipe",
                    "w" 
            ), // stdout
            2 => array (
                    "pipe",
                    "w" 
            ) 
    ); // stderr
    
    $process = proc_open ( $cmd, $descriptorspec, $pipes );
    
    if ( !is_resource ( $process ) ) {
        return OPEN_PROCESS_OTHER_ERROR;
    }
    
    // pipes should be non-blocking
    stream_set_blocking ( $pipes[1], 0 );
    stream_set_blocking ( $pipes[2], 1 );
    
    $orig_pipes = array (
            $pipes[1],
            $pipes[2] 
    );
    $starttime = microtime ( true );
    $stderr_content = '';
    $ret = -1;
    
    while ( $ret < 0 ) {
        $r = $orig_pipes;
        $write = $except = null;
        
        if ( count ( $r ) ) {
            $num_changed = stream_select ( $r, $write, $except, 0, 800000 );
            if ( $num_changed === false ) {
                continue;
            }
        } else {
            usleep ( 800000 );
        }
        
        foreach ( $r as $stream ) {
            if ( feof ( $stream ) ) {
                $key = array_search ( $stream, $orig_pipes, true );
                unset ( $orig_pipes[$key] );
            } else if ( $stream === $pipes[1] ) {
                $output .= stream_get_contents ( $stream );
            } else if ( $stream === $pipes[2] ) {
                $stderr_content .= stream_get_contents ( $stream );
            }
        }
        
        $status = proc_get_status ( $process );
        
        // check time
        $time = microtime ( true ) - $starttime;
        if ( $time >= $timeout_real ) {
            proc_terminate ( $process, defined ( 'SIGKILL' ) ? SIGKILL : 9 );
            $ret = OPEN_PROCESS_TIMEOUT;
        }
        
        // check output limit
        if ( (strlen ( $output ) + strlen ( $stderr_content )) > $output_limit ) {
            proc_terminate ( $process, defined ( 'SIGKILL' ) ? SIGKILL : 9 );
            $ret = OPEN_PROCESS_OUTPUT_LIMIT;
        }
        
        if ( $status['signaled'] ) {
            $ret = OPEN_PROCESS_UNCAUGHT_SIGNAL;
        } else if ( !$status['running'] ) {
            $ret = OPEN_PROCESS_SUCCESS;
        }
    }
    
    $output .= $stderr_content;
    
    // all pipes need to be closed before calling proc_close
    fclose ( $pipes[0] );
    fclose ( $pipes[1] );
    fclose ( $pipes[2] );
    
    proc_close ( $process );
    
    $time = microtime ( true ) - $starttime;
    
    return $ret;
}

/**
 * Handle request, compile and execute junit test
 *
 * @return array result
 */
function compile_execute() {
    global $memory_xmx, $memory_limit_output, $timeoutreal;
    
    // create unique directory
    $temp_folder = DATAROOT . 'javaunittest_tmp_' . intval ( $_POST['attemptid'] );
    if ( file_exists ( $temp_folder ) ) {
        delTree ( $temp_folder );
    }
    mkdir_recursive ( $temp_folder );
    
    try {
        // write testfile
        $testclassname = $_POST['testclassname'];
        if ( !preg_match ( '/^[a-zA-Z0-9_]+$/', $testclassname ) )
            throw new Exception ( 'testclassname contains not allowed characters' );
        
        $testfile = $temp_folder . '/' . $testclassname . '.java';
        $fd_testfile = fopen ( $testfile, 'w' );
        if ( $fd_testfile === false )
            throw new Exception ( 'could not create testfile' );
        fwrite ( $fd_testfile, $_POST['junitcode'] );
        fclose ( $fd_testfile );
        
        // try to get the name of the student's class
        $studentscode = $_POST['studentscode'];
        $matches = array ();
        preg_match ( '/^(?:\s*public)?\s*class\s+(\w[a-zA-Z0-9_]+)/m', $studentscode, $matches );
        $studentsclassname = (!empty ( $matches[1] ) && $matches[1] != $testclassname) ? $matches[1] : 'Xy';
        
        // write student's file
        $studentsfile = $temp_folder . '/' . $studentsclassname . '.java';
        $fd_studentsfile = fopen ( $studentsfile, 'w' );
        if ( $fd_studentsfile === false )
            throw new Exception ( 'could not create studentsfile' );
        fwrite ( $fd_studentsfile, $studentscode );
        fclose ( $fd_studentsfile );
        
        // compile student's response
        $compiler = compile ( $studentsfile );
        $compiletime = $compiler['time'];
        
        // compiler error
        if ( !empty ( $compiler['compileroutput'] ) ) {
            $compileroutput = str_replace ( $temp_folder, '', $compiler['compileroutput'] ); //#TODO BM
            delTree ( $temp_folder );
            write_log ( '[questionattemptid ' . intval ( $_POST['attemptid'] ) . '] compile student error' );
            return array (
                    'error' => true,
                    'errortype' => 'COMPILE_STUDENT_ERROR',
                    'compileroutput' => $compileroutput 
            );
        }
        
        // compile testfile
        $compiler = compile ( $testfile );
        $compiletime += $compiler['time'];
        
        // compiler error
        if ( !empty ( $compiler['compileroutput'] ) ) {
            delTree ( $temp_folder );
            write_log ( '[questionattemptid ' . intval ( $_POST['attemptid'] ) . '] compile testfile error' );
            return array (
                    'error' => true,
                    'errortype' => 'COMPILE_TESTFILE_ERROR' 
            );
        }
        
        // run test
        $command = PATH_TO_JAVA . ' -Xmx' . $memory_xmx . 'm -Djava.security.manager=default -Djava.security.policy=' .
                 PATH_TO_POLICY . ' -cp ' . PATH_TO_JUNIT . ':' . PATH_TO_HAMCREST . ':' . $temp_folder .
                 ' org.junit.runner.JUnitCore ' . $testclassname;
        
        $output = '';
        $testruntime = 0;
        
        $ret_proc = open_process ( PRECOMMAND . '; exec ' . escapeshellcmd ( $command ), $timeoutreal, 
                $memory_limit_output * 1024, $output, $testruntime );
        
        if ( ! DEBUG_NOCLEANUP ) delTree ( $temp_folder );
        
        if ( $ret_proc == OPEN_PROCESS_TIMEOUT || $ret_proc == OPEN_PROCESS_UNCAUGHT_SIGNAL ) {
            write_log ( '[questionattemptid ' . intval ( $_POST['attemptid'] ) . '] uncaught signal (timeout)' );
            return array (
                    'error' => true,
                    'errortype' => 'TIMEOUT_RUNNING' 
            );
        }
        
        return array (
                'junitoutput' => $output,
                'error' => false,
                'compiletime' => $compiletime,
                'testruntime' => $testruntime 
        );
    } catch ( Exception $e ) {
        if ( ! DEBUG_NOCLEANUP ) delTree ( $temp_folder );
        header ( "HTTP/1.1 500 Internal Server Error" );
        write_log ( '[questionattemptid ' . intval ( $_POST['attemptid'] ) . '] Exception occured: ' . $e->getMessage () );
        die ( 'Internal Server Error: ' . $e->getMessage () );
    }
}

/**
 * Assistent function to compile the java code
 *
 * @param string $file the .java file that should be compiled
 * @return string $compileroutput the output of the compiler
 */
function compile($file) {
    global $memory_limit_output, $timeoutreal;
    
    $command = PATH_TO_JAVAC . ' -nowarn -cp ' . PATH_TO_JUNIT . ' -sourcepath ' . dirname ( $file ) . ' ' . $file;
    
    // execute the command
    $compileroutput = '';
    $time = 0;
    $ret = open_process ( PRECOMMAND . ';' . escapeshellcmd ( $command ), $timeoutreal, $memory_limit_output * 1024, 
            $compileroutput, $time );
    
    if ( $ret != OPEN_PROCESS_SUCCESS && empty ( $compileroutput ) ) {
        $compileroutput = 'error (timeout?)';
    }
    
    return array (
            'compileroutput' => $compileroutput,
            'time' => $time 
    );
}

/**
 * Assistent function to create a directory inclusive missing top directories.
 *
 * @param string $folder the absolute path
 * @return boolean true on success
 */
function mkdir_recursive($folder) {
    if ( is_dir ( $folder ) ) {
        return true;
    }
    if ( !mkdir_recursive ( dirname ( $folder ) ) ) {
        return false;
    }
    $rc = mkdir ( $folder, 01700 );
    if ( !$rc ) {
        die ( "cannot create directory " . $folder . "<br>\n" );
    }
    return $rc;
}

/**
 * Assistent function to delete a directory tree.
 *
 * @param string $dir the absolute path
 * @return boolean true on success, false else
 */
function delTree($dir) {
    $files = array_diff ( scandir ( $dir ), array (
            '.',
            '..' 
    ) );
    foreach ( $files as $file ) {
        (is_dir ( "$dir/$file" )) ? delTree ( "$dir/$file" ) : unlink ( "$dir/$file" );
    }
    $rc = rmdir ( $dir );
    return $rc;
}

/**
 * Write a message to log.
 *
 * @param string $msg
 */
function write_log($msg) {
    if ( LOGFILE == '' ) {
        return;
    }
    
    $fd = fopen ( LOGFILE, 'a' );
    if ( $fd ) {
        $msg = '[' . date ( DATE_RFC2822 ) . '] ' . $msg . "\n";
        fwrite ( $fd, $msg );
        fclose ( $fd );
    }
}