<?php

//namespace Lifyzer\Api;
//
//use PHPMailer;

include 'class.phpmailer.php';
//include 'class.smtp.php';

class Email
{
    function sendMail($sender_email_id,$message, $Mailsubject, $userEmailId)
    {
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";   //charset=iso-8859-1
        $headers .= 'From: Lifyzer App' . "\r\n";
        $mail = new PHPMailer();
        $mail->IsSMTP();
        $mail->CharSet = 'UTF-8';
        $mail->Host = "smtp.webfaction.com";
        $mail->SMTPAuth= true;
        $mail->Port = 465; // Or 587
        $mail->Username = "lifyzer"; //SENDER_EMAIL_ID; // GMAIL username
        $mail->Password = '1784Y3))*ScanF0Odapi$'; //SENDER_EMAIL_PASSWORD; // GMAIL password
        $mail->SMTPSecure = 'ssl';
        $mail->From = "hello@lifyzer.com"; //SENDER_EMAIL_ID;
        $mail->FromName= APPNAME;
        $mail->isHTML(true);
        $mail->Subject = $Mailsubject;
        $mail->Body = $message;
        $mail->addAddress($userEmailId);
        if($mail->Send()){
          //  echo "sucsess";
            return true;
        }else {
          //  echo "failed";
            return false;
        }
    }
}


