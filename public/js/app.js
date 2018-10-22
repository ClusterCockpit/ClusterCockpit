var window_width = $(window).width();
var size = (window_width/3)*0.9;
var p_width = size;
var p_height = size*0.6;
var id = $("body").data("id");

$("body").append( $('<div id="CC-processing" class="clusterCockpit_processing"><h4 class="text-center mt-4">\
                              <i class="fas fa-circle-notch fa-spin fa-lg"></i> loading...\
                      </h4></div> ') );

$(document).ready( function () {
    $.ajax({
        type: "GET",
        url: "/web/jobs/"+id,
        contentType : 'application/json',
        dataType: 'json',
        success: function(data) {
            var nodeStats = data.nodeStats;
            var cols = Object.keys(nodeStats[0]);
            var columnsSource = [];
            // console.log(data);

            for (var col of cols) {
                columnsSource.push({"data" : col});
            }

            for (let plot of data.plots) {
                let options = plot.options;
                options['height'] = p_height;
                options['width'] = p_width;

                Plotly.newPlot(
                    plot.name,
                    plot.data,
                    options,
                    {staticPlot: true});
            }

            var tablelist =  $('#stat').DataTable(
                {
                    paging: false,
                    bFilter: false,
                    data: nodeStats,
                    columns: columnsSource,
                }
            );

            tablelist
                .draw();

            $('#CC-processing').remove();
        },
        error: function(result) {
            console.log("Error");
        }
    });

    $('.perfplot').click(function(e) {
        var plot = $(this).attr('id');
        Plotly.plot(plot, [], {}, { staticPlot: false });
    });
});

