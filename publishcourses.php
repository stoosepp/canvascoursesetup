<?php


$canvas_URL = $_POST['domain'];
$token = $_POST['token'];
$items = $_POST['items'];
$quarter = $_POST['quarter'];
$front_page_body = $_POST['welcomemessage'];
$skipped_course_count = 0;
$published_course_count = 0;
$invalid_course_count = 0;

date_default_timezone_set('America/Los_Angeles');

$date_string  = date('l jS \of F Y h:i:s A');
$file_date_string  = date('mdYH:i:s');
echo ($date_string.'<br \><br \>');
$file_name = 'log'.$file_date_string.'.txt';

$log_string =  "LOG FOR ".$date_string."\n";

if (empty($canvas_URL)) {
    echo 'You missed something on the Form<br />';
    echo '<a href="index.html">Take me back to the form</a><br /><br />';
}

//Get User's Name
$user_is_admin = "false";


$usernameResponse = @file_get_contents('https://'.$canvas_URL.'/api/v1/users/self/profile?&access_token='.$token);
$usernameJSON = json_decode($usernameResponse);
//echo $usernameResponse;
$avatarURL = $usernameJSON->avatar_url;
$username = $usernameJSON->name;
$userID  = $usernameJSON->sis_user_id;
	if (strpos($usernameResponse,'Invalid') !== false) {
	    echo 'Sorry, your token is Invalid. Try another one.';
	}
	else
	{
	echo "Hello $username <br \>";
	echo '<img src='.$avatarURL.'/><br \>';
	
	//Get Account Name
	$accounts = @file_get_contents('https://'.$canvas_URL.'/api/v1/accounts/?&access_token='.$token);
	$accountList =  json_decode($accounts);
	$accountCount = count($accountList);
	if ($accountCount == 0)
	{
		echo "Sorry, Don't have permission to do anything here.<br \><br \>";
		echo '<a href="index.html">Take me back to the form</a>';
	}
	
	for($i=0;$i<$accountCount;$i++)
		{
		$accountName = $accountList[$i]->name;
		$accountID = $accountList[$i]->id;
		//echo "$userID <br \>";
		$adminsResponse = @file_get_contents('https://'.$canvas_URL.'/api/v1/accounts/'.$accountID.'/admins?&access_token='.$token);
			if (strpos($adminsResponse,$userID) !== false) {
			    echo "Status: You are an admin at $accountName <br \><br \>";
			    $user_is_admin = "true";
			    //Run function to release courses
			}
			else
			{
				echo "Sorry, you don't have permission to do anything here<br \><br \>";
				echo "<a href=''index.html''>Take me back to the form</a>";
			}
		}
	}


if ($user_is_admin == "true")
{
echo "Processing Courses. Don't close this window.<br \><br \>";
$course_list_array = explode(",",$items);

$course_count = count($course_list_array);
//echo $course_count;
	for($x=0; $x < $course_count; $x++)
	{
	$course_sis_id = $quarter.$course_list_array[$x];
	//echo "$course_sis_id: ";
	$current_course = @file_get_contents('https://'.$canvas_URL.'/api/v1/courses/sis_course_id:'.$course_sis_id.'?&access_token='.$token);
	$current_courseJSON =  json_decode($current_course);
	$current_course_name = $current_courseJSON->name;
	$current_course_status = $current_courseJSON->workflow_state;
	
	if ($current_course_name == NULL)
	{
		$log_string .= "OH NO! ".$course_sis_id." was not a valid course.\n";
		echo 'OH NO! '.$course_sis_id.' was not a valid course:(<br \>';
		$invalid_course_count++;
	}
	else
	{
	//Change course status language to something a little easier to understand
	if ($current_course_status == "available"){$current_course_status = "published";}
	
	
	if ($current_course_status == "unpublished")
	{
		$log_string .= "$current_course_name is $current_course_status. Publishing...\n"; 
		echo "$current_course_name is $current_course_status. Publishing...<br \>"; 
		$published_course_count++;
	
		//Create Home Page
		CreateFrontPage($canvas_URL,$front_page_body,$course_sis_id,$token);
		SetHomePage($canvas_URL,$course_sis_id,$token);
		HideAllTabs($canvas_URL,$course_sis_id,$token);
		PublishCourse($canvas_URL,$course_sis_id,$token);
		$log_string .= "$current_course_name is now Published. \n"; 
	}
	else
	{
	$log_string .= "$current_course_name is $current_course_status. Skipping...\n"; 	
	echo "$current_course_name is $current_course_status. Skipping...<br \>"; 
	$skipped_course_count++;	
	}
	}
	
	}
echo "And We're done! It's safe to close this window now.<br \><br \>";
echo "<br \><br \>PUBLISHED COURSES: ".$published_course_count."<br \>SKIPPED COURSES: ".$skipped_course_count."<br \>INVALID COURSES: ".$invalid_course_count;
$log_string .= "\n\n\nPUBLISHED COURSES: ".$published_course_count."\nSKIPPED COURSES: ".$skipped_course_count."\nINVALID COURSES: ".$invalid_course_count;
file_put_contents($file_name, $log_string);

	$EmailTo = "ssepp@btc.ctc.edu";
  $EmailFrom = "elearning@btc.ctc.edu";
  $EmailSubject = "Course Publish Log File";


  $separator = md5(time());

  // carriage return type (we use a PHP end of line constant)
  $eol = PHP_EOL;

  // attachment name
 
  $attachment = chunk_split(base64_encode(file_get_contents($file_name)));

  // main header
  $headers  = "From: ".$EmailFrom.$eol;
  $headers .= "MIME-Version: 1.0".$eol; 
  $headers .= "Content-Type: multipart/mixed; boundary=\"".$separator."\"";

  // no more headers after this, we start the body! //

  $body = "--".$separator.$eol;
  $body .= "Content-Transfer-Encoding: 7bit".$eol.$eol;
  $body .= "This is a MIME encoded message.".$eol;

  // message
  $body .= "--".$separator.$eol;
  $body .= "Content-Type: text/html; charset=\"iso-8859-1\"".$eol;
  $body .= "Content-Transfer-Encoding: 8bit".$eol.$eol;
  $body .= $message.$eol;

  // attachment
  $body .= "--".$separator.$eol;
  $body .= "Content-Type: application/octet-stream; name=\"".$file_name."\"".$eol; 
  $body .= "Content-Transfer-Encoding: base64".$eol;
  $body .= "Content-Disposition: attachment".$eol.$eol;
  $body .= $attachment.$eol;
  $body .= "--".$separator."--";

  // send message
  if (mail($EmailTo, $EmailSubject, $body, $headers)) {
  $mail_sent=true;
  echo "<br \><br \>Log file emailed to $EmailTo.";
  } else {
  $mail_sent=false;
  echo "<br \><br \>Error, Mail not sent";

}
}

//FUNCTIONS

//CREATE FRONT PAGE
function CreateFrontPage($canvas_URL,$front_page_body,$course_sis_id,$token)
{
$front_page_settings = array(

'wiki_page'=> array(
'title'=>'Welcome',
'body'=> $front_page_body,
'published' => 'true',
'front_page' => 'true'
));

// make the POST fields
$data_string = json_encode($front_page_settings); 
//echo "$data_string <br \>";

// initialize array
$url = array();
$url = implode('&', $url);

// set up the curl resources
$ch = curl_init();

$curlURL = 'https://'.$canvas_URL.'/api/v1/courses/sis_course_id:'.$course_sis_id.'/pages?&access_token='.$token;
//echo "CURL URL is $curlURL <br \>";


curl_setopt($ch, CURLOPT_URL,$curlURL).PHP_EOL;
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); // note the PUT here
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
    'Content-Type: application/json',                                                                                
    'Content-Length: ' . strlen($data_string)                                                                       
));
     
// execute the request
$output = curl_exec($ch);
//echo "$output<br \>";
if(!$output)
{
echo "API Didn't work - check your code, dummy :( <br \>";
}

// close curl resource to free up system resources
curl_close($ch);

}

//SET COURSE HOME PAGE TO BE WIKI FRONT PAGE
function SetHomePage($canvas_URL,$course_sis_id,$token)
{
$front_page_settings = array(

'course'=> array(
'default_view'=>'wiki'
));

// make the POST fields
$data_string = json_encode($front_page_settings); 
//echo "$data_string <br \>";

// initialize array
$url = array();
$url = implode('&', $url);

// set up the curl resources
$ch = curl_init();

//echo ("https://api.copernica.com/database/$databaseID/profiles/?$url").PHP_EOL;
$curlURL = 'https://'.$canvas_URL.'/api/v1/courses/sis_course_id:'.$course_sis_id.'?&access_token='.$token;
//echo "CURL URL is $curlURL <br \>";


curl_setopt($ch, CURLOPT_URL,$curlURL).PHP_EOL;
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); // note the PUT here
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
    'Content-Type: application/json',                                                                                
    'Content-Length: ' . strlen($data_string)                                                                       
));
     
// execute the request
$output = curl_exec($ch);
//echo "$output<br \>";
if(!$output)
{
echo "API Didn't work - check your code, dummy :( <br \>";
}

// close curl resource to free up system resources
curl_close($ch);

}

//HIDE ALL THE TABS IN THE COURSE
function HideAllTabs($canvas_URL,$course_sis_id,$token)
{
//Get Tabs
$tabs_list = @file_get_contents('https://'.$canvas_URL.'/api/v1/courses/sis_course_id:'.$course_sis_id.'/tabs?&access_token='.$token);
//echo $tabs_list;
$tabsJSONList =  json_decode($tabs_list);
$tabsCount = count($tabsJSONList);
for($t=0;$t<$tabsCount;$t++)
{
$tab_label = $tabsJSONList[$t]->label;
$tab_id = $tabsJSONList[$t]->id;
$tab_hidden = $tabsJSONList[$t]->hidden;
//echo "$t: $tab_label - $tab_id<br \>";

//Hide Tab
$tabhidesettings = array(
    'hidden' => 'true'   
);

// make the POST fields
$data_string = json_encode($tabhidesettings); 

// initialize array
$url = array();
$url = implode('&', $url);

// set up the curl resources
$ch = curl_init();

$curlURL = 'https://'.$canvas_URL.'/api/v1/courses/sis_course_id:'.$course_sis_id.'/tabs/'.$tab_id.'?&access_token='.$token;
//echo "CURL URL is $curlURL";

curl_setopt($ch, CURLOPT_URL,$curlURL).PHP_EOL;
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); // note the PUT here
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
    'Content-Type: application/json',                                                                                
    'Content-Length: ' . strlen($data_string)                                                                       
));       
// execute the request
$output = curl_exec($ch);
//echo $output;
if(!$output)
{
echo "Hiding of $tab_label Didn't work:(";
}
// close curl resource to free up system resources
curl_close($ch);
}




}

//PUBLISH THE COURSE
function PublishCourse($canvas_URL,$course_sis_id,$token)
{
//Update Publish State
$coursepublishsettings = array(
    'offer' => 'true'
);

// make the POST fields
$data_string = json_encode($coursepublishsettings); 

// initialize array
$url = array();
$url = implode('&', $url);

// set up the curl resources
$ch = curl_init();

$curlURL = 'https://'.$canvas_URL.'/api/v1/courses/sis_course_id:'.$course_sis_id.'?&access_token='.$token;
//echo "CURL URL is $curlURL";

curl_setopt($ch, CURLOPT_URL,$curlURL).PHP_EOL;
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); // note the PUT here
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
    'Content-Type: application/json',                                                                                
    'Content-Length: ' . strlen($data_string)                                                                       
));       

// execute the request
$output = curl_exec($ch);

if(!$output)
{
echo "Publish Didn't work:(";
}

// close curl resource to free up system resources
curl_close($ch);

//Get updated Course Publish Status
$published_course = @file_get_contents('https://'.$canvas_URL.'/api/v1/courses/sis_course_id:'.$course_sis_id.'?&access_token='.$token);
$published_courseJSON =  json_decode($published_course);
$published_course_name = $published_courseJSON->name;
$published_course_status = $published_courseJSON->workflow_state;
echo "$published_course_name is now $published_course_status <br \>"; 

}


?>