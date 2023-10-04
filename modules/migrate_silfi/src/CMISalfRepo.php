<?php

namespace Drupal\migrate_silfi;

/**************************************************************************
*	ALFRESCO PHP CMIS API
*	© 2013 by Fabrizio Vettore - fabrizio(at)vettore.org
*	V 0.6
*
*	BASIC repo and object handling:
*	Create, upload, download, delete, change properties.
*	Can change basic ASPECTS like TITLE and DESCRIPTION
*	(this is the real reason why i wrote it ;-))
*	BASIC repo QUERY
*
*	COMPATIBILTY:
*	ALFRESCO 4.x with cmisatom binding
*	ALFRESCO 5.x (not fully tested)
*	(url like: http://alfrescoserver:8080/alfresco/cmisatom)
*	Partial compatibility with the prevoius deprecated version
*	(http://alfrescoserver:8080/alfresco/service/cmis) (under development)
*
*    This program is free software: you can redistribute it and/or modify
*    it under the terms of the GNU General Public License as published by
*    the Free Software Foundation, either version 3 of the License, or
*    (at your option) any later version.
*
*    This program is distributed in the hope that it will be useful,
*    but WITHOUT ANY WARRANTY; without even the implied warranty of
*    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*    GNU General Public License for more details.
*
*    You should have received a copy of the GNU General Public License
*    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
**************************************************************************/

//MAIN CLASS FOR HANDLING THE REPO
class CMISalfRepo
{
	var $username;
	var $url;
	var $password;

	public $repoId;//Very important
	public $rootFolderId;//can be useful
	public $connected=FALSE;
	//last http reply for debugging purpose can be accessed by the program
	public $lastHttp;
	public $lastHttpStatus;//for debugging
	public $uritemplates;

function __construct($url, $username = null, $password = null){
	$this->url=$url;
	$this->connect($url, $username, $password);
}


//CONNECTION to the repo to get basic data
function connect($url, $username, $password){
	$this->url=$url;
	//try to connect
	$ch=curl_init();
	$reply=$this->getHttp($url,$username,$password);
	$this->lastHttp=$reply;
	if($reply==FALSE)return FALSE;
	//complex handling of different namespaces returned;
	//ATOM->APP->CMISRA
	$repodata=simplexml_load_string($reply);
	$this->namespaces=$repodata->getNameSpaces(true);
	//Different implementation of namespaces:
	//Alfresco 3.x - []->CMISRA
	//Alfresco 4.x - APP->CMISRA
	if(isset($this->namespaces['app']))
		$app=$repodata->children($this->namespaces['app']);
	else
		$app=$repodata->children();
	$cmisra=$app->children($this->namespaces['cmisra']);
	$uritemplates=$cmisra->children($this->namespaces['cmis']);
	$cmis=$cmisra->children($this->namespaces['cmis']);
	$this->rootFolderId=$cmis->rootFolderId;
	$this->repoId=$cmis->repositoryId;
	$this->cmisobject=$cmis;
	//get all uri templates for different implementations (for example alfresco 3.x)
	//URITEMPLATES explain how the URL must be composed in order to retrieve
	//CMIS object from a repo
	foreach($cmisra->uritemplate as $template){
		$tempuri=$template->template;
		$type=$template->type;

		$this->uritemplates["$type"]=$tempuri;
	}
	$this->connected=TRUE;
	return$this->repoId;
}

//check for ERRORS
function checkHttpCode($code){
	//valid code from 200 to 299
	//200 OK
	//201 DOCUMENT CREATED
	//202 ACCEPTED......
	if($code>=200 && $code < 300) return TRUE;//IS OK
	else return FALSE;//Not OK: error code returned
}

//Handles HTTP requests with GET method
function getHttp($url, $username, $password){
//	echo "<br>URL: $url</br>";
	$ch=curl_init();
	curl_setopt($ch, CURLOPT_URL, $url );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE );
	curl_setopt($ch, CURLOPT_USERPWD,"$username:$password");
	$reply=curl_exec($ch);
	$this->lastHttp=$reply;
	$status=curl_getinfo($ch);
	//get status
	$this->lastHttpStatus=$status['http_code'];
	if($this->checkHttpCode($status['http_code'])) return $reply;
//	echo "$url\n$reply\n";
	return FALSE;
	}

//Handles HTTP requests with POST method
function postHttp($url, $username, $password,$postfields){
	$ch=curl_init($url);
//	echo "<br>URL: $url</br>";
        curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE );
	curl_setopt($ch, CURLOPT_POST, TRUE );
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");//probably unnecessary
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/atom+xml;type=entry"));
	curl_setopt($ch, CURLOPT_USERPWD,"$username:$password");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
	$reply=curl_exec($ch);
	$this->lastHttp=$reply;
	$status=curl_getinfo($ch);
	//get status
	$this->lastHttpStatus=$status['http_code'];
	if($this->checkHttpCode($status['http_code'])) return $reply;
	else return FALSE;
	}

//Handles HTTP requests with PUT method
function putHttp($url, $username, $password,$postfields,$contentType="application/atom+xml;type=entry"){
	//found no other way for the PUT method to work fine other than putting a real file.
	//May be there is a better solution.....
	$fp=fopen("put.xml","wb+");
	if(!$fp){
		echo "CANNOT open file! Please check for folder write permission\n\n";
		return FALSE;
	}
	fwrite($fp,$postfields);
	fclose($fp);//reopening for curl INFILE
	$fp=fopen("put.xml","rb+");
	$ch=curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_INFILE, $fp);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE );
	curl_setopt($ch, CURLOPT_PUT, TRUE );
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");//probably unnecessary
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Length: ' . strlen($postfields),"X-HTTP-Method-Override: PUT","Content-Type: $contentType"));
	curl_setopt($ch, CURLOPT_USERPWD,"$username:$password");
	$reply=curl_exec($ch);
	$this->lastHttp=$reply;
	fclose($fp);
	unlink("put.xml");
	$status=curl_getinfo($ch);
	//get status
	$this->lastHttpStatus=$status['http_code'];
	if($this->checkHttpCode($status['http_code'])) return $reply;
	else return FALSE;
	}

//Handles HTTP requests with DELETE method
function deleteHttp($url, $username, $password){
	$ch=curl_init($url);
	curl_setopt($ch, CURLOPT_URL, $url );
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE );
	curl_setopt($ch, CURLOPT_USERPWD,"$username:$password");
	$reply=curl_exec($ch);
	$this->lastHttp=$reply;
	$status=curl_getinfo($ch);
	//get status
	$this->lastHttpStatus=$status['http_code'];
	if($this->checkHttpCode($status['http_code'])) return TRUE;
	else return FALSE;
	}

//END of CLASS
}
