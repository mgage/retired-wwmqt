<?php
/**
 * The WebworkClient Class.
 * 
 * @copyright &copy; 2008 Matthew Leventi
 * @author mleventi@gmail.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package webwork_qtype
**/


/**
* @desc Singleton class that contains function for communication to the WeBWorK Question Server.
*/
class WebworkClient {
    
    /**
    * @desc Gets a single instance of WebworkClient. Constructs the instance if it doesn't exist.
    * @return WebworkClient instance.
    */
    public static function Get() {
        static $client = null;
        if ($client == null) {
            $client = new WebworkClient();
        }
        return $client;
    }
    
    private $_client = null;
    
    /**
    * @desc Private Constructor that creates a PHP::SOAP client.
    */
    private function __construct()
    {
        $this->_client = new SoapClient(WWQUESTION_WSDL);
    }
    
    /**
    * @desc Calls the server function renderProblem.
    * @param object $env The problem environment.
    * @param string $code The base64 encoded PG code.
    * @return mixed The results of the server call.
    */
    public function renderProblem($env,$code,$answers=array()) {
        $problem = new stdClass;
        $problem->code = $code;
        $problem->id = $env->studentLogin;
        $problem->seed = $env->problemSeed;
        $problem->displayMode = $env->displayMode;
        $problem->answers = $answers;
  
        $problem->env = $env;
        notify("renderProblem");
        try {
            $results = $this->_client->renderProblem($problem);
        } catch (SoapFault $exception) {
            print_error('error_soap','qtype_webwork',$exception);
            return false;
        }
        return $results;
        
    }
    
    /**
    * @desc Calls the server function renderProblem.
    * @param object $env The problem environment.
    * @param string $code The base64 encoded PG code.
    * @param array $answers The array of answers.
    * @return mixed The results of the server call.
    */
    public function renderProblemAndCheck($env,$code,$answers) {
        $problem = new stdClass;
        $problem->code = $code;
        $problem->env = $env;
        notify("renderProblemAndCheck");
        try {
            # $response = $this->_client->renderProblemAndCheck($problem,$answers);
            $response = $this->_client->renderProblem($env,$code,$answers);
        } catch (SoapFault $exception) {
            print_error('error_soap','qtype_webwork',$exception);
            return false;
        }
        return $response;
    }
}

?>
