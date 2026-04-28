    <!-- Donation Modal -->
    <div class="modal fade show" id="donatemodal" tabindex="-1" role="dialog" aria-labelledby="donatemodal" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <h2 class="pb-4">Support us ☕
                        <button type="button" class="close pt-1" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true"><svg class="bi bi-x" width="40" height="40" viewBox="0 0 16 16" fill="white" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" d="M11.854 4.146a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708-.708l7-7a.5.5 0 0 1 .708 0z"/>
                                <path fill-rule="evenodd" d="M4.146 4.146a.5.5 0 0 0 0 .708l7 7a.5.5 0 0 0 .708-.708l-7-7a.5.5 0 0 0-.708 0z"/>
                            </svg></span>
                        </button>
                    </h2>
                    <p>Lamixtape celebrates musical diversity with human-curated mixtapes.</p>
                    <p>If you enjoy what we do, please consider supporting us!</p>
                    <div class="donate-box">
                        <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=ADUSQ5WWUPQXQ&item_name=Lamixtape&currency_code=EUR&source=url" class="btn btn-donate mt-4 mb-3" target="_blank">Donate via PayPal</a><br>
                        <small>Not in the mood to donate? A few <a class="no--hover underline" data-toggle="modal" data-target="#contactmodal" href="#">kind words</a> or feedback go a long way.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Contact Modal -->
    <div class="modal fade show" id="contactmodal" tabindex="-1" role="dialog" aria-labelledby="contactmodal" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <h2 class="pb-4">Contact us 💌
                        <button type="button" class="close pt-1" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true"><svg class="bi bi-x" width="40" height="40" viewBox="0 0 16 16" fill="white" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" d="M11.854 4.146a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708-.708l7-7a.5.5 0 0 1 .708 0z"/>
                                <path fill-rule="evenodd" d="M4.146 4.146a.5.5 0 0 0 0 .708l7 7a.5.5 0 0 0 .708-.708l-7-7a.5.5 0 0 0-.708 0z"/>
                            </svg></span>
                        </button>
                    </h2>
                    <p class="mb-4">Your feedback helps us, we read each message and reply personally.<p>
                    <div class="form-container">
                        <?php echo do_shortcode( '[contact-form-7 id="66478de" title="Contact"]' ); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php wp_footer(); ?>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js"></script>
    <?php include "analytics.php" ?>
</body>
</html>
