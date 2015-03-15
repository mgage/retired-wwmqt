<?php
/**
 * The question type class for the webwork question type.
 * 
 * This questiontype allows a user to answer webwork questions within moodle.
 *
 * @copyright &copy; 2008 Matthew Leventi
 * @author Matthew Leventi mleventi@gmail.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package webwork_qtype
**/


require_once("$CFG->dirroot/question/type/webwork/config.php");
require_once("$CFG->dirroot/question/type/webwork/lib/question.php");
require_once("$CFG->dirroot/question/type/webwork/lib/questionfactory.php");

require_once("$CFG->dirroot/question/type/questiontype.php");
require_once("$CFG->dirroot/backup/lib.php");

const full_debug = 0;
const debug_trace = 0;

/**
 * The webwork question class
 *
 * Allows webwork questions to be used in Moodle through a new question type.
 */
 
 #   utility function used to sort answers in print_question_formulation_and_controls()
    function myalphacmp($a, $b)  {
    		return strcmp($a->field, $b->field);
	}

class webwork_qtype extends default_questiontype {
      
    //////////////////////////////////////////////////////////////////
    // Functions overriding default_questiontype functions
    //////////////////////////////////////////////////////////////////

    /**
    * @desc Required function that names the question type.
    * @return string webwork.
    */
    function name() {
        return 'webwork';
    }
    
    /**
    * @desc Gives the label in the Create Question dropdown.
    * @return string WeBWorK
    */
    function menu_name() {
        return 'WeBWorK';
    }
    
    /**
     * @desc Retrieves information out of question_webwork table and puts it into question object.
     * @return boolean to indicate success of failure.
     */
    function get_question_options(&$question) {
        //check if we have a question id.s
        if (full_debug) { notify("get question options"); }
        if(!isset($question->id)) {
            print_error('error_question_id','qtype_webwork');
            return false;
        }
        //check if we have a webwork question for this...
        if (full_debug) {notify("question is ".print_r($question,true)) ;}
        try {
            #$wwquestion = WebworkQuestionFactory::LoadByParent($question->id);
            $wwquestion = WebworkQuestionFactory::Load($question->id);
            $question->webwork = $wwquestion;
        } catch(Exception $e) {
            print_error('error_question_id_no_child','qtype_webwork');
        }        
        return true;
    }
    
    /**
    * @desc Saves the question object. Created to make some initial checks so we don't mess up the DB badly if stuff goes wrong.
    * @param $question object The question object holding new data.
    * @param $form object The form entries.
    * @param $course object The course object.
    */
    function save_question($question, $form, $course) {
        //check if we have a filepath key
        if (debug_trace) { notify("enter save_questions");}
        if(isset($form->storekey)) {
            $key = $form->storekey;
        } elseif(isset($question->storekey)) {
            $key = $question->storekey;
        } else {
            print_error('error_no_filepath','qtype_webwork');
            return false;
        }
        //check if we have a record
        try {
            $wwquestion = WebworkQuestionFactory::Retrieve($key);
            $form->webwork = $wwquestion;
            $temp = array();
            $form->questiontext = addslashes(base64_decode($wwquestion->render(0,$temp,0)));
            
            $form->questiontextformat = FORMAT_MOODLE;
        } catch(Exception $e) {
            print_error('error_no_filepath_record','qtype_webwork');
        }
        if (full_debug) { notify("saving question ".print_r($question,true)."form ".print_r($form, true) ); }
        //call parent
        return parent::save_question($question,$form,$course);
    }
    
    /**
     * @desc Saves the webwork question code and default seed setting into question_webwork. Will recreate all corresponding derived questions.
     * @param $question object The question object holding new data.
     * @return boolean to indicate success of failure.
     */
    function save_question_options($question) {
    	notify("entering save_question_options with: ");
    	notify( print_r($question, true) );
        if(!isset($question->id)) {
            print_error('error_question_id','qtype_webwork');
            return false;
        }
        $wwquestion = $question->webwork;
        //set the parent question number
        $wwquestion->setParent($question->id);
        //save
        try {
            $result = $wwquestion->save();
        } catch(Exception $e) {
            print_error('error_db_failure','qtype_webwork');
        }
        return true;
    }
    
    /**
    * @desc Creates an empty response before a student answers a question. This contains the possibly randomized seed for that particular student. Sticky seeds are created here.
    * @param $question object The question object.
    * @param $state object The state object.
    * @param $cmoptions object The cmoptions containing the course ID
    * @param $attempt id The attempt ID.
    * @return bool true. 
    */
    function create_session_and_responses(&$question, &$state, $cmoptions, $attempt) {
        if (debug_trace) { notify("Create session and responses"); }
        $state->responses['seed'] = rand(1,10000);
        return true;
    }
    
    /**
     * @desc Deletes question from the question_webwork table
     * @param integer $questionid The question being deleted
     * @return boolean to indicate success of failure.
     */
    function delete_question($questionid) {
       if (debug_trace) { notify("deleting question $questionid");}
        try {
            #$wwquestion = WebworkQuestionFactory::LoadByParent($questionid);
            $wwquestion = WebworkQuestionFactory::Load($questionid);  #FIXME
            if (full_debug) { notify("question is ".print_r($wwquestion, true)); }
            $wwquestion->remove();
            if (debug_trace) { notify($questionid." removed "); }
        } catch(Exception $e) {
            print_error('error_question_id_no_child','qtype_webwork');
        }
        return true;
    }
    
    /**
    * @desc Decodes and unserializes a students response into the response array carried by state
    * @param $question object The question object.
    * @param $state object The state that needs to be restored.
    * @return bool true.
    */
    function restore_session_and_responses(&$question, &$state) {
        if (debug_trace) { notify("restore session and responses"); }
        $serializedresponse = $state->responses[''];
        $serializedresponse = base64_decode($serializedresponse);
        $responses = unserialize($serializedresponse);
        if (full_debug) { notify("restored responses are: ".print_r($responses, true));}
        $state->responses = $responses;
        return true;
    }
    
    /**
    * @desc Serialize, encodes and inserts a students response into the question_states table.
    * @param $question object The question object for the session.
    * @param $state object The state to save.
    * @return true, or error on db change.
    */
    function save_session_and_responses(&$question, &$state) {
        if (debug_trace) { notify("save session and responses. State_id=".$state->id); }
        $responses = $state->responses;
        if (full_debug) { notify("saving responses : ".print_r($responses, true)); }
        $serialized = serialize($responses);
        $serialized = base64_encode($serialized);
        return set_field('question_states', 'answer', $serialized, 'id', $state->id);
    }
    
    /**
    * @desc Prints the question. Calls question_webwork_derived, and prints out the html associated with derivedid.
    * @param $question object The question object to print.
    * @param $state object The state of the responses for the question.
    * @param $cmoptions object Options containing course ID.
    * @param $options object
    */
    function print_question_formulation_and_controls(&$question, &$state, $cmoptions, $options) {
        global $CFG,$USER;
        if (debug_trace) { notify("enter print_question_formulation_and_controls"); } #evaluating the answer
//        #notify("question: ".print_r($question, true) );
//      #notify("state: ".print_r($state, true));
//        #notify("options: ".print_r($options, true ));
//        #notify("cmoptions: ".print_r($cmoptions, true ));
        //find webworkquestion object
        $wwquestion = $question->webwork;
        if(!isset($question->webwork)) {
            print_error('error_no_wwquestion','qtype_webwork');
            return false;
        }
        
        //find seed
        if(!isset($state->responses['seed'])) {
            print_error('error_no_seed','qtype_webwork');
            return false;
        }
 
        //find answers
        $answers=array();
         if((isset($state->responses)) && (is_array($state->responses))) {
            if (full_debug) { notify("questiontype: responses is an array containing submitted answers ".print_r($state->responses,true)); }
            foreach($state->responses as $key => $value) {
                if((is_string($key)) && (is_string($value))) {
                    array_push($answers, array('field' => $key,'answer'=>$value));
                }
            }
        }
 

        if (full_debug) { notify("question_type: print_question: answers: ".print_r($answers, true)); }
        $seed = $state->responses['seed'];
        $event = $state->event;
        if (full_debug) { notify("questiontype: call render with answers".print_r($answers, true) ); }
        
        # render the question 
        $questionhtml = $wwquestion->render($seed,$answers,$event);
        $showPartiallyCorrectAnswers = $wwquestion->getGrading();
        $qid = $wwquestion->getQuestion();
        if (full_debug) { notify("questiontype: showPartiallyCorrectAnswers=".print_r($showPartiallyCorrectAnswers, true)); }
        
        // grade responses if there are any?
        if (full_debug) { notify("Calling grade_responses internally"); }
    	$this->grade_responses(&$question, &$state, $cmoptions);
        if (full_debug) { notify("questiontype: after grading responses is an array containing answers ".print_r($state->responses,true)); }
        if(isset($state->responses['answers'])) {   #if answers have been graded
            $graded_answers = $state->responses['answers'];
        } else {
        	$graded_answers = Array();
        }
        
        # sort graded_answers
		usort($graded_answers, "myalphacmp");  # utility function myalphacmp defined at top of script
		
        //Answer Table construction
        if($state->event == QUESTION_EVENTGRADE) {
            if (full_debug) { notify("questiontype: grading answers"); }
            $answertable = new stdClass;
            $answertable->head = array();
            if($showPartiallyCorrectAnswers == 1) {
                array_push($answertable->head,'Result');
            }
            array_push($answertable->head,'Answer','Preview','Evaluated','Errors');
            $answertable->width = "100%";
            $answertabledata = array();
            if (full_debug) { notify("questiontype: answers are ".print_r($answers,true)); }
            foreach ($graded_answers as $answer) {
                if (full_debug) { notify("questionstype: answer is ". print_r($answer,true)); }
                if (!is_object($answer) ) { //create a fake object

                 $tmp =$answer;
                 $answer = new stdClass();
                 $answer->answer = $tmp;
                 $answer->score ="unknown";
                 $answer->preview = "undefined";
                 $answer->evaluated="maybe";
                 $answer->answer_msg="what?";
                }
                 if (full_debug) { notify("answer is ", print_r($answer, true)); }
            
                $answertablerow = array();
                //MEG
                if (full_debug) { notify("questiontype: partial correct answers = ". $showPartiallyCorrectAnswers); }
                if($showPartiallyCorrectAnswers == 1  ) {
                    $firstfield = '';
                    $firstfield .= question_get_feedback_image($answer->score);
                    if (full_debug) { notify("questiontype: score = ".$answer->score); }
                    if( $answer->score == 1) { 
                        $firstfield .= "Correct"; 
                    } else { 
                        $firstfield .= "Incorrect"; 
                    }
                    if (full_debug) { notify("first field = ".$firstfield); }
                    array_push($answertablerow,$firstfield);
                }
                array_push($answertablerow,$answer->answer,$answer->preview,$answer->evaluated,$answer->answer_msg);
                array_push($answertabledata,$answertablerow);
            }
            $answertable->data = $answertabledata;
            if (full_debug) { notify("questiontype: data for table ".print_r($answertable, print_r)); }
            $answertable = print_table($answertable);
        } else {
            $answertable = "";
        }
        if (debug_trace) { notify("enter display.html"); }
        include("$CFG->dirroot/question/type/webwork/display.html");
        if (debug_trace) { notify("leave display.html"); }
        if (debug_trace) { notify("leave print_question_formulation_and_controls"); }
        flush();
    }
    
    /**
    * @desc Assigns a grade for a student response. Currently a percentage right/total questions. Calls the Webwork Server to evaluate answers.
    * @param $question object The question to grade.
    * @param $state object The response to the question.
    * @param $cmoptions object ...
    * @return boolean true.
    */
    
    function grade_responses(&$question, &$state, $cmoptions) {
        global $CFG,$USER;
        if (debug_trace) { notify("GRADE RESPONSES"); }
        if(!isset($question->webwork)) {
            print_error('error_no_wwquestion','qtype_webwork');
            return false;
        }
        $wwquestion = $question->webwork;
        $wwquestion->grade($state);
        // Apply the penalty for this attempt
        $state->penalty = $question->penalty * $question->maxgrade;
        if (debug_trace) {notify("leave grade responses"); }
        return true;
    }
    
    /**
    * @desc Comparison of two student responses for the same question. Checks based on seed equality, and response equality.
    * Perhaps we could add check on evaluated answer (depends on whether the server is called before this function)
    * @param $question object The question object to compare.
    * @param $state object The first response.
    * @param $teststate object The second response.
    * @return boolean, Returns true if the state are equal | false if not.
    */
    function compare_responses($question, $state, $teststate) {  
        if (debug_trace) { notify("compare responses"); }     
        if(sizeof($state->responses) != sizeof($teststate->responses)) {
            return false;
        }
        //check values are equal
        foreach($state->responses as $key => $value) {
            if($value != $teststate->responses[$key]) {
                return false;
            }
        }
        return true;
    }
    
    /**
    * @desc Gets the correct answers from the SOAP server for the seed in state. Places them into the state->responses array.
    * @param $question object The question object.
    * @param $state object The state object.
    * @return object Object containing the seed,derivedid, and answers.
    */
    function get_correct_responses(&$question, &$state) {
        if (debug_trace) { notify("get correct responses"); }
        if(!isset($question->webwork)) {
            print_error('error_no_wwquestion','qtype_webwork');
            return false;
        }
        $wwquestion = $question->webwork;
        
        //find seed
        if(!isset($state->responses['seed'])) {
            print_error('error_no_seed','qtype_webwork');
            return false;
        }
        $seed = $state->responses['seed'];
        $wwquestion->grade($state);
        
        $state->raw_grade = 1;
        $state->event = ($state->event ==  QUESTION_EVENTCLOSE) ? QUESTION_EVENTCLOSEANDGRADE : QUESTION_EVENTGRADE;
        $state->penalty = 0;
        
        $ret = array();
        $ret['seed'] = $seed;
        $ret['answers'] = array();
        for($i=0;$i<count($state->responses['answers']);$i++) {
            $ret['answers'][$i]->answer = $state->responses['answers'][$i]->correct;
            $ret['answers'][$i]->answer_msg = "";
            $ret['answers'][$i]->score = '1';
            $ret['answers'][$i]->evaluated = "";
            $ret['answers'][$i]->preview = "";
            $ret['answers'][$i]->field = $state->responses['answers'][$i]->field;
        }
        return $ret;
    }
    
    /**
    * @desc Prints the questions buttons.
    * @param $question object The question object.
    * @param $state object The state object.
    * @param $cmoptions object The quizzes or other mods options
    * @param $options object The questions options.
    */
    function print_question_submit_buttons(&$question, &$state, $cmoptions, $options) {
        $courseid = $cmoptions->course;
        $seed = $state->responses['seed'];
        $attempt = $state->attempt;
        echo "<table><tr><td>";
        parent::print_question_submit_buttons($question,$state,$cmoptions,$options);
        echo "</td><td>";
        if((!$options->readonly) && ($courseid != 1)) {
            echo link_to_popup_window('/question/type/webwork/emailinstructor.php?qid=' . $question->id.'&amp;aid='.$attempt, 'emailinstructor',
                "<input type=\"button\" value=\"Email Instructor\" class=\"submit btn\">",
                600, 700, "Email Instructor");
        }
        echo "</td></tr></table>";
    }
    
    /**
    * @desc Enumerates the pictures for a response.
    * @param $question object The question object.
    * @param $state object The state object.
    * @return array HTML code with <img> tag for each picture.
    */
    function get_actual_response($question, $state) {
        if (debug_trace) { notify("get actual response");}
        $temp = '';
        $i = 1;
        foreach($state->responses['answers'] as $key => $value) {
            $responses[] = "Q$i) " . $value->answer;
            $i++;
        }
        if (debug_trace) {notify("actual responses are ".print_r($responses, true));}
        return $responses;
    }
    
    /**
    * @desc Prints a summary of a response.
    * @param $question object The question object.
    * @param $state object The state object.
    * @return string HTML.
    */
    function response_summary($question, $state, $length=80) {
        // This should almost certainly be overridden
        $responses = $this->get_actual_response($question, $state);
        if (empty($responses) || !is_array($responses)) {
            $responses = array();
        }
        if (is_array($responses)) {
            $responses = implode(' ', $responses);
        }
        return $responses;
    }


    
    /**
     * Backup the data in the question
     *
     * This is used in question/backuplib.php
     */
    function backup($bf,$preferences,$question,$level=6) {

        $status = true;

        $webworks = get_records('question_webwork', 'question', $question, 'id');
        //If there are webworks
        if ($webworks) {
            //Iterate over each webwork
            foreach ($webworks as $webwork) {
                $status = fwrite($bf,start_tag("WEBWORK",$level,true));
                fwrite ($bf,full_tag("CODE",$level+1,false,$webwork->code));
                fwrite ($bf,full_tag("GRADING",$level+1,false,$webwork->grading));
                $status = fwrite($bf,end_tag("WEBWORK",$level,true));
            }
            $status = question_backup_answers($bf,$preferences,$question);
        }
        return $status;
    }

    /**
     * Restores the data in the question
     *
     * This is used in question/restorelib.php
     */
     function restore($old_question_id,$new_question_id,$info,$restore) {

        $status = true;

        //Get the webworks array
        $webworks = $info['#']['WEBWORK'];

        //Iterate over webworks
        for($i = 0; $i < sizeof($webworks); $i++) {
            $webwork_info = $webworks[$i];

            //Now, build the question_webwork record structure
            $webwork = new stdClass;
            $webwork->question = $new_question_id;
            $webwork->code = backup_todb($webwork_info['#']['CODE']['0']['#']);
            $webwork->grading = backup_todb($webwork_info['#']['GRADING']['0']['#']);

            //The structure is equal to the db, so insert the question_shortanswer
            $newid = insert_record("question_webwork",$webwork);

            //Do some output
            if (($i+1) % 50 == 0) {
                if (!defined('RESTORE_SILENTLY')) {
                    echo ".";
                    if (($i+1) % 1000 == 0) {
                        echo "<br />";
                    }
                }
                backup_flush(300);
            }

            if (!$newid) {
                $status = false;
            }
        }
        return $status;
    }
    function print_question_grading_details($question, $state, $cmoptions, $options) {
    	if (debug_trace) { notify("enter print_question_grading_details"); }
    	if (full_debug) { notify("question_object: ".print_r($question,true)); }
    	//notify("state_object: ".print_r($state,true));
    	if (full_debug) { notify("cmoptions ".print_r($cmoptions, true)); }
    	//notify("options ".print_r($options, true));
    	//if (full_debug) { notify("Calling grade_responses internally"); }
    	//$this->grade_responses(&$question, &$state, $cmoptions);
    	//$state->last_graded->grade = 1;
    	//$state->last_graded->raw_grade = 1;
    	if (full_debug) { notify("state_object: ".print_r($state,true)); }
    	parent::print_question_grading_details($question, $state, $cmoptions, $options);
    }
}

// Register this question type with the system.
question_register_questiontype(new webwork_qtype());
?>
