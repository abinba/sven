<?php

namespace Telebot\Core;


use Telebot\Addons\Scene;

class Bot
{

    const RUN_AS_POLL = 0;
    const RUN_AS_HOOK = 1;

    private $api;
    /**
     * $ctx Context
     */
    public $ctx;

    public $commandHandlers = [];
    public $textHandlers = [];
    public $messageHandlers = [];
    public $updateHandlers = [];
    public $actionHandlers = [];

    private $handlers = [];
    private $scenes = [];
    /**
     * 'sceneKey' => [
     * 'enterHandler' => $func,
     * 'handlers' => array [$func],
     * 'leaveHandler' => $func,
     */

    public $addons = [];

    private $customParams = [ // Для onText
        '{INT}' => '([\d]+)',
        '{STR}' => '([\s\S]+)',
        '{NUM}' => '([\d])',
        '{SYM}' => '([\w])',
        '{ENG}' => '([A-Za-z]+)',
        '{RUS}' => '([А-Яа-я]+)',
        '{UKR}' => '([А-Яа-яёЁЇїІіЄєҐґ]+)',
    ];

    private $customTypes = [
        'int' => '[\d]+',
        'num' => '[\d]',
        'str' => '[\s\S]+',
        'chr' => '[\w]',
        'eng' => '[A-Za-z]+',
        'rus' => '[А-Яа-я]+',
        'ukr' => '[А-Яа-яёЁЇїІіЄєҐґ]+'
    ];

    public function __construct($settings)
    {
        $this->api = new API($settings);
    }

    public function ctx(): Context
    {
        return $this->ctx;
    }

    public function run()
    {
        switch ($this->api->settings['run_type']) {
            case self::RUN_AS_POLL:
                $update_id = 0;
                while (true) {
                    $updates = $this->api->getUpdates(['offset' => $update_id]);
                    if ($updates->ok) {
                        foreach ($updates->result as $update) {
                            $update_id = $update->update_id + 1;
                            $this->processUpdate($update);
                        }
                    } else {
                        $this->api->trace('После критической ошибки, бот не был запущен в режиме Long Poll');
                        break;
                    }
                }
                break;

            case self::RUN_AS_HOOK:
                if (isset($_REQUEST)) {
                    $this->processUpdate(json_decode(file_get_contents('php://input')));
                }
                break;
        }
    }

    public function processUpdate($update)
    {
        $this->ctx = new Context($update, $this->api, $this->scenes);
        if ($this->loadAddons()) {
            if($this->ctx->usedControl()) {
                $scene = (isset($this->scenes[$this->ctx()->getState()])) ? $this->scenes[$this->ctx()->getState()] : null;
                if(is_array($scene)) {
                    $handlers = $scene['handlers'];
                    foreach ($handlers as $handler) {
                        if ($handler($this->ctx) === true) break;
                    }
                    return;
                }
            }
            foreach ($this->handlers as $handler) {
                if ($handler($this->ctx) === true) break;
            }
        }
    }


    private function parseCommands($message)
    {
        $commands = [];
        foreach ($message->entities as $entity) {
            if ($entity->type == 'bot_command') {
                $commands[] = mb_substr($message->text, $entity->offset, $entity->length);
            }
        }
        return $commands;
    }

    private function loadAddons()
    {
        $ctx = $this->ctx;
        foreach ($this->addons as $addon) {
            $ctx = $addon($ctx);
            if (!$ctx) return false;
        }
        $this->ctx = $ctx;
        return true;
    }

    private function initParams($param)
    {
        preg_match_all("{([A-Za-z]+:[A-Za-z]+)}", $param, $matches);
        $matches = $matches[0];
        foreach ($matches as $preset) {
            list($key, $type) = explode(':', $preset);
            $param = str_replace('{' . $preset . '}', '(?<' . $key . '>' . $this->customTypes[$type] . ')', $param);
        }
        return str_replace(array_flip($this->customParams), $this->customParams, $param);
    }

    private function parseParams($matches)
    {
        $output = [];
        foreach ($matches as $k => $match) {
            if (is_string($k)) $output[$k] = $match;
        }
        return $output;
    }

    /**
     * @param $func
     */
    public function addHandler($func)
    {
            $this->handlers[] = $func;
    }

    public function txt($text, $func, $regex = false, $anyCase = true)
    {
        $case = $anyCase ? 'i' : '';
        $text = !$regex ? '#^' . $this->initParams($text) . '$#u' . $case : $text;
        $this->addHandler(function (Context $ctx) use ($text, $func) {
            if (!isset($ctx->update->message->text)) return false;
            if (preg_match($text, $ctx->update->message->text, $matches)) {
                $ctx->params = $this->parseParams($matches);
                $func($ctx, $matches);
                return true;
            }
            return false;
        });
    }

    public function cmd($command, $func)
    {
        $this->addHandler(function (Context $ctx) use ($command, $func) {
            if (!isset($ctx->update->message->entities)) return false;
            $commands = $this->parseCommands($ctx->update->message);
            if (count($commands) == 0) return false;
            foreach ($commands as $cmd) {
                if ('/' . $command == $cmd) {
                    $func($ctx);
                    return true;
                }
            }
            return false;
        });
    }

    public function hears($text, $func)
    {
        if (is_array($text)) {
            foreach ($text as $string) {
                $this->txt("/$string/iu", $func, true);
            }
        } else $this->txt("/$text/iu", $func, true);
    }

    public function onMessage($field, $func)
    {
        $this->addHandler(function (Context $ctx) use ($field, $func) {
            if (isset($ctx->update->message->$field)) {
                $func($ctx);
                return true;
            }
            return false;
        });
    }

    public function onUpdate($field, $func)
    {
        $this->addHandler(function (Context $ctx) use ($field, $func) {
            if (isset($ctx->update->$field)) {
                $func($ctx);
                return true;
            }
            return false;
        });
    }

    public function act($act, $func, $regex = false, $anyCase = true)
    {
        $case = $anyCase ? 'i' : '';
        $act = !$regex ? '#^' . $this->initParams($act) . '$#u' . $case : $act;
        $this->addHandler(function (Context $ctx) use ($act, $func) {
            if (preg_match($act, $ctx->callbackQuery()->data, $matches)) {
                $ctx->params = $this->parseParams($matches);
                $func($ctx, $matches);
                return true;
            }
            return false;
        });
    }

    public function inlQuery($query, $func, $regex = false, $anyCase = true)
    {
        $case = $anyCase ? 'i' : '';
        $query = !$regex ? '#^' . $this->initParams($query) . '$#u' . $case : $query;
        $this->addHandler(function(Context $ctx) use ($query, $func) {
            if (preg_match($query, $ctx->inlineQuery()->query, $matches)) {
                $ctx->params = $this->parseParams($matches);
                $func($ctx, $matches);
                return true;
            }
            return false;
        });
    }

    public function addScene(Scene $scene)
    {
        $this->scenes[$scene->name] = $scene->import();
    }

    public function addScenes()
    {
        $scenes = func_get_args();
        foreach ($scenes as $scene) {
            $this->addScene($scene);
        }
    }

    public function usage($func)
    { // Добавление промежуточных функций
        $this->addons[] = $func;
    }

    public function addUsages()
    {
        $usages = func_get_args();
        foreach ($usages as $usage) {
            $this->usage($usage);
        }
    }

}