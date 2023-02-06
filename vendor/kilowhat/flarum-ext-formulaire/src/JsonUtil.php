<?php

namespace Kilowhat\Formulaire;

class JsonUtil
{
    /**
     * Most of the application works with associative array
     * But when it's time to return the data, we want to make sure the JSON cast will transform an empty array to object
     * @param $data
     * @return \stdClass
     */
    public static function associativeArrayToObject($data)
    {
        if (is_array($data) && count($data) === 0) {
            return new \stdClass();
        }

        return $data;
    }
}
