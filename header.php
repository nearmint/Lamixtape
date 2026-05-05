<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="theme-color" content="#333333">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
    <a class="lmt-skip-link" href="#main"><?php esc_html_e( 'Skip to main content', 'lamixtape' ); ?></a>
    <nav class="navbar" aria-label="<?php esc_attr_e( 'Main navigation', 'lamixtape' ); ?>">
        <div class="container mx-auto px-4 flex flex-wrap items-center gap-3 h-[85px]">
            <!-- LEFT: burger (toggle menu — full-screen mobile, side panel desktop) -->
            <button id="burger-btn" class="bg-transparent border-0 p-0 cursor-pointer" aria-label="<?php esc_attr_e( 'Open menu', 'lamixtape' ); ?>" aria-expanded="false" aria-controls="mobile-menu-overlay">
                <svg aria-hidden="true" focusable="false" width="22" height="16" viewBox="0 0 22 16" xmlns="http://www.w3.org/2000/svg" fill="none">
                    <rect x="0" y="0" width="22" height="1.5" rx="0.75" fill="#ffffff"/>
                    <rect x="8" y="7" width="14" height="1.5" rx="0.75" fill="#ffffff"/>
                    <rect x="3" y="14" width="19" height="1.5" rx="0.75" fill="#ffffff"/>
                </svg>
            </button>
            <!-- LEFT (next to burger): title (Lamixtape logo) -->
            <a class="no--hover" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                <span class="lmt-logo"><?php esc_html_e( 'Lamixtape', 'lamixtape' ); ?></span>
            </a>
            <!-- RIGHT: random mixtape text link (REST endpoint 302 redirect to random permalink). ml-auto pushes it to the far right of the flex row. uppercase for visual consistency with the burger-menu items; hover styling comes from the global a:hover in general.css (border-bottom 2px CurrentColor + padding-bottom 1px). -->
            <a class="ml-auto uppercase" href="<?php echo esc_url( rest_url( 'lamixtape/v1/random-mixtape' ) ); ?>" aria-label="<?php esc_attr_e( 'Random mixtape', 'lamixtape' ); ?>">
                <?php esc_html_e( 'Random mixtape', 'lamixtape' ); ?>
            </a>
        </div>
    </nav>

    <!-- Fullscreen Menu Overlay for mobile navigation -->
    <div id="mobile-menu-overlay" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Mobile navigation', 'lamixtape' ); ?>" aria-hidden="true">
        <div class="container mx-auto px-4 flex items-center justify-end h-[85px]">
            <button id="close-mobile-menu" aria-label="Close menu">
                <svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16">
                    <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/>
                </svg>
            </button>
        </div>
        <div class="container mx-auto px-4 mt-4">
            <ul>
                <li class="menu-fade-in menu-delay-0"><a href="<?php echo esc_url( home_url( '/explore/' ) ); ?>">Search</a></li>
                <li class="menu-fade-in menu-delay-1"><a href="<?php echo esc_url( rest_url( 'lamixtape/v1/random-mixtape' ) ); ?>"><?php esc_html_e( 'Random mixtape', 'lamixtape' ); ?></a></li>
                <li class="menu-fade-in menu-delay-2"><a href="<?php echo esc_url( home_url( '/guests/' ) ); ?>">Guest curators</a></li>
                <li class="menu-fade-in menu-delay-3"><button type="button" class="lmt-link-button" data-lmt-dialog="contactmodal" aria-haspopup="dialog" aria-controls="contactmodal">Contact us</button></li>
                <li class="menu-fade-in menu-delay-4"><button type="button" class="lmt-link-button" data-lmt-dialog="donatemodal" data-tracking-source="mobile_menu" aria-haspopup="dialog" aria-controls="donatemodal">Support us</button> ⚡️</li>
                <li class="menu-fade-in menu-delay-5"><a href="<?php echo esc_url( home_url( '/colophon/' ) ); ?>">Colophon</a></li>
                <li class="menu-fade-in menu-delay-5"><a href="<?php echo esc_url( home_url( '/legal-notice/' ) ); ?>">Legal Notice</a></li>
            </ul>
        </div>
    </div>
    <main id="main" tabindex="-1">
