<div class="notice sendpdf-message">
    <p><?php echo __('<strong>Welcome to Send PDF for Contact Form 7</strong> - Please read <a href="https://restezconnectes.fr/tutoriel-wordpress-lextension-send-pdf-for-contact-form-7/">tutorial</a> for more information. Leave a message on the <a href="https://wordpress.org/support/plugin/send-pdf-for-contact-form-7/">WordPress support page</a> if you have a request or an idea for improvement.', 'send-pdf-for-contact-form-7' ); ?></p>

    <p class="submit">
        <!--<a href="" class="button-primary">
            <?php //echo __( 'Documentation', 'send-pdf-for-contact-form-7' ); ?>
        </a>-->
        <a href="/wp-admin/admin.php?page=wpcf7-send-pdf" class="button-primary">
            <?php echo __( 'Create your fisrt PDF', 'send-pdf-for-contact-form-7' ); ?>
        </a>
        <a href="https://restezconnectes.fr/tutoriel-wordpress-lextension-send-pdf-for-contact-form-7/" onfocus="this.blur();" class="button-primary">
            <?php echo __( 'Tutorial here', 'send-pdf-for-contact-form-7' ); ?>
        </a>
        <a href="https://github.com/Florent73/send-pdf-for-contact-form-7/" onfocus="this.blur();" class="button-primary">
            <?php echo __( 'GitHub version', 'send-pdf-for-contact-form-7' ); ?>
        </a>
        <a href="https://paypal.me/RestezConnectes/10" onfocus="this.blur();" target="_blank" class="button-primary">
            <?php echo __( 'Donate now !', 'send-pdf-for-contact-form-7' ); ?>
        </a>
        <a class="button-secondary skip" onfocus="this.blur();" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wpcf7pdf-hide-notice', 'welcome' ), 'wpcf7pdf_hide_notices_nonce', '_wpcf7pdf_notice_nonce' ) ); ?>">
            <?php echo __( 'Hide', 'send-pdf-for-contact-form-7' ); ?>
        </a>
    </p>
</div><!-- /.notice -->
