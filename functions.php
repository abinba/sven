<?php 

namespace Functions; 

use Telebot\Addons\Keyboard,

class Functions {
    
    public function getImage($url, $num, $delnum) {
        $request = str_replace(' ', '-', $req);
        $content = file_get_contents("https://unsplash.com/".$url); // "search/photos/"
        $array = explode(" ", $content);
        $length = count($array);

        $sample = "https://images.unsplash.com/photo-";

        $n = 1; 
        $photos = array();
        $exp = array();

        for($pos = 0; $pos < $length; $pos++) {
            if($n <= $num) {
                if(stristr($array[$pos], $sample)) {

                    $exp = explode("photo-", $array[$pos]);
                    $expl[$n] = explode("ixlib", $exp[1]); 
                    
                    if(isset($photos[$n-1])) {
                        
                        $photos[$n] = stristr($array[$pos], $sample);
                        
                        if($expl[$n][0] != $expl[$n-1][0])
                            $n++;   
                    
                    } else {
                        $photos[$n] = stristr($array[$pos], $sample);
                        $n++;
                    }
                }
            } else {
                break;
            }
        }

        $num = 0;

        foreach($photos as $photo) {
            $right = explode("crop", $photo);
            if($num >= $delnum) {
                $result[$num] = $right[0];
            }
            $num++;
        }

        return $result;
    }

    public function getDayPhoto() {
        $content = file_get_contents("https://unsplash.com");
        $array = explode(' ', $content);
        $length = count($array);

        $sample = 'srcset="';

        $n = 1; 
        $photo = "";

        for($pos = 0; $pos < $length; $pos++) {
            if($n < 2) {
                if(stristr($array[$pos], $sample)) {
                    $photo = stristr($array[$pos], $sample);
                    $n++;
                }
            } else {
                break;
            }
        }

        $right = explode('"', $photo);
        $result = $right[1];

        return $result;
    }

    public function generatePhotoKeyboard($collected, $info) {
        $menu = new Keyboard(Keyboard::INLINE);
        
        if($collected) {    
            $text = "Remove";
            $action = "collect.remove".$info;
        } else {
            $text = "Collect+";
            $action = "collect.plus".$info;
        }
        
        $menu->row(Keyboard::btn($text, $action), Keyboard::btn('Get Original', 'get.original'.$info));
        return $menu;
    }

    public function generateMoreKeyboard() {
        $menu = new Keyboard(Keyboard::INLINE);
        $menu->row(Keyboard::btn('Get More', 'get.more'), Keyboard::btn('No, thanks', 'no.thanks'));
        return $menu;
    }

    public function saveImageDinfo($thisUser, $image) {
        $first = explode("photo-", $image);
        $image_id = explode("?", $first[1]);
        $info = $image_id[0];
        
        return $info;
    }
}