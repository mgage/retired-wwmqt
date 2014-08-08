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
        notify("creating new wwquestion");
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
        notify("enter question: render");
        if(1 or !isset($this->_derivation)) {
            $client = WebworkClient::Get();
            $env = WebworkQuestion::DefaultEnvironment();
            #notify("create derivation env : ".print_r($env, true));
            $env->problemSeed = $seed;
            #notify("_data->code ".print_r($this->_data->code,true));
            notify("before rendering answers: ".print_r($answers, true));
            $result = $client->renderProblem($env,$this->_data->code, $answers);
            $derivation->html = base64_decode($result->output);
            #notify("derivationHTML ".$derivation->html);
            notify("after rendering answers:".print_r($answers,true));
            $derivation->seed = $result->seed;
            $this->_derivation = $derivation;
        }
        
        $orderedanswers = array();
        $tempanswers = array();
        foreach($answers as $answer) {
            notify(" answer is ".print_r($answer,true));
        	$tempanswers[$answer['field']] = $answer['answer'];
        }
        $answers = $tempanswers;
        notify("in render answers are: ".print_r($answers, true));
        $showpartialanswers = $this->_data->grading;
        $questionhtml = "";
        $parser = new HtmlParser($this->_derivation->html);
        #notify("parser html is ".print_r($this->_data->code, true));
        $currentselect = "";
        $textarea = false;
        $checkboxes = array();
        #notify("before parser");
        while($parser->parse()) {
            //change some attributes of html tags for moodle compliance
            #notify("changing attributes".print_r($parser->iNodeType). " is ". NODE_TYPE_ELEMENT);
            if ($parser->iNodeType == NODE_TYPE_ELEMENT) {
                $nodename = strtoupper($parser->iNodeName);
                if(isset($parser->iNodeAttributes['name'])) {
                    $name = $parser->iNodeAttributes['name'];
                }
                notify("node name is ".$nodename);
                notify("name is ". $name);
                //handle generic change of node's attribute name']
                if(($nodename == "INPUT") || ($nodename == "SELECT") || ($nodename == "TEXTAREA")) {
                    $parser->iNodeAttributes['name'] = 'resp' . $this->_data->question . '_' . $name;
                    #notify("changing ". $name. " to ".$parser->iNodeAttributes['name']. "value ".$parser->iNodeAttributes["value"]);
                    if(($event == QUESTION_EVENTGRADE) && (isset($answers[$name]))) {
                        if($showpartialanswers) {
                            if(isset($parser->iNodeAttributes['class'])) {
                                $class = $parser->iNodeAttributes['class'];
                            } else {
                                $class = "";
                            }
                            notify("name ".$name." answers ".print_r($answers, true));
                            $parser->iNodeAttributes['class'] = $class . ' ' . question_get_feedback_class($answers[$name]->score);
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
                            array_push($orderedanswers,$answers[$name]);
                            $parser->iNodeAttributes['value'] = $answers[$name]->answer;
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
        notify("leave question render answers are: ".print_r($orderedanswers, true) );
        return $questionhtml;
    }
    
    /**
    * @desc Grades a particular question state.
    * @param object $state The state to grade.
    * @return true.
    */    
    public function grade(&$state) {
        notify("enter grade");
        notify("state is ".print_r($state,true));
        #notify("state->responses['answers'] is ".print_r($state->responses['answers'], true));
        $seed = $state->responses['seed'];
        $answers = array();
        if((isset($state->responses['answers'])) && (is_array($state->responses['answers']))) {
            notify("there are answers");
            foreach($state->responses['answers'] as $answerobj) {
                if((is_string($answerobj->field)) && (is_string($answerobj->answer))) {
                    array_push($answers, array('field' => $answerobj->field, 'answer'=> $answerobj->answer));
                }
            }
        }
        if((isset($state->responses)) && (is_array($state->responses))) {
            notify("responses is an array, creating answers");
            notify(print_r($state->responses,true));
            foreach($state->responses as $key => $value) {
                if((is_string($key)) && (is_string($value))) {
                    array_push($answers, array('field' => $key,'answer'=>$value));
                }
            }
        }
        notify("inside grade answers is ".print_r($answers,true));
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
        foreach($checkanswers as $key => $value) {
            array_push($tempanswers,array('field' => $key,'answer' => $value));
        }
        $answers = $tempanswers;
        
        //base64 encoding
        for($i=0;$i<count($answers);$i++) {
            $answers[$i]['field'] = base64_encode($answers[$i]['field']);
            $answers[$i]['answer'] = base64_encode($answers[$i]['answer']);
        }
        
        $client = WebworkClient::Get();
        $env = WebworkQuestion::DefaultEnvironment();
        $env->problemSeed = $seed;
        #notify("enter renderProblemAndCheck with ".base64_decode($this->_data->code) );
        notify("enter renderProblemAndCheck with answers ".print_r($answers,true));
        $results = $client->renderProblemAndCheck($env,$this->_data->code,$answers);
        notify("leave renderProblemAndCheck with results". print_r($results, true) );

        //process the question
        $question = $results->problem;
        $derivation = new stdClass;
        $derivation->seed = $question->seed;
        $derivation->html = base64_decode($question->output);
        $this->_derivation = $derivation;
        $this->_data->grading = $question->grading;
        
        //assign a grade
        $answers = $results->answers;
        notify("answers: ".print_r($answers,true) );
        $state->raw_grade = $this->processAnswers($answers);
        notify("raw grade ".print_r($state->raw_grade, true));
        notify("answers after grading".print_r($answers, true));
        // mark the state as graded
        $state->event = ($state->event ==  QUESTION_EVENTCLOSE) ? QUESTION_EVENTCLOSEANDGRADE : QUESTION_EVENTGRADE;
        
        //put the responses into the state to remember
        if(!is_array($state->responses)) {
            $state->responses = array();
        }
        $state->responses['answers'] = $answers;
        notify("leave grade");
        return true;
    }
    
    /**
    * @desc Saves a question into the database.
    * @return true.
    */
    public function save() {
        notify("saving this question ");
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
        notify("question: update");
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
        notify("question: insert: ".print_r($this,true));
        $this->_data->grading=1;
        notify("insert data: ".print_r($this->_data,true));
        $dbresult = insert_record('question_webwork',$this->_data);
        if($dbresult) {
            $this->_data->id = $dbresult;
        } else {
            throw new Exception();
        }
        notify("after insert -- modified question: ".print_r($this, true));
    }
    
    /**
    * @desc Does basic processing on the answers back from the server.
    * @param array $answers The answers received.
    * @return integer The grade.
    */
    protected function processAnswers(&$answers) {
        notify("process answers");
        $total = 0;   
        for($i=0;$i<count($answers);$i++) {
            $answers[$i]->field = base64_decode($answers[$i]->field);
            $answers[$i]->answer = base64_decode($answers[$i]->answer);
            $answers[$i]->answer_msg = base64_decode($answers[$i]->answer_msg);
            $answers[$i]->correct = base64_decode($answers[$i]->correct);
            $answers[$i]->evaluated = base64_decode($answers[$i]->evaluated);
            $answers[$i]->preview = base64_decode($answers[$i]->preview);
            $total += $answers[$i]->score;            
        }
        return $total / $i;
    }    
     
//////////////////////////////////////////////////////////////////////////////////
//SETTERS
//////////////////////////////////////////////////////////////////////////////////
    
    public function setParent($id) {
        notify("set parent id to ".$id);
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
