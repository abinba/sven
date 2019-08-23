<?php

namespace Telebot\Addons;

use Telebot\Core\Context;
use RedBeanPHP\R;

class Profile {

	public function getRating($user_id) {
		$rating = R::findOne( 'tgusers' , 'user_id = ?', [$user_id]);

		return $rating;
	}

}