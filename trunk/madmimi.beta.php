<?php
/*
Plugin Name: Gravity Forms Mad Mimi Add-On (Beta)
Plugin URI: http://www.gravityforms.com
Description: Integrates Gravity Forms with Mad Mimi allowing form submissions to be automatically sent to your Mad Mimi account
Version: 2.0.2
Requires at least: 3.2
Author: Katz Web Services, Inc.
Author URI: http://www.katzwebservices.com

------------------------------------------------------------------------
Copyright 2013 Katz Web Services, Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

add_action('plugins_loaded', 'load_KWSGFMadMimiAddon');
function load_KWSGFMadMimiAddon($value='') {
    $base = WP_PLUGIN_DIR . "/" . basename(dirname(__FILE__));
    require_once $base.'/lib/kwsaddon.php';
    require_once $base.'/madmimi.addon.php';
    if(class_exists('KWSGFMadMimiAddon')) {
        $KWSGFMadMimi = new KWSGFMadMimiAddon;
        $KWSGFMadMimi->add_custom_hooks();
    }
}