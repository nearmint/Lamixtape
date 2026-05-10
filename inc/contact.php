<?php
/**
 * Contact form REST endpoint + render helper.
 *
 * Replaces Contact Form 7 (Item 1, Phase 9). Exposes :
 *   POST /wp-json/lamixtape/v1/contact
 *     Forwards a message to the site owner via the Web3Forms API
 *     (wp_mail() native is silently blocked on OVH — Phase 9.7).
 *     Permission : wp_rest nonce + rate-limit 5/hour/IP (transients).
 *     Antispam   : honeypot field (silent 422 on fill).
 *     Validation : name 0-100, email FILTER_VALIDATE_EMAIL,
 *                  message 10-5000 chars.
 *     Transport  : LMT_WEB3FORMS_KEY constant (wp-config.php).
 *     Recipient  : LMT_CONTACT_EMAIL constant (wp-config.php),
 *                  passed to Web3Forms as 'email_to' override.
 *
 * Loaded from functions.php via require_once.
 *
 * @package Lamixtape
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the contact REST route.
 */
add_action(
    'rest_api_init',
    function () {
        register_rest_route(
            'lamixtape/v1',
            '/contact',
            array(
                'methods'             => 'POST',
                'callback'            => 'lmt_contact_submit',
                'permission_callback' => 'lmt_contact_permission',
            )
        );
    }
);

/**
 * Permission callback : verify nonce + apply IP rate-limit.
 *
 * Mirrors the pattern of lmt_rest_pagination_permission (Phase 3) :
 * X-WP-Nonce header verification (CSRF) + 5 submits per hashed IP
 * per hour (RGPD-compliant via wp_hash). Counter is incremented in
 * lmt_contact_submit() AFTER a successful send so failed attempts
 * don't deplete the bucket — but a hard 5+ check still applies.
 *
 * @param  WP_REST_Request $request The current REST request.
 * @return true|WP_Error
 */
function lmt_contact_permission( WP_REST_Request $request ) {
    $nonce = $request->get_header( 'X-WP-Nonce' );
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        return new WP_Error(
            'lmt_invalid_nonce',
            __( 'Invalid or missing nonce.', 'lamixtape' ),
            array( 'status' => 403 )
        );
    }

    $ip_raw = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
    $ip     = filter_var( $ip_raw, FILTER_VALIDATE_IP );
    if ( ! $ip ) {
        return new WP_Error(
            'lmt_invalid_ip',
            __( 'Could not resolve client IP.', 'lamixtape' ),
            array( 'status' => 400 )
        );
    }

    $bucket_key = 'lmt_contact_rl_' . wp_hash( $ip );
    $count      = (int) get_transient( $bucket_key );
    if ( $count >= 5 ) {
        return new WP_Error(
            'lmt_rate_limited',
            __( 'Too many messages. Please try again in an hour.', 'lamixtape' ),
            array( 'status' => 429 )
        );
    }

    return true;
}

/**
 * Submit handler : sanitize, validate, send via wp_mail().
 *
 * @param  WP_REST_Request $request The current REST request.
 * @return WP_REST_Response|WP_Error
 */
function lmt_contact_submit( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    if ( ! is_array( $params ) ) {
        $params = array();
    }

    // Honeypot — silent reject 422 if filled. We do not expose any
    // diagnostic so a bot cannot fingerprint the antispam logic.
    $hp = isset( $params['hp'] ) ? trim( (string) $params['hp'] ) : '';
    if ( '' !== $hp ) {
        return new WP_Error(
            'lmt_invalid_request',
            __( 'Invalid request.', 'lamixtape' ),
            array( 'status' => 422 )
        );
    }

    // Name (optional, 0-100).
    $name = isset( $params['name'] ) ? sanitize_text_field( wp_unslash( $params['name'] ) ) : '';
    if ( strlen( $name ) > 100 ) {
        return new WP_Error(
            'lmt_invalid_name',
            __( 'Name is too long (maximum 100 characters).', 'lamixtape' ),
            array( 'status' => 400 )
        );
    }

    // Email (required, valid format).
    $email = isset( $params['email'] ) ? sanitize_email( wp_unslash( $params['email'] ) ) : '';
    if ( ! $email || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
        return new WP_Error(
            'lmt_invalid_email',
            __( 'Please provide a valid email address.', 'lamixtape' ),
            array( 'status' => 400 )
        );
    }

    // Message (required, 10-5000).
    $message = isset( $params['message'] ) ? sanitize_textarea_field( wp_unslash( $params['message'] ) ) : '';
    $msg_len = strlen( $message );
    if ( $msg_len < 10 ) {
        return new WP_Error(
            'lmt_invalid_message',
            __( 'Message is too short (minimum 10 characters).', 'lamixtape' ),
            array( 'status' => 400 )
        );
    }
    if ( $msg_len > 5000 ) {
        return new WP_Error(
            'lmt_invalid_message',
            __( 'Message is too long (maximum 5000 characters).', 'lamixtape' ),
            array( 'status' => 400 )
        );
    }

    // Build email payload.
    $ip_raw = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
    $ip     = filter_var( $ip_raw, FILTER_VALIDATE_IP );
    if ( ! $ip ) {
        $ip = 'unknown';
    }

    $name_for_subject = '' !== $name ? $name : __( 'Anonymous', 'lamixtape' );
    /* translators: %s = sender name (or "Anonymous"). */
    $subject = sprintf( __( '[Lamixtape] New message from %s', 'lamixtape' ), $name_for_subject );

    $body  = 'Name: ' . ( '' !== $name ? $name : 'Anonymous' ) . "\n";
    $body .= 'Email: ' . $email . "\n\n";
    $body .= $message . "\n\n";
    $body .= "---\n";
    $body .= 'IP: ' . $ip . "\n";
    $body .= 'Date: ' . gmdate( 'Y-m-d H:i:s' ) . " UTC\n";

    // Resolve recipient — never hardcode an email address in this
    // file because the theme repo is public on GitHub. The canonical
    // source of truth is the LMT_CONTACT_EMAIL constant defined in
    // wp-config.php (not committed). Fallback to the WordPress
    // admin_email option if the constant is missing or not a valid
    // email, so a fresh checkout still has a sane default.
    $to = ( defined( 'LMT_CONTACT_EMAIL' ) && is_email( LMT_CONTACT_EMAIL ) )
        ? LMT_CONTACT_EMAIL
        : get_option( 'admin_email' );
    if ( ! $to || ! is_email( $to ) ) {
        return new WP_Error(
            'lmt_misconfigured',
            __( 'Contact form is misconfigured (no destination email).', 'lamixtape' ),
            array( 'status' => 500 )
        );
    }

    // Phase 9.7 — wp_mail() native is silently blocked on OVH
    // (CF7 didn't work either, confirmed by user). We forward the
    // payload to the Web3Forms API instead, which delivers the
    // mail using their infrastructure. Access key stored in the
    // LMT_WEB3FORMS_KEY constant (wp-config.php, not committed).
    if ( ! defined( 'LMT_WEB3FORMS_KEY' ) || ! LMT_WEB3FORMS_KEY ) {
        return new WP_Error(
            'lmt_misconfigured',
            __( 'Contact form is misconfigured (no mail transport).', 'lamixtape' ),
            array( 'status' => 500 )
        );
    }

    $payload = array(
        'access_key' => LMT_WEB3FORMS_KEY,
        'subject'    => $subject,
        'email'      => $email,
        'name'       => $name_for_subject,
        'from_name'  => 'Lamixtape Contact',
        'reply_to'   => $email,
        'email_to'   => $to,
        'message'    => $body,
    );

    $response = wp_remote_post(
        'https://api.web3forms.com/submit',
        array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
        )
    );

    if ( is_wp_error( $response ) ) {
        return new WP_Error(
            'lmt_mail_transport_failed',
            __( 'Failed to send the message. Please try again later.', 'lamixtape' ),
            array( 'status' => 500 )
        );
    }

    $response_code = (int) wp_remote_retrieve_response_code( $response );
    $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( 200 !== $response_code || empty( $response_body['success'] ) ) {
        return new WP_Error(
            'lmt_mail_transport_failed',
            __( 'Failed to send the message. Please try again later.', 'lamixtape' ),
            array( 'status' => 500 )
        );
    }

    // Increment rate-limit counter on success.
    $bucket_key = 'lmt_contact_rl_' . wp_hash( $ip );
    $count      = (int) get_transient( $bucket_key );
    set_transient( $bucket_key, $count + 1, HOUR_IN_SECONDS );

    return rest_ensure_response(
        array(
            'success' => true,
            'message' => __( 'Your message has been sent. Thank you!', 'lamixtape' ),
        )
    );
}

/**
 * Render the contact form HTML.
 *
 * Called from footer.php inside the #contactmodal <dialog>. Emits
 * the form + a hidden success state element that JS swaps on a
 * 200 response. Honeypot field "hp" is hidden via CSS (.lmt-contact-hp
 * rules in css/contact.css) and tabindex=-1 + aria-hidden so it is
 * skipped by humans + AT users but reachable to scrapers.
 *
 * @return void
 */
function lmt_render_contact_form() {
    $nonce = wp_create_nonce( 'wp_rest' );
    ?>
    <form id="lmt-contact-form" class="lmt-contact-form" novalidate data-nonce="<?php echo esc_attr( $nonce ); ?>">
        <div class="lmt-contact-field">
            <label for="lmt-contact-name" class="sr-only"><?php esc_html_e( 'Name (optional)', 'lamixtape' ); ?></label>
            <input type="text" id="lmt-contact-name" name="name" maxlength="100" autocomplete="name" placeholder="<?php esc_attr_e( 'Name', 'lamixtape' ); ?>" />
        </div>
        <div class="lmt-contact-field">
            <label for="lmt-contact-email" class="sr-only"><?php esc_html_e( 'Email (required)', 'lamixtape' ); ?></label>
            <input type="email" id="lmt-contact-email" name="email" required autocomplete="email" placeholder="<?php esc_attr_e( 'Email', 'lamixtape' ); ?>" />
        </div>
        <div class="lmt-contact-field">
            <label for="lmt-contact-message" class="sr-only"><?php esc_html_e( 'Message (required)', 'lamixtape' ); ?></label>
            <textarea id="lmt-contact-message" name="message" rows="5" minlength="10" maxlength="5000" required placeholder="<?php esc_attr_e( 'Message', 'lamixtape' ); ?>"></textarea>
        </div>
        <div class="lmt-contact-hp" aria-hidden="true">
            <label for="lmt-contact-hp">URL</label>
            <input type="text" id="lmt-contact-hp" name="hp" tabindex="-1" autocomplete="off" />
        </div>
        <div class="lmt-contact-actions">
            <button type="submit" class="lmt-contact-submit"><?php esc_html_e( 'Send', 'lamixtape' ); ?></button>
        </div>
        <div class="lmt-contact-feedback" role="status" aria-live="polite"></div>
    </form>
    <div class="lmt-contact-success-state" hidden role="status" aria-live="polite">
        <p class="lmt-contact-success-message"></p>
        <button type="button" class="lmt-contact-reset"><?php esc_html_e( 'Send another message', 'lamixtape' ); ?></button>
    </div>
    <?php
}
