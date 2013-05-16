<?php
/*
Plugin Name: wp-flickr-uploadr
Plugin URI: http://rataki.eu/
Description: uploads any 'image' post from your wordpress instance to flickr using email and your flickr API key.
Version: 0.1
Author: Trent Gardner
Author URI: http://tgardner.net/

Copyright 2007  Trent Gardner  (email : trent.gardner@gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/



class flickr_uploadr {
    const GROUP = 'flickr_uploadr_option_group';
    const EMAIL_OPTION_NAME = 'flickr_uploadr_email';

    public function __construct() {
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_plugin_menu'));
            add_action('admin_init', array($this, 'page_init'));
        }
    }

    public function add_plugin_menu() {
        // This page will be under "Settings"
        add_options_page(
            __('Flickr Uploadr', 'wp-flickr-uploadr'), // page_title
            __('Flickr Uploadr', 'wp-flickr-uploadr'), // menu_title
            'manage_options', // capability
            'flickr-uploadr-setting', // menu_slug
            array($this, 'create_admin_page') // function
        );
    }

    public function create_admin_page() {
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2><?php echo __('Flickr Uploadr', 'wp-flickr-uploadr'); ?></h2>
            <form method="post" action="options.php">
                <?php
                    // This prints out all hidden setting fields
                    settings_fields(self::GROUP);
                    do_settings_sections('flickr-uploadr-setting');
                ?>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function page_init() {
        register_setting(self::GROUP, self::EMAIL_OPTION_NAME, array($this, 'check_email_address'));

        add_settings_section(
            'setting_section_id',
            __('Set up your email address', 'wp-flickr-uploadr'),
            null, //array($this, 'print_section_info'),
            'flickr-uploadr-setting'
        );

        add_settings_field(
            'setting_field_id', // id
            __('Email address', 'wp-flickr-uploadr'), // title
            array($this, 'get_id_field'), // 
            'flickr-uploadr-setting',
            'setting_section_id'
        );
    }

    public function check_email_address($input) {
        return $input;
    }

    public function print_section_info() {
        echo __('Enter your flickr uploadr email address:', 'wp-flickr-uploadr');
    }

    public function get_id_field(){
        echo '<input type="text" id="' . self::EMAIL_OPTION_NAME .'" name="' . self::EMAIL_OPTION_NAME . '" value="' . get_option(self::EMAIL_OPTION_NAME) . '" />';
    }
}

$flickr_uploadr = new flickr_uploadr();