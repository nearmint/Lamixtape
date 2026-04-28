<!-- Contrôles personnalisés -->
  <div id="footer-player" class="player-slide-up">
	  <div class="container">
	  	<div class="row controls footer-player-container align-items-center">
	  		<div class="col-3 d-none d-sm-block">
	  			<div style="display: flex; align-items: center; gap: 10px;">
	  				<a id="yt-thumb-link" href="#" target="_blank" rel="noopener" style="display:none;" class="no--border">
            <img id="yt-thumb" src="" alt="Track thumbnail" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">
          </a>
	  				<div id="title" style="white-space:nowrap;overflow:hidden;"></div>
	  			</div>
	  		</div>
	  		<div class="col d-flex flex-row align-items-center">
	  			<button id="play-pause" class="btn btn-link mr-3" aria-label="Play/Pause">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor"
           class="bi bi-play-fill" viewBox="0 0 16 16" style="margin-bottom: 5px;">
        <path d="m11.596 8.697-6.363 3.692c-.54.313-1.233-.066-1.233-.697V4.308
                 c0-.63.692-1.01 1.233-.696l6.363 3.692a.802.802 0 0 1 0 1.393"/>
      </svg>    
          </button>
	  			<div id="time" class="mr-3 d-none d-sm-block" aria-live="polite">00:00</div>
	              <input type="range" id="seekbar" class="custom-range" min="0" max="100" value="0" step="0.1" aria-label="Seek">
	              <div id="duration" class="ml-3 d-none d-sm-block" aria-live="polite"></div>
	  		</div>
	  		<div class="col-2 d-none d-sm-block">
	  			<button id="discogs-search" class="btn btn-xs btn-outline-light btn-discogs" style="display: none;">
            Search on Discogs 
            <svg fill="#FFFFFF" xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 50 50" width="18px" height="18px"><path d="M 25 2 C 12.318 2 2 12.318 2 25 C 2 37.683 12.318 48 25 48 C 37.682 48 48 37.683 48 25 C 48 12.318 37.682 2 25 2 z M 25 4 C 28.589599 4 31.969488 4.9098317 34.927734 6.5039062 L 33.978516 8.2636719 C 31.302743 6.8224328 28.246439 6 25 6 C 14.523 6 6 14.523 6 25 C 6 30.244439 8.1347842 35.000105 11.582031 38.441406 L 10.193359 39.875 C 6.370776 36.069689 4 30.806845 4 25 C 4 13.42 13.42 4 25 4 z M 25 8 C 27.903291 8 30.635992 8.7350243 33.029297 10.023438 L 32.082031 11.78125 C 29.971356 10.645889 27.559869 10 25 10 C 16.729 10 10 16.729 10 25 C 10 29.118153 11.669152 32.853049 14.365234 35.566406 L 12.972656 37.003906 C 9.9012739 33.926592 8 29.681061 8 25 C 8 15.626 15.626 8 25 8 z M 39.560547 9.9023438 C 43.521936 13.724102 46 19.073584 46 25 C 46 36.579 36.58 46 25 46 C 21.298016 46 17.822107 45.028203 14.798828 43.339844 L 15.748047 41.580078 C 18.488796 43.11563 21.641126 44 25 44 C 35.477 44 44 35.477 44 25 C 44 19.636496 41.757861 14.793937 38.171875 11.335938 L 39.560547 9.9023438 z M 25 12 C 27.216128 12 29.303004 12.560922 31.130859 13.542969 L 29.234375 17.0625 C 27.971455 16.386085 26.529847 16 25 16 C 20.038 16 16 20.038 16 25 C 16 27.429306 16.971256 29.633178 18.541016 31.253906 L 15.757812 34.126953 C 13.437597 31.777701 12 28.554724 12 25 C 12 17.832 17.832 12 25 12 z M 36.78125 12.771484 C 39.991845 15.865724 42 20.199398 42 25 C 42 34.374 34.374 42 25 42 C 21.984318 42 19.155419 41.203116 16.697266 39.820312 L 17.646484 38.060547 C 19.821759 39.290247 22.32796 40 25 40 C 33.271 40 40 33.271 40 25 C 40 20.762819 38.227174 16.937446 35.392578 14.207031 L 36.78125 12.771484 z M 34.001953 15.644531 C 36.460607 18.011127 38 21.326193 38 25 C 38 32.168 32.168 38 25 38 C 22.671094 38 20.488346 37.375903 18.595703 36.298828 L 20.494141 32.779297 C 21.821031 33.550858 23.357825 34 25 34 C 29.962 34 34 29.963 34 25 C 34 22.452006 32.930525 20.152737 31.222656 18.513672 L 34.001953 15.644531 z M 25 18 C 28.86 18 32 21.14 32 25 C 32 28.859 28.86 32 25 32 C 21.14 32 18 28.859 18 25 C 18 21.14 21.14 18 25 18 z M 25 24 A 1 1 0 0 0 25 26 A 1 1 0 0 0 25 24 z"/></svg>
          </button>
	  		</div>
	  	</div>
	  </div>
  </div>
