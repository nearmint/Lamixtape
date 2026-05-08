( function () {
    'use strict';

    // Module-level observer reference: lifted out of the per-init
    // closure so a previous-page IntersectionObserver can be
    // disconnect()ed before a new one is created on the next
    // lmt:pjax:swapped event. Without this, multiple PJAX cycles
    // would stack observers (one per swap) and each scroll past
    // the sentinel would fire N duplicate fetches.
    var observer = null;

    var SKELETON_COUNT = 6;
    var ROOT_MARGIN_PX = 400;

    function initInfiniteScroll( $ ) {
        // Q2: disconnect any previous observer before re-init.
        if ( observer ) {
            observer.disconnect();
            observer = null;
        }

        var $sentinel = $( '#lmt-infinite-sentinel' );
        if ( ! $sentinel.length ) {
            return;
        }

        var $container = $( '#lmt-mixtapes-container' );
        if ( ! $container.length ) {
            return;
        }

        // Q3: fresh state from the (PHP-rendered) sentinel data-attrs.
        // PJAX renders a new <main> on each nav, so initial-offset
        // already reflects the page-1 rendering — no client-side
        // bookkeeping to reset across navigations.
        var context = $sentinel.data( 'context' );
        var category = $sentinel.data( 'category' ) || 0;
        var exclude = $sentinel.data( 'exclude' ) || 0;
        var offset = parseInt( $sentinel.data( 'initial-offset' ), 10 ) || 30;
        var loading = false;
        var hasMore = true;

        var renderSkeletons = function () {
            var html = '';
            for ( var i = 0; i < SKELETON_COUNT; i++ ) {
                html += '<article class="lmt-card-skeleton" aria-hidden="true"></article>';
            }
            $container.append( html );
        };

        var clearSkeletons = function () {
            $container.find( '.lmt-card-skeleton' ).remove();
        };

        var stopObserving = function () {
            if ( observer ) {
                observer.unobserve( $sentinel.get( 0 ) );
            }
        };

        var loadMore = function () {
            if ( loading || ! hasMore ) {
                return;
            }
            loading = true;

            renderSkeletons();

            var params = new URLSearchParams();
            params.set( 'context', context );
            params.set( 'offset', offset );
            if ( category ) {
                params.set( 'category', category );
            }
            if ( exclude ) {
                params.set( 'exclude', exclude );
            }

            var url = lmtData.site_url + '/wp-json/lamixtape/v1/posts?' + params.toString();

            fetch( url, {
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': lmtData.nonce },
            } ).then( function ( response ) {
                if ( ! response.ok ) {
                    console.error( '[infinite-scroll] HTTP', response.status );
                    hasMore = false;
                    clearSkeletons();
                    stopObserving();
                    return null;
                }
                return response.json();
            } ).then( function ( data ) {
                if ( ! data ) {
                    return;
                }
                clearSkeletons();
                if ( data.html ) {
                    $container.append( data.html );
                }
                offset = parseInt( data.next_offset, 10 ) || offset;
                hasMore = !! data.has_more;
                if ( ! hasMore ) {
                    stopObserving();
                }
            } ).catch( function ( err ) {
                console.error( '[infinite-scroll] failed:', err );
                hasMore = false;
                clearSkeletons();
                stopObserving();
            } ).finally( function () {
                loading = false;
            } );
        };

        if ( ! ( 'IntersectionObserver' in window ) ) {
            // Very old browsers — keep the page usable, just don't infinite-scroll.
            return;
        }

        observer = new IntersectionObserver( function ( entries ) {
            if ( entries[ 0 ].isIntersecting ) {
                loadMore();
            }
        }, { rootMargin: ROOT_MARGIN_PX + 'px' } );

        observer.observe( $sentinel.get( 0 ) );
    }

    jQuery( function ( $ ) {
        initInfiniteScroll( $ );
    } );

    // PJAX phase 3.4 — re-init after each <main> swap. Function
    // early-returns when no #lmt-infinite-sentinel is present, so
    // it is safe to call unconditionally on every navigation
    // (including swaps to non-listing pages like /explore, /404).
    document.addEventListener( 'lmt:pjax:swapped', function () {
        initInfiniteScroll( jQuery );
    } );
}() );
