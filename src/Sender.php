<?php

namespace Telegram;
use Telegram\Elements; // TODO

class Sender {
	private $parent;
	public $bot;
	private $content = array();
	private $broadcast = NULL;
	private $method = NULL;
	private $_keyboard;
	private $_inline;

	function __construct($uid = NULL, $key = NULL, $name = NULL){
		$this->_keyboard = new \Telegram\Keyboards\Keyboard($this);
		$this->_inline = new \Telegram\Keyboards\InlineKeyboard($this);

		if(!empty($uid)){
			if($uid instanceof Receiver){
				$this->parent = $uid;
				$this->bot = $this->parent->bot;
			}elseif($uid instanceof Bot){
				$this->bot = $uid;
			}else{
				$this->set_access($uid, $key, $name);
			}
		}
	}

	function set_access($uid, $key = NULL, $name = NULL){
		$this->bot = new \Telegram\Bot($uid, $key, $name);
		return $this;
	}

	function chat($id = NULL){
		if($id === TRUE && $this->parent instanceof \Telegram\Receiver){ $id = $this->parent->chat->id; }
		$this->content['chat_id'] = $id;
		return $this;
	}

	function chats($ids){
		if(empty($ids)){ return $this; } // HACK
		$this->broadcast = $ids;
		$this->content['chat_id'] = $ids[0]; // HACK
		return $this;
	}

	function user($id = NULL){
		if(empty($id)){ return $this->content['user_id']; }
		$this->content['user_id'] = $id;
		return $this;
	}

	function message($id = NULL){
		if(empty($id)){ return $this->content['message_id']; }
		if($id === TRUE && $this->parent instanceof \Telegram\Receiver){ $id = $this->parent->message; }
		elseif(is_array($id) and isset($id['message_id'])){ $id = $id['message_id']; } // JSON Response from another message.
		$this->content['message_id'] = $id;
		return $this;
	}

	function get_file($id){
		$this->method = "getFile";
		$this->content['file_id'] = $id;
		return $this->send();
	}

	function file($type, $file, $caption = NULL, $keep = FALSE){
		if(!in_array($type, ["photo", "audio", "voice", "document", "sticker", "video"])){ return FALSE; }

		$url = FALSE;
		if(filter_var($file, FILTER_VALIDATE_URL) !== FALSE){
			// ES URL, descargar y enviar.
			$url = TRUE;
			$tmp = tempnam("/tmp", "telegram") .substr($file, -4); // .jpg
			file_put_contents($tmp, fopen($file, 'r'));
			$file = $tmp;
		}

		$this->method = "send" .ucfirst(strtolower($type));
		if(file_exists(realpath($file))){
			$this->content[$type] = new \CURLFile(realpath($file));
		}else{
			$this->content[$type] = $file;
		}
		if($caption === NULL && isset($this->content['text'])){
			$caption = $this->content['text'];
			unset($this->content['text']);
		}
		if($caption !== NULL){
			$key = "caption";
			if($type == "audio"){ $key = "title"; }
			$this->content[$key] = $caption;
		}

		if(!empty($this->broadcast)){
			$result = array();

			foreach($this->broadcast as $chat){
				$this->chat($chat);

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					"Content-Type:multipart/form-data"
				));
				curl_setopt($ch, CURLOPT_URL, $this->_url(TRUE));
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $this->content);
				$result[] = curl_exec($ch);

				curl_close($ch);
			}

			if($url === TRUE){ unlink($file); }
			if($keep === FALSE){ $this->_reset(); }
			return $result;
		}

		if(empty($this->content['chat_id']) && $this->parent instanceof Receiver){ $this->content['chat_id'] = $this->parent->chat->id; }

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type:multipart/form-data"
		));
		curl_setopt($ch, CURLOPT_URL, $this->_url(TRUE));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $this->content);
		$output = curl_exec($ch);
		curl_close($ch);

		if($url === TRUE){ unlink($file); }
		if($keep === FALSE){ $this->_reset(); }
		return $output;
		// return $this;
	}

	function location($lat, $lon = NULL){
		if(is_array($lat) && $lon == NULL){ $lon = $lat[1]; $lat = $lat[0]; }
		elseif(is_string($lat) && strpos($lat, ",") !== FALSE){
			$lat = explode(",", $lat);
			$lon = trim($lat[1]);
			$lat = trim($lat[0]);
		}
		$this->content['latitude'] = $lat;
		$this->content['longitude'] = $lon;
		$this->method = "sendLocation";
		return $this;
	}

	function venue($title, $address, $foursquare = NULL){
		if(isset($this->content['latitude']) && isset($this->content['longitude'])){
			$this->content['title'] = $title;
			$this->content['address'] = $address;
			if(!empty($foursquare)){ $this->content['foursquare_id'] = $foursquare; }
			$this->method = "sendVenue";
		}
		return $this;
	}

	function dump($user){
		var_dump($this->method); var_dump($this->content);
		$bm = $this->method;
		$bc = $this->content;

		$this->_reset();
		$this
			->chat($user)
			->text(json_encode($bc))
		->send();
		$this->method = $bm;
		$this->content = $bc;
		return $this;
	}

	function contact($phone, $first_name, $last_name = NULL){
		$this->content['phone_number'] = $phone;
		$this->content['first_name'] = $first_name;
		if(!empty($last_name)){ $this->content['last_name'] = $last_name; }
		$this->method = "sendContact";
		return $this;
	}

	function text($text, $type = NULL){
		$this->content['text'] = $text;
		$this->method = "sendMessage";
		if($type === TRUE){ $this->content['parse_mode'] = 'Markdown'; }
		elseif(in_array($type, ['Markdown', 'HTML'])){ $this->content['parse_mode'] = $type; }

		return $this;
	}

	function keyboard(){ return $this->_keyboard; }
	function inline_keyboard(){ return $this->_inline; }

	function force_reply($selective = TRUE){
		$this->content['reply_markup'] = ['force_reply' => TRUE, 'selective' => $selective];
		return $this;
	}

	function caption($text){
		$this->content['caption'] = $text;
		return $this;
	}

	function disable_web_page_preview($value = FALSE){
		if($value === TRUE){ $this->content['disable_web_page_preview'] = TRUE; }
		return $this;
	}

	function notification($value = TRUE){
		if($value === FALSE){ $this->content['disable_notification'] = TRUE; }
		else{ if(isset($this->content['disable_notification'])){ unset($this->content['disable_notification']); } }
		return $this;
	}

	function reply_to($message_id = NULL){
		if(is_bool($message_id) && $this->parent instanceof Receiver){
			if($message_id === TRUE or ($message_id === FALSE && !$this->parent->has_reply)){ $message_id = $this->parent->message; }
			elseif($message_id === FALSE){
				if(!$this->parent->has_reply){ return; }
				$message_id = $this->parent->reply->message_id;
			}
		}
		$this->content['reply_to_message_id'] = $message_id;
		return $this;
	}

	function forward_to($chat_id_to){
		if(empty($this->content['chat_id']) or empty($this->content['message_id'])){ return $this; }
		$this->content['from_chat_id'] = $this->content['chat_id'];
		$this->content['chat_id'] = $chat_id_to;
		$this->method = "forwardMessage";

		return $this;
	}

	function chat_action($type){
		$actions = ['typing', 'upload_photo', 'record_video', 'upload_video', 'record_audio', 'upload_audio', 'upload_document', 'find_location'];
		if(!in_array($type, $actions)){ $type = $actions[0]; } // Default is typing
		$this->content['action'] = $type;
		$this->method = "sendChatAction";
		return $this;
	}

	function kick($user = NULL, $chat = NULL, $keep = FALSE){
				$this->ban($user, $chat, $keep);
		return  $this->unban($user, $chat, $keep);
	}

	function ban($user = NULL, $chat = NULL, $keep = FALSE){ return $this->_parse_generic_chatFunctions("kickChatMember", $keep, $chat, $user); }
	function unban($user = NULL, $chat = NULL, $keep = FALSE){ return $this->_parse_generic_chatFunctions("unbanChatMember", $keep, $chat, $user); }
	function leave_chat($chat = NULL, $keep = FALSE){ return $this->_parse_generic_chatFunctions("leaveChat", $keep, $chat); }
	function get_chat($chat = NULL, $keep = FALSE){ return $this->_parse_generic_chatFunctions("getChat", $keep, $chat); }
	function get_admins($chat = NULL, $keep = FALSE){ return $this->_parse_generic_chatFunctions("getChatAdministrators", $keep, $chat); }
	function get_member_info($user = NULL, $chat = NULL, $keep = FALSE){ return $this->_parse_generic_chatFunctions("getChatMember", $keep, $chat, $user); }
	function get_members_count($chat = NULL, $keep = FALSE){ return $this->_parse_generic_chatFunctions("getChatMembersCount", $keep, $chat); }
	function get_user_avatar($user = NULL, $offset = NULL, $limit = 100){
		if(!empty($user)){ $this->content['user_id'] = $user; }
		$this->content['offset'] = $offset;
		$this->content['limit'] = $limit;
		$this->method = "getUserProfilePhotos";

		$res = $this->send($keep);
		if(!isset($res['photos']) or empty($res['photos'])){ return FALSE; }
		return $res['photos'];
	}

	// DEBUG
	/* function get_message($message, $chat = NULL){
		$this->method = 'getMessage';
		if(empty($chat) && !isset($this->context['chat_id'])){
			$this->context['chat_id'] = $this->parent->chat->id;
		}

		return $this->send();
	} */

	function answer_callback($alert = FALSE, $text = NULL, $id = NULL){
		// Function overload :>
		// $this->text can be empty. (Answer callback with empty response to finish request.)
		if($text == NULL && $id == NULL){
			$text = $this->content['text'];
			if($this->parent instanceof Receiver && $this->parent->key == "callback_query"){
				$id = $this->parent->id;
			}
			if(empty($id)){ return $this; } // HACK
			$this->content['callback_query_id'] = $id;
			$this->content['text'] = $text;
			$this->content['show_alert'] = $alert;
			$this->method = "answerCallbackQuery";
		}

		return $this->send();
	}

	function edit($type){
		if(!in_array($type, ['text', 'message', 'caption', 'keyboard', 'inline', 'markup'])){ return FALSE; }
		if(isset($this->content['text']) && in_array($type, ['text', 'message'])){
			$this->method = "editMessageText";
		}elseif(isset($this->content['caption']) && $type == "caption"){
			$this->method = "editMessageCaption";
		}elseif(isset($this->content['inline_keyboard']) && in_array($type, ['keyboard', 'inline', 'markup'])){
			$this->method = "editMessageReplyMarkup";
		}else{
			return FALSE;
		}

		return $this->send();
	}

	function delete($message = NULL, $chat = NULL){
		if($message === TRUE or (empty($message) && !isset($this->context['message_id']))){
			$this->message(TRUE);
		}elseif(is_array($message) and isset($message["message_id"])){
			$this->message($message["message_id"]);
		}elseif(!empty($message)){
			$this->message($message);
		}

		if($message === TRUE or (empty($chat) && !isset($this->context['chat_id']))){
			$this->chat(TRUE);
		}elseif(!empty($chat)){
			$this->chat($chat);
		}

		$this->method = "deleteMessage";
		return $this->send();
	}

	function _push($key, $val){
		$this->content[$key] = $val;
		return $this;
	}

	function _reset(){
		$this->method = NULL;
		$this->content = array();
	}

	private function _url($with_method = FALSE, $host = "api.telegram.org"){
		$url = ("https://$host/bot" .$this->bot->id .':' .$this->bot->key .'/');
		if($with_method){ $url .= $this->method; }
		return $url;
	}

	function send($keep = FALSE, $_broadcast = FALSE){
		if(!empty($this->broadcast) and !$_broadcast){
			$result = array();
			foreach($this->broadcast as $chat){
				$this->content['chat_id'] = $chat;
				// Send and keep data
				$result[] = $this->send(TRUE, TRUE);
			}
			return $result;
		}

		if(empty($this->method)){ return FALSE; }
		if(empty($this->content['chat_id']) && $this->parent instanceof Receiver){ $this->content['chat_id'] = $this->parent->chat->id; }

		$result = $this->Request($this->method, $this->content);
		if($keep === FALSE){ $this->_reset(); }
		return $result;
	}

	function _parse_generic_chatFunctions($action, $keep, $chat, $user = FALSE){
		$this->method = $action;
		if($user === FALSE){ // No hay user.
			if(empty($chat) && empty($this->chat())){ return FALSE; }
		}else{
			if(empty($user) && empty($chat) && (empty($this->chat()) or empty($this->user()))){ return FALSE; }
		}
		if(!empty($chat)){ $this->content['chat_id'] = $chat; }
		if(!empty($user)){ $this->content['user_id'] = $user; }
		return $this->send($keep);
		// return $this;
	}

	function RequestWebhook($method, $parameters) {
		if (!is_string($method)) {
			error_log("Method name must be a string\n");
			return false;
		}

		if (!$parameters) {
			$parameters = array();
		} else if (!is_array($parameters)) {
			error_log("Parameters must be an array\n");
			return false;
		}

		$parameters["method"] = $method;

		header("Content-Type: application/json");
		echo json_encode($parameters);
		return true;
	}

	function exec_curl_request($handle) {
		$response = curl_exec($handle);

		if ($response === false) {
			$errno = curl_errno($handle);
			$error = curl_error($handle);
			error_log("Curl returned error $errno: $error\n");
			curl_close($handle);
			return false;
		}

		$http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
		curl_close($handle);

		if ($http_code >= 500) {
		// do not wat to DDOS server if something goes wrong
			sleep(10);
			return false;
		} else if ($http_code != 200) {
			$response = json_decode($response, true);
			error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
			if ($http_code == 401) {
				throw new \Exception('Invalid access token provided');
			}
			return false;
		} else {
			$response = json_decode($response, true);
			if (isset($response['description'])) {
				error_log("Request was successfull: {$response['description']}\n");
			}
			$response = $response['result'];
		}

		return $response;
	}

	function Request($method, $parameters) {
		if (!is_string($method)) {
			error_log("Method name must be a string\n");
			return false;
		}

		if (!$parameters) {
			$parameters = array();
		} else if (!is_array($parameters)) {
			error_log("Parameters must be an array\n");
			return false;
		}

		foreach ($parameters as $key => &$val) {
		// encoding to JSON array parameters, for example reply_markup
			if (!is_numeric($val) && !is_string($val)) {
				$val = json_encode($val);
			}
		}
		$url = $this->_url() .$method.'?'.http_build_query($parameters);

		$handle = curl_init($url);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($handle, CURLOPT_TIMEOUT, 60);

		return $this->exec_curl_request($handle);
	}

	function RequestJson($method, $parameters) {
		if (!is_string($method)) {
			error_log("Method name must be a string\n");
			return false;
		}

		if (!$parameters) {
			$parameters = array();
		} else if (!is_array($parameters)) {
			error_log("Parameters must be an array\n");
			return false;
		}

		$parameters["method"] = $method;

		$handle = curl_init($this->_url());
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($handle, CURLOPT_TIMEOUT, 60);
		curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
		curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

		return $this->exec_curl_request($handle);
	}
}


?>
