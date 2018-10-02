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
            var nodeStats = data.nodeStats;
            var cols = Object.keys(nodeStats[0]);
            var columnsSource = [];
            console.log(cols);

            for (var col of cols) {
            console.log(col);
                columnsSource.push({"data" : col});
            }

            for (let plot of data.plots) {
                // console.log(plot);

                let options = plot.options;
                options['height'] = p_height;
                options['width'] = p_width;

                Plotly.newPlot(
                    plot.name,
                    plot.data,
                    options,
                    {staticPlot: true});
            }

            console.log(columnsSource);

            var tablelist =  $('#stat').DataTable(
                {
                    "columnDefs": [
                        { "visible": false, "targets": [10,11,12,13,14,15] }
                    ],
                    paging: false,
                    bFilter: false,
                    data: nodeStats,
                    columns: columnsSource,
                }
            );

            tablelist
                .draw();

        },
        error: function(result) {
            console.log("Error");
        }
    });
});

