$(document).ready(function() {
    $(document).ajaxStart(function(){
        $("#overlay").css("display", "block");
    });
    $(document).ajaxComplete(function(){
        $("#overlay").css("display", "none");
    });
});