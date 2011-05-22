<?php

	/*
	 * Author: Andreas Linde <mail@andreaslinde.de>
	 *         Kenth Sutherland
	 *
	 * Copyright (c) 2009-2011 Andreas Linde & Kent Sutherland.
	 * All rights reserved.
	 *
	 * Permission is hereby granted, free of charge, to any person
	 * obtaining a copy of this software and associated documentation
	 * files (the "Software"), to deal in the Software without
	 * restriction, including without limitation the rights to use,
	 * copy, modify, merge, publish, distribute, sublicense, and/or sell
	 * copies of the Software, and to permit persons to whom the
	 * Software is furnished to do so, subject to the following
	 * conditions:
	 *
	 * The above copyright notice and this permission notice shall be
	 * included in all copies or substantial portions of the Software.
	 *
	 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
	 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
	 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
	 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
	 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
	 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
	 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
	 * OTHER DEALINGS IN THE SOFTWARE.
	 */
	 
//
// This script will be invoked by the application to submit a crash log
//

require_once('config.php');

if (!class_exists('XMLReader', false)) die(xml_for_result(FAILURE_PHP_XMLREADER_CLASS));

if ($push_activated || $boxcar_activated) {
	
	$curl_info = curl_version();	// Checks for cURL function and SSL version. Thanks Adrian Rollett!
	if(!function_exists('curl_exec') || empty($curl_info['ssl_version']))
    	die(xml_for_result(FAILURE_PHP_CURL_LIB));
	
	if ($push_prowlids != "") {
		include('ProwlPHP.php');

    	if (!class_exists('Prowl', false)) die(xml_for_result(FAILURE_PHP_PROWL_CLASS));

		$prowl = new Prowl($push_prowlids);
	} else {
		$push_activated = false;
	}
	
	if ($boxcar_uid != "" && $boxcar_pwd != ""){
		include('class.boxcar.php');
	} else {
		$boxcar_activated = false;
	}
	
} else {
	
	$push_activated = false;
	$boxcar_activated = false;
}

// Check for mail code injection
foreach($_REQUEST as $fields => $value) {
	if (preg_match('/TO:/i', $value) || preg_match('/CC:/i', $value) || preg_match('/CCO:/i', $value) || preg_match('/Content-Type:/i', $value)) {
        $mail_activated = false;
    }
}

function xml_for_result($result) {
	return '<?xml version="1.0" encoding="UTF-8"?><result>'.$result.'</result>'; 
}

function parseblock($matches, $appString) {
    $result_offset = "";
    //make sure $matches[1] exists
	if (is_array($matches) && count($matches) >= 2) {
		$result = explode("\n", $matches[1]);
		foreach ($result as $line) {
			// search for the first occurance of the application name
			if (strpos($line, $appString) !== false && strpos($line, "uncaught_exception_handler (PLCrashReporter.m:") === false) {
				preg_match('/[0-9]+\s+[^\s]+\s+([^\s]+) /', $line, $matches);

                if (count($matches) >= 2) {
                    if ($result_offset != "")
                        $result_offset .= "%";
                    $result_offset .= $matches[1];
                }
			}
		}
	}
	
	return $result_offset;
}

function doPost($url, $postdata) {
    $url = parse_url($url);

    if (!isset($url['port'])) {
        if ($url['scheme'] == 'http') { $url['port']=80; }
        elseif ($url['scheme'] == 'https') { $url['port']=443; }
        elseif ($url['scheme'] == 'ssl') { $url['port']=443; }
    }
    $url['query']=isset($url['query'])?$url['query']:'';

    $url['protocol']=$url['scheme'].'://';
    
    $handle = fsockopen($url['protocol'].$url['host'], $url['port'], $errno, $errstr, 30);
	if (!$handle) {
		return 'error'; 
	} else {
		srand((double)microtime()*1000000);
        $boundary = "---------------------".substr(md5(rand(0,32000)),0,10);
        
        $data = "--$boundary\r\n";
        $data .="Content-Disposition: form-data; name=\"xml\"; filename=\"crash.xml\"\r\n";
        $data .= "Content-Type: text/xml\r\n\r\n";
        $data .= "".$postdata."\r\n";
        $data .="--$boundary--\r\n";

		$temp = "POST ".$url['path']." HTTP/1.1\r\n"; 
		$temp .= "Host: ".$url['host']."\r\n";
		$temp .= "User-Agent: PHP Script\r\n";
		$temp .= "Content-Type: multipart/form-data; boundary=$boundary\r\n";
        $temp .= "Content-length: " . strlen($data) . "\r\n\r\n";
        
		fwrite($handle, $temp.$data); 
		
		$response = '';
		
		while (!feof($handle)) 
			$response.=fgets($handle, 128); 
		
		$response=preg_split('/\r\n\r\n/',$response);

		$header=$response[0]; 
		$responsecontent=$response[1]; 
		
		if(!(strpos($header,"Transfer-Encoding: chunked")===false)) {
			$aux=preg_split('/\r\n/',$responsecontent);
			for($i=0;$i<count($aux);$i++) 
				if($i==0 || ($i%2==0)) 
					$aux[$i]=""; 
			$responsecontent=implode("",$aux); 
		} 
		
		fclose($handle);
		return chop($responsecontent); 
	} 
}

$allowed_args = ',xmlstring,';

/* Verbindung aufbauen, auswählen einer Datenbank */
// $link = mysql_connect($server, $loginsql, $passsql)
//     or die(xml_for_result(FAILURE_DATABASE_NOT_AVAILABLE));
// mysql_select_db($base) or die(xml_for_result(FAILURE_DATABASE_NOT_AVAILABLE));
try {
	if($dbtype == 'sqlite')
		$db = new PDO($dbtype.':'.$base); //sqlite:dbpath
	else
		$db = new PDO($dbtype.":host=".$server.';dbname='.$base, $loginsql, $passsql); //mysql:host=localhost;dbname=testdb
}
catch(PDOException $e) {
	die(xml_for_result(FAILURE_DATABASE_NOT_AVAILABLE));
}
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // prefer exceptions
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // prefer associative arrays

foreach(array_keys($_POST) as $k) {
    $temp = ",$k,";
    if(strpos($allowed_args,$temp) !== false) { $$k = $_POST[$k]; }
}
if (!isset($xmlstring)) $xmlstring = "";

if ($xmlstring == "") die(xml_for_result(FAILURE_INVALID_POST_DATA));

// Fix parsing bug in pre 1.0 mac client and iOS client, fixed in latest commi
$xmlstring = str_replace("<description><![CDATA[", "<description>", $xmlstring);
$xmlstring = str_replace("]]></description>", "</description>", $xmlstring);
$xmlstring = str_replace("<description>", "<description><![CDATA[", $xmlstring);
$xmlstring = str_replace("</description>", "]]></description>", $xmlstring);

$reader = new XMLReader();

$reader->XML($xmlstring);

$crashIndex = -1;
$crashes = array();

function reading($reader, $tag) {
	$input = "";
	while ($reader->read()) {
    	if ($reader->nodeType == XMLReader::TEXT ||
        	$reader->nodeType == XMLReader::CDATA ||
        	$reader->nodeType == XMLReader::WHITESPACE ||
        	$reader->nodeType == XMLReader::SIGNIFICANT_WHITESPACE)
    	{
			$input .= $reader->value;
		} else if ($reader->nodeType == XMLReader::END_ELEMENT
			&& $reader->name == $tag)
		{
			break;
		}
	}
    return $input;
}

define('VALIDATE_NUM',          '0-9');
define('VALIDATE_ALPHA_LOWER',  'a-z');
define('VALIDATE_ALPHA_UPPER',  'A-Z');
define('VALIDATE_ALPHA',        VALIDATE_ALPHA_LOWER . VALIDATE_ALPHA_UPPER);
define('VALIDATE_SPACE',        '\s');
define('VALIDATE_PUNCTUATION',  VALIDATE_SPACE . '\.,;\:&"\'\?\!\(\)');


/**
 * Validate a string using the given format 'format'
 *
 * @param string $string  String to validate
 * @param array  $options Options array where:
 *                          'format' is the format of the string
 *                              Ex:VALIDATE_NUM . VALIDATE_ALPHA (see constants)
 *                          'min_length' minimum length
 *                          'max_length' maximum length
 *
 * @return boolean true if valid string, false if not
 *
 * @access public
 */
function ValidateString($string, $options) {
	$format     = null;
	$min_length = 0;
	$max_length = 0;
	
	if (is_array($options)) {
		extract($options);
	}
	
	if ($format && !preg_match("|^[$format]*\$|s", $string)) {
		return false;
	}
	
	if ($min_length && strlen($string) < $min_length) {
		return false;
	}
	
	if ($max_length && strlen($string) > $max_length) {
		return false;
	}
	
	return true;
}

while ($reader->read()) {
	if ($reader->name == "crash" && $reader->nodeType == XMLReader::ELEMENT) {
	    $crashIndex++;
	    
	    $crashes[$crashIndex]["bundleidentifier"] = "";
        $crashes[$crashIndex]["applicationname"] = "";
        $crashes[$crashIndex]["systemversion"] = "";
        $crashes[$crashIndex]["platform"] = "";
        $crashes[$crashIndex]["senderversion"] = "";
        $crashes[$crashIndex]["version"] = "";
        $crashes[$crashIndex]["userid"] = "";
        $crashes[$crashIndex]["contact"] = "";
        $crashes[$crashIndex]["description"] = "";
        $crashes[$crashIndex]["logdata"] = "";
        $crashes[$crashIndex]["appname"] = "";
        
	} else if ($reader->name == "bundleidentifier" && $reader->nodeType == XMLReader::ELEMENT) {
		$crashes[$crashIndex]["bundleidentifier"] = mysql_real_escape_string(reading($reader, "bundleidentifier"));
	} else if ($reader->name == "version" && $reader->nodeType == XMLReader::ELEMENT) {
        $crashes[$crashIndex]["version"] = mysql_real_escape_string(reading($reader, "version"));
		if( !ValidateString( $crashes[$crashIndex]["version"], array('format'=>VALIDATE_NUM . VALIDATE_ALPHA. VALIDATE_SPACE . VALIDATE_PUNCTUATION) ) )
		    die(xml_for_result(FAILURE_XML_VERSION_NOT_ALLOWED));
	} else if ($reader->name == "senderversion" && $reader->nodeType == XMLReader::ELEMENT) {
        $crashes[$crashIndex]["senderversion"] = mysql_real_escape_string(reading($reader, "senderversion"));
        if (!ValidateString( $crashes[$crashIndex]["senderversion"], array('format'=>VALIDATE_NUM . VALIDATE_ALPHA. VALIDATE_SPACE . VALIDATE_PUNCTUATION) ) )
            die(xml_for_result(FAILURE_XML_SENDER_VERSION_NOT_ALLOWED));
	} else if ($reader->name == "applicationname" && $reader->nodeType == XMLReader::ELEMENT) {
		$crashes[$crashIndex]["applicationname"] = mysql_real_escape_string(reading($reader, "applicationname"));
	} else if ($reader->name == "systemversion" && $reader->nodeType == XMLReader::ELEMENT) {
		$crashes[$crashIndex]["systemversion"] = mysql_real_escape_string(reading($reader, "systemversion"));
	} else if ($reader->name == "userid" && $reader->nodeType == XMLReader::ELEMENT) {
		$crashes[$crashIndex]["userid"] = mysql_real_escape_string(reading($reader, "userid"));
	} else if ($reader->name == "contact" && $reader->nodeType == XMLReader::ELEMENT) {
        $crashes[$crashIndex]["contact"] = mysql_real_escape_string(reading($reader, "contact"));
	} else if ($reader->name == "description" && $reader->nodeType == XMLReader::ELEMENT) {
		$crashes[$crashIndex]["description"] = mysql_real_escape_string(reading($reader, "description"));
	} else if ($reader->name == "log" && $reader->nodeType == XMLReader::ELEMENT) {
		$crashes[$crashIndex]["logdata"] = reading($reader, "log");
	} else if ($reader->name == "platform" && $reader->nodeType == XMLReader::ELEMENT) {
		$crashes[$crashIndex]["platform"] = reading($reader, "platform");
	}
}

$reader->close();

$lastError = 0;

// store the best version status to return feedback
$best_status = VERSION_STATUS_UNKNOWN;

// go through all crah reports
foreach ($crashes as $crash) {

    // don't proceed if we don't have anything to search for
    if ($crashIndex < 0 || $crash["bundleidentifier"] == "")
	    die("No valid data entered!");
	
    // by default set the appname to bundleidentifier, so it has some meaningful value for sure
    $crash["appname"] =  $crash["bundleidentifier"];

    // store the status of the fix version for this crash
    $crash["fix_status"] = VERSION_STATUS_UNKNOWN;

    // the status of the buggy version
    $crash["version_status"] = VERSION_STATUS_UNKNOWN;

    // by default assume push is turned of for the found version
    $notify = $notify_default_version;

    // push ids to send notifications to (per app setting)
    $notify_pushids = '';

    // email addresses to send notifications to (per app setting)
    $notify_emails = '';
    
    // check out if we accept this app and version of the app
    $acceptlog = false;
    $symbolicate = false;

    $hockeyappidentifier = '';
    
    // shall we accept any crash log or only ones that are named in the database
    if ($acceptallapps) {
	    // external symbolification is turned on by default when accepting all crash logs
	    $acceptlog = true;
	    $symbolicate = true;
	
		// get the app name
		$query = "SELECT name, hockeyappidentifier FROM ".$dbapptable." where bundleidentifier = ?";
		try {
			$stmt = $db->prepare($query);
			$stmt->bindParam(1, $crash['bundleidentifier']);
			$stmt->execute();
			
			if ($stmt->rowCount() == 1) {
				$row = $stmt->fetch();
				$crash["appname"] = $row['name'];
				$hockeyappidentifier = $row['hockeyappidentifier'];
				$notify_emails = $mail_addresses;
				$notify_pushids = $push_prowlids;
			}
		}
		catch(PDOException $e) {
			// be nice to log the actual error?
			die(xml_for_result(FAILURE_SQL_SEARCH_APP_NAME));
        }
    } else {
	    // the bundleidentifier is the important string we use to find a match
        // $query = "SELECT id, symbolicate, name, notifyemail, notifypush, hockeyappidentifier FROM ".$dbapptable." where bundleidentifier = '".$crash["bundleidentifier"]."'";
		$query = "SELECT id, symbolicate, name, notifyemail, notifypush, hockeyappidentifier FROM ".$dbapptable." where bundleidentifier = ?";
		try {
			$stmt = $db->prepare($query);
			$stmt->bindValue(1, $crash["bundleidentifier"]);
			$stmt->execute();
			
			if ($stmt->rowCount() == 1) {
			    // we found one, so let this crash through
			    $acceptlog = true;

			    $row = $stmt->fetch();

			    // check if a todo entry shall be added to create remote symbolification
			    if ($row['symbolicate'] == 1)
				    $symbolicate = true;

			    // get the app name
			    $crash["appname"] = $row['name'];

			    $notify_emails = $row['notifyemail'];
			    $notify_pushids = $row['notifypush'];

			    $hockeyappidentifier = $row['hockeyappidentifier'];
		    }

	        // add global email addresses
		    if ($mail_addresses != '') {
	            if ($notify_emails != '') {
	                $notify_emails .= ';'.$mail_addresses;
	            } else {
	                $notify_emails = $mail_addresses;
	            }
	        }

	        // add global prowl ids
		    if ($push_prowlids != '') {
	            if ($notify_pushids != '') {
	                $notify_pushids .= ','.$push_prowlids;
	            } else {
	                $notify_pushids = $push_prowlids;
	            }
	        }

		    // mysql_free_result($result);
			$stmt->closeCursor();
		}
		catch(PDOException $e) {
			die(xml_for_result(FAILURE_SQL_SEARCH_APP_NAME));
		}
	    // $result = mysql_query($query) or die(xml_for_result(FAILURE_SQL_SEARCH_APP_NAME));
	}


    // Make sure we only have a max of 5 prowl ids
	$push_array=preg_split('/[,]+/',$notify_pushids);
    if (sizeof($push_array) > 5) {
        $notify_pushids = '';
        for ($i=0; $i < 5; $i++) {
            if (i>0)
                $notify_pushids .= ',';
            $notify_pushids .= $push_array[$i];
        }
    }

    // add the crash data to the database
    if ($crash["logdata"] != "" && $crash["version"] != "" & $crash["applicationname"] != "" && $crash["bundleidentifier"] != "" && $acceptlog == true) {
        // check if we need to redirect this crash
        if ($hockeyappidentifier != '') {
            if (!isset($hockeyAppURL))
        	    $hockeyAppURL = "ssl://beta.hockeyapp.net/";
        	    
            // we assume all crashes in this xml goes to the same app, since it is coming from one client. so push them all at once to HockeyApp
            $result = doPost($hockeyAppURL."api/2/apps/".$hockeyappidentifier."/crashes", utf8_encode($xmlstring));
            
            // we do not parse the result, values are different anyway, so simply return unknown status            
            echo xml_for_result(VERSION_STATUS_UNKNOWN);

			/* schliessen der Verbinung */
			// mysql_close($link);

    	    // HockeyApp doesn't support direct feedback, it requires the new client to do that. So exit right away.
    	    exit;
        }

    	// is this a jailbroken device?
    	$jailbreak = 0;
    	if(strpos($crash["logdata"], "MobileSubstrate") !== false)
    		$jailbreak = 1;

        // Since analyzing the log data seems to have problems, first add it to the database, then read it, since it seems that one is fine then

        // first check if the version status is not discontinued
    
       	// check if the version is already added and the status of the version and notify status
    	
		try {
			$query = "SELECT id, status, notify FROM ".$dbversiontable." WHERE bundleidentifier = ? and version = ?";
			$stmt = $db->prepare($query);
			$stmt->bindValue(1, $crash["bundleidentifier"]);
			$stmt->bindValue(1, $crash['version']);
			$stmt->execute();
		}
		catch (PDOException $e) {
			die(xml_for_result(FAILURE_SQL_CHECK_VERSION_EXISTS));
		}
    	// $result = mysql_query($query) or die(xml_for_result(FAILURE_SQL_CHECK_VERSION_EXISTS));

    	$numrows = $stmt->rowCount();
    	if ($numrows == 0) {
            // version is not available, so add it with status VERSION_STATUS_AVAILABLE
    		// $query = "INSERT INTO ".$dbversiontable." (bundleidentifier, version, status, notify) values ('".$crash["bundleidentifier"]."', '".$crash["version"]."', ".VERSION_STATUS_UNKNOWN.", ".$notify_default_version.")";
			try {
				$stmt->closeCursor();
				$query = "INSERT INTO ".$dbversiontable." (bundleidentifier, version, status, notify) values (:identifier, :version, :status, :notify)";
				$stmt = $db->prepare($query);
				$stmt->bindValue(':identifier', $crash['bundleidentifier']);
				$stmt->bindValue(':version', $crash['version']);
				$stmt->bindValue(':status', VERSION_STATUS_UNKNOWN);
				$stmt->bindValue(':notify', $notify_default_version);
				$stmt->execute();
			} catch (PDOException $e) {
				die(xml_for_result(FAILURE_SQL_ADD_VERSION));
			}
    	} else {
            $row = $stmt->fetch();
    		$crash["version_status"] = $row['status'];
    		$notify = $row['notify'];
    		$stmt->closeCursor();
    	}

    	if ($crash["version_status"] == VERSION_STATUS_DISCONTINUED)
    	{
        	$lastError = FAILURE_VERSION_DISCONTINUED;
        	continue;
    	}

        // now try to find the offset of the crashing thread to assign this crash to a crash group
	
    	// this stores the offset which we need for grouping
    	$crash_offset = "";
    	$appcrashtext = "";
	
    	preg_match('%Application Specific Information:.*?\n(.*?)\n\n%is', $crash["logdata"], $appcrashinfo);
    	if (is_array($appcrashinfo) && count($appcrashinfo) == 2) {
            $appcrashtext = str_replace("\\", "", $appcrashinfo[1]);
            $appcrashtext = str_replace("'", "\'", $appcrashtext);
        }
    
    	// extract the block which contains the data of the crashing thread
      	preg_match('%Thread [0-9]+ Crashed:.*?\n(.*?)\n\n%is', $crash["logdata"], $matches);
        $crash_offset = parseblock($matches, $crash["applicationname"]);	
        if ($crash_offset == "") {
            $crash_offset = parseblock($matches, $crash["bundleidentifier"]);
        }
        if ($crash_offset == "") {
            preg_match('%Thread [0-9]+ Crashed:\n(.*?)\n\n%is', $crash["logdata"], $matches);
            $crash_offset = parseblock($matches, $crash["applicationname"]);
        }
        if ($crash_offset == "") {
            $crash_offset = parseblock($matches, $crash["bundleidentifier"]);
        }

    	// stores the group this crashlog is associated to, by default to none
    	$log_groupid = 0;
	
    	// if the offset string is not empty, we try a grouping
    	if (strlen($crash_offset) > 0) {
			// get all the known bug patterns for the current app version
			try {
				$query = "SELECT id, fix, amount, description FROM ".$dbgrouptable." WHERE bundleidentifier = :ident AND affected = :affected AND pattern = :pattern";
				$stmt = $db->prepare($query);
				$stmt->bindValue(':ident', $crash["bundleidentifier"]);
				$stmt->bindValue(':affected', $crash["version"]);
				$stmt->bindValue(':pattern', $crash_offset);
				$stmt->execute();
			}
			catch (PDOException $e) {
				die(xml_for_result(FAILURE_SQL_FIND_KNOWN_PATTERNS));
			}

    		$numrows = $stmt->rowCount();
		
    		if ($numrows == 1) {
    			// assign this bug to the group
    			$row = $stmt->fetch();
    			$log_groupid = $row['id'];
    			$amount = $row['amount'];
                $desc = $row['description'];
            
    			$stmt->closeCursor();

				// update the occurances of this pattern
				try {
					$query = "UPDATE ".$dbgrouptable." SET amount=amount+1, latesttimestamp = :time WHERE id=:id";
					$stmt = $db->prepare($query);
					$stmt->bindValue(':time', time());
					$stmt->bindValue(':id', $log_groupid);
					$stmt->execute();
				} catch (PDOException $e) {
					die(xml_for_result(FAILURE_SQL_UPDATE_PATTERN_OCCURANCES));
				}

				if ($desc != "" && $appcrashtext != "") {
					$desc = str_replace("'", "\'", $desc);
					if (strpos($desc, $appcrashtext) === false) {
						$appcrashtext = $desc."\n".$appcrashtext;
						try {
							$query = "UPDATE ".$dbgrouptable." SET description=:desc WHERE id=:id";
							$stmt = $db->prepare($query);
							$stmt->bindValue(':desc', $appcrashtext);
							$stmt->bindValue(':id', $log_groupid);
							$stmt->execute();
						} catch (PDOException $e) {
							die(end_with_result('Error in SQL '.$query));
						}
                    }
                }                       

    			// check the status of the bugfix version
				try {
					$query = "SELECT status FROM ".$dbversiontable." WHERE bundleidentifier = :ident AND version = :version";
					$stmt = $db->prepare($query);
					$stmt->bindValue(':ident', $crash["bundleidentifier"]);
					$stmt->bindValue(':version', $row['fix']);
					$stmt->execute();
				}
				catch (PDOException $e) {
					die(xml_for_result(FAILURE_SQL_CHECK_BUGFIX_STATUS));
				}
			
    			$numrows = $stmt->rowCount();
    			if ($numrows == 1) {
    				$row = $stmt->fetch();
    				$crash["fix_status"] = $row['status'];
    			}

    			if ($notify_amount_group > 1 && $notify_amount_group == $amount && $notify >= NOTIFY_ACTIVATED) {
                    // send prowl notification
                    if ($push_activated) {
                        $prowl->push(array(
    						'application'=>$crash["appname"],
    						'event'=>'Critical Crash',
    						'description'=>'Version '.$crash["version"].' Pattern '.$crash_offset.' has a MORE than '.$notify_amount_group.' crashes!\n Sent at ' . date('H:i:s'),
    						'priority'=>0,
                        ),true);
                    }

                    // send boxcar notification
    				if($boxcar_activated) {
    					$boxcar = new Boxcar($boxcar_uid, $boxcar_pwd);
    					print_r($boxcar->send($crash["appname"], 'Version '.$crash["version"].' Pattern '.$crash_offset.' has a MORE than '.$notify_amount_group.' crashes!\n Sent at ' . date('H:i:s')));
    				}
				
                    // send email notification
                    if ($mail_activated) {
                        $subject = $crash["appname"].': Critical Crash';
                    
                        if ($crash_url != '')
                            $url = "Link: ".$crash_url."admin/crashes.php?bundleidentifier=".$crash["bundleidentifier"]."&version=".$crash["version"]."&groupid=".$log_groupid."\n\n";
                        else
                            $url = "\n";
                        $message = "Version ".$crash["version"]." Pattern ".$crash_offset." has a MORE than ".$notify_amount_group." crashes!\n".$url."Sent at ".date('H:i:s');

                        mail($notify_emails, $subject, $message, 'From: '.$mail_from. "\r\n");
                    }
                }
            } else if ($numrows == 0) {
                // create a new pattern for this bug and set amount of occurrances to 1
				try {
					$query = "INSERT INTO ".$dbgrouptable." (bundleidentifier, affected, pattern, amount, latesttimestamp, description) VALUES(:ident, :affected, :pattern, :amount, :time, :desc)";
					$stmt = $db->prepare($query);
					$stmt->bindValue(':ident', $crash["bundleidentifier"]);
					$stmt->bindValue(':affected', $crash["version"]);
					$stmt->bindValue(':pattern', $crash_offset);
					$stmt->bindValue(':amount', 1);
					$stmt->bindValue(':time', time());
					$stmt->bindValue(':desc', $appcrashtext);
					$stmt->execute();
				}
				catch (PDOException $e) {
					die(xml_for_result(FAILURE_SQL_ADD_PATTERN));
				}
			
    			$log_groupid = $db->lastInsertId($dbgrouptable.'_id_seq'); // sequence name used by postgres, ignored by others

    			if ($notify == NOTIFY_ACTIVATED) {
                    // send push notification
                    if ($push_activated) {
                        $prowl->push(array(
    						'application'=>$crash["appname"],
    						'event'=>'New Crash type',
    						'description'=>'Version '.$crash["version"].' has a new type of crash!\n Sent at ' . date('H:i:s'),
    						'priority'=>0,
    					),true);
    				}
				
                    // send email notification
                    if ($mail_activated) {
                        $subject = $crash["appname"].': New Crash type';

                        if ($crash_url != '')
                            $url = "Link: ".$crash_url."admin/crashes.php?bundleidentifier=".$crash["bundleidentifier"]."&version=".$crash["version"]."&groupid=".$log_groupid."\n\n";
                        else
                            $url = "\n";
                        $message = "Version ".$crash["version"]." has a new type of crash!\n".$url."Sent at ".date('H:i:s');

                        mail($notify_emails, $subject, $message, 'From: '.$mail_from. "\r\n");
                    }
    			}
    		}
    	}
	
        // now insert the crashlog into the database
    	// $query = "INSERT INTO ".$dbcrashtable." (userid, contact, bundleidentifier, applicationname, systemversion, platform, senderversion, version, description, log, groupid, timestamp, jailbreak) values ('".$crash["userid"]."', '".$crash["contact"]."', '".$crash["bundleidentifier"]."', '".$crash["applicationname"]."', '".$crash["systemversion"]."', '".$crash["platform"]."', '".$crash["senderversion"]."', '".$crash["version"]."', '".$crash["description"]."', '".mysql_real_escape_string($crash["logdata"])."', '".$log_groupid."', '".date("Y-m-d H:i:s")."', ".$jailbreak.")";
		try {
			$query = "INSERT INTO ".$dbcrashtable." (userid, contact, bundleidentifier, applicationname, systemversion, platform, senderversion, version, description, log, groupid, timestamp, jailbreak) values (:userid, :contact, :ident, :name, :sysversion, :platform, :senderversion, :version, :desc, :log, :groupid, :time, :jailbreak)";
			$stmt = $db->prepare($query);
			$stmt->bindValue(':userid', $crash["userid"]);
			$stmt->bindValue(':contact', $crash["contact"]);
			$stmt->bindValue(':ident', $crash["bundleidentifier"]);
			$stmt->bindValue(':name', $crash["applicationname"]);
			$stmt->bindValue(':sysversion', $crash["systemversion"]);
			$stmt->bindValue(':platform', $crash["platform"]);
			$stmt->bindValue(':senderversion', $crash["senderversion"]);
			$stmt->bindValue(':version', $crash["version"]);
			$stmt->bindValue(':desc', $crash["description"]);
			$stmt->bindValue(':log', $crash["logdata"]);
			$stmt->bindValue(':groupid', $log_groupid);
			$stmt->bindValue(':time', date("Y-m-d H:i:s"));
			$stmt->bindValue(':jailbreak', $jailbreak);
			$stmt->execute();
		} 
		catch (PDOException $e) {
			die(xml_for_result(FAILURE_SQL_ADD_CRASHLOG));
		}
		
    	$new_crashid = $db->lastInsertId($dbcrashtable.'_id_seq');

    	// if this crash log has to be manually symbolicated, add a todo entry
    	if ($symbolicate) {
			try {
				$query = "INSERT INTO ".$dbsymbolicatetable." (crashid, done) values (:crashid, 0)";
				$stmt = $db->prepare($query);
				$stmt->bindValue(':crashid', $new_crashid);
				$stmt->execute();
			} catch (PDOException $e) {
				die(xml_for_result(FAILURE_SQL_ADD_SYMBOLICATE_TODO));
			}
    	}
    	$lastError = 0;
    } else if ($acceptlog == false) {
    	$lastError = FAILURE_INVALID_INCOMING_DATA;
    	continue;
    }
    
    if ($crash["fix_status"] > $best_status)
        $best_status = $crash["fix_status"];

}

/* schliessen der Verbinung */
// pdo will close itself

/* Ausgabe der Ergebnisse in XML */
if ($lastError != 0) {
    echo xml_for_result($lastError);
} else {
    echo xml_for_result($best_status);
}
