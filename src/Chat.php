<?php

namespace Telegram;

class Chat extends User {
	public $title;
	public $type;
	public $members = NULL;
	private $admins = array();
	public $creator = NULL;
	public $all_members_are_administrators = FALSE;

	function is_group(){
		return (in_array($this->type, ["group", "supergroup"]));
	}

	function parse($bot, $full = FALSE){
		if(is_bool($bot)){
			// Flip if needed
			$tmp = $bot;
			$bot = $full;
			$full = $tmp;
			unset($tmp);
		}
		$this->info($bot);
		if($full == TRUE){
			$this->admins($bot);
			$this->count($bot);
		}
	}

	function admins($bot = NULL){
		if(!empty($this->admins) or empty($bot)){ return $this->admins; }
		$send = new Sender($bot);
		$admins = $send->get_admins($this->id);
		foreach($admins as $u){
			$this->admins[] = $u['user']['id'];
			if($u['status'] == 'creator'){
				$this->creator = $u['user']['id'];
			}
		}
		return $this->admins;
	}

	function count($bot = NULL){
		if(!empty($this->members) or empty($bot)){ return $this->members; }
		$send = new Sender($bot);
		$this->members = $send->get_members_count($this->id);
		return $this->members;
	}

	function ban($user, $bot){ return $this->__admin_user_kick($user, $bot, 'ban'); }
	function kick($user, $bot){ return $this->__admin_user_kick($user, $bot, 'kick'); }
	function unban($user, $bot){ return $this->__admin_user_kick($user, $bot, 'unban'); }
	function __admin_user_kick($user, $bot, $action){
		// Flip if needed
		if($user instanceof Bot){
			$tmp = $bot;
			$bot = $user;
			$user = $tmp;
			unset($tmp);
		}
		if($user instanceof User){ $user = $user->id; }
		$send = new Sender($bot);
		return $send->$action($user, $this->id);
	}

	function __construct($id, $type = NULL){
		unset($this->is_bot);

		if(is_array($id)){
			foreach($id as $k => $v){ $this->$k = $v; }
		}else{
			$this->id = intval($id);
			$this->type = $type;
		}

		foreach(['first_name', 'last_name', 'title'] as $name){
			if(!empty($this->{$name})){
				$this->{$name} = str_replace("\u{202e}", "", $this->{$name});
			}
		}

		if(!$this->is_group()){
			$this->members = 2;
			unset($this->all_members_are_administrators);
			unset($this->title);
		}else{
			unset($this->first_name);
			unset($this->last_name);
		}
		return $this;
	}

	function __toString(){
		if($this->is_group()){ return  $this->title; } // Group
		return ($this->first_name ." " .$this->last_name); // User
	}
}

?>
