<?php
include_once 'Services/Exceptions/classes/class.ilException.php';

class ilXeventsException extends ilException {
const UNAUTHORIZED = 1000;
protected $message;
protected $code;
protected $add_info;


   public function __construct($a_message, $a_code = 0){
	$this->code = $a_code;
	$this->message = $a_message;
	$this->assignMessageToCode();
	parent::__construct($this->message, $this->code);
	}
 
   private function assignMessageToCode(){
	global $DIC;
	$lng = $DIC['lng'];
	switch ($this->code){
		case self::UNAUTHORIZED:
			$this->message="Echec de l'autorisation";
			break;
	   }
        }
}
?>
