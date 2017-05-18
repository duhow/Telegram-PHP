<?php

namespace Telegram;

class User {
	public $id = NULL;
	public $first_name = NULL;
	public $last_name = NULL;
	public $language_code = NULL;
	public $username = NULL;
	protected $bot;
	protected $extra = array();

	function __construct($id, $first_name = NULL, $last_name = NULL, $username = NULL, $language_code = NULL){
		if(is_array($id)){
			foreach($id as $k => $v){
				$$k = $v;
			}
		}

		if($first_name instanceof Bot){
			$this->bot = $first_name;
		}

		$this->id = intval($id);
		$this->first_name = trim($first_name);
		$this->username = trim($username);
		$this->last_name = trim($last_name);
		$this->language_code = trim($language_code);

		return $this;
	}

	function avatar($id = NULL){
		// group or user, if not already get, get info
		// and save to self variable
	}

	function info($bot = NULL){
		if(!empty($this->bot) && empty($bot)){ $bot = $this->bot;}
		$send = new Sender($bot);
		$info = $send->get_chat($this->id);
		return $this->__construct($info);
	}

	function __toString(){
		return $this->first_name ." " .$this->last_name;
	}

	function __get($k){
		if(isset($this->$k)){ return $this->$k; }
		if(array_key_exists($k, $this->extra)) {
            return $this->extra[$k];
        }
		return NULL;
	}

	function __set($k, $v){
		if(isset($this->$k)){ $this->$k = $v; }
		else{ $this->extra[$k] = $v; }
	}
}

?>
