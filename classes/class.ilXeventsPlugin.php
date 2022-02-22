<?php

include_once ("./Services/Component/classes/class.ilPlugin.php");
include_once ("./Services/EventHandling/classes/class.ilEventHookPlugin.php");
include_once ("class.ilXeventsRequest.php");

class ilXeventsPlugin extends ilEventHookPlugin{

    public $OBJ_TYPE = array(
		"adm"=>"systemFolder",
                "blog"=>"blog","bibl"=>"blibliographie","book"=>"bookingPool",
		"cld"=>"cloudObj","crsr"=>"courseRef","chtr"=>"chatroom","cat"=>"category","crs"=>"course","cmix"=>"xapi-cmi","copa"=>"contentPage","catr"=>"categoryRef",
		"dcl"=>"datacollection",
		"exc"=>"exercice",
		"frm"=>"forum","forum"=>"forum","fold"=>"folder","file"=>"file","feed"=>"externalFeed",
		"glo"=>"glossaire","grp"=>"groupe","grpr"=>"groupRef",
		"htlm"=>"doc_html",
		"iass"=>"ind_assmt","itgr"=>"objectGroup",
		"lti"=>"lti_consumer","lso"=>"learning_seq","lres"=>"moduleILIAS",
		"mcst"=>"mediacast","mob"=>"media","mep"=>"mediaPool",
		"orgu"=>"organisationUnit",
		"prg"=>"studyProgram","prgr"=>"studyProgramRef","pool"=>"vote","prtf"=>"portfolio","prtt"=>"portfolioTemplate","prfa"=>"portfolioAdmin","prtf"=>"portfolioPage","prtt"=>"portfolioTemplatePage",
		"qpl"=>"questionPool",
		"root"=>"catalogue","rcat"=>"remoteCategory","rcrs"=>"remoteCourse","rfil"=>"remoteFile","rglo"=>"remoteGlossary","rgrp"=>"remoteGroup","rlm"=>"remoteLearningModule","rtst"=>"remoteTest","rwik"=>"remoteWiki",
		"sahs"=>"scorm_aicc","sess"=>"session","svy"=>"survey","spl"=>"surveyQuestionPool",
		"tst"=>"quizz",
		"wiki"=>"wiki","webr"=>"webLink","wfld"=>"workSpaceFolder","wsrt"=>"workSpaceRootFolder"
		);

    const PLUGIN_NAME = "Xevents";

    final function getPluginName(){
	return "Xevents";
    }

    protected function init(){
    }
/********************************************************************************
*										*
*  fonction qui capture les évènements fournis par les objets d'ilias.		*
*  pour chaque evènement, une requete est envoyée ver le LRS.			*
*  params : IN 	=> $a_component : catégorie de l'évènement			*
*  		=> $a_event : évènement emis par l'objet			*
*		=> $a_parameter : array contenant les paramètres de l'event	*
*										*
*	    OUT => void								*
*********************************************************************************/
    public function handleEvent($a_component, $a_event, $a_parameter) : void {
	global $log,$DIC;
        $iluser = $DIC ['ilUser'];
	
	switch ($a_component) {
	   case "Services/Object":
		if ($a_event== "putObjectInTree"){
			$verb=array("id" => "http://w3id.org/xapi/dod-isd/verbs/created",
				    "display" => array("en-US"=>"Created","fr-FR"=>"Crée")
				);
			$log->write("logXapi--Obj_id=".$a_parameter['obj_id']."--obj_type=".$a_parameter['obj_type']."--parent=".$a_parameter['parent_ref_id']."--verb=".$a_event);
			$request = new ilXeventsRequest();
			$request->sendStatement($this->getActor($iluser->getId()),
						$verb,
						$this->getObject($a_parameter['object'],$a_parameter['parent_ref_id']),
						$this->getContext($a_parameter['parent_ref_id']),
						null
						);
		}
	   case "Modules/Course":
	//	if ($a_event== "create" or $a_event=="update" or $a_event=="delete"){
	/*	if ($a_event== "create"){
			$log->write("logXapi--own_id=".$object_owner."--Obj_id".$a_parameter['obj_id']."--Ref_id=".$object_ref_id."--type=".$object_type."--titre=".$object_title."--verb=".$a_event);
		}*/
		if ($a_event== "addParticipant" or $a_event=="deleteParticipant"){
			$log->write("logXapi--obj_id=".$a_parameter['obj_id']."--User_id=".$iluser->getLogin()."--verb=".$a_event);
		}
		break;
           case "Services/Tracking":
		if ($a_event=="updateStatus"){
			$obj_type = $this->OBJ_TYPE[$this->getObjDesc($this->getRefId($a_parameter['obj_id']))['type']];
			$obj_titre= $this->getObjDesc($this->getRefId($a_parameter['obj_id']))['title'];
			$obj_desc = $this->getObjDesc($this->getRefId($a_parameter['obj_id']))['description'];

			$log->write("logXapi--obj_id=".$a_parameter['obj_id']."--Usr_id=".$a_parameter['usr_id']."--oldStatus=".$a_parameter['old_status']."--NewStatus=".$a_parameter['status']."--verb=".$a_event);

			if (($a_parameter['status']==3 and $obj_type=="quizz")or($a_parameter['status']==2 and $a_parameter['old_status']==0)or($a_parameter['status']==2 and $a_parameter['old_status']==1)or($a_parameter['status']==2 and $a_parameter['old_status']==3)){
			   	$log->write('===========================================');
				if ($obj_type=="quizz"){
				   $verb=array("id" => "http://adlnet.gov/expapi/verbs/attempted",
                                               "display" => array("en-US"=>"Attempted","fr-FR"=>"tenté")
                                              );
				   $result = $this->getTestResult($a_parameter['usr_id'],$a_parameter['status']);
				   $registration = (new \Ramsey\Uuid\UuidFactory())->uuid4()->toString();
				// envoi de toutes les statements représentant les réponses 
				   $this->sendAllResponses($a_parameter['usr_id'],$a_parameter['obj_id']);
				}else{
				   $verb=array("id" => "http://adlnet.gov/expapi/verbs/completed",
                                   	       "display" => array("en-US"=>"Completed","fr-FR"=>"Validé")
                                   	      );
				   $result = array("completion"=>true);
				   $registration=null;
				}
				$obj=array("objectType" => "Activity",
 					   "id" => "http://eform.intradef.gouv.fr/".$obj_type."/objId-".$a_parameter['obj_id'],
  				 	   "definition" => array("type" => "http://vocab.xapi.fr/activities/".$obj_type,
                        		 			 "name" => array("fr"=>$obj_titre),
                                 				 "description" => array("fr"=>$obj_desc),
                                 				 "extensions" => array("http://vocab.xapi.fr/extensions/platform-concept" => $obj_type)
                                    	   		   )
      		   		   	   );
				$request = new ilXeventsRequest();
                        	$request->sendStatement($this->getActor($a_parameter['usr_id']),
                                	                $verb,
                                        	        $obj,
							$this->getContext($this->getParent($this->getRefId($a_parameter['obj_id'])),$registration),
                                               	 	$result
                                               		);
			   }



		}
	   case "Services/Authentication":
		if ($a_event=="afterLogin"){
			$act =array("objectType" => "Agent",
                       		    "account" => array("homePage" => "http://eform.intradef.gouv.fr",
                                    "name" => $a_parameter['username']));
			$verb=array("id" => "http://w3id.org/xapi/verbs/logged-in",
                                    "display" => array("en-US"=>"Connected","fr-FR"=>"Connecte")
                                );
			$s_obj=array("objectType" => "Activity",
				     "definition" => array("description" => array("fr-FR"=>"Portail e-form"),
				     			   "type" =>"http://id.tincanapi.com/activitytype/lms",
							   "name" => array("fr-FR" =>"E-Form Intradef")
							  ),
				     "id" => "http://emm/dpmm/form/ppm/eform_intradef"
				     );
			$log->write("logXapi--user=".$a_parameter['username']."--verb=logged in");
			$request = new ilXeventsRequest();
                        $request->sendStatement($act,$verb,$s_obj,null,null);
		}
	   case "Modules/Forum":
		if($a_event=="createdPost"){
			$forum_id = $this->getForumId($a_parameter['post']->getForumId());
			$verb=array("id" => "https://w3id.org/xapi/dod-isd/verbs/created",
				    "display" => array("fr-FR"=>"crée"));
			$log->write("logXapi--user=".$a_parameter['username']."--verb=create(post)---".$forum_id);
                        $request = new ilXeventsRequest();
                        $request->sendStatement($this->getActor(),$verb,$this->getPost($a_parameter['post']),$this->getContext($this->getRefId($forum_id)),null);
		}
	   default:
	        break;
	}
    }

/* retourne le ref_id du parent */
    private function getParent($ref_id){
    	global $DIC,$log;
	$ilDB=$DIC->database();
	$q=$ilDB->query("SELECT parent FROM tree WHERE child=".$ref_id);
	$s=$ilDB->fetchAssoc($q);
	return $s['parent'];
    }

/********************************************************************************/
/*                                                                              */
/* fonction destinée à récupérer l'obj_id du forum dans lequel un post est écrit*/
/*                                                                              */
/* params : IN => $id : référence du forum dans lequel le post est écrit        */
/*                                                                              */
/*          OUT => integer : identifiant du forum (obj_id) dans ILIAS	        */
/*                                                                              */
/********************************************************************************/
    private function getForumId($id){
	global $DIC;
	$ilDB = $DIC->database();
	$r=$ilDB->query("SELECT top_frm_fk FROM frm_data WHERE top_pk=".$id);
	$row=$ilDB->fetchAssoc($r);
	$obj_id = $row['top_frm_fk'];
	return $obj_id;
    }

/********************************************************************************/
/*                                                                              */
/* fct destinée à récupérer le ref_id d'un objet ILIAS en entrant son obj_id	*/
/*                                                                              */
/* params : IN => $obj_id : $obj_id de l'objet	 			        */
/*                                                                              */
/*          OUT => integer : $ref_id de l'objet  dans ILIAS		        */
/*                                                                              */
/********************************************************************************/
    private function getRefId($obj_id){
        global $DIC;
        $ilDB = $DIC->database();
        $r=$ilDB->query("SELECT object_reference.ref_id FROM object_reference,object_data WHERE object_reference.obj_id=object_data.obj_id and object_data.obj_id=".$obj_id);
        $row=$ilDB->fetchAssoc($r);
        $ref_id = $row['ref_id'];
        return $ref_id;
    }

/********************************************************************************/
/*                                                                              */
/* fonction destinée à construire un objet xapi spécifique à la rédaction d'un  */
/* post dans un forum								*/
/*                                                                              */
/* params : IN => $obj : objet ILIAS qui a emis l'évènement (le post)           */
/*                                                                              */
/*          OUT => array xapi correspondant à la partie Object du statement     */
/*                                                                              */
/********************************************************************************/
    protected function getPost($post){
	global $log;
	$log->write("----------".$post->getId()."---".$post->getSubject());
	$xapiObject=array("objectType"=>"Activity",
                          "id"=>"http://eform.intradef.gouv.fr/activity/forum/objId-".$post->getId(),
                          "definition" => array("type" => "http://id.tincanapi.com/activitytype/forum-topic",
                                                "name" => array("fr"=>"Sujet de forum"),
                                                "description" => array("fr"=>$post->getSubject()))
                                        );


	return $xapiObject;
    }

/********************************************************************************/
/*                                                                              */
/* fonction destinée à construire l'acteur du statement xapi			*/
/*                                                                              */
/* params : IN => aucun                     					*/
/*                                                                              */
/*          OUT => array xapi correspondant à la partie actor du statement      */
/*                                                                              */
/********************************************************************************/
    protected function getActor($usrId){
        global $log,$DIC;
        $ilDB = $DIC->database();
	$q = $ilDB->query("SELECT login,email FROM usr_data WHERE usr_id=".$usrId);
	$set = $ilDB->fetchAssoc($q);
        $actor  = array("objectType" => "Agent",
		        "account" => array("homePage" => "http://eform.intradef.gouv.fr",
					   "name" => $set['login'])
		       );
	return $actor;

    }

/********************************************************************************/
/*                                                                              */
/* fonction destinée à construire un objet xapi en fonction de l'élément passé  */
/* en paramètre. 								*/
/* 								                */
/* params : IN => $obj : objet ILIAS qui a emis l'évènement			*/
/*             							                */
/*          OUT => array xapi correspondant à la partie Object du statement     */
/*                                                                              */
/********************************************************************************/
    protected function getObject($obj, $parent=null){
	global $log;
        $log->write(" ---------- getObject -----------");
	switch ($obj->type) {
	   case "file" :
		$log->write("--------- case file ------------");
		$title = $obj->getFileName();
		$act_type = $obj->type;
		$description = "Fichier";
		break;
	   case "frm" :
 	   case "blog" :
	   case "crs" :
	   case "wiki":
	   case "grp":
	   case "html":
	   case "exe":
	   case "tst":
	   case "glo":
	   case "iass":
	   case "mcst":
	   case "lso":
	   case "sahs":
	   //case "lti":
	   //case "cmix":
                $log->write("--------- case ".$obj->getType()." ------------");
		if ($obj->getType()=="blog"){$act_type = "blog";}
		if ($obj->getType()=="forum"){$act_type = "forum";}
		if ($obj->getType()=="crs"){$act_type = "course";}
		if ($obj->getType()=="wiki"){$act_type = "wiki";}
		if ($obj->getType()=="grp"){$act_type = "groupe";}
		if ($obj->getType()=="html"){$act_type = "doc_html";}
		if ($obj->getType()=="exe"){$act_type = "exercice";}
		if ($obj->getType()=="tst"){$act_type = "quizz";}
		if ($obj->getType()=="glo"){$act_type = "glossaire";}
		if ($obj->getType()=="iass"){$act_type = "ind_assmt";}
		if ($obj->getType()=="mcst"){$act_type = "mediacast";}
		if ($obj->getType()=="cmix"){$act_type = "xapi-cmi";}
		if ($obj->getType()=="sahs"){$act_type = "scorm_aicc";}
		if ($obj->getType()=="lti"){$act_type = "lti_consumer";}
		if ($obj->getType()=="lso"){$act_type = "learning_seq";}
		$title = $obj->getTitle();
		$description = $obj->getDescription();
	   	break;
	   default:
		$log->write("-------- case other ".$obj->getType()."--------");
	        $act_type = "other";
		$title = $obj->getTitle();
		$description = $obj->getDescription();
		break;
	}

	$id = $obj->getId();
	//$act_type = $obj->getType();
	$xapiObj=array("objectType" => "Activity",
                       "id" => "http://eform.intradef.gouv.fr/".$act_type."/objId-".$id,
                       "definition" => array("type" => "http://vocab.xapi.fr/activities/".$act_type,
                                             "name" => array("fr"=>$title),
                                             "description" => array("fr"=>$description),
                                             "extensions" => array("http://vocab.xapi.fr/extensions/platform-concept" => $act_type)
                                             )
                       );
        return $xapiObj;
    }

/********************************************************************************/
/*										*/
/* fonction destinée à récupérer le context du statement. Ce context précise 	*/
/* (entre autre) le parent dans lequel est inséré l'objet xapi.			*/
/* params : IN => $parent => ref_id de l'objet					*/
/*          OUT => array xapi							*/
/*										*/
/********************************************************************************/

    private function getContext($parent,$registration=null){
    	global $log, $DIC;
	$ilDB = $DIC->database();
	$r=$ilDB->query("SELECT object_data.type,object_data.obj_id FROM object_data,object_reference WHERE object_data.obj_id=object_reference.obj_id AND ref_id=".$parent."");
	$row= $ilDB->fetchAssoc($r);
	$str_type = $this->OBJ_TYPE[$row['type']];
	$r = $ilDB->query("SELECT path FROM tree WHERE child = ".$parent);
	$row = $ilDB->fetchAssoc($r);
        $items = explode(".",$row['path']);
        $grp=array();
	foreach ($items as $v) {
		//if ($v!=$parent) {
			$grp[]=array("id"=>"http://eform.intradef.gouv.fr/activity/obj_id-".$this->getObjDesc($v)['obj_id'],"definition"=>array("name"=>array("fr"=>"".$this->getObjDesc($v)['title']."")));
                //}
        }
        if (isset($registration)){
	   $xapiContext = array("registration"=>$registration,"contextActivities"=>array("parent" => [
                  						   array("id"=>"http://eform.intradef.gouv.fr/activity/".$str_type."/obj_Id-".$row['obj_id'],
                        		      			         "definition" => array("type"=>"http://vocag.xapi.fr/activities/".$str_type))
								   ],
							      "grouping" =>$grp
						              )
                                );
	}else{
	   $xapiContext = array("contextActivities"=>array("parent" => [
                                                                array("id"=>"http://eform.intradef.gouv.fr/activity/".$str_type."/obj_Id-".$row['obj_id'],
                                                                "definition" => array("type"=>"http://vocag.xapi.fr/activities/".$str_type))
                                                                  ],
                                                        "grouping" =>$grp
                                                       )
                             );
	}
	return $xapiContext;

    }

    protected function getObjDesc($ref_id){
	global $log,$DIC;
$log->write("dans getObjDesc *************ref_id = ".$ref_id."************* ");
	$ilDB = $DIC->database();
	$set = $ilDB->query("SELECT object_data.obj_id,object_data.type,object_data.title,object_data.description FROM object_data,object_reference WHERE object_data.obj_id=object_reference.obj_id AND ref_id=".$ref_id);
	$row = $ilDB->fetchAssoc($set);
$log->write("type=".$row['type']."---titre=".$row['title']."---descr".$row['description']);
	return $row;
    }

    public function iso_8601_duration($seconds){
	$days = floor($seconds / 86400);
	$seconds = $seconds % 86400;
	$hours = floor($seconds / 3600);
	$seconds = $seconds % 3600;
	$minutes = floor($seconds / 60);
	$seconds = $seconds % 60;

	return sprintf('P%dDT%dH%dM%dS' , $days , $hours , $minutes , $seconds);

	}

    private function getTestResult($userId,$status){
	global $DIC,$log;
	$ilDB=$DIC->database();
	$req = $ilDB->query("SELECT tst_active.active_id,tst_pass_result.points,tst_pass_result.maxpoints,tst_pass_result.workingtime FROM tst_active,tst_pass_result WHERE tst_active.active_id=tst_pass_result.active_fi AND tst_active.user_fi=".$userId);
	while ($set = $ilDB->fetchAssoc($req)){
	   $pts[] = $set['points'];
           $maxPts[] =$set['maxpoints'];
	   $rawDuration[] =  $set['workingtime'];
	   }
	$rec = count($pts)-1;
	$minPts = 0;
	$log->write("--- pts:".$pts[$rec]."--max".$maxPts[$rec]."--duration".$rawDuration[$rec]."--ligne".$rec);
	$scaled = $pts[$rec]/$maxPts[$rec];
	$raw = (int)$pts[$rec];
	$tps = $rawDuration[$rec];
	$duration = $this->iso_8601_duration($tps);
	if ($status==2){$success=true;}else{$success=false;}

	$result = array("success"=>$success,"score"=>array("max"=>(int)$maxPts[$rec],"min"=>$minPts,"raw"=>(int)$raw,"scaled"=>$scaled),"duration"=>$duration);
	return $result;
    }


      private function getAnswer($usr_id,$tst_id,$qpl_id,$qpl_type,$qpl_text) {
	global $DIC,$log;
	$tables = array(1=>"qpl_a_sc",2=>"qpl_a_mc",3=>"qpl_a_cloze",4=>"qpl_a_matching",5=>"qpl_a_ordering",6=>"qpl_a_imagemap");
	$ilDB = $DIC->database();
	//selection des réponses possibles dans une question
	$q = $ilDB->query("SELECT *  FROM ".$tables[$qpl_type]." WHERE question_fi=".$qpl_id);
	$n=0;
	while ($set = $ilDB->fetchAssoc($q)){

	   switch ($qpl_type){
		case 1 :
		case 2 :
			//if ($n>1){$choice[]="[,]";}
			$log->write("*************".$set['answertext']."****".$qpl_id);
		        $choices[]=array("id"=>$set['answertext']);
          		//$n=$n+1;
			$definition = array("description"=>$qpl_text,
                     			    "type"=>"http://adlnet.gov/expapi/activities/cmi.interaction",
                     			    "interaction_type"=>"choice",
                     			    "correctResponsesPattern"=>[],
		     			    "choices"=>$choices
					   );
			break;
		case 4 :
			$definition = array("description"=>$qpl_text,
                                            "type"=>"http://adlnet.gov/expapi/activities/cmi.interaction",
                                            "interaction_type"=>"matching",
                                            "correctResponsesPattern"=>[],
                                            "source"=>[],
					    "target"=>[]
                                           );
                        break;
		case 9 :
			$definition = array("description"=>$qpl_text,
                                            "type"=>"http://adlnet.gov/expapi/activities/cmi.interaction",
                                            "interaction_type"=>"numeric",
                                            "correctResponsesPattern"=>[]
                                           );
                        break;
		case 5 :
		case 13 :
			$definition = array("description"=>$qpl_text,
                                            "type"=>"http://adlnet.gov/expapi/activities/cmi.interaction",
                                            "interaction_type"=>"sequencing",
                                            "correctResponsesPattern"=>[],
                                            "choices"=>[]
                                           );
                        break;
		case 18 :
			$definition = array("description"=>$qpl_text,
                                            "type"=>"http://adlnet.gov/expapi/activities/cmi.interaction",
                                            "interaction_type"=>"linkert",
                                            "correctResponsesPattern"=>[],
                                            "scale"=>[]
                                           );
                        break;
		default :
			$definition = array("description"=>$qpl_text,
                                            "type"=>"http://adlnet.gov/expapi/activities/cmi.interaction",
                                            "interaction_type"=>"other",
                                            "correctResponsesPattern"=>[]
                                           );
	  } 
	}
$log->write("-----------------------");
foreach ($definition['choices'] as $v){$log->write("-------".$v[0]['id']);}
$log->write($definition['description']."---".$definition['type']."---".$definition['choices'][0]['id']);
$log->write("-----------------------");

	$verb = array("id"=>"http://adlnet.gov/expapi/verbs/answered","display"=>array("fr-FR"=>"Répondu"));
	$obj = array("id"=>"http://eform.intradef.gouv.fr/activity/test/question/qplId-".$qpl_id,
		     "definition"=>$definition,
		     "objectType"=>"Activity"
		    );

	$response = array($this->getActor($usr_id),$verb,$obj,$result,$context);
	return $response;
    }

    private function sendAllResponses($usr_id,$obj_id) {
	global $log,$DIC;
	$ilDB = $DIC->database();
	// récup de l'active_id du test
	//$q=$ilDB->query("select tst_active.active_id from tst_active,tst_tests where tst_active.test_fi=tst_tests.test_id and tst_tests.obj_fi=".obj_id." and tst_active.user_fi="$usr_id.);
	//$set= $ilDB->fetchAssoc($q);
	//$act_id = $set['active_id'];
	// recupère toutes les questions du test
	$q = $ilDB->query("SELECT qpl_questions.*,tst_tests.test_id FROM qpl_questions,tst_tests WHERE qpl_questions.obj_fi=tst_tests.obj_fi AND tst_tests.obj_fi=".$obj_id." AND qpl_questions.original_id is null"); 

	// Envoi d'un statement pour chaque réponse
	while ($set = $ilDB->fetchAssoc($q)){
		$log->write("dans sendAllResponse");
		$this->getAnswer($usr_id,$set['test_id'],$set['question_id'],$set['question_type_fi'],$set['title']);
	}
    }
}
?>

