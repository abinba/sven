<?php

require_once 'vendor/autoload.php';

use Telebot\Core\Bot,
    Telebot\Core\Context;
use Telebot\Addons\Control,
    Telebot\Addons\Scene,
    Telebot\Addons\Keyboard,
    Telebot\Addons\MediaGroup,
    Telebot\Addons\Calendar,
    Telebot\Addons\Tasks,
    Telebot\Addons\Quests,
    Telebot\Addons\Menus,
    Telebot\Addons\Profile;

use Telebot\Addons\Inline\Result, // Инлайн
    Telebot\Addons\Inline\Article,
    Telebot\Addons\Inline\InputTextMessageContent;

use RedBeanPHP\R;

R::setup( 'mysql:host=127.0.0.1;dbname=',
    '', '');

$settings = array(
    'api_token' => '',
    'base_url' => 'https://api.telegram.org/',
    'use_proxy' => false,
    'hook reply' => true,
    'debug_mode' => false,
    'log_path' => "/tb_log",
    'run_type' => Bot::RUN_AS_POLL
);

$bot = new Bot($settings);

$bot->usage(function (Context $ctx) {
   $ctx->setUserControl(new Control($ctx));
   return $ctx;
});

/* -------------------------------- Command Start ----------------------------------------*/

$bot->cmd('start', function(Context $ctx) {
	$menu = new Menus;
	$menu = $menu->startMenu();

	$startMess = "Welcome, ".$ctx->getFirstName()."!";

	$check = $ctx->reply($startMess, $menu);
    $ctx->user()->addToStorage('menu', 'start')->saveStorage();
});

/* --------------------------------Starting Scene ----------------------------------------*/

$startScene = new Scene('start');

$startScene->enter(function(Context $ctx) {
	$ctx->user()->clearStorage();

	$menu = new Menus;
	$menu = $menu->startMenu();

	$startMess = "Welcome, ".$ctx->getFirstName()."!";

	$check = $ctx->editLastMessage($startMess, Markdown,  $disableWebPreview = false, $menu);

    $ctx->user()->addToStorage('menu', 'start')->saveStorage();
    $ctx->leave();
});

$bot->addScene($startScene);

/* --------------------------------Tasks Scene ----------------------------------------*/

$taskScene = new Scene('task');

$taskScene->enter(function (Context $ctx) {
	$menu = new Menus;
	$menu = $menu->tasksMenu();

	$tasks = new Tasks;
	$taskarr = $tasks->getTasks();
    
    $tasktxt = "";
	foreach ($taskarr as $task) {
		$tasktxt .= "/t".$task['id']." - *".$task['name']."*\n";
		$position = $task['id'];
	}

	$ctx->user()->addToStorage('pos', $position)->saveStorage();
	$ctx->user()->addToStorage('menu', 'start')->saveStorage();
	$ctx->user()->addToStorage('searchpage', '0')->saveStorage();
	$ctx->editLastMessage($tasktxt, Markdown,  $disableWebPreview = false, $menu);
    $ctx->leave();
});

$bot->addScene($taskScene);

/* --------------------------------Discussions----------------------------------------*/

$disScene = new Scene('dis');

$disScene->enter(function(Context $ctx) {
    $ctx->user()->addToStorage('page', 'discuss')->saveStorage();
	$ctx->user()->addToStorage('menu', 'start')->saveStorage();

	$menu = new Keyboard('inline_keyboard');
	$menu->row("<<", ">>");
	$menu->row("Ask a question", "Find answers");
	$menu->row("Back");

	$quests = new Quests;
	$questarr = $quests->getQuests();

	if($questarr == null) {
		$questtxt = "No questions";
	} else {
	    $questtxt = "";
		foreach ($questarr as $quest) {
			$questtxt .= "/q".$quest['id']." - *".$quest['title']."*\n";
		}
	}

	$ctx->editLastMessage($questtxt, Markdown, $disableWebPreview = false, $menu);
	$ctx->leave();
});

$bot->addScene($disScene);

/* --------------------------------Adding a question----------------------------------------*/
$questScene = new Scene('quest');

$questScene->enter(function(Context $ctx) {
	$ctx->replyMarkdown('*Insert the title of your question (maximum 50 characters):*');
});

$questScene->txt('{title:str}', function(Context $ctx) {
	$length = strlen($ctx->params['title']);
	if($length > 50) {
		$ctx->replyMarkdown('*Too many characters! Try again.*');
		$ctx->enter('quest');
	} else {
		$ctx->user()->addToStorage('title', $ctx->params['title'])->saveStorage();
		$ctx->enter('req');
	}
});

$bot->addScene($questScene);

$reqScene = new Scene('req');

$reqScene->enter(function(Context $ctx) {
	$ctx->replyMarkdown('*Insert your question:*');
});

$reqScene->txt('{req:str}', function(Context $ctx) {
	$ctx->user()->addToStorage('req', $ctx->params['req'])->saveStorage();
	$ctx->leave();
});

$reqScene->leave(function(Context $ctx) {
	$ctx->user()->addToStorage('menu', 'disc')->saveStorage();
	$quests = new Quests();
	$user = $ctx->getUsername();

	$title = $ctx->user()->getFromStorage('title');
	$question = $ctx->user()->getFromStorage('req');

	$id = $quests->createQuest($title, $question, $user);

    $menu = new Keyboard('inline_keyboard');
    $menu->row("Back");

	if($id)
		$ctx->reply("Your question has been successfully created.", $menu);
});

$bot->addScene($reqScene);

/* --------------------------------Search Question Or Task----------------------------------------*/
$searchScene = new Scene('search');

$searchScene->enter(function(Context $ctx) {
	$ctx->delMessage($ctx->getChatID, $ctx->getMessageID);
	$ctx->replyMarkdown('*Insert your search request:*');
});

$searchScene->txt('{sreq:str}', function(Context $ctx) {
	$ctx->user()->addToStorage('sreq', $ctx->params['sreq'])->saveStorage();
	$ctx->enter('taskSearch');
});

$bot->addScene($searchScene);

/* --------------------------------Task Search----------------------------------------*/

$searchResScene = new Scene('taskSearch');

$searchResScene->enter(function(Context $ctx) {
	$tasks = new Tasks();

	$type = $ctx->user()->getFromStorage('taskSearch');
	$request = $ctx->user()->getFromStorage('sreq');
	$ctx->user()->addToStorage('menu', 'start')->saveStorage();

	$menu = new Menus;
	$menu = $menu->tasksMenu();

	if($type == "name")
		$searchres = $tasks->searchTasksName($request);
	elseif($type == "diff")
		$searchres = $tasks->searchTasksDiff($request);

	if($searchres == null) {
		$tasktxt = "No results found";
		$menu = new Keyboard('inline_keyboard');
		$menu->row('Back');
		$ctx->user()->addToStorage('menu', 'task')->saveStorage();
		$ctx->reply($tasktxt, $menu);
	} else {
	    $tasktxt = "";
		foreach ($searchres as $task) {
			$tasktxt .= "/t".$task['id']." - *".$task['name']."*\n";
			$position = $task['id'];
		}
		$ctx->user()->addToStorage('pos', $position)->saveStorage();
		$ctx->replyMarkdown($tasktxt, $menu);
	}

    $ctx->leave();

});

$bot->addScene($searchResScene);

/* --------------------------------Search Question Result----------------------------------------*/
$searchDisScene = new Scene('searchDis');

$searchDisScene->enter(function(Context $ctx) {
	$quests = new Quests();

	$request = $ctx->user()->getFromStorage('sreq');
	$searchres = $quests->searchQuests($request);

	$menu = new Keyboard('inline_keyboard');
	$menu->row("<<", ">>");
	$menu->row("Ask a question", "Find answers");
	$menu->row("Back");

	if($searchres == null) {
		$questtxt = "No questions";
	} else {
	    $questtxt = "";
		foreach ($searchres as $quest) {
			$questtxt .= "/q".$quest['id']." - *".$quest['title']."*\n";
		}
	}

	$ctx->replyMarkdown($questtxt, $menu);
	$ctx->leave();
});

$bot->addScene($searchDisScene);

/* --------------------------------Search Tasks Result----------------------------------------*/

$searchTScene = new Scene('searchT');

$searchTScene->enter(function(Context $ctx) {
	$menu = new Keyboard('inline_keyboard');
	$menu->row("Name", "Difficulty");
	$menu->row("Back");

	$txt = "Choose the type of search:";

	$ctx->editLastMessage($txt, Markdown,  $disableWebPreview = false, $menu);
	$ctx->user()->addToStorage('searchpage', 'search')->saveStorage();
	$ctx->leave();
});

$bot->addScene($searchTScene);

/* --------------------------------Actions----------------------------------------*/

$bot->act("Tasks", function(Context $ctx) {
	$ctx->user()->addToStorage('page', 'task')->saveStorage();
	$ctx->enter('task');
});

$bot->act("Discussions", function(Context $ctx) {
    $ctx->enter('dis');
});

$bot->act("Search", function(Context $ctx) {
	$ctx->user()->addToStorage('menu', 'task')->saveStorage();
	$ctx->enter('searchT');
});

$bot->act("Name", function(Context $ctx) {
	$ctx->user()->addToStorage('taskSearch', 'name')->saveStorage();
	$ctx->enter('search');
});

$bot->act("Difficulty", function(Context $ctx) {
	$ctx->user()->addToStorage('taskSearch', 'diff')->saveStorage();
	$ctx->enter('search');
});

$bot->act("<<", function(Context $ctx) {

	$page = $ctx->user()->getFromStorage('page');
	$spage = $ctx->user()->getFromStorage('searchpage');
	$pos = $ctx->user()->getFromStorage('pos');

	if($spage != "search") {
		if($page = "tasks") {

			$menu = new Menus;
	$menu = $menu->tasksMenu();

			if($pos > 5) {

				$tasks = new Tasks();
				$taskarr = $tasks->getTaskPos($pos, true);
                
                $tasktxt = "";
				foreach ($taskarr as $task) {
					$tasktxt .= "/t".$task['id']." - *".$task['name']."*\n";
					$position = $task['id'];
				}

				$ctx->user()->addToStorage('pos', $position)->saveStorage();
				$ctx->editLastMessage($tasktxt, Markdown,  $disableWebPreview = false, $menu);
			}

		} elseif ($page == "discuss") {
		}
	} else {
		$menu = new Menus;
		$menu = $menu->tasksMenu();

		if($pos > 5) {

			$tasks = new Tasks();
			$request = $ctx->user()->getFromStorage('sreq');
			$taskarr = $tasks->getSearchPos($pos, $request, true);
            
            $tasktxt = "";
			foreach ($taskarr as $task) {
				$tasktxt .= "/t".$task['id']." - *".$task['name']."*\n";
				$position = $task['id'];
			}

			$ctx->user()->addToStorage('pos', $position)->saveStorage();
			$ctx->editLastMessage($tasktxt, Markdown,  $disableWebPreview = false, $menu);
		}
	}
});


$bot->act(">>", function(Context $ctx) {

	$page = $ctx->user()->getFromStorage('page');
	$spage = $ctx->user()->getFromStorage('searchpage');
	$pos = $ctx->user()->getFromStorage('pos');

	if($spage != "search") {
		if($page = "tasks") {

			$menu = new Menus;
			$menu = $menu->tasksMenu();

			$tasks = new Tasks();
			$taskarr = $tasks->getTaskPos($pos);

			if($taskarr != null) {
			    $tasktxt = "";
				foreach ($taskarr as $task) {
					$tasktxt .= "/t".$task['id']." - *".$task['name']."*\n";
					$position = $task['id'];
				}

				$ctx->user()->addToStorage('pos', $position)->saveStorage();
				$ctx->editLastMessage($tasktxt, Markdown,  $disableWebPreview = false, $menu);
			}

		} elseif($page == "discuss") {
		}
	} else {
		$menu = new Menus;
		$menu = $menu->tasksMenu();

		$tasks = new Tasks();
		$request = $ctx->user()->getFromStorage('sreq');
		$taskarr = $tasks->getSearchPos($pos, $request);

		if($taskarr != null) {
		    
            $tasktxt = "";
			foreach ($taskarr as $task) {
				$tasktxt .= "/t".$task['id']." - *".$task['name']."*\n";
				$position = $task['id'];
			}

			$ctx->user()->addToStorage('pos', $position)->saveStorage();
			$ctx->editLastMessage($tasktxt, Markdown,  $disableWebPreview = false, $menu);
		}
	}

});

$bot->act("Sort by", function(Context $ctx) {
	$chat_id = $ctx->getChatID();
	$message = $ctx->getMessageID();

	$menu = new Menus;
	$menu = $menu->tasksMenu(true);

	$ctx->editKeyboard($chat_id, $message, $menu);
});

$bot->act("Sort", function(Context $ctx) {
	$chat_id = $ctx->getChatID();
	$message = $ctx->getMessageID();

	$menu = new Menus;
	$menu = $menu->tasksMenu();

	$ctx->editKeyboard($chat_id, $message, $menu);
});

$bot->txt("/t{id:int}", function(Context $ctx) {

	$tasks = new Tasks;
	$task = $tasks->getTask($ctx->params['id']);

	$menu = new Keyboard('inline_keyboard');
	$menu->row("Send solution");
	$menu->row("Back");

	$tasktxt = "*Task #".$task['id']."* - `".$task['name']
	."`\n*Difficulty - ".$task['diff']
	."*\n*Solved by ".$task['solved']." users.*"
	."\n\n".$task['task'];

	$ctx->replyMarkdown($tasktxt, $menu);
	$ctx->user()->addToStorage('menu', 'task')->saveStorage();
	$ctx->user()->addToStorage('tasknum', $ctx->params['id'])->saveStorage();
});

$bot->act("Show answers", function(Context $ctx) {
    $antworte = R::find('answers', 'quest_id = ?', [$ctx->user()->getFromStorage('id')]);
    foreach ($antworte as $antwort) {
        $ctx->reply($antwort['user'].": ".$antwort['answer']);
    }
});

$bot->act("Give an answer", function(Context $ctx) {
    $ctx->enter('answer');
});

$bot->txt("/q{id:int}", function(Context $ctx) {

    $ctx->user()->addToStorage('menu', 'dis')->saveStorage();
	$quests = new Quests;
	$quest = $quests->getQuest($ctx->params['id']);
    $ctx->user()->addToStorage('id', $ctx->params['id'])->saveStorage();
	$menu = new Keyboard('inline_keyboard');
	
	$menu->row("Show answers");
	$menu->row("Give an answer");
	$menu->row("Back");

	$questtxt = "*Question #".$quest['id']."* - `".$quest['title']
	."`\n - ".$quest['quest']
	."\n\n".$quest['task'];

	$ctx->replyMarkdown($questtxt, $menu);

});

$bot->act("Find answers", function(Context $ctx) {
	$ctx->enter('search');
});

$bot->act("Ask a question", function(Context $ctx) {
	$ctx->enter('quest');
});

$bot->act("Profile", function(Context $ctx) {
	$profile = new Profile();
    $menu = new Keyboard('inline_keyboard');
    $menu->row("My questions");
	$menu->row("Show statistics");
	$menu->row("Settings");
    $menu->row("Back");
    $chatID = $ctx->getChatID();
    $rating = $profile->getRating($chatID);
    $ctx->editLastMessage("*Nickname:* ".$ctx->getFirstName()."\n".
                        "*Rating:* "."\n".
                        "*Username:* @".$ctx->getUsername(), Markdown,  0, $menu);
});

$bot->act("My questions", function(Context $ctx) {
    $ctx->user()->addToStorage('menu', 'start')->saveStorage();
    $user = $ctx->getUsername();
    $questarr = R::find('quests' , ' user = ? ', [$user]);

    if($questarr == null) {
		$questtxt = "No questions";
	} else {
	    $questtxt = "";
		foreach ($questarr as $quest) {
			$questtxt .= "/q".$quest['id']." - *".$quest['title']."*\n";
		}
	}

    $menu = new Keyboard('inline_keyboard');
    $menu->row("Back");

	$ctx->editLastMessage($questtxt, Markdown,  $disableWebPreview = false, $menu);

});

$bot->act("Send solution", function(Context $ctx) {
	$menu = new Menus;
    $menu = $menu->langChoiceMenu();

    $newmenu = new Keyboard("inline_keyboard");
    $ctx->editKeyboard($ctx->getChatID(), $ctx->getMessageID(), $newmenu);

	$ctx->replyMarkdown("*Choose the programming language*", $menu);
});

$codeScene = new Scene('code');

$codeScene->enter(function(Context $ctx) {
	$ctx->editLastMessage("*Send your code as a message*", Markdown);
});

$codeScene->txt("{code:str}", function(Context $ctx) {
	$tasknum = $ctx->user()->getFromStorage("tasknum");
	$lang = $ctx->user()->getFromStorage("lang");

	$chatid = $ctx->getChatID();
	
	$tasks = new Tasks();
	$id = $tasks->getUserID($chatid);
	$userid = $id["id"];

	$ext = "";
	if($lang == "python3")
		$ext = ".py";
	else if($lang == "cpp11") 
		$ext = ".cpp";
	else if($lang == "pabc")
		$ext = ".pas";

	$codefile = $userid."_".$tasknum.$ext;

	$handle = fopen($codefile, "w");
	fwrite($handle, $ctx->params['code']);
	fclose($handle);

	$run = "bash /root/compile_".$lang.".sh ".$tasknum." ".$userid;
	exec($run);

	$response = $userid."_".$tasknum.".response";

	if (file_exists ($response)) {

	    $handle = fopen($response, "r");
	    $resp = fread($handle, filesize($response));
	   
	    $explode = explode('array=', $resp);
	    $newexplode = explode('=end', $explode[1]);
	    $explodes = str_replace("\n", "", $newexplode[0]);
	    $spaces = str_replace(", }", "}", $explodes);
	    $explode = str_replace(",}", "}", $spaces);
	   	
	    fclose($handle);

	    // $ctx->reply($explode);
	    $jsonresp = json_decode($explode, true);
	    $count = count($jsonresp["status"]);
	    
	    $solved = true;
	    $testnum = 1;
	    for($i = 1; $i <= $count; $i++) {

	    	if($jsonresp["status"][$i] != "OK") {
	    		$solved = false;
	    	}

	    	$testtxt = "*Test #*".$testnum
	    	."\n*Input* - `".$jsonresp["input"][$i]
	    	."`\n*Output* - `".$jsonresp["program_output"][$i]
	    	."`\n*Right Answer* - `".$jsonresp["output"][$i]
	    	."`\n*Status* - `".$jsonresp["status"][$i]."`";

	    	$ctx->replyMarkdown($testtxt);
	    	$testnum++;

	    }

	    $menu = new Keyboard('inline_keyboard');
	    $menu->row("Back");

	    if($solved) 
	    	$ctx->replyMarkdown("*Excellent! You solved task correctly and got rating increased!*", $menu);
	    else 
	    	$ctx->replyMarkdown("*Incorrect. Try Again.*", $menu);

	    /*$run = "sudo rm ".$userid."_".$tasknum."*";
	    exec($run);*/
	}

	$ctx->leave();
});

$bot->addScene($codeScene);

$bot->act("Python3.5", function (Context $ctx) {
	$ctx->user()->addToStorage("lang", "python3")->saveStorage();
	$ctx->enter('code');
});

$bot->act("PascalABC", function (Context $ctx) {
	$ctx->user()->addToStorage("lang", "pabc")->saveStorage();
	$ctx->enter('code');
});

$bot->act("Cpp11", function (Context $ctx) {
	$ctx->user()->addToStorage("lang", "cpp11")->saveStorage();
	$ctx->enter('code');
});

$bot->act("Settings", function(Context $ctx) {
    $menu = new Menus;
    $menu = $menu->langChoiceMenu();

	$ctx->editLastMessage("*Here you can change the default programming language*",
                           Markdown, 0,  $menu);
});

$bot->act("Back", function(Context $ctx) {
	$menu = $ctx->user()->getFromStorage('menu');
	$ctx->enter($menu);
});

$bot->run();
