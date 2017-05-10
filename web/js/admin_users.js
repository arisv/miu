$(document).ready(function(){

    var AdminControlPanel = {
        initialize: function(){
            this.obtainFileSizes();
        },
        obtainFileSizes: function(){
            var cells = $('td[data-usersize]');
            var self = this;
            $.each(cells, function(){
                var cell = this;
                var row = $(this).parent();
                var userId = $(row).data("userid");
                self.getData('/endpoint/getstoragestats/', {'id': userId}).done(
                    function(data, status, xhr){
                        $(cell).html(data.message);
                    }
                );
            });
        },
        getData: function(url, data) {
            return $.ajax({
                type: 'GET',
                url: url,
                data: data
            });
        }
    };

    AdminControlPanel.initialize();

});