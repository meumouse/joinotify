<?php

namespace MeuMouse\Joinotify\Validations;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Media types validation for WhatsApp messages
 * 
 * @since 1.0.0
 * @package MeuMouse.com
 */
class Media_Types {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @return void
     */
    public function __construct() {
        add_filter( 'upload_mimes', array( __CLASS__, 'allow_custom_mime_types' ) );
    }


    /**
     * Get allowed media types
     * 
     * @since 1.0.0
     * @return array
     */
    public static function get_media_types() {
        return apply_filters( 'Joinotify/Validations/Get_Media_Types', array(
            'image' => __( 'Imagem', 'joinotify' ),
            'video' => __( 'Vídeo', 'joinotify' ),
            'document' => __( 'Documento', 'joinotify' ),
            'audio' => __( 'Áudio', 'joinotify' ),
        ));
    }


    /**
     * Get mime types for a specific media type
     * 
     * @since 1.0.0
     * @param string|null $media_type | The media type ('image', 'video', 'document', 'audio')
     * @return array
     */
    public static function get_mime_types( $media_type = null ) {
        $mime_types = apply_filters( 'Joinotify/Validations/Get_Mime_Types', array(
            'image' => array(
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/bmp',
                'image/webp',
            ),
            'video' => array(
                'video/mp4',
                'video/3gpp',
                'video/avi',
                'video/mpeg',
            ),
            'document' => array(
                'application/pdf',
                'application/msword', // .doc
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
                'application/vnd.ms-excel', // .xls
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
                'application/vnd.ms-powerpoint', // .ppt
                'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx
                'text/plain',
            ),
            'audio' => array(
                'audio/mpeg',
                'audio/ogg',
                'audio/wav',
                'audio/amr',
                'audio/mp3',
            ),
        ));

        if ( $media_type === null ) {
            $all_mime_types = array();

            foreach ( $mime_types as $types ) {
                $all_mime_types = array_merge( $all_mime_types, $types );
            }
            return $all_mime_types;
        } elseif ( array_key_exists( $media_type, $mime_types ) ) {
            return $mime_types[ $media_type ];
        } else {
            return array();
        }
    }


    /**
     * Allow new mime types
     * 
     * @since 1.0.0
     * @param array $existing_mimes | Existing MIME types
     * @return array
     */
    public static function allow_custom_mime_types( $existing_mimes ) {
        $custom_mime_types = self::get_mime_types();
        
        // Adds custom MIME types to existing MIME types
        return array_merge( $existing_mimes, $custom_mime_types );
    }
}