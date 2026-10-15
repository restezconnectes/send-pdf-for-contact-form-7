<div class="notice sendpdf-message">
	<p><?php echo wp_kses_post( __( '<strong>Welcome to Send PDF for Contact Form 7</strong> - Please read <a href="https://restezconnectes.fr/tutoriel-wordpress-lextension-send-pdf-for-contact-form-7/">tutorial</a> for more information. Leave a message on the <a href="https://wordpress.org/support/plugin/send-pdf-for-contact-form-7/">WordPress support page</a> if you have a request or an idea for improvement.', 'send-pdf-for-contact-form-7' ) ); ?></p>

	<p class="submit">
		<a href="/wp-admin/admin.php?page=wpcf7-send-pdf" class="button-primary">
			<?php echo esc_html__( 'Create your first PDF', 'send-pdf-for-contact-form-7' ); ?>
		</a>
		<a href="https://restezconnectes.fr/tutoriel-wordpress-lextension-send-pdf-for-contact-form-7/" onfocus="this.blur();" class="button-primary">
			<?php echo esc_html__( 'Tutorial here', 'send-pdf-for-contact-form-7' ); ?>
		</a>
		<a href="https://github.com/Florent73/send-pdf-for-contact-form-7/" onfocus="this.blur();" class="button-primary">
			<?php echo esc_html__( 'GitHub version', 'send-pdf-for-contact-form-7' ); ?>
		</a>
		<a href="https://paypal.me/RestezConnectes/10" onfocus="this.blur();" target="_blank" class="button-primary">
			<?php echo esc_html__( 'Donate now !', 'send-pdf-for-contact-form-7' ); ?>
		</a>
		<a class="button-secondary skip" onfocus="this.blur();" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wpcf7pdf-hide-notice', 'welcome' ), 'wpcf7pdf_hide_notices_nonce', '_wpcf7pdf_notice_nonce' ) ); ?>">
			<?php echo esc_html__( 'Hide', 'send-pdf-for-contact-form-7' ); ?>
		</a>
	</p>
</div><!-- /.notice -->
