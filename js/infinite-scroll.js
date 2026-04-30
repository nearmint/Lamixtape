jQuery( function ( $ ) {
    'use strict';

    var $sentinel = $( '#lmt-infinite-sentinel' );
    if ( ! $sentinel.length ) {
        return;
    }

    var $container = $( '#lmt-mixtapes-container' );
    if ( ! $container.length ) {
        return;
    }

    var context = $sentinel.data( 'context' );
    var category = $sentinel.data( 'category' ) || 0;
    var exclude = $sentinel.data( 'exclude' ) || 0;
    var offset = parseInt( $sentinel.data( 'initial-offset' ), 10 ) || 30;
    var loading = false;
    var hasMore = true;
    var observer;

    var SKELETON_COUNT = 6;
    var ROOT_MARGIN_PX = 400;

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
} );
