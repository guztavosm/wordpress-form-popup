<?php
/*
Plugin Name: Wordpress form popup
Description: Wordpress email popup with modifiable content using Carbon Fields
Version: 1.0
Author: Guztavosm
Author URI: https://guztavosm.com
License: GPLv2 or later
*/

// Import composer dependencies
require_once('vendor/autoload.php');

if (!defined('ABSPATH')) {
    die('Kangaroos cannot jump here');
}

// Admin fields
function RPP_boot_carbon_fields()
{
    \Carbon_Fields\Carbon_Fields::boot();
}
add_action('after_setup_theme', 'RPP_boot_carbon_fields');

use Carbon_Fields\Container;
use Carbon_Fields\Field;

function RPP_add_plugin_settings_page()
{
    // Settings page definition
    Container::make('theme_options', __('Rutinas Poderosas'))
        ->set_icon('dashicons-pdf')

        ->add_fields(array(
            Field::make('association', 'rpp_trigger_page', __('Paginas en las que se desplegara el popup'))->set_types(array(
                array(
                    'type'      => 'post',
                    'post_type' => 'page',
                )
            )),
            Field::make('rich_text', 'rpp_popup_content', __('Cuerpo del Popup')),
            Field::make('text', 'rpp_email_receiver', __('Correo electronico de destino de los resultados'))->set_default_value(get_option('admin_email')),
            Field::make('text', 'rpp_email_subject', __('Asunto del email')),
            Field::make('image', 'rpp_email_image', __('Imagen de encabezado del E-mail'))->set_value_type('url'),
            Field::make('rich_text', 'rpp_email_body', __('Cuerpo del E-mail')),
            Field::make('complex', 'rpp_email_attachments', __('Archivos adjuntos al email'))
                ->add_fields(array(
                    Field::make('text', 'title', __('Titulo del archivo')),
                    Field::make('file', 'attachment', __('Archivo')),
                    Field::make('checkbox', 'active', __('Activo?'))
                        ->set_option_value('yes')
                ))
        ));
}
add_action('carbon_fields_register_fields', 'RPP_add_plugin_settings_page');

define('RPP_PLUGIN', plugin_dir_url(__FILE__));

// Import popup plugin
function rutinas_popup_load_scripts()
{
    $selected_pages = array_column(carbon_get_theme_option('rpp_trigger_page'), "id");
    if (!empty($selected_pages) && is_page($selected_pages)) {
        // Fancybox
        wp_enqueue_script(
            'jquery_fancybox',
            "https://cdn.jsdelivr.net/gh/fancyapps/fancybox@3.5.7/dist/jquery.fancybox.min.js",
            array('jquery'),
            "3.5.7"
        );
        wp_enqueue_style(
            'jquery_fancybox',
            "https://cdn.jsdelivr.net/gh/fancyapps/fancybox@3.5.7/dist/jquery.fancybox.min.css",
            false,
            "3.5.7"
        );

        // Flatpickr
        wp_enqueue_script(
            'flatpickr',
            "https://cdn.jsdelivr.net/npm/flatpickr@4.6.6/dist/flatpickr.min.js",
            false,
            "4.6.6"
        );
        wp_enqueue_style(
            'flatpickr',
            "https://cdn.jsdelivr.net/npm/flatpickr@4.6.6/dist/flatpickr.min.css",
            false,
            "4.6.6"
        );

        // IntTelInput
        wp_enqueue_script(
            'intlTelInput',
            'https://cdn.jsdelivr.net/npm/intl-tel-input@17.0.8/build/js/intlTelInput.js',
            false,
            "17.0.8"
        );
        wp_enqueue_script(
            'intlTelInput-utils',
            'https://cdn.jsdelivr.net/npm/intl-tel-input@17.0.8/build/js/utils.js',
            array('intlTelInput'),
            "17.0.8"
        );
        wp_enqueue_style(
            'intlTelInput',
            'https://cdn.jsdelivr.net/npm/intl-tel-input@17.0.8/build/css/intlTelInput.css',
            false,
            "17.0.8"
        );

        // Plugin js and css
        wp_enqueue_script(
            'rutinas_popup',
            RPP_PLUGIN . 'js/app.js',
            array('jquery', 'jquery_fancybox', 'flatpickr', 'intlTelInput'),
            "1.0.0"
        );
        wp_enqueue_style(
            'rutinas_popup',
            RPP_PLUGIN . "css/style.css",
            false,
            "1.0.0"
        );

        wp_localize_script(
            'rutinas_popup',
            'RPP_variables',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
            )
        );
    }
}
add_action('wp_enqueue_scripts', 'rutinas_popup_load_scripts');

function rutinas_popup_form()
{
    $form_html = file_get_contents(__DIR__ . '/includes/form.html');
    // Wordpress substitution
    $nonce = wp_nonce_field('rutinas_popup', 'nonce', true, false);
    $popup_body = carbon_get_theme_option('rpp_popup_content');
    $form_html = str_replace("{{nonce}}", $nonce, $form_html);
    $form_html = str_replace("{{content}}", $popup_body, $form_html);

    echo $form_html;
}
add_action('wp_footer', 'rutinas_popup_form');

// add the action that enables the logged users to do a job request
add_action('wp_ajax_RPP_register', 'RPP_register');
add_action('wp_ajax_nopriv_RPP_register', 'RPP_register');
// Function used to apply to a job
function RPP_register()
{
    // Validation to avoid external request
    if (!wp_verify_nonce($_REQUEST['nonce'], "rutinas_popup")) {
        header("HTTP/1.1 400 Bad Request");
        exit("No naughty business please");
    }

    $correo = trim(filter_input(INPUT_POST, "correo",  FILTER_SANITIZE_STRING));
    $fecha_nacimiento = trim(filter_input(INPUT_POST, "fecha_nacimiento",  FILTER_SANITIZE_STRING));
    $nombre = trim(filter_input(INPUT_POST, "nombre",  FILTER_SANITIZE_STRING));
    $telefono = trim(filter_input(INPUT_POST, "telefono",  FILTER_SANITIZE_STRING));


    // Define email variables
    $to = $correo;
    $subject = carbon_get_theme_option('rpp_email_subject');
    $body = "<img src='" . carbon_get_theme_option('rpp_email_image') . "' style='width:100%' width='100'/>";
    $body .= wpautop(carbon_get_theme_option('rpp_email_body'));

    $rpp_attachments = carbon_get_theme_option('rpp_email_attachments');
    $attachments = array();
    foreach ($rpp_attachments as $att) {
        if ($att['active']) {
            $attachments[] = get_attached_file($att["attachment"]);
        }
    }

    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($to, $subject, $body, $headers, $attachments);

    // Email to admin with user Info
    $to = carbon_get_theme_option('rpp_email_receiver');
    $subject = "$subject - Informacion de usuario";
    $body = "
    <p><strong>Correo Electronico:</strong> $correo</p>
    <p><strong>Nombre Completo:</strong> $nombre</p>
    <p><strong>Fecha de Nacimiento:</strong> $fecha_nacimiento</p>
    <p><strong>Telefono:</strong> $telefono</p>
    ";
    wp_mail($to, $subject, $body, $headers);

    echo "Email Sent";
    wp_die();
}
