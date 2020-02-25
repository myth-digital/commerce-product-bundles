(function($) {

    $("#addCategory").click(function() {

        var idx = $("#categorySelectTable tbody tr").length - 1;

        // Load the partial and inject into the DOM
        $.get('/admin/bundles/partials/category/' + idx, function(data) {
            $('#categorySelectTable tr:last').before(data);

            new Craft.BaseElementSelectInput({"id":"categories" + idx,"name":"categories[" + idx + "]","elementType":"craft\\elements\\Category","sources":null,"criteria":null,"sourceElementId":null,"viewMode":"list","limit":1,"showSiteMenu":false,"modalStorageKey":null,"fieldId":null,"sortable":true,"prevalidate":false});

        });        

    })
    

})(jQuery);
