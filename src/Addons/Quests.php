<?php

namespace Telebot\Addons;

use Telebot\Core\Context;
use RedBeanPHP\R;

class Quests {

	public function getQuests() { 
		$quests = R::find( 'quests' , 'LIMIT 5');

		return $quests;
	}

	public function getQuest($quest_id) { 
		$quest = R::findOne( 'quests' , 'id = ?', [$quest_id]);

		return $quest;
	}

	public function createQuest($title, $question, $user) {
		$quest = R::dispense( 'quests' );
		$quest->title = $title;
		$quest->quest = $question;
		$quest->user = $user;
		$id = R::store( $quest );
		return $id;
	}

	public function searchQuests($req) {
		$quests = R::find( 'quests' , ' title LIKE ? LIMIT 5', ["%".$req."%"]);

		return $quests;
	}

	public function getQuestPos($pos, $descOrder = false) {
		if(!$descOrder) 
			$quests = R::find( 'quests' , 'id > ? LIMIT 5', [$pos]);
		else 
			$quests = R::find( 'quests' , 'id <= ? LIMIT 5', [$pos]);

		return $quests;
	}

}