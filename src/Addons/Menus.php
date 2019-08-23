<?php

namespace Telebot\Addons;

use Telebot\Core\Context;
use Telebot\Addons\Keyboard;

class Menus {

	public function startMenu() {
		$menu = new Keyboard('inline_keyboard');
		$menu->row("Tasks", "Discussions");
		$menu->row("Lessons");
		$menu->row("Profile");
		return $menu;
	}

	public function tasksMenu($sort = false) {
		if(!$sort) {
			$menu = new Keyboard('inline_keyboard');
			$menu->row("<<", ">>");
			$menu->row("Sort by", "Search");
			$menu->row("Back");
			return $menu;
		} else {
			$menu = new Keyboard('inline_keyboard');
			$menu->row("<<", ">>");
			$menu->row("Sort", "Search");
			$menu->row("Sort by difficulty");
			$menu->row("Sort by solutions number");
			$menu->row("Back");
			return $menu;
		}
	}

	public function langChoiceMenu() {
		$menu = new Keyboard('inline_keyboard');
		$menu->row("Cpp11", "Python3.5");
		$menu->row("PascalABC");
		$menu->row("Back");
		return $menu;
	}

}