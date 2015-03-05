#!/usr/bin/php
<?php

$sender_email_default="contact@rack42.fr";
$sender_name_default="Contact Rack42";

function usage() {

	global $sender_email_default;
	global $sender_name_default;


	echo "Utilisation : ./mailing.php ...
 -L : fichier contenant la liste des destinataires
 -P : donnees de publipostage
 -h : fichier contenant le mail au format html
 -t : fichier contenant le mail au format texte
 -s : sujet du mail
 -l : ne fait rien, donne juste la liste des destinataires
 -e : adresse email de l'expediteur (par défaut : ".$sender_email_default.")
 -E : nom de l'expéditeur (apparaitra dans le from du message, par défaut : ".$sender_name_default.")
 -v : active le mode verbeux
";
}

function merge_publi_data($publi_data,$string,$is_html) {
    $result=$string;
    for($i=0;$i<count($publi_data);$i++) {
        $result=preg_replace('/<<<'.($i+1).'>>>/',$publi_data[$i],$result);
    }
    if($is_html) {
        $result=html_entities($result);
    }
    return $result;
}

$options = getopt("L:h:t:s:l:e:E:vP:d");

if(isset($options["e"])) {
	$sender_email=$options["e"];
} else {
	$sender_email=$sender_email_default;
}

if(isset($options["E"])) {
	$sender_name=$options["E"];
} else {
	$sender_name=$sender_name_default;
}

if(isset($options["h"])) {
	$message_html = true;
	$message_file_html = $options["h"];
} else {
	$message_html = false;
}

if(isset($options["t"])) {
	$message_text = true;
	$message_file_text = $options["t"];
} else {
	$message_text = false;
}

if(!isset($message_file_text) && !isset($message_file_html)) {
	echo "Vous devez spécifier au moins une des options h ou t\n";
	usage();
	exit(1);
}

if(!isset($options["L"])) {
	echo "L'option L est obligatoire\n";
	usage();
	exit(1);
}
$to_file = $options["L"];

if(!isset($options["s"])) {
	echo "L'option s est obligatoire\n";
	usage();
	exit(1);
}
$message_subject = $options["s"];

if(isset($options["l"])) {
	$lint=true;
} else {
	$lint=false;
}

if(isset($options["v"])) {
	$verbose=true;
} else {
	$verbose=false;
}

if(isset($options["P"])) {
	$publi_data = array_map('str_getcsv', file($options["P"]));
}

if($message_html) {
	$message_content_html=file($message_file_html);
	if($message_content_html === FALSE) {
		echo "Impossible de lire le contenu du fichier $message_file_html\n";
		exit(1);
	}
	while(list($key,$value)=each($message_content_html)) {
		$body_html.=$value;
	}
	unset($message_content_html);
}
if($message_text) {
	$message_content_text=file($message_file_text);
	if($message_content_text === FALSE) {
		echo "Impossible de lire le contenu du fichier $message_file_text\n";
		exit(1);
	}
	$body_text="";
	while(list($key,$value)=each($message_content_text)) {
		$body_text.=$value;
	}
	unset($message_content_text);
}

$list_dest = array_map('str_getcsv', file($to_file));

if(count($list_dest) == 0) {
	echo "Aucun destinataires selectionnees. Etes vous sur de votre option -L ?\n";
	usage();
	exit(1);
}

if(count($list_dest) != count($publi_data)) {
	echo "Le fichier de donnees de publipostage doit contenir le meme nombre de ligne que le fichier de destinataire\n";
	usage();
	exit(1);
}

if($lint === TRUE) {
	echo "Sujet : $message_subject\n";
	if($message_html) {
		echo "Contenu du message HTML :\n";
		echo $body_html;
		echo "\n";
	}
	echo "Contenu du message texte :\n";
	echo $body_text;
	echo "\n";
	echo "Liste des destinataires :\n";
	print_r($list_dest);
	echo "Donnees de publipostage :\n";
	print_r($publi_data);
	exit(0);
}

$logfile=fopen("mailing.log","a+");
if($logfile === FALSE) {
	echo "Impossible d'ouvrir le fichier de log ...\n";
	exit(1);
}

require_once('/usr/share/php/libphp-phpmailer/class.phpmailer.php');

fwrite($logfile,date("M d H:i:s ")." mailing.php: Sujet : ".$message_subject);

while(list($index,$to)=each($list_dest)) {
    $display_name=$to[0];
    $mail_address=$to[1];
	if($verbose) echo "Preparation du mail pour $display_name <$mail_address>\n";
	fwrite($logfile,date("M d H:i:s ")." mailing.php: Preparation du mail pour ".$display_name." <".$mail_address.">\n");

	$mail = new PHPMailer();
	$mail->CharSet="UTF-8";
	$mail->IsSendmail();
	$mail->SetFrom($sender_email,$sender_name);
	$mail->AddAddress($mail_address,$display_name);
	$mail->Subject = merge_publi_data($publi_data[$index],$message_subject,false);
    $mail->AddCustomHeader('X-Mailer: Rack42 Mailing Scrip');
	if($message_html) {
        $mail->isHTML();
		$mail->MsgHTML(merge_publi_data($publi_data[$index],$body_html,true));
		if($message_text) {
			$mail->AltBody=merge_publi_data($publi_data[$index],$body_text,false);
		}
	} else {
		$mail->Body = merge_publi_data($publi_data[$index],$body_text,false);
	}
	if($mail->Send() === false) {
		if($verbose) echo "Erreur (".$mail->ErrorInfo.") lors de l'envoi du mail pour $display_name <$mail_address>\n";
		fwrite($logfile,date("M d H:i:s ")." mailing.php: Mail envoye avec erreur pour ".$display_name." <".$mail_address.">\n");
		fwrite($logfile,date("M d H:i:s ")." mailing.php: Erreur : ".$mail->ErrorInfo."\n");
	} else {
		if($verbose) echo "Envoi avec succes du mail pour $display_name <$mail_address>\n";
		fwrite($logfile,date("M d H:i:s ")." mailing.php: Mail envoye sans erreur pour ".$display_name." <".$mail_address.">\n");
	}
	unset($mail);
	
}

exit(0);

?>
