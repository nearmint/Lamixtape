<!-- Contrôles personnalisés -->
  <div id="footer-player" class="player-slide-up">
	  <div class="container mx-auto px-4">
	  	<div class="flex flex-wrap footer-player-container items-center">

	  		<!-- MOBILE ONLY: Line 1 (Listen on dropdown + track title) -->
	  		<div class="w-full flex items-center gap-2 md:hidden lmt-player-info-mobile">
	  			<?php lmt_render_listen_on( 'mobile' ); ?>
	  			<div class="lmt-player-title-wrap flex-1 overflow-hidden">
	  				<span id="title-mobile" class="lmt-player-title"></span>
	  			</div>
	  		</div>

	  		<!-- MOBILE ONLY: Line 2 (mixtape color dot + name link, Phase 8.3) -->
	  		<div class="w-full md:hidden lmt-player-mixtape-line">
	  			<a id="lmt-current-mixtape-link-mobile" href="" class="lmt-mixtape-link no--border" style="display:none;">
	  				<span id="lmt-current-mixtape-color-mobile" class="lmt-mixtape-color" aria-hidden="true"></span>
	  				<span id="lmt-current-mixtape-name-mobile" class="lmt-mixtape-name"></span>
	  			</a>
	  		</div>

	  		<!-- DESKTOP ONLY: Col 1 (thumbnail + title + mixtape link) -->
	  		<div class="w-1/4 px-4 hidden md:block">
	  			<div class="flex items-center gap-[10px]">
	  				<a id="yt-thumb-link" href="#" target="_blank" rel="noopener" style="display:none;" class="no--border">
            <img id="yt-thumb" src="" alt="Track thumbnail" style="width:40px;height:40px;object-fit:cover;border-radius:4px;" loading="lazy" decoding="async">
          </a>
	  				<div class="flex flex-col overflow-hidden">
	  					<div id="title" style="white-space:nowrap;overflow:hidden;"></div>
	  					<a id="lmt-current-mixtape-link" href="" class="lmt-mixtape-link no--border" style="display:none;">
	  						<span id="lmt-current-mixtape-color" class="lmt-mixtape-color" aria-hidden="true"></span>
	  						<span id="lmt-current-mixtape-name" class="lmt-mixtape-name"></span>
	  					</a>
	  				</div>
	  			</div>
	  		</div>

	  		<!-- ALWAYS: Controls (play/pause + seekbar + time/duration desktop / countdown mobile) -->
	  		<div class="flex-1 px-4 flex flex-row items-center lmt-player-controls">
	  			<button id="play-pause" class="bg-transparent border-0 mr-2 cursor-pointer p-0" aria-label="Play/Pause">
            <svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor"
           class="bi bi-play-fill" viewBox="0 0 16 16" style="margin-bottom: 5px;">
        <path d="m11.596 8.697-6.363 3.692c-.54.313-1.233-.066-1.233-.697V4.308
                 c0-.63.692-1.01 1.233-.696l6.363 3.692a.802.802 0 0 1 0 1.393"/>
      </svg>
          </button>
	  			<div id="time" class="mr-2 hidden md:block" aria-live="polite">00:00</div>
	              <input type="range" id="seekbar" min="0" max="100" value="0" step="0.1" aria-label="Track progress" aria-valuetext="00:00 of 00:00">
	              <div id="duration" class="ml-2 hidden md:block" aria-live="polite"></div>
	              <span id="player-countdown" class="ml-3 md:hidden lmt-player-countdown" aria-live="off">&minus;0:00</span>
	  		</div>

	  		<!-- DESKTOP ONLY: Col 3 (Listen on dropdown) -->
	  		<div class="w-1/6 px-4 hidden md:flex md:justify-end">
	  			<?php lmt_render_listen_on( 'desktop' ); ?>
	  		</div>
	  	</div>
	  </div>
  </div>
