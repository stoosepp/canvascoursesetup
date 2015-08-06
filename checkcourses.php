<?php


$canvas_URL = $_POST['domain'];
$token = $_POST['token'];
$items = $_POST['items'];
$quarter = $_POST['quarter'];
$front_page_body = $_POST['welcomemessage'];
$skipped_course_count = 0;
$published_course_count = 0;
$total_course_count = 0;
$invalid_course_count = 0;

date_default_timezone_set('America/Los_Angeles');

$date_string  = date('l jS \of F Y h:i:s A');
$file_date_string  = date('mdYH:i:s');
echo ($date_string.'<br \><br \>');
$file_name = 'coursestatuslog'.$quarter.$file_date_string.'.txt';

$log_string =  "COURSE STATUS FOR ".$date_string."\n\n";

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
		$total_course_count++;
	}
	else
	{
	//Change course status language to something a little easier to understand
	if ($current_course_status == "available"){$current_course_status = "published";}
	
	
	if ($current_course_status == "unpublished")
	{
		$published_course_count++;
		$total_course_count++;
		echo "$current_course_name is $current_course_status.<br \>";
		$log_string .= "$current_course_status: $current_course_name\n"; 
	
	}
	else
	{	
$log_string .= "$current_course_status: $current_course_name\n"; 
	$skipped_course_count++;
	$total_course_count++;	
	}
	}
	
	}

echo "<br \><br \>UNPUBLISHED COURSES: ".$published_course_count."<br \>PUBLISHED COURSES: ".$skipped_course_count."<br \>INVALID COURSES: ".$invalid_course_count."<br \>TOTAL COURSES: ".$total_course_count;
$log_string .= "\n\n\nUNPUBLISHED COURSES: ".$published_course_count."\nSKIPPED COURSES: ".$skipped_course_count."\nINVALID COURSES: ".$invalid_course_count;

file_put_contents($file_name, $log_string);


}


?>