jQuery(document).on('click', '.delete-record', function () {
    //var id = this.id;
    var el = this;
    var id = jQuery(this).data('id');
    var idform = jQuery(this).data('idform');
    var nonce = jQuery(this).data('nonce');
    var message = jQuery(this).data('message');
    
    if(confirm(message)) {
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {"action": "wpcf7pdf_js_action", "element_id": id, "form_id":idform, "nonce": nonce},
            success: function (data) {
                //run stuff on success here.  You can use `data` var in the 
               //return so you could post a message.
                 // Remove row from HTML Table
                    jQuery(el).closest('tr').css('background','tomato');
                    jQuery(el).closest('tr').fadeOut(1800,function(){
                        jQuery(this).remove();
                    });
                
            }
        });
        return false;
    }
});
