var window_width = $(window).width();
var size = (window_width/3)*0.9;
var p_width = size;
var p_height = size*0.6;
var id = $("body").data("id");

$(document).ready( function () {
    $.ajax({
        type: "GET",
        url: "/web/jobs/"+id,
        contentType : 'application/json',
        dataType: 'json',
        success: function(data) {
            for (let plot of data.plots) {
                // console.log(plot);

                let options = JSON.parse(plot.options);
                options['height'] = p_height;
                options['width'] = p_width;

                Plotly.newPlot(
                    plot.name,
                    JSON.parse(plot.data),
                    options,
                    {staticPlot: true});
            }
        },
        error: function(result) {
            console.log("Error");
        }
    });
});

