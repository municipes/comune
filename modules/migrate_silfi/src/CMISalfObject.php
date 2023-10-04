<?php

namespace Drupal\migrate_silfi;

//OBJECT class is an extension of the ABOVE REPO
class CMISalfObject extends CMISalfRepo
{

	//some properties accessible from the program after loading object
	public $properties=array();
	public $aspects=array();
	public $loaded=FALSE;
	public $objId;
	public $contentUrl;
	public $selfUrl;
	public $childrenUrl;
	public $parentUrl;
	public $editUrl;

	//variables for query
	public $maxItems=100; //number of results to be fetched
	public $skipCount=0; //initial skip results
	private $queryResult;
	public $num_rows=0;
	private $fetchPosition=0; //position for queryresult->fetch_array()

	//A complete list of all contained objects with their properties and aspects
	//Must be initialized with listContent() method.
	public $containedObjects=array();

function __construct($url, $username = null, $password = null,$objId = null,$objUrl=null,$objPath=null){
	$this->url=$url;
	$this->connect($url, $username, $password);
	$this->username=$username;
	$this->password=$password;
	if($objUrl){
		$this->loadCMISObject(null,$objUrl);
	}
	else if($objId){
		$this->loadCMISObject($objId);
	}
	else if($objPath){
		$this->loadCMISObject(null,null,$objPath);
	}
	else return FALSE;
}


//Loads object by ID or by URL with PROPERTIES and ASPECTS
function loadCMISObject($objId=null,$objUrl=null,$objPath=null){
	$this->objId=$objId;
	$this->objUrl=$objUrl;

	//	print_r($this->uritemplates);

	//BE CAREFUL to access objects directly by their SELF url or PATH....
	if($objUrl){
		$reply=$this->getHttp($objUrl,$this->username,$this->password);
	}
	else if($objPath){
		$urltemplate=$this->uritemplates['objectbypath'];
		$url=str_replace("{path}",urlencode($objPath),$urltemplate);
		//dirty method for removing unused {templates} in the url
		$url=str_replace("{","<",$url);
		$url=str_replace("}",">",$url);
		$url=strip_tags($url);
		$reply=$this->getHttp($url,$this->username,$this->password);
	}
	else {
		//Get object info under ENTRY
		$urltemplate=$this->uritemplates['objectbyid'];
		$url=str_replace("{id}",urlencode($objId),$urltemplate);
		//dirty method for removing unused {templates} in the url
		$url=str_replace("{","<",$url);
		$url=str_replace("}",">",$url);
		$url=strip_tags($url);
		$reply=$this->getHttp($url,$this->username,$this->password);
	}
	//FAILED http request - no need to go on
	if($reply==FALSE){
		$this->loaded=FALSE;
		return FALSE;
	}

	$objdata=simplexml_load_string($reply);

	//very complex handling of different namespaces returned;
	//ATOM->CMISRA-> CMIS -> ASPECTS
	$atom=$objdata->children($this->namespaces['atom']);
	$this->namespaces=$objdata->getNameSpaces(true);
	$cmisra=$objdata->children($this->namespaces['cmisra']);
	$cmis=$cmisra->children($this->namespaces['cmis']);
	$this->cmisobject=$cmis;
	$this->loaded=TRUE;
	if($atom->content)$this->contentUrl=(string)$atom->content->attributes()->src;//useful for downloading content

	if($objdata->link)$links=$objdata->link;
	//On the new alfresco is under ATOM namespace
	else if($atom->link)$links=$atom->link;

	foreach($links as $link){
	$rel= $link->attributes()->rel;
	$href= $link->attributes()->href;
	$type= $link->attributes()->type;
/*		//for debugging list LINKS
		echo "\n================\n";
		echo "REL $rel\n";
		echo "HREF $href\n";
		echo "TYPE $type\n";

*/
		if($rel=='up')$this->parentUrl=$href;
		//not to be confused with descendant
		if($rel=='down' && $type=="application/atom+xml;type=feed")
			$this->childrenUrl=$href;
		if($rel=='edit')$this->editUrl=$href;
		if($rel=='self')$this->selfUrl=$href;

	}

	//Getting object PROPERTIES within different attributes
	//PropertyString
	for($x=0;$x<count($cmis->properties->propertyString);$x++){
		$propertyDefinitionId=$cmis->properties->propertyString[$x]->attributes()->propertyDefinitionId;
		$value=(string)$cmis->properties->propertyString[$x]->value;
		$this->properties["$propertyDefinitionId"]=$value;
	}
	//PropertyId
	for($x=0;$x<count($cmis->properties->propertyId);$x++){
		$propertyDefinitionId=$cmis->properties->propertyId[$x]->attributes()->propertyDefinitionId;
		$value=(string)$cmis->properties->propertyId[$x]->value;
		$this->properties["$propertyDefinitionId"]=$value;
	}
	//PropertyDateTime
	for($x=0;$x<count($cmis->properties->propertyDateTime);$x++){
		$propertyDefinitionId=$cmis->properties->propertyDateTime[$x]->attributes()->propertyDefinitionId;
		$value=(string)$cmis->properties->propertyDateTime[$x]->value;
		$this->properties["$propertyDefinitionId"]=$value;
	}
	//PropertyBoolean
	for($x=0;$x<count($cmis->properties->propertyBoolean);$x++){
		$propertyDefinitionId=$cmis->properties->propertyBoolean[$x]->attributes()->propertyDefinitionId;
		$value=(string)$cmis->properties->propertyBoolean[$x]->value;
		$this->properties["$propertyDefinitionId"]=$value;
	}
	//Getting object ASPECTS
	//Driving me crazy with NESTED NAMESPACES :-)
	//Moreover different implementation for Alfresco 3.x aspects namespace=alf
	//Moreover different implementation for Alfresco 5 aspects namespace=e1
	if(isset($this->namespaces['aspects']))$aspectsdata=$cmis->children($this->namespaces['aspects'])->aspects;
	//different implementation for Alfresco 5 aspects namespace=e1
	else if(isset($this->namespaces['e1']))$aspectsdata=$cmis->children($this->namespaces['e1'])->aspects;

	else $aspectsdata=$cmis->children($this->namespaces['alf'])->aspects;
	for($x=0;$x<count($aspectsdata->properties);$x++){
		$cmisprop=$aspectsdata->properties[$x]->children($this->namespaces['cmis']);
		if($n=count($cmisprop)){
			for($k=0;$k<$n;$k++){
				$propertyDefinitionId=$cmisprop[$k]->attributes()->propertyDefinitionId;
				$value=(string)$cmisprop[$k]->value;
				if($value)$this->aspects["$propertyDefinitionId"]=$value;
			}
		}
	}
	$this->objId=$this->properties['cmis:objectId'];
	return TRUE;
}

//Initializes content array (for easy access to the contained objects)
//Useful for browsing a folder in a repo
public function listContent(){
	//note the check on baseTypeId instead of objectId in order to include sites....
	if($this->properties['cmis:baseTypeId']<>"cmis:folder"){
		//NOT A FOLDER!!!!
		return FALSE;
	}
 	$newurl=$this->childrenUrl;
	$reply=$this->getHttp($newurl,$this->username,$this->password);
	$objdata=simplexml_load_string($reply);

	$this->namespaces=$objdata->getNameSpaces(true);
	//LOADING atom NAMESPACE
	//Different for Alfresco 3.x (no atom namespace)
	if(isset($this->namespaces['atom']))$atom=$objdata->children($this->namespaces['atom']);
	else $atom=$objdata->children();
	//LOOKING FOR ENTRIES
	$entry=$atom->entry;
	//How many entries?
	for($x=0;$x<count($entry);$x++){
		$ent=$entry[$x];
		$link=$ent->link;
		foreach($ent->link as $link){
			//resolve all links looking for SELF
			$rel= $link->attributes()->rel;
			$href= $link->attributes()->href;
			if($rel=="self")$objUrl=$href;
		}
		$tempdoc[$x]=new CMISalfObject($this->url,$this->username,$this->password,null,$objUrl);
		$this->containedObjects[$x]->objUrl = $objUrl;
		$this->containedObjects[$x]->author=(string)$ent->author->name;
		$this->containedObjects[$x]->title=(string)$ent->title;
		if($ent->content)$this->containedObjects[$x]->content=(string)$ent->content->attributes()->src;//useful for downloading content
		$this->containedObjects[$x]->properties=$tempdoc[$x]->properties;
		$this->containedObjects[$x]->aspects=$tempdoc[$x]->aspects;
	}
}


//Initializes content array (for easy access to the contained objects)
//Useful for browsing a folder with a lot of contents in a repo
//The difference from the above is it doesn't initialize an object for any contained object
//but it doesn't return a complete list of properties for any contained object
//It is useful in folder with a lot of documents since it is CONSIDERABLY faster....
public function quickListContent(){
	//note the check on baseTypeId instead of objectId in order to include sites....
	if($this->properties['cmis:baseTypeId']<>"cmis:folder"){
		//NOT A FOLDER!!!!
		return FALSE;
	}
 	$newurl=$this->childrenUrl;
	$reply=$this->getHttp($newurl,$this->username,$this->password);
	$objdata=simplexml_load_string($reply);
/*	if(!$objdata=simplexml_load_string($reply)){
		echo "INVALID XML OBJECT\n\n$reply\n\n";
		die();
		return FALSE;
	}*/
	$this->namespaces=$objdata->getNameSpaces(true);
	//LOADING atom NAMESPACE
	//Different for Alfresco 3.x (no atom namespace)
	if(isset($this->namespaces['atom']))$atom=$objdata->children($this->namespaces['atom']);
	else $atom=$objdata->children();
	//LOOKING FOR ENTRIES
	$entry=$atom->entry;
	//How many entries?
	for($x=0;$x<count($entry);$x++){
		$ent=$entry[$x];
		$link=$ent->link;
		foreach($ent->link as $link){
			//resolve all links looking for SELF
			$rel= $link->attributes()->rel;
			$href= $link->attributes()->href;
			if($rel=="self")$objUrl=$href;
			if($rel=="describedby"){
				if(strpos($href,"cmis:folder"))
					$objType="cmis:folder";
				else
					$objType="cmis:document";
			}

		}
		$this->containedObjects[$x]->objUrl=(string)$objUrl;
		$this->containedObjects[$x]->author=(string)$ent->author->name;
		$this->containedObjects[$x]->title=(string)$ent->title;
		$this->containedObjects[$x]->type=$objType;
		if($ent->content)$this->containedObjects[$x]->content=(string)$ent->content->attributes()->src;//useful for downloading content
	}
}


//RETURNS the MIME content of the current object
public function getContent(){
	$url=$this->contentUrl;
	return $this->getHttp($url, $this->username, $this->password);
}

//DOWNLOADS and saves the MIME content of the current object
public function download(){
	$url=$this->contentUrl;
	$content=$this->getHttp($url, $this->username, $this->password);
	if(!$content)return FALSE;
	//note filename=object name.
	//take care of the filename extension under windows :-)
	$name=$this->properties['cmis:name'];
	$fp=fopen($name,"wb");
	if(!$fp)return FALSE;
	fwrite($fp,$content);
	fclose($fp);
	return $name;
}

//CREATES a new folder into a folder object
//RETURNS new object id
public function createFolder($foldername){
	//note the check on baseTypeId instead of objectId in order to include sites....
	if($this->properties['cmis:baseTypeId']<>"cmis:folder"){
		//NOT A FOLDER!!!!
		return FALSE;
	}
	//ESCAPE & char
	$foldername=str_replace("&","&#038;",$foldername);
	//ATOM FEED for new folder
	$inquiry="<?xml version='1.0' encoding='UTF-8'?>
<atom:entry
        xmlns:atom=\"http://www.w3.org/2005/Atom\"
        xmlns:cmis=\"http://docs.oasis-open.org/ns/cmis/core/200908/\"
        xmlns:cmisra=\"http://docs.oasis-open.org/ns/cmis/restatom/200908/\"
        xmlns:app=\"http://www.w3.org/2007/app\">
<atom:title>$foldername</atom:title>
<cmisra:object>
        <cmis:properties>
                <cmis:propertyId queryName=\"cmis:objectTypeId\" displayName=\"Object Type Id\" localName=\"objectTypeId\" propertyDefinitionId=\"cmis:objectTypeId\">
                <cmis:value>cmis:folder</cmis:value>
                </cmis:propertyId>
        </cmis:properties>
</cmisra:object>
</atom:entry>
";
	$url=$this->childrenUrl;
	$result=$this->postHttp($url,$this->username,$this->password,$inquiry);
	return $this->getObjectId($result);
}

//RETURNS new object id
public function upload($filename,$mimetype=null){
	//note the check on baseTypeId instead of objectId in order to include sites....
	if($this->properties['cmis:baseTypeId']<>"cmis:folder"){
		//NOT A FOLDER!!!!
		return FALSE;
	}
	$handle = fopen($filename, "r");
	if(!$handle)return FALSE;//file not found
	$contents = fread($handle, filesize($filename));
	if(!$mimetype)$type=mime_content_type($filename);
	else $type=$mimetype;
	fclose($handle);
	$base64_content=base64_encode($contents);

	//ESCAPE & char
	$fileescname=str_replace("&","&#038;",$filename);
//ATOM FEED for new doc
$inquiry="<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>
<atom:entry xmlns:cmis=\"http://docs.oasis-open.org/ns/cmis/core/200908/\"
xmlns:cmism=\"http://docs.oasis-open.org/ns/cmis/messaging/200908/\"
xmlns:atom=\"http://www.w3.org/2005/Atom\"
xmlns:app=\"http://www.w3.org/2007/app\"
xmlns:cmisra=\"http://docs.oasis-open.org/ns/cmis/restatom/200908/\">
<atom:title>$fileescname</atom:title>
	<cmisra:content>
		<cmisra:mediatype>$type</cmisra:mediatype>
                        <cmisra:base64>$base64_content</cmisra:base64>
	</cmisra:content>
	<cmisra:object>
		<cmis:properties>
	        <cmis:propertyId propertyDefinitionId=\"cmis:objectTypeId\">
		<cmis:value>cmis:document</cmis:value>
	               </cmis:propertyId>
	        </cmis:properties>
        </cmisra:object>
</atom:entry>
";

	$url=$this->childrenUrl;
 	$result=$this->postHttp($url,$this->username,$this->password,$inquiry);
	//if something went wrong you can check the $this->lastHttpCode
	if($result)return $this->getObjectId($result);
	else return FALSE;
}

//ASPECT set/modification on the current object
//CURRENTLY NOT WORKING WITH ALFRESCO3.x
// with old deprecated service http://alfresco.loc:8080/alfresco/service/cmis
public function setAspect($aspect,$value){

	//ESCAPE & char
	$value=str_replace("&","&#038;",$value);
	$inquiry="<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>
<atom:entry xmlns:cmis=\"http://docs.oasis-open.org/ns/cmis/core/200908/\"
xmlns:cmism=\"http://docs.oasis-open.org/ns/cmis/messaging/200908/\"
xmlns:atom=\"http://www.w3.org/2005/Atom\"
xmlns:app=\"http://www.w3.org/2007/app\"
xmlns:aspects=\"http://www.alfresco.org\"
xmlns:cmisra=\"http://docs.oasis-open.org/ns/cmis/restatom/200908/\">
   <cmisra:object>
       <cmis:properties>
           <aspects:setAspects>
               <aspects:properties>
                   <cmis:propertyString propertyDefinitionId=\"$aspect\" queryName=\"$aspect\">
                        <cmis:value>$value</cmis:value>
                   </cmis:propertyString>
               </aspects:properties>
           </aspects:setAspects>
       </cmis:properties>
    </cmisra:object>
</atom:entry>
";

	$url=$this->selfUrl;
	$result=$this->putHttp($url,$this->username,$this->password,$inquiry);
	//reload modified object
	$this->loadCMISObject($this->properties['alfcmis:nodeRef']);
}


//DELETES node
public function delete(){
	$url=$this->selfUrl;
	return $this->deleteHttp($url,$this->username,$this->password);
}

//returns object id from a XML node
function getObjectId($node){
	$objdata=simplexml_load_string($node);
	if($objdata==FALSE){
//			return FALSE;
	}
	//GETS objecId from returned XML
	//very complex handling of different namespaces returned;
	//ATOM->CMISRA-> CMIS -> ASPECTS
	$namespaces=$objdata->getNameSpaces(true);
	$cmisra=$objdata->children($namespaces['cmisra']);
	$cmis=$cmisra->children($namespaces['cmis']);
	for($x=0;$x<count($cmis->properties->propertyId);$x++){
		$propertyDefinitionId=$cmis->properties->propertyId[$x]->attributes()->propertyDefinitionId;
		$value=(string)$cmis->properties->propertyId[$x]->value;
		if($propertyDefinitionId=="cmis:objectId") return $value;
	}
	return FALSE;//not found (is it possible???? :-) )
}



//Performs a CMIS query on the current repo. It is similar to MYSQLI.
//Since there isn't anyting like SQL LIMIT, be careful to initaiize $this->maxItems to a reasonable value
//You can skip first n results by setting $this-skipCount
//
//DEVELOPED AND TESTED on ALFRESCO 4
//Probably not working with different versions due to different namesoace definition

public function query($query){
	$this->num_rows=0;
	$this->fetchPosition=0;
	unset($this->queryResult);

	//XML for new query
	$inquiry="<cmis:query xmlns:cmis=\"http://docs.oasis-open.org/ns/cmis/core/200908/\">
       <cmis:statement>$query</cmis:statement>
       <cmis:searchAllVersions>false</cmis:searchAllVersions>
       <cmis:includeAllowableActions>false</cmis:includeAllowableActions>
       <cmis:maxItems>".$this->maxItems."</cmis:maxItems>
       <cmis:skipCount>".$this->skipCount."</cmis:skipCount>
</cmis:query>
";
	$url=$this->childrenUrl;
	$url=$this->url.$this->repoId."/query";

	$result=$this->postHttp($url,$this->username,$this->password,$inquiry);

	if($result)$objdata=simplexml_load_string($result);
	else return FALSE;

	$namespaces=$objdata->getNameSpaces(true);

	//Enjoy surfing trough nested namespaces :)
	//definition for ALF4: ATOM->[entry]->CMISRA->CMIS
	$atom=$objdata->children($namespaces['atom']);
	$entry=$atom->entry;
	$x=0;
	foreach($entry as $ent){
		$cmisra=$ent->children($namespaces['cmisra']);
		$cmis=$cmisra->children($namespaces['cmis']);
		$prop=$cmis->properties;
		$y=0;
		foreach($prop as $p){
			foreach($p as $a){
				$field=(string)$a->attributes()->queryName;
				$value=(string)$a->value;
				$this->queryResult[$x][$field]=$value;
				$this->queryResult[$x][$y]=$value;
				$y++;
			}
		}
		$x++;
	}
	$this->num_rows=count($this->queryResult);
	if($this->num_rows)return $this;
	else return false;
}

//fetches the query result array. Very similar to mysqli functions
public function fetch_array(){
	if($this->fetchPosition<($this->num_rows-1)){
		$this->fetchPosition++;
		return $this->queryResult[$this->fetchPosition];
	}
	else return false;
}

//END of CLASS
}
