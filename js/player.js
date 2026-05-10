// --- Diagnostic block ---

// --- Dynamic YouTube Iframe API Loader ---
function loadYouTubeIframeAPI(callback) {
  if (window.YT && window.YT.Player) {
    // API already loaded
    callback();
    return;
  }
  // If script is already being loaded, just wait for it
  if (window._ytApiLoading) {
    document.addEventListener('ytApiReady', callback, { once: true });
    return;
  }
  window._ytApiLoading = true;
  // Create script tag
  var tag = document.createElement('script');
  tag.src = "https://www.youtube.com/iframe_api";
  var firstScriptTag = document.getElementsByTagName('script')[0];
  firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
  // Hook into the global callback
  window.onYouTubeIframeAPIReady = function() {
    document.dispatchEvent(new Event('ytApiReady'));
    callback();
  };
}

// --- Hybrid Player Logic (YouTube iframe API for YouTube, MediaElement.js for MP3/MP4) ---
// Pass `jQuery` explicitly and alias as `$` inside the closure: ensures we bind
// to WP-bundled jQuery (which carries the .mediaelementplayer plugin) and not
// to the global `$`, which may resolve to a different jQuery instance when the
// CDN jquery in header.php is still loaded alongside the WP-bundled one.
jQuery(function ($) {
  var player;
  var youtubePlayer;
  var youtubeReady = false;
  var pendingYouTubeAction = null;
  var timerInterval;
  var currentTrack = 0;
  var playlistItems = $("#playlist li");
  var isSeeking = false;
  var trackUrl = "";
  var trackTitle = "";
  var $seekbar = $("#seekbar");
  var $timeDiv = $("#time");
  var $durationDiv = $("#duration");
  var $playPauseBtn = $("#play-pause");
  var $titleDiv = $("#title");
  var $titleMobile = $("#title-mobile");
  var $playerCountdown = $("#player-countdown");
  var $debugDiv = $("#debug");
  var $playlist = $("#playlist");
  var $audioPlayer = $("#audioPlayer");
  var $youtubePlayer = $("#youtubePlayer");
  var currentType = null;

  // PJAX phase 3.2 — global state exposed for cross-page detection.
  // Tracks the slug of the mixtape currently loaded into the player
  // (= the one whose track is or was last loaded), independent of
  // which page is currently displayed in <main>. URL pathname acts
  // as the canonical slug source via window.lmtGetMixtapeSlug() —
  // window.location.pathname is updated by lmt-pjax history.pushState.
  window.lmtPlayerCurrentSlug = window.lmtPlayerCurrentSlug || null;

  // PJAX phase 3.6 — companion to lmtPlayerCurrentSlug. Tracks the
  // post_id of the mixtape currently loaded in the player, used by
  // js/autoplay.js to query the next-thematic-mixtape REST endpoint
  // with the ID of the mixtape that JUST ENDED (= playing), not the
  // page being viewed when it ended. Without this, autoplay queries
  // would target the wrong mixtape during PJAX cross-page playback.
  window.lmtPlayerCurrentId = window.lmtPlayerCurrentId || null;

  // Phase 8.3 — title + url + color of the mixtape currently loaded
  // in the player, displayed in the omniscient player (#lmt-current-
  // mixtape-link, desktop + mobile) so the user can click back to the
  // playing mixtape's page from anywhere. Click is intercepted by the
  // PJAX delegated handler (Phase 1). Synced at 3 points : initial
  // single load (from lmtData), Q-A1 first PJAX arrival on single,
  // and Q-C1 cross-mixtape track click (both read from current DOM).
  // Color = ACF 'color' field (#RRGGBB), rendered as a 16px dot next
  // to the mixtape name.
  window.lmtPlayerCurrentTitle = window.lmtPlayerCurrentTitle || null;
  window.lmtPlayerCurrentUrl = window.lmtPlayerCurrentUrl || null;
  window.lmtPlayerCurrentColor = window.lmtPlayerCurrentColor || null;

  // Phase Tracking v1 — flag to prevent duplicate play_start events.
  // YouTube `PLAYING` state and MediaElement `playing` event both
  // fire on initial play AND on resume after pause. We only want to
  // track the first play per track. Reset in preparePlayer() when a
  // new track is loaded.
  var playStartTrackedForCurrentTrack = false;

  // Initialize MediaElement.js for MP3/MP4
  $audioPlayer.mediaelementplayer({
    features: [],
    success: function (media, node, instance) {
      player = media;
      player.addEventListener("ended", function () {
        // Phase Tracking v1 — MediaElement `ended` event fires
        // only on natural end (not on skip), so no percentage
        // check needed. Fire play_complete then auto-advance.
        if (typeof window.lmtTrack === 'function') {
          window.lmtTrack('play_complete', {
            mixtape_slug: window.lmtGetMixtapeSlug(),
            track_index: currentTrack,
            track_title: trackTitle
          });
        }
        if (triggerAutoplayIfLastTrack()) { return; }
        playNextSong();
      });
      player.addEventListener("playing", function () {
        $playPauseBtn.text("Pause");
        startTimer();
        // Phase Tracking v1 — fire play_start on first play only.
        if (!playStartTrackedForCurrentTrack && typeof window.lmtTrack === 'function') {
          window.lmtTrack('play_start', {
            mixtape_slug: window.lmtGetMixtapeSlug(),
            track_index: currentTrack,
            track_title: trackTitle,
            source: 'mp3'
          });
          playStartTrackedForCurrentTrack = true;
        }
      });
      player.addEventListener("pause", function () {
        $playPauseBtn.text("Play");
        stopTimer();
      });
      player.addEventListener("error", function () {
        // Phase Recette II L6 — F11 : mark the failed track DOM
        // element greyed-out (parity with YouTube onError handler).
        markTrackUnavailable(currentTrack);
        if (triggerAutoplayIfLastTrack()) { return; }
        playNextSong();
      });
      player.addEventListener("loadedmetadata", function () {
        updateTimeDisplay();
      });
      player.addEventListener("timeupdate", function () {
        updateTimeDisplay();
      });
    }
  });

  // --- YouTube Player Initialization ---
  function initYouTubePlayer() {
    if (window.YT && window.YT.Player && !$youtubePlayer.data('yt-initialized')) {
    youtubePlayer = new YT.Player('youtubePlayer', {
      height: '100%',
      width: '100%',
      videoId: '',
      playerVars: {
        'autoplay': 0,
        'controls': 0,
        'disablekb': 1,
        'enablejsapi': 1,
        'fs': 0,
        'rel': 0,
        'showinfo': 0
      },
      events: {
        'onReady': onPlayerReady,
        'onStateChange': onPlayerStateChange,
        'onError': onPlayerError
      }
    });
      $youtubePlayer.data('yt-initialized', true);
    }
  }

  // Make sure YouTube API is loaded and player is initialized
  loadYouTubeIframeAPI(initYouTubePlayer);

  function onPlayerReady(event) {
    youtubeReady = true;
    // SEC-009: harden the YouTube iframe — limit Referer leak.
    // sandbox is intentionally NOT applied (it breaks postMessage to
    // the YT JS API).
    try {
      var iframeEl = youtubePlayer && youtubePlayer.getIframe ? youtubePlayer.getIframe() : null;
      if (iframeEl && !iframeEl.hasAttribute('referrerpolicy')) {
        iframeEl.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
      }
    } catch (e) { /* defensive: never block the player on a hardening tweak */ }
    if (pendingYouTubeAction) {
      pendingYouTubeAction();
      pendingYouTubeAction = null;
    }
  }

  function onPlayerStateChange(event) {

const playSvg = `
  <svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor"
       class="bi bi-play-fill" viewBox="0 0 16 16" style="margin-bottom: 5px;">
    <path d="m11.596 8.697-6.363 3.692c-.54.313-1.233-.066-1.233-.697V4.308
             c0-.63.692-1.01 1.233-.696l6.363 3.692a.802.802 0 0 1 0 1.393"/>
  </svg>
`;

const pauseSvg = `
  <svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor"
       class="bi bi-pause-fill" viewBox="0 0 16 16" style="margin-bottom: 5px;">
    <path d="M5.5 3.5A1.5 1.5 0 0 1 7 5v6a1.5 1.5 0 0 1-3 0V5
             a1.5 1.5 0 0 1 1.5-1.5m5 0A1.5 1.5 0 0 1 12 5v6
             a1.5 1.5 0 0 1-3 0V5a1.5 1.5 0 0 1 1.5-1.5"/>
  </svg>
`;

if (event.data === YT.PlayerState.ENDED) {
  // Phase Tracking v1 — fire play_complete only if >=95% played
  // (defensive : avoid false positives if YouTube ENDED fires early
  // due to network glitch or rapid skip-to-end manipulation).
  if (typeof window.lmtTrack === 'function') {
    var ytCurrent = (youtubePlayer && typeof youtubePlayer.getCurrentTime === 'function') ? youtubePlayer.getCurrentTime() : 0;
    var ytDuration = (youtubePlayer && typeof youtubePlayer.getDuration === 'function') ? youtubePlayer.getDuration() : 0;
    if (ytDuration > 0 && (ytCurrent / ytDuration) >= 0.95) {
      window.lmtTrack('play_complete', {
        mixtape_slug: window.lmtGetMixtapeSlug(),
        track_index: currentTrack,
        track_title: trackTitle
      });
    }
  }
  if (triggerAutoplayIfLastTrack()) { return; }
  playNextSong();
} else if (event.data === YT.PlayerState.PLAYING) {
  $playPauseBtn.html(pauseSvg);
  startTimer();
  // Phase Tracking v1 — fire play_start on first play only.
  if (!playStartTrackedForCurrentTrack && typeof window.lmtTrack === 'function') {
    window.lmtTrack('play_start', {
      mixtape_slug: window.lmtGetMixtapeSlug(),
      track_index: currentTrack,
      track_title: trackTitle,
      source: 'youtube'
    });
    playStartTrackedForCurrentTrack = true;
  }
} else if (event.data === YT.PlayerState.PAUSED) {
  $playPauseBtn.html(playSvg);
  stopTimer();
}
}


  function onPlayerError(event) {
    // Phase Tracking v1 — fire youtube_embed_error BEFORE auto-skip.
    // YouTube error codes (event.data) :
    //   2   = invalid params (rare, probably bad videoId formatting)
    //   5   = HTML5 player error (player itself crashed)
    //   100 = video not found / private / removed
    //   101 = embed restricted by uploader
    //   150 = embed restricted by uploader (alias of 101)
    // Critical for legacy mixtapes where YouTube videos disappear
    // over time. Output : actionable list per mixtape_slug.
    if (typeof window.lmtTrack === 'function') {
      var youtubeId = trackUrl ? extractYouTubeId(trackUrl) : null;
      window.lmtTrack('youtube_embed_error', {
        mixtape_slug: window.lmtGetMixtapeSlug(),
        track_index: currentTrack,
        youtube_id: youtubeId,
        error_code: event.data
      });
    }
    // Phase Recette II L6 — F11 : visual marking of unavailable
    // tracks. Idempotent if onError fires multiple times for the
    // same track.
    markTrackUnavailable(currentTrack);
    if (triggerAutoplayIfLastTrack()) { return; }
    playNextSong();
  }

  // Phase Recette II L6 — F11 : mark a tracklist <li> as unavailable.
  // Adds opacity / cursor-not-allowed via .lmt-track-unavailable class
  // (css/mixtape-page.css), plus title tooltip + aria-disabled on the
  // inner <a>. Used by both YouTube onPlayerError and MediaElement
  // error listener for cross-source consistency. Session-only marking;
  // a page reload re-fires onError on still-broken tracks and re-marks
  // them, which is acceptable.
  function markTrackUnavailable(trackIndex) {
    var $li = $(playlistItems[trackIndex]);
    if ($li.length) {
      $li.addClass('lmt-track-unavailable');
      // aria-label (not title) so the message is announced by
      // assistive tech without triggering the browser's native
      // ~1s-delay tooltip — the visible tooltip is rendered by
      // .lmt-track-unavailable::after in css/mixtape-page.css for
      // instant hover feedback.
      $li.find('a').attr('aria-label', 'Track unavailable')
                   .attr('aria-disabled', 'true');
    }
  }

  // Pre-validate track URLs at load (and after PJAX swap) to mark
  // legacy invalid URLs as unavailable BEFORE the user can click
  // them. Complements the runtime onError detection above (which
  // catches dead-but-syntactically-valid URLs : private YouTube
  // videos, deleted MP3s, etc.) by also catching URLs that never
  // even reach the network — typically `http://0` from the historic
  // SoundCloud cleanup, plus empty strings, '#', '0', non-HTTP(S)
  // protocols and unparseable URLs.
  function isInvalidTrackUrl(url) {
    if (!url || typeof url !== 'string') return true;
    var trimmed = url.trim();
    if (!trimmed) return true;
    if (trimmed === '#' || trimmed === '0') return true;
    if (/^https?:\/\/0\/?$/i.test(trimmed)) return true;
    try {
      var u = new URL(trimmed);
      return !(u.protocol === 'http:' || u.protocol === 'https:');
    } catch (e) {
      return true;
    }
  }

  function markInvalidTrackUrls() {
    if (!playlistItems || !playlistItems.length) return;
    playlistItems.each(function (i) {
      var url = $(this).find('a').attr('data-src') || '';
      if (isInvalidTrackUrl(url)) {
        markTrackUnavailable(i);
      }
    });
  }

  function formatTime(seconds) {
    if (isNaN(seconds) || seconds === undefined || seconds === null || seconds === Infinity) return '00:00';
    seconds = Math.max(0, seconds);
    var mins = Math.floor(seconds / 60);
    var secs = Math.floor(seconds % 60);
    return (mins < 10 ? "0" : "") + mins + ":" + (secs < 10 ? "0" : "") + secs;
  }

  // Mobile player redesign — marquee scroll only when title overflows
  // its wrap. Otherwise the CSS ellipsis fallback applies. Used on both
  // the desktop #title and the mobile #title-mobile (cross-device
  // coherence: short titles stay still, long titles scroll). Called
  // from preparePlayer() with a setTimeout so the DOM has updated.
  function detectAndApplyMarquee($titleElement) {
    if (!$titleElement.length) return;
    var el = $titleElement[0];
    if (el.scrollWidth > el.clientWidth) {
      $titleElement.addClass('lmt-player-title-scroll');
    } else {
      $titleElement.removeClass('lmt-player-title-scroll');
    }
  }

  // Typographic MINUS SIGN (U+2212) — wider and properly spaced
  // versus the ASCII hyphen-minus. Used in the countdown prefix to
  // avoid the "-5:53" cramped look on mobile. Also used for the
  // initial value reset in preparePlayer().
  var MINUS_SIGN = '−';

  // Mobile player redesign — countdown −X:XX. Updated by
  // updateTimeDisplay() (already ticking every 500ms via startTimer
  // for both YouTube and MP3 sources). Falls back to −0:00 when
  // duration is unknown (loadedmetadata not yet fired) or invalid.
  function updateCountdown() {
    var remaining = 0;
    if (currentType === 'youtube' && youtubePlayer && typeof youtubePlayer.getCurrentTime === 'function' && typeof youtubePlayer.getDuration === 'function') {
      var ytCur = youtubePlayer.getCurrentTime();
      var ytDur = youtubePlayer.getDuration();
      if (ytDur > 0 && !isNaN(ytDur) && ytDur !== Infinity) {
        remaining = Math.max(0, ytDur - ytCur);
      }
    } else if (currentType === 'mp3' && player) {
      var mpDur = player.duration;
      var mpCur = player.currentTime;
      if (mpDur > 0 && !isNaN(mpDur) && mpDur !== Infinity) {
        remaining = Math.max(0, mpDur - mpCur);
      }
    }
    var minutes = Math.floor(remaining / 60);
    var seconds = Math.floor(remaining % 60);
    $playerCountdown.text(MINUS_SIGN + minutes + ':' + (seconds < 10 ? '0' : '') + seconds);
  }

  // Phase 5 A11Y-011: build a screen-reader-friendly seekbar value
  // text. Without this, AT announces "Seek, 0 of 100" — useless. With
  // it, AT announces "Track progress, 01:23 of 03:45 — <track title>".
  function updateSeekbarAria(cur, dur) {
    var curStr = formatTime(cur);
    var durStr = formatTime(dur);
    var titlePart = trackTitle ? ' — ' + trackTitle : '';
    $seekbar.attr('aria-valuetext', curStr + ' of ' + durStr + titlePart);
  }

  function updateTimeDisplay() {
    if (currentType === 'youtube' && youtubePlayer && youtubePlayer.getCurrentTime) {
      var cur = youtubePlayer.getCurrentTime();
      var dur = youtubePlayer.getDuration();
      $timeDiv.text(formatTime(cur));
      if (dur > 0 && !isNaN(dur) && dur !== Infinity) {
        $durationDiv.text(formatTime(dur));
        $seekbar.attr("max", dur);
      }
      $seekbar.val(cur);
      updateSeekbarAria(cur, dur);
    } else if (currentType === 'mp3' && player) {
      var cur = player.currentTime;
      var dur = player.duration;
      $timeDiv.text(formatTime(cur));
      if (dur > 0 && !isNaN(dur) && dur !== Infinity) {
        $durationDiv.text(formatTime(dur));
        $seekbar.attr("max", dur);
      }
      $seekbar.val(cur);
      updateSeekbarAria(cur, dur);
    }
    updateCountdown();
  }

  function playNextSong() {
    if (currentTrack >= playlistItems.length - 1) {
      currentTrack = 0;
    } else {
      currentTrack++;
    }
    preparePlayer($(playlistItems[currentTrack]));
  }

  // Auto-play feature — when the last track of the mixtape ends
  // (naturally) or errors out (defensive : last-track failure still
  // signals end-of-mixtape from the user's standpoint), trigger the
  // autoplay countdown toast instead of looping back to track 0.
  // Returns true if autoplay was triggered (caller must skip the
  // legacy wrap-around playNextSong()), false otherwise.
  function triggerAutoplayIfLastTrack() {
    if (currentTrack !== playlistItems.length - 1) {
      return false;
    }
    if (typeof window.lmtAutoplayInit === 'function') {
      window.lmtAutoplayInit();
      return true;
    }
    return false;
  }

  function preparePlayer($item, options) {
    var skipAutoPlay = !!(options && options.skipAutoPlay);
    stopTimer();
    playlistItems.find('a').removeClass('playing');
    $item.find('a').addClass('playing');
    trackUrl = $item.find('a').data('src');
    trackTitle = $item.find('a').text();
    $titleDiv.text(trackTitle);
    $titleMobile.text(trackTitle);

    // Phase 11.1 — Discogs button replaced by the multi-service
    // "Listen on" dropdown. Search URLs are now built lazily via
    // updateListenOnLinks() at toggle time (handler at the bottom
    // of this closure) so each open reflects the current track.

    // Mobile player redesign — reset countdown to −0:00 (typographic
    // MINUS SIGN U+2212) until loadedmetadata fires and
    // updateTimeDisplay() recomputes it.
    $playerCountdown.text(MINUS_SIGN + '0:00');

    // Mobile player redesign — overflow-detection marquee. setTimeout
    // 50ms lets the layout settle (the title text was just inserted
    // and scrollWidth/clientWidth would be stale on the same tick).
    setTimeout(function () {
      detectAndApplyMarquee($titleDiv);
      detectAndApplyMarquee($titleMobile);
    }, 50);

    // Phase Tracking v1 — reset play_start flag for the new track.
    playStartTrackedForCurrentTrack = false;
    if (!trackUrl) {
      return;
    }
    setPlayerSource(skipAutoPlay);
  }

  function setPlayerSource(skipAutoPlay) {
    if (!trackUrl) {
      return;
    }
    // Clean up current player before switching
    if (currentType === 'youtube' && youtubePlayer && typeof youtubePlayer.pauseVideo === 'function') {
      youtubePlayer.pauseVideo();
    } else if (currentType === 'mp3' && player) {
      player.pause();
    }
    stopTimer();

    // Determine the type of track and play accordingly
    if (trackUrl.indexOf("youtube.com") > -1 || trackUrl.indexOf("youtu.be") > -1) {
      currentType = 'youtube';
      var videoId = extractYouTubeId(trackUrl);
      if (videoId) {
        setYouTubeThumbnail(videoId);
        if (youtubeReady && youtubePlayer && typeof youtubePlayer.loadVideoById === 'function') {
          if (skipAutoPlay) {
            youtubePlayer.cueVideoById(videoId);
          } else {
            youtubePlayer.loadVideoById(videoId);
            youtubePlayer.playVideo();
          }
        } else {
          // Only queue the action, do NOT skip to next song
          pendingYouTubeAction = function() {
            if (youtubePlayer && typeof youtubePlayer.loadVideoById === 'function') {
              if (skipAutoPlay) {
                youtubePlayer.cueVideoById(videoId);
              } else {
                youtubePlayer.loadVideoById(videoId);
                youtubePlayer.playVideo();
              }
            }
          };
        }
      } else {
        // If videoId is not valid, skip
        playNextSong();
      }
    } else if (trackUrl.match(/\.mp3$|\.mp4$/i)) {
      currentType = 'mp3';
      if (player) {
        player.setSrc([{ src: trackUrl }]);
        player.load();
        if (!skipAutoPlay) {
          player.play();
        }
      }
      $("#yt-thumb-link").hide();
      $("#yt-thumb").hide();
    } else {
      // Only skip if truly unsupported
      playNextSong();
    }
  }

  function extractYouTubeId(url) {
    var regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
    var match = url.match(regExp);
    return (match && match[2].length === 11) ? match[2] : null;
  }

  function startTimer() {
    stopTimer();
    timerInterval = setInterval(updateTimeDisplay, 500);
  }

  function stopTimer() {
    if (timerInterval) clearInterval(timerInterval);
  }

  function setYouTubeThumbnail(videoId) {
    var thumbUrl = "https://img.youtube.com/vi/" + videoId + "/default.jpg";
    var ytUrl = "https://www.youtube.com/watch?v=" + videoId;
    $("#yt-thumb").attr("src", thumbUrl);
    $("#yt-thumb-link").attr("href", ytUrl).show();
    $("#yt-thumb").show();
  }

  // Helpers for PJAX phase 3.2 — re-query stale jQuery refs after a
  // <main> swap, and re-apply .playing highlight on the current
  // track when returning to the currently-playing mixtape (Q-D1).
  function refreshPlaylistRefs() {
    $playlist = $("#playlist");
    playlistItems = $("#playlist li");
    markInvalidTrackUrls();
  }

  // PJAX phase 3.6 — read the post_id from body class (`postid-X`,
  // set by WP body_class()). lmt-pjax updates document.body.className
  // on swap, so this returns the displayed page's post_id at call
  // time. Used to keep window.lmtPlayerCurrentId in sync with the
  // mixtape loaded into the player on initial load + Q-C1 + Q-A1.
  function getPostIdFromBodyClass() {
    var match = document.body.className.match(/postid-(\d+)/);
    return match ? parseInt(match[1], 10) : null;
  }

  // Phase 8.3 — extract the mixtape title from document.title.
  // lmt-pjax (Phase 4.1) updates document.title from the swapped
  // page's <title>, so this returns the current page's title at
  // call time. Defensive regex strips common Rank Math suffixes
  // (' — Lamixtape', ' - Lamixtape', ' | Lamixtape') ; if no match,
  // returns the full title rather than empty (graceful fallback).
  function getCurrentPageTitle() {
    var t = document.title || '';
    return t.replace(/\s+[—\-|]\s+Lamixtape\s*$/i, '').trim() || t.trim();
  }

  // Phase 8.3 — read the ACF mixtape color from <article class=
  // "mixtape" data-color="#rrggbb"> in the swapped <main>. lmt-pjax
  // swaps <main> on every PJAX nav, so this returns the displayed
  // single's color at call time. Defensive regex validates #RRGGBB
  // format ; returns null on invalid / missing / non-single page.
  function getCurrentMixtapeColor() {
    var article = document.querySelector('article.mixtape');
    if (!article) { return null; }
    var color = article.dataset.color;
    return (color && /^#[0-9a-f]{6}$/i.test(color)) ? color : null;
  }

  // Phase 8.3 — sync the omniscient player's mixtape link DOM with
  // window.lmtPlayerCurrentTitle / Url / Color. Updates both desktop
  // and mobile <a> together (idiomatic of the #title / #title-mobile
  // dual-markup pattern). Hides both when no mixtape is loaded
  // (initial state on home, etc.). Color dot is hidden via
  // background-color cleared when invalid.
  function updatePlayerMixtapeDisplay() {
    var $links = $('#lmt-current-mixtape-link, #lmt-current-mixtape-link-mobile');
    if (!$links.length) { return; }
    if (!window.lmtPlayerCurrentTitle || !window.lmtPlayerCurrentUrl) {
      $links.hide();
      return;
    }
    $links.attr('href', window.lmtPlayerCurrentUrl);
    $('#lmt-current-mixtape-name, #lmt-current-mixtape-name-mobile')
      .text(window.lmtPlayerCurrentTitle);
    var $dots = $('#lmt-current-mixtape-color, #lmt-current-mixtape-color-mobile');
    if (window.lmtPlayerCurrentColor) {
      $dots.css({ 'background-color': window.lmtPlayerCurrentColor, 'display': '' });
    } else {
      $dots.hide();
    }
    $links.css('display', '');
  }

  function highlightCurrentTrack() {
    playlistItems.find('a').removeClass('playing');
    if (playlistItems.length > currentTrack) {
      $(playlistItems[currentTrack]).find('a').addClass('playing');
    }
  }

  // Playlist item click — delegated on document with namespace
  // 'click.lmt-tracklist' so it survives PJAX <main> swaps (the
  // document persists across navigations). Detects cross-mixtape
  // clicks via URL pathname (Q-C1) and switches the player to the
  // displayed mixtape's track when needed.
  $(document)
    .off('click.lmt-tracklist')
    .on('click.lmt-tracklist', '#playlist li', function (e) {
      e.preventDefault();
      e.stopImmediatePropagation();
      // Skip pre-marked unavailable tracks (legacy http://0 URLs
      // and runtime onError-failed tracks) — preventing
      // preparePlayer() from churning currentTrack/currentType
      // for a known dead URL.
      if ($(this).hasClass('lmt-track-unavailable')) return;
      var pageSlug = window.lmtGetMixtapeSlug();
      var clickedIndex = $(this).index();
      if (pageSlug !== window.lmtPlayerCurrentSlug) {
        // Q-C1: user clicked a track of a different mixtape →
        // switch the player to it (refresh playlist refs to point
        // at the new <ul#playlist>). Phase 3.6: also sync currentId
        // so autoplay's getCurrentMixtapeId() points to the new
        // mixtape when its last track ends. Phase 8.3: sync
        // currentTitle + currentUrl so the omniscient player link
        // tracks the new mixtape.
        window.lmtPlayerCurrentSlug = pageSlug;
        window.lmtPlayerCurrentId = getPostIdFromBodyClass();
        window.lmtPlayerCurrentTitle = getCurrentPageTitle();
        window.lmtPlayerCurrentUrl = window.location.href;
        window.lmtPlayerCurrentColor = getCurrentMixtapeColor();
        updatePlayerMixtapeDisplay();
        refreshPlaylistRefs();
      }
      currentTrack = clickedIndex;
      preparePlayer($(this));
    });

  // Play/Pause button event
  $playPauseBtn.on("click", function (e) {
    e.preventDefault();
    if (currentType === 'youtube' && youtubePlayer && typeof youtubePlayer.getPlayerState === 'function') {
      if (youtubePlayer.getPlayerState() === YT.PlayerState.PLAYING && typeof youtubePlayer.pauseVideo === 'function') {
        youtubePlayer.pauseVideo();
      } else if (youtubeReady && typeof youtubePlayer.playVideo === 'function') {
        youtubePlayer.playVideo();
      } else if (!youtubeReady) {
        pendingYouTubeAction = function() {
          if (youtubePlayer && typeof youtubePlayer.playVideo === 'function') {
            youtubePlayer.playVideo();
          }
        };
      }
    } else if (currentType === 'mp3' && player) {
      if (player.paused) {
        player.play();
      } else {
        player.pause();
      }
    }
  });

  // Seekbar events
  $seekbar.on("mousedown", function () {
    isSeeking = true;
  });
  $seekbar.on("input", function () {
    if (isSeeking) {
      var seekTime = parseFloat($seekbar.val());
      if (currentType === 'youtube' && youtubePlayer && youtubePlayer.seekTo) {
        youtubePlayer.seekTo(seekTime, true);
      } else if (currentType === 'mp3' && player && player.duration > 0) {
        player.setCurrentTime(seekTime);
      }
      updateTimeDisplay();
    }
  });
  $seekbar.on("mouseup", function () {
    isSeeking = false;
  });

  // Discogs standalone button retired in Phase 11.1 ; replaced by
  // the multi-service "Listen on" dropdown. Discogs is one of the
  // five entries in the dropdown, alongside Spotify, Apple Music,
  // YouTube and Deezer. Search URLs are built lazily by
  // updateListenOnLinks() at toggle time (handler at the bottom
  // of this closure).

  // Pre-validate URLs once at initial load — PJAX swaps re-run
  // this via refreshPlaylistRefs().
  markInvalidTrackUrls();

  // Start with the first track in the playlist (initial single
  // load only — PJAX arrival on a single is handled in the
  // lmt:pjax:swapped listener below).
  if (playlistItems.length > 0) {
    preparePlayer($(playlistItems[0]));
    window.lmtPlayerCurrentSlug = window.lmtGetMixtapeSlug();
    // PJAX phase 3.6 — sync currentId on initial single load. Prefer
    // wp_localize_script's lmtData.post_id (canonical, set server-side
    // by get_queried_object_id() at initial render); fall back to body
    // class only if lmtData.post_id is unavailable.
    window.lmtPlayerCurrentId = (typeof lmtData !== 'undefined' && lmtData.post_id)
      ? parseInt(lmtData.post_id, 10) || null
      : getPostIdFromBodyClass();
    // Phase 8.3 — sync currentTitle + currentUrl + currentColor from
    // lmtData on initial single load (canonical, set server-side via
    // wp_localize_script with get_the_title() + get_permalink() +
    // ACF get_field('color')).
    if (typeof lmtData !== 'undefined' && lmtData.mixtape_title && lmtData.mixtape_url) {
      window.lmtPlayerCurrentTitle = lmtData.mixtape_title;
      window.lmtPlayerCurrentUrl = lmtData.mixtape_url;
      window.lmtPlayerCurrentColor = lmtData.mixtape_color || null;
      updatePlayerMixtapeDisplay();
    }

    // Reveal player slide-up animation. Moved here from main.js
    // post-A2 refactor (Phase 1 PJAX) so the .visible class is
    // only added when player.js actually has a tracklist to play.
    // On non-single pages (Phase 3.2: player.js loads site-wide)
    // the markup exists but stays hidden via the .player-slide-up
    // base CSS rule.
    setTimeout(function () {
      $('#footer-player').addClass('visible');
    }, 300);
  }

  // PJAX phase 3.2 — re-bind tracklist + state sync after a
  // <main> swap. Three cases (cf. _docs/p5.md decisions A1/B1/D1):
  //   * lmtPlayerCurrentSlug === null (Q-A1): first PJAX arrival
  //     on a single, never played anything → preload track 0
  //     without auto-playing (cueVideoById / no .play()).
  //   * Same mixtape (Q-D1): refresh playlist refs to the new
  //     <ul#playlist> and re-apply .playing on currentTrack.
  //   * Different mixtape with playback active (Q-B1): no-op —
  //     playback continues on current mixtape, the new tracklist
  //     is displayed without highlight. Cornerstone of the
  //     omniscient player UX.
  document.addEventListener('lmt:pjax:swapped', function () {
    if (!document.body.classList.contains('single')) return;
    if (!$('#playlist').length) return;
    var newSlug = window.lmtGetMixtapeSlug();
    if (window.lmtPlayerCurrentSlug === null) {
      // Q-A1
      window.lmtPlayerCurrentSlug = newSlug;
      // Phase 3.6 — first arrival on single, sync currentId from
      // body class (lmt-pjax updates document.body.className on swap).
      window.lmtPlayerCurrentId = getPostIdFromBodyClass();
      // Phase 8.3 — sync currentTitle + currentUrl + currentColor from
      // the new page (document.title updated by lmt-pjax Phase 4.1,
      // location updated by history.pushState, color read from
      // article.mixtape data-color attribute in the swapped <main>).
      window.lmtPlayerCurrentTitle = getCurrentPageTitle();
      window.lmtPlayerCurrentUrl = window.location.href;
      window.lmtPlayerCurrentColor = getCurrentMixtapeColor();
      updatePlayerMixtapeDisplay();
      refreshPlaylistRefs();
      currentTrack = 0;
      if (playlistItems.length > 0) {
        preparePlayer($(playlistItems[0]), { skipAutoPlay: true });
        $('#footer-player').addClass('visible');
      }
    } else if (newSlug === window.lmtPlayerCurrentSlug) {
      // Q-D1
      refreshPlaylistRefs();
      highlightCurrentTrack();
    }
    // Else (Q-B1): different mixtape, current playback continues.
    // playlistItems intentionally left pointing at the previous
    // (now-detached) DOM so playNextSong() keeps walking the
    // currently playing mixtape's tracks.
  });

  // Phase 10.1 — Keyboard controls.
  //
  // Space        → toggle play/pause (reuses existing
  //                $playPauseBtn click handler, preserves YouTube
  //                readyness fallback + pendingYouTubeAction logic)
  // ArrowRight   → next track (or autoplay end-of-mixtape if last
  //                track, leveraging Phase 3.6 autoplay)
  // ArrowLeft    → restart current track (idempotent if at 0)
  //
  // Skips :
  //   - Modifier keys pressed (Cmd/Ctrl/Alt) — preserve browser
  //     shortcuts (Cmd+Space spotlight, Ctrl+Right history, etc.)
  //   - Focus on input/textarea/select/contenteditable — preserve
  //     text input UX (form contact, future search inputs)
  //   - currentType === null — page sans player (404, fresh visit
  //     without playback, PJAX Q-A1 first arrival before track load)
  //
  // Listener attached on document inside this jQuery closure runs
  // once at DOMContentLoaded. document persists across PJAX swaps,
  // so the listener stays bound naturally without any re-init.
  document.addEventListener('keydown', function (event) {
    if (event.metaKey || event.ctrlKey || event.altKey) return;

    var target = document.activeElement;
    if (target) {
      var tag = target.tagName;
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
      if (target.isContentEditable) return;
      // Phase 11.1 — let Space open the Listen on dropdown via
      // its native button click, not toggle play/pause.
      if (target.classList && target.classList.contains('lmt-listen-on-trigger')) return;
    }

    if (currentType === null) return;

    if (event.key === ' ' || event.key === 'Spacebar') {
      event.preventDefault();
      $playPauseBtn.trigger('click');
    } else if (event.key === 'ArrowRight') {
      event.preventDefault();
      if (!triggerAutoplayIfLastTrack()) {
        playNextSong();
      }
    } else if (event.key === 'ArrowLeft') {
      event.preventDefault();
      if (currentType === 'youtube' && youtubePlayer && typeof youtubePlayer.seekTo === 'function') {
        youtubePlayer.seekTo(0, true);
      } else if (currentType === 'mp3' && player) {
        player.setCurrentTime(0);
      }
    }
  });

  // Phase 11.1 — "Listen on" dropdown handlers (replaces the
  // standalone Discogs button). The two markup instances (desktop
  // col 3 and mobile line 1) are both rendered by
  // lmt_render_listen_on() in inc/listen-on.php and share the
  // same .lmt-listen-on-* class hooks, so a single set of
  // delegated handlers drives both.
  //
  // href attributes on the entries are intentionally rendered
  // empty server-side ; updateListenOnLinks() rewrites them at
  // toggle time so each open reflects the track currently loaded
  // in the player (closure var trackTitle, kept fresh across PJAX
  // by preparePlayer()).
  var LISTEN_ON_URLS = {
    spotify:     'https://open.spotify.com/search/{query}',
    apple_music: 'https://music.apple.com/search?term={query}',
    youtube:     'https://www.youtube.com/results?search_query={query}',
    deezer:      'https://www.deezer.com/search/{query}',
    discogs:     'https://www.discogs.com/search/?q={query}&type=all'
  };
  var LISTEN_ON_FALLBACK = {
    spotify:     'https://open.spotify.com/search',
    apple_music: 'https://music.apple.com/search',
    youtube:     'https://www.youtube.com',
    deezer:      'https://www.deezer.com/search',
    discogs:     'https://www.discogs.com/search/'
  };

  function buildListenOnUrl(service, title) {
    if (!LISTEN_ON_URLS[service]) return null;
    var trimmed = (title || '').trim().replace(/\s+/g, ' ');
    if (!trimmed) {
      return LISTEN_ON_FALLBACK[service] || '#';
    }
    return LISTEN_ON_URLS[service].replace('{query}', encodeURIComponent(trimmed));
  }

  function updateListenOnLinks() {
    var links = document.querySelectorAll('.lmt-listen-on-dropdown a[data-service]');
    for (var i = 0; i < links.length; i++) {
      var service = links[i].getAttribute('data-service');
      var url = buildListenOnUrl(service, trackTitle);
      if (url) links[i].setAttribute('href', url);
    }
  }

  function closeAllListenDropdowns() {
    $('.lmt-listen-on-dropdown').prop('hidden', true);
    $('.lmt-listen-on-trigger').attr('aria-expanded', 'false');
  }

  // Click trigger → toggle (open + lazy update links).
  $(document).on('click', '.lmt-listen-on-trigger', function (e) {
    e.preventDefault();
    e.stopPropagation();
    var $trigger = $(this);
    var $wrap = $trigger.closest('.lmt-listen-on-wrap');
    var $dropdown = $wrap.find('.lmt-listen-on-dropdown');
    var willOpen = $dropdown.prop('hidden');
    closeAllListenDropdowns();
    if (willOpen) {
      updateListenOnLinks();
      $dropdown.prop('hidden', false);
      $trigger.attr('aria-expanded', 'true');
    }
  });

  // Click outside any wrap → close.
  $(document).on('click', function (e) {
    if (!$(e.target).closest('.lmt-listen-on-wrap').length) {
      closeAllListenDropdowns();
    }
  });

  // Escape → close.
  $(document).on('keydown', function (e) {
    if (e.key === 'Escape' || e.keyCode === 27) {
      closeAllListenDropdowns();
    }
  });

  // Click a service entry → close (the link itself opens in a
  // new tab via target=_blank).
  $(document).on('click', '.lmt-listen-on-dropdown a[data-service]', function () {
    closeAllListenDropdowns();
  });
});
