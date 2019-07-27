function collectTableData(table, count){
    var data = [];
    var keys = [];

    $('th',table).each(function(i){
        if ( i < count ){
            var str = $(this).text();
            keys.push(str.toLowerCase());
        }
    });
    $('tr',table).each(function(i){
        var row = {};
        $(this).children('td').each(function(j){
            if ( j < count ){
                row[keys[j]] = $(this).text();
            }
        });
        data.push(row);
    });

    return data;
}
function findTables(){
    var lists = [];

    $('body table.table').each(function(i){
        var list = {};
        list['name'] = $(this).attr('id');
        if ( list['name'] != null ){
            list['rows'] = collectTableData($(this), 10);
            lists.push(list);
        }
    });

    return lists;
}
function editTableRow(row, rowData) {
    var formRow = $("<tr/>").insertAfter(row);
    $.each(rowData, function(i, c) {
        if ( i < 10 ){
            formRow.append($('<td/>').html("<input class=\"form-control text-center\" type=\"text\" value=\""+c+"\">"));
        }
    });
    formRow.append($('<td/>').html("<button type=\"button\" class=\"btn btn-success form-callback\">Change</button>"));
    formRow.append($('<td/>').html("<button type=\"button\" class=\"btn btn-danger form-callback\">Cancel</button>"));

    return formRow;
}
function addTableRow(row) {
    var formRow = $("<tr/>").insertAfter(row);
    var rowData = new Array(10);

    $.each(rowData, function(colIndex, c) {
        formRow.append($('<td/>').html("<input class=\"form-control text-center\" type=\"text\" >"));
    });
    formRow.append($('<td/>').html("<button type=\"button\" class=\"btn btn-success form-callback\">Append</button>"));
    formRow.append($('<td/>').html("<button type=\"button\" class=\"btn btn-danger form-callback\">Cancel</button>"));

    return formRow;
}
function getRow(row) {
    var data = [];
    row.find('td').each(function (colIndex, c) {
        data.push(c.textContent);
    });
    return data;
}
$( document ).ready(function() {
    $("tbody").on("click", ".form-callback", function(e) {
        e.preventDefault();
        var action = $(this).text();
        var $row = $(this).closest("tr");

        if ( action === 'Change'){
            var $prevRow = $row.prev();
            var fields = $row.find('input');

            $prevRow.find('td').each(function(index, col ) {
                if ( index < 10 ){
                    $(col).text($(fields[index]).val());
                }
            });
        }
        if ( action === 'Append'){
            var fields = $row.find('input');
            var newRow = $("<tr/>").insertBefore($row);

            $.each(fields, function(colIndex, c) {
                newRow.append($('<td/>').addClass( "align-middle text-center" ).text($(c).val()));
            });
            newRow.append($('<td/>').addClass( "align-middle text-center" ).html("<button type=\"button\" name=\"edit\" class=\"btn btn-success button-callback\"><span class=\"fas fa-pen-square fa-lg\" aria-hidden=\"true\"></span></button>"));
            newRow.append($('<td/>').addClass( "align-middle text-center" ).html("<button type=\"button\" name=\"delete\" class=\"btn btn-danger button-callback\"><span class=\"fas fa-times fa-lg\" aria-hidden=\"true\"></span></button>"));
        }
        $row.remove();
    });
    $(".table-responsive").on("click", ".button-callback", function(e) {
        e.preventDefault();
        var action = $(this).attr("name");
        var row = $(this).closest("tr");

        if ( action === 'edit'){
            var data = getRow(row);
            editTableRow(row, data);
        }
        if ( action === 'delete'){
            $(row).remove();
        }
        if ( action === 'add'){
            var tableId = $(this).val();
            var id = "#"+tableId;
            row = $('<tr/>').appendTo($(id).find('tbody:last'));
            addTableRow(row);
            $(row).remove()
        }
    });
    $("#cluster_save").click(function( e ) {
        var data = {};
        data['metricLists'] = findTables();
        var id = $('body').data('id');

        $.ajax({
            type: "PATCH",
            url: "/web/clusters/"+id,
            data: JSON.stringify(data),
            processData: false,
            contentType : 'application/json',
            dataType: 'json',
            success: function(result) {
                <!-- alert('Success!'); -->
            },
            error: function(result) {
                alert('Error!');
            }
        });
    });
});
