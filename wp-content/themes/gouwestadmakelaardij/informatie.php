<?php
/*
Template Name: contact
*/
?>



<section>
<div class="container">
    <div class="row-fluid">
        <div class="span8">    
            <?php
                   //init variables
                   $cf = array();
                   $sr = false;
            
                   if(isset($_SESSION['cf_returndata'])){
                     $cf = $_SESSION['cf_returndata'];
                     $sr = true;
                   }
            ?>

            <!-- Error & succes displays above the form -->
            <div id="errors" class="alert alert-error <?php echo ($sr && !$cf['form_ok']) ? 'visible' : ''; ?>">
                      <a class="close" data-dismiss="alert">×</a>

              <ul>
                <li id="info">Er zijn problemen met het door u ingevulde formulier:</li>

                <?php 
                        if(isset($cf['errors']) && count($cf['errors']) > 0) :
                           foreach($cf['errors'] as $formerror) :
                        ?>
                <li><?php echo $formerror ?></li>
                <?php
                           endforeach;
                          endif;
                        ?>
              </ul>
            </div>

            <div id="success" class="alert alert-success <?php echo ($sr && $cf['form_ok']) ? 'visible' : ''; ?>">
                      <a class="close" data-dismiss="alert">×</a>
                <p>Bedankt voor uw bericht. U hoort z.s.m. van ons.</p>
            </div>


            <form method="post" action="http://gouwestadmakelaardij.nl/process.php" class="form-horizontal">
                <legend>Contact formulier</legend>
                <fieldset>
                    <div class="control-group">
                        <label for="formname" class="control-label">Uw naam: <span class="required">*</span></label>
                        <div class="controls">
                            <input type="text" id="name" name="formname" value="<?php echo ($sr && !$cf['form_ok']) ? $cf['posted_form_data']['formname'] : '' ?>" placeholder="Naam" required autofocus />
                        </div>
                    </div>
                    <div class="control-group">
                        <label for="email" class="control-label">Emailadres: <span class="required">*</span></label>
                        <div class="controls">
                            <input type="email" id="email" name="formemail" value="<?php echo ($sr && !$cf['form_ok']) ? $cf['posted_form_data']['formemail'] : '' ?>" placeholder="voorbeeld@gmail.com" required />
                        </div>
                    </div>
                    <div class="control-group">
                        <label for="telephone" class="control-label">Telefoon: </label>
                        <div class="controls">
                            <input type="tel" id="telephone" name="formtelephone" value="<?php echo ($sr && !$cf['form_ok']) ? $cf['posted_form_data']['formtelephone'] : '' ?>" />
                        </div>
                    </div>
                    <div class="control-group">                
                        <label for="message" class="control-label">Bericht: <span class="required">*</span></label>
                        <div class="controls">
                            <textarea id="message" name="formmessage" placeholder="Uw bericht moet minimaal 20 tekens bevatten" required data-minlength="20"><?php echo ($sr && !$cf['form_ok']) ? $cf['posted_form_data']['message'] : '' ?></textarea>
                        </div>
                    </div>
                <span id="loading"></span>
                <input type="submit" value="Verstuur" id="submit-button" class="btn btn-large" />
                <p id="req-field-desc"><span class="required">*</span> Vereist veld</p>
                </fieldset>
            </form>
            <?php unset($_SESSION['cf_returndata']); ?>
     </div>
     <div class="span4">
         <address>
                  <strong>Gouwestad Makelaardij</strong><br>
                  Zuidplaslaan 198<br>
                  2743 CP Waddinxveen<br>
                  <abbr title="Telefoon">T:</abbr> 0182 - 63 60 21
                </address>
                 
                <address>
                  <strong>Email</strong><br>
                  <a href="mailto:#">info@gouwestadmakelaardij.nl</a>
                </address>
        </div>
    </div>
</div>
</section>


<?php
if( isset($_POST) ){
  
  //form validation vars
  $formok = true;
  $formerrors = array();
  
  //sumbission data
  $formipaddress = $_SERVER['REMOTE_ADDR'];
  $formdate = date('d/m/Y');
  $formtime = date('H:i:s');
  
  //form data
  $formname = $_POST['name']; 
  $formemail = $_POST['email'];
  $formtelephone = $_POST['telephone'];
  $formmessage = $_POST['message'];
  
  //validate form data
  
  //validate name is not empty
  if(empty($name)){
    $formok = false;
    $formerrors[] = "U heeft geen naam ingevuld";
  }
  
  //validate email address is not empty
  if(empty($email)){
    $formok = false;
    $formerrors[] = "U heeft geen emailadres ingevuld";
  //validate email address is valid
  }elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
    $formok = false;
    $formerrors[] = "U heeft geen geldig emailadres ingevuld";
  }
  
  //validate message is not empty
  if(empty($message)){
    $formok = false;
    $formerrors[] = "U heeft geen bericht ingevuld";
  }
  //validate message is greater than 20 charcters
  elseif(strlen($message) < 20){
    $formok = false;
    $formerrors[] = "Uw bericht moet minimaal 20 tekens bevatten";
  }
  
  //send email if all is ok.. Add your own email on line 62
  if($formok){
    $formheaders = "from: info@gouwestadmakelaardij.nl" . "\r\n";
    $formheaders .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
    
    $emailbody = "<p>Nieuwe informatieaanvraag gouwestadmakelaardij.nl</p>
            <p><strong>Naam: </strong> {$formname} </p>
            <p><strong>Emailadres: </strong> {$formemail} </p>
            <p><strong>Telefoon: </strong> {$formtelephone} </p>
            <p><strong>Bericht: </strong> {$formmessage} </p>
            <p>This message was sent from the IP Address: {$formipaddress} on {$formdate} at {$formtime}</p>";
    
    mail("info@gouwestadmm.nl","Nieuwe info aanvraag",$emailbody,$formheaders);
    
  }
  
  //what we need to return back to our form
  $returndata = array(
    'posted_form_data' => array(
      'name' => $formname,
      'email' => $formemail,
      'telephone' => $formtelephone,
      'message' => $formmessage
    ),
    'form_ok' => $formok,
    'errors' => $formerrors
  );
    
//if this is not an ajax request
  if(empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'){
    //set session variables
  
    $_SESSION['cf_returndata'] = $returndata;
    
   
  }
}

?>
