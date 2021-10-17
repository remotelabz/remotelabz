<?php

/**
* Class Gravatar
*
* From Gravatar Help:
* "A gravatar is a dynamic image resource that is requested from our server. The request
* URL is presented here, broken into its segments."
* Source:
* http://site.gravatar.com/site/implement
*
* Usage:
* <code>
* $email = "youremail@yourhost.com";
* $default = "http://www.yourhost.com/default_image.jpg"; // Optional
* $gravatar = new Gravatar($email, $default);
* $gravatar->size = 80;
* $gravatar->rating = "G";
* $gravatar->border = "FF0000";
*
* echo $gravatar; // Or echo $gravatar->toHTML();
* </code>
*
* Class Page: http://www.phpclasses.org/browse/package/4227.html
*
* @author Lucas Araújo <araujo.lucas@gmail.com>
* @version 1.0
* @package Gravatar
*/

namespace App\Utils;

class Gravatar
{
    /**
     * Get either a Gravatar URL or complete image tag for a specified email address.
     *
     * @param string $email The email address
     * @param string $s Size in pixels, defaults to 80px [ 1 - 2048 ]
     * @param string $d Default imageset to use [ 404 | mp | identicon | monsterid | wavatar ]
     * @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
     * @param boole $img True to return a complete IMG tag False for just the URL
     * @param array $atts Optional, additional key/value attributes to include in the IMG tag
     * @return String containing either just a URL or a complete image tag
     * @source https://gravatar.com/site/implement/images/php/
     */

    static function getGravatar( $email, $s = 128, $d = 'identicon', $r = 'r', $img = false, $atts = array() ) {
        $url = 'https://www.gravatar.com/avatar/';
        $url .= md5( strtolower( trim( $email ) ) );
        $url .= "?s=$s&d=$d&r=$r";
        if ( $img ) {
            $url = '<img src="' . $url . '"';
            foreach ( $atts as $key => $val )
                $url .= ' ' . $key . '="' . $val . '"';
            $url .= ' />';
        }
        return $url;
    }
}
