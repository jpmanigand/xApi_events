<?php
include_once "class.ilXeventsException.php";

const LRS_ENDPOINT= "http://ll-lrs.local.com/data/xAPI/";
const LRS_KEY ="f30a5d8df5faac7def4b239715e61d2fcdf1547d";
const LRS_PASSWD = "d904ddc6af3466df32ad09861458279e7795386c";
const LRS_BASIC_AUTH = "Basic ZjMwYTVkOGRmNWZhYWM3ZGVmNGIyMzk3MTVlNjFkMmZjZGYxNTQ3ZDpkOTA0ZGRjNmFmMzQ2NmRmMzJhZDA5ODYxNDU4Mjc5ZTc3OTUzODZj";


use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\EntityBody;
use GuzzleHttp\Event\CompleteEvent;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

class ilXeventsRequest {

protected $log;

   public function __construct() {
	$this->log = ilLoggerFactory::getLogger('xevt');
	$this->endpoint = LRS_ENDPOINT;
	$this->key = LRS_KEY;
	$this->password = LRS_PASSWD;
	$this->token =  base64_encode($this->key.":".$this->password);
 }

   public function sendStatement($act,$verb,$obj,$context=null,$result=null){
	global $log;
	$client = new Client();

	if ($context==null and $result!=null){
		$data = json_encode(["actor"=>$act,"verb"=>$verb,"object"=>$obj,"result"=>$result]);
	}else if ($result==null and $context==null){
		$data = json_encode(["actor"=>$act,"verb"=>$verb,"object"=>$obj]);
	}else if ($result==null and $context!=null){
		$data = json_encode(["actor"=>$act,"verb"=>$verb,"object"=>$obj,"context"=>$context]);
	}else{
		$data = json_encode(["actor"=>$act,"verb"=>$verb,"object"=>$obj,"context"=>$context,"result"=>$result]);
	}


	$request = new Request('POST',$this->endpoint.'statements',$this->getHeaders(),$data);
	try{
		$promise = $client->sendAsync($request);
		$response = $promise->wait();
		if ($response->getStatusCode() == 200){
			$log->write("---------- Statement transmit ---------- ");
			ilUtil::sendSuccess("Trace d'apprentissage transmise au LRS",true);
		}
	   }
	catch(Exception $e){
		$this->log->error('error: '.$e->getMessage());
		switch ($e->getCode()){
			case 401:
			   $msg = "Echec authentification LRS (401)";
			   break;
			case 404:
			   $msg = "Erreur serveur - mauvaise url (404)";
		 	   break;
			case 400;
			   $msg = "Requete xApi mal structurée (400)";
		  break;}
		  ilUtil::sendFailure($msg,true);
	   }
   }

   private function getHeaders(){
	return ["Authorization" => "Basic ".$this->token,
		"X-Experience-API-Version" => "1.0.3",
		"content-Type" => "application/json",
		//"content-lenght" => strlen($body),
		"Accept" => '*/*'];
   }
}
?>
