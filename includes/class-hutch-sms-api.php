<?php
/**
 * Hutch SMS API Handler
 * Handles Login, Token Renew, Send SMS, and Bulk SMS endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Hutch_SMS_API {

    const BASE_URL      = 'https://bsms.hutch.lk/api';
    const API_VERSION   = 'v1';

    /**
     * Authenticate and retrieve access + refresh tokens.
     * Tokens are stored in wp_options.
     *
     * @return array|WP_Error
     */
    public static function login() {
        $username = get_option( 'hutch_sms_username', '' );
        $password = get_option( 'hutch_sms_password', '' );

        if ( empty( $username ) || empty( $password ) ) {
            return new WP_Error( 'missing_credentials', 'Username or password not configured.' );
        }

        $response = wp_remote_post( self::BASE_URL . '/login', array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Accept'        => '*/*',
                'X-API-VERSION' => self::API_VERSION,
            ),
            'body'    => wp_json_encode( array(
                'username' => $username,
                'password' => $password,
            ) ),
            'timeout' => 30,
        ) );

        return self::parse_response( $response, 'login' );
    }

    /**
     * Renew the access token using the stored refresh token.
     *
     * @return array|WP_Error
     */
    public static function renew_token() {
        $refresh_token = get_option( 'hutch_sms_refresh_token', '' );

        if ( empty( $refresh_token ) ) {
            return self::login();
        }

        $response = wp_remote_get( self::BASE_URL . '/token/accessToken', array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Accept'        => '*/*',
                'X-API-VERSION' => self::API_VERSION,
                'Authorization' => 'Bearer ' . $refresh_token,
            ),
            'timeout' => 30,
        ) );

        $result = self::parse_response( $response, 'renew_token' );

        if ( is_wp_error( $result ) ) {
            // Fall back to full login
            return self::login();
        }

        return $result;
    }

    /**
     * Get a valid access token, refreshing/logging in as needed.
     *
     * @return string|WP_Error
     */
    public static function get_access_token() {
        $access_token  = get_option( 'hutch_sms_access_token', '' );
        $token_expiry  = (int) get_option( 'hutch_sms_token_expiry', 0 );

        // Token still valid (with 60s buffer)
        if ( ! empty( $access_token ) && time() < ( $token_expiry - 60 ) ) {
            return $access_token;
        }

        // Try to renew
        $result = self::renew_token();

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( isset( $result['accessToken'] ) ) {
            // JWT expiry: decode payload to get exp
            $expiry = self::decode_jwt_expiry( $result['accessToken'] );
            update_option( 'hutch_sms_access_token', $result['accessToken'] );
            update_option( 'hutch_sms_token_expiry', $expiry );
        }

        if ( isset( $result['refreshToken'] ) ) {
            update_option( 'hutch_sms_refresh_token', $result['refreshToken'] );
        }

        return get_option( 'hutch_sms_access_token', '' );
    }

    /**
     * Send a single SMS.
     *
     * @param array $params {
     *   campaignName, mask, numbers (comma-separated), content,
     *   messageClass (optional), deliveryReportRequest (optional bool)
     * }
     * @return array|WP_Error
     */
    public static function send_sms( array $params ) {
        $token = self::get_access_token();
        if ( is_wp_error( $token ) ) return $token;

        $body = array(
            'campaignName' => sanitize_text_field( $params['campaignName'] ?? 'WP Campaign' ),
            'mask'         => sanitize_text_field( $params['mask'] ?? get_option( 'hutch_sms_mask', '' ) ),
            'numbers'      => sanitize_text_field( $params['numbers'] ),
            'content'      => wp_kses_post( $params['content'] ),
        );

        if ( ! empty( $params['messageClass'] ) ) {
            $body['messageClass'] = $params['messageClass'];
        }
        if ( ! empty( $params['deliveryReportRequest'] ) ) {
            $body['deliveryReportRequest'] = (bool) $params['deliveryReportRequest'];
        }

        $response = wp_remote_post( self::BASE_URL . '/sendsms', array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Accept'        => '*/*',
                'X-API-VERSION' => self::API_VERSION,
                'Authorization' => 'Bearer ' . $token,
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ) );

        $result = self::parse_response( $response, 'send_sms', $body );

        // Log result
        Hutch_SMS_Logger::log( array(
            'type'     => 'individual',
            'numbers'  => $body['numbers'],
            'campaign' => $body['campaignName'],
            'message'  => $body['content'],
            'status'   => is_wp_error( $result ) ? 'error' : 'success',
            'response' => is_wp_error( $result ) ? $result->get_error_message() : wp_json_encode( $result ),
        ) );

        return $result;
    }

    /**
     * Send bulk SMS (up to 50 per request per API docs).
     *
     * @param array $messages Array of {campaignName, mask, numbers, content}
     * @return array|WP_Error
     */
    public static function send_bulk_sms( array $messages ) {
        $token = self::get_access_token();
        if ( is_wp_error( $token ) ) return $token;

        // Chunk to 50 per request
        $chunks  = array_chunk( $messages, 50 );
        $results = array();

        foreach ( $chunks as $chunk ) {
            $payload = array();
            foreach ( $chunk as $msg ) {
                $payload[] = array(
                    'campaignName' => sanitize_text_field( $msg['campaignName'] ?? 'Bulk Campaign' ),
                    'mask'         => sanitize_text_field( $msg['mask'] ?? get_option( 'hutch_sms_mask', '' ) ),
                    'numbers'      => sanitize_text_field( $msg['numbers'] ),
                    'content'      => wp_kses_post( $msg['content'] ),
                );
            }

            $response = wp_remote_post( self::BASE_URL . '/sendsms/bulk', array(
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Accept'        => '*/*',
                    'X-API-VERSION' => self::API_VERSION,
                    'Authorization' => 'Bearer ' . $token,
                ),
                'body'    => wp_json_encode( $payload ),
                'timeout' => 60,
            ) );

            $result = self::parse_response( $response, 'send_bulk_sms', $payload );

            Hutch_SMS_Logger::log( array(
                'type'     => 'bulk',
                'numbers'  => implode( ', ', array_column( $payload, 'numbers' ) ),
                'campaign' => $payload[0]['campaignName'] ?? 'Bulk',
                'message'  => count( $payload ) . ' messages in batch',
                'status'   => is_wp_error( $result ) ? 'error' : 'success',
                'response' => is_wp_error( $result ) ? $result->get_error_message() : wp_json_encode( $result ),
            ) );

            $results[] = $result;
        }

        return $results;
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    private static function parse_response( $response, string $context = '', $request_body = null ) {
        if ( is_wp_error( $response ) ) {
            Hutch_SMS_Logger::debug( "[$context] WP_Error: " . $response->get_error_message() );
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        Hutch_SMS_Logger::debug( "[$context] HTTP $code | Body: $body" );

        if ( $code === 401 ) {
            return new WP_Error( 'unauthorized', 'Authentication failed (401). Please check credentials.' );
        }

        if ( $code < 200 || $code >= 300 ) {
            $msg = $data['message'] ?? $data['error'] ?? "HTTP $code";
            return new WP_Error( 'api_error', $msg );
        }

        return $data ?? array();
    }

    /**
     * Decode JWT expiry claim from token payload.
     */
    private static function decode_jwt_expiry( string $token ): int {
        $parts = explode( '.', $token );
        if ( count( $parts ) < 2 ) return time() + 600; // 10 min fallback
        $payload = json_decode( base64_decode( str_pad( strtr( $parts[1], '-_', '+/' ), strlen( $parts[1] ) % 4, '=', STR_PAD_RIGHT ) ), true );
        return (int) ( $payload['exp'] ?? time() + 600 );
    }
}
