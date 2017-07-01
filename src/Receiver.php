<?php

namespace Telegram;

class Receiver {

	function __construct($uid = NULL, $key = NULL, $name = NULL){
		$this->user = new User(NULL);
		$this->chat = new Chat(NULL);

		$this->process();
		if(!empty($uid)){
			if($uid instanceof Bot){
				$this->bot = $uid;
			}else{
				$this->set_access($uid, $key, $name);
			}
		}
		$this->send = new Sender($this);
	}

	private $raw;
	private $data = array();
	public $bot = array();
	public $key = NULL;
	public $id = NULL;
	public $message = NULL; // DEPRECATED
	public $message_id = NULL;
	public $timestamp = 0;
	public $chat = NULL;
	public $user = NULL;
	public $language = NULL;
	public $entities = NULL;
	public $reply = NULL;
	public $new_user = NULL;
	public $new_users = array();
	public $left_user = NULL;
	public $reply_user = NULL;
	public $has_reply = FALSE;
	public $has_forward = FALSE;
	public $is_edit = FALSE;
	public $edit_date = NULL;
	public $reply_is_forward = FALSE;
	public $caption = NULL;
	public $offset = NULL; // inline query
	public $callback = FALSE;
	public $send = FALSE; // Class
	public $migrate_chat = NULL;

	function set_access($uid, $key = NULL, $name = NULL){
		$this->bot = new Bot($uid, $key, $name);

		// Set sender
		$this->send = new Sender($this->bot);
		return $this;
	}

	function process($content = NULL){
		if($content === NULL){
			$content = file_get_contents("php://input");
		}

		if(!empty($content)){
			$this->raw = $content;
			$this->data = json_decode($content, TRUE);
			$this->id = $this->data['update_id'];
			if(isset($this->data['message']) or isset($this->data['edited_message'])){
				$this->key = (isset($this->data['edited_message']) ? "edited_message" : "message");
				if($this->key == "edited_message"){
					$this->is_edit = TRUE;
					$this->edit_date = $this->data[$this->key]['edit_date'];
				}
				$this->message = $this->data[$this->key]['message_id']; // DEPRECATED
				$this->message_id = intval($this->data[$this->key]['message_id']);
				$this->timestamp = $this->data[$this->key]['date']; // HACK Tener en cuenta edit_date
				$this->chat = new Chat($this->data[$this->key]['chat']);
				$this->user = new User($this->data[$this->key]['from']);
				if(isset($this->data[$this->key]['from']['language_code'])){
					$this->language = $this->data[$this->key]['from']['language_code'];
					if(strpos($this->language, "-") !== FALSE){
						$this->language = explode("-", $this->language);
						$this->language = strtolower($this->language[0]);
					}
				}
				if(isset($this->data[$this->key]['caption'])){
					$this->caption = $this->data[$this->key]['caption'];
				}
				if(isset($this->data[$this->key]['reply_to_message'])){
					$this->has_reply = TRUE;
					$this->reply_user = new User($this->data[$this->key]['reply_to_message']['from']);
					$this->reply = (object) $this->data[$this->key]['reply_to_message'];
					$this->reply_is_forward = (isset($this->data[$this->key]['reply_to_message']['forward_from']));
					if($this->reply_is_forward){
						$this->reply->forward_from = new User($this->data[$this->key]['reply_to_message']['forward_from']);
						$this->reply->forward_from_chat = new Chat($this->data[$this->key]['reply_to_message']['forward_from_chat']);
					}
				}
				// Comprobar, porque esto sólo es cierto si viene de un channel.
				// El correcto sería forward_from Y forward_from_chat
				if(isset($this->data[$this->key]['forward_from_chat'])){
					$this->has_forward = TRUE;
				}
				if(isset($this->data[$this->key]['new_chat_members'])){
					foreach($this->data[$this->key]['new_chat_members'] as $user){
						$this->new_users[] = new User($user);
					}
					$this->new_user = $this->new_users[0]; // COMPATIBILITY: Tal y como hace Telegram, se agrega el primer usuario.
				// DEPRECTAED en un futuro?
				}elseif(isset($this->data[$this->key]['new_chat_member'])){
					$this->new_user = new User($this->data[$this->key]['new_chat_member']);
					$this->new_users = [$this->new_user];
				}elseif(isset($this->data[$this->key]['left_chat_member'])){
					// DEPRECATED
					$this->new_user = new User($this->data[$this->key]['left_chat_member']);
					$this->left_user = $this->new_user;
				}elseif(isset($this->data[$this->key]['migrate_to_chat_id'])){
					$this->migrate_chat = $this->data[$this->key]['migrate_to_chat_id'];
				}elseif(isset($this->data[$this->key]['migrate_from_chat_id'])){
					$this->migrate_chat = $this->data[$this->key]['migrate_from_chat_id'];
				}
				if(isset($this->data[$this->key]['entities'])){
					foreach($this->data[$this->key]['entities'] as $ent){
						$this->entities[] = new Elements\MessageEntity($ent, $this->text());
					}
				}
			}elseif(isset($this->data['callback_query'])){
				$this->key = "callback_query";
				$this->id = $this->data[$this->key]['id'];
				$this->message = $this->data[$this->key]['message']['message_id']; // DEPRECATED
				$this->message_id = $this->data[$this->key]['message']['message_id'];
				$this->chat = new Chat($this->data[$this->key]['message']['chat']);
				$this->user = new User($this->data[$this->key]['from']);
				$this->callback = $this->data[$this->key]['data'];
			}elseif(isset($this->data['channel_post']) or isset($this->data['edited_channel_post'])){
				$this->key = (isset($this->data['edited_channel_post']) ? "edited_channel_post" : "channel_post");
				if($this->key == "edited_channel_post"){
					$this->is_edit = TRUE;
					$this->edit_date = $this->data[$this->key]['edit_date'];
				}
				$this->id = $this->data['update_id'];
				$this->message_id = $this->data[$this->key]['message_id'];
				$this->timestamp = $this->data[$this->key]['date'];
				$this->chat = (object) $this->data[$this->key]['chat'];

				if(isset($this->data[$this->key]['from'])){
					$this->user = (object) $this->data[$this->key]['from'];
				}
			}elseif(isset($this->data['inline_query'])){
				$this->key = "inline_query";
				$this->id = $this->data[$this->key]['id'];
				// $this->message_id = $this->data[$this->key]['id'];
				$this->user = new User($this->data[$this->key]['from']);
				$this->chat = new Chat($this->data[$this->key]['from']); // Compatibility, but not set
				$this->offset = $this->data[$this->key]['offset'];
			}
		}
	}

	function text_message(){
		if($this->key == "callback_query"){ return $this->data[$this->key]['message']['text']; }
		elseif($this->has_reply){ return $this->data[$this->key]['reply_to_message']['text']; }
		return NULL;
	}

	function text($clean = FALSE){
		$text = @$this->data[$this->key]['text'];
		if($this->key == "callback_query"){
			$text = @$this->data[$this->key]['data'];
			if(substr($text, 0, 2) != "T:"){ return NULL; }
			$text = substr($text, 2);
		}
		if($clean === TRUE){ $text = $this->clean('alphanumeric-full-spaces', $text); }
		return $text;
	}

	function text_query(){
		if($this->key != "inline_query"){ return FALSE; }
		$text = $this->data[$this->key]['query'];
		if(empty($text)){ $text = NULL; }
		return $text;
	}

	function text_query_has($input, $next_word = NULL, $position = NULL){
		$text = $this->text_query();
		if(empty($text)){ return FALSE; }
		return $this->text_has($input, $next_word, $position, $text, TRUE);
	}

	function text_encoded($clean_quotes = FALSE){
		$t = json_encode($this->text(FALSE));
		if($clean_quotes){ $t = substr($t, 1, -1); }
		return $t;
	}

	function text_contains($input, $strpos = NULL){
		if(!is_array($input)){ $input = array($input); }
		$text = strtolower($this->text());
		$text = str_replace(["á", "é", "í", "ó", "ú"], ["a", "e", "i", "o", "u"], $text); // HACK
		foreach($input as $i){
			$j = str_replace(["á", "é", "í", "ó", "ú"], ["a", "e", "i", "o", "u"], $i); // HACK
			if(
				($strpos === NULL and strpos($text, strtolower($j)) !== FALSE) or // Buscar cualquier coincidencia
				($strpos === TRUE and strpos($text, strtolower($j)) === 0) or // Buscar textualmente eso al principio
				($strpos === FALSE and strpos($this->text(), $i) === 0) or // Buscar textualmente al principio + CASE sensitive
				($strpos !== NULL and strpos($text, strtolower($j)) == $strpos) // Buscar por strpos
			){
				return TRUE;
			}
		}
		return FALSE;
	}

	private function text_cleanup_prepare($input, $tolower = TRUE){
		if($tolower){ $input = strtolower($input); }
		$vocals = [
			"á" => "a", "é" => "e", "í" => "i", "ó" => "o", "ú" => "u",
			"Á" => "A", "É" => "E", "Í" => "I", "Ó" => "O", "Ú" => "U"
		];
		$input = str_replace(array_keys($vocals), array_values($vocals), $input);
		$input = str_replace("%20", " ", $input); // HACK web
		if($tolower){ $input = strtolower($input); }
		return $input;
	}

	function text_has($input, $next_word = NULL, $position = NULL, $text = NULL, $cleanup = TRUE){
		// A diferencia de text_contains, esto no será valido si la palabra no es la misma.
		// ($input = "fanta") -> fanta OK , fanta! OK , fantasma KO
		if(!is_array($input)){ $input = array($input); }
		// FIXME si algun input contiene un PIPE | , ya me ha jodio. Controlarlo.

		$input = implode("|", $input);
		$input = $this->text_cleanup_prepare($input, TRUE);
        $input = str_replace("/", "\/", $input); // CHANGED fix para escapar comandos y demás.

		if(is_bool($next_word)){ $position = $next_word; $next_word = NULL; }
		elseif($next_word !== NULL){
			if(!is_array($next_word)){ $next_word = array($next_word); }
			$next_word = implode("|", $next_word);
			$next_word = $this->text_cleanup_prepare($next_word, TRUE);
            $next_word = str_replace("/", "\/", $next_word); // CHANGED
		}

		// Al principio de frase
		if($position === TRUE){
			if($next_word === NULL){ $regex = "^(" .$input .')([\s!.,"]?)'; }
			else{ $regex = "^(" .$input .')([\s!.,"]?)\s(' .$next_word .')([\s!?.,"]?)'; }
		// Al final de frase
		}elseif($position === FALSE){
			if($next_word === NULL){ $regex = "(" .$input .')([!?,."]?)$'; }
			else{ $regex = "(" .$input .')([\s!.,"]?)\s(' .$next_word .')([?!.,"]?)$'; }
		// En cualquier posición
		}else{
			if($next_word === NULL){ $regex = "(" .$input .')([\s!?.,"])|(' .$input .')$'; }
			else{ $regex = "(" .$input .')([\s!.,"]?)\s(' .$next_word .')([\s!?.,"])|(' .$input .')([\s!.,"]?)\s(' .$next_word .')([!?.,"]?)$'; }
		}

		if($text === NULL){ $text = strtolower($this->text()); }
		if($cleanup){
			$text = $this->text_cleanup_prepare($text, FALSE);
			$text = strtolower($text);
		}
		return preg_match("/$regex/", $text);
	}

	// WIP TODO
	function text_has_emoji($emoji = NULL, $return = FALSE){
		if(empty($emoji)){
			return (strpos($this->text_encoded(), '\u') !== FALSE);
		}elseif(is_array($emoji)){
			foreach($emoji as $e){
				if(empty($e)){ continue; }
				$r = $this->text_has_emoji($e, $return);
				if($r !== FALSE){ return $r; }
			}
			return FALSE;
		}
		if(in_array(substr($emoji, 0, 1), [':', '\\'])){ $emoji = $this->emoji($emoji); }
		$text = $this->text();
		return (strpos($text, $emoji) !== FALSE);
	}

	function text_regex($expr, $cleanup = TRUE){
		if(!is_array($expr)){ $expr = [$expr]; }
		$text = $this->text();
		if($cleanup){
			$text = strtolower($text);
			$text = str_replace(["á", "é", "í", "ó", "ú"], ["a", "e", "i", "o", "u"], $text); // HACK
			$text = str_replace(["Á", "É", "Í", "Ó", "Ú"], ["A", "E", "I", "O", "U"], $text); // HACK
			$text = str_replace("%20", " ", $text); // HACK web
			$text = strtolower($text); // HACK Twice
		}
		foreach($expr as $ex){
			$r = preg_match($ex, $text);
			if($r){ return $r; }
		}
		return FALSE;
	}

	function text_mention($user = NULL){
		// Incluye users registrados y anónimos.
		// NULL -> decir si hay usuarios mencionados o no (T/F)
		// TRUE -> array [ID => @nombre o nombre]
		// NUM -> decir si el NUM ID usuario está mencionado o no, y si es @nombre, parsear para validar NUM ID.
		// STR -> decir si nombre o @nombre está mencionado o no.
		if(empty($this->entities)){ return FALSE; }
		$users = array();
		$text = $this->text(FALSE); // No UTF-8 clean
		foreach($this->entities as $e){
			if($e->type == 'text_mention'){
				$users[] = [$e->user->id => $e->value];
			}elseif($e->type == 'mention'){
				$u = trim($e->value); // @username
				// $d = $this->send->get_member_info($u); HACK
				$d = FALSE;
				$users[] = ($d === FALSE ? $u : [$d['user']['id'] => $u] );
			}
		}
		if($user == NULL){ return (count($users) > 0 ? $users[0] : FALSE); }
		if($user === TRUE){ return $users; }
		if(is_numeric($user)){
			if($user < count($users)){
				$k = array_keys($users);
				$v = array_values($users);
				return [ $k[$user] => $v[$user] ];
			}
			return in_array($user, array_keys($users));
		}
		if(is_string($user)){ return in_array($user, array_values($users)); }
		return FALSE;
	}

	function text_email($email = NULL){
		// NULL -> saca el primer mail o FALSE.
		// TRUE -> array [emails]
		// STR -> email definido.
		if(empty($this->entities)){ return FALSE; }
		$emails = array();
		$text = $this->text(FALSE); // No UTF-8 clean
		foreach($this->entities as $e){
			if($e->type == 'email'){ $emails[] = strtolower($e->value); }
		}
		if($email == NULL){ return (count($emails) > 0 ? $emails[0] : FALSE); }
		if($email === TRUE){ return $emails; }
		if(is_string($email)){ return in_array(strtolower($email), $emails); }
		return FALSE;
	}

	function text_command($cmd = NULL, $begin = TRUE){
		// NULL -> saca el primer comando o FALSE.
		// TRUE -> array [comandos]
		// STR -> comando definido.
		// $begin = si es comando inicial
		if(empty($this->entities)){ return FALSE; }
		if($cmd === FALSE){ $begin = FALSE; $cmd = NULL; }
		$cmds = array();
		$text = $this->text(FALSE); // No UTF-8 clean
		$initbegin = FALSE;
		foreach($this->entities as $e){
			if($e->type == 'bot_command'){
				$cmds[] = strtolower($e->value);
				if($initbegin == FALSE && $e->offset == 0){ $initbegin = TRUE; }
			}
		}
		if($cmd == NULL){
			if(count($cmds) > 0){
				if($begin && !$initbegin){ return FALSE; }
				return $cmds[0];
			}
			return FALSE;
		}
		if($cmd === TRUE){ return $cmds; }
		if(is_string($cmd)){ $cmd = [$cmd]; }
		if(is_array($cmd)){
			foreach($cmd as $csel){
				if($csel[0] != "/"){ $csel = "/" .$csel; }
				$csel = strtolower($csel);
				if(in_array($csel, $cmds) && strpos($csel, "@") === FALSE){ return !($begin && !$initbegin); }
				$name = strtolower($this->bot->username);
				if($name){
					if($name[0] != "@"){ $name = "@" .$name; }
					$csel = $csel.$name;
				}
				if(in_array($csel, $cmds)){ return !($begin && !$initbegin); }
			}
		}
		return FALSE;
	}

	function text_hashtag($tag = NULL){
		// NULL -> saca el primer hashtag o FALSE.
		// TRUE -> array [hashtags]
		// STR -> hashtag definido.
		if(empty($this->entities)){ return FALSE; }
		$hgs = array();
		$text = $this->text(FALSE); // No UTF-8 clean
		foreach($this->entities as $e){
			if($e->type == 'hashtag'){ $hgs[] = strtolower($e->value); }
		}
		if($tag == NULL){ return (count($hgs) > 0 ? $hgs[0] : FALSE); }
		if($tag === TRUE){ return $hgs; }
		if(is_string($tag)){
			if($tag[0] != "#"){ $tag = "#" .$tag; }
			return in_array(strtolower($tag), $hgs);
		}
		return FALSE;
	}

	function text_url($cmd = NULL){
		// NULL -> saca la primera URL o FALSE.
		// TRUE -> array [URLs]
		if(empty($this->entities)){ return FALSE; }
		$cmds = array();
		$text = $this->text(FALSE); // No UTF-8 clean
		foreach($this->entities as $e){
			if($e->type == 'url'){ $cmds[] = $e->value; }
		}
		if($cmd == NULL){ return (count($cmds) > 0 ? $cmds[0] : FALSE); }
		if($cmd === TRUE){ return $cmds; }
		return FALSE;
	}

	function last_word($clean = FALSE){
		$text = $this->words(TRUE);
		if($clean === TRUE){ $clean = 'alphanumeric-accent'; }
		return $this->clean($clean, array_pop($text));
	}

	function words($position = NULL, $amount = 1, $filter = FALSE){ // Contar + recibir argumentos
		if($position === NULL){
			return count(explode(" ", $this->text()));
		}elseif($position === TRUE){
			return explode(" ", $this->text());
		}elseif(is_numeric($position)){
			if($amount === TRUE){ $filter = 'alphanumeric'; $amount = 1; }
			elseif(is_string($amount)){ $filter = $amount; $amount = 1; }
			$t = explode(" ", $this->text());
			$a = $position + $amount;
			$str = '';
			for($i = $position; $i < $a; $i++){
				$str .= $t[$i] .' ';
			}
			if($filter !== FALSE){ $str = $this->clean($filter, $str); }
			return trim($str);
		}
	}

	function word_position($find){
		$text = $this->text();
		if(empty($text)){ return FALSE; }
		$pos = strpos($text, $find);
		if($pos === FALSE){ return FALSE; }
		$text = substr($text, 0, $pos);
		return count(explode(" ", $text));
	}

	function clean($pattern = 'alphanumeric-full', $text = NULL){
		$pats = [
			'number' => '/^[0-9]+/',
			'number-calc' => '/^([+-]?)\d+(([\.,]?)\d+?)/',
			'alphanumeric' => '/[^a-zA-Z0-9]+/',
			'alphanumeric-accent' => '/[^a-zA-Z0-9áéíóúÁÉÍÓÚ]+/',
			'alphanumeric-symbols-basic' => '/[^a-zA-Z0-9\._\-]+/',
			'alphanumeric-full' => '/[^a-zA-Z0-9áéíóúÁÉÍÓÚ\._\-]+/',
			'alphanumeric-full-spaces' => '/[^a-zA-Z0-9áéíóúÁÉÍÓÚ\.\s_\-]+/',
		];
		if(empty($text)){ $text = $this->text(); }
		if($pattern == FALSE){ return $text; }
		if(!isset($pats[$pattern])){ return FALSE; }
		return preg_replace($pats[$pattern], "", $text);
	}

	/**
	 *  Return date of message.
	 *  TRUE = diff time() - Telegram timestamp.
	 *  NULL = return date format.
	 *  int = diff int time() - Telegram timestamp.
	 *  string date = diff date - Telegram timestamp.
	 *  string date_format = return specified date format.
	 */
	function date($parse = NULL, $time = NULL){
		if(empty($time)){ $time = $this->timestamp; }
		if(empty($time)){ $time = time(); } // TEMP HACK Si no hay timestamp.

		if($parse === NULL){ return date("Y-m-d H:i:s", $time); }
		elseif($parse === TRUE){ $parse = time(); }

		if(is_numeric($parse)){
			// timestamp, diff time.
			return ($parse - $time);
		}else{
			$date = strtotime($parse);
			if($date > 0){
				// Diff with timestamp
				return ($date - $time);
			}
			// Parse date format
			return date($parse, $time);
		}
	}

	function progressbar($val, $max = 100, $chars = 12, $chfull = NULL, $chempty = NULL){
		$chfull  = (empty($chfull) ? json_decode('"\u2588"') : $this->emoji($chfull));
		$chempty = (empty($chempty) ? json_decode('"\u2592"') : $this->emoji($chempty));

		$nfull = floor(($val / $max) * $chars);
		if($nfull < 0){ $nfull = 0; }
		$nempty = max(($chars - $nfull), 0);

		$str = "";
		for($i = 0; $i < $nfull; $i++){ $str .= $chfull; }
		for($i = 0; $i < $nempty; $i++){ $str .= $chempty; }

		return $str;
	}

	function is_chat_group(){ return isset($this->chat->type) && in_array($this->chat->type, ["group", "supergroup"]); }
	function data_received($expect = NULL){
		if($expect !== NULL){
			return (isset($this->data[$this->key][$expect]));
		}
		$data = [
			"migrate_to_chat_id", "migrate_from_chat_id",
			"new_chat_participant", "left_chat_participant", "new_chat_members", "new_chat_member", "left_chat_member",
			"reply_to_message", "text", "audio", "document", "photo", "video_note", "voice", "location", "contact"
		];
		foreach($data as $t){
			if(isset($this->data[$this->key][$t])){
				if($expect == NULL or $expect == $t){ return $t; }
			}
		}
		return FALSE;
	}

	function forward_type($expect = NULL){
		if(!$this->has_forward){ return FALSE; }
		$type = $this->data['message']['forward_from_chat']['type'];
		if($expect !== NULL){ return (strtolower($expect) == $type); }
		return $type;
	}

	function is_bot($user = NULL){
		if($user === NULL){ $user = $this->user->username; }
		elseif($user === TRUE && $this->has_reply){ $user = $this->reply_user->username; }
		elseif($user instanceof User){ $user = $user->username; }
		return (!empty($user) && substr(strtolower($user), -3) == "bot");
		// TODO Si realmente es un bot y se intenta hacer un chatAction, no debería dejar.
		// A no ser que ese usuario también haya bloqueado al bot.
	}

	// NOTE: Solo funcionará si el bot está en el grupo.
	function user_in_chat(&$user, $chat = NULL, $object = FALSE){
		if($chat === TRUE){ $object = TRUE; $chat = NULL; }
		if(empty($chat)){ $chat = $this->chat; }
		if($chat instanceof Chat){ $chat = $chat->id; }

		$uid = $user;
		if($user instanceof User){ $uid = $user->id; }
		$info = $this->send->get_member_info($uid, $chat);
		$ret = ($object ? (object) $info : $info);

		// TODO CHECK DATA
		if($user instanceof User && $info !== FALSE){
			$user->status = $info['status'];
		}

		return ( ($info === FALSE or in_array($info['status'], ['left', 'kicked'])) ? FALSE : $ret );
	}

	// TODO Join function and deprecate
	function grouplink($text, $url = FALSE){
		$link = "https://t.me/";
		if($text[0] != "@" and strlen($text) == 22){
			$link .= "joinchat/$text";
		}else{
			if($url && $text[0] == "@"){ $link .= substr($text, 1); }
			else{ $link = $text; }
		}
		return $link;
	}

	function get_chat_link($chat = NULL){
		if(empty($chat)){ $chat = $this->chat->id; }
		return $this->send->get_chat_link($chat);
	}

	function answer_if_callback($text = "", $alert = FALSE){
		if($this->key != "callback_query"){ return FALSE; }
		return $this->send
			->text($text)
		->answer_callback($alert);
	}

	function dump($json = FALSE){ return($json ? json_encode($this->data) : $this->data); }

	function get_admins($chat = NULL, $full = FALSE){
		$ret = array();
		if(empty($chat)){ $chat = $this->chat->id; }
		$admins = $this->send->get_admins($chat);
		if(!empty($admins)){
			foreach($admins as $a){	$ret[] = $a['user']['id']; }
		}
		return ($full == TRUE ? $admins : $ret);
	}

	function data($type, $object = TRUE){
		$accept = ["text", "audio", "video", "video_note", "document", "photo", "voice", "location", "contact"];
		$type = strtolower($type);
		if(in_array($type, $accept) && isset($this->data['message'][$type])){
			if($object){ return (object) $this->data['message'][$type]; }
			return $this->data['message'][$type];
		}
		return FALSE;
	}

	function _generic_content($key, $object = NULL, $rkey = 'file_id'){
		if(!isset($this->data[$this->key][$key])){ return FALSE; }
		$data = $this->data[$this->key][$key];
		if(empty($data)){ return FALSE; }
		if($object === TRUE){ return (object) $data; }
		elseif($object === FALSE){ return array_values($data); }

		if(in_array($key, ["document", "location"])){ return $data; }
		return $data[$rkey];
	}

	function document($object = TRUE){ return $this->_generic_content('document', $object); }
	function location($object = TRUE){ return $this->_generic_content('location', $object); }
	function voice($object = NULL){ return $this->_generic_content('voice', $object); }
 	function video($object = NULL){ return $this->_generic_content('video', $object); }
	function video_note($object = NULL){ return $this->_generic_content('video_note', $object); }
	function sticker($object = NULL){ return $this->_generic_content('sticker', $object); }

	function gif(){
		$gif = $this->document(TRUE);
		if(!$gif or !in_array($gif->mime_type, ["video/mp4"])){ return FALSE; }
		// TODO gif viene por size?
		return $gif->file_id;
	}

	function photo($retall = FALSE, $sel = -1){
		if(!isset($this->data['message']['photo'])){ return FALSE; }
		$photos = $this->data['message']['photo'];
		if(empty($photos)){ return FALSE; }
		// Select last file or $sel_id
		$sel = ($sel == -1 or ($sel > count($photos) - 1) ? (count($photos) - 1) : $sel);
		if(!isset($photos[$sel])){ $sel = 0; } // TEMP FIX
		if($retall === FALSE){ return $photos[$sel]['file_id']; }
		elseif($retall === TRUE){ return (object) $photos[$sel]; }
	}

	function contact($self = FALSE, $object = TRUE){
		$contact = $this->data['message']['contact'];
		if(empty($contact)){ return FALSE; }
		if(
			$self == FALSE or
			($self == TRUE && $this->user->id == $contact['user_id'])
		){
			if($object == TRUE){ return (object) $contact; }
			return $contact;
		}elseif($self == TRUE){
			return FALSE;
		}
	}

	function reply_target($priority = NULL){
		if(!$this->has_reply){ return NULL; }
		// El reply puede ser hacia la persona del mensaje al cual se hace reply
		// o si es un forward, hacia ese usuario creador del mensaje.

		$ret = $this->reply_user;
		if($priority == NULL or $priority == TRUE or strtolower($priority) == 'forward'){
			if($this->reply_is_forward){
				$ret = $this->reply->forward_from;
			}
		}

		return $ret;
	}

	// Return UserID siempre que sea posible.
	function user_selector($priority = NULL, $word = NULL){
		$user = $this->reply_target($priority);
		if(!empty($user)){ return $user->id; }
		// TODO
	}

	function pinned_message($content = NULL){
		if(!isset($this->data['message']['pinned_message'])){ return FALSE; }
		$pin = $this->data['message']['pinned_message'];
		if($content === NULL){
			$user = (object) $pin['from'];
			$chat = (object) $pin['chat'];
			$data = $pin['text'];
			return (object) array(
				'user' => $user,
				'chat' => $chat,
				'data' => $data
			);
		}
		elseif($content === TRUE){ return $pin['text']; }
		elseif($content === FALSE){  }
	}

	function user_can($action = NULL, $user = NULL, $chat = NULL){
		if(empty($user)){ $user = $this->user->id; }
		if(empty($chat)){ $chat = $this->chat->id; }
		$data = $this->send->get_member_info($user, $chat);

		$can = [
			'can_be_edited', 'can_change_info',
			'can_post_messages', 'can_edit_messages',
			'can_delete_messages', 'can_invite_users',
			'can_restrict_members', 'can_pin_messages',
			'can_promote_members', 'can_send_messages',
			'can_send_media_messages', 'can_send_other_messages',
			'can_add_web_page_previews'
		];

		// Return all results.
		if($action === TRUE or $action === NULL){
			$final = array();
			foreach($can as $c){
				if(isset($data['result'][$c])){ $final[$c] = $data['result'][$c]; }
			}
			return $final;
		}

		if(strpos($action, "can_") === FALSE){ $action = "can_" .$action; }
		$action = strtolower($action);

		if(!isset($data['result'][$action])){ return NULL; }
		return (bool) $data['result'][$action];
	}

	function download($file_id, $path = NULL){
		$data = $this->send->get_file($file_id);
		$url = "https://api.telegram.org/file/bot" .$this->bot->id .":" .$this->bot->key ."/";
		$file = $url .$data['file_path'];
		if(!empty($path)){
			return file_put_contents($path, file_get_contents($file));
		}
		return $file;
	}

	function emoji($text, $reverse = FALSE){
		$emoji = [
			'kiss' => '\ud83d\ude18',
			'heart' => '\u2764\ufe0f',
			'heart-blue' => '\ud83d\udc99',
			'heart-green' => '\ud83d\udc9a',
			'heart-yellow' => '\ud83d\udc9b',
			'laugh' => '\ud83d\ude02',
			'tongue' => '\ud83d\ude1b',
			'smiley' => '\ud83d\ude00',
			'happy' => '\ud83d\ude19',
			'die' => '\ud83d\ude35',
			'cloud' => '\u2601\ufe0f',
			'sun' => '\u2600\ufe0f',
			'rainbow' => '\ud83c\udf08',
			'orange' => '\ud83c\udf4a',
			'apple-red' => '\ud83c\udf4e',
			'birthcake' => '\ud83c\udf82',
			'gun' => '\ud83d\udd2b',
			'green-check' => '\u2705',
			'antenna' => '\ud83d\udce1',
			'spam' => '\ud83d\udce8',
			'laptop' => '\ud83d\udcbb',
			'pin' => '\ud83d\udccd',
			'home' => '\ud83c\udfda',
			'map' => '\ud83d\uddfa',
			'candy' => '\ud83c\udf6c',
			'spiral' => '\ud83c\udf00',
			'tennis' => '\ud83c\udfbe',
			'key' => '\ud83d\udddd',
			'door' => '\ud83d\udeaa',
			'frog' => '\ud83d\udc38',

			'forbid' => '\u26d4\ufe0f',
			'times' => '\u274c',
			'warning' => '\u26a0\ufe0f',
			'banned' => '\ud83d\udeab',
			'star' => '\u2b50\ufe0f',
			'star-shine' => '\ud83c\udf1f',
			'mouse' => '\ud83d\udc2d',
			'multiuser' => '\ud83d\udc65',
			'robot' => '\ud83e\udd16',
			'fire' => '\ud83d\udd25',
			'collision' => '\ud83d\udca5',
			'joker' => '\ud83c\udccf',
			'medal-1' => '\ud83e\udd47',
			'medal-2' => '\ud83e\udd48',
			'medal-3' => '\ud83e\udd49',
			'medal-gold' => '\ud83c\udfc5',
			'medal-star' => '\ud83c\udf96',
			'trophy' => '\ud83c\udfc6',
			'exclamation-red' => '\u2757\ufe0f',
			'question-red' => '\u2753',
			'exclamation-grey' => '\u2755',
			'question-grey' => '\u2754',
			'info' => '\u2139\ufe0f',
			'new' => '\ud83c\udd95',
			'id' => '\ud83c\udd94',
			'abc' => '\ud83d\udd24',
			'abcd' => '\ud83d\udd21',
			'ABCD' => '\ud83d\udd20',
			'OK' => '\ud83c\udd97',

			'no-18' => '\ud83d\udd1e',
			'no-phone' => '\ud83d\udcf5',
			'male' => '\ud83d\udeb9',
			'female' => '\ud83d\udeba',
			'passport-control' => '\ud83d\udec2',

			'euro' => '\ud83d\udcb6',
			'money-bag' => '\ud83d\udcb0',

			'1' => '1\u20e3',
			'2' => '2\u20e3',
			'3' => '3\u20e3',
			'4' => '4\u20e3',
			'5' => '5\u20e3',
			'6' => '6\u20e3',
			'7' => '7\u20e3',
			'8' => '8\u20e3',
			'9' => '9\u20e3',
			'0' => '0\u20e3',

			'triangle-left' => '\u25c0\ufe0f',
			'triangle-up' => '\ud83d\udd3c',
			'triangle-right' => '\u25b6\ufe0f',
			'triangle-down' => '\ud83d\udd3d',
			'arrow-left' => '\u2b05\ufe0f',
			'arrow-up' => '\u2b06\ufe0f',
			'arrow-right' => '\u27a1\ufe0f',
			'arrow-down' => '\u2b07\ufe0f',
			'arrow-up-left' => '\u2196\ufe0f',
			'arrow-up-right' => '\u2197\ufe0f',
			'arrow-down-right' => '\u2198\ufe0f',
			'arrow-down-left ' => '\u2199\ufe0f',

			'minus' => '\u2796',
			'plus' => '\u2795',
			'multiply' => '\u2716\ufe0f',
			'search-left' => '\ud83d\udd0d',
			'search-right' => '\ud83d\udd0e',

			'world-europe' => '\ud83c\udf0d',
			'world-usa' => '\ud83c\udf0e',
			'world-asia' => '\ud83c\udf0f',

			'clock-1' => '\ud83d\udd50',
			'clock-2' => '\ud83d\udd51',
			'clock-3' => '\ud83d\udd52',
			'clock-4' => '\ud83d\udd53',
			'clock-5' => '\ud83d\udd54',
			'clock-6' => '\ud83d\udd55',
			'clock-7' => '\ud83d\udd56',
			'clock-8' => '\ud83d\udd57',
			'clock-9' => '\ud83d\udd58',
			'clock-10' => '\ud83d\udd59',
			'clock-11' => '\ud83d\udd5a',
			'clock-12' => '\ud83d\udd5b',

			'clock-1-half' => '\ud83d\udd5c',
			'clock-2-half' => '\ud83d\udd5d',
			'clock-3-half' => '\ud83d\udd5e',
			'clock-4-half' => '\ud83d\udd5f',
			'clock-5-half' => '\ud83d\udd60',
			'clock-6-half' => '\ud83d\udd61',
			'clock-7-half' => '\ud83d\udd62',
			'clock-8-half' => '\ud83d\udd63',
			'clock-9-half' => '\ud83d\udd64',
			'clock-10-half' => '\ud83d\udd65',
			'clock-11-half' => '\ud83d\udd66',
			'clock-12-half' => '\ud83d\udd67',
		];

		$search = [
			'kiss' => [':kiss:', ':*'],
			'heart' => [':heart-red:', '<3', ':heart:', ':love:'],
			'heart-blue' => [':heart-blue:'],
			'heart-green' => [':heart-green:'],
			'heart-yellow' => [':heart-yellow:'],
			'smiley' => [':smiley:', ':>', ']:D'],
			'happy' => [':happy:', '^^'],
			'laugh' => [':lol:', ":'D"],

			'tongue' => [':tongue:', '=P'],
			'die' => [':die:', '>X'],
			'cloud' => [':cloud:'],
			'sun' => [':sun:'],
			'rainbow' => [':rainbow:'],
			'gun' => [':gun:'],
			'door' => [':door:'],

			'orange' => [':orange:'],
			'apple-red' => [':apple:', ':apple-red:'],
			'birthcake' => [':birthcake:', ':birthday:'],

			'1' => [':1:', '[1]'],
			'2' => [':2:', '[2]'],
			'3' => [':3:', '[3]'],
			'4' => [':4:', '[4]'],
			'5' => [':5:', '[5]'],
			'6' => [':6:', '[6]'],
			'7' => [':7:', '[7]'],
			'8' => [':8:', '[8]'],
			'9' => [':9:', '[9]'],
			'0' => [':0:', '[0]'],

			'forbid' => [':forbid:'],
			'times' => [':times:', ':ko:', ':no:', ':x:'],
			'banned' => [':banned:'],
			'star' => [':star:'],
			'star-shine' => [':star-shine:'],
			'mouse' => [':mouse:', ':ratkid:'],
			'robot' => [':robot:', ':bot:'],
			'multiuser' => [':multiuser:'],
			'fire' => [':fire:'],
			'collision' => [':collision:'],
			'joker' => [':joker:'],
			'antenna' => [':antenna:'],
			'laptop' => [':laptop:'],
			'spam' => [':spam:'],
			'pin' => [':pin:'],
			'home' => [':home:'],
			'map' => [':map:'],
			'candy' => [':candy:'],
			'spiral' => [':spiral:'],
			'tennis' => [':tennis:'],
			'key' => [':key:', ':res:'],
			'frog' => [':frog:', ':enl:'],
			'green-check' => [':ok:', ':green-check:'],
			'warning' => [':warning:', '/!\\'],
			'exclamation-red' => [':exclamation-red:', ':!:'],
			'question-red' => [':question-red:', ':?:'],
			'exclamation-grey' => [':exclamation-grey:'],
			'question-grey' => [':question-grey:'],

			'no-18' => [':no-18:', ':/18:', ':!18:'],
			'no-phone' => [':no-phone:', ':no-mobile:'],
			'male' => [':male:', ':man:'],
			'female' => [':female:', ':woman:'],
			'passport-control' => [':guard:', ':passport-control:', ':passport:'],

			'euro' => [':euro:', ':euros:'],
			'money-bag' => [':money-bag:'],

			'medal-1' => [':medal-1:', ':bronce:', ':bronze:'],
			'medal-2' => [':medal-2:', ':silver:'],
			'medal-3' => [':medal-3:', ':golden:'],
			'medal-gold' => [':medal-gold:'],
			'medal-star' => [':medal-star:'],
			'trophy' => [':trophy:'],

			'info' => [':info:', ':i:'],
			'new' => [':new:'],
			'id' => [':id:'],
			'abc' => [':abc:'],
			'abcd' => [':abcd:'],
			'ABCD' => [':ABCD:'],
			'OK' => [':OK:'],
			'triangle-left' => [':triangle-left:', '<|'],
			'triangle-up' => [':triangle-up:'],
			'triangle-right' => [':triangle-right:', '|>'],
			'triangle-down' => [':triangle-down:'],
			'arrow-left' => [':arrow-left:', '<-'],
			'arrow-up' => [':arrow-up:'],
			'arrow-right' => [':arrow-right:', '->'],
			'arrow-down' => [':arrow-down:'],
			'arrow-up-left' => [':arrow-up-left:'],
			'arrow-up-right' => [':arrow-up-right:'],
			'arrow-down-right' => [':arrow-down-right:'],
			'arrow-down-left ' => [':arrow-down-left :'],
			'minus' => [':minus:', ':-:'],
			'plus' => [':plus:', ':+:'],
			'multiply' => [':multiply:', ':*:'],
			'search-left' => [':search-left:'],
			'search-right' => [':search-right:'],

			'world-europe' => [':world-europe:', ':world:'],
			'world-usa' => [':world-usa:'],
			'world-asia' => [':world-asia:'],

			'clock-1' => [':clock-1:'],
			'clock-2' => [':clock-2:', ':clock:'],
			'clock-3' => [':clock-3:'],
			'clock-4' => [':clock-4:'],
			'clock-5' => [':clock-5:'],
			'clock-6' => [':clock-6:'],
			'clock-7' => [':clock-7:'],
			'clock-8' => [':clock-8:'],
			'clock-9' => [':clock-9:'],
			'clock-10' => [':clock-10:'],
			'clock-11' => [':clock-11:'],
			'clock-12' => [':clock-12:'],

			'clock-1-half' => [':clock-1-half:'],
			'clock-2-half' => [':clock-2-half:'],
			'clock-3-half' => [':clock-3-half:'],
			'clock-4-half' => [':clock-4-half:'],
			'clock-5-half' => [':clock-5-half:'],
			'clock-6-half' => [':clock-6-half:'],
			'clock-7-half' => [':clock-7-half:'],
			'clock-8-half' => [':clock-8-half:'],
			'clock-9-half' => [':clock-9-half:'],
			'clock-10-half' => [':clock-10-half:'],
			'clock-11-half' => [':clock-11-half:'],
			'clock-12-half' => [':clock-12-half:'],
		];

		if(!$reverse){
			foreach($search as $n => $k){
				$text = str_replace($k, $emoji[$n], $text);
			}
			$text = str_replace("\n", '\n', $text); // FIX testing
			return json_decode('"' . $text .'"', TRUE);
		}
		$text = json_encode($text); // HACK decode UTF -> ASCII para buscar y reemplazar
		foreach($emoji as $n => $u){
			$text = str_replace($u, $search[$n][0], $text);
		}
		$text = json_decode($text);
		return substr(json_encode($text), 1, -1); // No comas
	}
}

?>
