<?php

if(!defined('INC_FROM_DOLIBARR')) 
{
	define('INC_FROM_CRON_SCRIPT', true);
	require('../config.php');
}

if (empty($conf->propalrelaunch->enabled)) exit("Module is not enabled.");

dol_include_once('/societe/class/societe.class.php');
dol_include_once('/contact/class/contact.class.php');
dol_include_once('/comm/propal/class/propal.class.php');
dol_include_once('/core/class/CMailFile.class.php');
dol_include_once('/core/lib/files.lib.php');

$TMail = array();
$TErrorMail = array();
$today = date('Y-m-d');
//Requete pour avoir toutes les propals avec une date de relance en date du jour
$sql = 'SELECT p.rowid FROM '.MAIN_DB_PREFIX.'propal p 
		INNER JOIN '.MAIN_DB_PREFIX.'propal_extrafields pe ON (p.rowid = pe.fk_object) 
		WHERE p.entity = '.$conf->entity.' 
		AND pe.date_relance = "'.$db->escape($today).'"';

$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0)
{
	while ($line = $db->fetch_object($resql))
	{
		$contactFound = false;
		$propal = new Propal($db);
		$propal->fetch($line->rowid);
		
		$filename_list = array();
		$mimetype_list = array();
		$mimefilename_list = array();
		
		if (!empty($conf->global->PROPALRELAUNCH_SEND_PDF))
		{
			$ref = dol_sanitizeFileName($propal->ref);
			
			$file = $conf->propal->dir_output . '/' . $ref . '/' . $ref . '.pdf';
			
			$filename = basename($file);
			$mimefile=dol_mimetype($file);
			$filename_list[] = $file;
			$mimetype_list[] = $mimefile;
			$mimefilename_list[] = $filename;
		}
		
		if ($propal->id > 0)
		{
			$TContact = $propal->liste_contact(-1, 'external');
			foreach ($TContact as $TInfo) 
			{
				
				//Contact client suivi proposition => fk_c_type_contact = 41
				if ($TInfo['code'] == 'CUSTOMER')
				{
					$contact = new Contact($db);
					$contact->fetch($TInfo['id']);
					
					$contactFound = true;
					$mail = $TInfo['email'];
					
					if (isValidEmail($mail))
					{
						$msg = $conf->global->PROPALRELAUNCH_MSG_CONTACT;
						
						$prefix = '__CONTACT_';
						$TSearch = $TVal = array();
						foreach ($contact as $attr => $val) 
						{
							if (!is_array($val) && !is_object($val))
							{
								$TSearch[] = $prefix.$attr;
								$TVal[] = $val;	
							}
						}
						
						$msg = str_replace($TSearch, $TVal, $msg);
						
						$TMail[] = $mail;
						
						// Construct mail
						$CMail = new CMailFile(
							'Relance proposition commerciale'
							,$mail
							,$conf->global->MAIN_MAIL_EMAIL_FROM
							,$msg
							,$filename_list
							,$mimetype_list
							,$mimefilename_list
							,'' //,$addr_cc=""
							,'' //,$addr_bcc=""
							,'' //,$deliveryreceipt=0
							,'' //,$msgishtml=0*/
							,$conf->global->MAIN_MAIL_ERRORS_TO
							//,$css=''
						);
							
						// Send mail
						$CMail->sendfile();
						if ($CMail->error) $TErrorMail[] = $CMail->error;
					}
					
				}
			}

			if (!$contactFound)
			{
				$propal->fetch_thirdparty();
				$mail = $propal->thirdparty->email;
				
				if (isValidEmail($mail))
				{
					$msg = $conf->global->PROPALRELAUNCH_MSG_THIRDPARTY;
					
					$prefix = '__THIRDPARTY_';
					$TSearch = $TVal = array();
					foreach ($propal->thirdparty as $attr => $val) 
					{
						if (!is_array($val) && !is_object($val))
						{
							$TSearch[] = $prefix.$attr;
							$TVal[] = $val;	
						}
					}
					
					$msg = str_replace($TSearch, $TVal, $msg);
					
					$TMail[] = $mail;
					
					// Construct mail
					$CMail = new CMailFile(
						'Relance proposition commerciale'
						,$mail
						,$conf->global->MAIN_MAIL_EMAIL_FROM
						,$msg
						,$filename_list
						,$mimetype_list
						,$mimefilename_list
						,'' //,$addr_cc=""
						,'' //,$addr_bcc=""
						,'' //,$deliveryreceipt=0
						,'' //,$msgishtml=0*/
						,$conf->global->MAIN_MAIL_ERRORS_TO
						//,$css=''
					);
						
					// Send mail
					$CMail->sendfile();
					if ($CMail->error) $TErrorMail[] = $CMail->error;
				}
				
			}

		}
		
	}

	echo "liste des mails ok : ";
	var_dump($TMail);
	echo "<br />liste des mails en erreur : ";
	var_dump($TErrorMail);

}
