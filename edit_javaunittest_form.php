<?php
/**
 * The edit form class for this question type. Each time a question is created moodle uses the
 * edit form to collect data from the teacher. With this form the following attributes of a question 
 * need to be defined: name, question text, geven code and the JUnit test class. Affter editing this 
 * form a question is created in the database with the form's attributes.
 *
 * @package    qtype
 * @subpackage javaunittest
 * @author     Gergely Bertalan, bertalangeri@freemail.hu
 * @reference  sojunit 2008, Süreç Özcan, suerec@darkjade.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined ( 'MOODLE_INTERNAL' ) || die ();

/**
 * javaunittest question type editing form
 */
class qtype_javaunittest_edit_form extends question_edit_form {
    protected function definition_inner ( $mform ) {
        global $DB, $question, $PAGE;
        
        $loaded_initialy = optional_param ( 'reloaded_initialy', 1, PARAM_INT );
        
        $qtype = question_bank::get_qtype ( 'javaunittest' );
        
        $definitionoptions = $this->_customdata['definitionoptions'];
        $attachmentoptions = $this->_customdata['attachmentoptions'];
        
		
        
        // -------------------------- feedback options
        $mform->addElement ( 'select', 'feedbacklevel', get_string ( 'feedbacklevel', 'qtype_javaunittest' ), 
                $qtype->feedback_levels () );
        $mform->setDefault ( 'feedbacklevel', FEEDBACK_ONLY_TIMES );
        
		// -------------------------- feedback options
        $mform->addElement ( 'select', 'auditlevel', get_string ( 'auditlevel', 'qtype_javaunittest' ), 
                $qtype->audit_values() 
		);
        $mform->setDefault ( 'auditlevel', AUDIT_VALUE_30 );
        
        // -------------------------- size of the response field
        $mform->addElement ( 'select', 'responsefieldlines', get_string ( 'responsefieldlines', 'qtype_javaunittest' ), 
                $qtype->response_sizes () );
        $mform->setDefault ( 'responsefieldlines', 15 );
        
        // -------------------------- "Given Code" Text-Area
		
		$init_page  = '<script language="javascript" type="text/javascript" ';
		#$init_page .= 'src="http://www.cdolivet.com/editarea/editarea/edit_area/edit_area_full.js"></script> ';
		$init_page .= 'src=" http://lms-test2.eigsi.fr/inc/edit_area/edit_area_full.js"></script> ';
		$init_page .= '<script language="javascript" type="text/javascript" src="./../../../inc/no-copy-paste.js"></script>';
		$init_page .= '<script language="javascript" type="text/javascript"> ';
		$init_page .= 'editAreaLoader.init({id : "' . $attributes['id'] . '",syntax: "java",start_highlight: true,is_editable: ' . 'true' .' }); ';
		$init_page .= 
		$init_page .= ' </script> ';			
		
		#$mform->addElement ( $init_page ) ;
		
		#<textarea cols="80" rows="20" name="givencode" id="id_givencode">/** Sum of first integers. */&#010;class SommeN {&#010;    /** Computes the sum of the n first integer numbers.&#010;    * @param n the given number [0 +oo[&#010;    * @return the sum of n first integers&#010;    */&#010;    public static int sommeN (int n) {&#010;        // please put your code here&#010;        int res=0;&#010;        for(int i=1;i&lt;=n;i++) {&#010;            res+=i;&#010;        }&#010;        return res;&#010;    }&#010;}</textarea>
		
        #$mform->addElement ( 'script' , 'javascript',
        #        array (
		#			'src' => 'http://www.cdolivet.com/editarea/editarea/edit_area/edit_area_full.js',
        #        ) );
				
		#$PAGE->requires->js( "http://lms-test2.eigsi.fr/inc/edit_area/edit_area_full.js", true );
		 
		$mform->addElement ( 'textarea', 'givencode', get_string ( 'givencode', 'qtype_javaunittest' ), 
                array (
                        'cols' => 80,
                        'rows' => 20,
                ) );
				
				
		
        $mform->setType ( 'givencode', PARAM_RAW );
        $mform->addHelpButton ( 'givencode', 'givencode', 'qtype_javaunittest' );
        
        // -------------------------- "Test class" Text-Area
        $mform->addElement ( 'textarea', 'testclassname', get_string ( 'testclassname', 'qtype_javaunittest' ), 
                array (
                        'cols' => 80,
                        'rows' => 1 
                ) );
        $mform->setType ( 'testclassname', PARAM_ALPHANUMEXT );
        $mform->addRule ( 'testclassname', null, 'required' );
        $mform->addHelpButton ( 'testclassname', 'testclassname', 'qtype_javaunittest' );
        $mform->addElement ( 'textarea', 'junitcode', get_string ( 'uploadtestclass', 'qtype_javaunittest' ), 
                array (
                        'cols' => 80,
                        'rows' => 20 
                ) );
        $mform->setType ( 'junitcode', PARAM_RAW );
        $mform->addRule ( 'junitcode', null, 'required' );
        $mform->addHelpButton ( 'junitcode', 'uploadtestclass', 'qtype_javaunittest' );
    }
    public function qtype () {
        return 'javaunittest';
    }
}
