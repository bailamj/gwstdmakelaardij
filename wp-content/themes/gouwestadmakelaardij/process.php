<?php
if( isset($_POST) ){
	
	//form validation vars
	$formok = true;
	$errors = array();
	
	//sumbission data
	$ipaddress = $_SERVER['REMOTE_ADDR'];
	$date = date('d/m/Y');
	$time = date('H:i:s');
	
	//form data
	$name = $_POST['name'];	
	$email = $_POST['email'];
	$telephone = $_POST['telephone'];
	$message = $_POST['message'];
	
	//validate form data
	
	//validate name is not empty
	if(empty($name)){
		$formok = false;
		$errors[] = "U heeft geen naam ingevuld";
	}
	
	//validate email address is not empty
	if(empty($email)){
		$formok = false;
		$errors[] = "U heeft geen emailadres ingevuld";
	//validate email address is valid
	}elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
		$formok = false;
		$errors[] = "U heeft geen geldig emailadres ingevuld";
	}
	
	//validate message is not empty
	if(empty($message)){
		$formok = false;
		$errors[] = "U heeft geen bericht ingevuld";
	}
	//validate message is greater than 20 charcters
	elseif(strlen($message) < 20){
		$formok = false;
		$errors[] = "Uw bericht moet minimaal 20 tekens bevatten";
	}
	
	//send email if all is ok.. Add your own email on line 62
	if($formok){
		$headers = "from: info@gouwestadmakelaardij.nl" . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
		
		$emailbody = "<p>Nieuwe informatieaanvraag gouwestadmakelaardij.nl</p>
					  <p><strong>Naam: </strong> {$name} </p>
					  <p><strong>Emailadres: </strong> {$email} </p>
					  <p><strong>Telefoon: </strong> {$telephone} </p>
					  <p><strong>Bericht: </strong> {$message} </p>
					  <p>This message was sent from the IP Address: {$ipaddress} on {$date} at {$time}</p>";
		
		mail("info@gouwestadmm.nl","Nieuwe info aanvraag",$emailbody,$headers);
		
	}
	
	//what we need to return back to our form
	$returndata = array(
		'posted_form_data' => array(
			'name' => $name,
			'email' => $email,
			'telephone' => $telephone,
			'message' => $message
		),
		'form_ok' => $formok,
		'errors' => $errors
	);
		
	
	//if this is not an ajax request
	if(empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'){
		//set session variables
		session_start();
		$_SESSION['cf_returndata'] = $returndata;
		
		//redirect back to form
		header('location: ' . $_SERVER['HTTP_REFERER']);
	}
}
