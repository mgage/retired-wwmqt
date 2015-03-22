<?php
/**
 * The WebworkQuestion Class.
 * 
 * @copyright &copy; 2007 Matthew Leventi
 * @author mleventi@gmail.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package webwork_qtype
**/

require_once("$CFG->dirroot/question/type/webwork/config.php");
require_once("$CFG->dirroot/question/type/webwork/lib/client.php");
require_once("$CFG->dirroot/question/type/webwork/lib/htmlparser.php");

/**
* @desc The WeBWorKQuestion class
*/
class WebworkQuestion {
    
    /**
    * @desc The derivation used by this question.
    */
    private $_derivation;
    
    /**
    * @desc Object holding the same fields as the DB.
    */
    private $_data;
    
    /**
    * @desc Sets up the default problem environment that gets passed to the server.
    * @return object The problem environment.
    */
    public static function DefaultEnvironment() {
        global $USER;
        $env = new stdClass;
        $env->psvn = "MoodleSet";
        $env->psvnNumber = $env->psvn;
        $env->probNum = "MoodleProblemNum";
        $env->questionNumber = $env->probNum;
        $env->fileName = "MoodleProblemTemplate";
        $env->probFileName = $env->fileName;
        $env->problemSeed = "0";
        $env->displayMode = "images";
        $env->languageMode = $env->displayMode;
        $env->outputMode = $env->displayMode;
        $env->formattedOpenDate = "MoodleOpenDate";
        $env->openDate = "10";
        $env->formattedDueDate = "MoodleOpenDate";
        $env->dueDate = "11";
        $env->formattedAnswerDate = "MoodleAnswerDate";
        $env->answerDate = "12";
        $env->numOfAttempts = "3";
        $env->problemValue = "";
        $env->sectionName = "Default Profs Name";
        $env->sectionNumber = $env->sectionName;
        $env->recitationName = "Default TAs Name";
        $env->recitationNumber = $env->recitationName;
        $env->setNumber = "Default Set";
        $env->studentLogin = $USER->username;
        $env->studentName = $USER->firstname . " " . $USER->lastname;
        $env->studentID = $USER->username;
        $env->ANSWER_PREFIX = "Moodle";
        return $env;
    }
    

//////////////////////////////////////////////////////////////////////////////////
//MAIN FUNCTIONS
//////////////////////////////////////////////////////////////////////////////////
    
    /**
    * @desc Constructor for a question. Sets the data and creates a path to a directory.
    * @param object $dataobject The object that will go into the db.
    * @param object $derivation A derivation of the question.
    */
    public function WebworkQuestion($dataobject,$derivation=null) {
        if (full_debug) { notify("creating new wwquestion"); }
        $this->_data = $dataobject;
        $this->_derivation = $derivation;    
    }
    
    /**
    * @desc Generates the HTML for a particular question.
    * @param integer $seed The seed of the question.
    * @param array $answers An array of answers that needs to be rendered.
    * @param object $event The event object.
    * @return string The HTML question representation.
    */
    public function render($seed,&$answers,$event) {
        //JIT Derivation creation
        //Usually we have this from the check answers call
        if (debug_trace) { notify("enter question: render($seed, &$answers, $event) "); }
        //filter seed field from answer list
        $temporaryanswers = array();
        foreach ($answers as $answer) {
        	if($answer['field']!='seed') {
        		array_push( $temporaryanswers, $answer);
        	}
        }
        $answers = $temporaryanswers;
        // if there is no derivation create one (disabled for now)
        if(1 or !isset($this->_derivation)) {
            $client = WebworkClient::Get();
            $env = WebworkQuestion::DefaultEnvironment();
            if (full_debug) { notify("create derivation env : ".print_r($env, true));}
            $env->problemSeed = $seed;
            //notify("_data->code ".print_r($this->_data->code,true));
            if (full_debug) { notify("before processing by PG answers: ".print_r($answers, true));}
            $result = $client->renderProblem($env,$this->_data->code, $answers);
            if (full_debug) { notify("question: render: received result from client::renderProblem. Answers may have been modified by renderer.");}
            $derivation->html = base64_decode($result->output);
            if (full_debug) { notify("derivation: ".print_r($derivation, true) );}
            if (full_debug) { notify("after processing by PG:  answers:".print_r($answers,true));}
            $derivation->seed = $result->seed;
            $this->_derivation = $derivation;
        }
        
        $orderedanswers = array();
        $tempanswers = array();
        
        // convert answer format
        if (full_debug) { notify( "convert answer format from that returned by PG"); }
         //notify( "PG answers ". print_r($answers,true) );
        foreach($answers as $answer) {           
        	$tempanswers[$answer['field']] = $answer['answer'];
        }
        $answers = $tempanswers;
        if (full_debug) {  notify("converted, formatted answers are: ".print_r($answers, true));}
        
        //
        $showpartialanswers = $this->_data->grading;
//		notify("this is ".print_r($this,true));
        $questionhtml = "";
        $parser = new HtmlParser($this->_derivation->html);
        #notify("parser html is ".print_r($this->_data->code, true));
        $currentselect = "";
        $textarea = false;
        $checkboxes = array();
        //notify( "all answers ".print_r($answers, true));
        if (full_debug) {  notify( "question:: renderer- modifying HTML, adjusting answer labels, etc. "); }
        while($parser->parse()) {
            //change some attributes of html tags for moodle compliance
            //notify("changing attributes".print_r($parser->iNodeType). " is ". NODE_TYPE_ELEMENT);
            if ($parser->iNodeType == NODE_TYPE_ELEMENT) {
                $nodename = strtoupper($parser->iNodeName);
                if(isset($parser->iNodeAttributes['name'])) {
                    $name = $parser->iNodeAttributes['name'];
                }
//				notify("node name is ".$nodename);
                //handle generic change of node's attribute name']
                if(($nodename == "INPUT") || ($nodename == "SELECT") || ($nodename == "TEXTAREA")) {
//					notify("name is ".$name);
                    $parser->iNodeAttributes['name'] = 'resp' . $this->_data->question . '_' . $name;
//                    notify("changing ". $name. " to ".$parser->iNodeAttributes['name']. "value ".$parser->iNodeAttributes["value"]);
                    if(($event == QUESTION_EVENTGRADE) && (isset($answers[$name]))) {
                        if($showpartialanswers) {
                            if(isset($parser->iNodeAttributes['class'])) {
                                $class = $parser->iNodeAttributes['class'];
                            } else {
                                $class = "";
                            }
							 //notify("question: name ".$name." answers [".print_r($answers[$name], true)."]");
                            //FIXME  MEG -- need to get the score correctly
                            //FIXME answers don't appear to have scores yet because they haven't been graded
                            //if ( !isset($answers[$name]['score'] )) {
                            //	notify("question: name ".$name." answers ".print_r($answers[$name], true));              
                            //	$parser->iNodeAttributes['class'] = $class . ' ' . question_get_feedback_class($answers[$name]['score']);
                            //}
                        }
                    }
                }
                //handle specific change
                if($nodename == "INPUT") {
                    $nodetype = strtoupper($parser->iNodeAttributes['type']);
                    if($nodetype == "CHECKBOX") {
                        if(strstr($answers[$name]->answer,$parser->iNodeAttributes['value'])) {
                            //FILLING IN ANSWER (CHECKBOX)
                            array_push($orderedanswers,$answers[$name]);
                            $parser->iNodeAttributes['checked'] = '1';
                        }
                        $parser->iNodeAttributes['name'] = $parser->iNodeAttributes['name'] . '_' . $parser->iNodeAttributes['value'];                      
                    } else if($nodetype == "TEXT") {
                        if(isset($answers[$name])) {
                            //FILLING IN ANSWER (FIELD)
                            //notify("Pushing to $name $answers[$name], ", print_r($answers,true) );
                            array_push($orderedanswers,$answers[$name]);
                            //MEG remove ->answer 
                            $parser->iNodeAttributes['value'] = $answers[$name];
                        }
                    }
                } else if($nodename == "SELECT") {
                    $currentselect = $name;    
                } else if($nodename == "OPTION") {
                    if($parser->iNodeAttributes['value'] == $answers[$currentselect]->answer) {
                        //FILLING IN ANSWER (DROPDOWN)
                        array_push($orderedanswers,$answers[$currentselect]);
                        $parser->iNodeAttributes['selected'] = '1';
                    }
                } else if($nodename == "TEXTAREA") {
                    if(isset($answers[$name])) {
                        array_push($orderedanswers,$answers[$name]);
                        $textarea = true;
                        $questionhtml .= $parser->printTag();
                        $questionhtml .= $answers[$name]->answer;
                    }
                }
            }
            if(!$textarea) {
                $questionhtml .= $parser->printTag();
            } else {
                $textarea = false;
            }
        }
        $answers = $orderedanswers;
        if (debug_trace) {  notify("leave question::render ordered answers are: ".print_r($orderedanswers, true) );}
        return $questionhtml;
    }
    
    /**
    * @desc Grades a particular question state.
    * @param object $state The state to grade.
    * @return true.
    */    
    public function grade(&$state) {
        if (debug_trace) { notify("enter grade"); }
        if (full_debug) {  notify("question::grade: state is ".print_r($state,true));}
//        notify("state->responses['answers'] is ".print_r($state->responses['answers'], true));
        $seed = $state->responses['seed'];
        $answers = array();
        // This seems to be extra??? why was this here?
//        if((isset($state->responses['answers'])) && (is_array($state->responses['answers']))) {
//             notify("question::grade: there are answers");
//             foreach($state->responses['answers'] as $answerobj) {
//                 if((is_string($answerobj->field)) && (is_string($answerobj->answer))) {
//                     array_push($answers, array('field' => $answerobj->field, 'answer'=> $answerobj->answer));
//                 }
//             }
//         }
        if((isset($state->responses)) && (is_array($state->responses))) {
            if (full_debug) { notify("question::grade: responses is an array --good -- now creating answers");}
            if (full_debug) { notify(print_r($state->responses,true));}
            foreach($state->responses as $key => $value) {
                if((is_string($key)) && (is_string($value))) {
                    array_push($answers, array('field' => $key,'answer'=>$value));
                }
            }
        }
         if (full_debug) { notify("question::grade: answers array is now ".print_r($answers,true)); }
        //combine results from the answer array for checkboxes
        $checkanswers = array();
        $tempanswers = array();
        for($i=0;$i<count($answers);$i++) {
            $fieldname = $answers[$i]['field'];
            $pos = strpos($fieldname,'_');
            if($pos !== FALSE) {
                $fieldname = substr($fieldname,0,$pos);
                if(isset($checkanswers[$fieldname])) {
                    $checkanswers[$fieldname] .= $answers[$i]['answer'];
                } else {
                    $checkanswers[$fieldname] = $answers[$i]['answer'];
                }              
            } else {
                array_push($tempanswers,$answers[$i]);
            }
        }
         if (full_debug) { notify("question::grade: converting answer array format"); }
        foreach($checkanswers as $key => $value) {
            array_push($tempanswers,array('field' => $key,'answer' => $value));
        }
        $answers = $tempanswers;
         if (full_debug) { notify(" grade: answer array in new format ", print_r($answers, true));}
        //base64 encoding
        for($i=0;$i<count($answers);$i++) {
            $answers[$i]['field'] = base64_encode($answers[$i]['field']);
            $answers[$i]['answer'] = base64_encode($answers[$i]['answer']);
        }
        
        $client = WebworkClient::Get();
        $env = WebworkQuestion::DefaultEnvironment();
        $env->problemSeed = $seed;
        if (full_debug) { notify("question::grade::renderProblemAndCheck answer data_sent  ".print_r($answers,true));}
        $results = $client->renderProblemAndCheck($env,$this->_data->code,$answers);
        if (full_debug) {notify("question::grade::renderProblemAndCheck answer data_returned ". print_r($results, true) );}

        //process the question
        if (full_debug) { notify("question::grade:process question");}
        $question = $results->problem;
        $derivation = new stdClass;
        $derivation->seed = $question->seed;
        $derivation->html = base64_decode($question->output);
        $this->_derivation = $derivation;
        $this->_data->grading = $question->grading;
        
        //assign a grade
        if (full_debug) { notify("question::grade:assign a grade"); }
        $answers = $results->answers;
        //$answers_tmp = $answers;
        //$answers_tmp[0]->preview = base64_decode($answers_tmp[0]->preview);
        if (full_debug) { notify("question::grade:answers: sent to process answers".print_r($answers,true) );}
        $state->raw_grade = $this->processAnswers($answers);
        if (full_debug) { notify("raw grade ".print_r($state->raw_grade, true));}
        if (full_debug) {notify("answers after processAnswers".print_r($answers, true));}
        // mark the state as graded
        $state->event = ($state->event ==  QUESTION_EVENTCLOSE) ? QUESTION_EVENTCLOSEANDGRADE : QUESTION_EVENTGRADE;
        
    
        //put the responses into the state to remember
        if(!is_array($state->responses)) {
            $state->responses = array();
        }
        $state->responses['answers'] = $answers;
        if (debug_trace) { notify("leave grade state = ".print_r($state, true)); }
        return true;
    }
    
    /**
    * @desc Saves a question into the database.
    * @return true.
    */
    public function save() {
         if (debug_trace) { notify("saving this question ");}
        if(isset($this->_data->id)) {
            $this->update();
        } else {
            $this->insert();
        }
        return true;
    }
    
    /**
    * @desc Updates the wwquestion record in the database.
    * @throws Exception on DB error.
    */
    protected function update() {
         if (debug_trace) { notify("question: update");}
        $dbresult = update_record('question_webwork',$this->_data);
        if(!$dbresult) {
            throw new Exception();
        }
    }
    
    /**
    * @desc Inserts the wwquestion record into the database. Fills in the new database ID.
    * @throws Exception on DB error.
    */
    protected function insert() {
        if (debug_trace) {  notify("question: insert: ".print_r($this,true));}
        $this->_data->grading=1;
         if (full_debug) { notify("insert data: ".print_r($this->_data,true)); }
        $dbresult = insert_record('question_webwork',$this->_data);
        if($dbresult) {
            $this->_data->id = $dbresult;
        } else {
            throw new Exception();
        }
         if (full_debug) { notify("after insert -- modified question: ".print_r($this, true));}
    }
    
    /**
    * @desc Does basic processing on the answers back from the server.
    * @param array $answers The answers received.
    * @return integer The grade.
    */
    protected function processAnswers(&$answers) {
        if (debug_trace) { notify("enter process answers"); }
        $total = 0;   
        for($i=0;$i<count($answers);$i++) {
            if (full_debug) { notify("process answer:before # $i: ".print_r($answers[$i], true)); }
            $answers[$i]->field = base64_decode($answers[$i]->field);
            $answers[$i]->answer = base64_decode($answers[$i]->answer);
            $answers[$i]->answer_msg = base64_decode($answers[$i]->answer_msg);
            $answers[$i]->correct = base64_decode($answers[$i]->correct);
            $answers[$i]->evaluated = base64_decode($answers[$i]->evaluated);
            $answers[$i]->preview = base64_decode($answers[$i]->preview);
            $total += $answers[$i]->score;  
            if (full_debug) { notify("process answer:after # $i: ".print_r($answers[$i], true)); }          
        }
        return $total / $i;
    }    
     
//////////////////////////////////////////////////////////////////////////////////
//SETTERS
//////////////////////////////////////////////////////////////////////////////////
    
    public function setParent($id) {
         if (debug_trace) { notify("set parent id to ".$id); }
        $this->_data->question = $id;
    }

//////////////////////////////////////////////////////////////////////////////////
//GETS
//////////////////////////////////////////////////////////////////////////////////

    
    public function getId() {
        return $this->_data->id;
    }
    
    public function getQuestion() {
        return $this->_data->question;
    }
    
    public function getCode() {
        return $this->_data->code;
    }
    
    public function getCodeText() {
        return base64_decode($this->_data->code);
    }
    
    public function getCodeCheck() {
        return $this->_data->codecheck;
    }
    
    public function getGrading() {
        return $this->_data->grading;
    }

//////////////////////////////////////////////////////////////////////////////////
//REMOVERS
//////////////////////////////////////////////////////////////////////////////////
    
    /**
    * @desc Removes a wwquestion object.
    */
    public function remove() {
        delete_records('question_webwork','id',$this->_data->id);
    }
    
    
}

?>
