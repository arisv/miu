$('document').ready(
    function(){
        new Clipboard('.clipbutton');
        $('#upload-legacy').hide();
        $('#upload-dropzone').show();
    }
);