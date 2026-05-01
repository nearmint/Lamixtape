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
  var $discogsSearchBtn = $("#discogs-search");
  var $debugDiv = $("#debug");
  var $playlist = $("#playlist");
  var $audioPlayer = $("#audioPlayer");
  var $youtubePlayer = $("#youtubePlayer");
  var currentType = null;

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
    playNextSong();
  }

  function formatTime(seconds) {
    if (isNaN(seconds) || seconds === undefined || seconds === null || seconds === Infinity) return '00:00';
    seconds = Math.max(0, seconds);
    var mins = Math.floor(seconds / 60);
    var secs = Math.floor(seconds % 60);
    return (mins < 10 ? "0" : "") + mins + ":" + (secs < 10 ? "0" : "") + secs;
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
  }

  function playNextSong() {
    if (currentTrack >= playlistItems.length - 1) {
      currentTrack = 0;
    } else {
      currentTrack++;
    }
    preparePlayer($(playlistItems[currentTrack]));
  }

  function preparePlayer($item) {
    stopTimer();
    playlistItems.find('a').removeClass('playing');
    $item.find('a').addClass('playing');
    trackUrl = $item.find('a').data('src');
    trackTitle = $item.find('a').text();
    $titleDiv.text(trackTitle);
    $discogsSearchBtn.show();
    // Phase Tracking v1 — reset play_start flag for the new track.
    playStartTrackedForCurrentTrack = false;
    if (!trackUrl) {
      return;
    }
    setPlayerSource();
  }

  function setPlayerSource() {
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
          youtubePlayer.loadVideoById(videoId);
          youtubePlayer.playVideo();
        } else {
          // Only queue the action, do NOT skip to next song
          pendingYouTubeAction = function() {
            if (youtubePlayer && typeof youtubePlayer.loadVideoById === 'function') {
              youtubePlayer.loadVideoById(videoId);
              youtubePlayer.playVideo();
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
        player.play();
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

  // Playlist item click event
  $playlist.on("click", "li", function (e) {
    e.preventDefault();
    e.stopImmediatePropagation();
    currentTrack = $(this).index();
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

  // Discogs Search Button event
  $discogsSearchBtn.on("click", function (e) {
    e.preventDefault();
    var searchQuery = trackTitle.trim().replace(/\s+/g, ' ');
    var encodedQuery = encodeURIComponent(searchQuery);
    var discogsUrl = `https://www.discogs.com/search/?q=${encodedQuery}&type=all`;
    window.open(discogsUrl, '_blank');
  });

  // Start with the first track in the playlist
  preparePlayer($(playlistItems[0]));
});
