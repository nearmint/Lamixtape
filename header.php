<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="mask-icon" href="/safari-pinned-tab.svg" color="#333333">
    <meta name="msapplication-TileColor" content="#333333">
    <meta name="theme-color" content="#ffffff">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
    <nav class="navbar">
        <div class="tw:container tw:mx-auto tw:px-4 tw:flex tw:flex-wrap tw:items-center tw:justify-between">
            <a class="no--hover tw:pt-1" href="<?php echo esc_url( get_bloginfo( 'wpurl' ) ); ?>">
                <h1><?php esc_html_e( 'Lamixtape', 'lamixtape' ); ?></h1>
            </a>
            <span class="tw:text-right">
                <ul class="tw:flex tw:gap-x-2 tw:uppercase tw:mb-0">
                    <li class="burger-menu tw:block">
                        <button id="burger-btn" class="tw:bg-transparent tw:border-0 tw:p-0 tw:outline-none" aria-label="Open menu">
                            <svg width="22" height="16" viewBox="0 0 22 16" xmlns="http://www.w3.org/2000/svg" fill="none">
                                <rect x="0" y="0" width="22" height="1.5" rx="0.75" fill="#ffffff"/>
                                <rect x="8" y="7" width="14" height="1.5" rx="0.75" fill="#ffffff"/>
                                <rect x="3" y="14" width="19" height="1.5" rx="0.75" fill="#ffffff"/>
                            </svg>
                        </button>
                    </li>
                </ul>
            </span>
        </div>
    </nav>

    <!-- Fullscreen Menu Overlay for mobile navigation -->
    <div id="mobile-menu-overlay">
        <div class="tw:container tw:mx-auto tw:px-4">
            <button id="close-mobile-menu" aria-label="Close menu">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16">
                    <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/>
                </svg>
            </button>
            <ul>
                <li class="menu-fade-in menu-delay-0"><a href="<?php echo esc_url( home_url( '/explore/' ) ); ?>">Search</a></li>
                <li class="menu-fade-in menu-delay-1">
                    <?php
                    $random = lmt_get_random_mixtape( 'header_mobile_menu' );
                    if ( $random ) :
                        echo '<a href="' . esc_url( get_permalink( $random ) ) . '">';
                        esc_html_e( 'Random mixtape', 'lamixtape' );
                        echo '</a>';
                    endif;
                    ?>
                </li>
                <li class="menu-fade-in menu-delay-2"><a href="<?php echo esc_url( home_url( '/guests/' ) ); ?>">Guest curators</a></li>
                <li class="menu-fade-in menu-delay-3"><a href="#" data-lmt-dialog="contactmodal">Contact us</a></li>
                <li class="menu-fade-in menu-delay-4"><a href="#" data-lmt-dialog="donatemodal">Support us</a> ⚡️</li>
                <li class="menu-fade-in menu-delay-5"><a href="colophon">Colophon</a></li>
                <li class="menu-fade-in menu-delay-5"><a href="legal-notice">Legal Notice</a></li>
            </ul>
        </div>
    </div>
