<?php

/*----------DEFINE-----------*/
define("IP_DELAY", 4);
define("TEMP_DELAY", 15);
define("TEMP_SOGLIA", 65);
define("DDOS_DELAY", 60);
define("POLLING_DELAY", 15);

/*---------TELEGRAM---------*/
$TOKEN = "";
$API_URL = "https://api.telegram.org/bot";
$WHITELIST_ID[""] = TRUE;
$CHAT_ID = ;

/*------------VARIABLES---------*/
$current_t0 = 0;
$current_t1 = 0;
$OFFSET = -1;
$maxtemp = -20;
$mintemp = 100;
$temp_flag = FALSE;


/*main loop*/
while(1){

	/*wait for incoming messages*/
	$message = json_decode(file_get_contents($API_URL.$TOKEN."/getupdates?timeout=".POLLING_DELAY."&offset=$OFFSET"), TRUE);

	/*If the message isn't empty continue, else wait again*/
	if( isset($message["result"][0]) ){

		/*Reset user counter after DDOS_DELAY seconds*/
		$USER_ID = $message["result"][0]["message"]["from"]["id"];
		if( isset($USER_TIMER[$USER_ID]) && time() > $USER_TIMER[$USER_ID]+DDOS_DELAY  ){
				unset($USER_TIMER[$USER_ID]);
				unset($USER_COUNTER[$USER_ID]);
		}
		$flag = ( isset($USER_COUNTER[$USER_ID]) && $USER_COUNTER[$USER_ID] < 6 );
		/*If user made less than 6 request then continue*/
		if( $flag || !isset($USER_COUNTER[$USER_ID]) ){

			/*read and decode the incoming text message*/
			read_incoming_text();

			/*If user id is in whitelist then continue*/
			if( isset($WHITELIST_ID[$USER_ID]) ){
				echo "[AUTORIZZATO] Utente $USER_FIRST_NAME, ID: $USER_ID\n";
				echo "[AUTORIZZATO] Comando: ";
				select_function();
			}
			/*else send warnings*/
			else {
				/*send messages for logs*/
				echo "[   NEGATO  ] Utente $USER_FIRST_NAME , ID: $USER_ID\n";
				echo "[   NEGATO  ] Testo inserito: $TEXT\n";

				/*allocate the counter and increment it*/
				if( isset($USER_COUNTER[$USER_ID]) )
					$USER_COUNTER[$USER_ID]++;
				else
					$USER_COUNTER[$USER_ID] = 1;

				switch($USER_COUNTER[$USER_ID]){
					case 1:
						curl_post("<b>ACCESSO NEGATO</b>",$USER_CHAT_ID);
						$USER_TIMER[$USER_ID] = time();
						break;
					case 2:
						curl_post("Cosa di \"<b>ACCESSO NEGATO</b>\" non ti è chiaro?",$USER_CHAT_ID);
						break;
					case 3:
						curl_post("Forse non capisci l'italiano, te lo ripeto in inglese.
						\n<b>ACCESS DENIED</b>",$USER_CHAT_ID);
						break;
					case 4:
						curl_post("Ok, proviamo in zulu.
						\n<b>ukufinyelela kunqatshelwe</b>",$USER_CHAT_ID);
						break;
					case 5:
						curl_post("Senti $USER_FIRST_NAME , io ci rinuncio, fa quello che vuoi...", $USER_CHAT_ID);
						break;
					default:
						curl_post("Hai esagerato, sei stato <b>bloccato</b> e <b>segnalato</b> all'amministratore", $USER_CHAT_ID);
						echo "[  WARNING  ] L'utente $USER_FIRST_NAME, ID:$USER_ID, e' stato bloccato";
				}
			}
			unset($USER_CHAT_ID);
			unset($USER_FIRST_NAME);
			unset($USER_USERNAME);
			unset($TEXT);
		} else {
			/*update the offset when ignoring messages*/
			$OFFSET = $message["result"][0]["update_id"] + 1;
		}
		unset($USER_ID);
	}

	check_temp($current_t1);


}


/*Selects the right function from given command*/
function select_function(){

	global $USER_CHAT_ID, $external_IP, $TEXT, $current_t0, $maxtemp, $mintemp;

	switch($TEXT){
		case "/ip":
			getip($external_IP , $internal_IP, $current_t0);
			curl_post("IP esterno: $external_IP\nIP interno: $internal_IP" , $USER_CHAT_ID);
			echo "IP\n";
			break;
		case "/temp":
			$temp = gettemp();
			curl_post("Temperatura attuale CPU: $temp °C\n".
					  "Temperatura massima CPU: $maxtemp °C\n".
					  "Temperatura minima  CPU: $mintemp °C\n" , $USER_CHAT_ID);

			echo "Temperature\n";
			break;
		case "/riavvia":
			curl_post("Riavvio..." , $USER_CHAT_ID);
			echo "Restart\n";
			break;
		case "/spegni":
			curl_post("Spengo..." , $USER_CHAT_ID);
			echo "Shutdown\n";
			break;
		case "/start":
			curl_post("Bot avviato" , $USER_CHAT_ID);
			echo "Bot started\n";
			break;
		case "/stato":
			curl_post("Tutto bene, grazie" , $USER_CHAT_ID);
			echo "Status\n";
			break;
		default:
			curl_post("Comando non riconosciuto" , $USER_CHAT_ID);
			echo "Unknow\n";
	}
}


/*Sends a text message via POST method using curl*/
function curl_post($text = "", $chat_ID = 0){
		global $API_URL, $TOKEN;

		$data = array("chat_id" => "$chat_ID", "text" => "$text", "parse_mode" => "HTML");
		$curl = curl_init($API_URL.$TOKEN."/sendmessage");

		curl_setopt($curl, CURLOPT_POST, TRUE);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

		curl_exec($curl);
		curl_close($curl);
}




/*Reads an incoming text message, and updates the offset*/
function read_incoming_text(){

	/* set the global variables */
	global $OFFSET, $USER_ID, $USER_CHAT_ID, $USER_FIRST_NAME, $USER_USERNAME;
	global $TEXT, $message;

	/* read and update the offset */
	$OFFSET = $message["result"][0]["update_id"] + 1;

	/* read the user ID */
	$USER_ID = $message["result"][0]["message"]["from"]["id"];

	/* read the user chat id*/
	$USER_CHAT_ID = $message["result"][0]["message"]["chat"]["id"];

	/* read the first name */
	$USER_FIRST_NAME = $message["result"][0]["message"]["from"]["first_name"];

	/* check if username it's present */
	if( isset($message["result"][0]["message"]["from"]["username"]) )
		/*if yes save it*/
		$USER_USERNAME = $message["result"][0]["message"]["from"]["username"];

	/* check a text message it's present */
	if( isset($message["result"][0]["message"]["text"]) )
		/*if yes save it*/
		$TEXT = $message["result"][0]["message"]["text"];
}




/*Update the min/max temp and send an sms in case of overheat*/
function check_temp(&$t1 = 0){
	global $mintemp, $maxtemp, $CHAT_ID, $temp_flag;

	if( time() >= $t1+TEMP_DELAY ) {
		$t1 = time();
		$temp = gettemp();
		if( $mintemp > $temp )
			$mintemp = $temp;
			elseif( $maxtemp < $temp ){
				$maxtemp = $temp;
				if($maxtemp >= TEMP_SOGLIA){
					echo "[  WARNING  ] Temperatura massima superata ($maxtemp °C)\n";
					curl_post("<b>Temperatura massima superata! ($maxtemp °C)</b>" , $CHAT_ID);
					$temp_flag = TRUE;
				}
			}
		if( $temp_flag &&  $temp < TEMP_SOGLIA){
			echo "[    OK     ] Temperatura normale ($temp °C)\n";
			curl_post("Temperatura normale <b>($temp °C)</b>" , $CHAT_ID);
			$temp_flag = FALSE;
		}
	}
}


/*take the local and external IPV4*/
function getip(&$e_IP = NULL , &$i_IP = NULL, &$t0 = 0){

	if( time() >= $t0+IP_DELAY ) {
		$t0 = time();
		$e_IP = exec("curl -s ifconfig.co");
	}
	$i_IP = exec(' ifconfig | grep -A1  "eth0" | grep -o "inet [0-9]\{1,\}.[0-9]\{1,\}.[0-9]\{1,\}.[0-9]\{1,\}" | grep -o "[0-9]\{1,\}.[0-9]\{1,\}.[0-9]\{1,\}.[0-9]\{1,\}"');
}



/*Get the temperature of CPU and return it as float number*/
function gettemp(){
	return exec('vcgencmd measure_temp | grep -o "[0-9]\{1,\}.[0-9]\{1,\}" ');
}

?>

