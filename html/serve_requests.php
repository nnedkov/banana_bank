<?php

require('../pdf/mpdf.php');

// configuration variables
$DEBUG_MODE = true;
$SILENT_MODE = true;
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = 'alpha12';
$DB_NAME = 'my_bank';

if ($_SERVER['REQUEST_METHOD'] != 'POST')
	return error('Accepting only POST requests');

if (empty($_POST['action']))
	return set_trans_file();

$action = $_POST['action'];

switch ($action) {
	// CLIENT API
	case 'reg_client':	return reg_client();
	case 'login_client':	return login_client();
	case 'logout_client':	return logout_client();
	case 'get_account_client':	return get_account_client();
	case 'get_trans_client':	return get_trans_client();
	case 'get_trans_client_pdf':	return get_trans_client_pdf();
	case 'get_tancode_id':		return get_tancode_id();
	case 'set_trans_form':	return set_trans_form();
	case 'set_trans_file':	return set_trans_file();
	// EMPLOYEE API
	case 'reg_emp': return reg_emp();
	case 'login_emp':	return login_emp();
	case 'logout_emp':	return logout_emp();
	case 'get_clients':	return get_clients();
	case 'get_account_emp':	return get_account_emp();
	case 'get_trans_emp':	return get_trans_emp();
	case 'get_trans_emp_pdf':	return get_trans_emp_pdf();
	case 'get_trans':	return get_trans();
	case 'approve_trans':	return approve_trans();
	case 'reject_trans':	return reject_trans();
	case 'get_new_users':	return get_new_users();
	case 'approve_user':	return approve_user();
	case 'reject_user':	return reject_user();
	default:		return error('Unknown Action');
}

function error($message) {
	header('Content-Type: application/json');
	$res['status'] = 'false';
	$res['message'] = $message;

	echo json_encode($res);
}

function reg_client() {
	print_debug_message('Checking if email & pass parameters are set...');
	if (empty($_POST['email']) or empty($_POST['pass']))
		return error('Email or password not specified');

	print_debug_message('Sanitizing input...');
	$email = test_input($_POST['email']);
	$pass = test_input($_POST['pass']);

	print_debug_message('Checking if email format is valid...');
     	if (!filter_var($email, FILTER_VALIDATE_EMAIL))
		return error('Invalid email format');

	print_debug_message('Checking if password content is valid...');
	if (!preg_match('/^[a-zA-Z0-9]*$/', $pass))
		return error('Invalid password (only letters and digits are allowed)');

	try {
		$con = get_dbconn();

		print_debug_message('Checking if user with same email exists...');
		$email = mysql_real_escape_string($email);
		$query = 'select * from USERS
			  where email="' . $email . '"';
		$result = mysqli_query($con, $query);

		$num_rows = mysqli_num_rows($result);
		if ($num_rows != 0)
			return error('Existing user with same email');

		print_debug_message('No registered user with same email exists. Inserting new user to db...');
		$pass = mysql_real_escape_string($pass);
		$query = 'insert into USERS (email, password)
			  values ("' . $email . '", "' . $pass . '")';
		$result = mysqli_query($con, $query);

		$num_rows = mysqli_affected_rows($con);
		if ($num_rows == 0)
			return error('Unsuccesfully stored. Please try again');

		close_dbconn($con);

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}

	$res['status'] = 'true';
	$res['message'] = null;

	echo json_encode($res);
}

function login_client() {
	print_debug_message('Checking if email & pass parameters are set...');
	if (empty($_POST['email']) or empty($_POST['pass']))
		return error('Email or password not specified');

	print_debug_message('Sanitizing input...');
	$email = test_input($_POST['email']);
	$pass = test_input($_POST['pass']);

	print_debug_message('Checking if email format is valid...');
     	if (!filter_var($email, FILTER_VALIDATE_EMAIL))
		return error('Invalid email format');

	try {
		$con = get_dbconn();

		print_debug_message('Checking if credentials were correct...');
		$email = mysql_real_escape_string($email);
		$pass = mysql_real_escape_string($pass);
		$query = 'select * from USERS
			  where email="' . $email . '" and
			  password="' . $pass . '" and
			  is_employee=0';
		$result = mysqli_query($con, $query);

		$num_rows = mysqli_num_rows($result);
		if ($num_rows == 0)
			return error('Wrong email or password');

		$rec = mysqli_fetch_array($result);
		if ($rec['is_approved'] == 0)
			return error('Registration not approved yet');

		print_debug_message('Credentials were correct');

		close_dbconn($con);

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}

	session_start();
	session_regenerate_id();
	$_SESSION['email'] = $email;
	$_SESSION['is_employee'] = 'false';
	session_write_close();

	$res['status'] = 'true';
	$res['message'] = null;

	echo json_encode($res);
}

function logout_client() {

	print_debug_message('Checking if parameters were set during login in the session...');
	session_start();
	if (empty($_SESSION['email']) or empty($_SESSION['is_employee']))
		return error('Invalid session');

	if ($_SESSION['is_employee'] == 'true')
		return error('Invalid operation for employee');

	print_debug_message('Removing all session variables...');
	session_unset();

	print_debug_message('Destroying the session...');
	session_destroy();

	$res['status'] = 'true';
	$res['message'] = null;

	echo json_encode($res);
}

function get_account_client() {
	
	print_debug_message('Checking if parameters were set during login 			in the session...');
	session_start();
	if (empty($_SESSION['email']) or empty($_SESSION['is_employee']))
		return error('Invalid session');

	if ($_SESSION['is_employee'] == 'true')
		return error('Invalid operation for employee');

	$email = $_SESSION['email'];
	
	try {
		$con = get_dbconn();

		print_debug_message('Obtaining balance of user...');
		$email = mysql_real_escape_string($email);
		$query = 'select balance from BALANCE
			  where email="' . $email . '"';
		$result = mysqli_query($con, $query);

		$num_rows = mysqli_num_rows($result);
		if ($num_rows == 0)
			return error('email is not registered');
		$row = mysqli_fetch_array($result);			

		close_dbconn($con);

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}

	$res['status'] = "true";
	$res['message'] = null;
	$res['email'] = $email;
	$res['balance'] = $row['balance'];

	echo json_encode($res);
}

function get_trans_client() {

	print_debug_message('Checking if parameters were set during login in the session...');
	session_start();
	if (empty($_SESSION['email']) or empty($_SESSION['is_employee']))
		return error('Invalid session');

	if ($_SESSION['is_employee'] == 'true')
		return error('Invalid operation for employee');

	$email = $_SESSION['email'];

	try {
		$con = get_dbconn();

		print_debug_message('Obtaining transaction records...');
		$email = mysql_real_escape_string($email);
		$query = 'select trans_id, email_src, email_dest, amount, date, is_approved from TRANSACTIONS
			  where email_src="' . $email . '" order by trans_id';
		$result = mysqli_query($con, $query);

		$trans_recs = array();
		while ($rec = mysqli_fetch_array($result)) {
			$trans_rec = array($rec['trans_id'], $rec['email_dest'], $rec['amount'], $rec['date'], $rec['is_approved']);
			array_push($trans_recs, $trans_rec);
		}

		close_dbconn($con);

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}

	$res['status'] = 'true';
	$res['message'] = null;
	$res['trans'] = $trans_recs;

	echo json_encode($res);
}

function get_trans_client_pdf(){
	
	print_debug_message('Checking if parameters were set during login in the session...');
	session_start();
	if (empty($_SESSION['email']) or empty($_SESSION['is_employee']))
		return error('Invalid session');

	if ($_SESSION['is_employee'] == 'true')
		return error('Invalid operation for employee');

	$email = $_SESSION['email'];

	try {
		$con = get_dbconn();

		print_debug_message('Obtaining transaction records...');
		$email = mysql_real_escape_string($email);
		$query = 'select trans_id, email_src, email_dest, amount, date, is_approved from TRANSACTIONS

			  where email_src="' . $email . '" order by trans_id';
		$result = mysqli_query($con, $query);

		$trans_recs = array();
		while ($rec = mysqli_fetch_array($result)) {
			$trans_rec = array($rec['trans_id'], $rec['email_dest'], $rec['amount'], $rec['date'], $rec['is_approved']);
			array_push($trans_recs, $trans_rec);
		}

		close_dbconn($con);

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}

	$res['status'] = 'true';
	$res['message'] = null;
	$res['trans'] = $trans_recs;

$mpdf=new mPDF();

$HTML = output_html($email,$trans_recs);

$mpdf->WriteHTML($HTML);
$mpdf->Output("/var/www/downloads/".$email.".pdf",'F');

}



function get_tancode_id() {
	print_debug_message('Checking if parameters were set during login in the session...');
	session_start();
	if (empty($_SESSION['email']) or empty($_SESSION['is_employee']))
		return error('Invalid session');

	if ($_SESSION['is_employee'] == 'true')
		return error('Invalid operation for employee');

	$email = $_SESSION['email'];
	
	try {
		$con = get_dbconn();

		print_debug_message('Obtaining free tan_code_id of user...');
		$email = mysql_real_escape_string($email);
		$query = 'select trans_code_id from TRANSACTION_CODES where email="' . $email . '" and
			  Is_used=0';
		$result = mysqli_query($con, $query);

		$num_rows = mysqli_num_rows($result);
		if ($num_rows == 0)
			return error('No free tancodes available!');
		$number = rand(0, $num_rows-1);
		if(!mysqli_data_seek($result,$number))
			return error('Something went wrong. Please try again'); 
		$row = mysqli_fetch_array($result);			

		close_dbconn($con);

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}

	$_SESSION['tan_code_id'] = $row['trans_code_id'];
	session_write_close();

	$res['status'] = "true";
	$res['message'] = null;
	$res['tan_code_id'] = $row['trans_code_id'];

	echo json_encode($res);
}

function transfer_money($src,$dst,$amount,$approval){
	$email_src = test_input($src);
	$email_dest = test_input($dst);
	$amount= test_input($amount);
	
	print_debug_message('Checking if source email format is valid...');
     	if (!filter_var($email_src, FILTER_VALIDATE_EMAIL))
		return error('Invalid email format');
	print_debug_message('Checking if destination email format is valid...');
     	if (!filter_var($email_dest, FILTER_VALIDATE_EMAIL))
		return error('Invalid email format');

	try {
		$con = get_dbconn();

		print_debug_message('preparing to execute transaction...');
		$email_src = mysql_real_escape_string($email_src);
		$email_dest = mysql_real_escape_string($email_dest);
		$amount = mysql_real_escape_string($amount);
		
		$query = 'select * from USERS
			 where email="' . $email_dest . '"
			 and is_approved=1 and is_employee = 0';
		$result = mysqli_query($con, $query);

		$num_rows = mysqli_num_rows($result);
		if ($num_rows == 0)
			return error('Destination is not registered or approved');
		
		$query = 'select * from USERS
			 where email="' . $email_src . '"
			 and is_approved=1';
		$result = mysqli_query($con, $query);

		$num_rows = mysqli_num_rows($result);
		if ($num_rows == 0)
			return error('Source is not registered or approved');


		$query = 'select balance from BALANCE
			  where email="' . $email_src . '"';
		$result = mysqli_query($con, $query);

		$num_rows = mysqli_num_rows($result);
		if ($num_rows == 0)
			return error('Somthing went wrong!');
		
		$row = mysqli_fetch_array($result);

		$balance = $row['balance'];
		if($balance < $amount)
			return error('Your current balanace does not allow you to do this transaction!');
		
		print_debug_message('Executing Transaction...');
		if($amount <= 10000 || $approval == 1){
			$is_approved = 1;
	
			print_debug_message('Debiting ' .$amount. ' from Source...');		
			$query = 'update BALANCE set balance= balance - ' .$amount. '
				  where email="' . $email_src . '"';
			$result = mysqli_query($con, $query);

			$num_rows = mysqli_affected_rows($con);
			if ($num_rows == 0)
				return error('Whoops, Something went wrong. Please try again');
		
			print_debug_message('Crediting ' .$amount. ' to Destination...');
		
			$query = 'update BALANCE set balance= balance + ' .$amount. '
				  where email="' . $email_dest . '"';
			$result = mysqli_query($con, $query);
			
			$num_rows = mysqli_affected_rows($con);
			if ($num_rows == 0){
				$query = 'update BALANCE set balance=balance + ' .$amount. '
				  where email="' . $email_src . '"';
				$result = mysqli_query($con, $query);	
			return error('Whoops, Something went wrong. Please try again');
			}
		}else{
			$is_approved = 0;
		}	print_debug_message('Transaction needs approval of Employee...');				

		$query = 'insert into TRANSACTIONS (email_src, email_dest,
			amount, is_approved)
			values ("' . $email_src . '", "' . $email_dest . '", "' . $amount . '",
			 "' . $is_approved . '")';

		$result = mysqli_query($con, $query);
		$num_rows = mysqli_affected_rows($con);
		if ($num_rows == 0){
			if($is_approved == 1){
				$query = 'update BALANCE set balance=balance + ' .$amount. '
				  where email="' . $email_src . '"';
				$result = mysqli_query($con, $query);	
				$query = 'update BALANCE set balance=balance - ' .$amount. '
				  where email="' . $email_dest . '"';
				$result = mysqli_query($con, $query);
			}
			return error('Whoops, Something went wrong. Please try again');
		}

		close_dbconn($con);
		return true;

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}
}

function set_trans_form() {
	print_debug_message('Checking if parameters were set during login in the session...');
	session_start();
	if (empty($_SESSION['email']) or empty($_SESSION['is_employee']))
		return error('Invalid session');

	if ($_SESSION['is_employee'] == 'true')
		return error('Invalid operation for employee');

	$email_src = $_SESSION['email'];
	
	if (empty($_POST['email_dest']))
		return error('Destination email not specified');
	if (empty($_POST['amount']))
		return error('amount not specified');
	if (empty($_POST['tancode_value']))
		return error('TAN code value not specified');
	if (empty($_SESSION['tan_code_id']))
		return error('No tancode ID stored in the session');
	$tancode_id = $_SESSION['tan_code_id'];

	print_debug_message('Sanitizing input...');	
	$tancode_value = $_POST['tancode_value'];
	if(strlen($tancode_value) != 15)
		return error('Tancode length should be 15!');
	
	
	try {
		$con = get_dbconn();

		$tancode_id = mysql_real_escape_string($tancode_id);		
		$query = 'select Is_used from TRANSACTION_CODES where 
			 email= "' . $email_src . '" and
			 trans_code_id= "' . $tancode_id . '" and 
			 trans_code = "' . $tancode_value . '"';
		$result = mysqli_query($con, $query);

		$num_rows = mysqli_num_rows($result);
		if ($num_rows == 0)
			return error('You entered an invalid Tancode!');
		$row = mysqli_fetch_array($result);					
		if($row['Is_used'] != 0)
			return error('You entered an already used tancode!');		
		
		$status = transfer_money($email_src,$_POST['email_dest'],$_POST['amount'],0);
		if($status == true){
			
			$query = 'update TRANSACTION_CODES set Is_used =1
				where email = "'. $email_src . '" and trans_code_id = "'. $tancode_id .'"';
			
			$result = mysqli_query($con, $query);
			$num_rows = mysqli_affected_rows($con);
			if ($num_rows == 0){
				print_debug_message("Code wasn't set to used!");  //TODO:make sure it does!
			}
		} else
			return;
		close_dbconn($con);

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}

	$res['status'] = "true";
	$res['message'] = null;

	echo json_encode($res);
}

function set_trans_file() {
	print_debug_message('Checking if parameters were set during login in the session...');
	session_start();
	if (empty($_SESSION['email']) or empty($_SESSION['is_employee']))
		return error('Invalid session');

	if ($_SESSION['is_employee'] == 'true')
		return error('Invalid operation for employee');

	if (empty($_SESSION['tan_code_id']))
		return error('No tancode ID stored in the session');

	$email_src = $_SESSION['email'];
	$tancode_id = $_SESSION['tan_code_id'];
	$filename = upload_file();
	if($filename == FALSE)
		return;
	print_debug_message("parsing file " .$filename);
	parse_file($filename,$email_src,$tancode_id);
}

function parse_file($filename,$email_src,$tancode_id){

        $handle = popen("./main ".$filename, "r");
				

	$params = array();
	while($s = fgets($handle)) {
		if(ord($s) == 32) //check if empty line
			break;
		$words = str_word_count($s,1,'1234567890!#$%&*+-/@=?^_`{|}~.');		
		array_push($params, $words);				
	}	
	end($params); 
	$key = key($params);
	$value = current($params);
	if(count($value) != 1)
		return error('Uploaded file does not comply with rules! Last line should have only tan code');
	if(strlen($value[0]) != 15)
		return error('Tan code entered is not 15 characters!');
	
	$tancode_value = test_input($value[0]);
	
	try {
		$con = get_dbconn();

		$tancode_id = mysql_real_escape_string($tancode_id);
		$tancode_value = mysql_real_escape_string($tancode_value);		
		
		$query = 'select Is_used from TRANSACTION_CODES where 
			 email= "' . $email_src . '" and
			 trans_code_id= "' . $tancode_id . '" and 
			 trans_code = "' . $tancode_value . '"';
		$result = mysqli_query($con, $query);

		$num_rows = mysqli_num_rows($result);
		if ($num_rows == 0)
			return error('You entered an invalid Tancode!');
		$row = mysqli_fetch_array($result);					
		if($row['Is_used'] != 0)
			return error('You entered an already used tancode!');		
		
		for($i = 0; $i < count($params)-1;$i++){
			$status = transfer_money($email_src,$params[$i][0],$params[$i][1],0);			
		}


		if($status == true){
			
			$query = 'update TRANSACTION_CODES set Is_used =1
				where email = "'. $email_src . '" and trans_code_id = "'. $tancode_id .'"';
			
			$result = mysqli_query($con, $query);
			$num_rows = mysqli_affected_rows($con);
			if ($num_rows == 0){
				print_debug_message("Code wasn't set to used!");  //TODO:make sure it does!
			}
		} else
			return;
		close_dbconn($con);

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}

	$res['status'] = "true";
	$res['message'] = null;

	echo json_encode($res);

}

function upload_file(){

	$target = "/var/www/uploads/{$_FILES['uploadFile']['name']}";
	$upload_ready=1;
	
	// Check file size
	if ($_FILES["uploadFile"]["size"] > 1000) {
	    echo "Sorry, your file is too large.";
	    $upload_ready = 0;
	}
	
	if (!($_FILES["uploadFile"]["type"] == "text/plain")) {
	    echo "Sorry, only text files are allowed.";
	    $upload_ready = 0;
	}
	
	if ($upload_ready == 0) {
	    echo "Your file was not uploaded.";
	    return FALSE;
	} else { 
	    if (move_uploaded_file($_FILES['uploadFile']['tmp_name'],$target)) {
	        print_debug_message("The file ". basename( $_FILES["uploadFile"]["name"]). " has been uploaded.");
	    } else {
	        return error("Whoops something went wrong while trying to upload the file!");
	    }
	}
	return "/var/www/uploads/{$_FILES['uploadFile']['name']}";
}

function reg_emp() {
	print_debug_message('Checking if email & pass parameters are set...');
	if (empty($_POST['email']) or empty($_POST['pass']))
		return error('Email or password not specified');

	print_debug_message('Sanitizing input...');
	$email = test_input($_POST['email']);
	$pass = test_input($_POST['pass']);

	print_debug_message('Checking if email format is valid...');
     	if (!filter_var($email, FILTER_VALIDATE_EMAIL))
		return error('Invalid email format');

	print_debug_message('Checking if password content is valid...');
	if (!preg_match('/^[a-zA-Z0-9]*$/', $pass))
		return error('Invalid password (only letters and digits are allowed)');

	try {
		$con = get_dbconn();

		print_debug_message('Checking if user with same email exists...');
		$email = mysql_real_escape_string($email);
		$query = 'select * from USERS
			  where email="' . $email . '"';
		$result = mysqli_query($con, $query);

		$num_rows = mysqli_num_rows($result);
		if ($num_rows != 0)
			return error('Already used email');

		print_debug_message('No registered user with same email exists. Inserting new user to db...');
		$pass = mysql_real_escape_string($pass);
		$query = 'insert into USERS (email, password, is_employee)
			  values ("' . $email . '", "' . $pass . '", 1)';
		$result = mysqli_query($con, $query);

		$num_rows = mysqli_affected_rows($con);
		if ($num_rows == 0)
			return error('Unsuccesfully stored. Please try again');

		close_dbconn($con);

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}

	$res['status'] = 'true';
	$res['message'] = null;

	echo json_encode($res);
}

function login_emp() {
	print_debug_message('Checking if email & pass parameters are set...');
	if (empty($_POST['email']) or empty($_POST['pass']))
		return error('Email or password not specified');

	print_debug_message('Sanitizing input...');
	$email = test_input($_POST['email']);
	$pass = test_input($_POST['pass']);

	print_debug_message('Checking if email format is valid...');
     	if (!filter_var($email, FILTER_VALIDATE_EMAIL))
		return error('Invalid email format');

	try {
		$con = get_dbconn();

		print_debug_message('Checking if credentials were correct...');
		$email = mysql_real_escape_string($email);
		$pass = mysql_real_escape_string($pass);
		$query = 'select * from USERS
			  where email="' . $email . '" and
			  password="' . $pass . '" and
			  is_employee=1';
		$result = mysqli_query($con, $query);

		$num_rows = mysqli_num_rows($result);
		if ($num_rows == 0)
			return error('Wrong email or password');

		$rec = mysqli_fetch_array($result);
		if ($rec['is_approved'] == 0)
			return error('Registration not approved yet');

		print_debug_message('Credentials were correct');

		close_dbconn($con);

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}

	session_start();
	session_regenerate_id();
	$_SESSION['email'] = $email;
	$_SESSION['is_employee'] = 'true';
	session_write_close();

	$res['status'] = 'true';
	$res['message'] = null;

	echo json_encode($res);
}

function logout_emp() {

	print_debug_message('Checking if parameters were set during login in the session...');
	session_start();
	if (empty($_SESSION['email']) or empty($_SESSION['is_employee']))
		return error('Invalid session');

	if ($_SESSION['is_employee'] == 'false')
		return error('Unauthorized operation for client');

	print_debug_message('Removing all session variables...');
	session_unset();

	print_debug_message('Destroying the session...');
	session_destroy();

	$res['status'] = 'true';
	$res['message'] = null;

	echo json_encode($res);
}

function get_clients() {
	print_debug_message('Checking if parameters were set during login in the session...');
	session_start();
	if (empty($_SESSION['email']) or empty($_SESSION['is_employee']))
		return error('Invalid session');

	if ($_SESSION['is_employee'] == 'false')
		return error('Invalid operation for client');

	$email = $_SESSION['email'];

	try {
		$con = get_dbconn();

		print_debug_message('Obtaining List of all clients...');
		$query = 'select email from USERS
			  where is_employee = 0 and is_approved = 1';
		$result = mysqli_query($con, $query);

		$clients = array();
		while ($rec = mysqli_fetch_array($result)) {
			$client = $rec['email'];
			array_push($clients, $client);
		}

		close_dbconn($con);

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}

	$res['status'] = "true";
	$res['message'] = null;
	$res['clients'] = $clients;

	echo json_encode($res);

}

function get_account_emp() {
	print_debug_message('Checking if parameters were set during login in the session...');
	session_start();
	if (empty($_SESSION['email']) or empty($_SESSION['is_employee']))
		return error('Invalid session');

	if ($_SESSION['is_employee'] == 'false')
		return error('Invalid operation for client');

	print_debug_message('Sanitizing input...');
	$email = test_input($_POST['email']);

	print_debug_message('Checking if email format is valid...');
     	if (!filter_var($email, FILTER_VALIDATE_EMAIL))
		return error('Invalid email format');

	try {
		$con = get_dbconn();

		print_debug_message('Obtaining balance of user...');
		$email = mysql_real_escape_string($email);
		$query = 'select balance from BALANCE
			  where email="' . $email . '"';
		$result = mysqli_query($con, $query);

		$num_rows = mysqli_num_rows($result);
		if ($num_rows == 0)
			return error('email is not registered');
		$row = mysqli_fetch_array($result);			

		close_dbconn($con);

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}

	$res['status'] = "true";
	$res['message'] = null;
	$res['balance'] = $row['balance'];

	echo json_encode($res);
}

function get_trans_emp() {
	print_debug_message('Checking if parameters were set during login in the session...');
	session_start();
	if (empty($_SESSION['email']) or empty($_SESSION['is_employee']))
		return error('Invalid session');

	if ($_SESSION['is_employee'] == 'false')
		return error('Unauthorized operation for client');

	print_debug_message('Checking if email parameter is set...');
	if (empty($_POST['email']))
		return error('Email not specified');

	print_debug_message('Sanitizing input...');
	$email = test_input($_POST['email']);

	print_debug_message('Checking if email format is valid...');
     	if (!filter_var($email, FILTER_VALIDATE_EMAIL))
		return error('Invalid email format');

	try {
		$con = get_dbconn();

		print_debug_message('Obtaining transaction records...');
		$email = mysql_real_escape_string($email);
		$query = 'select trans_id, email_src, email_dest, amount, date, is_approved from TRANSACTIONS
			  where email_src="' . $email . '" order by trans_id';
		$result = mysqli_query($con, $query);

		$trans_recs = array();
		while ($rec = mysqli_fetch_array($result)) {
			$trans_rec = array($rec['trans_id'], $rec['email_dest'], $rec['amount'], $rec['date'], $rec['is_approved']);
			array_push($trans_recs, $trans_rec);
		}

		close_dbconn($con);

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}

	$res['status'] = 'true';
	$res['message'] = null;
	$res['trans'] = $trans_recs;

	echo json_encode($res);
}

function get_trans_emp_pdf() {

	print_debug_message('Checking if parameters were set during login in the session...');
	session_start();
	if (empty($_SESSION['email']) or empty($_SESSION['is_employee']))
		return error('Invalid session');

	if ($_SESSION['is_employee'] == 'false')
		return error('Unauthorized operation for client');

	print_debug_message('Checking if email parameter is set...');
	if (empty($_POST['email']))
		return error('Email not specified');

	print_debug_message('Sanitizing input...');
	$email = test_input($_POST['email']);

	print_debug_message('Checking if email format is valid...');
     	if (!filter_var($email, FILTER_VALIDATE_EMAIL))
		return error('Invalid email format');

	try {
		$con = get_dbconn();

		print_debug_message('Obtaining transaction records...');
		$email = mysql_real_escape_string($email);
		$query = 'select trans_id, email_src, email_dest, amount, date, is_approved from TRANSACTIONS

			  where email_src="' . $email . '" order by trans_id';
		$result = mysqli_query($con, $query);

		$trans_recs = array();
		while ($rec = mysqli_fetch_array($result)) {
			$trans_rec = array($rec['trans_id'], $rec['email_dest'], $rec['amount'], $rec['date'], $rec['is_approved']);
			array_push($trans_recs, $trans_rec);
		}

		close_dbconn($con);

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}

	$res['status'] = 'true';
	$res['message'] = null;
	$res['trans'] = $trans_recs;

$mpdf=new mPDF();

$HTML = output_html($email,$trans_recs);

$mpdf->WriteHTML($HTML);
$mpdf->Output("/var/www/downloads/".$email.".pdf",'F');

}

function get_trans() {
	print_debug_message('Checking if parameters were set during login in the session...');
	session_start();
	if (empty($_SESSION['email']) or empty($_SESSION['is_employee']))
		return error('Invalid session');

	if ($_SESSION['is_employee'] == 'false')
		return error('Unauthorized operation for client');

	try {
		$con = get_dbconn();

		print_debug_message('Obtaining unapproved transaction records...');
		$query = 'select trans_id, email_src, email_dest, amount, date from TRANSACTIONS
			  where is_approved=0
			  and amount>=10000
			  order by trans_id';
		$result = mysqli_query($con, $query);

		$trans_recs = array();
		while ($rec = mysqli_fetch_array($result)) {
			$trans_rec = array($rec['trans_id'], $rec['email_src'], $rec['email_dest'], $rec['amount'], $rec['date']);
			array_push($trans_recs, $trans_rec);
		}

		close_dbconn($con);

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}

	$res['status'] = 'true';
	$res['message'] = null;
	$res['trans'] = $trans_recs;

	echo json_encode($res);
}

function approve_trans() {
	print_debug_message('Checking if parameters were set during login in the session...');
	session_start();
	if (empty($_SESSION['email']) or empty($_SESSION['is_employee']))
		return error('Invalid session');

	if ($_SESSION['is_employee'] == 'false')
		return error('Unauthorized operation for client');

	print_debug_message('Checking if trans_id parameter is set...');
	if (empty($_POST['trans_id']))
		return error('Transaction id not specified');

	print_debug_message('Sanitizing input...');
	$trans_id = test_input($_POST['trans_id']);

	print_debug_message('Checking if transaction id is valid...');
	if (!preg_match('/^[0-9]*$/', $trans_id))
		return error('Invalid transaction id');

	try {
		$con = get_dbconn();

		$trans_id = mysql_real_escape_string($trans_id);
		
		$query = 'select email_src,email_dest,amount from TRANSACTIONS
			  where trans_id="' . $trans_id . '"';
		$result = mysqli_query($con, $query);
		$num_rows = mysqli_num_rows($result);
		if ($num_rows == 0)
			return error('Non existing transaction with the specified id');
		$row = mysqli_fetch_array($result);
		
		$status = transfer_money($row['email_src'],$row['email_dest'],$row['amount'],1);

		if($status == true) {
			$query = 'update TRANSACTIONS set is_approved=1
				  where trans_id="' . $trans_id . '"';
			$result = mysqli_query($con, $query);
			$num_rows = mysqli_affected_rows($con);
			if ($num_rows == 0)
				return error('Non existing transaction with the specified id');
		} else
			return;

		close_dbconn($con);

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}

	$res['status'] = "true";
	$res['message'] = null;

	echo json_encode($res);
}

function reject_trans() {
	print_debug_message('Checking if parameters were set during login in the session...');
	session_start();
	if (empty($_SESSION['email']) or empty($_SESSION['is_employee']))
		return error('Invalid session');

	if ($_SESSION['is_employee'] == 'false')
		return error('Unauthorized operation for client');

	print_debug_message('Checking if trans_id parameter is set...');
	if (empty($_POST['trans_id']))
		return error('Transaction id not specified');

	print_debug_message('Sanitizing input...');
	$trans_id = test_input($_POST['trans_id']);

	print_debug_message('Checking if transaction id is valid...');
	if (!preg_match('/^[0-9]*$/', $trans_id))
		return error('Invalid transaction id');

	try {
		$con = get_dbconn();

		$trans_id = mysql_real_escape_string($trans_id);
		$query = 'delete from TRANSACTIONS
			  where trans_id="' . $trans_id . '"
			  and is_approved=0';
		$result = mysqli_query($con, $query);

		$num_rows = mysqli_affected_rows($con);
		if ($num_rows == 0)
			return error('Non existing transaction with the specified id');

		close_dbconn($con);

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}

	$to = $email;
	$subject = 'Transaction in Banana bank';
	$txt = 'Dear Madame/Sir,\r\n we inform you that your transaction in Banana bank was not approved.';
	$headers = 'From: admin@bananabank.de';
	mail($to, $subject, $txt, $headers);

	$res['status'] = 'true';
	$res['message'] = null;

	echo json_encode($res);
}

function get_new_users() {
	print_debug_message('Checking if parameters were set during login in the session...');
	session_start();
	if (empty($_SESSION['email']) or empty($_SESSION['is_employee']))
		return error('Invalid session');

	if ($_SESSION['is_employee'] == 'false')
		return error('Unauthorized operation for client');

	try {
		$con = get_dbconn();

		print_debug_message('Obtaining new users...');
		$query = 'select email, is_employee from USERS
			  where is_approved=0';
		$result = mysqli_query($con, $query);

		$new_users = array();
		while ($rec = mysqli_fetch_array($result)) {
			$user_type = $rec['is_employee'] == 1 ? 'employee' : 'client';
			$new_user = array($rec['email'], $user_type);
			array_push($new_users, $new_user);
		}

		close_dbconn($con);

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}

	$res['status'] = 'true';
	$res['message'] = null;
	$res['new_users'] = $new_users;

	echo json_encode($res);
}

function approve_user() {
	print_debug_message('Checking if parameters were set during login in the session...');
	session_start();
	if (empty($_SESSION['email']) or empty($_SESSION['is_employee']))
		return error('Invalid session');

	if ($_SESSION['is_employee'] == 'false')
		return error('Unauthorized operation for client');

	print_debug_message('Checking if email parameter is set...');
	if (empty($_POST['email']))
		return error('Email not specified');

	print_debug_message('Sanitizing input...');
	$email = test_input($_POST['email']);

	print_debug_message('Checking if email format is valid...');
     	if (!filter_var($email, FILTER_VALIDATE_EMAIL))
		return error('Invalid email format');
	try {
		$con = get_dbconn();

		print_debug_message('Approving new user...');
		$email = mysql_real_escape_string($email);
		$query = 'update USERS set is_approved=1
			  where email="' . $email . '" and
			  is_approved=0';
		$result = mysqli_query($con, $query);

		$num_rows = mysqli_affected_rows($con);
		if ($num_rows == 0)
			return error('Non existing user with the specified email');

		$query = 'select is_employee from USERS where email ="'.$email.'"';
		$result = mysqli_query($con,$query);
		$num_rows = mysqli_affected_rows($con);
		if ($num_rows == 0)
			return error('Non existing user with the specified email');
		$row = mysqli_fetch_array($result);
		if($row['is_employee'] == 0){
			$codes = array();
			for($i = 0 ; $i < 100 ; $i++){
				$codes[$i]['value'] = uniqid(chr(mt_rand(97,122)).chr(mt_rand(97,122)));
				$query = 'insert into TRANSACTION_CODES (trans_code,email)
				 values ("'.$codes[$i]['value'].'","'.$email.'")';
				$result = mysqli_query($con,$query);
				$num_rows = mysqli_affected_rows($con);
				if ($num_rows == 0)
					return error('Whoops, something went wrong while adding tancode');
				$query = "SELECT LAST_INSERT_ID()";
				$result = mysqli_query($con,$query);
				$row = mysqli_fetch_array($result);
				$codes[$i]['ID'] = $row[0];
			}
				
		send_codes($codes,$email);
		$query = 'insert into BALANCE (email, balance)
			  values ("' . $email . '", ' . rand(1000, 15000) . ')';
		$result = mysqli_query($con, $query);

		$num_rows = mysqli_affected_rows($con);
		if ($num_rows == 0)
			return error("Can't add money to user!. Please try again");

		}		
		close_dbconn($con);

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}

	$res['status'] = 'true';
	$res['message'] = null;

	echo json_encode($res);
}

function send_codes($codes,$dest){

	$to = "$dest";
	$subject = "Your TAN codes";
	$HTML = '<!DOCTYPE html>
		<html>
		<head>
		<style>
		table, th, td {
		    border: 1px solid black;
		}
		</style>
		</head>
		<body>

		<h2 align="center"> Banana Bank </h2>

		<p>We would like to welcome you to our family. The Banana Bank family!</p>
		<p> At Banana Bank, we care about the safety of your bananas. That\'s why we have sent you TAN codes that will help us make sure that nobody can access your bananas except you!
Keep these TAN codes safe, and don\'t show them to anyone! You will be asked to enter one each time you make a transaction.</p>

		<table style="width:40%">
		<tr>
		<th>TAN Code ID</th>
		<th>TAN Code</th> 
		</tr>';
	for($i = 0 ; $i <count($codes) ; $i++){	
		$HTML = $HTML . '<tr>';
		$HTML = $HTML . '<td align="left">' . $codes[$i]['ID'] . '</td>';
		$HTML = $HTML . '<td align="left">' . $codes[$i]['value'] . '</td>';
		$HTML = $HTML . '</tr>';
	}
	$HTML = $HTML . '</table>';	
	$HTML = chunk_split(base64_encode($HTML));
	$header = "From:e.hazbon@gmail.com\r\n";
	$header .= "MIME-Version: 1.0\r\n";
	$header .= "Content-Transfer-Encoding: base64\r\n";
	$header .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
	$retval = mail ($to,$subject,$HTML,$header);
	return $retval;
}

function reject_user() {
	print_debug_message('Checking if parameters were set during login in the session...');
	session_start();
	if (empty($_SESSION['email']) or empty($_SESSION['is_employee']))
		return error('Invalid session');

	if ($_SESSION['is_employee'] == 'false')
		return error('Unauthorized operation for client');

	print_debug_message('Checking if email parameter is set...');
	if (empty($_POST['email']))
		return error('Email not specified');

	print_debug_message('Sanitizing input...');
	$email = test_input($_POST['email']);

	print_debug_message('Checking if email format is valid...');
     	if (!filter_var($email, FILTER_VALIDATE_EMAIL))
		return error('Invalid email format');

	try {
		$con = get_dbconn();

		print_debug_message('Rejecting new user...');
		$email = mysql_real_escape_string($email);
		$query = 'delete from USERS
			  where email="' . $email . '" and
			  is_approved=0';
		$result = mysqli_query($con, $query);

		$num_rows = mysqli_affected_rows($con);
		if ($num_rows == 0)
			return error('Non existing user with the specified email');

		close_dbconn($con);

	} catch (Exception $e) {
		print_debug_message('Exception occured: ' . $e->getMessage());
		return error('Something went wrong. Please try again');
	}
	
	$to = $email;
	$subject = 'Registration to Banana bank';
	$txt = 'Dear Madam/Sir,\r\n we inform you that your registration to Banana bank was not approved.';
	$header = "From:noreply@mybank.de \r\n";
	mail ($to,$subject,$txt,$header);

	$res['status'] = 'true';
	$res['message'] = null;

	echo json_encode($res);
}

function print_debug_message($message) {
	global $DEBUG_MODE;
	global $SILENT_MODE;
	if ($DEBUG_MODE and $SILENT_MODE)
		error_log($message . '\n', 3, '/var/tmp/my-errors.log');
	elseif ($DEBUG_MODE)
		echo $message . '<br>';
}

function test_input($input) {
	$input = trim($input);
	$input = stripslashes($input);
	$input = htmlspecialchars($input);

	return $input;
}

function close_dbconn($con) {
	if ($con == null)
		return;
	print_debug_message('Closing MySQL connection...');
	mysqli_close($con);
	print_debug_message('Closed MySQL connection.');
}

function get_dbconn() {
	global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

	print_debug_message('Establishing new MySQL connection...');
	$con = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

	if (mysqli_connect_errno()) {
		print_debug_message('Failed to connect to MySQL!' .  mysqli_connect_error());
		return error('Failed to connect to database');
	}
	print_debug_message('Established new MySQL connection.');

	return $con;
}


function output_html($email, $trans_recs) {

	$HTML = '<!DOCTYPE html>
		<html>
		<head>
		<style>
		table, th, td {
		    border: 1px solid black;
		}
		</style>
		</head>
		<body>

		<img style="vertical-align: top" src="./images/BananaBankLogo2.jpg" width="80" />
		<h2 align="center"> Banana Bank </h2>

		<p> Transaction history of ' . $email . ' as of '. date("Y/m/d") . ' ' . date("h:i:s") . '<br></p>

		<table style="width:100%">
		<tr>
		<th>ID</th>
		<th>Destination</th> 
		<th>Amount</th>
		<th>Date</th>
		<th>Status</th>
		</tr>';

	$arrlength = count($trans_recs);
	for ($i=0 ; $i<$arrlength ; $i++) {
		$trans_rec = $trans_recs[$i];
		$HTML = $HTML . '<tr>';
		$HTML = $HTML . '<td align="center">' . $trans_rec[0] . '</td>';
		$HTML = $HTML . '<td align="left">' . $trans_rec[1] . '</td>';
		$HTML = $HTML . '<td align="right">' . $trans_rec[2] . '</td>';
		$HTML = $HTML . '<td align="center">' . $trans_rec[3] . '</td>';
		$is_approved = $trans_rec[4] == 0 ? 'not approved yet' : 'approved';
		$HTML = $HTML . '<td align="center">' . $is_approved . '</td>';
		$HTML = $HTML . '</tr>';
	}
	$HTML = $HTML . '</table>';

	return $HTML;
}


?>


