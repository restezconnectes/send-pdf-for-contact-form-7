 'use strict';
 (function($){
    $(function(){
        if( $('#wp_cf7pdf_pdf').length ) {
            var editorSettings = wp.codeEditor.defaultSettings ? _.clone( wp.codeEditor.defaultSettings ) : {};
            editorSettings.codemirror = _.extend(
                {},
                editorSettings.codemirror,
                {
                    indentUnit: 2,
                    tabSize: 2,
                    mode: 'text/html',
                }
            );
            var editor = wp.codeEditor.initialize( $('#wp_cf7pdf_pdf'), editorSettings );
        }

        if( $('#wp_cf7pdf_pdf_css').length ) {
            var editorSettings = wp.codeEditor.defaultSettings ? _.clone( wp.codeEditor.defaultSettings ) : {};
            editorSettings.codemirror = _.extend(
                {},
                editorSettings.codemirror,
                {
                    indentUnit: 2,
                    tabSize: 2,
                    mode: 'text/css',
                }
            );
            var editor = wp.codeEditor.initialize( $('#wp_cf7pdf_pdf_css'), editorSettings );
        }

        if( $('#cf7pdf_html_footer').length ) {
            var editorSettings = wp.codeEditor.defaultSettings ? _.clone( wp.codeEditor.defaultSettings ) : {};
            editorSettings.codemirror = _.extend(
                {},
                editorSettings.codemirror,
                {
                    indentUnit: 2,
                    tabSize: 2,
                    mode: 'text/html',
                }
            );
            var editor = wp.codeEditor.initialize( $('#cf7pdf_html_footer'), editorSettings );
        }
    });
 })(jQuery);