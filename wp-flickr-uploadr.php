<?php
/*
Plugin Name: wp-flickr-uploadr
Plugin URI: https://github.com/pbodilis/wp-flickr-uploadr
Description: uploads any 'image' post from your wordpress instance to flickr using email and your flickr API key.
Version: 0.1
Author: Pierre Bodilis
Author URI: http://rataki.eu/

Copyright 2013  Pierre Bodilis (email : pierre.bodilis+github@gmail.com)

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
    const UPLOAD_TO_FLICKR_ACTION = 'upload_to_flickr';

    public function __construct() {
        if (is_admin()) {
            // add setting page
            add_action('admin_menu', array($this, 'add_plugin_menu'));
            add_action('admin_init', array($this, 'page_init'));

            // admin actions/filters for bulk action
            add_action('admin_footer-edit.php', array(&$this, 'custom_bulk_admin_footer'));
            add_action('load-edit.php',         array(&$this, 'custom_bulk_action'));
            add_action('admin_notices',         array(&$this, 'custom_bulk_admin_notices'));

            // add action upload to flickr
            add_filter('post_row_actions',      array(&$this, 'add_flickr_uploadr_action_row'), 10, 2);
            add_action('post.php',              array(&$this, 'upload_to_flickr'));
        }
    }

    /**
     * Add the "Upload to flickr" option to the bulk action dropdown menu
     */
    function custom_bulk_admin_footer() {
        global $post_type;

        if($post_type == 'post') {
            ?>
                <script type="text/javascript">
                    jQuery(document).ready(function() {
                        jQuery('<option>').val('export').text('<?php _e('Upload to flickr')?>').appendTo("select[name='action']");
                        jQuery('<option>').val('export').text('<?php _e('Upload to flickr')?>').appendTo("select[name='action2']");
                    });
                </script>
            <?php
        }
    }

    function add_flickr_uploadr_action_row($actions, $post) {
        $actions['flickr_uploadr'] = '<a href="' . admin_url('upload-to-flickr.php?post=' . $post->ID) . '">' . __('Upload to flickr') . '</a>';
        return $actions;
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


    /**
        * Step 2: handle the custom Bulk Action
        *
        * Based on the post http://wordpress.stackexchange.com/questions/29822/custom-bulk-action
        */
    function custom_bulk_action() {
        global $typenow;
        $post_type = $typenow;

        if($post_type == 'post') {

            // get the action
            $wp_list_table = _get_list_table('WP_Posts_List_Table');  // depending on your resource type this could be WP_Users_List_Table, WP_Comments_List_Table, etc
            $action = $wp_list_table->current_action();

            $allowed_actions = array("export");
            if(!in_array($action, $allowed_actions)) return;

            // security check
            check_admin_referer('bulk-posts');

            // make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
            if(isset($_REQUEST['post'])) {
                $post_ids = array_map('intval', $_REQUEST['post']);
            }

            if(empty($post_ids)) return;

            // this is based on wp-admin/edit.php
            $sendback = remove_query_arg( array('exported', 'untrashed', 'deleted', 'ids'), wp_get_referer() );
            if ( ! $sendback )
                $sendback = admin_url( "edit.php?post_type=$post_type" );

            $pagenum = $wp_list_table->get_pagenum();
            $sendback = add_query_arg( 'paged', $pagenum, $sendback );

            switch($action) {
                case 'export':

                    // if we set up user permissions/capabilities, the code might look like:
                    //if ( !current_user_can($post_type_object->cap->export_post, $post_id) )
                    //  wp_die( __('You are not allowed to export this post.') );

                    $exported = 0;
                    foreach( $post_ids as $post_id ) {

                        if ( !$this->perform_export($post_id) )
                            wp_die( __('Error exporting post.') );

                        $exported++;
                    }

                    $sendback = add_query_arg( array('exported' => $exported, 'ids' => join(',', $post_ids) ), $sendback );
                break;

                default: return;
            }

            $sendback = remove_query_arg( array('action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status',  'post', 'bulk_edit', 'post_view'), $sendback );

            wp_redirect($sendback);
            exit();
        }
    }


    /**
        * Step 3: display an admin notice on the Posts page after exporting
        */
    function custom_bulk_admin_notices() {
        global $post_type, $pagenow;

        if($pagenow == 'edit.php' && $post_type == 'post' && isset($_REQUEST['exported']) && (int) $_REQUEST['exported']) {
            $message = sprintf( _n( 'Post exported.', '%s posts exported.', $_REQUEST['exported'] ), number_format_i18n( $_REQUEST['exported'] ) );
            echo "<div class=\"updated\"><p>{$message}</p></div>";
        }
    }

    function perform_export($post_id) {
        // do whatever work needs to be done
        return true;
    }
}

$flickr_uploadr = new flickr_uploadr();