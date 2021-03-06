<?php
/**
 * The question class for this question type.
 *
 * @package    qtype
 * @subpackage javaunittest
 * @author     Gergely Bertalan, bertalangeri@freemail.hu
 * @author     Michael Rumler, rumler@ni.tu-berlin.de
 * @author     Martin Gauk, gauk@math.tu-berlin.de
 * @reference  sojunit 2008, Süreç Özcan, suerec@darkjade.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 OR later
 */
defined ( 'MOODLE_INTERNAL' ) || die ();

/**
 * Represents a javaunittest question.
 */
class qtype_javaunittest_question extends question_graded_automatically {
    public $responseformat = 'plain';
    public $responsefieldlines;
    public $givencode;
    public $testclassname;
    public $junitcode;
    public $feedbacklevel;
    public $auditvalue = 0.1;
    public $questionattemptid = null;
    
    /**
     * The moodle_page the page we are outputting to.
     *
     * @param moodle_page $page
     * @return qtype_javaunittest_format_renderer_base the response-format-specific renderer
     */
    public function get_format_renderer ( moodle_page $page ) {
        return $page->get_renderer ( 'qtype_javaunittest', 'format_' . $this->responseformat );
    }
    
    /**
     * The methode is called when the question attempt is actually stared and does necessary initialisation. In this
     * case only the type of the answer is defined.
     *
     * @return array of expected parameters
     */
    public function get_expected_data () {
        return array (
                'answer' => PARAM_RAW_TRIMMED 
        );
    }
    
    /**
     * Sumarize the response of the student.
     *
     * @param array $response
     * @return string answer OR null
     */
    public function summarise_response ( array $response ) {
        if ( isset ( $response['answer'] ) ) {
            $formatoptions = new stdClass ();
            $formatoptions->para = false;
            return html_to_text ( format_text ( $response['answer'], FORMAT_HTML, $formatoptions ), 0, false );
        } else {
            return null;
        }
    }
    
    /**
     * Delivers the correct response of the student, Since we do not have any given correct response to the question, we
     * return null.
     *
     * @return null
     */
    public function get_correct_response () {
        return null;
    }
    
    /**
     * Check whether the student has already answered the question.
     *
     * @param array $response
     * @return bool true if $response['answer'] is not empty
     */
    public function is_complete_response ( array $response ) {
        return !empty ( $response['answer'] );
    }
    
    /**
     * Validate the student's response. Since we have a gradable response, we always return an empty string here.
     *
     * @param array $response
     * @return string empty string OR please-select-an-answer-message
     */
    public function get_validation_error ( array $response ) {
        return '';
    }
    
    /**
     * Every time students change their response in the texteditor this function is called to check whether the
     * student's newly entered response differs.
     *
     * @param array $newresponse
     * @return boolean true if old and new response->answer are equal
     */
    public function is_same_response ( array $prevresponse, array $newresponse ) {
        return question_utils::arrays_same_at_key_missing_is_blank ( $prevresponse, $newresponse, 'answer' );
    }
    
    /**
     * When an in-progress {@link question_attempt} is re-loaded from the database, this method is called so that the
     * question can re-initialise its internal state as needed by this attempt. For example, the multiple choice
     * question type needs to set the order of the choices to the order that was set up when start_attempt was called
     * originally. All the information required to do this should be in the $step object, which is the first step of the
     * question_attempt being loaded.
     *
     * @param question_attempt_step The first step of the {@link question_attempt} being loaded.
     */
    public function apply_attempt_state ( question_attempt_step $step ) {
        global $DB;
        
        $stepid = $step->get_id ();
        
        // We need a place to store the feedback generated by JUnit.
        // Therefore we want to know the questionattemptid.
        if ( !empty ( $stepid ) ) {
            $record = $DB->get_record ( 'question_attempt_steps', 
                    array (
                            'id' => $stepid 
                    ), 'questionattemptid' );
            if ( $record ) {
                $this->questionattemptid = $record->questionattemptid;
            }
        }
    }
    
    /**
     * Call local or remote execute function. Evaluation of junit output is done. Grade is calculated and feedback
     * generated.
     *
     * @param array $response the response of the student
     * @return array $fraction fraction of the grade. If the max grade is 10 then fraction can be for example 2 (10/5 =
     *         2 indicating that from 10 points the student achieved 5).
     */
    public function grade_response ( array $response ) {
        global $CFG, $DB;
        
        if ( $this->questionattemptid === null ) {
            // we need the attempt id
            throw new Exception ( 'qtype_javaunittest: grade_response, no questionattemptid' );
        }
        
        $fraction = 0;
		$fraction_code = 0;
		#$weight_style = 0.20;
		$weight_style = $auditvalue;
		$weight_unittests  = 1.0 - $weight_style;
		$num_errors_style = 0;
        $feedback = '';
        $cfg_plugin = get_config ( 'qtype_javaunittest' );
        
        if ( empty ( $cfg_plugin->remoteserver ) ) {
            $ret = $this->local_execute ( $response );
        } else {
            $ret = $this->remote_execute ( $response );
        }
        
		$num_errors_style = substr_count( $ret['auditoutput'], '.java:' );
		$max_errors_style = 6;		
		if ( $num_errors_style < 0 ) {
			$num_errors_style = 0;
		}
		if ( $num_errors_style > $max_errors_style ) {
			$num_errors_style = $max_errors_style;
		}
		$fraction_style =  (($max_errors_style-$num_errors_style) * 1.0 )/ $max_errors_style;
		
        if ( $ret['error'] ) {
            if ( $ret['errortype'] == 'COMPILE_STUDENT_ERROR' ) {
                $feedback = get_string ( 'CE', 'qtype_javaunittest' ) . '<br><pre>' .
                         htmlspecialchars ( $ret['compileroutput'] ) . '</pre>';
            } else if ( $ret['errortype'] == 'COMPILE_TESTFILE_ERROR' ) {
                $feedback = get_string ( 'JE', 'qtype_javaunittest' );
				$output = $ret['compileroutput'];
				$feedback .= '<br/>';				
				$feedback .= '<div style="border:1px solid #000;">';
				$feedback .= '<b><font color="red">Error in Unit Test Definition</font></b>';
				$feedback .= '</div><br/>';
				$feedback .= '<font color="#0000A0"><b>Details of the execution:</b></font><br/>';
				$feedback .= '<pre>' . $output . '</pre>';
				$feedback .= '<br/>';
            } else if ( $ret['errortype'] == 'TIMEOUT_RUNNING' ) {
                $feedback = get_string ( 'TO', 'qtype_javaunittest' );
            } else if ( $ret['errortype'] == 'REMOTE_SERVER_ERROR' ) {
                $feedback = htmlspecialchars ( 'REMOTE_SERVER_ERROR: ' . $ret['message'] );
            }
        } else {
            
            // the JUnit-execution-output returns always a String in the first line
            // e.g. "...F",
            // which means that 1 out of 3 test cases didn't pass the JUnit test
            // In the second line it says "Time ..."
            $output = $ret['junitoutput'];
            $junitstart = strrpos ( $output, 'JUnit version' );
            $matches = array ();
            $found = preg_match ( '@JUnit version [\d\.]*\n([\.EF]+)\n@', $output, $matches, 0, $junitstart );
            
            if ( !$found ) {
                $feedback = get_string ( 'JE', 'qtype_javaunittest' );
				$feedback .= '<br/>';				
				$feedback .= '<div style="border:1px solid #000;">';
				$feedback .= '<b><font color="red">Error in Unit Test Definition</font></b>';
				$feedback .= '</div><br/>';
				$feedback .= '<font color="#0000A0"><b>Details of the execution:</b></font><br/>';
				$feedback .= '<pre>' . $ret['compileroutput'] . '</pre>';
				$feedback .= '<br/>';
            } else {
                // count failures and errors
                $numtests = substr_count ( $matches[1], '.' );
                $numfailures = substr_count ( $matches[1], 'F' );
                $numerrors = substr_count ( $matches[1], 'E' );
                $totalerrors = $numfailures + $numerrors;
                
                $fraction_code = 1 - round ( ($totalerrors / $numtests), 2 );
                $fraction = ($fraction_code*$weight_unittests) + ($fraction_style*$weight_style);
		
                // generate feedback
                if ( $this->feedbacklevel >= FEEDBACK_ONLY_TIMES ) {
                    $feedback = get_string ( 'compiling', 'qtype_javaunittest', round ( $ret['compiletime'], 1 ) );
                    $feedback .= "<br>\n";
                    $feedback .= get_string ( 'running', 'qtype_javaunittest', round ( $ret['testruntime'], 1 ) );
                    $feedback .= "<br>\n";
                }
                if ( $this->feedbacklevel >= FEEDBACK_TIMES_COUNT_OF_TESTS ) {
                    #$feedback .= "Tests: " . $numtests . "<br>\n";
                    #$feedback .= "Failures: " . $numfailures . "<br>\n";
                    #$feedback .= "Errors: " . $numerrors . "<br>\n";
                    $feedback .= "<br>\n";
                }

                if ( $this->feedbacklevel == FEEDBACK_ALL_EXCEPT_STACKTRACE ) {
                    $matches = array ();
                    $found = preg_match ( '@(.*)There (were|was) (\d*) failure(s?):\n@s', $output, $matches );
                    if ( $found ) {
                        $feedback .= '<pre>' . htmlspecialchars ( $matches[1] ) . '</pre>';
                        $feedback .= "<br>\n";
                    } else {
                        $feedback .= '<pre>' . htmlspecialchars ( $output ) . '</pre>';
                        $feedback .= "<br>\n";
                    }
                }

                if ( $this->feedbacklevel == FEEDBACK_ALL ) {
				
						$feedback .= '<font color="#5F04B4"><b>';
						if ( ($numtests>0) && ($totalerrors==0) && ($num_errors_style==0) ) {
							$feedback .= get_string ( 'CA', 'qtype_javaunittest' );
						} else if ( ($numtests>0) && ( ($totalerrors>0) || ($num_errors_style>0) ) ) {
					    	$feedback .= get_string ( 'PCA', 'qtype_javaunittest' );
						} else if ( ($numtests>0) && ( ($numtests>=$totalerrors) && ($num_errors_style>=$max_errors_style) ) ) {
							$feedback .= get_string ( 'WA', 'qtype_javaunittest' );
						}						
						$feedback .= "</b></font>\n";
						$feedback .= "<br>\n";
						$feedback .= '<font color="#0000A0">Global grade for the current question: <b>'. ($fraction*100) . '&#37; / '. (100) . '&#37;</b></font><br/><br/>';											
						$feedback .= "<br>\n";
						
				        $outtext = preg_replace('/at(.*)\)\n/', '', $output);
						$outtext = htmlspecialchars ( $outtext );
						$outtext = preg_replace('/junit(.*)\n/', '<br/><b>junit$1</b><br/>', $outtext);
						
						$output_audit = preg_replace('/at(.*)\)\n/', '', $output);
						$output_audit = htmlspecialchars ( $ret['auditoutput'] );
						
						#$cwd = getcwd();
						#$feedback .= 'cwd=' .  getcwd() . '<br/>';
						$feedback .= 'dirroot=' .  $CFG->dirroot  . '<br/>';
						$feedback .= 'wwwroot=' .  $CFG->wwwroot  . '<br/>';
						$feedback .= '<div style="border:1px solid #000;">';
						$feedback .= '<b><font color="blue">Results of Code Audit</font></b>';
						$feedback .= '</div><br/>';
						$feedback .= '<font color="#0000A0">Value of Code Audit in question grade: <b>'. ($weight_style*100) . '&#37;</b></font><br/>';
						$feedback .= '<font color="#0000A0">The first <b>'. ($max_errors_style) . '</b> errors give bad points</font><br/>';						
						$feedback .= '<font color="#0000A0">Number of errors made by the student: <b>'. ($num_errors_style) . '</b></font><br/>';
						$feedback .= '<font color="#0000A0">Grade for section Code Audit: <b>'. ($fraction_style*$weight_style*100) . '&#37; / '. ($weight_style*100) . '&#37;</b></font><br/><br/>';
						$feedback .= '<font color="#0000A0"><b>Details of audit hereafter:</b></font><br/>';
						$feedback .= '<pre>' . $output_audit . '</pre>';
						$feedback .= '<br/>';
						
						$feedback .= '<div style="border:1px solid #000;">';
						$feedback .= '<b><font color="blue">Results of Unit Tests</font></b>';
						$feedback .= '</div><br/>';						
						$feedback .= '<font color="#0000A0">Number of evaluated tests: <b>'. ($numtests) . '</b></font><br/>';
						$feedback .= '<font color="#0000A0">Number of observed failures: <b>'. ($numfailures) . ' / '. ($numtests) . '</b></font><br/>';
						$feedback .= '<font color="#0000A0">Number of observed errors: <b>'. ($numerrors) . ' / '. ($numtests) . '</b></font><br/>';
						$feedback .= '<font color="#0000A0">Grade for section Unit Tests: <b>'. ($fraction_code*$weight_unittests*100) . '&#37; / '. ($weight_unittests*100) . '&#37;</b></font><br/><br/>';						
						$feedback .= '<font color="#0000A0"><b>Details of unit tests hereafter:</b></font><br/>';
						$feedback .= '<pre>' . $outtext . '</pre>';
                        $feedback .= "<br>\n";
                }



                // search for common throwables, by package, by alphabet
                if ( strpos ( $output, 'java.io.IOException' ) !== false ) {
                    $feedback .= get_string ( 'ioexception', 'qtype_javaunittest' );
                    $feedback .= "<br>\n<br>\n";
                }
                if ( strpos ( $output, 'java.io.FileNotFoundException' ) !== false ) {
                    $feedback .= get_string ( 'filenotfoundexception', 'qtype_javaunittest' );
                    $feedback .= "<br>\n<br>\n";
                }
                if ( strpos ( $output, 'java.lang.ArrayIndexOutOfBoundsException' ) !== false ) {
                    $feedback .= get_string ( 'arrayindexoutofboundexception', 'qtype_javaunittest' );
                    $feedback .= "<br>\n<br>\n";
                }
                if ( strpos ( $output, 'java.lang.ClassCastException' ) !== false ) {
                    $feedback .= get_string ( 'classcastexception', 'qtype_javaunittest' );
                    $feedback .= "<br>\n<br>\n";
                }
                if ( strpos ( $output, 'java.lang.NegativeArraySizeException' ) !== false ) {
                    $feedback .= get_string ( 'negativearraysizeexception', 'qtype_javaunittest' );
                    $feedback .= "<br>\n<br>\n";
                }
                if ( strpos ( $output, 'java.lang.NullPointerException' ) !== false ) {
                    $feedback .= get_string ( 'nullpointerexception', 'qtype_javaunittest' );
                    $feedback .= "<br>\n<br>\n";
                }
                if ( strpos ( $output, 'java.lang.OutOfMemoryError' ) !== false ) {
                    $feedback .= get_string ( 'outofmemoryerror', 'qtype_javaunittest' );
                    $feedback .= "<br>\n<br>\n";
                }
                if ( strpos ( $output, 'java.lang.StackOverflowError' ) !== false ) {
                    $feedback .= get_string ( 'stackoverflowerror', 'qtype_javaunittest' );
                    $feedback .= "<br>\n<br>\n";
                }
                if ( strpos ( $output, 'java.lang.StringIndexOutOfBoundsException' ) !== false ) {
                    $feedback .= get_string ( 'stringindexoutofboundexception', 'qtype_javaunittest' );
                    $feedback .= "<br>\n<br>\n";
                }
                if ( strpos ( $output, 'java.nio.BufferOverflowException' ) !== false ) {
                    $feedback .= get_string ( 'bufferoverflowexception', 'qtype_javaunittest' );
                    $feedback .= "<br>\n<br>\n";
                }
                if ( strpos ( $output, 'java.nio.BufferUnderflowException' ) !== false ) {
                    $feedback .= get_string ( 'bufferunderflowexception', 'qtype_javaunittest' );
                    $feedback .= "<br>\n<br>\n";
                }
                if ( strpos ( $output, 'java.security.AccessControlException' ) !== false ) {
                    $feedback .= get_string ( 'accesscontrolexception', 'qtype_javaunittest' );
                    $feedback .= "<br>\n<br>\n";
                }
                // append feedback phrase (wrong / [partially] corrent answer phrase)
                if ( $numtests > 0 && $totalerrors == 0 && $num_errors_style == 0 ) {
                    $feedback .= get_string ( 'CA', 'qtype_javaunittest' );
                } else if ( $numtests > 0 && ( $totalerrors != 0 || $num_errors_style != 0 ) ) {
                    $feedback .= get_string ( 'PCA', 'qtype_javaunittest' );
                } else if ( $numtest > 0 && ( $numtests >= $totalerrors && $num_errors_style >= $max_errors_style ) ) {
                    $feedback .= get_string ( 'WA', 'qtype_javaunittest' );
                }
				$feedback .= "<br>\n";
                                
            }
        }
        
        // save feedback
        $cur_feedback = $DB->get_record ( 'qtype_javaunittest_feedback', 
                array (
                        'questionattemptid' => $this->questionattemptid 
                ) );
        
        $db_feedback = new stdClass ();
        $db_feedback->questionattemptid = $this->questionattemptid;
        $db_feedback->feedback = $feedback;
        if ( $cur_feedback ) {
            $db_feedback->id = $cur_feedback->id;
            $DB->update_record ( 'qtype_javaunittest_feedback', $db_feedback );
        } else {
            $DB->insert_record ( 'qtype_javaunittest_feedback', $db_feedback );
        }
        
		
        return array (
                $fraction,
                question_state::graded_state_for_fraction ( $fraction ) 
        );
    }
    
    /**
     * Here happens everything important. Files are loaded and created. Compile- and execute-functions are called.
     *
     * @param array $response the response of the student
     * @return array result
     */
    function local_execute ( $response ) {
        global $CFG;
        $cfg_plugin = get_config ( 'qtype_javaunittest' );
        
        // create a unique temp folder to keep the data together in one place
        $temp_folder = $CFG->dataroot . '/temp/javaunittest_tmp_' . $this->id . '_' . $this->questionattemptid;
        
        if ( file_exists ( $temp_folder ) ) {
            $this->delTree ( $temp_folder );
        }
        $this->mkdir_recursive ( $temp_folder );
        
        try {
            // write testfile
            if ( !preg_match ( '/^[a-zA-Z0-9_]+$/', $this->testclassname ) )
                throw new Exception ( 'testclassname contains not allowed characters' );
            $testfile = $temp_folder . '/' . $this->testclassname . '.java';
            $fd_testfile = fopen ( $testfile, 'w' );
            if ( $fd_testfile === false )
                throw new Exception ( 'could not create testfile' );
            fwrite ( $fd_testfile, $this->junitcode );
            fclose ( $fd_testfile );
            
            // try to get the name of the student's class
            $studentscode = $response['answer'];
            $matches = array ();
            preg_match ( '/^(?:\s*public)?\s*class\s+(\w[a-zA-Z0-9_]+)/m', $studentscode, $matches );
            $studentsclassname = (!empty ( $matches[1] ) && $matches[1] != $this->testclassname) ? $matches[1] : 'Xy';
            
            // write student's file
            $studentsfile = $temp_folder . '/' . $studentsclassname . '.java';
            $fd_studentsfile = fopen ( $studentsfile, 'w' );
            if ( $fd_studentsfile === false )
                throw new Exception ( 'could not create studentsfile' );
            fwrite ( $fd_studentsfile, $studentscode );
            fclose ( $fd_studentsfile );
            
            // compile student's response
            $compiler = $this->compile ( $studentsfile );
            $compiletime = $compiler['time'];
            
            // compiler error
            if ( !empty ( $compiler['compileroutput'] ) ) {
                $compileroutput = str_replace ( $temp_folder, '', $compiler['compileroutput'] );
                if ( $cfg_plugin->debug_logfile) {
                    $logfile = $studentsfile . "_compilerout.txt";
                    $fd_logfile = fopen ( $logfile, 'w' );
                    if ( $fd_logfile === false )
                        throw new Exception ( 'could not create logfile' );
                    fwrite ( $fd_logfile, $compiler['compileroutput'] );
                    fclose ( $fd_logfile );
                }
                if ( !$cfg_plugin->debug_nocleanup )
                    $this->delTree ( $temp_folder );
                return array (
                        'error' => true,
                        'errortype' => 'COMPILE_STUDENT_ERROR',
                        'compileroutput' => $compileroutput 
                );
            }
            
            // compile testfile
            $compiler = $this->compile ( $testfile );
            $compiletime += $compiler['time'];
            
            // compiler error
            if ( !empty ( $compiler['compileroutput'] ) ) {
                if ( $cfg_plugin->debug_logfile) {
                    $logfile = $testfile . "_compilerout.txt";
                    $fd_logfile = fopen ( $logfile, 'w' );
                    if ( $fd_logfile === false )
                        throw new Exception ( 'could not create logfile' );
                    fwrite ( $fd_logfile, $compiler['compileroutput'] );
                    fclose ( $fd_logfile );
                }
                if ( !$cfg_plugin->debug_nocleanup )
                    $this->delTree ( $temp_folder );
                return array (
                        'error' => true,
                        'errortype' => 'COMPILE_TESTFILE_ERROR' ,
						'compileroutput' => $compiler['compileroutput'] 
                );
            }
            
            // run unit test
            $command = $cfg_plugin->pathjava . ' -Xmx' . $cfg_plugin->memory_xmx .
                     'm -Djava.security.manager=default -Djava.security.policy=' . $cfg_plugin->pathpolicy . ' -cp ' .
                     $cfg_plugin->pathjunit . ':' . $cfg_plugin->pathhamcrest . ':' . $temp_folder .
                     ' org.junit.runner.JUnitCore ' . $this->testclassname;
            
            $output = '';
            $testruntime = 0;
            
            $ret_proc = open_process ( $cfg_plugin->precommand . '; exec ' . escapeshellcmd ( $command ), 
                    $cfg_plugin->timeoutreal, $cfg_plugin->memory_limit_output * 1024, $output, $testruntime );
        
			
			
			$checkstylecommand = 'checkstyle -c "' . $CFG->dirroot . '/question/type/javaunittest/eigsi_checks.xml" "' . $studentsfile . '" ' ;
			$ret_check = open_process ( $cfg_plugin->precommand . '; exec ' . escapeshellcmd ( $checkstylecommand ), 
                    $cfg_plugin->timeoutreal, $cfg_plugin->memory_limit_output * 1024, $compileroutput_check, $testruntime );        
			$compileroutput_check = str_replace('' . dirname($studentsfile) .  '', '', $compileroutput_check);
			
            if ( $cfg_plugin->debug_logfile) {
                $logfile = $testfile . "_junitout.txt";
                $fd_logfile = fopen ( $logfile, 'w' );
                if ( $fd_logfile === false )
                    throw new Exception ( 'could not create logfile' );
                fwrite ( $fd_logfile, $output );
                fclose ( $fd_logfile );
            }
            if ( !$cfg_plugin->debug_nocleanup )
                $this->delTree ( $temp_folder );
            
            if ( $ret_proc == OPEN_PROCESS_TIMEOUT || $ret_proc == OPEN_PROCESS_UNCAUGHT_SIGNAL ) {
                return array (
                        'error' => true,
                        'errortype' => 'TIMEOUT_RUNNING' 
                );
            }
            
            return array (
                    'junitoutput' => $output,
					'auditoutput' => $compileroutput_check,
                    'error' => false,
                    'compiletime' => $compiletime,
                    'testruntime' => $testruntime 
            );
        } catch ( Exception $e ) {
            if ( !$cfg_plugin->debug_nocleanup )
                $this->delTree ( $temp_folder );
            throw $e;
        }
    }
    
    /**
     * Here happens everything important for remote executing.
     *
     * @param array $response the response of the student
     * @return array result
     */
    function remote_execute ( $response ) {
        $cfg_plugin = get_config ( 'qtype_javaunittest' );
        
        $post = array (
                'clientversion' => $cfg_plugin->version,
                'attemptid' => $this->questionattemptid,
                'testclassname' => $this->testclassname,
                'studentscode' => $response['answer'],
                'junitcode' => $this->junitcode,
                'memory_xmx' => $cfg_plugin->memory_xmx,
                'memory_limit_output' => $cfg_plugin->memory_limit_output,
                'timeoutreal' => $cfg_plugin->timeoutreal 
        );
        
        $curlHandle = curl_init ();
        curl_setopt ( $curlHandle, CURLOPT_URL, $cfg_plugin->remoteserver );
        curl_setopt ( $curlHandle, CURLOPT_POST, 1 );
        curl_setopt ( $curlHandle, CURLOPT_VERBOSE, 0 );
        curl_setopt ( $curlHandle, CURLOPT_POSTFIELDS, $post );
        curl_setopt ( $curlHandle, CURLOPT_FOLLOWLOCATION, 1 );
        curl_setopt ( $curlHandle, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt ( $curlHandle, CURLOPT_AUTOREFERER, 1 );
        curl_setopt ( $curlHandle, CURLOPT_MAXREDIRS, 10 );
        curl_setopt ( $curlHandle, CURLOPT_CONNECTTIMEOUT, 5 );
        curl_setopt ( $curlHandle, CURLOPT_TIMEOUT, 2 * $cfg_plugin->timeoutreal );
        curl_setopt ( $curlHandle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
        curl_setopt ( $curlHandle, CURLOPT_USERPWD, 
                $cfg_plugin->remoteserver_user . ':' . $cfg_plugin->remoteserver_password );
        $result = curl_exec ( $curlHandle );
        $HTTPStatusCode = curl_getinfo ( $curlHandle, CURLINFO_HTTP_CODE );
        curl_close ( $curlHandle );
        
        if ( $HTTPStatusCode != 200 ) {
            return array (
                    'error' => true,
                    'errortype' => 'REMOTE_SERVER_ERROR',
                    'message' => $result 
            );
        }
        
        $json = json_decode ( $result, true );
        if ( $json === null ) {
            return array (
                    'error' => true,
                    'errortype' => 'REMOTE_SERVER_ERROR',
                    'message' => 'JSON decoding error' 
            );
        }
        
        return $json;
    }
    
    /**
     * Assistent function to compile the java code
     *
     * @param string $file the .java file that should be compiled
     * @return array $compileroutput and $time
     */
    function compile ( $file ) {
        $cfg = get_config ( 'qtype_javaunittest' );
        
        $command = $cfg->pathjavac . ' -nowarn -cp ' . $cfg->pathjunit . ' -sourcepath ' . dirname ( $file ) . ' ' .
                 $file;        
		
        // execute the command
        $compileroutput = '';
        $time = 0;
        $ret = open_process ( $cfg->precommand . ';' . escapeshellcmd ( $command ), $cfg->timeoutreal, 
                $cfg->memory_limit_output * 1024, $compileroutput, $time );
        
		
		$compileroutput_compose = $compileroutput;
		
        if ( ($ret != OPEN_PROCESS_SUCCESS) && empty ( $compileroutput ) ) {
            $compileroutput_compose = 'error (timeout?)';
        }
        
        return array (		        
                'compileroutput' => $compileroutput_compose,
                'time' => $time 
        );
    }
    
    /**
     * Assistent function to create a directory inclusive missing top directories.
     *
     * @param string $folder the absolute path
     * @return boolean true on success
     */
    function mkdir_recursive ( $folder ) {
        if ( is_dir ( $folder ) ) {
            return true;
        }
        if ( !$this->mkdir_recursive ( dirname ( $folder ) ) ) {
            return false;
        }
        $rc = mkdir ( $folder, 01700 );
        if ( !$rc ) {
            throw new Exception ( "qtype_javaunittest: cannot create directory " . $folder . "<br>\n" );
        }
        return $rc;
    }
    
    /**
     * Assistent function to delete a directory tree.
     *
     * @param string $dir the absolute path
     * @return boolean true on success, false else
     */
    function delTree ( $dir ) {
        $files = array_diff ( scandir ( $dir ), array (
                '.',
                '..' 
        ) );
        foreach ( $files as $file ) {
            (is_dir ( "$dir/$file" )) ? $this->delTree ( "$dir/$file" ) : unlink ( "$dir/$file" );
        }
        $rc = rmdir ( $dir );
        return $rc;
    }
}

define ( 'OPEN_PROCESS_SUCCESS', 0 );
define ( 'OPEN_PROCESS_TIMEOUT', 1 );
define ( 'OPEN_PROCESS_OUTPUT_LIMIT', 2 );
define ( 'OPEN_PROCESS_UNCAUGHT_SIGNAL', 3 );
define ( 'OPEN_PROCESS_OTHER_ERROR', 4 );

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
function open_process ( $cmd, $timeout_real, $output_limit, &$output, &$time ) {
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
