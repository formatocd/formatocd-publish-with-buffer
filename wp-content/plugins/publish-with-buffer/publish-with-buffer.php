<?php
/**
 * Plugin Name: Publish with Buffer
 * Plugin URI:  https://github.com/formatocd/publish-with-buffer
 * Description: Generates Buffer posts automatically from WordPress posts.
 * Version:     1.0.0
 * Author:      Carlos Durán
 * License:     GPL-2.0+
 * Text Domain: publish-with-buffer
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'add_meta_boxes', 'buffer_plugin_add_meta_box' );
function buffer_plugin_add_meta_box() {
    add_meta_box(
        'buffer_plugin_meta_box',      
        __( 'Publish with Buffer', 'publish-with-buffer' ),          
        'buffer_plugin_meta_box_html', 
        'post',                        
        'side',                        
        'high'                         
    );
}

function buffer_plugin_meta_box_html( $post ) {
    wp_nonce_field( 'buffer_plugin_save_meta', 'buffer_plugin_meta_nonce' );

    $already_sent = get_post_meta( $post->ID, '_buffer_sent_flag', true );

    if ( 'yes' === $already_sent ) {
        echo '<p><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <strong>' . esc_html__( 'This post has already been published to Buffer.', 'publish-with-buffer' ) . '</strong></p>';
        return;
    }

    $global_default_mode = get_option( 'buffer_default_mode', '' ); 
    $has_meta            = metadata_exists( 'post', $post->ID, '_buffer_is_active' );

    if ( ! $has_meta ) {
        if ( '' !== $global_default_mode ) {
            $is_active = 'yes';
            $mode      = $global_default_mode;
        } else {
            $is_active = 'no';       
            $mode      = 'shareNow'; 
        }
        $due_at = '';
    } else {
        $is_active = get_post_meta( $post->ID, '_buffer_is_active', true );
        $mode      = get_post_meta( $post->ID, '_buffer_mode', true );
        $due_at    = get_post_meta( $post->ID, '_buffer_due_at', true );
    }

    ?>
    <p>
        <label>
            <input type="checkbox" name="buffer_is_active" value="yes" <?php checked( $is_active, 'yes' ); ?> />
            <strong><?php esc_html_e( 'Send to Buffer on publish', 'publish-with-buffer' ); ?></strong>
        </label>
    </p>
    
    <p>
        <label for="buffer_mode"><?php esc_html_e( 'Publishing mode:', 'publish-with-buffer' ); ?></label>
        <select name="buffer_mode" id="buffer_mode" style="width: 100%;">
            <option value="shareNow" <?php selected( $mode, 'shareNow' ); ?>><?php esc_html_e( 'Share Now', 'publish-with-buffer' ); ?></option>
            <option value="addToQueue" <?php selected( $mode, 'addToQueue' ); ?>><?php esc_html_e( 'Add to Queue', 'publish-with-buffer' ); ?></option>
            <option value="customScheduled" <?php selected( $mode, 'customScheduled' ); ?>><?php esc_html_e( 'Custom Scheduled', 'publish-with-buffer' ); ?></option>
        </select>
    </p>

    <p id="buffer_date_wrapper" style="<?php echo ( $mode === 'customScheduled' ) ? 'display:block;' : 'display:none;'; ?>">
        <label for="buffer_due_at"><?php esc_html_e( 'Date and Time (UTC):', 'publish-with-buffer' ); ?></label>
        <input type="datetime-local" name="buffer_due_at" id="buffer_due_at" value="<?php echo esc_attr( $due_at ); ?>" style="width: 100%;" />
        <small><?php esc_html_e( 'Format required for Buffer.', 'publish-with-buffer' ); ?></small>
    </p>

    <?php
}

add_action( 'admin_enqueue_scripts', 'buffer_plugin_enqueue_admin_scripts' );
function buffer_plugin_enqueue_admin_scripts( $hook ) {
    if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
        return;
    }
    wp_enqueue_script( 
        'buffer-admin-script', 
        plugins_url( 'js/buffer-admin.js', __FILE__ ), 
        array(), 
        '1.0.0', 
        true 
    );
}

add_action( 'save_post', 'buffer_plugin_save_meta_box_data' );
function buffer_plugin_save_meta_box_data( $post_id ) {
    if ( ! isset( $_POST['buffer_plugin_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['buffer_plugin_meta_nonce'] ) ), 'buffer_plugin_save_meta' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return; 
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $already_sent = get_post_meta( $post_id, '_buffer_sent_flag', true );
    if ( 'yes' === $already_sent ) {
        return;
    }

    $is_active = isset( $_POST['buffer_is_active'] ) ? 'yes' : 'no';
    update_post_meta( $post_id, '_buffer_is_active', $is_active );

    if ( isset( $_POST['buffer_mode'] ) ) {
        update_post_meta( $post_id, '_buffer_mode', sanitize_text_field( wp_unslash( $_POST['buffer_mode'] ) ) );
    }

    if ( isset( $_POST['buffer_due_at'] ) && ! empty( $_POST['buffer_due_at'] ) ) {
        $date = new DateTime( sanitize_text_field( wp_unslash( $_POST['buffer_due_at'] ) ) );
        update_post_meta( $post_id, '_buffer_due_at', $date->format('Y-m-d\TH:i:s\Z') );
    } else {
        delete_post_meta( $post_id, '_buffer_due_at' );
    }

    if ( 'publish' === get_post_status( $post_id ) ) {
        buffer_plugin_check_and_send( $post_id, $is_active );
    }
}

add_action( 'publish_future_post', 'buffer_plugin_trigger_scheduled_post' );
function buffer_plugin_trigger_scheduled_post( $post_id ) {
    $is_active = get_post_meta( $post_id, '_buffer_is_active', true );
    buffer_plugin_check_and_send( $post_id, $is_active );
}

function buffer_plugin_check_and_send( $post_id, $is_active ) {
    $already_sent = get_post_meta( $post_id, '_buffer_sent_flag', true );
    
    if ( 'yes' === $is_active && 'yes' !== $already_sent ) {
        update_post_meta( $post_id, '_buffer_sent_flag', 'yes' ); 
        buffer_plugin_send_to_buffer( $post_id );
    }
}

function buffer_plugin_send_to_buffer( $post_id ) {
    $post = get_post( $post_id );
    
    $api_token   = get_option( 'buffer_api_token' );
    $channels    = get_option( 'buffer_channels', [] ); 
    $template    = get_option( 'buffer_template', '{title} - {url}' );
    $allowed_cat = get_option( 'buffer_allowed_category', '' ); 

    if ( empty( $api_token ) || empty( $channels ) ) return;

    if ( ! empty( $allowed_cat ) ) {
        $post_categories = wp_get_post_categories( $post_id );
        if ( ! in_array( $allowed_cat, $post_categories ) ) return; 
    }

    $excerpt = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : wp_trim_words( $post->post_content, 20 );
    $author_name = get_the_author_meta( 'display_name', $post->post_author );
    $categories = get_the_category( $post_id );
    $category_name = ! empty( $categories ) ? $categories[0]->name : '';
    
    $tags = get_the_tags( $post_id );
    $tags_string = '';
    if ( $tags ) {
        $tag_names = wp_list_pluck( $tags, 'name' );
        $hashtag_array = array_map(function($tag) {
            return '#' . str_replace(' ', '', $tag);
        }, $tag_names);
        $tags_string = implode(' ', $hashtag_array);
    }

    $message = str_replace(
        ['{title}', '{url}', '{excerpt}', '{author}', '{category}', '{tags}'],
        [$post->post_title, get_permalink( $post_id ), $excerpt, $author_name, $category_name, $tags_string],
        $template
    );

    $image_url = null;
    if ( has_post_thumbnail( $post_id ) ) {
        $image_url = get_the_post_thumbnail_url( $post_id, 'full' );
    }

    $mode   = get_post_meta( $post_id, '_buffer_mode', true );
    $due_at = get_post_meta( $post_id, '_buffer_due_at', true );

    $query = '
    mutation CreatePost($input: CreatePostInput!) {
      createPost(input: $input) {
        ... on PostActionSuccess {
          post { id text }
        }
        ... on MutationError {
          message
        }
      }
    }';

    foreach ( $channels as $channel_id ) {
        $input_vars = [
            'text'           => $message,
            'channelId'      => $channel_id,
            'schedulingType' => 'automatic',
            'mode'           => $mode
        ];

        if ( $image_url ) {
            $input_vars['assets'] = [ 'images' => [ ['url' => $image_url] ] ];
        }

        if ( 'customScheduled' === $mode && ! empty( $due_at ) ) {
            $input_vars['dueAt'] = $due_at;
        }

        $payload = [
            'query'     => $query,
            'variables' => [ 'input' => $input_vars ]
        ];

        wp_remote_post( 'https://api.buffer.com', [
            'headers'     => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_token,
            ],
            'body'        => wp_json_encode( $payload ),
            'data_format' => 'body',
            'timeout'     => 15,
            'blocking'    => false 
        ] );
    }
}

add_action( 'admin_menu', 'buffer_plugin_add_admin_menu' );
function buffer_plugin_add_admin_menu() {
    add_menu_page(
        __( 'Publish with Buffer', 'publish-with-buffer' ),            
        __( 'Publish with Buffer', 'publish-with-buffer' ),            
        'manage_options',                 
        'publish-with-buffer',            
        'buffer_plugin_settings_page_html', 
        'dashicons-share',                
        80                                
    );
}

add_action( 'admin_init', 'buffer_plugin_settings_init' );
function buffer_plugin_settings_init() {
    register_setting( 'buffer_plugin_settings_group', 'buffer_api_token', 'sanitize_text_field' );
    register_setting( 'buffer_plugin_settings_group', 'buffer_channels', 'buffer_plugin_sanitize_channels' );
    register_setting( 'buffer_plugin_settings_group', 'buffer_template', 'sanitize_textarea_field' );
    register_setting( 'buffer_plugin_settings_group', 'buffer_allowed_category', 'absint' );
    register_setting( 'buffer_plugin_settings_group', 'buffer_default_mode', 'sanitize_text_field' );

    add_settings_section(
        'buffer_plugin_main_section',
        __( 'API and Publishing Settings', 'publish-with-buffer' ),
        '__return_empty_string', 
        'publish-with-buffer'
    );

    add_settings_field( 'buffer_api_token', __( 'Buffer API Token', 'publish-with-buffer' ), 'buffer_plugin_api_token_cb', 'publish-with-buffer', 'buffer_plugin_main_section' );
    add_settings_field( 'buffer_channels', __( 'Channel IDs', 'publish-with-buffer' ), 'buffer_plugin_channels_cb', 'publish-with-buffer', 'buffer_plugin_main_section' );
    add_settings_field( 'buffer_template', __( 'Message Template', 'publish-with-buffer' ), 'buffer_plugin_template_cb', 'publish-with-buffer', 'buffer_plugin_main_section' );
    add_settings_field( 'buffer_default_mode', __( 'Default Publishing Mode', 'publish-with-buffer' ), 'buffer_plugin_mode_cb', 'publish-with-buffer', 'buffer_plugin_main_section' );
    add_settings_field( 'buffer_allowed_category', __( 'Filter by Category', 'publish-with-buffer' ), 'buffer_plugin_category_cb', 'publish-with-buffer', 'buffer_plugin_main_section' );
}

function buffer_plugin_sanitize_channels( $input ) {
    if ( is_array( $input ) ) return $input;
    $channels = explode( ',', $input );
    $channels = array_map( 'trim', $channels );
    $channels = array_map( 'sanitize_text_field', $channels );
    return array_filter( $channels ); 
}

function buffer_plugin_api_token_cb() {
    $val = get_option( 'buffer_api_token' );
    echo '<input type="password" name="buffer_api_token" value="' . esc_attr( $val ) . '" class="regular-text" />';
}

function buffer_plugin_channels_cb() {
    $val = get_option( 'buffer_channels', [] );
    $val_string = is_array( $val ) ? implode( ', ', $val ) : '';
    echo '<input type="text" name="buffer_channels" value="' . esc_attr( $val_string ) . '" class="regular-text" />';
    echo '<p class="description">' . esc_html__( 'Example:', 'publish-with-buffer' ) . ' <code>id_1, id_2</code></p>';
}

function buffer_plugin_template_cb() {
    $val = get_option( 'buffer_template', '{title} - {url}' );
    echo '<textarea name="buffer_template" rows="3" class="large-text">' . esc_textarea( $val ) . '</textarea>';
    echo '<p class="description"><strong>' . esc_html__( 'Available variables:', 'publish-with-buffer' ) . '</strong><br>';
    echo '<code>{title}</code> : ' . esc_html__( 'Post title.', 'publish-with-buffer' ) . '<br>';
    echo '<code>{url}</code> : ' . esc_html__( 'Direct link to the post.', 'publish-with-buffer' ) . '<br>';
    echo '<code>{excerpt}</code> : ' . esc_html__( 'Post excerpt.', 'publish-with-buffer' ) . '<br>';
    echo '<code>{author}</code> : ' . esc_html__( 'Author public display name.', 'publish-with-buffer' ) . '<br>';
    echo '<code>{category}</code> : ' . esc_html__( 'Primary category of the post.', 'publish-with-buffer' ) . '<br>';
    echo '<code>{tags}</code> : ' . esc_html__( 'Post tags (automatically converted to #hashtags).', 'publish-with-buffer' ) . '</p>';
}

function buffer_plugin_mode_cb() {
    $val = get_option( 'buffer_default_mode', '' ); 
    ?>
    <select name="buffer_default_mode">
        <option value="" <?php selected( $val, '' ); ?>><?php esc_html_e( 'None (Disabled on posts)', 'publish-with-buffer' ); ?></option>
        <option value="shareNow" <?php selected( $val, 'shareNow' ); ?>><?php esc_html_e( 'Share Now', 'publish-with-buffer' ); ?></option>
        <option value="addToQueue" <?php selected( $val, 'addToQueue' ); ?>><?php esc_html_e( 'Add to Queue', 'publish-with-buffer' ); ?></option>
    </select>
    <p class="description"><?php esc_html_e( 'If you choose "None", the checkbox in the editor will be unchecked by default.', 'publish-with-buffer' ); ?></p>
    <?php
}

function buffer_plugin_category_cb() {
    $val = get_option( 'buffer_allowed_category', '' );
    $args = [
        'show_option_all'    => __( 'All categories', 'publish-with-buffer' ),
        'name'               => 'buffer_allowed_category',
        'selected'           => $val,
        'value_field'        => 'term_id',
    ];
    wp_dropdown_categories( $args );
}

function buffer_plugin_settings_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Publish with Buffer Settings', 'publish-with-buffer' ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'buffer_plugin_settings_group' );
            do_settings_sections( 'publish-with-buffer' );
            submit_button( __( 'Save Settings', 'publish-with-buffer' ) );
            ?>
        </form>
    </div>
    <?php
}