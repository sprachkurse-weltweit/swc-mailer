<?php

require $path_to_backend . 'SWC-Mailer/handlebars-php/src/Handlebars/Autoloader.php';
require $path_to_backend . 'PHPMailer/src/Exception.php';
require $path_to_backend . 'PHPMailer/src/PHPMailer.php';
require $path_to_backend . 'PHPMailer/src/SMTP.php';

Handlebars\Autoloader::register();

use Handlebars\Handlebars;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$env = require $path_to_backend . 'SWC-Mailer/env.php';

$template_de = file_get_contents($path_to_backend . 'SWC-Mailer/enroll_de.hbs');
$template_en = file_get_contents($path_to_backend . 'SWC-Mailer/enroll_en.hbs');

// honeypot bot trap
if(array_key_exists('email', $_POST) && !empty($_POST['email'])) {
  echo 'Thank you for your request!';
  sleep(5);
  exit;
}

if (!array_key_exists('email_from', $_POST)) {    // checks if 'email_from' exists

	// echo "Mailer Error: " . $mail->ErrorInfo;  // uncomment to DEBUG

	die('Error: "email_from" field not set!');  // kill script
}

composeMail("DE");                         // send german mail
composeMail("EN");                        // send english mail
autoRespond();                           // send auto-responder mail

echo "<script>window.location.href='" . $redirect . "';</script>"; 
exit; // redirect -> back to homepage -> exit script

// setup SMTP
function setupSMTP($mail, $from_name) {
  global $env;
  $mail->isSMTP();
  $mail->Host       = $env['smtp_host'];
  $mail->SMTPAuth   = true;
  $mail->Username   = $env['smtp_email'];
  $mail->Password   = $env['smtp_password'];
  $mail->SMTPSecure = 'ssl';
  $mail->Port       = 465;

  $mail->setFrom($env['smtp_email'], $from_name ? $from_name : $env['smtp_email']);
  $mail->CharSet = 'UTF-8';
}

function composeMail($lang){
  global $_POST, $env, $send_to, $school_name, $template_de, $template_en, $curry_id;

  $template = ($lang == "DE") ? $template_de : $template_en;
  $post_type = ($lang == "DE") ? "Buchung" : "Booking";

  $firstname = htmlspecialchars($_POST['firstname']);
  $lastname = htmlspecialchars($_POST['lastname']);
  $location = htmlspecialchars($_POST['location']);

  $handlebars = new Handlebars();
  $form_array = array();
  $form_array['extras'] = array();

  $mail = new PHPMailer;

  $from_name = strtoupper($post_type);

  setupSMTP($mail, $from_name);

  $mail->addAddress($send_to);

  if (!$mail->addReplyTo($_POST['email_from'])) {    // email validation 

    // echo "Mailer Error: " . $mail->ErrorInfo;   // uncomment to DEBUG
  
    die("<div style='color: red;'>Ung&uuml;ltige Email-Adresse.</div><br /> Bitte geben Sie eine g&uuml;ltige Email-Adresse ein.<br /><a href='javascript: history.back(-1)'>Zur&uuml;ck</a>");

  }

  // use curry id to get school email reply to adress from curry db
  // (english version only)
  if($lang == "EN"){
    // clear all reply-tos
    $mail->ClearReplyTos();
    if(isset($curry_id) && $curry_id){
      // get school email adress from curry db
      $curry_data = url_get_contents($env['curry_api_base_url'] . $curry_id);
      if($curry_data) {
        $json = json_decode($curry_data);
        if($json && isset($json->email)) {
          $school_email = $json->email;
          if($school_email){
            // set adress as reply to
            $mail->addReplyTo($school_email, $school_name);
          }
        }
      }
    }
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

  // add school name to main array
  $form_array['school_name'] = $school_name;

  // add key value pairs to main array
  foreach($_POST as $name => $value) {
    // extra fields
    if(strpos($name, '*') !== false){
      // format: split @ * -> replace _ with space -> uppercase words
      $base = ($lang == "DE") ? explode('*',$name)[1] : explode('*',$name)[0];
      $formated = ucwords(str_replace("_", " ", $base));
      // add formated string to array
      array_push($form_array['extras'], array(
        "key" => $formated,
        "val" => $value
      ));
    }
    // values that need translation
    elseif(in_array($name, ["gender", "level"])) {
      $form_array[$name] = ($lang == "DE") ? formatVal($value)[0] : formatVal($value)[1];
    }
    // normal fields
    else {
      $form_array[$name] = $value;
    }
  }

  // render template and set mail body
  $mail->Body = $handlebars->render($template, $form_array);

  /*************** PLAIN TEXT FALLBACK *****************/

  $plain_array = $form_array;

  // format extras
  foreach($plain_array["extras"] as $extra){
    $plain_array[$extra["key"]] = $extra["val"];
  }

  // delete unneccessary keys
  $unsetList = array("no_accommodation", "no_transfer", "agb_checked", "privacy_policy_checked", "extras", "school_name", "location");

  foreach($unsetList as $item){
    unset($plain_array[$item]);
  }

  // create plain text mail body
  $plainBody = "Booking: " . $school_name;
  if(strlen($location)){
    $plainBody .= " " . $location;
  }
  $plainBody .= "\n\n";
  $maxlen = 0;

  // check the longest name input
  foreach($plain_array as $name => $value) {
    $nlen = strlen($name);
    if ($nlen > $maxlen) {$maxlen = $nlen;}
  }

  // insert key/value list
  foreach($plain_array as $name => $value) {
    $nlen = strlen($name);
    $name = ucwords(preg_replace('/_/', ' ', htmlspecialchars($name)));

    $fill = str_repeat('.', $maxlen - $nlen);

    if(is_array($value)) {
      $plainBody .= $name . "$fill...: ";
      $plainBody .= htmlspecialchars(implode(", ", $value)) . "\n";
    }
    else {
      $plainBody .= $name . "$fill...: " . htmlspecialchars($value) . "\n";
    }
    
  }

  // set mail alt body
  $mail->AltBody = $plainBody;

  /****************** ERRORS *****************/

  // send the message, check for errors
  if (!$mail->send()) { 

    // echo "Mailer Error: " . $mail->ErrorInfo; // uncomment to DEBUG

    die("Leider konnte Ihre Email nicht zugestellt werden. Das tut uns leid! <br />Bitte versuchen Sie es zu einem sp&auml;teren Zeitpunkt noch einmal oder kontaktieren Sie uns telefonisch unter +49 (0)9473 951 550.<br /><br />");

  }
}

/****************** CURL API *****************/

function url_get_contents ($Url) {
  if (!function_exists('curl_init')){ 
      return FALSE;
  }
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $Url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $output = curl_exec($ch);
  curl_close($ch);
  return $output;
}

/**************** VALUE FORMATING ************/

function formatVal($val){
  switch($val){
    // gender
    case "male": return ["Herr", "Mr"];
    case "female": return ["Frau", "Ms"];
    case "div": return ["Divers", "Diverse"];
    // level
    case "A0": return ["Absoluter Anfänger (A0)", "Complete Beginner (A0)"];
    case "A1": return ["Geringe Kenntnisse (A1)", "Beginner (A1)"];
    case "A2": return ["Grundlagen (A2)", "Elementary (A2)"];
    case "B1": return ["Durchschnittlich (B1)", "Intermediate (B1)"];
    case "B2": return ["Gut (B2)", "High (B2)"];
    case "C1_C2": return ["Fortgeschritten (C1/C2)", "Advanced (C1/C2)"];
    default: return [$val, $val];
  }
}

/*************** AUTO RESPONDER *************/

function autoRespond(){
  global $_POST, $school_name, $path_to_backend;

  $location = htmlspecialchars($_POST['location']);

  $mail = new PHPMailer;
  
  setupSMTP($mail, null);

  $mail->addAddress(htmlspecialchars($_POST['email_from']));
  
  $mail->addEmbeddedImage($path_to_backend . 'SWC-Mailer/img/logo.png', 'logo');
  $mail->addEmbeddedImage($path_to_backend . 'SWC-Mailer/img/check.png', 'check');
  
  $signup_respond = $path_to_backend . 'SWC-Mailer/signup_respond.html';
  
  $ar_subject = "Anmeldung zum Sprachkurs bei " . $school_name;
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
