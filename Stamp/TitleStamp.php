<?php

namespace Flasher\Prime\Stamp;

class TitleStamp implements StampInterface
{
    /**
     * @var string
     */
    private $title;

    /**
     * @param string $title
     */
    public function __construct($title)
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }
}
