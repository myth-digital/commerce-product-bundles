(function($) {

    $("#addCategory").click(function() {

        var idx = $("#categorySelectTable tbody tr").length - 1;

        // Load the partial and inject into the DOM
        $.get('/admin/bundles/partials/category/' + idx, function(data) {
            $('#categorySelectTable tr:last').before(data);

            new Craft.BaseElementSelectInput({"id":"categories" + idx,"name":"categories[" + idx + "]","elementType":"craft\\elements\\Category","sources":null,"criteria":null,"sourceElementId":null,"viewMode":"list","limit":null,"showSiteMenu":false,"modalStorageKey":null,"fieldId":null,"sortable":true,"prevalidate":false});

        });        

    });

    $("#addPurchasable").click(function() {

        var idx = $("#purchasableSelectTable tbody tr").length - 1;

        // Load the partial and inject into the DOM
        $.get('/admin/bundles/partials/purchasable/' + idx, function(data) {
            $('#purchasableSelectTable tr:last').before(data);

            new Craft.BaseElementSelectInput({"id":"purchasables" + idx,"name":"purchasables[" + idx + "]","elementType":"craft\\commerce\\elements\\Product","sources":null,"criteria":null,"sourceElementId":null,"viewMode":"list","limit":null,"showSiteMenu":false,"modalStorageKey":null,"fieldId":null,"sortable":true,"prevalidate":false});

        });        

    });    
    

})(jQuery);
