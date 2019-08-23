<?php

namespace Telebot\Addons\Inline;


use Telebot\Addons\Inline\Base\InlineQueryResult;

class Location extends InlineQueryResult
{
    public function __construct($id)
    {
        $this->out['type'] = 'location';
        $this->out['id'] = $id;
    }

    public function latitude($latitude)
    {
        $this->out['latitude'] = $latitude;
        return $this;
    }

    public function longitude($longitude)
    {
        $this->out['longitude'] = $longitude;
        return $this;
    }

    public function title($title)
    {
        $this->out['title'] = $title;
        return $this;
    }

    public function livePeriod($livePeriod)
    {
        $this->out['live_period'] = $livePeriod;
        return $this;
    }

    public function thumbUrl($thumb_url)
    {
        $this->out['thumb_url'] = $thumb_url;
        return $this;
    }

    public function thumbWidth($thumb_width)
    {
        $this->out['thumb_width'] = $thumb_width;
        return $this;
    }

    public function thumbHeight($thumb_height)
    {
        $this->out['thumb_height'] = $thumb_height;
        return $this;
    }
}