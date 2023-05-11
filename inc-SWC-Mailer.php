<?php

require $path_to_backend . 'SWC-Mailer/handlebars-php/src/Handlebars/Autoloader.php';
require $path_to_backend . 'PHPMailer/src/Exception.php';
require $path_to_backend . 'PHPMailer/src/PHPMailer.php';

Handlebars\Autoloader::register();

use Handlebars\Handlebars;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$template_de = file_get_contents($path_to_backend . 'SWC-Mailer/enroll_de.hbs');
$template_en = file_get_contents($path_to_backend . 'SWC-Mailer/enroll_en.hbs');

if (!array_key_exists('email_from', $_POST)) {    // checks if 'email_from' exists

	// echo "Mailer Error: " . $mail->ErrorInfo;  // uncomment to DEBUG

	die('Error: "email_from" field not set!');  // kill script
}

composeMail("Buchung", $template_de);      // send german mail
composeMail("Booking", $template_en);     // send english mail
autoRespond();                           // send auto-responder mail
header("Location: " . $redirect);       // redirect -> back to homepage

function composeMail($post_type, $template){
  global $_POST, $send_to, $school_name, $path_to_backend, $redirect;

  $firstname = htmlspecialchars($_POST['firstname']);
  $lastname = htmlspecialchars($_POST['lastname']);
  $send_from = htmlspecialchars($_POST['email_from']);
  $location = htmlspecialchars($_POST['location']);

  $handlebars = new Handlebars();
  $form_array = array();
  $form_array['extras'] = array();

  $mail = new PHPMailer;

  $mail->isMail();
  $mail->CharSet = 'UTF-8';

  $mail->setFrom($send_to, '', false);

  $mail->addAddress($send_to);

  if (!$mail->addReplyTo($_POST['email_from'])) {    // email validation 

    // echo "Mailer Error: " . $mail->ErrorInfo;   // uncomment to DEBUG
  
    die("<div style='color: red;'>Ung&uuml;ltige Email-Adresse.</div><br /> Bitte geben Sie eine g&uuml;ltige Email-Adresse ein.<br /><a href='javascript: history.back(-1)'>Zur&uuml;ck</a>");

  }
    
  $subject = $post_type . ": " . $school_name;
  if(strlen($firstname) && strlen($lastname)) {
    $subject = $lastname . ", " . $firstname . ", " . $post_type . " " . $school_name;
    if(strlen($location)){
      $subject .= " " . $location;
    }
  }

  $mail->Subject = $subject;

  /*********** HTML ***********/

  $mail->isHTML(true);

  // add key value pairs to main array
  foreach($_POST as $name => $value) {
    // extra fields
    if(strpos($name, '*') !== false){
      array_push($form_array['extras'], array(
        "en" => explode('*',$name)[0],
        "de" => explode('*',$name)[1],
        "val" => $value
      ));
    }
    // normal fields
    else {
      $form_array[$name]=$value;
    }
  }
  // add school name to main array
  $form_array['school_name']=$school_name;

  // render template and set mail body
  $mail->Body = $handlebars->render($template, $form_array);

  // no plain text fallback yet
  # $mail->AltBody = $PLAIN;

  /****************** ERRORS *****************/

  // send the message, check for errors
  if (!$mail->send()) { 

    // echo "Mailer Error: " . $mail->ErrorInfo; // uncomment to DEBUG

    die("Leider konnte Ihre Email nicht zugestellt werden. Das tut uns leid! <br />Bitte versuchen Sie es zu einem sp&auml;teren Zeitpunkt noch einmal oder kontaktieren Sie uns telefonisch unter +49 (0)9473 951 550.<br /><br />");

  }
}

/*************** AUTO RESPONDER *************/

function autoRespond(){
  global $_POST, $send_to, $school_name, $path_to_backend, $redirect;

  $location = htmlspecialchars($_POST['location']);

  $mail = new PHPMailer;

  $mail->isMail();
  $mail->CharSet = 'UTF-8';
  
  $mail->setFrom($send_to, "", false);
  $mail->addReplyTo($send_to);
  $mail->addAddress(htmlspecialchars($_POST['email_from']));
  
  $mail->addEmbeddedImage($path_to_backend . 'SWC-Mailer/img/logo.png', 'logo');
  $mail->addEmbeddedImage($path_to_backend . 'SWC-Mailer/img/check.png', 'check');
  
  $signup_respond = $path_to_backend . 'SWC-Mailer/signup_respond.html';
  
  $ar_subject = utf8_encode("Anmeldung zum Sprachkurs bei " . $school_name);
  if(strlen($location)){
    $ar_subject .= " " . $location;
  }
  $plain_respond = "Vielen Dank f&uuml;r Ihre Anmeldung! Sie erhalten in K&uuml;rze (normalerweise innerhalb eines Arbeitstages) eine Best&auml;tigung und weitere Informationen per E-Mail.";
  $htmlBody = file_get_contents($signup_respond);
  if(strlen($location)){
    $htmlBody = str_replace('{SCHOOL}', $school_name . " " . $location, $htmlBody);
  }
  else {
    $htmlBody = str_replace('{SCHOOL}', $school_name, $htmlBody);
  }
  $htmlBody = str_replace('check.png', 'cid:check', $htmlBody);
  $htmlBody = str_replace('logo.png', 'cid:logo', $htmlBody);
  
  $mail->Subject = $ar_subject;
  $mail->isHTML(true);
  
  $mail->msgHTML($htmlBody);
  $mail->AltBody = $plain_respond;
  
  /****************** ERRORS *****************/

  // send the message, check for errors
  if (!$mail->send()) {

    // echo "Mailer Error: " . $mail->ErrorInfo; // uncomment to DEBUG

    echo "Leider konnten wir keine automatische Best&auml;tigungs-E-Mail an die von Ihnen angegebene Adresse schicken.<br />Ihre Anmeldung ist jedoch eingegangen und Sie erhalten in K&uuml;rze (normalerweise innerhalb eines Arbeitstages) eine Best&auml;tigung und weitere Informationen per E-Mail.<br><br>Sie werden in 5 Sekunden automatisch weitergeleitet..." ;

    sleep(5);

  }
}

?>