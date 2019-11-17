jQuery(document).on('click', '.delete-record', function () {
    //var id = this.id;
    var el = this;
    var id = $(this).data('id');
    var nonce = $(this).data('nonce');
    var message = $(this).data('message');
    
    if(confirm(message)) {
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {"action": "wpcf7pdf_js_action", "element_id": id, "nonce": nonce},
            success: function (data) {
                //run stuff on success here.  You can use `data` var in the 
               //return so you could post a message.
                 // Remove row from HTML Table
                    $(el).closest('tr').css('background','tomato');
                    $(el).closest('tr').fadeOut(1800,function(){
                        $(this).remove();
                    });
                
            }
        });
        return false;
    }
});