<?php

namespace Telebot\Addons;

use Telebot\Core\Context;
use RedBeanPHP\R;

class Tasks {

	public function getTasks() { 
		$tasks = R::find( 'tasks' , 'LIMIT 5');

		return $tasks;
	}

	public function getTask($tasknum) { 
		$task = R::findOne( 'tasks' , 'id = ?', [$tasknum]);

		return $task;
	}

	public function getTaskPos($pos, $descOrder = false) {
		if(!$descOrder) 
			$tasks = R::find( 'tasks' , 'id > ? LIMIT 5', [$pos]);
		else 
			$tasks = R::find( 'tasks' , 'id < ? LIMIT 5', [$pos]);

		return $tasks;
	}

	public function getSearchPos($pos, $req, $descOrder = false) {
		if(!$descOrder) 
			$tasks = R::find( 'tasks' , '(name = ? OR diff = ?) AND id > ? LIMIT 5', [$req, $req, $pos]);
		else 
			$tasks = R::find( 'tasks' , '(name = ? OR diff = ?) AND id <= ? LIMIT 5', [$req, $req, $pos]);

		return $tasks;
	}

	public function searchTasksName($req) {
		$tasks = R::find( 'tasks' , ' name LIKE ? LIMIT 5', ["%".$req."%"]);

		return $tasks;
	}

	public function searchTasksDiff($req) {
		$tasks = R::find( 'tasks' , ' diff LIKE ? LIMIT 5', ["%".$req."%"]);

		return $tasks;
	}

	public function getUserID($chat_id) {
		$userid = R::findOne( 'tgusers' , 'user_id = ?', [$chat_id]);

		return $userid;
	}


}