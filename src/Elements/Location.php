<?php

namespace Telegram\Elements;

class Location {
	public $longitude;
	public $latitude;

	function __construct($data = NULL){
		if(is_array($data)){
			foreach($data as $k => $v){ $this->$k = $v; }
		}
	}

	function __toString(){
		return (string) $this->latitude .", " .$this->longitude;
	}
}

?>
